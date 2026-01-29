<?php
// 1. INÍCIO DA LIMPEZA (Captura tudo que o PHP tentar falar)
ob_start();

// Configurações básicas
set_time_limit(120);
ini_set('display_errors', 0); // Não mostra erros na tela
error_reporting(E_ALL); // Registra erros no log interno

session_start();
require 'db.php';
require 'tools.php';
$config = require 'config.php';

// Função auxiliar para pegar debug (Segura contra falhas)
function get_debug_safe($pdo, $uid) {
    try {
        $f = $pdo->prepare("SELECT key, value FROM facts WHERE user_id=?");
        $f->execute([$uid]);
        $t = $pdo->prepare("SELECT description FROM tasks WHERE user_id=? AND status='pending'");
        $t->execute([$uid]);
        return ['facts' => $f->fetchAll(PDO::FETCH_ASSOC), 'tasks' => $t->fetchAll(PDO::FETCH_COLUMN)];
    } catch (Exception $e) { return ['facts'=>[], 'tasks'=>[]]; }
}

// Lógica Principal
$response = [];

try {
    // Auth Check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception("Sessão expirada");
    }
    
    $uid = $_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET (Carregar Chat) ---
    if ($method === 'GET') {
        if (isset($_GET['logout'])) { session_destroy(); exit; }

        $msgs = $pdo->prepare("SELECT role, content FROM messages WHERE user_id=? ORDER BY id ASC");
        $msgs->execute([$uid]);
        
        $dbg = get_debug_safe($pdo, $uid);

        $response = [
            'email' => $_SESSION['email'],
            'messages' => $msgs->fetchAll(PDO::FETCH_ASSOC),
            'debug_mode' => $config['debug_mode'],
            'debug_facts' => $dbg['facts'],
            'debug_tasks' => $dbg['tasks']
        ];
    }

    // --- POST (Enviar Mensagem) ---
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $msg = $input['message'] ?? '';
        
        // 1. Salvar User
        $pdo->prepare("INSERT INTO messages (user_id, role, content) VALUES (?, 'user', ?)")->execute([$uid, $msg]);

        // 2. Contexto
        $dbg = get_debug_safe($pdo, $uid);
        $persona = "FATOS CONHECIDOS:\n";
        foreach($dbg['facts'] as $f) $persona .= "- {$f['key']}: {$f['value']}\n";
        
        // RAG Simples
        preg_match_all('/\b\w{4,}\b/u', $msg, $m);
        $rag = "";
        if(!empty($m[0])) {
            $sql = "SELECT content FROM messages WHERE user_id=$uid AND content LIKE ? LIMIT 3";
            $st = $pdo->prepare($sql); $st->execute(['%'.$m[0][0].'%']);
            foreach($st->fetchAll() as $r) $rag .= "- " . substr($r['content'],0,100) . "...\n";
        }

        // 3. Prompt
        $sys = "Você é Jarvis.\n$persona\nContexto Antigo:\n$rag\nResponda curto.";
        $hist = [['role'=>'system','content'=>$sys]];
        
        // Histórico Recente
        $st = $pdo->prepare("SELECT role, content FROM messages WHERE user_id=? ORDER BY id DESC LIMIT 5");
        $st->execute([$uid]);
        foreach(array_reverse($st->fetchAll(PDO::FETCH_ASSOC)) as $h) {
            $hist[] = ['role'=>$h['role'], 'content'=>$h['content']];
        }

        // 4. API Call
        $ch = curl_init($config['api_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$config['model'], 'messages'=>$hist]),
            CURLOPT_HTTPHEADER=>["Content-Type: application/json", "Authorization: Bearer ".$config['api_key']],
            CURLOPT_SSL_VERIFYPEER=>false
        ]);
        $apiRes = curl_exec($ch);
        $apiData = json_decode($apiRes, true);
        $reply = $apiData['choices'][0]['message']['content'] ?? 'Sem resposta da IA';

        // 5. Salvar Bot
        $pdo->prepare("INSERT INTO messages (user_id, role, content) VALUES (?, 'assistant', ?)")->execute([$uid, $reply]);
        
        // Dados frescos para atualizar a tela
        $newDbg = get_debug_safe($pdo, $uid);
        
        $response = [
            'reply' => $reply,
            'debug_facts' => $newDbg['facts']
        ];
    }

} catch (Exception $e) {
    // Se der erro, prepara resposta de erro JSON
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
}

// 2. LIMPEZA FINAL (O Pulo do Gato)
// Descarta qualquer aviso/texto que o PHP tenha gerado até agora
ob_end_clean();

// 3. ENTREGA PURA
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>