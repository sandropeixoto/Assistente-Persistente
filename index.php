<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistente Pessoal + Debug</title>
    <style>
        :root { --bg: #f0f2f5; --chat-bg: #fff; --user-msg: #007bff; --bot-msg: #e4e6eb; --debug-bg: #1e1e1e; --debug-text: #00ff00; }
        body { font-family: sans-serif; background: var(--bg); margin: 0; display: flex; height: 100vh; overflow: hidden; }
        
        /* Layout Principal */
        .app-container { display: flex; width: 100%; height: 100%; }
        
        /* Área do Chat (Esquerda) */
        .chat-section { flex: 1; display: flex; flex-direction: column; background: var(--chat-bg); box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        header { padding: 15px; border-bottom: 1px solid #ddd; font-weight: bold; background: #fff; display: flex; justify-content: space-between; }
        #chat-box { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; }
        .input-area { padding: 20px; border-top: 1px solid #ddd; display: flex; gap: 10px; }
        input { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 25px; outline: none; }
        button { padding: 10px 25px; background: var(--user-msg); color: white; border: none; border-radius: 25px; cursor: pointer; }

        /* Mensagens */
        .message { max-width: 80%; padding: 10px 15px; border-radius: 15px; font-size: 15px; line-height: 1.4; word-wrap: break-word; }
        .message.user { align-self: flex-end; background: var(--user-msg); color: white; }
        .message.assistant { align-self: flex-start; background: var(--bot-msg); color: #000; }
        .message.system { align-self: center; font-size: 12px; color: #888; }

        /* Área de Debug (Direita) */
        .debug-section { width: 350px; background: var(--debug-bg); color: var(--debug-text); display: none; flex-direction: column; font-family: 'Courier New', monospace; font-size: 12px; border-left: 1px solid #333; }
        .debug-header { padding: 15px; background: #2d2d2d; font-weight: bold; border-bottom: 1px solid #333; color: #fff; }
        .debug-content { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 20px; }
        
        .debug-block h4 { color: #fff; margin: 0 0 5px 0; border-bottom: 1px solid #444; padding-bottom: 3px; }
        .fact-item { margin-bottom: 4px; color: #ccc; }
        .fact-key { color: #ff79c6; font-weight: bold; }
        .log-entry { margin-bottom: 10px; border-bottom: 1px dashed #444; padding-bottom: 5px; }
        .log-time { color: #888; font-size: 10px; }
    </style>
</head>
<body>

<div class="app-container">
    <div class="chat-section">
        <header>
            <span>Assistente Pessoal</span>
            <span id="status-badge" style="font-size:12px; color: green;">Online</span>
        </header>
        <div id="chat-box"></div>
        <div class="input-area">
            <input type="text" id="user-input" placeholder="Digite..." autocomplete="off">
            <button id="send-btn">Enviar</button>
        </div>
    </div>

    <div class="debug-section" id="debug-sidebar">
        <div class="debug-header">🛠️ Debug & Monitoramento</div>
        <div class="debug-content">
            
            <div class="debug-block">
                <h4>Memória Permanente (Facts)</h4>
                <div id="facts-list">Carregando...</div>
            </div>

            <div class="debug-block">
                <h4>Log de Decisão (Última Ação)</h4>
                <div id="decision-log" style="color: #f1fa8c;">Aguardando interação...</div>
            </div>

        </div>
    </div>
</div>

<script>
    const chatBox = document.getElementById('chat-box');
    const userInput = document.getElementById('user-input');
    const sendBtn = document.getElementById('send-btn');
    const debugSidebar = document.getElementById('debug-sidebar');
    const factsList = document.getElementById('facts-list');
    const decisionLog = document.getElementById('decision-log');

    // Carregar dados iniciais
    async function loadData() {
        try {
            const res = await fetch('api.php');
            const data = await res.json();
            
            // Renderiza mensagens
            chatBox.innerHTML = '';
            if(data.messages) {
                data.messages.forEach(msg => appendMessage(msg.role, msg.content));
            }

            // Ativa Debug se config for true
            if(data.debug_mode) {
                debugSidebar.style.display = 'flex';
                renderFacts(data.facts);
            }

        } catch (e) { console.error(e); }
    }

    function renderFacts(facts) {
        factsList.innerHTML = '';
        facts.forEach(fact => {
            const div = document.createElement('div');
            div.className = 'fact-item';
            div.innerHTML = `<span class="fact-key">${fact.key}:</span> ${fact.value}`;
            factsList.appendChild(div);
        });
    }

    function appendMessage(role, text) {
        const div = document.createElement('div');
        div.classList.add('message', role);
        div.innerHTML = text.replace(/\n/g, '<br>');
        chatBox.appendChild(div);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    async function sendMessage() {
        const text = userInput.value.trim();
        if (!text) return;

        appendMessage('user', text);
        userInput.value = '';
        userInput.disabled = true;

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text })
            });
            const data = await res.json();
            
            if (data.error) throw new Error(data.error);
            
            appendMessage('assistant', data.reply);

            // ATUALIZA LOG DE DEBUG
            if (debugSidebar.style.display !== 'none') {
                const time = new Date().toLocaleTimeString();
                decisionLog.innerHTML = `
                    <div class="log-entry">
                        <div class="log-time">[${time}] Envio do Usuário</div>
                        <div>Keywords: ${data.debug_used_keywords || 'Nenhuma'}</div>
                        <div style="margin-top:5px; color:#ccc">${data.debug_rag}</div>
                    </div>
                ` + decisionLog.innerHTML;
            }

        } catch (err) {
            appendMessage('system', 'Erro: ' + err.message);
        } finally {
            userInput.disabled = false;
            userInput.focus();
        }
    }

    sendBtn.addEventListener('click', sendMessage);
    userInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });

    loadData();
</script>

</body>
</html>