<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jarvis AI - Bootstrap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body { height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        
        /* Área do Chat */
        #chat-container { flex: 1; overflow-y: auto; scroll-behavior: smooth; background-color: #212529; }
        
        /* Balões de Mensagem */
        .msg-bubble { max-width: 80%; padding: 12px 18px; border-radius: 15px; margin-bottom: 15px; position: relative; font-size: 0.95rem; line-height: 1.5; }
        .msg-user { background-color: #0d6efd; color: white; border-bottom-right-radius: 2px; align-self: flex-end; margin-left: auto; }
        .msg-bot { background-color: #343a40; color: #e9ecef; border: 1px solid #495057; border-bottom-left-radius: 2px; align-self: flex-start; margin-right: auto; }
        
        /* Sidebar de Debug */
        .debug-sidebar { width: 320px; border-left: 1px solid #495057; background-color: #1a1d20; font-family: 'Consolas', monospace; font-size: 0.85rem; overflow-y: auto; }
        .debug-card { background-color: #212529; border: 1px solid #343a40; margin-bottom: 10px; }
        .debug-key { color: #6ea8fe; font-weight: bold; }
        .debug-val { color: #adb5bd; }

        /* Digitando... */
        .typing-indicator { display: none; color: #adb5bd; font-style: italic; font-size: 0.8rem; margin-bottom: 10px; margin-left: 10px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="bi bi-robot"></i> Jarvis AI</a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-secondary small" id="user-email-display"></span>
                <button class="btn btn-outline-danger btn-sm" onclick="logout()">Sair</button>
            </div>
        </div>
    </nav>

    <div class="d-flex flex-grow-1 overflow-hidden">
        
        <main class="d-flex flex-column flex-grow-1 position-relative">
            <div id="chat-container" class="d-flex flex-column p-4">
                </div>
            
            <div id="typing" class="typing-indicator">Jarvis está pensando...</div>

            <div class="p-3 bg-dark border-top border-secondary">
                <div class="input-group">
                    <input type="text" id="user-input" class="form-control" placeholder="Digite sua mensagem..." autocomplete="off">
                    <button class="btn btn-primary" id="send-btn" onclick="sendMessage()">
                        <i class="bi bi-send-fill"></i> Enviar
                    </button>
                </div>
            </div>
        </main>

        <aside id="debug-panel" class="debug-sidebar p-3 d-none">
            <h6 class="text-uppercase text-secondary fw-bold mb-3 border-bottom border-secondary pb-2">
                <i class="bi bi-cpu"></i> Painel Neural
            </h6>

            <div class="mb-4">
                <small class="text-success text-uppercase fw-bold"><i class="bi bi-database"></i> Memória (Facts)</small>
                <div id="debug-facts" class="mt-2 d-flex flex-column gap-2"></div>
            </div>

            <div class="mb-4">
                <small class="text-warning text-uppercase fw-bold"><i class="bi bi-tools"></i> Tools Activity</small>
                <div id="debug-tools" class="mt-2">
                    <span class="text-muted fst-italic">Nenhuma ação recente.</span>
                </div>
            </div>

            <div class="mb-4">
                <small class="text-info text-uppercase fw-bold"><i class="bi bi-search"></i> Contexto (RAG)</small>
                <div id="debug-rag" class="mt-2">
                    <span class="text-muted fst-italic">Nenhuma memória antiga usada.</span>
                </div>
            </div>
            
             <div class="mb-4">
                <small class="text-danger text-uppercase fw-bold"><i class="bi bi-list-check"></i> Tarefas</small>
                <div id="debug-tasks" class="mt-2 d-flex flex-column gap-2"></div>
            </div>
        </aside>
    </div>

    <div class="modal fade" id="loginModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Login</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" id="auth-email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" id="auth-pass" class="form-control">
                    </div>
                    <div id="auth-error" class="text-danger small mb-3"></div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="authAction()">Entrar / Registrar</button>
                        <button class="btn btn-link text-decoration-none" onclick="toggleAuthMode()" id="toggle-auth-btn">Não tem conta? Crie uma.</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Variáveis de Estado
        let isRegister = false;
        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        const chatContainer = document.getElementById('chat-container');
        const userInput = document.getElementById('user-input');
        const typingIndicator = document.getElementById('typing');

        // --- Lógica de Auth ---
        function showLogin() { loginModal.show(); }
        
        function toggleAuthMode() {
            isRegister = !isRegister;
            document.getElementById('modalTitle').innerText = isRegister ? 'Nova Conta' : 'Login';
            document.getElementById('toggle-auth-btn').innerText = isRegister ? 'Já tenho conta. Login.' : 'Não tem conta? Crie uma.';
            document.getElementById('auth-error').innerText = '';
        }

        async function authAction() {
            const email = document.getElementById('auth-email').value;
            const password = document.getElementById('auth-pass').value;
            const action = isRegister ? 'register' : 'login';

            try {
                const res = await fetch('auth.php', { method: 'POST', body: JSON.stringify({ action, email, password }) });
                const data = await res.json();

                if (data.success) {
                    loginModal.hide();
                    loadChat();
                } else {
                    document.getElementById('auth-error').innerText = data.error;
                }
            } catch (e) {
                document.getElementById('auth-error').innerText = "Erro de conexão.";
            }
        }
        async function loadChat() {
            try {
                console.log("Iniciando carregamento..."); // Debug
                const res = await fetch('api.php');
                
                // Se der erro de autenticação, mostra login
                if (res.status === 401) {
                    console.log("Não autorizado (401). Mostrando login.");
                    return showLogin(); 
                }

                // Tenta ler o texto bruto antes do JSON para ver se tem erro PHP
                const rawText = await res.text();
                console.log("Resposta bruta do servidor:", rawText);

                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (e) {
                    console.error("Erro ao processar JSON. O servidor retornou HTML/Erro?", e);
                    // Mostra alerta visual se o JSON estiver quebrado
                    document.getElementById('chat-container').innerHTML = `<div class="alert alert-danger">Erro no servidor: Verifique o console (F12).</div>`;
                    return;
                }

                // Se chegou aqui, o JSON é válido. Renderiza.
                document.getElementById('user-email-display').innerText = data.email || 'Usuário';
                
                // Renderiza Mensagens
                const container = document.getElementById('chat-container');
                container.innerHTML = '';
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => appendMessage(msg.role, msg.content));
                } else {
                    container.innerHTML = '<div class="text-muted text-center mt-5">Nenhuma conversa encontrada.</div>';
                }

                // Renderiza Debug
                if (data.debug_mode) {
                    document.getElementById('debug-panel').classList.remove('d-none');
                    updateDebugPanel(data);
                }
                
                scrollToBottom();

            } catch (e) {
                console.error("Erro fatal no loadChat:", e);
            }
        }

        function appendMessage(role, text) {
            const div = document.createElement('div');
            div.className = `msg-bubble ${role === 'user' ? 'msg-user' : 'msg-bot'}`;
            // Converte quebras de linha em <br> e formata markdown simples se necessário
            div.innerHTML = text.replace(/\n/g, '<br>');
            chatContainer.appendChild(div);
        }

        function scrollToBottom() {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        async function sendMessage() {
            const text = userInput.value.trim();
            if (!text) return;

            appendMessage('user', text);
            userInput.value = '';
            userInput.disabled = true;
            scrollToBottom();
            
            // Mostra "Digitando..."
            typingIndicator.style.display = 'block';

            try {
                const res = await fetch('api.php', { method: 'POST', body: JSON.stringify({ message: text }) });
                const data = await res.json();

                if (data.error) {
                    appendMessage('assistant', "⚠️ Erro: " + data.error);
                } else {
                    appendMessage('assistant', data.reply);
                    updateDebugPanel(data); // Atualiza lateral com dados frescos
                }
            } catch (e) {
                appendMessage('assistant', "Erro fatal de conexão.");
            } finally {
                userInput.disabled = false;
                typingIndicator.style.display = 'none';
                userInput.focus();
                scrollToBottom();
            }
        }

        // --- Lógica de Atualização do Debug ---
        function updateDebugPanel(data) {
            // Facts
            const factsDiv = document.getElementById('debug-facts');
            if (data.debug_facts && data.debug_facts.length > 0) {
                factsDiv.