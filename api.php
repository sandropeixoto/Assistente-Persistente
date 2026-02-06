<?php
ob_start();
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

set_time_limit(120);
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado', 'redirect' => 'login.php']);
    exit;
}

require 'db.php';
require 'tools.php';
$config = require 'config.php';

function ensure_default_topic($pdo, $uid) {
    $s = $pdo->prepare("SELECT id FROM topics WHERE user_id=? ORDER BY updated_at DESC, id DESC LIMIT 1");
    $s->execute([$uid]);
    $id = (int)($s->fetchColumn() ?: 0);

    if ($id <= 0) {
        $ins = $pdo->prepare("INSERT INTO topics (user_id, title) VALUES (?, 'Geral')");
        $ins->execute([$uid]);
        $id = (int)$pdo->lastInsertId();
    }

    $fix = $pdo->prepare("UPDATE messages SET topic_id=? WHERE user_id=? AND topic_id IS NULL");
    $fix->execute([$id, $uid]);

    return $id;
}

function list_topics($pdo, $uid) {
    $s = $pdo->prepare("SELECT id, title, updated_at FROM topics WHERE user_id=? ORDER BY updated_at DESC, id DESC");
    $s->execute([$uid]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

function get_debug_safe($pdo, $uid) {
    try {
        $f = $pdo->prepare("SELECT key, value FROM facts WHERE user_id=? ORDER BY id DESC");
        $f->execute([$uid]);

        $t = $pdo->prepare("SELECT id, description, status, priority, due_date FROM tasks WHERE user_id=? ORDER BY CASE WHEN status='pending' THEN 0 ELSE 1 END, priority ASC, id DESC LIMIT 30");
        $t->execute([$uid]);

        $tags = $pdo->prepare("SELECT tag, score FROM profile_tags WHERE user_id=? ORDER BY score DESC, updated_at DESC LIMIT 12");
        $tags->execute([$uid]);

        $sum = $pdo->prepare("SELECT summary FROM session_summaries WHERE user_id=? ORDER BY id DESC LIMIT 5");
        $sum->execute([$uid]);

        return [
            'facts' => $f->fetchAll(PDO::FETCH_ASSOC),
            'tasks' => $t->fetchAll(PDO::FETCH_ASSOC),
            'profile_tags' => $tags->fetchAll(PDO::FETCH_ASSOC),
            'session_summaries' => $sum->fetchAll(PDO::FETCH_COLUMN)
        ];
    } catch (Exception $e) {
        return ['facts' => [], 'tasks' => [], 'profile_tags' => [], 'session_summaries' => []];
    }
}

function call_ai_safe($msgs, $cfg) {
    if (empty($cfg['api_key'])) return 'Erro de configuração: ASSISTENTE_API_KEY não definida.';

    $payload = json_encode(['model' => $cfg['model'], 'messages' => $msgs, 'temperature' => 0.3]);

    $ch = curl_init($cfg['api_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['api_key']],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 60
    ]);

    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) return 'Erro Conexão IA: ' . curl_error($ch);

    $d = json_decode($res, true);
    if (!is_array($d)) return 'Erro IA: resposta inválida do provedor.';
    if ($status >= 400) {
        $providerError = $d['error']['message'] ?? $d['error'] ?? 'erro desconhecido';
        return 'Erro IA HTTP ' . $status . ': ' . $providerError;
    }

    return $d['choices'][0]['message']['content'] ?? 'Sem resposta da IA.';
}

function extract_tool_call($reply) {
    $clean = trim($reply);
    $parsed = json_decode($clean, true);
    if (is_array($parsed) && isset($parsed['tool'], $parsed['args']) && is_array($parsed['args'])) return $parsed;

    if (preg_match('/```json\s*(\{[\s\S]*?\})\s*```/i', $clean, $m)) {
        $parsedBlock = json_decode($m[1], true);
        if (is_array($parsedBlock) && isset($parsedBlock['tool'], $parsedBlock['args']) && is_array($parsedBlock['args'])) return $parsedBlock;
    }
    return null;
}

function handle_natural_task_command($msg, $pdo, $uid) {
    $m = trim(mb_strtolower($msg));

    if (preg_match('/(?:marque|marcar|concluir|conclua).*(?:tarefa\s*)?(\d+).*(?:conclu[íi]d)/u', $m, $x) || preg_match('/(?:concluir|conclua)\s+(\d+)/u', $m, $x)) {
        return complete_task(['task_id' => (int)$x[1]], $pdo, $uid);
    }
    if (preg_match('/(?:prioridade).*(?:tarefa\s*)?(\d+).*(alta|m[eé]dia|baixa)/u', $m, $x)) {
        return update_task(['task_id' => (int)$x[1], 'priority' => $x[2]], $pdo, $uid);
    }
    if (preg_match('/(?:prazo|vencimento).*(?:tarefa\s*)?(\d+).*?(\d{4}-\d{2}-\d{2})/u', $m, $x)) {
        return update_task(['task_id' => (int)$x[1], 'due_date' => $x[2]], $pdo, $uid);
    }
    if (preg_match('/(?:editar|atualizar).*(?:tarefa\s*)?(\d+)\s*[:\-]\s*(.+)$/u', $msg, $x)) {
        return update_task(['task_id' => (int)$x[1], 'description' => trim($x[2])], $pdo, $uid);
    }
    return null;
}

function upsert_profile_tag($pdo, $uid, $tag, $delta = 1) {
    $tag = mb_strtolower(trim($tag));
    if ($tag === '') return;
    $stmt = $pdo->prepare("INSERT INTO profile_tags (user_id, tag, score, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(user_id, tag)
        DO UPDATE SET score = score + excluded.score, updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$uid, $tag, $delta]);
}

