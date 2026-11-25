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
    <link rel="stylesheet" href="assets/app.css?v=20261113-voz-hf7">
    <script>
        window.CODEX_BOOT = {
            authenticated: <?= $user ? 'true' : 'false' ?>,
            csrf: <?= json_encode($csrf) ?>,
            user: <?= json_encode($user ? ['name' => $nome, 'email' => (string)($user['email'] ?? '')] : null) ?>
        };
    </script>
    <script src="assets/app.js?v=20261113-voz-hf7" defer></script>
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
                <div class="voice-status" id="voiceStatus" hidden>
                    <span class="voice-orb" id="voiceOrb" data-estado="off" hidden aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle class="orb-ring" cx="12" cy="12" r="9"></circle>
                            <rect class="orb-bar orb-b1" x="7" y="9" width="2" height="6" rx="1"></rect>
                            <rect class="orb-bar orb-b2" x="11" y="6" width="2" height="12" rx="1"></rect>
                            <rect class="orb-bar orb-b3" x="15" y="9" width="2" height="6" rx="1"></rect>
                        </svg>
                    </span>
                    <span id="voiceStatusText"></span>
                    <button class="voice-link" id="voiceStop" type="button" hidden>parar</button>
                    <button class="voice-link" id="voiceMute" type="button" hidden>mudo</button>
                </div>
                <div class="composer-row">
                    <div class="composer-tools">
                        <button class="icon-btn attach-btn" id="attachBtn" type="button" aria-label="Anexar arquivos" title="Anexar arquivos">&#128206;</button>
                        <button class="icon-btn mic-btn" id="micBtn" type="button" aria-label="Falar" title="Falar: grava, transcreve e envia" aria-pressed="false">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="9" y="2" width="6" height="11" rx="3"></rect>
                                <path d="M5 10v1a7 7 0 0 0 14 0v-1"></path>
                                <line x1="12" y1="19" x2="12" y2="22"></line>
                            </svg>
                        </button>
                        <button class="icon-btn handsfree-btn" id="handsFreeBtn" type="button" aria-label="Mãos livres" title="Mãos livres: ouve, detecta quando você para e envia sozinho" aria-pressed="false">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 10v4"></path>
                                <path d="M8 6v12"></path>
                                <path d="M12 8v8"></path>
                                <path d="M16 4v16"></path>
                                <path d="M20 9v6"></path>
                            </svg>
                        </button>
                    </div>
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








