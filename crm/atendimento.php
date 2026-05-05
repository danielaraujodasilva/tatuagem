<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require_once __DIR__ . '/data_store.php';

date_default_timezone_set('America/Sao_Paulo');

function atendimento_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function atendimento_from_me(array $msg): bool
{
    if (!empty($msg['fromMe'])) {
        return true;
    }

    $autor = strtolower((string)($msg['de'] ?? $msg['autor'] ?? ''));
    return in_array($autor, ['eu', 'me', 'atendente', 'humano', 'bot'], true);
}

function atendimento_status_label(string $status): string
{
    $labels = [
        'novo' => 'Novo',
        'lead_quente' => 'Lead quente',
        'agendado' => 'Agendado',
        'sem_retorno' => 'Sem retorno',
        'em_atendimento' => 'Em atendimento',
        'fechado' => 'Fechado',
        'perdido' => 'Perdido',
    ];

    return $labels[$status] ?? ($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Novo');
}

function atendimento_normalizar_status(string $status): string
{
    $status = strtolower(trim($status));
    $status = strtr($status, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
        'é' => 'e', 'ê' => 'e',
        'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u',
        'ç' => 'c',
    ]);
    $status = preg_replace('/[^a-z0-9]+/', '_', $status) ?? '';
    return trim($status, '_') ?: 'novo';
}

function atendimento_time_ago(?int $timestamp): string
{
    if (!$timestamp) {
        return '-';
    }

    $diff = max(0, time() - $timestamp);
    if ($diff < 3600) {
        $min = max(1, (int)floor($diff / 60));
        return $min . ' min';
    }

    if ($diff < 86400) {
        return (int)floor($diff / 3600) . ' h';
    }

    return (int)floor($diff / 86400) . ' d';
}

function atendimento_numero_limpo(string $numero): string
{
    return preg_replace('/\D+/', '', $numero) ?? '';
}

$clientesRaw = crmCarregarClientes();
$respostasRapidas = array_values(array_filter(crmCarregarRespostasRapidas(), static function (array $resposta): bool {
    return !empty($resposta['ativo']);
}));

$conversas = [];

foreach ($clientesRaw as $cliente) {
    $mensagens = is_array($cliente['mensagens'] ?? null) ? $cliente['mensagens'] : [];
    usort($mensagens, static function (array $a, array $b): int {
        return strcmp((string)($a['data'] ?? ''), (string)($b['data'] ?? ''));
    });

    $ultima = $mensagens ? end($mensagens) : [];
    $ultimaData = !empty($ultima['data']) ? strtotime((string)$ultima['data']) : null;
    $ultimaEntrada = null;
    $ultimaSaida = null;

    foreach ($mensagens as $msg) {
        $ts = !empty($msg['data']) ? strtotime((string)$msg['data']) : null;
        if (!$ts) {
            continue;
        }

        if (atendimento_from_me($msg)) {
            $ultimaSaida = $ts;
        } else {
            $ultimaEntrada = $ts;
        }
    }

    $semResposta = $ultimaEntrada && (!$ultimaSaida || $ultimaEntrada > $ultimaSaida);
    $status = atendimento_normalizar_status((string)($cliente['status'] ?? 'novo'));
    $atendente = trim((string)($cliente['atendente'] ?? ''));
    $modo = strtolower(trim((string)($cliente['modo_atendimento'] ?? '')));
    if ($modo === '') {
        $modo = $atendente === '' || strtolower($atendente) === 'bot' ? 'bot' : 'humano';
    }

    $conversas[] = [
        'id' => 'wa_' . ($cliente['id'] ?? ''),
        'clienteId' => (string)($cliente['id'] ?? ''),
        'nome' => (string)($cliente['nome'] ?? 'Cliente'),
        'numero' => (string)($cliente['numero'] ?? ''),
        'numeroLimpo' => atendimento_numero_limpo((string)($cliente['numero'] ?? '')),
        'status' => $status,
        'statusLabel' => atendimento_status_label($status),
        'atendente' => $atendente !== '' ? $atendente : 'Bot',
        'modo' => $modo,
        'interesse' => (string)($cliente['interesse'] ?? ''),
        'valor' => (float)($cliente['valor'] ?? 0),
        'origem' => (string)($cliente['origem'] ?? 'WhatsApp'),
        'semResposta' => (bool)$semResposta,
        'tempoSemResposta' => $semResposta ? atendimento_time_ago($ultimaEntrada) : '-',
        'minutosSemResposta' => $semResposta && $ultimaEntrada ? (int)floor((time() - $ultimaEntrada) / 60) : 0,
        'ultimaMensagem' => (string)($ultima['texto'] ?? ''),
        'ultimaData' => $ultimaData ? date('d/m H:i', $ultimaData) : '',
        'ultimaTimestamp' => $ultimaData ?: 0,
        'mensagens' => array_map(static function (array $msg): array {
            $data = (string)($msg['data'] ?? '');
            return [
                'texto' => (string)($msg['texto'] ?? ''),
                'data' => $data,
                'hora' => $data ? date('H:i', strtotime($data)) : '',
                'fromMe' => atendimento_from_me($msg),
                'tipo' => (string)($msg['tipo'] ?? 'texto'),
                'mediaUrl' => (string)($msg['mediaUrl'] ?? ''),
                'mediaMime' => (string)($msg['mediaMime'] ?? ''),
                'transcricao' => (string)($msg['transcricao'] ?? ''),
            ];
        }, $mensagens),
    ];
}

