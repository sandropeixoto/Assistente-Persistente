<?php
// tools.php

function normalize_priority($priority) {
    $raw = mb_strtolower(trim((string)$priority));
    $map = [
        '1' => 1, 'alta' => 1, 'high' => 1,
        '2' => 2, 'media' => 2, 'média' => 2, 'normal' => 2,
        '3' => 3, 'baixa' => 3, 'low' => 3
    ];

    return $map[$raw] ?? 2;
}

function normalize_due_date($dueDate) {
    $dueDate = trim((string)$dueDate);
    if ($dueDate === '') return null;

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        return $dueDate . ' 23:59:59';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $dueDate)) {
        return strlen($dueDate) === 16 ? $dueDate . ':00' : $dueDate;
    }

    return null;
}

function save_memory($args, $pdo, $userId) {
    $key = $args['key'] ?? '';
    $value = $args['value'] ?? '';

    if (!$key || !$value) return "Erro: Chave ou valor vazios.";

    $stmt = $pdo->prepare("INSERT OR REPLACE INTO facts (user_id, key, value) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $key, $value]);

    return "Memória salva com sucesso: [$key] = $value";
}

function add_task($args, $pdo, $userId) {
    $desc = trim((string)($args['description'] ?? ''));
    if ($desc === '') return 'Erro: descrição da tarefa vazia.';

    $priority = normalize_priority($args['priority'] ?? 2);
    $dueDate = normalize_due_date($args['due_date'] ?? null);

    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, description, priority, due_date) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $desc, $priority, $dueDate]);

    $id = $pdo->lastInsertId();
    return "Tarefa #$id adicionada com prioridade $priority.";
}

function complete_task($args, $pdo, $userId) {
    $taskId = (int)($args['task_id'] ?? 0);
    if ($taskId <= 0) return 'Erro: task_id inválido.';

    $stmt = $pdo->prepare("UPDATE tasks SET status='completed', completed_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?");
    $stmt->execute([$taskId, $userId]);

    if ($stmt->rowCount() === 0) return "Tarefa #$taskId não encontrada.";
    return "Tarefa #$taskId marcada como concluída.";
}

function update_task($args, $pdo, $userId) {
    $taskId = (int)($args['task_id'] ?? 0);
    if ($taskId <= 0) return 'Erro: task_id inválido.';

    $sets = [];
    $params = [];

    if (isset($args['description'])) {
        $desc = trim((string)$args['description']);
        if ($desc !== '') {
            $sets[] = 'description=?';
            $params[] = $desc;
        }
    }

    if (isset($args['priority'])) {
        $sets[] = 'priority=?';
        $params[] = normalize_priority($args['priority']);
    }

    if (array_key_exists('due_date', $args)) {
        $sets[] = 'due_date=?';
        $params[] = normalize_due_date($args['due_date']);
    }

    if (empty($sets)) return 'Nenhuma alteração enviada.';

    $params[] = $taskId;
    $params[] = $userId;

    $sql = "UPDATE tasks SET " . implode(', ', $sets) . " WHERE id=? AND user_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) return "Tarefa #$taskId não encontrada ou sem mudanças.";
    return "Tarefa #$taskId atualizada.";
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
            'description' => 'Adiciona uma tarefa com prioridade opcional (1=alta,2=média,3=baixa) e prazo opcional.',
            'parameters' => '{"description": "texto", "priority": "1|2|3|alta|media|baixa", "due_date": "YYYY-MM-DD ou YYYY-MM-DD HH:MM"}'
        ],
        [
            'name' => 'update_task',
            'description' => 'Edita tarefa existente por id (descrição, prioridade e/ou prazo).',
            'parameters' => '{"task_id": 12, "description": "novo texto", "priority": 1, "due_date": "2026-02-20"}'
        ],
        [
            'name' => 'complete_task',
            'description' => 'Marca tarefa como concluída.',
            'parameters' => '{"task_id": 12}'
        ]
    ];
}
?>
