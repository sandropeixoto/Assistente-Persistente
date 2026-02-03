<?php
// tools.php

function save_memory($args, $pdo, $userId) {
    $key = $args['key'] ?? '';
    $value = $args['value'] ?? '';
    
    if (!$key || !$value) return "Erro: Chave ou valor vazios.";
    
    // INSERT OR REPLACE garante que atualiza se a chave já existir
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO facts (user_id, key, value) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $key, $value]);
    
    return "Memória salva com sucesso: [$key] = $value";
}

function add_task($args, $pdo, $userId) {
    $desc = $args['description'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, description) VALUES (?, ?)");
    $stmt->execute([$userId, $desc]);
    return "Tarefa adicionada.";
}

function get_tools_definition() {
    return [
        [
            'name' => 'save_memory',
            'description' => 'Salva ou atualiza uma informação permanente sobre o usuário (Ex: nome, profissão, gostos).',
            'parameters' => '{"key": "categoria (ex: nome, cargo)", "value": "a informação"}'
        ],
        [
            'name' => 'add_task',
            'description' => 'Adiciona um item na lista de tarefas.',
            'parameters' => '{"description": "texto da tarefa"}'
        ]
    ];
}
?>