function extract_semantic_tags($text) {
    $t = mb_strtolower($text);
    $map = [
        'tecnologia' => ['php', 'python', 'javascript', 'programação', 'software', 'código', 'api'],
        'produtividade' => ['produtividade', 'organização', 'planejamento', 'foco', 'rotina'],
        'estudos' => ['estudo', 'faculdade', 'curso', 'aprend', 'prova', 'leitura'],
        'carreira' => ['trabalho', 'carreira', 'emprego', 'empresa', 'profissão'],
        'finanças' => ['dinheiro', 'invest', 'finança', 'gasto', 'orçamento'],
        'saúde' => ['saúde', 'dieta', 'academia', 'treino', 'sono'],
        'automóveis' => ['carro', 'veículo', 'automóvel', 'moto'],
        'família' => ['família', 'filho', 'esposa', 'marido', 'pais'],
        'lazer' => ['filme', 'série', 'jogo', 'viagem', 'música']
    ];

    $tags = [];
    foreach ($map as $tag => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($t, $kw) !== false) {
                $tags[] = $tag;
                break;
            }
        }
    }

    return array_values(array_unique($tags));
}

function build_session_summary($userMsg, $assistantReply) {
    $userMsg = trim(preg_replace('/\s+/', ' ', $userMsg));
    $assistantReply = trim(preg_replace('/\s+/', ' ', $assistantReply));
    return mb_substr("Usuário: " . mb_substr($userMsg, 0, 180) . " | Assistente: " . mb_substr($assistantReply, 0, 220), 0, 420);
}

function update_semantic_memory($pdo, $uid, $userMsg, $assistantReply, $facts) {
    $stmt = $pdo->prepare("INSERT INTO session_summaries (user_id, summary) VALUES (?, ?)");
    $stmt->execute([$uid, build_session_summary($userMsg, $assistantReply)]);

    foreach (extract_semantic_tags($userMsg . ' ' . $assistantReply) as $tag) upsert_profile_tag($pdo, $uid, $tag, 1);
    foreach ($facts as $fact) if (!empty($fact['key'])) upsert_profile_tag($pdo, $uid, 'fact:' . $fact['key'], 1);
}

