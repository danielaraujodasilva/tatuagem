<?php
require_once __DIR__ . '/../../auth/auth.php';
require_staff();
require_once __DIR__ . '/../data_store.php';
require_once __DIR__ . '/../../includes/system_settings.php';

date_default_timezone_set('America/Sao_Paulo');
$quickReplies = array_values(array_filter(crmCarregarRespostasRapidas(), static function (array $resposta): bool {
    return !empty($resposta['ativo']);
}));
$valorPomadaAnestesica = system_pomada_unit_price();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Web - CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --wa-green: #00a884;
            --wa-green-dark: #008069;
            --wa-bg: #0b141a;
            --wa-panel: #111b21;
            --wa-panel-2: #202c33;
            --wa-line: #2a3942;
            --wa-text: #e9edef;
            --wa-muted: #8696a0;
            --wa-bubble-in: #202c33;
            --wa-bubble-out: #005c4b;
            --wa-input: #2a3942;
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            overflow: hidden;
            background:
                linear-gradient(var(--wa-green-dark), var(--wa-green-dark)) top / 100% 128px no-repeat,
                #0c1317;
            color: var(--wa-text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .wa-shell {
            width: min(100vw - 38px, 1560px);
            height: min(100vh - 38px, 980px);
            margin: 19px auto;
            display: grid;
            grid-template-columns: 420px minmax(0, 1fr);
            min-height: 0;
            overflow: hidden;
            background: var(--wa-panel);
            box-shadow: 0 16px 46px rgba(0, 0, 0, 0.35);
        }

        .wa-sidebar {
            min-width: 0;
            min-height: 0;
            height: 100%;
            overflow: hidden;
            border-right: 1px solid var(--wa-line);
            display: flex;
            flex-direction: column;
            background: var(--wa-panel);
        }

        .wa-topbar {
            height: 60px;
            flex: 0 0 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: var(--wa-panel-2);
        }

        .wa-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .wa-avatar {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            color: #d9fdd3;
            background: #1f544a;
            font-weight: 800;
            overflow: hidden;
        }

        .wa-name {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-size: 15px;
            font-weight: 600;
        }

        .wa-subtitle {
            margin-top: 3px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            color: var(--wa-muted);
            font-size: 12px;
        }

        .wa-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .wa-icon-btn {
            width: 40px;
            height: 40px;
            border: 0;
            border-radius: 999px;
            display: grid;
            place-items: center;
            color: var(--wa-muted);
            background: transparent;
            cursor: pointer;
            text-decoration: none;
            font-size: 17px;
        }

        .wa-icon-btn:hover { background: rgba(255, 255, 255, 0.07); color: var(--wa-text); }

        .wa-action-pill {
            min-height: 36px;
            border: 0;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 13px;
            color: var(--wa-text);
            background: rgba(255, 255, 255, 0.07);
            cursor: pointer;
            text-decoration: none;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
        }

        .wa-action-pill:hover,
        .wa-action-pill.active {
            color: #02130f;
            background: #00a884;
        }

        .wa-status-select {
            width: 138px;
            min-height: 36px;
            border: 1px solid rgba(134, 150, 160, 0.24);
            border-radius: 999px;
            padding: 0 11px;
            color: var(--wa-text);
            background: rgba(255, 255, 255, 0.06);
            outline: 0;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
        }

        .wa-status-select option {
            color: var(--wa-text);
            background: var(--wa-panel);
        }

        .wa-toggle {
            display: inline-flex;
            padding: 3px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.18);
        }

        .wa-toggle button {
            min-height: 30px;
            border: 0;
            border-radius: 999px;
            padding: 0 11px;
            color: var(--wa-muted);
            background: transparent;
            cursor: pointer;
            font: inherit;
            font-size: 12px;
            font-weight: 800;
        }

        .wa-toggle button.active {
            color: #02130f;
            background: #00a884;
        }

        .wa-search {
            padding: 8px 12px;
            background: var(--wa-panel);
            border-bottom: 1px solid var(--wa-line);
        }

        .wa-search-box {
            height: 38px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 14px;
            border-radius: 9px;
            color: var(--wa-muted);
            background: var(--wa-panel-2);
        }

        .wa-search-box input {
            width: 100%;
            border: 0;
            outline: 0;
            color: var(--wa-text);
            background: transparent;
            font: inherit;
            font-size: 14px;
        }

        .wa-filter-row {
            display: flex;
            gap: 8px;
            padding: 0 12px 10px;
            border-bottom: 1px solid var(--wa-line);
        }

        .wa-chip {
            border: 0;
            border-radius: 999px;
            padding: 7px 13px;
            color: var(--wa-muted);
            background: var(--wa-panel-2);
            cursor: pointer;
            font-weight: 600;
        }

        .wa-chip.active {
            color: #d9fdd3;
            background: rgba(0, 168, 132, 0.18);
        }

        .wa-chat-list {
            flex: 1 1 auto;
            height: 0;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-color: #374248 transparent;
            scrollbar-width: thin;
        }

        .wa-chat-list::-webkit-scrollbar {
            width: 8px;
        }

        .wa-chat-list::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: #374248;
        }

        .wa-chat-item {
            min-height: 74px;
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr);
            gap: 12px;
            padding: 10px 14px;
            border: 0;
            border-bottom: 1px solid rgba(42, 57, 66, 0.72);
            color: inherit;
            background: transparent;
            cursor: pointer;
            text-align: left;
            width: 100%;
        }

        .wa-chat-item:hover,
        .wa-chat-item.active { background: #2a3942; }

        .wa-chat-main { min-width: 0; }
        .wa-chat-line {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            min-width: 0;
        }
        .wa-chat-title {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            color: var(--wa-text);
            font-size: 16px;
        }
        .wa-chat-time {
            flex: 0 0 auto;
            color: var(--wa-muted);
            font-size: 12px;
        }
        .wa-chat-preview {
            margin-top: 6px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            color: var(--wa-muted);
            font-size: 14px;
        }

        .wa-chat-meta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 7px;
            color: var(--wa-muted);
            font-size: 12px;
        }

        .wa-stage-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--wa-green);
        }

        .wa-conversation {
            position: relative;
            min-width: 0;
            min-height: 0;
            height: 100%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #0b141a;
        }

        .wa-chat-header {
            min-height: 64px;
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 10px 18px;
            background: var(--wa-panel-2);
        }

        .wa-chat-header-tools {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 7px;
        }

        .wa-empty,
        .wa-loading {
            flex: 1;
            display: grid;
            place-items: center;
            text-align: center;
            color: var(--wa-muted);
            padding: 32px;
        }

        .wa-empty-card {
            max-width: 470px;
        }

        .wa-empty-icon {
            width: 118px;
            height: 118px;
            margin: 0 auto 28px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            color: #6b7c85;
            background: #1f2c33;
            font-size: 58px;
        }

        .wa-empty h1 {
            margin: 0 0 12px;
            color: #d1d7db;
            font-weight: 300;
            font-size: 32px;
        }

        .wa-empty p {
            margin: 0;
            line-height: 1.55;
        }

        .wa-messages {
            position: relative;
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 26px 7.8% 20px;
            background:
                linear-gradient(rgba(11, 20, 26, 0.93), rgba(11, 20, 26, 0.93)),
                radial-gradient(circle at 25px 25px, rgba(255,255,255,0.05) 2px, transparent 2px) 0 0 / 52px 52px,
                radial-gradient(circle at 12px 38px, rgba(255,255,255,0.035) 2px, transparent 2px) 0 0 / 52px 52px;
        }

        .wa-day {
            width: fit-content;
            margin: 0 auto 16px;
            padding: 7px 12px;
            border-radius: 8px;
            color: #d1d7db;
            background: #182229;
            box-shadow: 0 1px 1px rgba(0,0,0,0.2);
            font-size: 12px;
        }

        .wa-bubble-row {
            display: flex;
            margin: 3px 0;
        }

        .wa-bubble-row.out { justify-content: flex-end; }

        .wa-bubble {
            max-width: min(660px, 72%);
            min-width: 86px;
            padding: 8px 10px 6px;
            border-radius: 9px;
            color: var(--wa-text);
            background: var(--wa-bubble-in);
            box-shadow: 0 1px 1px rgba(0,0,0,0.18);
            line-height: 1.42;
            white-space: normal;
            overflow-wrap: anywhere;
            font-size: 14.6px;
        }

        .wa-bubble-row.in + .wa-bubble-row.out,
        .wa-bubble-row.out + .wa-bubble-row.in {
            margin-top: 10px;
        }

        .wa-bubble-row.out .wa-bubble {
            background: var(--wa-bubble-out);
            border-top-right-radius: 4px;
        }

        .wa-bubble-row.in .wa-bubble {
            border-top-left-radius: 4px;
        }

        .wa-bubble-text {
            padding-right: 42px;
            white-space: pre-wrap;
        }

        .wa-transcription {
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            color: #d7f7ee;
            background: rgba(0, 0, 0, 0.22);
            font-size: 13px;
        }

        .wa-transcription.error {
            color: #ffd6d6;
            background: rgba(127, 29, 29, 0.42);
        }

        .wa-media {
            display: block;
            max-width: 280px;
            max-height: 240px;
            margin-bottom: 6px;
            border-radius: 7px;
            object-fit: cover;
        }

        .wa-doc {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 320px;
            margin-bottom: 6px;
            padding: 10px;
            border-radius: 8px;
            color: var(--wa-text);
            background: rgba(0,0,0,0.16);
            text-decoration: none;
        }

        .wa-audio {
            width: min(320px, 64vw);
            display: block;
            margin-bottom: 6px;
        }

        .wa-bubble-time {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            margin-top: 3px;
            color: rgba(233, 237, 239, 0.65);
            font-size: 11px;
        }

        .wa-status-read { color: #53bdeb; }

        .wa-composer {
            min-height: 62px;
            flex: 0 0 auto;
            display: flex;
            align-items: flex-end;
            gap: 8px;
            padding: 10px 16px;
            background: var(--wa-panel-2);
        }

        .wa-attachment-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 16px;
            border-top: 1px solid rgba(134, 150, 160, 0.14);
            background: #1f2c33;
            color: #d1d7db;
            font-size: 13px;
        }

        .wa-attachment-preview button {
            border: 0;
            color: var(--wa-muted);
            background: transparent;
            cursor: pointer;
        }

        .wa-emoji-panel,
        .wa-replies-panel {
            position: absolute;
            left: 16px;
            bottom: 74px;
            z-index: 10;
            width: min(360px, calc(100vw - 32px));
            max-height: 330px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid rgba(134, 150, 160, 0.18);
            border-radius: 14px;
            background: #111b21;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.36);
        }

        .wa-replies-panel {
            left: 68px;
            width: min(440px, calc(100vw - 32px));
        }

        .wa-emoji-panel button {
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            cursor: pointer;
            font-size: 20px;
        }

        .wa-emoji-panel button:hover { background: rgba(255, 255, 255, 0.08); }

        .wa-reply-search {
            width: 100%;
            min-height: 38px;
            margin-bottom: 8px;
            border: 1px solid rgba(134, 150, 160, 0.16);
            border-radius: 10px;
            padding: 0 11px;
            color: var(--wa-text);
            background: #202c33;
            outline: 0;
        }

        .wa-reply-card {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 10px;
            color: var(--wa-text);
            background: transparent;
            text-align: left;
            cursor: pointer;
        }

        .wa-reply-card:hover { background: rgba(255, 255, 255, 0.07); }
        .wa-reply-card strong { display: block; margin-bottom: 4px; font-size: 13px; }
        .wa-reply-card span { display: block; color: var(--wa-muted); font-size: 12px; line-height: 1.35; }

        .wa-composer textarea {
            min-height: 42px;
            max-height: 130px;
            flex: 1;
            resize: none;
            border: 0;
            outline: 0;
            border-radius: 9px;
            padding: 11px 14px;
            color: var(--wa-text);
            background: var(--wa-input);
            font: inherit;
            line-height: 1.4;
        }

        .wa-send {
            width: 46px;
            height: 42px;
            border: 0;
            border-radius: 999px;
            color: var(--wa-text);
            background: transparent;
            cursor: pointer;
            font-size: 20px;
        }

        .wa-send:hover { color: var(--wa-green); }
        .wa-hidden { display: none !important; }

        .wa-modal {
            position: fixed;
            inset: 0;
            z-index: 30;
            display: grid;
            place-items: center;
            padding: 22px;
            background: rgba(0, 0, 0, 0.68);
        }

        .wa-modal-panel {
            width: min(760px, 100%);
            max-height: min(92vh, 860px);
            overflow-y: auto;
            border-radius: 18px;
            background: #111b21;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.55);
        }

        .wa-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--wa-line);
            background: #202c33;
        }

        .wa-modal-title {
            margin: 0;
            font-size: 18px;
        }

        .wa-modal-copy {
            margin: 5px 0 0;
            color: var(--wa-muted);
            font-size: 13px;
        }

        .wa-form {
            padding: 20px;
        }

        .wa-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 13px;
        }

        .wa-field label {
            display: block;
            margin-bottom: 6px;
            color: var(--wa-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .wa-field input,
        .wa-field textarea {
            width: 100%;
            min-height: 42px;
            border: 1px solid rgba(134, 150, 160, 0.18);
            border-radius: 11px;
            padding: 10px 12px;
            color: var(--wa-text);
            background: #202c33;
            outline: 0;
            font: inherit;
        }

        .wa-field textarea { min-height: 88px; resize: vertical; }
        .wa-field.full { grid-column: 1 / -1; }

        .wa-client-results {
            display: grid;
            gap: 8px;
            margin-top: 10px;
        }

        .wa-client-option {
            border: 1px solid rgba(134, 150, 160, 0.15);
            border-radius: 11px;
            padding: 10px 12px;
            color: var(--wa-text);
            background: #202c33;
            text-align: left;
            cursor: pointer;
        }

        .wa-result-box {
            margin-top: 14px;
            padding: 12px;
            border-radius: 12px;
            color: #d9fdd3;
            background: rgba(0, 168, 132, 0.12);
        }

        .wa-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .wa-mobile-back { display: none; }

        @media (max-width: 880px) {
            body { background: var(--wa-panel); }
            .wa-shell {
                width: 100vw;
                height: 100vh;
                margin: 0;
                grid-template-columns: 1fr;
            }
            .wa-conversation { display: none; }
            .wa-shell.chat-open .wa-sidebar { display: none; }
            .wa-shell.chat-open .wa-conversation { display: flex; }
            .wa-mobile-back { display: grid; }
            .wa-bubble { max-width: 88%; }
            .wa-messages { padding: 18px 12px; }
        }
    </style>
</head>
<body>
    <main id="app" class="wa-shell">
        <aside class="wa-sidebar">
            <header class="wa-topbar">
                <div class="wa-profile">
                    <div class="wa-avatar">CRM</div>
                    <div>
                        <div class="wa-name">Atendimento</div>
                        <div class="wa-subtitle">CRM WhatsApp</div>
                    </div>
                </div>
                <div class="wa-actions">
                    <a class="wa-icon-btn" href="../index.php" title="Voltar ao CRM"><i class="fa-solid fa-table-columns"></i></a>
                    <button class="wa-icon-btn" id="refreshBtn" title="Atualizar"><i class="fa-solid fa-rotate"></i></button>
                </div>
            </header>

            <div class="wa-search">
                <label class="wa-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="searchInput" type="search" placeholder="Pesquisar ou comecar uma nova conversa">
                </label>
            </div>

            <div class="wa-filter-row">
                <button class="wa-chip active" data-filter="all">Todas</button>
                <button class="wa-chip" data-filter="unread">Nao lidas</button>
                <button class="wa-chip" data-filter="mine">Minhas</button>
            </div>

            <section id="chatList" class="wa-chat-list">
                <div class="wa-loading">Carregando conversas...</div>
            </section>
        </aside>

        <section class="wa-conversation">
            <div id="emptyState" class="wa-empty">
                <div class="wa-empty-card">
                    <div class="wa-empty-icon"><i class="fa-brands fa-whatsapp"></i></div>
                    <h1>WhatsApp Web</h1>
                    <p>Selecione uma conversa para atender com menos distracao. Esta tela usa os mesmos clientes e mensagens do CRM atual.</p>
                </div>
            </div>

            <div id="chatPanel" class="wa-hidden" style="display: contents;">
                <header class="wa-chat-header">
                    <div class="wa-profile">
                        <button class="wa-icon-btn wa-mobile-back" id="backBtn" title="Voltar"><i class="fa-solid fa-arrow-left"></i></button>
                        <div id="chatAvatar" class="wa-avatar"></div>
                        <div>
                            <div id="chatName" class="wa-name"></div>
                            <div id="chatSubtitle" class="wa-subtitle"></div>
                        </div>
                    </div>
                    <div class="wa-actions">
                        <div class="wa-chat-header-tools">
                            <button id="scheduleBtn" class="wa-action-pill" type="button"><i class="fa-regular fa-calendar-plus"></i> Agendar</button>
                            <div class="wa-toggle" aria-label="Modo de atendimento">
                                <button id="botModeBtn" type="button">IA</button>
                                <button id="humanModeBtn" type="button">Atendente</button>
                            </div>
                            <button id="assumeBtn" class="wa-action-pill" type="button"><i class="fa-solid fa-user-check"></i> Assumir</button>
                            <select id="statusSelect" class="wa-status-select" title="Status">
                                <option value="novo">Novo</option>
                                <option value="lead_quente">Lead quente</option>
                                <option value="agendado">Agendado</option>
                                <option value="sem_retorno">Sem retorno</option>
                                <option value="em_atendimento">Em atendimento</option>
                                <option value="fechado">Fechado</option>
                                <option value="perdido">Perdido</option>
                            </select>
                            <a id="waDirectLink" class="wa-icon-btn" href="#" target="_blank" title="Abrir no WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                            <a id="crmLeadLink" class="wa-icon-btn" href="#" title="Abrir no CRM"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                </header>

                <div id="messages" class="wa-messages"></div>

                <div id="emojiPanel" class="wa-emoji-panel wa-hidden">
                    <button type="button" data-emoji="🙂">🙂</button>
                    <button type="button" data-emoji="😂">😂</button>
                    <button type="button" data-emoji="😍">😍</button>
                    <button type="button" data-emoji="🙏">🙏</button>
                    <button type="button" data-emoji="👍">👍</button>
                    <button type="button" data-emoji="🔥">🔥</button>
                    <button type="button" data-emoji="✨">✨</button>
                    <button type="button" data-emoji="❤️">❤️</button>
                </div>

                <div id="repliesPanel" class="wa-replies-panel wa-hidden">
                    <input id="replySearch" class="wa-reply-search" placeholder="Buscar resposta pronta">
                    <div id="replyList"></div>
                </div>

                <div id="attachmentPreview" class="wa-attachment-preview wa-hidden"></div>

                <form id="composer" class="wa-composer">
                    <button type="button" class="wa-icon-btn" id="emojiBtn" title="Emoji"><i class="fa-regular fa-face-smile"></i></button>
                    <button type="button" class="wa-icon-btn" id="quickReplyBtn" title="Respostas prontas"><i class="fa-solid fa-bolt"></i></button>
                    <button type="button" class="wa-icon-btn" id="attachBtn" title="Anexar arquivo"><i class="fa-solid fa-paperclip"></i></button>
                    <input id="fileInput" class="wa-hidden" type="file" accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.txt">
                    <button type="button" class="wa-icon-btn" id="recordBtn" title="Gravar audio"><i class="fa-solid fa-microphone"></i></button>
                    <textarea id="messageInput" rows="1" placeholder="Digite uma mensagem"></textarea>
                    <button class="wa-send" title="Enviar"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </section>
    </main>

    <div id="scheduleOverlay" class="wa-modal wa-hidden">
        <div class="wa-modal-panel">
            <div class="wa-modal-head">
                <div>
                    <h2 class="wa-modal-title">Agendar tatuagem</h2>
                    <p class="wa-modal-copy">Busque o cliente na ficha ou salve um cadastro basico com nome e telefone.</p>
                </div>
                <button id="closeScheduleBtn" type="button" class="wa-icon-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="scheduleForm" class="wa-form">
                <input type="hidden" id="scheduleClienteId" name="cliente_id">
                <div class="wa-field full">
                    <label for="scheduleClientSearch">Pesquisar cliente</label>
                    <input type="text" id="scheduleClientSearch" placeholder="Nome, telefone ou e-mail">
                    <div id="scheduleClientResults" class="wa-client-results wa-hidden"></div>
                    <p id="scheduleClientNotice" class="wa-modal-copy"></p>
                </div>

                <div class="wa-form-grid">
                    <div class="wa-field">
                        <label for="scheduleNome">Nome do cliente *</label>
                        <input type="text" id="scheduleNome" name="nome" required>
                    </div>
                    <div class="wa-field">
                        <label for="scheduleTelefone">Telefone *</label>
                        <input type="tel" id="scheduleTelefone" name="telefone" required>
                    </div>
                    <div class="wa-field">
                        <label for="scheduleData">Data *</label>
                        <input type="date" id="scheduleData" name="data_tatuagem" required>
                    </div>
                    <div class="wa-field">
                        <label for="scheduleValor">Valor base (R$)</label>
                        <input type="number" step="0.01" id="scheduleValor" name="valor">
                    </div>
                    <div class="wa-field">
                        <label for="scheduleHoraInicio">Hora inicio *</label>
                        <input type="time" id="scheduleHoraInicio" name="hora_inicio" required>
                    </div>
                    <div class="wa-field">
                        <label for="scheduleHoraFim">Hora fim</label>
                        <input type="time" id="scheduleHoraFim" name="hora_fim">
                    </div>
                    <div class="wa-field">
                        <label for="schedulePomadas">Pomadas anestesicas</label>
                        <input type="number" min="0" step="1" id="schedulePomadas" name="pomadas_anestesicas" value="0">
                    </div>
                    <div class="wa-field">
                        <label>Total com pomadas</label>
                        <input type="text" id="scheduleTotalPreview" value="R$ 0,00" readonly>
                    </div>
                    <div class="wa-field full">
                        <label for="scheduleDescricao">Descricao / arte pretendida</label>
                        <input type="text" id="scheduleDescricao" name="descricao">
                    </div>
                    <div class="wa-field full">
                        <label for="scheduleObservacoes">Observacoes</label>
                        <textarea id="scheduleObservacoes" name="observacoes"></textarea>
                    </div>
                    <div class="wa-field full">
                        <label for="scheduleReferencia">Arte de referencia</label>
                        <input type="file" id="scheduleReferencia" name="referencia" accept="image/*,.pdf">
                    </div>
                </div>

                <div id="scheduleResult" class="wa-result-box wa-hidden"></div>
                <div class="wa-modal-actions">
                    <button id="cancelScheduleBtn" type="button" class="wa-action-pill">Cancelar</button>
                    <button id="scheduleSubmit" type="submit" class="wa-action-pill active">Salvar agendamento</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const quickReplies = <?= json_encode($quickReplies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const pomadaUnitPrice = <?= json_encode($valorPomadaAnestesica) ?>;
        const state = {
            clientes: [],
            filtered: [],
            readState: {},
            activeId: '',
            activeFilter: 'all',
            polling: null,
            listPolling: null,
            sending: false,
            pendingTranscriptions: {},
            mediaRecorder: null,
            recordedAudioChunks: [],
            recordedAudioFile: null,
            recordingTimer: null,
            recordingStartedAt: 0,
            scheduleSearchTimer: null,
        };

        const el = {
            app: document.getElementById('app'),
            chatList: document.getElementById('chatList'),
            search: document.getElementById('searchInput'),
            refresh: document.getElementById('refreshBtn'),
            emptyState: document.getElementById('emptyState'),
            chatPanel: document.getElementById('chatPanel'),
            chatAvatar: document.getElementById('chatAvatar'),
            chatName: document.getElementById('chatName'),
            chatSubtitle: document.getElementById('chatSubtitle'),
            messages: document.getElementById('messages'),
            composer: document.getElementById('composer'),
            input: document.getElementById('messageInput'),
            emoji: document.getElementById('emojiBtn'),
            emojiPanel: document.getElementById('emojiPanel'),
            quickReply: document.getElementById('quickReplyBtn'),
            repliesPanel: document.getElementById('repliesPanel'),
            replySearch: document.getElementById('replySearch'),
            replyList: document.getElementById('replyList'),
            attach: document.getElementById('attachBtn'),
            file: document.getElementById('fileInput'),
            record: document.getElementById('recordBtn'),
            attachmentPreview: document.getElementById('attachmentPreview'),
            back: document.getElementById('backBtn'),
            waDirect: document.getElementById('waDirectLink'),
            crmLead: document.getElementById('crmLeadLink'),
            schedule: document.getElementById('scheduleBtn'),
            assume: document.getElementById('assumeBtn'),
            botMode: document.getElementById('botModeBtn'),
            humanMode: document.getElementById('humanModeBtn'),
            status: document.getElementById('statusSelect'),
            scheduleOverlay: document.getElementById('scheduleOverlay'),
            closeSchedule: document.getElementById('closeScheduleBtn'),
            cancelSchedule: document.getElementById('cancelScheduleBtn'),
            scheduleForm: document.getElementById('scheduleForm'),
            scheduleSearch: document.getElementById('scheduleClientSearch'),
            scheduleResults: document.getElementById('scheduleClientResults'),
            scheduleNotice: document.getElementById('scheduleClientNotice'),
            scheduleClienteId: document.getElementById('scheduleClienteId'),
            scheduleNome: document.getElementById('scheduleNome'),
            scheduleTelefone: document.getElementById('scheduleTelefone'),
            scheduleData: document.getElementById('scheduleData'),
            scheduleValor: document.getElementById('scheduleValor'),
            schedulePomadas: document.getElementById('schedulePomadas'),
            scheduleDescricao: document.getElementById('scheduleDescricao'),
            scheduleTotal: document.getElementById('scheduleTotalPreview'),
            scheduleResult: document.getElementById('scheduleResult'),
            scheduleSubmit: document.getElementById('scheduleSubmit'),
        };

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));
        }

        function onlyDigits(value) {
            return String(value ?? '').replace(/\D/g, '');
        }

        function formatCurrency(value) {
            return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        function crmRelativeUrl(path) {
            return new URL(path, new URL('../', window.location.href)).href;
        }

        function statusLabel(status) {
            const labels = {
                novo: 'Novo',
                lead_quente: 'Lead quente',
                agendado: 'Agendado',
                sem_retorno: 'Sem retorno',
                em_atendimento: 'Em atendimento',
                fechado: 'Fechado',
                perdido: 'Perdido',
            };
            return labels[status] || status || 'Novo';
        }

        function atendimentoMode(cliente) {
            const mode = String(cliente?.modo_atendimento || '').toLowerCase();
            if (mode) return mode;
            const atendente = String(cliente?.atendente || '').toLowerCase();
            return atendente && atendente !== 'bot' ? 'humano' : 'bot';
        }

        function normalizeClientPayload(cliente) {
            if (!cliente) return;
            const existing = state.clientes.find(item => String(item.id) === String(cliente.id));
            if (existing) {
                Object.assign(existing, cliente);
            }
            renderList();
            const active = findActive();
            if (active) renderActiveHeader(active);
        }

        function initials(name) {
            const text = String(name || 'Cliente').trim();
            const parts = text.split(/\s+/).slice(0, 2);
            return parts.map(part => part[0] || '').join('').toUpperCase() || 'C';
        }

        function toTime(dateValue) {
            if (!dateValue) return '';
            const date = new Date(String(dateValue).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return '';
            return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        function parseDate(dateValue) {
            if (!dateValue) return null;
            const date = new Date(String(dateValue).replace(' ', 'T'));
            return Number.isNaN(date.getTime()) ? null : date;
        }

        function toDay(dateValue) {
            if (!dateValue) return '';
            const date = new Date(String(dateValue).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return '';
            const today = new Date();
            const sameDay = date.toDateString() === today.toDateString();
            if (sameDay) return 'Hoje';
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            if (date.toDateString() === yesterday.toDateString()) return 'Ontem';
            return date.toLocaleDateString('pt-BR');
        }

        function getMessages(cliente) {
            return Array.isArray(cliente?.mensagens) ? cliente.mensagens : [];
        }

        function lastMessage(cliente) {
            const messages = getMessages(cliente);
            return messages[messages.length - 1] || null;
        }

        function isFromMe(msg) {
            if (!msg) return false;
            if (msg.fromMe || msg.rawFromMe) return true;
            const author = String(msg.de || msg.autor || '').toLowerCase();
            return ['eu', 'me', 'atendente', 'humano', 'bot'].includes(author);
        }

        function unreadCount(cliente) {
            const messages = getMessages(cliente);
            const lastRead = parseDate(state.readState[String(cliente?.id ?? '')] || '');
            let count = 0;
            for (const msg of messages) {
                if (isFromMe(msg)) continue;
                const msgDate = parseDate(msg.data);
                if (!lastRead || !msgDate || msgDate > lastRead) {
                    count++;
                }
            }
            return count;
        }

        async function loadReadState() {
            const response = await fetch('read_state.php', { cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json().catch(() => null);
            if (data?.ok && data.read && typeof data.read === 'object') {
                state.readState = data.read;
            }
        }

        async function markConversationRead(id) {
            const normalizedId = String(id || '').replace(/^wa_/, '');
            if (!normalizedId) return;

            state.readState[normalizedId] = new Date().toISOString().slice(0, 19).replace('T', ' ');
            applyFilters();

            try {
                const response = await fetch('read_state.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ id: normalizedId }),
                });
                const data = await response.json().catch(() => null);
                if (data?.ok && data.read_at) {
                    state.readState[normalizedId] = data.read_at;
                    applyFilters();
                }
            } catch (error) {
                // Mantem a leitura visual local mesmo que a persistencia falhe momentaneamente.
            }
        }

        function previewMessage(msg) {
            if (!msg) return 'Sem mensagens ainda';
            if (msg.transcricao) return 'Audio: ' + msg.transcricao;
            if (msg.texto) return msg.texto;
            if (String(msg.tipo || '').startsWith('image')) return 'Imagem';
            if (String(msg.mediaMime || '').startsWith('image/')) return 'Imagem';
            if (String(msg.mediaMime || '').startsWith('audio/')) return 'Audio';
            if (msg.mediaUrl) return msg.mediaFileName || 'Arquivo';
            return 'Mensagem';
        }

        function sortClientes(clientes) {
            return [...clientes].sort((a, b) => {
                const lastA = lastMessage(a)?.data || a.updated_at || a.created_at || '';
                const lastB = lastMessage(b)?.data || b.updated_at || b.created_at || '';
                return String(lastB).localeCompare(String(lastA));
            });
        }

        async function loadClientes(keepActive = true) {
            const response = await fetch('../api_clientes.php', { cache: 'no-store' });
            if (!response.ok) throw new Error('Nao foi possivel carregar conversas.');
            const clientes = await response.json();
            state.clientes = sortClientes(Array.isArray(clientes) ? clientes : []);
            applyFilters();

            if (keepActive && state.activeId) {
                const active = findActive();
                if (active) renderActiveHeader(active);
            }
        }

        function applyFilters() {
            const query = el.search.value.trim().toLowerCase();
            state.filtered = state.clientes.filter(cliente => {
                const haystack = [
                    cliente.nome,
                    cliente.numero,
                    cliente.status,
                    cliente.interesse,
                    cliente.atendente,
                    previewMessage(lastMessage(cliente)),
                ].join(' ').toLowerCase();
                if (query && !haystack.includes(query)) return false;
                if (state.activeFilter === 'unread' && unreadCount(cliente) === 0) return false;
                if (state.activeFilter === 'mine' && String(cliente.atendente || '').toLowerCase() !== 'humano') return false;
                return true;
            });
            renderList();
        }

        function renderList() {
            if (!state.filtered.length) {
                el.chatList.innerHTML = '<div class="wa-loading">Nenhuma conversa encontrada.</div>';
                return;
            }

            el.chatList.innerHTML = state.filtered.map(cliente => {
                const last = lastMessage(cliente);
                const unread = unreadCount(cliente);
                const active = String(cliente.id) === state.activeId ? 'active' : '';
                const unreadBadge = unread ? `<span style="margin-left:auto;min-width:20px;height:20px;border-radius:999px;display:inline-grid;place-items:center;background:#00a884;color:#03120f;font-size:12px;font-weight:800;">${unread}</span>` : '';
                return `
                    <button class="wa-chat-item ${active}" data-id="${escapeHtml(cliente.id)}">
                        <div class="wa-avatar">${escapeHtml(initials(cliente.nome))}</div>
                        <div class="wa-chat-main">
                            <div class="wa-chat-line">
                                <div class="wa-chat-title">${escapeHtml(cliente.nome || 'Cliente')}</div>
                                <div class="wa-chat-time">${escapeHtml(toTime(last?.data || cliente.updated_at || cliente.created_at))}</div>
                            </div>
                            <div class="wa-chat-line">
                                <div class="wa-chat-preview">${isFromMe(last) ? 'Voce: ' : ''}${escapeHtml(previewMessage(last))}</div>
                                ${unreadBadge}
                            </div>
                            <div class="wa-chat-meta">
                                <span class="wa-stage-dot"></span>
                                <span>${escapeHtml(cliente.status || cliente.etapa || 'novo')}</span>
                                ${cliente.valor ? `<span>- R$ ${Number(cliente.valor || 0).toLocaleString('pt-BR')}</span>` : ''}
                            </div>
                        </div>
                    </button>
                `;
            }).join('');
        }

        function findActive() {
            return state.clientes.find(cliente => String(cliente.id) === state.activeId) || null;
        }

        function openChat(id) {
            state.activeId = String(id);
            const cliente = findActive();
            if (!cliente) return;

            renderList();
            renderActiveHeader(cliente);
            el.emptyState.classList.add('wa-hidden');
            el.chatPanel.classList.remove('wa-hidden');
            el.app.classList.add('chat-open');
            loadMessages(true);
            markConversationRead(cliente.id);
            startMessagePolling();
            el.input.focus();
        }

        function renderActiveHeader(cliente) {
            const numero = onlyDigits(cliente.numero);
            const mode = atendimentoMode(cliente);
            el.chatAvatar.textContent = initials(cliente.nome);
            el.chatName.textContent = cliente.nome || 'Cliente';
            el.chatSubtitle.textContent = [cliente.numero, cliente.origem || 'WhatsApp', cliente.interesse || statusLabel(cliente.status)].filter(Boolean).join(' - ');
            el.waDirect.href = numero ? `https://wa.me/${numero}` : '#';
            el.crmLead.href = `../index.php?cliente=${encodeURIComponent(cliente.id)}`;
            el.botMode.classList.toggle('active', mode !== 'humano');
            el.humanMode.classList.toggle('active', mode === 'humano');
            el.status.value = ['novo', 'lead_quente', 'agendado', 'sem_retorno', 'em_atendimento', 'fechado', 'perdido'].includes(cliente.status)
                ? cliente.status
                : 'novo';
        }

        async function loadMessages(stick = false) {
            if (!state.activeId) return;
            const response = await fetch(`../api_chat.php?id=${encodeURIComponent(state.activeId)}`, { cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.ok || !Array.isArray(data.mensagens)) return;
            renderMessages(data.mensagens, stick);
            markConversationRead(state.activeId);
        }

        function renderMedia(msg) {
            const url = msg.mediaUrl ? '../' + String(msg.mediaUrl).replace(/^\/+/, '') : '';
            const mime = String(msg.mediaMime || '');
            if (!url) return '';
            if (mime.startsWith('image/')) {
                return `<img class="wa-media" src="${escapeHtml(url)}" alt="${escapeHtml(msg.mediaFileName || 'Imagem')}">`;
            }
            if (mime.startsWith('audio/')) {
                const key = transcriptionKey(msg.messageId || '', msg.mediaUrl || '');
                const pending = state.pendingTranscriptions[key];
                return `
                    <audio class="wa-audio" controls src="${escapeHtml(url)}"></audio>
                    ${pending ? renderTranscriptionProgress(pending) : `<button type="button" class="wa-action-pill wa-transcribe-btn" data-message-id="${escapeHtml(msg.messageId || '')}" data-media-url="${escapeHtml(msg.mediaUrl || '')}">Transcrever audio</button>`}
                `;
            }
            return `<a class="wa-doc" href="${escapeHtml(url)}" target="_blank"><i class="fa-solid fa-file"></i><span>${escapeHtml(msg.mediaFileName || 'Arquivo')}</span></a>`;
        }

        function transcriptionKey(messageId, url) {
            return messageId || url;
        }

        function renderTranscriptionProgress(progressState) {
            const progress = Math.max(8, Math.min(95, Number(progressState?.progress) || 8));
            const elapsed = Math.max(0, Math.floor((Date.now() - (progressState?.startedAt || Date.now())) / 1000));
            const label = elapsed > 40 ? 'Transcrevendo com mais qualidade...' : 'Preparando transcricao...';
            return `
                <div class="wa-transcription">
                    <strong>${label}</strong>
                    <div style="height:4px;margin-top:7px;border-radius:999px;background:rgba(255,255,255,.15);overflow:hidden;">
                        <div style="height:100%;width:${progress}%;background:#00a884;"></div>
                    </div>
                </div>
            `;
        }

        function statusIcon(status) {
            const normalized = String(status || '').toLowerCase();
            if (['read', 'played'].includes(normalized)) return '<i class="fa-solid fa-check-double wa-status-read"></i>';
            if (['delivered'].includes(normalized)) return '<i class="fa-solid fa-check-double"></i>';
            if (['sent'].includes(normalized)) return '<i class="fa-solid fa-check"></i>';
            if (['pending'].includes(normalized)) return '<i class="fa-regular fa-clock"></i>';
            return '';
        }

        function renderMessages(messages, stick = false) {
            const wasNearBottom = el.messages.scrollTop + el.messages.clientHeight >= el.messages.scrollHeight - 120;
            let lastDay = '';
            el.messages.innerHTML = messages.map(msg => {
                const day = toDay(msg.data);
                const dayHtml = day && day !== lastDay ? `<div class="wa-day">${escapeHtml(day)}</div>` : '';
                if (day) lastDay = day;
                const out = isFromMe(msg);
                const text = msg.transcricao && !msg.texto ? msg.transcricao : msg.texto;
                return `
                    ${dayHtml}
                    <div class="wa-bubble-row ${out ? 'out' : 'in'}">
                        <div class="wa-bubble">
                            ${renderMedia(msg)}
                            ${text ? `<div class="wa-bubble-text">${escapeHtml(text)}</div>` : ''}
                            ${msg.transcricao && msg.texto ? `<div class="wa-transcription"><strong>Transcricao:</strong> ${escapeHtml(msg.transcricao)}</div>` : ''}
                            ${msg.transcricao_erro ? `<div class="wa-transcription error"><strong>Erro na transcricao:</strong> ${escapeHtml(msg.transcricao_erro)}</div>` : ''}
                            <div class="wa-bubble-time">
                                <span>${escapeHtml(msg.hora || toTime(msg.data))}</span>
                                ${out ? statusIcon(msg.status) : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            if (stick || wasNearBottom) {
                el.messages.scrollTop = el.messages.scrollHeight;
            }
        }

        function startMessagePolling() {
            clearInterval(state.polling);
            state.polling = setInterval(() => loadMessages(false), 2500);
        }

        function autoResize() {
            el.input.style.height = '42px';
            el.input.style.height = Math.min(el.input.scrollHeight, 130) + 'px';
        }

        function insertEmoji(emoji) {
            const start = el.input.selectionStart || 0;
            const end = el.input.selectionEnd || 0;
            el.input.value = el.input.value.slice(0, start) + emoji + el.input.value.slice(end);
            el.input.focus();
            el.input.selectionStart = el.input.selectionEnd = start + emoji.length;
            autoResize();
        }

        function renderReplies() {
            const term = el.replySearch.value.trim().toLowerCase();
            const filtered = quickReplies.filter(reply => {
                const haystack = [reply.titulo, reply.categoria, reply.atalho, reply.texto].join(' ').toLowerCase();
                return !term || haystack.includes(term);
            });

            if (!filtered.length) {
                el.replyList.innerHTML = '<div class="wa-subtitle" style="padding:10px;">Nenhuma resposta encontrada.</div>';
                return;
            }

            el.replyList.innerHTML = filtered.map(reply => `
                <button type="button" class="wa-reply-card" data-id="${escapeHtml(reply.id)}">
                    <strong>${escapeHtml(reply.titulo || 'Resposta')}</strong>
                    <span>${escapeHtml(reply.atalho || reply.categoria || 'Geral')}</span>
                    <span>${escapeHtml(reply.texto || '')}</span>
                </button>
            `).join('');
        }

        function updateAttachmentPreview() {
            const file = state.recordedAudioFile || el.file.files?.[0];
            if (!file) {
                el.attachmentPreview.classList.add('wa-hidden');
                el.attachmentPreview.innerHTML = '';
                return;
            }

            el.attachmentPreview.classList.remove('wa-hidden');
            el.attachmentPreview.innerHTML = `
                <span><i class="fa-solid fa-paperclip"></i> ${escapeHtml(file.name)}</span>
                <button type="button" id="removeAttachmentBtn"><i class="fa-solid fa-xmark"></i></button>
            `;
            document.getElementById('removeAttachmentBtn').addEventListener('click', removeAttachment);
        }

        function removeAttachment() {
            el.file.value = '';
            state.recordedAudioFile = null;
            updateAttachmentPreview();
        }

        function updateRecordingPreview() {
            const elapsed = Math.max(0, Math.floor((Date.now() - state.recordingStartedAt) / 1000));
            const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const seconds = String(elapsed % 60).padStart(2, '0');
            el.attachmentPreview.classList.remove('wa-hidden');
            el.attachmentPreview.innerHTML = `
                <span style="color:#ffd6d6;"><i class="fa-solid fa-circle" style="font-size:9px;color:#ef4444;"></i> Gravando ${minutes}:${seconds}</span>
                <button type="button" id="cancelRecordingBtn">Cancelar</button>
            `;
            document.getElementById('cancelRecordingBtn').addEventListener('click', () => stopAudioRecording(true));
        }

        async function toggleAudioRecording() {
            if (state.mediaRecorder && state.mediaRecorder.state === 'recording') {
                stopAudioRecording(false);
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                alert('Seu navegador nao liberou gravacao de audio aqui.');
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const options = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                    ? { mimeType: 'audio/webm;codecs=opus' }
                    : {};
                state.mediaRecorder = new MediaRecorder(stream, options);
                state.recordedAudioChunks = [];
                state.recordedAudioFile = null;

                state.mediaRecorder.ondataavailable = event => {
                    if (event.data && event.data.size > 0) state.recordedAudioChunks.push(event.data);
                };

                state.mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(track => track.stop());
                    clearInterval(state.recordingTimer);
                    state.recordingTimer = null;
                    el.record.classList.remove('active');
                };

                state.recordingStartedAt = Date.now();
                state.mediaRecorder.start();
                el.record.classList.add('active');
                updateRecordingPreview();
                state.recordingTimer = setInterval(updateRecordingPreview, 500);
            } catch (error) {
                alert('Nao consegui acessar o microfone: ' + (error.message || error));
            }
        }

        function stopAudioRecording(cancel = false) {
            if (!state.mediaRecorder || state.mediaRecorder.state !== 'recording') return;
            const recorder = state.mediaRecorder;
            recorder.addEventListener('stop', () => {
                if (!cancel && state.recordedAudioChunks.length) {
                    const mime = recorder.mimeType || 'audio/webm';
                    const blob = new Blob(state.recordedAudioChunks, { type: mime });
                    state.recordedAudioFile = new File([blob], `audio_${Date.now()}.webm`, { type: mime });
                } else {
                    state.recordedAudioFile = null;
                }
                state.recordedAudioChunks = [];
                updateAttachmentPreview();
            }, { once: true });
            recorder.stop();
        }

        function transcribeAudio(messageId, mediaUrl) {
            const key = transcriptionKey(messageId, mediaUrl);
            state.pendingTranscriptions[key] = {
                progress: 8,
                startedAt: Date.now(),
                timer: setInterval(() => {
                    const current = state.pendingTranscriptions[key];
                    if (!current) return;
                    current.progress = Math.min(95, current.progress + 4);
                    loadMessages(false);
                }, 2200),
            };
            loadMessages(false);

            fetch('../transcrever_audio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messageId, mediaUrl, model: 'small' }),
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.ok) throw new Error(data.error || 'Nao foi possivel transcrever o audio');
                    return loadMessages(true);
                })
                .catch(error => alert(error.message))
                .finally(() => {
                    clearInterval(state.pendingTranscriptions[key]?.timer);
                    delete state.pendingTranscriptions[key];
                    loadMessages(false);
                });
        }

        function postAttendanceAction(payload) {
            return fetch('../atendimento_acoes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            }).then(response => response.json());
        }

        function updateScheduleTotalPreview() {
            const valor = Number(el.scheduleValor.value || 0);
            const pomadas = Number(el.schedulePomadas.value || 0);
            el.scheduleTotal.value = formatCurrency(valor + (pomadas * Number(pomadaUnitPrice || 0)));
        }

        function setScheduleNotice(message, tone = 'amber') {
            el.scheduleNotice.textContent = message || '';
            el.scheduleNotice.style.color = tone === 'green' ? '#7ee7c6' : '#facc15';
        }

        function openScheduleOverlay() {
            const cliente = findActive();
            if (!cliente) return;

            el.scheduleForm.reset();
            el.scheduleClienteId.value = '';
            el.scheduleNome.value = cliente.nome && cliente.nome !== 'Cliente' ? cliente.nome : '';
            el.scheduleTelefone.value = cliente.numero || '';
            el.scheduleDescricao.value = cliente.interesse || '';
            el.schedulePomadas.value = '0';
            el.scheduleSearch.value = cliente.numero || cliente.nome || '';
            el.scheduleResults.classList.add('wa-hidden');
            el.scheduleResults.innerHTML = '';
            el.scheduleResult.classList.add('wa-hidden');
            el.scheduleResult.innerHTML = '';
            updateScheduleTotalPreview();
            setScheduleNotice('Vou procurar esse telefone na ficha para evitar cadastro duplicado.');
            el.scheduleOverlay.classList.remove('wa-hidden');
            searchScheduleClients(cliente.numero || cliente.nome || '', true);
            setTimeout(() => el.scheduleData.focus(), 80);
        }

        function closeScheduleOverlay() {
            el.scheduleOverlay.classList.add('wa-hidden');
        }

        function scheduleSearchChanged() {
            clearTimeout(state.scheduleSearchTimer);
            const term = el.scheduleSearch.value.trim();
            state.scheduleSearchTimer = setTimeout(() => searchScheduleClients(term, false), 250);
        }

        async function searchScheduleClients(term, autoSelectByPhone = false) {
            el.scheduleResults.innerHTML = '';
            el.scheduleResults.classList.add('wa-hidden');
            if (!term || term.length < 2) {
                setScheduleNotice('Digite pelo menos 2 caracteres para buscar na ficha.');
                return;
            }

            try {
                const response = await fetch(`../agendamento_clientes.php?q=${encodeURIComponent(term)}`);
                const data = await response.json();
                const clientes = Array.isArray(data.clientes) ? data.clientes : [];
                if (!clientes.length) {
                    el.scheduleClienteId.value = '';
                    setScheduleNotice('Cliente nao encontrado. Ao salvar, o sistema cria um cadastro basico.');
                    return;
                }

                const activeDigits = onlyDigits(el.scheduleTelefone.value || findActive()?.numero || '');
                const exact = clientes.find(cliente => activeDigits && onlyDigits(cliente.telefone) === activeDigits);
                if (autoSelectByPhone && exact) {
                    selectScheduleClient(exact);
                    setScheduleNotice('Cliente ja encontrado na ficha. Vou usar esse cadastro.', 'green');
                    return;
                }

                el.scheduleResults.innerHTML = clientes.map((cliente, index) => `
                    <button type="button" class="wa-client-option" data-index="${index}">
                        <strong>${escapeHtml(cliente.nome || 'Cliente')}</strong>
                        <span style="display:block;color:var(--wa-muted);font-size:12px;margin-top:4px;">${escapeHtml(cliente.telefone || '')}${cliente.email ? ' - ' + escapeHtml(cliente.email) : ''}</span>
                    </button>
                `).join('');
                el.scheduleResults.querySelectorAll('.wa-client-option').forEach(button => {
                    button.addEventListener('click', () => selectScheduleClient(clientes[Number(button.dataset.index)]));
                });
                el.scheduleResults.classList.remove('wa-hidden');
                setScheduleNotice('Selecione o cliente correto para evitar duplicidade.');
            } catch (error) {
                setScheduleNotice('Nao consegui buscar clientes agora. Ainda da para salvar.');
            }
        }

        function selectScheduleClient(cliente) {
            el.scheduleClienteId.value = cliente.id || '';
            el.scheduleNome.value = cliente.nome || '';
            el.scheduleTelefone.value = cliente.telefone || '';
            el.scheduleSearch.value = `${cliente.nome || ''} ${cliente.telefone || ''}`.trim();
            el.scheduleResults.classList.add('wa-hidden');
            el.scheduleResults.innerHTML = '';
            setScheduleNotice('Cliente existente selecionado.', 'green');
        }

        async function saveSchedule(event) {
            event.preventDefault();
            if (!el.scheduleForm.reportValidity()) return;

            const formData = new FormData(el.scheduleForm);
            el.scheduleSubmit.disabled = true;
            el.scheduleSubmit.textContent = 'Salvando...';

            try {
                const response = await fetch('../agendamento_salvar.php', { method: 'POST', body: formData });
                const result = await response.json().catch(() => ({}));
                if (!response.ok || !result.ok) {
                    alert(result.error || 'Nao foi possivel salvar o agendamento.');
                    return;
                }

                const fichaUrl = crmRelativeUrl(result.ficha_url);
                const agendaUrl = result.agenda_url ? crmRelativeUrl(result.agenda_url) : '';
                el.scheduleResult.classList.remove('wa-hidden');
                el.scheduleResult.innerHTML = `
                    <strong>${escapeHtml(result.message || 'Agendamento salvo.')}</strong>
                    ${agendaUrl ? `<div style="margin-top:10px;"><a class="wa-action-pill active" href="${escapeHtml(agendaUrl)}" target="_blank">Abrir agenda</a></div>` : ''}
                    <div style="margin-top:10px;">Link da ficha: <input readonly value="${escapeHtml(fichaUrl)}" style="width:100%;margin-top:6px;border:0;border-radius:9px;padding:9px;background:#202c33;color:#e9edef;"></div>
                `;
            } finally {
                el.scheduleSubmit.disabled = false;
                el.scheduleSubmit.textContent = 'Salvar agendamento';
            }
        }

        async function sendMessage(event) {
            event.preventDefault();
            if (state.sending || !state.activeId) return;

            const cliente = findActive();
            if (!cliente) return;

            const text = el.input.value.trim();
            const file = state.recordedAudioFile || el.file.files[0] || null;
            if (!text && !file) return;

            state.sending = true;
            const form = new FormData();
            form.append('numero', cliente.numero || '');
            form.append('mensagem', text);
            if (file) {
                form.append('arquivo', file);
                if (state.recordedAudioFile) form.append('ptt', '1');
            }

            try {
                const response = await fetch('../enviar.php', { method: 'POST', body: form });
                const data = await response.json().catch(() => null);
                if (!response.ok || !data || !data.ok) {
                    alert(data?.erro || 'Nao foi possivel enviar a mensagem.');
                    return;
                }
                el.input.value = '';
                removeAttachment();
                autoResize();
                await loadClientes(true);
                await loadMessages(true);
            } finally {
                state.sending = false;
            }
        }

        document.addEventListener('click', event => {
            const item = event.target.closest('.wa-chat-item');
            if (item) openChat(item.dataset.id);

            const reply = event.target.closest('.wa-reply-card');
            if (reply) {
                const found = quickReplies.find(item => String(item.id) === String(reply.dataset.id));
                if (found) {
                    el.input.value = found.texto || '';
                    el.repliesPanel.classList.add('wa-hidden');
                    el.input.focus();
                    autoResize();
                }
            }

            const transcribe = event.target.closest('.wa-transcribe-btn');
            if (transcribe) {
                transcribeAudio(transcribe.dataset.messageId || '', transcribe.dataset.mediaUrl || '');
            }

            const chip = event.target.closest('.wa-chip');
            if (chip) {
                document.querySelectorAll('.wa-chip').forEach(btn => btn.classList.remove('active'));
                chip.classList.add('active');
                state.activeFilter = chip.dataset.filter || 'all';
                applyFilters();
            }
        });

        el.search.addEventListener('input', applyFilters);
        el.refresh.addEventListener('click', () => loadClientes(true));
        el.back.addEventListener('click', () => el.app.classList.remove('chat-open'));
        el.input.addEventListener('input', autoResize);
        el.emoji.addEventListener('click', () => {
            el.emojiPanel.classList.toggle('wa-hidden');
            el.repliesPanel.classList.add('wa-hidden');
        });
        el.emojiPanel.querySelectorAll('[data-emoji]').forEach(button => {
            button.addEventListener('click', () => insertEmoji(button.dataset.emoji || ''));
        });
        el.quickReply.addEventListener('click', () => {
            el.repliesPanel.classList.toggle('wa-hidden');
            el.emojiPanel.classList.add('wa-hidden');
            renderReplies();
        });
        el.replySearch.addEventListener('input', renderReplies);
        el.input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                el.composer.requestSubmit();
            }
        });
        el.attach.addEventListener('click', () => el.file.click());
        el.file.addEventListener('change', updateAttachmentPreview);
        el.record.addEventListener('click', toggleAudioRecording);
        el.composer.addEventListener('submit', sendMessage);
        el.schedule.addEventListener('click', openScheduleOverlay);
        el.closeSchedule.addEventListener('click', closeScheduleOverlay);
        el.cancelSchedule.addEventListener('click', closeScheduleOverlay);
        el.scheduleOverlay.addEventListener('click', event => {
            if (event.target === el.scheduleOverlay) closeScheduleOverlay();
        });
        el.scheduleForm.addEventListener('submit', saveSchedule);
        el.scheduleSearch.addEventListener('input', scheduleSearchChanged);
        el.scheduleTelefone.addEventListener('blur', () => {
            if (el.scheduleTelefone.value.trim()) searchScheduleClients(el.scheduleTelefone.value.trim(), true);
        });
        el.scheduleValor.addEventListener('input', updateScheduleTotalPreview);
        el.schedulePomadas.addEventListener('input', updateScheduleTotalPreview);
        el.assume.addEventListener('click', () => {
            const cliente = findActive();
            if (!cliente) return;
            postAttendanceAction({ action: 'assumir', id: cliente.id })
                .then(data => {
                    if (!data.ok) throw new Error(data.message || 'Erro ao assumir conversa');
                    normalizeClientPayload(data.cliente);
                })
                .catch(error => alert(error.message));
        });
        el.humanMode.addEventListener('click', () => {
            const cliente = findActive();
            if (!cliente) return;
            postAttendanceAction({ action: 'assumir', id: cliente.id })
                .then(data => {
                    if (!data.ok) throw new Error(data.message || 'Erro ao alternar atendimento');
                    normalizeClientPayload(data.cliente);
                })
                .catch(error => alert(error.message));
        });
        el.botMode.addEventListener('click', () => {
            const cliente = findActive();
            if (!cliente) return;
            postAttendanceAction({ action: 'bot', id: cliente.id })
                .then(data => {
                    if (!data.ok) throw new Error(data.message || 'Erro ao alternar atendimento');
                    normalizeClientPayload(data.cliente);
                })
                .catch(error => alert(error.message));
        });
        el.status.addEventListener('change', () => {
            const cliente = findActive();
            if (!cliente) return;
            postAttendanceAction({ action: 'status', id: cliente.id, status: el.status.value })
                .then(data => {
                    if (!data.ok) throw new Error(data.message || 'Erro ao atualizar status');
                    normalizeClientPayload(data.cliente);
                })
                .catch(error => alert(error.message));
        });

        loadReadState()
            .catch(() => {})
            .finally(() => {
                loadClientes(false).catch(error => {
                    el.chatList.innerHTML = `<div class="wa-loading">${escapeHtml(error.message)}</div>`;
                });
            });
        renderReplies();
        state.listPolling = setInterval(() => loadClientes(true).catch(() => {}), 8000);
    </script>
</body>
</html>
