<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Sem cache: garante que o navegador pegue sempre a versao nova do painel.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$user = current_user();
$csrf = csrf_token();
$nome = $user['name'] ?? $user['email'] ?? '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Codex · Daniel Tatuador</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="assets/app.css?v=20261004-5">
    <script>
        window.CODEX_BOOT = {
            authenticated: <?= $user ? 'true' : 'false' ?>,
            csrf: <?= json_encode($csrf) ?>,
            user: <?= json_encode($user ? ['name' => $nome] : null) ?>
        };
    </script>
    <script src="assets/app.js?v=20261031-dsh2" defer></script>
</head>
<body class="<?= $user ? 'is-app' : 'is-login' ?>">
<?php if (!$user): ?>
    <main class="login-shell">
        <section class="login-visual">
            <div class="brand-mark">&gt;_</div>
            <h1>Codex</h1>
            <p>Converse com o Codex que roda na sua maquina, veja as sessoes e continue de onde parou.</p>
            <div class="login-tags">
                <span>Local</span>
                <span>Sessoes</span>
                <span>Terminal</span>
            </div>
        </section>
        <section class="login-panel">
            <form id="loginForm" class="form-stack">
                <h2>Entrar</h2>
                <p class="muted">Use o mesmo login do Plan.</p>
                <label>
                    E-mail
                    <input id="loginEmail" name="email" type="email" value="danielaraujodasilva@gmail.com" autocomplete="email" required>
                </label>
                <label>
                    Senha
                    <input id="loginPassword" name="password" type="password" autocomplete="current-password" required>
                </label>
                <button class="primary-btn" type="submit">Acessar Codex</button>
                <p class="form-message" id="loginMessage"></p>
            </form>
        </section>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-top">
                <span class="logo-mark">&gt;_</span>
                <strong>Codex</strong>
            </div>
            <button class="primary-btn new-chat" id="newChat" type="button">+ Nova conversa</button>
            <input class="search" id="sessionSearch" type="search" placeholder="Buscar conversa..." autocomplete="off">
            <button class="link-btn trash-btn" id="trashBtn" type="button">Apagadas</button>
            <nav class="session-list" id="sessionList">
                <p class="muted">Carregando sessoes...</p>
            </nav>
            <div class="sidebar-foot">
                <span class="user" title="<?= htmlspecialchars((string)$nome, ENT_QUOTES) ?>"><?= htmlspecialchars((string)$nome, ENT_QUOTES) ?></span>
                <button class="link-btn" id="logoutBtn" type="button">Sair</button>
            </div>
        </aside>
        <button class="scrim" id="scrim" type="button" aria-label="Fechar conversas" hidden></button>
        <main class="chat">
            <header class="chat-head">
                <div class="chat-head-left">
                    <button class="icon-btn" id="menuBtn" type="button" aria-label="Abrir conversas">&#9776;</button>
                    <div class="chat-title-wrap">
                        <h2 id="chatTitle">Nova conversa</h2>
                        <p class="muted" id="chatMeta"></p>
                    </div>
                </div>
                <div class="bridge" id="bridgeBox">
                    <span class="dot" id="bridgeDot"></span>
                    <span id="bridgeText">verificando...</span>
                </div>
            </header>
            <div class="messages" id="messages">
                <div class="empty">
                    <p>Manda a primeira mensagem pro Codex.</p>
                    <p class="muted">Ele roda na sua maquina, com acesso aos arquivos e terminais.</p>
                </div>
            </div>
            <footer class="composer">
                <div class="attach-chips" id="attachChips" hidden></div>
                <div class="queue" id="queueList" hidden></div>
                <div class="composer-row">
                    <button class="icon-btn attach-btn" id="attachBtn" type="button" aria-label="Anexar arquivos" title="Anexar arquivos">&#128206;</button>
                    <textarea id="prompt" rows="1" placeholder="Escreva um pedido..." autocomplete="off"></textarea>
                    <div class="composer-actions">
                        <button class="stop-btn" id="stopBtn" type="button" hidden>Parar</button>
                        <button class="primary-btn send-btn" id="sendBtn" type="button">Enviar</button>
                    </div>
                </div>
                <input type="file" id="fileInput" multiple hidden accept="image/*,.pdf,.txt,.csv,.json,.md,.log,.xml,.sql,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.php,.js,.ts,.py,.html,.css,.sh,.bat,.ps1,.mp3,.wav,.ogg,.mp4,.webm,.mov">
            </footer>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