function touch_topic($pdo, $topicId) {
    $pdo->prepare("UPDATE topics SET updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$topicId]);
}

$response = [];
$uid = $_SESSION['user_id'];

try {
    $defaultTopicId = ensure_default_topic($pdo, $uid);
    if (!isset($_SESSION['current_topic_id'])) {
        $_SESSION['current_topic_id'] = $defaultTopicId;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: login.php');
            exit;
        }

        $requestedTopic = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
        if ($requestedTopic > 0) {
            $ok = $pdo->prepare("SELECT id FROM topics WHERE id=? AND user_id=?");
            $ok->execute([$requestedTopic, $uid]);
            if ($ok->fetchColumn()) $_SESSION['current_topic_id'] = $requestedTopic;
        }

        $topicId = (int)($_SESSION['current_topic_id'] ?? $defaultTopicId);
        $msgs = $pdo->prepare('SELECT id, role, content, timestamp, edited_at FROM messages WHERE user_id=? AND topic_id=? ORDER BY id ASC');
        $msgs->execute([$uid, $topicId]);

        $dbg = get_debug_safe($pdo, $uid);

        $searchResults = [];
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $sr = $pdo->prepare("SELECT m.id, m.topic_id, t.title AS topic_title, m.role, m.content, m.timestamp
                FROM messages m
                LEFT JOIN topics t ON t.id = m.topic_id
                WHERE m.user_id=? AND m.content LIKE ?
                ORDER BY m.id DESC LIMIT 40");
            $sr->execute([$uid, '%' . $q . '%']);
            $searchResults = $sr->fetchAll(PDO::FETCH_ASSOC);
        }

        $response = [
            'messages' => $msgs->fetchAll(PDO::FETCH_ASSOC),
            'topics' => list_topics($pdo, $uid),
            'current_topic_id' => $topicId,
            'search_results' => $searchResults,
            'debug_mode' => $config['debug_mode'],
            'debug_facts' => $dbg['facts'],
            'debug_tasks' => $dbg['tasks'],
            'debug_profile_tags' => $dbg['profile_tags'],
            'debug_session_summaries' => $dbg['session_summaries']
        ];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true);
        $action = trim((string)($in['action'] ?? ''));

        if ($action === 'create_topic') {
            $title = trim((string)($in['title'] ?? 'Novo tópico'));
            if ($title === '') $title = 'Novo tópico';
            $pdo->prepare("INSERT INTO topics (user_id, title) VALUES (?, ?)")->execute([$uid, mb_substr($title, 0, 120)]);
            $_SESSION['current_topic_id'] = (int)$pdo->lastInsertId();
            echo json_encode(['success' => true, 'topics' => list_topics($pdo, $uid), 'current_topic_id' => $_SESSION['current_topic_id']]);
            exit;
        }

        if ($action === 'switch_topic') {
            $topicId = (int)($in['topic_id'] ?? 0);
            $ok = $pdo->prepare("SELECT id FROM topics WHERE id=? AND user_id=?");
            $ok->execute([$topicId, $uid]);
            if ($ok->fetchColumn()) {
                $_SESSION['current_topic_id'] = $topicId;
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Tópico inválido.']);
            }
            exit;
        }

        if ($action === 'edit_message') {
            $messageId = (int)($in['message_id'] ?? 0);
            $newContent = trim((string)($in['content'] ?? ''));
            if ($messageId <= 0 || $newContent === '') {
                echo json_encode(['success' => false, 'error' => 'Dados inválidos para edição.']);
                exit;
            }

            $topicId = (int)($_SESSION['current_topic_id'] ?? $defaultTopicId);
            $upd = $pdo->prepare("UPDATE messages SET content=?, edited_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=? AND topic_id=? AND role='user'");
            $upd->execute([$newContent, $messageId, $uid, $topicId]);
            echo json_encode(['success' => $upd->rowCount() > 0, 'message' => $upd->rowCount() > 0 ? 'Mensagem editada.' : 'Mensagem não encontrada.']);
            exit;
        }

        $msg = trim((string)($in['message'] ?? ''));
        if ($msg === '') {
            echo json_encode(['error' => 'Mensagem vazia.']);
            exit;
        }

        $topicId = (int)($_SESSION['current_topic_id'] ?? $defaultTopicId);
        $pdo->prepare("INSERT INTO messages (user_id, topic_id, role, content) VALUES (?, ?, 'user', ?)")->execute([$uid, $topicId, $msg]);
        touch_topic($pdo, $topicId);

        $taskCmdResult = handle_natural_task_command($msg, $pdo, $uid);
        if ($taskCmdResult !== null) {
            $reply = $taskCmdResult;
            $pdo->prepare("INSERT INTO messages (user_id, topic_id, role, content) VALUES (?, ?, 'assistant', ?)")->execute([$uid, $topicId, $reply]);
            touch_topic($pdo, $topicId);
            $freshDbg = get_debug_safe($pdo, $uid);
            echo json_encode([
                'reply' => $reply,
                'stream_text' => $reply,
                'topics' => list_topics($pdo, $uid),
                'current_topic_id' => $topicId,
                'debug_facts' => $freshDbg['facts'],
                'debug_tasks' => $freshDbg['tasks'],
                'debug_profile_tags' => $freshDbg['profile_tags'],
                'debug_session_summaries' => $freshDbg['session_summaries']
            ]);
            exit;
        }

        $dbg = get_debug_safe($pdo, $uid);
        $persona = "MEMÓRIA DE LONGO PRAZO:\n";
        foreach ($dbg['facts'] as $f) $persona .= "- {$f['key']}: {$f['value']}\n";

        $semantic = "\nPERFIL SEMÂNTICO (tags mais fortes):\n";
        foreach ($dbg['profile_tags'] as $tag) $semantic .= "- {$tag['tag']} (score {$tag['score']})\n";
        $semantic .= "\nRESUMOS RECENTES DE SESSÃO:\n";
        foreach ($dbg['session_summaries'] as $sum) $semantic .= "- {$sum}\n";

        $prompt = "Você é Jarvis. Personalize respostas usando memória factual e semântica.\n$persona\n$semantic\nFerramentas Disponíveis: " . json_encode(get_tools_definition());

        $hist = [['role' => 'system', 'content' => $prompt]];
        $limit = max(1, (int)($config['context_limit'] ?? 10));
        $st = $pdo->prepare("SELECT role, content FROM messages WHERE user_id=? AND topic_id=? ORDER BY id DESC LIMIT $limit");
        $st->execute([$uid, $topicId]);
        foreach (array_reverse($st->fetchAll(PDO::FETCH_ASSOC)) as $h) $hist[] = ['role' => $h['role'], 'content' => $h['content']];

        $reply = call_ai_safe($hist, $config);
        $toolCall = extract_tool_call($reply);
        if ($toolCall && isset($toolCall['tool'])) {
            $toolName = (string)$toolCall['tool'];
            $toolArgs = $toolCall['args'];

            if ($toolName === 'save_memory') $resTool = save_memory($toolArgs, $pdo, $uid);
            elseif ($toolName === 'add_task') $resTool = add_task($toolArgs, $pdo, $uid);
            elseif ($toolName === 'update_task') $resTool = update_task($toolArgs, $pdo, $uid);
            elseif ($toolName === 'complete_task') $resTool = complete_task($toolArgs, $pdo, $uid);
            else $resTool = 'Tool inválida: ' . $toolName;

            $hist[] = ['role' => 'assistant', 'content' => json_encode($toolCall, JSON_UNESCAPED_UNICODE)];
            $hist[] = ['role' => 'system', 'content' => "Tool Output: $resTool. Agora responda ao usuário confirmando."];
            $reply = call_ai_safe($hist, $config);
        }

        $pdo->prepare("INSERT INTO messages (user_id, topic_id, role, content) VALUES (?, ?, 'assistant', ?)")->execute([$uid, $topicId, $reply]);
        touch_topic($pdo, $topicId);
        update_semantic_memory($pdo, $uid, $msg, $reply, $dbg['facts']);

        $freshDbg = get_debug_safe($pdo, $uid);
        $response = [
            'reply' => $reply,
            'stream_text' => $reply,
            'topics' => list_topics($pdo, $uid),
            'current_topic_id' => $topicId,
            'debug_facts' => $freshDbg['facts'],
            'debug_tasks' => $freshDbg['tasks'],
            'debug_profile_tags' => $freshDbg['profile_tags'],
            'debug_session_summaries' => $freshDbg['session_summaries']
        ];
    }
} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
}

ob_end_clean();
echo json_encode($response);
?>
