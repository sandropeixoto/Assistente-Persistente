<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['error' => 'Preencha email e senha.']);
    exit;
}

try {
    if ($action === 'register') {
        // Verifica duplicidade
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => 'Email já existe.']);
            exit;
        }

        // Cria usuário
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->execute([$email, $hash]);
        $userId = $pdo->lastInsertId();

        // Cria fatos iniciais padrão
        $defaults = [
            'status' => 'Novo usuário cadastrado.',
            'preferencia' => 'Gosta de respostas diretas.'
        ];
        $stmtFact = $pdo->prepare("INSERT INTO facts (user_id, key, value) VALUES (?, ?, ?)");
        foreach ($defaults as $k => $v) $stmtFact->execute([$userId, $k, $v]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;
        echo json_encode(['success' => true]);

    } elseif ($action === 'login') {
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Dados incorretos.']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>