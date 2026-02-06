<?php
// Configuração de Sessão (24 horas)
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Limpa qualquer saída anterior para garantir JSON puro
ob_start();

header('Content-Type: application/json');
require 'db.php';

$response = ['success' => false, 'error' => 'Erro desconhecido'];

if (!isset($_SESSION['auth_rate'])) {
    $_SESSION['auth_rate'] = ['attempts' => 0, 'blocked_until' => 0];
}

function validate_auth_input($email, $password) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido.');
    }

    if (strlen($password) < 6) {
        throw new Exception('A senha deve ter no mínimo 6 caracteres.');
    }

    if (strlen($password) > 255) {
        throw new Exception('Senha muito longa.');
    }
}

function register_failed_attempt() {
    $_SESSION['auth_rate']['attempts'] = (int)($_SESSION['auth_rate']['attempts'] ?? 0) + 1;
    if ($_SESSION['auth_rate']['attempts'] >= 5) {
        $_SESSION['auth_rate']['blocked_until'] = time() + 60;
    }
}

function clear_failed_attempts() {
    $_SESSION['auth_rate'] = ['attempts' => 0, 'blocked_until' => 0];
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!in_array($action, ['register', 'login'], true)) throw new Exception('Ação inválida.');

    if ((int)($_SESSION['auth_rate']['blocked_until'] ?? 0) > time()) {
        throw new Exception('Muitas tentativas. Aguarde 1 minuto e tente novamente.');
    }

    if (!$email || !$password) throw new Exception("Preencha todos os campos.");
    validate_auth_input($email, $password);

    // --- REGISTRAR ---
    if ($action === 'register') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) throw new Exception("Email já cadastrado.");

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->execute([$email, $hash]);
        
        // Auto-login após registro
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['email'] = $email;
        
        clear_failed_attempts();
        $response = ['success' => true, 'redirect' => 'index.php'];
    }

    // --- LOGIN ---
    elseif ($action === 'login') {
        $stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            clear_failed_attempts();
            $response = ['success' => true, 'redirect' => 'index.php'];
        } else {
            register_failed_attempt();
            throw new Exception("Credenciais inválidas.");
        }
    }

} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

ob_end_clean(); // Limpa buffer
echo json_encode($response);
?>