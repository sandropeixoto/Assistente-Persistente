<?php
// Configurações de Sessão e Limpeza
ob_start();
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

// Configs PHP
set_time_limit(120);
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Proteção API
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado', 'redirect' => 'login.php']);
    exit;
}

require 'db.php';
require 'tools.php'; 
$config = require 'config.php';

// -- FUNÇÕES AUXILIARES --
function get_debug_safe($pdo, $uid) {
    try {
        // Busca Fatos (Ordenados pelos mais recentes primeiro para destaque visual)
        $f = $pdo->prepare("SELECT key, value FROM facts WHERE user_id=? ORDER BY id DESC"); 
        $f->execute([$uid]);
        
        // Busca Tarefas
        $t = $pdo->prepare("SELECT description FROM tasks WHERE user_id=? AND status='pending'"); 
        $t->execute([$uid]);
        
        return ['facts' => $f->fetchAll(PDO::FETCH_ASSOC), 'tasks' => $t->fetchAll(PDO::FETCH_COLUMN)];
    } catch (Exception $e) { return ['facts'=>[], 'tasks'=>[]]; }
}

function call_ai_safe($msgs, $cfg) {
    $ch = curl_init($cfg['api_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['model'=>$cfg['model'], 'messages'=>$msgs, 'temperature'=>0.3]),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer ".$cfg['api_key']],
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 60
    ]);
    $res = curl_exec($ch);
    if(curl_errno($ch)) return "Erro Conexão IA: ".curl_error($ch);
    $d = json_decode($res, true);
    return $d['choices'][0]['message']['content'] ?? "Sem resposta da IA.";
}

// -- LÓGICA PRINCIPAL --
$response = [];
$uid = $_SESSION['user_id'];

try {
    // --- CARGA INICIAL (GET) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if(isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

        $msgs = $pdo->prepare("SELECT role, content FROM messages WHERE user_id=? ORDER BY id ASC");
        $msgs->execute([$uid]);
        $dbg = get_debug_safe($pdo, $uid);
        
        $response = [
            'messages' => $msgs->fetchAll(PDO::FETCH_ASSOC),
            'debug_mode' => $config['debug_mode'],
            'debug_facts' => $dbg['facts'],
            'debug_tasks' => $dbg['tasks']
        ];
    }

    // --- CONVERSA (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true);
        $msg = $in['message'] ?? '';

        // 1. Salva User Msg
        $pdo->prepare("INSERT INTO messages (user_id, role, content) VALUES (?, 'user', ?)")->execute([$uid, $msg]);

        // 2. Carrega Contexto Atual (Para enviar à IA)
        $dbg = get_debug_safe($pdo, $uid);
        $persona = "MEMÓRIA DE LONGO PRAZO:\n";
        foreach($dbg['facts'] as $f) $persona .= "- {$f['key']}: {$f['value']}\n";
        
        // Detecta se é usuário novo (sem fatos)
        $intro = "";
        if (count($dbg['facts']) < 2) {
            $intro = "IMPORTANTE: Você ainda não sabe nada sobre o usuário. Seu objetivo PRINCIPAL agora é perguntar o Nome, Profissão e Carro dele. Use a ferramenta 'save_memory' assim que ele responder.";
        }
        
        $prompt = "Você é Jarvis.\n$persona\n$intro\nFerramentas Disponíveis: ".json_encode(get_tools_definition());
        
        // 3. Monta Histórico
        $hist = [['role'=>'system','content'=>$prompt]];
        $st = $pdo->prepare("SELECT role, content FROM messages WHERE user_id=? ORDER BY id DESC LIMIT 10");
        $st->execute([$uid]);
        foreach(array_reverse($st->fetchAll(PDO::FETCH_ASSOC)) as $h) $hist[] = ['role'=>$h['role'], 'content'=>$h['content']];

        // 4. Chama IA
        $reply = call_ai_safe($hist, $config);

        // 5. Verifica se IA usou Tool (save_memory)
        // Expressão regular melhorada para pegar JSON mesmo com texto em volta
        if (preg_match('/\{.*"tool":.*"args":.*\}/s', $reply, $m)) {
            $j = json_decode($m[0], true);
            
            if(isset($j['tool'])) {
                $resTool = "Erro tool";
                
                // Executa a ação no banco
                if ($j['tool'] == 'save_memory') {
                    $resTool = save_memory($j['args'], $pdo, $uid);
                } elseif ($j['tool'] == 'add_task') {
                    $resTool = add_task($j['args'], $pdo, $uid);
                }

                // Injeta resultado e pede resposta final em texto
                $hist[] = ['role'=>'assistant', 'content'=>$m[0]];
                $hist[] = ['role'=>'system', 'content'=>"Tool Output: $resTool. Agora responda ao usuário confirmando."];
                $reply = call_ai_safe($hist, $config);
            }
        }

        // 6. Salva Resposta do Bot
        $pdo->prepare("INSERT INTO messages (user_id, role, content) VALUES (?, 'assistant', ?)")->execute([$uid, $reply]);
        
        // 7. [PULO DO GATO] Recarrega dados do banco ATUALIZADOS para enviar ao Frontend
        $freshDbg = get_debug_safe($pdo, $uid);
        
        $response = [
            'reply' => $reply, 
            'debug_facts' => $freshDbg['facts'], // Envia a lista atualizada
            'debug_tasks' => $freshDbg['tasks']
        ];
    }
} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
}

ob_end_clean();
echo json_encode($response);
?>