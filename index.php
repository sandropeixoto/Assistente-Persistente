<?php
// CONFIGURAÇÃO DE SESSÃO (Deve ser igual ao auth_action)
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

// PROTEÇÃO: Se não estiver logado, redireciona para login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jarvis AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { height: 100vh; overflow: hidden; display: flex; flex-direction: column; background-color: #121212; }
        .main-container { flex: 1; display: flex; overflow: hidden; }
        #chat-area { flex: 1; display: flex; flex-direction: column; position: relative; }
        #messages-box { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth; }
        .msg { max-width: 85%; padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; word-wrap: break-word; }
        .msg-user { background-color: #0d6efd; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-bot { background-color: #2c3035; color: #e9ecef; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #3a3f45; }
        #debug-sidebar { width: 300px; background-color: #1a1d20; border-left: 1px solid #343a40; padding: 15px; overflow-y: auto; font-family: 'Consolas', monospace; font-size: 0.85rem; display: none; }
        .input-area { background-color: #1a1d20; padding: 20px; border-top: 1px solid #343a40; }
        .debug-title { text-transform: uppercase; font-weight: bold; font-size: 0.75rem; color: #adb5bd; margin-bottom: 10px; display: block; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#"><i class="bi bi-cpu-fill text-primary"></i> Jarvis AI</a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-secondary small"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
            <a href="api.php?logout=1" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </div>
</nav>

<div class="main-container">
    <main id="chat-area">
        <div id="messages-box"></div>
        <div id="typing-indicator" class="px-4 pb-2 text-muted small fst-italic" style="display:none;">Jarvis está pensando...</div>
        <div class="input-area">
            <div class="input-group">
                <input type="text" id="user-input" class="form-control bg-dark text-light border-secondary" placeholder="Digite sua mensagem..." autocomplete="off">
                <button class="btn btn-primary" onclick="sendMessage()"><i class="bi bi-send-fill"></i></button>
            </div>
        </div>
    </main>

    <aside id="debug-sidebar">
        <div class="mb-4">
            <span class="debug-title">Memória</span>
            <div id="debug-facts"><em class="text-muted">Carregando...</em></div>
        </div>
        <div>
            <span class="debug-title">Tarefas</span>
            <div id="debug-tasks"></div>
        </div>
    </aside>
</div>

<script>
    const msgBox = document.getElementById('messages-box');

    async function initChat() {
        try {
            const res = await fetch('api.php');
            if (res.redirected || res.status === 401) { window.location.href = 'login.php'; return; }
            
            const txt = await res.text();
            let data = JSON.parse(txt); // Se falhar aqui, o PHP enviou lixo

            msgBox.innerHTML = '';
            if(data.messages && data.messages.length) data.messages.forEach(m => addBubble(m.role, m.content));
            else msgBox.innerHTML = '<div class="text-center text-muted mt-5">Inicie a conversa...</div>';

            if(data.debug_mode) {
                document.getElementById('debug-sidebar').style.display = 'block';
                updateDebug(data);
            }
            scrollToBottom();
        } catch(e) { console.error("Erro Load:", e); }
    }

    function updateDebug(data) {
        document.getElementById('debug-facts').innerHTML = (data.debug_facts || []).map(f => `<div class="text-info mb-1">${f.key}: <span class="text-white">${f.value}</span></div>`).join('') || '<em class="text-muted">Vazio</em>';
        document.getElementById('debug-tasks').innerHTML = (data.debug_tasks || []).map(t => `<div class="text-warning mb-1">• ${t.description}</div>`).join('') || '<em class="text-muted">Vazio</em>';
    }

    function addBubble(role, text) {
        const div = document.createElement('div');
        div.className = `msg ${role === 'user' ? 'msg-user' : 'msg-bot'}`;
        div.innerHTML = text.replace(/\n/g, '<br>');
        msgBox.appendChild(div);
    }

    function scrollToBottom() { msgBox.scrollTop = msgBox.scrollHeight; }

    async function sendMessage() {
        const inp = document.getElementById('user-input');
        const txt = inp.value.trim();
        if(!txt) return;

        addBubble('user', txt);
        inp.value = ''; inp.disabled = true;
        document.getElementById('typing-indicator').style.display = 'block';
        scrollToBottom();

        try {
            const res = await fetch('api.php', {
                method: 'POST', 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message: txt})
            });
            const data = await res.json();
            
            if(data.error) addBubble('bot', 'Erro: ' + data.error);
            else {
                addBubble('assistant', data.reply);
                updateDebug(data);
            }
        } catch(e) { addBubble('bot', 'Erro de conexão.'); }
        
        inp.disabled = false; inp.focus();
        document.getElementById('typing-indicator').style.display = 'none';
        scrollToBottom();
    }

    document.getElementById('user-input').addEventListener('keypress', e => { if(e.key === 'Enter') sendMessage() });
    initChat();
</script>
</body>
</html>