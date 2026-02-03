<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Jarvis AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #121212; }
        .auth-card { width: 100%; max-width: 400px; padding: 2rem; background: #1e1e1e; border: 1px solid #333; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .btn-primary { background-color: #0d6efd; border: none; }
        .form-control { background-color: #2c2c2c; border: 1px solid #444; color: #fff; }
        .form-control:focus { background-color: #2c2c2c; color: #fff; border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
    </style>
</head>
<body>

<div class="auth-card">
    <h3 class="text-center mb-4 text-white" id="form-title">Login Jarvis</h3>
    
    <div class="mb-3">
        <label class="form-label text-secondary">Email</label>
        <input type="email" id="email" class="form-control" placeholder="seu@email.com">
    </div>
    
    <div class="mb-3">
        <label class="form-label text-secondary">Senha</label>
        <input type="password" id="password" class="form-control" placeholder="******">
    </div>

    <div id="error-msg" class="alert alert-danger d-none p-2 small"></div>

    <div class="d-grid gap-2 mt-4">
        <button class="btn btn-primary" onclick="handleAuth()" id="btn-action">Entrar</button>
        <button class="btn btn-outline-secondary" onclick="toggleMode()" id="btn-toggle">Criar conta</button>
    </div>
</div>

<script>
    let isRegister = false;

    function toggleMode() {
        isRegister = !isRegister;
        document.getElementById('form-title').innerText = isRegister ? 'Nova Conta' : 'Login Jarvis';
        document.getElementById('btn-action').innerText = isRegister ? 'Cadastrar e Entrar' : 'Entrar';
        document.getElementById('btn-toggle').innerText = isRegister ? 'Voltar para Login' : 'Criar conta';
        document.getElementById('error-msg').classList.add('d-none');
    }

    async function handleAuth() {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const btn = document.getElementById('btn-action');
        const err = document.getElementById('error-msg');

        if(!email || !password) {
            showError('Preencha todos os campos.');
            return;
        }

        btn.disabled = true;
        btn.innerText = 'Processando...';
        err.classList.add('d-none');

        try {
            const res = await fetch('auth_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: isRegister ? 'register' : 'login',
                    email: email,
                    password: password
                })
            });

            const data = await res.json();

            if (data.success) {
                window.location.href = data.redirect; // Redireciona para o index
            } else {
                showError(data.error);
            }
        } catch (e) {
            showError('Erro de conexão com o servidor.');
        } finally {
            btn.disabled = false;
            btn.innerText = isRegister ? 'Cadastrar e Entrar' : 'Entrar';
        }
    }

    function showError(msg) {
        const el = document.getElementById('error-msg');
        el.innerText = msg;
        el.classList.remove('d-none');
    }

    // Enter key support
    document.getElementById('password').addEventListener('keypress', e => { if(e.key==='Enter') handleAuth() });
</script>

</body>
</html>