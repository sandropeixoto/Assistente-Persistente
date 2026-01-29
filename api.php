<?php
// api.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

$config = require 'config.php';

try {
    $pdo = new PDO('sqlite:database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabelas (mesmas de antes)
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, role TEXT, content TEXT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS facts (key TEXT PRIMARY KEY, value TEXT)");

    // Auto-população (mesma lógica anterior...)
    $stmt = $pdo->query("SELECT count(*) FROM facts");
    if ($stmt->fetchColumn() == 0) {
        $initialFacts = [
            'nome' => 'Desenvolvedor',
            'cargo' => 'Senior Dev na SEFA/PA',
            'veiculo' => 'BYD Dolphin'
        ]; // (Resumido para o exemplo, mantenha o seu completo)
        $insert = $pdo->prepare("INSERT INTO facts (key, value) VALUES (:k, :v)");
        foreach ($initialFacts as $k => $v) $insert->execute([':k' => $k, ':v' => $v]);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET: Carregar Tudo ---
    if ($method === 'GET') {
        // 1. Mensagens
        $stmtMsg = $pdo->query("SELECT role, content, timestamp FROM messages ORDER BY id ASC");
        $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fatos (Memória Permanente)
        $stmtFacts = $pdo->query("SELECT * FROM facts");
        $facts = $stmtFacts->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'messages' => $messages,
            'facts' => $facts,
            'debug_mode' => $config['debug_mode']
        ]);
        exit;
    }

    // --- POST: Chat ---
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userMessage = $input['message'] ?? '';
        if (empty($userMessage)) throw new Exception("Mensagem vazia.");

        // 1. Salvar User
        $stmt = $pdo->prepare("INSERT INTO messages (role, content) VALUES ('user', :content)");
        $stmt->execute([':content' => $userMessage]);

        // 2. Carregar Contexto Permanente
        $stmt = $pdo->query("SELECT value FROM facts");
        $factsList = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $personaText = "### PERFIL:\n" . implode("\n- ", $factsList);

        // 3. RAG (Busca de memória antiga)
        $ragLog = "Nenhuma memória antiga relevante encontrada.";
        preg_match_all('/\b\w{4,}\b/u', $userMessage, $matches);
        $keywords = $matches[0];
        $longTermMemory = "";

        if (!empty($keywords)) {
            $clauses = []; $params = [];
            foreach ($keywords as $i => $word) {
                if (in_array(strtolower($word), ['para', 'como', 'quem'])) continue;
                $clauses[] = "content LIKE :word$i";
                $params[":word$i"] = "%$word%";
            }
            if (!empty($clauses)) {
                $sqlRAG = "SELECT role, content FROM messages WHERE (" . implode(' OR ', $clauses) . ") AND id < (SELECT MAX(id) FROM messages) - :limit ORDER BY id DESC LIMIT 3";
                $stmtRAG = $pdo->prepare($sqlRAG);
                $params[':limit'] = $config['context_limit'];
                foreach ($params as $k => $v) $stmtRAG->bindValue($k, $v);
                $stmtRAG->bindValue(':limit', $config['context_limit'], PDO::PARAM_INT);
                $stmtRAG->execute();
                $ragResults = $stmtRAG->fetchAll(PDO::FETCH_ASSOC);
                
                if ($ragResults) {
                    $longTermMemory = "\n### MEMÓRIA RECUPERADA:\n";
                    $ragLog = "Memórias recuperadas:\n"; // Para o Debug
                    foreach (array_reverse($ragResults) as $mem) {
                        $longTermMemory .= "- [{$mem['role']}]: {$mem['content']}\n";
                        $ragLog .= "- [{$mem['role']}]: " . substr($mem['content'], 0, 50) . "...\n";
                    }
                }
            }
        }

        // 4. Montar Payload
        $limit = $config['context_limit'];
        $stmt = $pdo->prepare("SELECT role, content FROM messages ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        $finalSystemPrompt = $personaText . $longTermMemory . "\nSeja direto.";
        array_unshift($history, ['role' => 'system', 'content' => $finalSystemPrompt]);

        // 5. Chamada API
        $ch = curl_init($config['api_url']);
        $payload = json_encode(['model' => $config['model'], 'messages' => $history, 'temperature' => 0.4]);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer " . $config['api_key']]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $botReply = $data['choices'][0]['message']['content'] ?? 'Erro API';

        // 6. Salvar Assistente
        $stmt = $pdo->prepare("INSERT INTO messages (role, content) VALUES ('assistant', :content)");
        $stmt->execute([':content' => $botReply]);

        // Retorna a resposta E os dados de debug
        echo json_encode([
            'reply' => $botReply,
            'debug_rag' => $ragLog,
            'debug_used_keywords' => implode(", ", $keywords)
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>