usort($conversas, static function (array $a, array $b): int {
    return ($b['ultimaTimestamp'] <=> $a['ultimaTimestamp']);
});

$totalConversas = count($conversas);
$naoRespondidas = count(array_filter($conversas, static fn(array $c): bool => $c['semResposta']));
$leadsQuentes = count(array_filter($conversas, static fn(array $c): bool => $c['status'] === 'lead_quente'));
$agendados = count(array_filter($conversas, static fn(array $c): bool => $c['status'] === 'agendado'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Atendimento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/crm-theme.css">
    <style>
        .attendance-layout {
            display: grid;
            grid-template-columns: minmax(280px, 360px) minmax(0, 1fr) minmax(260px, 330px);
            min-height: 720px;
        }

        .conversation-list,
        .quick-reply-list,
        .chat-messages {
            scrollbar-color: rgba(225, 29, 40, 0.55) rgba(255, 255, 255, 0.05);
        }

        .conversation-list {
            max-height: 650px;
            overflow-y: auto;
        }

        .conversation-item {
            width: 100%;
            padding: 14px;
            border: 0;
            border-bottom: 1px solid var(--crm-border);
            color: inherit;
            background: transparent;
            text-align: left;
            cursor: pointer;
        }

        .conversation-item:hover,
        .conversation-item.is-active {
            background: rgba(225, 29, 40, 0.12);
        }

        .conversation-item.is-active {
            box-shadow: inset 3px 0 0 var(--crm-red);
        }

        .chat-messages {
            height: 492px;
            overflow-y: auto;
            padding: 18px;
            background:
                linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        .message-row {
            display: flex;
            margin-bottom: 12px;
        }

        .message-row.is-me {
            justify-content: flex-end;
        }

        .message-bubble {
            width: fit-content;
            max-width: min(74%, 560px);
            padding: 11px 13px;
            border: 1px solid var(--crm-border);
            border-radius: 8px;
            background: #1b1d24;
            color: #f4f4f5;
        }

        .message-row.is-me .message-bubble {
            border-color: rgba(225, 29, 40, 0.28);
            background: linear-gradient(180deg, #9f1420, #7f111b);
        }

        .quick-reply-list {
            max-height: 510px;
            overflow-y: auto;
        }

        .reply-card {
            display: block;
            width: 100%;
            padding: 13px;
            border: 0;
            border-bottom: 1px solid var(--crm-border);
            color: inherit;
            background: transparent;
            text-align: left;
            cursor: pointer;
        }

        .reply-card:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .metric-card {
            padding: 16px;
        }

        @media (max-width: 1280px) {
            .attendance-layout {
                grid-template-columns: minmax(270px, 340px) minmax(0, 1fr);
            }

            .quick-panel {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 860px) {
            .attendance-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="crm-shell">
<div class="crm-page">
    <header class="crm-header">
        <div>
            <div class="crm-eyebrow"><i class="fa-brands fa-whatsapp"></i> CRM & Ficha</div>
            <h1 class="crm-title">Central de <strong>Atendimento</strong></h1>
            <p class="crm-subtitle">Inbox geral para responder, assumir e priorizar conversas quentes.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="crm-button" href="index.php"><i class="fa-solid fa-table-columns"></i> Pipeline</a>
            <a class="crm-button crm-button-primary" href="respostas_rapidas.php"><i class="fa-solid fa-bolt"></i> Respostas</a>
        </div>
    </header>

    <div class="crm-workspace">
        <aside class="crm-sidebar">
            <div class="crm-brand">
                <span class="crm-brand-name">Daniel <span>Tatuador</span></span>
                <small>Atendimento, leads e fechamento</small>
            </div>
            <nav class="crm-nav" aria-label="Menu CRM">
                <a href="index.php"><i class="fa-solid fa-chart-simple"></i> Dashboard / CRM</a>
                <a class="is-active" href="atendimento.php"><i class="fa-solid fa-comments"></i> Atendimento</a>
                <a href="respostas_rapidas.php"><i class="fa-solid fa-bolt"></i> Respostas Rapidas</a>
                <a href="../ficha/agenda/"><i class="fa-regular fa-calendar"></i> Agenda</a>
                <a href="../ficha/index.php"><i class="fa-regular fa-clipboard"></i> Ficha / Anamnese</a>
                <a href="relatorios.php"><i class="fa-solid fa-chart-line"></i> Relatorios</a>
                <a href="configuracoes.php"><i class="fa-solid fa-gear"></i> Configuracoes</a>
            </nav>
        </aside>

        <main class="crm-main">
            <section class="crm-grid-4 mb-4">
                <div class="crm-card metric-card">
                    <div class="crm-muted text-sm">Conversas</div>
                    <div class="text-3xl font-black mt-1"><?= $totalConversas ?></div>
                </div>
                <div class="crm-card metric-card">
                    <div class="crm-muted text-sm">Nao respondidas</div>
                    <div class="text-3xl font-black mt-1 text-red-400"><?= $naoRespondidas ?></div>
                </div>
                <div class="crm-card metric-card">
                    <div class="crm-muted text-sm">Leads quentes</div>
                    <div class="text-3xl font-black mt-1 text-amber-300"><?= $leadsQuentes ?></div>
                </div>
                <div class="crm-card metric-card">
                    <div class="crm-muted text-sm">Agendados</div>
                    <div class="text-3xl font-black mt-1 text-green-400"><?= $agendados ?></div>
                </div>
            </section>

            <section class="crm-panel attendance-layout">
                <div class="border-r border-white/10">
                    <div class="crm-panel-header">
                        <h2 class="crm-panel-title"><i class="fa-solid fa-inbox"></i> Conversas</h2>
                    </div>
                    <div class="p-3 border-b border-white/10 space-y-3">
                        <input id="searchInput" class="crm-input" placeholder="Buscar nome, telefone ou mensagem">
                        <div class="grid grid-cols-2 gap-2">
                            <button class="crm-button filter-button crm-button-primary" data-filter="todas">Todas</button>
                            <button class="crm-button filter-button" data-filter="nao_respondido">Nao respondido</button>
                            <button class="crm-button filter-button" data-filter="lead_quente">Lead quente</button>
                            <button class="crm-button filter-button" data-filter="agendado">Agendado</button>
                            <button class="crm-button filter-button" data-filter="sem_retorno">Sem retorno</button>
                            <button class="crm-button filter-button" data-filter="humano">Humano</button>
                        </div>
                    </div>
                    <div id="conversationList" class="conversation-list"></div>
                </div>

                <div class="min-w-0">
                    <div class="crm-panel-header">
                        <div class="min-w-0">
                            <h2 id="chatName" class="text-xl font-black truncate">Selecione uma conversa</h2>
                            <p id="chatMeta" class="crm-muted text-sm mt-1">Atendimento centralizado</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a id="waButton" class="crm-button crm-button-green hidden" target="_blank" rel="noopener">
                                <i class="fa-brands fa-whatsapp"></i> WhatsApp
                            </a>
                            <button id="assumeButton" type="button" class="crm-button crm-button-primary"><i class="fa-solid fa-user-check"></i> Assumir</button>
                        </div>
                    </div>

                    <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-3 border-b border-white/10">
                        <div class="crm-card p-3">
                            <div class="crm-muted text-xs">Atendimento</div>
                            <div id="modeBadge" class="mt-2 crm-status">-</div>
                        </div>
                        <div class="crm-card p-3">
                            <div class="crm-muted text-xs">Tempo sem resposta</div>
                            <div id="delayBadge" class="mt-2 crm-status crm-status-hot">-</div>
                        </div>
                        <div class="crm-card p-3">
                            <label for="statusSelect" class="crm-muted text-xs block mb-2">Status</label>
                            <select id="statusSelect" class="crm-select">
                                <option value="novo">Novo</option>
                                <option value="lead_quente">Lead quente</option>
                                <option value="agendado">Agendado</option>
                                <option value="sem_retorno">Sem retorno</option>
                                <option value="em_atendimento">Em atendimento</option>
                                <option value="fechado">Fechado</option>
                                <option value="perdido">Perdido</option>
                            </select>
                        </div>
                    </div>

                    <div id="chatMessages" class="chat-messages"></div>

                    <form id="messageForm" class="p-4 border-t border-white/10">
                        <div class="flex gap-2">
                            <textarea id="messageInput" class="crm-textarea min-h-[54px]" placeholder="Digite uma mensagem ou clique em uma resposta pronta..."></textarea>
                            <button class="crm-button crm-button-primary self-stretch px-5" type="submit" title="Enviar">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <aside class="quick-panel border-l border-white/10">
                    <div class="crm-panel-header">
                        <h2 class="crm-panel-title"><i class="fa-solid fa-bolt"></i> Respostas</h2>
                        <a class="crm-button" href="respostas_rapidas.php" title="Editar respostas"><i class="fa-solid fa-pen"></i></a>
                    </div>
                    <div class="p-3 border-b border-white/10">
                        <input id="replySearch" class="crm-input" placeholder="Buscar script">
                    </div>
                    <div id="quickReplyList" class="quick-reply-list"></div>
                </aside>
            </section>
        </main>
    </div>
</div>

<script>
const conversations = <?= json_encode($conversas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const quickReplies = <?= json_encode($respostasRapidas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const statusLabels = {
    novo: 'Novo',
    lead_quente: 'Lead quente',
    agendado: 'Agendado',
    sem_retorno: 'Sem retorno',
    em_atendimento: 'Em atendimento',
    fechado: 'Fechado',
    perdido: 'Perdido'
};

let activeFilter = 'todas';
let activeId = conversations[0]?.id || '';

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function getActive() {
    return conversations.find(item => item.id === activeId) || conversations[0] || null;
}

function statusClass(status) {
    if (status === 'lead_quente') return 'crm-status-hot';
    if (status === 'agendado' || status === 'fechado') return 'crm-status-green';
    if (status === 'sem_retorno') return 'crm-status-amber';
    return '';
}

function matchesFilter(item) {
    const term = document.getElementById('searchInput').value.toLowerCase().trim();
    const haystack = [item.nome, item.numero, item.ultimaMensagem, item.interesse, item.statusLabel].join(' ').toLowerCase();
    if (term && !haystack.includes(term)) return false;
    if (activeFilter === 'nao_respondido') return item.semResposta;
    if (activeFilter === 'lead_quente') return item.status === 'lead_quente';
    if (activeFilter === 'agendado') return item.status === 'agendado';
    if (activeFilter === 'sem_retorno') return item.status === 'sem_retorno';
    if (activeFilter === 'humano') return item.modo === 'humano';
    return true;
}

function renderConversationList() {
    const list = document.getElementById('conversationList');
    const filtered = conversations.filter(matchesFilter);

    if (!filtered.length) {
        list.innerHTML = '<div class="p-5 crm-muted">Nenhuma conversa nesse filtro.</div>';
        return;
    }

    list.innerHTML = filtered.map(item => `
        <button class="conversation-item ${item.id === activeId ? 'is-active' : ''}" data-id="${escapeHtml(item.id)}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-black truncate">${escapeHtml(item.nome)}</div>
                    <div class="crm-muted text-xs mt-1">${escapeHtml(item.numero || 'Sem telefone')}</div>
                </div>
                <span class="crm-status ${item.modo === 'humano' ? 'crm-status-green' : ''}">${item.modo === 'humano' ? 'Humano' : 'Bot'}</span>
            </div>
            <p class="text-sm text-gray-300 mt-3 line-clamp-2">${escapeHtml(item.ultimaMensagem || 'Sem mensagens')}</p>
            <div class="flex items-center justify-between gap-2 mt-3">
                <span class="crm-status ${statusClass(item.status)}">${escapeHtml(item.statusLabel)}</span>
                <span class="text-xs ${item.semResposta ? 'text-red-300' : 'text-gray-500'}">${item.semResposta ? item.tempoSemResposta + ' sem resposta' : item.ultimaData}</span>
            </div>
        </button>
    `).join('');

    list.querySelectorAll('.conversation-item').forEach(button => {
        button.addEventListener('click', () => {
            activeId = button.dataset.id;
            renderConversationList();
            renderActiveConversation(true);
        });
    });
}

function renderMessages(messages) {
    const box = document.getElementById('chatMessages');
    if (!messages?.length) {
        box.innerHTML = '<div class="h-full grid place-items-center crm-muted">Nenhuma mensagem encontrada.</div>';
        return;
    }

    box.innerHTML = messages.map(msg => `
        <div class="message-row ${msg.fromMe ? 'is-me' : ''}">
            <div class="message-bubble">
                ${msg.mediaUrl ? `<div class="mb-2 text-xs text-gray-300"><i class="fa-solid fa-paperclip"></i> ${escapeHtml(msg.mediaMime || 'anexo')}</div>` : ''}
                <div>${escapeHtml(msg.texto || msg.transcricao || '[midia]')}</div>
                <div class="text-[11px] text-gray-300 mt-2 text-right">${escapeHtml(msg.hora || '')}</div>
            </div>
        </div>
    `).join('');
    box.scrollTop = box.scrollHeight;
}

function renderActiveConversation(fetchFresh = false) {
    const item = getActive();
    const assumeButton = document.getElementById('assumeButton');
    const statusSelect = document.getElementById('statusSelect');

    if (!item) {
        document.getElementById('chatName').textContent = 'Nenhuma conversa';
        document.getElementById('chatMeta').textContent = 'As conversas do WhatsApp aparecem aqui quando existirem.';
        document.getElementById('chatMessages').innerHTML = '<div class="h-full grid place-items-center crm-muted">Sem conversas.</div>';
        assumeButton.disabled = true;
        statusSelect.disabled = true;
        return;
    }

    assumeButton.disabled = false;
    statusSelect.disabled = false;
    document.getElementById('chatName').textContent = item.nome;
    document.getElementById('chatMeta').textContent = `${item.numero || 'sem telefone'} • ${item.origem || 'WhatsApp'} • ${item.interesse || 'sem interesse definido'}`;
    document.getElementById('modeBadge').textContent = item.modo === 'humano' ? `Humano: ${item.atendente}` : 'Bot em atendimento';
    document.getElementById('modeBadge').className = `mt-2 crm-status ${item.modo === 'humano' ? 'crm-status-green' : ''}`;
    document.getElementById('delayBadge').textContent = item.semResposta ? `${item.tempoSemResposta} sem resposta` : 'Respondido';
    document.getElementById('delayBadge').className = `mt-2 crm-status ${item.semResposta ? 'crm-status-hot' : 'crm-status-green'}`;
    statusSelect.value = item.status in statusLabels ? item.status : 'novo';

    const waButton = document.getElementById('waButton');
    if (item.numeroLimpo) {
        waButton.href = `https://wa.me/55${item.numeroLimpo}`;
        waButton.classList.remove('hidden');
    } else {
        waButton.classList.add('hidden');
    }

    renderMessages(item.mensagens);

    if (fetchFresh) {
        fetch(`api_chat.php?id=${encodeURIComponent(item.id)}`)
            .then(response => response.json())
            .then(data => {
                if (!data.ok) return;
                item.mensagens = data.mensagens;
                renderMessages(item.mensagens);
            })
            .catch(() => {});
    }
}

function renderReplies() {
    const term = document.getElementById('replySearch').value.toLowerCase().trim();
    const list = document.getElementById('quickReplyList');
    const filtered = quickReplies.filter(reply => {
        const haystack = [reply.titulo, reply.categoria, reply.atalho, reply.texto].join(' ').toLowerCase();
        return !term || haystack.includes(term);
    });

    if (!filtered.length) {
        list.innerHTML = '<div class="p-5 crm-muted">Nenhuma resposta encontrada.</div>';
        return;
    }

    list.innerHTML = filtered.map(reply => `
        <button class="reply-card" data-reply-id="${escapeHtml(reply.id)}">
            <div class="flex items-center justify-between gap-2">
                <strong>${escapeHtml(reply.titulo)}</strong>
                <span class="crm-status">${escapeHtml(reply.atalho || reply.categoria)}</span>
            </div>
            <p class="crm-muted text-sm mt-2 line-clamp-3">${escapeHtml(reply.texto)}</p>
        </button>
    `).join('');

    list.querySelectorAll('.reply-card').forEach(button => {
        button.addEventListener('click', () => {
            const reply = quickReplies.find(item => item.id === button.dataset.replyId);
            const input = document.getElementById('messageInput');
            input.value = reply?.texto || '';
            input.focus();
        });
    });
}

function updateActiveFromPayload(cliente) {
    const item = getActive();
    if (!item || !cliente) return;

    item.atendente = cliente.atendente || item.atendente;
    item.modo = cliente.modo_atendimento || (item.atendente && item.atendente !== 'bot' ? 'humano' : 'bot');
    item.status = cliente.status || item.status;
    item.statusLabel = statusLabels[item.status] || item.status;
    renderConversationList();
    renderActiveConversation();
}

function postAttendanceAction(payload) {
    return fetch('atendimento_acoes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    }).then(response => response.json());
}

document.querySelectorAll('.filter-button').forEach(button => {
    button.addEventListener('click', () => {
        activeFilter = button.dataset.filter;
        document.querySelectorAll('.filter-button').forEach(btn => btn.classList.remove('crm-button-primary'));
        button.classList.add('crm-button-primary');
        renderConversationList();
    });
});

document.getElementById('searchInput').addEventListener('input', renderConversationList);
document.getElementById('replySearch').addEventListener('input', renderReplies);

document.getElementById('assumeButton').addEventListener('click', () => {
    const item = getActive();
    if (!item) return;
    postAttendanceAction({ action: 'assumir', id: item.id })
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Erro ao assumir conversa');
            updateActiveFromPayload(data.cliente);
        })
        .catch(error => alert(error.message));
});

document.getElementById('statusSelect').addEventListener('change', event => {
    const item = getActive();
    if (!item) return;
    postAttendanceAction({ action: 'status', id: item.id, status: event.target.value })
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Erro ao atualizar status');
            updateActiveFromPayload(data.cliente);
        })
        .catch(error => alert(error.message));
});

document.getElementById('messageForm').addEventListener('submit', event => {
    event.preventDefault();
    const item = getActive();
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    if (!item || !text) return;

    fetch('enviar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ numero: item.numero, mensagem: text })
    })
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.erro || 'Erro ao enviar mensagem');
            input.value = '';
            return fetch(`api_chat.php?id=${encodeURIComponent(item.id)}`);
        })
        .then(response => response.json())
        .then(data => {
            if (!data.ok) return;
            item.mensagens = data.mensagens;
            item.semResposta = false;
            item.tempoSemResposta = '-';
            renderConversationList();
            renderActiveConversation();
        })
        .catch(error => alert(error.message));
});

renderConversationList();
renderReplies();
renderActiveConversation();
</script>
</body>
</html>
