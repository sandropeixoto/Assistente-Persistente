<?php
// tools.php

// 1. Salvar Memória (Nova!)
function save_memory($args, $pdo, $userId) {
    $key = $args['key'] ?? '';
    $value = $args['value'] ?? '';
    
    if (!$key || !$value) return "Erro: Chave ou valor vazios.";
    
    // Remove duplicatas ou atualiza
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO facts (user_id, key, value) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $key, $value]);
    
    return "Memória gravada: [$key] = $value";
}

// 2. Cotação
function get_crypto_price($args) {
    $coin = strtolower($args['coin'] ?? 'bitcoin');
    $map = ['btc'=>'bitcoin', 'eth'=>'ethereum', 'sol'=>'solana', 'dolar'=>'usd-brl'];
    $id = $map[$coin] ?? $coin;
    
    $url = "https://api.coingecko.com/api/v3/simple/price?ids=$id&vs_currencies=brl,usd";
    $ctx = stream_context_create(['http'=>['header'=>"User-Agent: Bot/1.0"]]);
    $json = @file_get_contents($url, false, $ctx);
    
    if ($json) {
        $d = json_decode($json, true);
        if (isset($d[$id])) return "Preço $coin: R$ {$d[$id]['brl']} / USD {$d[$id]['usd']}";
    }
    return "Cotação indisponível para $coin.";
}

// 3. Tarefas
function add_task($args, $pdo, $userId) {
    $desc = $args['description'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, description) VALUES (?, ?)");
    $stmt->execute([$userId, $desc]);
    return "Tarefa agendada: $desc";
}

function list_tasks($args, $pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, description FROM tasks WHERE user_id=? AND status='pending'");
    $stmt->execute([$userId]);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$res) return "Sem tarefas pendentes.";
    $out = "Lista:\n";
    foreach($res as $r) $out .= "-ID {$r['id']}: {$r['description']}\n";
    return $out;
}

// DEFINIÇÃO PARA A IA
function get_tools_definition() {
    return [
        [
            'name' => 'save_memory',
            'description' => 'Guarda uma informação permanente sobre o usuário (Ex: nome, cargo, carro, filhos).',
            'parameters' => '{"key": "categoria (ex: nome, cargo, veiculo)", "value": "a informação em si"}'
        ],
        [
            'name' => 'get_crypto_price',
            'description' => 'Consulta preço de cripto ou moedas.',
            'parameters' => '{"coin": "nome (ex: bitcoin, dolar)"}'
        ],
        [
            'name' => 'add_task',
            'description' => 'Cria um lembrete/tarefa.',
            'parameters' => '{"description": "o que fazer"}'
        ],
        [
            'name' => 'list_tasks',
            'description' => 'Lista tarefas pendentes.',
            'parameters' => '{}'
        ]
    ];
}
?>