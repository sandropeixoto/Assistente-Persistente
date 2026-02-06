<?php
try {
    // Conexão SQLite
    $pdo = new PDO('sqlite:database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Tabela Usuários
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Mensagens
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        topic_id INTEGER NULL,
        role TEXT NOT NULL,
        content TEXT NOT NULL,
        edited_at DATETIME NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // Migração de colunas para mensagens em instalações antigas
    $msgCols = $pdo->query("PRAGMA table_info(messages)")->fetchAll(PDO::FETCH_ASSOC);
    $msgColNames = array_column($msgCols, 'name');
    if (!in_array('topic_id', $msgColNames, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN topic_id INTEGER NULL");
    }
    if (!in_array('edited_at', $msgColNames, true)) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN edited_at DATETIME NULL");
    }

    // 3. Fatos (Memória)
    $pdo->exec("CREATE TABLE IF NOT EXISTS facts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        key TEXT NOT NULL,
        value TEXT NOT NULL,
        UNIQUE(user_id, key),
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");


    // 4. Tópicos de conversa
    $pdo->exec("CREATE TABLE IF NOT EXISTS topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // 5. Tarefas
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        description TEXT NOT NULL,
        status TEXT DEFAULT 'pending',
        priority INTEGER DEFAULT 2,
        due_date DATETIME NULL,
        completed_at DATETIME NULL,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // Migração de colunas para instalações antigas
    $taskCols = $pdo->query("PRAGMA table_info(tasks)")->fetchAll(PDO::FETCH_ASSOC);
    $taskColNames = array_column($taskCols, 'name');

    if (!in_array('priority', $taskColNames, true)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN priority INTEGER DEFAULT 2");
    }
    if (!in_array('due_date', $taskColNames, true)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN due_date DATETIME NULL");
    }
    if (!in_array('completed_at', $taskColNames, true)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN completed_at DATETIME NULL");
    }

    // 6. Resumos de sessão (memória semântica)
    $pdo->exec("CREATE TABLE IF NOT EXISTS session_summaries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        summary TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // 7. Tags semânticas de perfil
    $pdo->exec("CREATE TABLE IF NOT EXISTS profile_tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        tag TEXT NOT NULL,
        score INTEGER DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, tag),
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

} catch (Exception $e) {
    die("Erro DB: " . $e->getMessage());
}
?>
