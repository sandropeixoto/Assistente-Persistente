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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$email || !$password) throw new Exception("Preencha todos os campos.");

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
            $response = ['success' => true, 'redirect' => 'index.php'];
        } else {
            throw new Exception("Credenciais inválidas.");
        }
    }

} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

ob_end_clean(); // Limpa buffer
echo json_encode($response);
?>