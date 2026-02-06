<?php
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

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
        .msg { max-width: 85%; padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; word-wrap: break-word; position: relative; }
        .msg-user { background-color: #0d6efd; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-bot { background-color: #2c3035; color: #e9ecef; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #3a3f45; }
        .edit-link { position: absolute; top: 6px; right: 10px; font-size: .7rem; color: #cfe2ff; cursor: pointer; text-decoration: underline; }
        #debug-sidebar { width: 340px; background-color: #1a1d20; border-left: 1px solid #343a40; padding: 15px; overflow-y: auto; font-family: 'Consolas', monospace; font-size: 0.85rem; display: none; }
        .input-area { background-color: #1a1d20; padding: 20px; border-top: 1px solid #343a40; }
        .debug-title { text-transform: uppercase; font-weight: bold; font-size: 0.75rem; color: #adb5bd; margin-bottom: 10px; display: block; }
        #search-results { max-height: 180px; overflow-y: auto; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#"><i class="bi bi-cpu-fill text-primary"></i> Jarvis AI</a>
        <div class="d-flex align-items-center gap-2 me-3">
            <select id="topic-select" class="form-select form-select-sm bg-dark text-light border-secondary" style="min-width:220px"></select>
            <button class="btn btn-outline-primary btn-sm" onclick="createTopic()"><i class="bi bi-plus-lg"></i> Tópico</button>
        </div>
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
            <div class="input-group mb-2">
                <input type="text" id="search-input" class="form-control bg-dark text-light border-secondary" placeholder="Buscar no histórico...">
                <button class="btn btn-outline-info" onclick="searchHistory()"><i class="bi bi-search"></i></button>
            </div>
            <div class="input-group">
                <input type="text" id="user-input" class="form-control bg-dark text-light border-secondary" placeholder="Digite sua mensagem..." autocomplete="off">
                <button class="btn btn-primary" onclick="sendMessage()"><i class="bi bi-send-fill"></i></button>
            </div>
            <div id="edit-banner" class="small text-warning mt-2" style="display:none"></div>
        </div>
    </main>

    <aside id="debug-sidebar">
        <div class="mb-4">
            <span class="debug-title">Busca no histórico</span>
            <div id="search-results"><em class="text-muted">Sem busca.</em></div>
        </div>
        <div class="mb-4">
            <span class="debug-title">Memória</span>
            <div id="debug-facts"><em class="text-muted">Carregando...</em></div>
        </div>
        <div class="mb-4">
            <span class="debug-title">Tarefas</span>
            <div id="debug-tasks"></div>
        </div>
        <div class="mb-4">
            <span class="debug-title">Perfil Semântico</span>
            <div id="debug-tags"></div>
        </div>
        <div>
            <span class="debug-title">Resumos de Sessão</span>
            <div id="debug-summaries"></div>
        </div>
    </aside>
</div>

<script>
    const msgBox = document.getElementById('messages-box');
    let currentTopicId = null;
    let editingMessageId = null;

    function escapeHtml(text) {
        return String(text).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
    }

    function formatText(text) { return escapeHtml(text).replace(/\n/g, '<br>'); }
    function scrollToBottom() { msgBox.scrollTop = msgBox.scrollHeight; }

    function renderTopics(topics = []) {
        const sel = document.getElementById('topic-select');
        sel.innerHTML = topics.map(t => `<option value="${t.id}">${escapeHtml(t.title)}</option>`).join('');
        if (currentTopicId) sel.value = String(currentTopicId);
    }

    function addBubble(message) {
        const div = document.createElement('div');
        const role = message.role;
        div.className = `msg ${role === 'user' ? 'msg-user' : 'msg-bot'}`;
        div.dataset.messageId = message.id || '';
        div.dataset.role = role;

        if (role === 'user' && message.id) {
            const editLink = document.createElement('span');
            editLink.className = 'edit-link';
            editLink.textContent = 'editar';
            editLink.onclick = () => startEdit(message.id, message.content);
            div.appendChild(editLink);
        }

        const txt = document.createElement('div');
        txt.innerHTML = formatText(message.content || '');
        div.appendChild(txt);
        msgBox.appendChild(div);
        return div;
    }

    async function streamIntoBubble(bubble, fullText) {
        const target = bubble.querySelector('div:last-child');
        target.innerHTML = '';
        let out = '';
        for (const ch of String(fullText)) {
            out += ch;
            target.innerHTML = formatText(out);
            await new Promise(r => setTimeout(r, 8));
        }
    }

    function updateDebug(data) {
        document.getElementById('debug-facts').innerHTML = (data.debug_facts || []).map(f => `<div class="text-info mb-1">${escapeHtml(f.key)}: <span class="text-white">${escapeHtml(f.value)}</span></div>`).join('') || '<em class="text-muted">Vazio</em>';
        document.getElementById('debug-tasks').innerHTML = (data.debug_tasks || []).map(t => { const pr = Number(t.priority||2); const prLabel = pr===1?'Alta':(pr===3?'Baixa':'Média'); const due = t.due_date ? ` | prazo: ${escapeHtml(String(t.due_date).slice(0,16))}` : ''; const done = t.status === 'completed' ? '✅' : '🟡'; return `<div class="text-warning mb-1">${done} #${escapeHtml(t.id)} ${escapeHtml(t.description)} <span class="text-secondary">[${prLabel}${due}]</span></div>`; }).join('') || '<em class="text-muted">Vazio</em>';
        document.getElementById('debug-tags').innerHTML = (data.debug_profile_tags || []).map(t => `<div class="text-success mb-1">#${escapeHtml(t.tag)} <span class="text-secondary">(${escapeHtml(t.score)})</span></div>`).join('') || '<em class="text-muted">Vazio</em>';
        document.getElementById('debug-summaries').innerHTML = (data.debug_session_summaries || []).map(s => `<div class="text-light small mb-2">• ${escapeHtml(s)}</div>`).join('') || '<em class="text-muted">Vazio</em>';
    }

    function showSearchResults(list) {
        document.getElementById('search-results').innerHTML = (list || []).map(r => `<div class="mb-2"><span class="text-info">[${escapeHtml(r.topic_title || 'Sem tópico')}]</span> <span class="text-muted">#${r.id}</span><br><span>${formatText(String(r.content).slice(0,120))}</span></div>`).join('') || '<em class="text-muted">Nenhum resultado.</em>';
    }

    async function initChat() {
        try {
            const res = await fetch('api.php');
            if (res.redirected || res.status === 401) { window.location.href = 'login.php'; return; }
            const data = await res.json();

            currentTopicId = data.current_topic_id || null;
            renderTopics(data.topics || []);

            msgBox.innerHTML = '';
            if (data.messages?.length) data.messages.forEach(m => addBubble(m));
            else msgBox.innerHTML = '<div class="text-center text-muted mt-5">Inicie a conversa...</div>';

            if (data.debug_mode) {
                document.getElementById('debug-sidebar').style.display = 'block';
                updateDebug(data);
            }
            showSearchResults([]);
            scrollToBottom();
        } catch (e) { console.error('Erro Load:', e); }
    }

    async function createTopic() {
        const title = prompt('Nome do novo tópico:');
        if (title === null) return;
        const res = await fetch('api.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'create_topic', title }) });
        const data = await res.json();
        if (data.success) {
            currentTopicId = data.current_topic_id;
            await initChat();
        }
    }

    document.getElementById('topic-select').addEventListener('change', async (e) => {
        const topicId = Number(e.target.value || 0);
        if (!topicId) return;
        await fetch('api.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'switch_topic', topic_id: topicId }) });
        currentTopicId = topicId;
        await initChat();
    });

    async function searchHistory() {
        const q = document.getElementById('search-input').value.trim();
        const res = await fetch('api.php?q=' + encodeURIComponent(q));
        const data = await res.json();
        showSearchResults(data.search_results || []);
    }

    function startEdit(id, content) {
        editingMessageId = id;
        document.getElementById('user-input').value = content || '';
        const b = document.getElementById('edit-banner');
        b.style.display = 'block';
        b.innerHTML = `Editando mensagem #${id}. Pressione Enter para salvar como edição.`;
        document.getElementById('user-input').focus();
    }

    async function sendMessage() {
        const inp = document.getElementById('user-input');
        const txt = inp.value.trim();
        if (!txt) return;

        inp.disabled = true;
        document.getElementById('typing-indicator').style.display = 'block';

        try {
            if (editingMessageId) {
                const res = await fetch('api.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'edit_message', message_id: editingMessageId, content: txt }) });
                const data = await res.json();
                addBubble({ role: 'assistant', content: data.message || 'Mensagem editada.' });
                editingMessageId = null;
                document.getElementById('edit-banner').style.display = 'none';
                inp.value = '';
                await initChat();
            } else {
                addBubble({ role: 'user', content: txt });
                inp.value = '';
                scrollToBottom();

                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ message: txt, topic_id: currentTopicId })
                });
                const data = await res.json();

                if (data.error) addBubble({ role: 'assistant', content: 'Erro: ' + data.error });
                else {
                    const bubble = addBubble({ role: 'assistant', content: '' });
                    await streamIntoBubble(bubble, data.stream_text || data.reply || '');
                    updateDebug(data);
                    currentTopicId = data.current_topic_id || currentTopicId;
                    renderTopics(data.topics || []);
                }
            }
        } catch (e) {
            addBubble({ role: 'assistant', content: 'Erro de conexão.' });
        }

        inp.disabled = false;
        inp.focus();
        document.getElementById('typing-indicator').style.display = 'none';
        scrollToBottom();
    }

    document.getElementById('user-input').addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });
    document.getElementById('search-input').addEventListener('keypress', e => { if (e.key === 'Enter') searchHistory(); });
    initChat();
</script>
</body>
</html>
