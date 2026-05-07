<?php
require_once __DIR__ . '/../../auth/auth.php';
require_staff();
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
            min-width: 0;
            min-height: 0;
            height: 100%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #0b141a;
        }

        .wa-chat-header {
            height: 60px;
            flex: 0 0 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 18px;
            background: var(--wa-panel-2);
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
            padding: 24px 7.6%;
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
            margin: 2px 0;
        }

        .wa-bubble-row.out { justify-content: flex-end; }

        .wa-bubble {
            max-width: min(660px, 72%);
            padding: 7px 9px 5px;
            border-radius: 8px;
            color: var(--wa-text);
            background: var(--wa-bubble-in);
            box-shadow: 0 1px 1px rgba(0,0,0,0.18);
            line-height: 1.45;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 14.4px;
        }

        .wa-bubble-row.out .wa-bubble { background: var(--wa-bubble-out); }

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
            margin-top: 2px;
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
                        <a id="waDirectLink" class="wa-icon-btn" href="#" target="_blank" title="Abrir no WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                        <a id="crmLeadLink" class="wa-icon-btn" href="#" title="Abrir no CRM"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    </div>
                </header>

                <div id="messages" class="wa-messages"></div>

                <form id="composer" class="wa-composer">
                    <button type="button" class="wa-icon-btn" id="attachBtn" title="Anexar"><i class="fa-solid fa-paperclip"></i></button>
                    <input id="fileInput" class="wa-hidden" type="file">
                    <textarea id="messageInput" rows="1" placeholder="Digite uma mensagem"></textarea>
                    <button class="wa-send" title="Enviar"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </section>
    </main>

    <script>
        const state = {
            clientes: [],
            filtered: [],
            activeId: '',
            activeFilter: 'all',
            polling: null,
            listPolling: null,
            sending: false,
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
            attach: document.getElementById('attachBtn'),
            file: document.getElementById('fileInput'),
            back: document.getElementById('backBtn'),
            waDirect: document.getElementById('waDirectLink'),
            crmLead: document.getElementById('crmLeadLink'),
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
            let count = 0;
            for (let i = messages.length - 1; i >= 0; i--) {
                if (isFromMe(messages[i])) break;
                count++;
            }
            return count;
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
            startMessagePolling();
            el.input.focus();
        }

        function renderActiveHeader(cliente) {
            const numero = onlyDigits(cliente.numero);
            el.chatAvatar.textContent = initials(cliente.nome);
            el.chatName.textContent = cliente.nome || 'Cliente';
            el.chatSubtitle.textContent = [cliente.numero, cliente.status || cliente.etapa].filter(Boolean).join(' - ');
            el.waDirect.href = numero ? `https://wa.me/${numero}` : '#';
            el.crmLead.href = `../index.php?cliente=${encodeURIComponent(cliente.id)}`;
        }

        async function loadMessages(stick = false) {
            if (!state.activeId) return;
            const response = await fetch(`../api_chat.php?id=${encodeURIComponent(state.activeId)}`, { cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.ok || !Array.isArray(data.mensagens)) return;
            renderMessages(data.mensagens, stick);
        }

        function renderMedia(msg) {
            const url = msg.mediaUrl ? '../' + String(msg.mediaUrl).replace(/^\/+/, '') : '';
            const mime = String(msg.mediaMime || '');
            if (!url) return '';
            if (mime.startsWith('image/')) {
                return `<img class="wa-media" src="${escapeHtml(url)}" alt="${escapeHtml(msg.mediaFileName || 'Imagem')}">`;
            }
            if (mime.startsWith('audio/')) {
                return `<audio class="wa-audio" controls src="${escapeHtml(url)}"></audio>`;
            }
            return `<a class="wa-doc" href="${escapeHtml(url)}" target="_blank"><i class="fa-solid fa-file"></i><span>${escapeHtml(msg.mediaFileName || 'Arquivo')}</span></a>`;
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
                            ${text ? `<div>${escapeHtml(text)}</div>` : ''}
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

        async function sendMessage(event) {
            event.preventDefault();
            if (state.sending || !state.activeId) return;

            const cliente = findActive();
            if (!cliente) return;

            const text = el.input.value.trim();
            const file = el.file.files[0] || null;
            if (!text && !file) return;

            state.sending = true;
            const form = new FormData();
            form.append('numero', cliente.numero || '');
            form.append('mensagem', text);
            if (file) form.append('arquivo', file);

            try {
                const response = await fetch('../enviar.php', { method: 'POST', body: form });
                const data = await response.json().catch(() => null);
                if (!response.ok || !data || !data.ok) {
                    alert(data?.erro || 'Nao foi possivel enviar a mensagem.');
                    return;
                }
                el.input.value = '';
                el.file.value = '';
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
        el.input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                el.composer.requestSubmit();
            }
        });
        el.attach.addEventListener('click', () => el.file.click());
        el.composer.addEventListener('submit', sendMessage);

        loadClientes(false).catch(error => {
            el.chatList.innerHTML = `<div class="wa-loading">${escapeHtml(error.message)}</div>`;
        });
        state.listPolling = setInterval(() => loadClientes(true).catch(() => {}), 8000);
    </script>
</body>
</html>
