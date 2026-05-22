<?php
require_once __DIR__ . '/../auth/auth.php';
$currentUser = require_staff();
require_once __DIR__ . '/data_store.php';
require_once __DIR__ . '/../includes/system_settings.php';
require_once __DIR__ . '/../includes/team_settings.php';

date_default_timezone_set('America/Sao_Paulo');
$valorPomadaAnestesica = system_pomada_unit_price();
$tattooArtists = team_active_tattoo_artists();
$defaultTattooArtist = team_default_tattoo_artist();
$currentAttendant = team_current_attendant($currentUser);

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
        'atendenteId' => (string)($cliente['atendente_id'] ?? ''),
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
                'messageId' => (string)($msg['messageId'] ?? ''),
                'status' => (string)($msg['status'] ?? ''),
                'status_updated_at' => (string)($msg['status_updated_at'] ?? ''),
                'tipo' => (string)($msg['tipo'] ?? 'texto'),
                'mediaUrl' => (string)($msg['mediaUrl'] ?? ''),
                'mediaMime' => (string)($msg['mediaMime'] ?? ''),
                'mediaFileName' => (string)($msg['mediaFileName'] ?? ''),
                'transcricao' => (string)($msg['transcricao'] ?? ''),
                'transcricao_erro' => (string)($msg['transcricao_erro'] ?? ''),
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
$emHumano = count(array_filter($conversas, static fn(array $c): bool => $c['modo'] === 'humano'));
$emBot = max(0, $totalConversas - $emHumano);
$valorPotencial = array_reduce($conversas, static fn(float $total, array $c): float => $total + (float)$c['valor'], 0.0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Atendimento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/crm-theme.css?v=20260505-embedded-redesign">
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

        .conversation-item.is-unread {
            background: rgba(225, 29, 40, 0.08);
        }

        .conversation-item.is-unread .conversation-title {
            color: #fff;
        }

        .home-drilldown-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        @media (min-width: 1024px) {
            .home-drilldown-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .home-drilldown-card {
            width: 100%;
            min-height: 92px;
            border: 1px solid var(--crm-border);
            background: linear-gradient(180deg, rgba(18, 18, 22, 0.98), rgba(10, 10, 12, 0.98));
            color: inherit;
            text-align: left;
            padding: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: transform .15s ease, border-color .15s ease, background .15s ease;
        }

        .home-drilldown-card:hover {
            transform: translateY(-1px);
            border-color: rgba(225, 29, 40, 0.45);
            background: linear-gradient(180deg, rgba(26, 26, 30, 0.98), rgba(14, 14, 16, 0.98));
        }

        .home-drilldown-card .home-drilldown-title {
            font-weight: 800;
            font-size: 1rem;
            line-height: 1.1;
        }

        .home-drilldown-card .home-drilldown-foot {
            margin-top: 8px;
            color: var(--crm-muted);
            font-size: .82rem;
        }

        .home-drilldown-overlay .crm-modal-panel {
            max-width: min(1100px, calc(100vw - 24px));
        }

        .drilldown-list {
            display: grid;
            gap: 10px;
        }

        .drilldown-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid var(--crm-border);
            border-radius: 8px;
            background: rgba(255,255,255,0.02);
        }

        .drilldown-row-title {
            font-weight: 800;
        }

        .drilldown-row-meta {
            color: var(--crm-muted);
            font-size: .84rem;
            margin-top: 2px;
        }

        .conversation-unread-badge {
            min-width: 21px;
            height: 21px;
            padding: 0 6px;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            background: var(--crm-red);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 900;
            line-height: 1;
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

        .crm-icon-button {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            padding: 0;
        }

        .attendance-toggle {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-height: 42px;
            padding: 4px;
            border: 1px solid var(--crm-border);
            border-radius: 7px;
            background: #0b0c10;
        }

        .attendance-toggle button {
            min-height: 32px;
            padding: 0 10px;
            border: 0;
            border-radius: 5px;
            color: var(--crm-muted);
            background: transparent;
            font-size: 0.82rem;
            font-weight: 900;
            cursor: pointer;
        }

        .attendance-toggle button.is-active {
            color: #fff;
            background: linear-gradient(180deg, var(--crm-red), var(--crm-red-2));
        }

        .chat-tool-row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }

        .chat-attachment-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
            padding: 11px 13px;
            border: 1px solid var(--crm-border);
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.05);
            color: #e5e7eb;
            font-size: 0.88rem;
        }

        .chat-attachment-preview.hidden,
        .crm-modal.hidden,
        .emoji-panel.hidden {
            display: none;
        }

        .emoji-panel {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 12px 16px;
            border-top: 1px solid var(--crm-border);
            background: rgba(10, 11, 14, 0.96);
        }

        .emoji-panel button {
            width: 34px;
            height: 34px;
            border: 1px solid var(--crm-border);
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.04);
            font-size: 1.1rem;
            cursor: pointer;
        }

        .crm-modal {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(0, 0, 0, 0.78);
        }

        .crm-modal-panel {
            width: min(920px, 100%);
            max-height: 92vh;
            overflow-y: auto;
            border: 1px solid var(--crm-border-strong);
            border-radius: 8px;
            background: #101114;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.58);
        }

        .workspace-view.hidden {
            display: none;
        }

        .embedded-workspace {
            min-height: calc(100vh - 150px);
        }

        .embedded-frame-wrap {
            height: min(980px, calc(100vh - 270px));
            min-height: 660px;
            overflow: hidden;
            border-top: 1px solid var(--crm-border);
            background: #07080a;
        }

        .embedded-frame {
            width: 100%;
            height: 100%;
            border: 0;
            background: #050505;
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
            <a class="crm-button" href="dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
            <a class="crm-button crm-button-primary" href="respostas_rapidas.php"><i class="fa-solid fa-bolt"></i> Respostas</a>
            <a class="crm-button" href="automacao.php"><i class="fa-solid fa-robot"></i> Automacao</a>
        </div>
    </header>

    <div class="crm-workspace">
        <aside class="crm-sidebar">
            <div class="crm-brand">
                <span class="crm-brand-name">Daniel <span>Tatuador</span></span>
                <small>Atendimento, leads e fechamento</small>
            </div>
            <nav class="crm-nav" aria-label="Menu CRM">
                <a href="dashboard.php"><i class="fa-solid fa-chart-simple"></i> Dashboard</a>
                <a class="is-active" href="index.php"><i class="fa-solid fa-comments"></i> Atendimento</a>
                <a href="respostas_rapidas.php"><i class="fa-solid fa-bolt"></i> Respostas Rapidas</a>
                <a href="automacao.php?embed=1&v=20260505-automation" data-workspace-link data-title="Automacao" data-subtitle="Regras, alertas e mensagens automaticas" data-src="automacao.php?embed=1&v=20260505-automation"><i class="fa-solid fa-robot"></i> Automacao</a>
                <a href="financeiro.php?embed=1&v=20260505-financeiro" data-workspace-link data-title="Financeiro" data-subtitle="Faturamento, despesas e valores a receber" data-src="financeiro.php?embed=1&v=20260505-financeiro"><i class="fa-solid fa-wallet"></i> Financeiro</a>
                <a href="../ficha/agenda/?v=20260505-embedded-redesign" data-workspace-link data-title="Agenda" data-subtitle="Calendario e rotina do estudio" data-src="../ficha/agenda/?v=20260505-embedded-redesign"><i class="fa-regular fa-calendar"></i> Agenda</a>
                <a href="../ficha/index.php?v=20260505-embedded-redesign" data-workspace-link data-title="Ficha / Anamnese" data-subtitle="Cadastro, saude, autorizacoes e observacoes" data-src="../ficha/index.php?v=20260505-embedded-redesign"><i class="fa-regular fa-clipboard"></i> Ficha / Anamnese</a>
                <a href="relatorios.php?v=20260505-embedded-redesign" data-workspace-link data-title="Relatorios" data-subtitle="Resultados, origem dos leads e faturamento" data-src="relatorios.php?v=20260505-embedded-redesign"><i class="fa-solid fa-chart-line"></i> Relatorios</a>
                <a href="configuracoes.php?embed=1&v=20260505-financeiro" data-workspace-link data-title="Configuracoes" data-subtitle="Pipeline, gatilhos e preferencias do CRM" data-src="configuracoes.php?embed=1&v=20260505-financeiro"><i class="fa-solid fa-gear"></i> Configuracoes</a>
            </nav>
        </aside>

        <main class="crm-main">
            <div id="attendanceView" class="workspace-view">
            <section class="home-drilldown-grid mb-4">
                <button type="button" class="home-drilldown-card" data-home-drilldown="conversas">
                    <div class="home-drilldown-title">Conversas</div>
                    <div class="home-drilldown-foot">Abrir detalhes</div>
                </button>
                <button type="button" class="home-drilldown-card" data-home-drilldown="nao_respondidas">
                    <div class="home-drilldown-title">Nao respondidas</div>
                    <div class="home-drilldown-foot">Abrir detalhes</div>
                </button>
                <button type="button" class="home-drilldown-card" data-home-drilldown="leads_quentes">
                    <div class="home-drilldown-title">Leads quentes</div>
                    <div class="home-drilldown-foot">Abrir detalhes</div>
                </button>
                <button type="button" class="home-drilldown-card" data-home-drilldown="agendados">
                    <div class="home-drilldown-title">Agendados</div>
                    <div class="home-drilldown-foot">Abrir detalhes</div>
                </button>
            </section>

            <section class="crm-panel mb-4">
                <div class="crm-panel-header">
                    <h2 class="crm-panel-title"><i class="fa-solid fa-chart-line"></i> Resumo do atendimento</h2>
                    <button class="crm-button" type="button" data-workspace-trigger data-title="Relatorios" data-subtitle="Resultados, origem dos leads e faturamento" data-src="relatorios.php?v=20260505-embedded-redesign">
                        <i class="fa-solid fa-chart-line"></i> Relatorios
                    </button>
                </div>
                <div class="home-drilldown-grid p-4">
                    <button type="button" class="home-drilldown-card" data-home-drilldown="modo_atendimento">
                        <div class="home-drilldown-title">Humano / bot</div>
                        <div class="home-drilldown-foot">Abrir detalhes</div>
                    </button>
                    <button type="button" class="home-drilldown-card" data-home-drilldown="potencial">
                        <div class="home-drilldown-title">Potencial das conversas</div>
                        <div class="home-drilldown-foot">Abrir detalhes</div>
                    </button>
                    <button type="button" class="home-drilldown-card" data-home-drilldown="pressao_fila">
                        <div class="home-drilldown-title">Pressao da fila</div>
                        <div class="home-drilldown-foot">Abrir detalhes</div>
                    </button>
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
                            <button id="scheduleButton" type="button" class="crm-button"><i class="fa-regular fa-calendar-plus"></i> Agendar</button>
                            <div class="attendance-toggle" aria-label="Modo de atendimento">
                                <button id="botModeButton" type="button">Bot</button>
                                <button id="humanModeButton" type="button">Humano</button>
                            </div>
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

                    <div id="assignmentNotice" class="hidden mx-4 mt-4 crm-card p-3 text-sm font-bold"></div>

                    <div id="chatMessages" class="chat-messages"></div>

                    <div id="emojiPanel" class="emoji-panel hidden">
                        <button type="button" data-emoji="😀">😀</button>
                        <button type="button" data-emoji="😂">😂</button>
                        <button type="button" data-emoji="😍">😍</button>
                        <button type="button" data-emoji="🙏">🙏</button>
                        <button type="button" data-emoji="👍">👍</button>
                        <button type="button" data-emoji="🔥">🔥</button>
                        <button type="button" data-emoji="✨">✨</button>
                        <button type="button" data-emoji="❤️">❤️</button>
                    </div>

                    <form id="messageForm" class="p-4 border-t border-white/10">
                        <div id="attachmentPreview" class="chat-attachment-preview hidden"></div>
                        <div class="chat-tool-row">
                            <button id="emojiButton" class="crm-button crm-icon-button" type="button" title="Emoji"><i class="fa-regular fa-face-smile"></i></button>
                            <input id="chatFile" type="file" class="hidden" accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.txt">
                            <button id="fileButton" class="crm-button crm-icon-button" type="button" title="Anexar"><i class="fa-solid fa-paperclip"></i></button>
                            <button id="recordAudioBtn" class="crm-button crm-icon-button" type="button" title="Gravar audio"><i class="fa-solid fa-microphone"></i></button>
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
            </div>

            <section id="embeddedView" class="workspace-view hidden crm-panel embedded-workspace">
                <div class="crm-panel-header">
                    <div>
                        <h2 id="embeddedTitle" class="crm-panel-title"><i class="fa-solid fa-window-maximize"></i> Painel</h2>
                        <p id="embeddedSubtitle" class="crm-muted text-sm mt-2">Carregado dentro da Central de Atendimento.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="backToAttendanceButton" type="button" class="crm-button"><i class="fa-solid fa-comments"></i> Atendimento</button>
                        <a id="openEmbeddedButton" class="crm-button" href="#" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir separado</a>
                    </div>
                </div>
                <div class="embedded-frame-wrap">
                    <iframe id="embeddedFrame" class="embedded-frame" title="Painel integrado"></iframe>
                </div>
            </section>
        </main>
    </div>
</div>

<div id="scheduleOverlay" class="crm-modal hidden">
    <div class="crm-modal-panel">
        <div class="crm-panel-header">
            <div>
                <h3 class="crm-panel-title"><i class="fa-regular fa-calendar-plus"></i> Agendar tatuagem</h3>
                <p class="crm-muted text-sm mt-2">Busque um cliente existente ou crie um cadastro basico com nome e telefone.</p>
            </div>
            <button type="button" id="closeScheduleButton" class="crm-button crm-icon-button" title="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="scheduleForm" class="p-5 space-y-5">
            <input type="hidden" id="scheduleClienteId" name="cliente_id">
            <div class="crm-card p-4">
                <label class="crm-muted text-sm block mb-2" for="scheduleClientSearch">Pesquisar cliente</label>
                <input type="text" id="scheduleClientSearch" placeholder="Digite nome, telefone ou e-mail" class="crm-input">
                <div id="scheduleClientResults" class="hidden mt-3 space-y-2"></div>
                <p id="scheduleClientNotice" class="text-sm text-amber-300 mt-3"></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="crm-muted text-sm block mb-2" for="scheduleNome">Nome do cliente *</label>
                    <input type="text" id="scheduleNome" name="nome" required class="crm-input">
                </div>
                <div>
                    <label class="crm-muted text-sm block mb-2" for="scheduleTelefone">Telefone *</label>
                    <input type="tel" id="scheduleTelefone" name="telefone" required class="crm-input">
                </div>
                <div>
                    <label class="crm-muted text-sm block mb-2" for="scheduleData">Data *</label>
                    <input type="date" id="scheduleData" name="data_tatuagem" required class="crm-input">
                </div>
                <div>
                    <label class="crm-muted text-sm block mb-2" for="scheduleArtist">Tatuador *</label>
                    <select id="scheduleArtist" name="tatuador_id" required class="crm-select">
                        <?php foreach ($tattooArtists as $artist): ?>
                            <option value="<?= atendimento_h((string)$artist['id']) ?>"><?= atendimento_h((string)$artist['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="crm-muted text-sm block mb-2" for="scheduleHoraInicio">Hora inicio *</label>
                        <input type="time" id="scheduleHoraInicio" name="hora_inicio" required class="crm-input">
                    </div>
                    <div>
                        <label class="crm-muted text-sm block mb-2" for="scheduleHoraFim">Hora fim</label>
                        <input type="time" id="scheduleHoraFim" name="hora_fim" class="crm-input">
                    </div>
                </div>
                <div>
                    <label class="crm-muted text-sm block mb-2" for="scheduleValor">Valor base da tatuagem (R$)</label>
                    <input type="number" step="0.01" id="scheduleValor" name="valor" class="crm-input">
                </div>
                <div>
                    <label class="crm-muted text-sm block mb-2" for="schedulePomadas">Pomadas anestesicas</label>
                    <input type="number" min="0" step="1" id="schedulePomadas" name="pomadas_anestesicas" value="0" class="crm-input">
                    <div class="crm-muted text-xs mt-1">+ <?= 'R$ ' . number_format($valorPomadaAnestesica, 2, ',', '.') ?> por unidade</div>
                </div>
                <div class="md:col-span-2 crm-card p-4">
                    <div class="crm-muted text-sm">Total com pomadas</div>
                    <div id="scheduleTotalPreview" class="text-2xl font-black mt-1">R$ 0,00</div>
                </div>
                <div class="md:col-span-2">
                    <label class="crm-muted text-sm block mb-2" for="scheduleDescricao">Descricao / arte pretendida</label>
                    <input type="text" id="scheduleDescricao" name="descricao" placeholder="Ex.: Fechamento de braço, fine line, cobertura..." class="crm-input">
                </div>
                <div class="md:col-span-2">
                    <label class="crm-muted text-sm block mb-2" for="scheduleObservacoes">Observacoes</label>
                    <textarea id="scheduleObservacoes" name="observacoes" rows="3" class="crm-textarea"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="crm-muted text-sm block mb-2" for="scheduleReferencia">Arte de referencia (opcional)</label>
                    <input type="file" id="scheduleReferencia" name="referencia" accept="image/*,.pdf" class="crm-input pt-2">
                </div>
            </div>

            <div id="scheduleResult" class="hidden crm-card p-4 text-sm"></div>

            <div class="flex flex-col md:flex-row gap-3 pt-2">
                <button type="button" id="cancelScheduleButton" class="crm-button flex-1">Cancelar</button>
                <button type="submit" id="scheduleSubmit" class="crm-button crm-button-primary flex-1">
                    Salvar agendamento
                </button>
            </div>
        </form>
    </div>
</div>

<div id="homeDrilldownOverlay" class="crm-modal hidden home-drilldown-overlay">
    <div class="crm-modal-panel">
        <div class="crm-panel-header">
            <div>
                <h3 id="homeDrilldownTitle" class="crm-panel-title"><i class="fa-solid fa-chart-column"></i> Detalhes</h3>
                <p id="homeDrilldownSubtitle" class="crm-muted text-sm mt-2">Detalhes do panorama</p>
            </div>
            <button type="button" id="closeHomeDrilldown" class="crm-button crm-icon-button" title="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="homeDrilldownBody" class="p-5"></div>
    </div>
</div>

<script>
const conversations = <?= json_encode($conversas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const homeDrilldownData = {
    totalConversas: <?= (int)$totalConversas ?>,
    naoRespondidas: <?= (int)$naoRespondidas ?>,
    leadsQuentes: <?= (int)$leadsQuentes ?>,
    agendados: <?= (int)$agendados ?>,
    emHumano: <?= (int)$emHumano ?>,
    emBot: <?= (int)$emBot ?>,
    valorPotencial: <?= json_encode(number_format($valorPotencial, 2, ',', '.'), JSON_UNESCAPED_UNICODE) ?>,
    conversaList: <?= json_encode(array_map(static function (array $item): array {
        return [
            'id' => $item['id'] ?? '',
            'nome' => $item['nome'] ?? '',
            'numero' => $item['numero'] ?? '',
            'status' => $item['status'] ?? '',
            'statusLabel' => $item['statusLabel'] ?? '',
            'modo' => $item['modo'] ?? '',
            'ultimaMensagem' => $item['ultimaMensagem'] ?? '',
            'ultimaData' => $item['ultimaData'] ?? '',
            'tempoSemResposta' => $item['tempoSemResposta'] ?? '',
            'semResposta' => !empty($item['semResposta']),
            'interesse' => $item['interesse'] ?? '',
            'valor' => number_format((float)($item['valor'] ?? 0), 2, ',', '.'),
        ];
    }, array_slice($conversas, 0, 12)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
};
const quickReplies = <?= json_encode($respostasRapidas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const pomadaUnitPrice = <?= json_encode($valorPomadaAnestesica) ?>;
const currentAttendant = <?= json_encode($currentAttendant, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const defaultTattooArtistId = <?= json_encode((string)($defaultTattooArtist['id'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
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
let readState = {};
const pendingTranscriptions = {};
let mediaRecorder = null;
let recordedAudioChunks = [];
let recordedAudioFile = null;
let recordingTimer = null;
let recordingStartedAt = 0;
let scheduleSearchTimer = null;
let chatPollTimer = null;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function mediaSource(path) {
    const value = String(path ?? '');
    if (/^(https?:|blob:|data:)/i.test(value)) return value;
    return 'media.php?path=' + encodeURIComponent(value);
}

function getActive() {
    return conversations.find(item => item.id === activeId) || conversations[0] || null;
}

function assignmentOwner(item) {
    const mode = String(item?.modo || item?.modo_atendimento || '').toLowerCase();
    if (mode !== 'humano') return { id: '', nome: '' };
    return {
        id: String(item?.atendenteId || item?.atendente_id || '').trim(),
        nome: String(item?.atendente || '').trim(),
    };
}

function isAssignedToMe(item) {
    const owner = assignmentOwner(item);
    if (!owner.id && !owner.nome) return false;
    const myId = String(currentAttendant?.id || '').trim();
    const myName = String(currentAttendant?.nome || '').trim().toLowerCase();
    if (owner.id && myId && owner.id === myId) return true;
    return !owner.id && owner.nome && owner.nome.toLowerCase() === myName;
}

function isAssignedToOther(item) {
    const owner = assignmentOwner(item);
    return Boolean((owner.id || owner.nome) && !isAssignedToMe(item));
}

function canInteract(item) {
    return isAssignedToMe(item);
}

function parseChatDate(value) {
    if (!value) return null;
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? null : date;
}

function conversationReadKey(itemOrId) {
    const raw = typeof itemOrId === 'object'
        ? (itemOrId?.clienteId || itemOrId?.id || '')
        : itemOrId;
    return String(raw || '').replace(/^wa_/, '');
}

function conversationByReadKey(itemOrId) {
    if (typeof itemOrId === 'object' && itemOrId !== null) return itemOrId;
    const id = conversationReadKey(itemOrId);
    return conversations.find(item => conversationReadKey(item) === id) || null;
}

function unreadCount(item) {
    const messages = Array.isArray(item?.mensagens) ? item.mensagens : [];
    const lastRead = parseChatDate(readState[conversationReadKey(item)] || '');
    let count = 0;

    for (const msg of messages) {
        if (msg.fromMe) continue;
        const msgDate = parseChatDate(msg.data);
        if (!lastRead || !msgDate || msgDate > lastRead) {
            count++;
        }
    }

    return count;
}

async function loadReadState() {
    try {
        const response = await fetch('whatsapp/read_state.php', { cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json().catch(() => null);
        if (data?.ok && data.read && typeof data.read === 'object') {
            readState = data.read;
        }
    } catch (error) {
        // Sem estado remoto, a tela continua funcionando com o estado local vazio.
    }
}

async function markConversationRead(itemOrId) {
    const item = conversationByReadKey(itemOrId);
    if (!item || !canInteract(item)) return;

    const id = conversationReadKey(item);
    if (!id) return;

    readState[id] = new Date().toISOString().slice(0, 19).replace('T', ' ');
    renderConversationList();

    try {
        const response = await fetch('whatsapp/read_state.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await response.json().catch(() => null);
        if (data?.ok && data.read_at) {
            readState[id] = data.read_at;
            renderConversationList();
        }
    } catch (error) {
        // Mantem a leitura visual local mesmo que a gravacao falhe por instante.
    }
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

    list.innerHTML = filtered.map(item => {
        const unread = unreadCount(item);
        const itemClasses = [
            'conversation-item',
            item.id === activeId ? 'is-active' : '',
            unread ? 'is-unread' : ''
        ].filter(Boolean).join(' ');

        return `
        <button class="${itemClasses}" data-id="${escapeHtml(item.id)}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="conversation-title font-black truncate">${escapeHtml(item.nome)}</div>
                    <div class="crm-muted text-xs mt-1">${escapeHtml(item.numero || 'Sem telefone')}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    ${unread ? `<span class="conversation-unread-badge">${unread}</span>` : ''}
                    <span class="crm-status ${item.modo === 'humano' ? 'crm-status-green' : ''}">${item.modo === 'humano' ? 'Humano' : 'Bot'}</span>
                </div>
            </div>
            <p class="text-sm text-gray-300 mt-3 line-clamp-2">${escapeHtml(item.ultimaMensagem || 'Sem mensagens')}</p>
            <div class="flex items-center justify-between gap-2 mt-3">
                <span class="crm-status ${statusClass(item.status)}">${escapeHtml(item.statusLabel)}</span>
                <span class="text-xs ${unread ? 'text-red-300' : (item.semResposta ? 'text-amber-300' : 'text-gray-500')}">${unread ? 'Nao lida' : (item.semResposta ? item.tempoSemResposta + ' sem resposta' : item.ultimaData)}</span>
            </div>
        </button>
    `;
    }).join('');

    list.querySelectorAll('.conversation-item').forEach(button => {
        button.addEventListener('click', () => {
            activeId = button.dataset.id;
            renderConversationList();
            renderActiveConversation(true);
            markConversationRead(activeId);
        });
    });
}

function renderMessages(messages) {
    const box = document.getElementById('chatMessages');
    if (!messages?.length) {
        box.innerHTML = '<div class="h-full grid place-items-center crm-muted">Nenhuma mensagem encontrada.</div>';
        return;
    }

    messages.forEach(msg => {
        const key = transcriptionKey(msg.messageId || '', msg.mediaUrl || '');
        if (pendingTranscriptions[key] && (msg.transcricao || msg.transcricao_erro)) {
            clearInterval(pendingTranscriptions[key].timer);
            delete pendingTranscriptions[key];
        }
    });

    box.innerHTML = messages.map(msg => `
        <div class="message-row ${msg.fromMe ? 'is-me' : ''}">
            <div class="message-bubble">
                ${renderChatMedia(msg)}
                ${msg.texto ? `<div class="whitespace-pre-wrap break-words ${msg.mediaUrl ? 'mt-2' : ''}">${escapeHtml(msg.texto)}</div>` : ''}
                ${msg.transcricao ? `<div class="mt-3 bg-black/25 rounded-md px-3 py-2 text-sm"><strong>Transcricao:</strong> ${escapeHtml(msg.transcricao)}</div>` : ''}
                ${msg.transcricao_erro ? `<div class="mt-3 bg-red-950/40 border border-red-800/60 rounded-md px-3 py-2 text-sm text-red-100"><strong>Erro na transcricao:</strong> ${escapeHtml(msg.transcricao_erro)}</div>` : ''}
                <div class="text-[11px] text-gray-300 mt-2 text-right">${escapeHtml(msg.hora || '')} ${renderMessageStatus(msg)}</div>
            </div>
        </div>
    `).join('');
    box.scrollTop = box.scrollHeight;
}

function renderMessageStatus(msg) {
    if (!msg.fromMe && !msg.status) return '';

    const status = msg.status || 'sent';
    const icons = {
        pending: '<i class="fas fa-clock text-gray-300" title="Enviando"></i>',
        sent: '<i class="fas fa-check text-gray-300" title="Enviada"></i>',
        delivered: '<span class="text-gray-300" title="Entregue">✓✓</span>',
        read: '<span class="text-sky-300 font-semibold" title="Visualizada">✓✓</span>',
        played: '<span class="text-sky-300 font-semibold" title="Reproduzida">✓✓</span>',
        error: '<i class="fas fa-triangle-exclamation text-red-300" title="Erro"></i>'
    };

    return icons[status] || icons.sent;
}

function renderChatMedia(msg) {
    if (!msg.mediaUrl) return '';

    const rawUrl = String(msg.mediaUrl || '');
    const url = escapeHtml(mediaSource(rawUrl));
    const transcribeUrl = escapeHtml(rawUrl);
    const mime = msg.mediaMime || '';
    const fileName = escapeHtml(msg.mediaFileName || 'arquivo');
    const pending = pendingTranscriptions[transcriptionKey(msg.messageId || '', msg.mediaUrl || '')];

    if (mime.startsWith('image/')) {
        return `<a href="${url}" target="_blank"><img src="${url}" class="max-h-72 rounded-md object-contain bg-black/20"></a>`;
    }
    if (mime.startsWith('video/')) {
        return `<video src="${url}" controls class="max-h-72 rounded-md bg-black/20"></video>`;
    }
    if (mime.startsWith('audio/') || msg.tipo === 'audio') {
        return `
            <div class="space-y-2">
                <audio src="${url}" controls class="w-72 max-w-full"></audio>
                ${pending ? renderTranscriptionProgress(pending) : `<button type="button" onclick="transcribeAudio(this, '${escapeHtml(msg.messageId || '')}', '${transcribeUrl}')" class="crm-button text-xs min-h-[34px]">Transcrever audio</button>`}
            </div>
        `;
    }

    return `<a href="${url}" target="_blank" class="flex items-center gap-3 bg-black/25 hover:bg-black/35 rounded-md px-3 py-3"><i class="fas fa-file"></i><span class="break-all">${fileName}</span></a>`;
}

function transcriptionKey(messageId, url) {
    return messageId || url;
}

function renderTranscriptionProgress(state) {
    const progress = Number(state?.progress) || 5;
    const elapsed = Math.max(0, Math.floor((Date.now() - (state?.startedAt || Date.now())) / 1000));
    const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
    const seconds = String(elapsed % 60).padStart(2, '0');
    const safeProgress = Math.max(5, Math.min(95, progress));
    let phase = 'Preparando audio...';
    if (elapsed >= 10) phase = 'Carregando modelo small...';
    if (elapsed >= 45) phase = 'Transcrevendo com mais qualidade...';
    if (elapsed >= 120) phase = 'Ainda trabalhando. A primeira vez pode demorar.';

    return `
        <div class="bg-black/30 rounded-md px-3 py-2 w-72 max-w-full">
            <div class="flex justify-between text-[11px] text-gray-200 mb-1">
                <span>${phase}</span>
                <span>${minutes}:${seconds}</span>
            </div>
            <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                <div class="h-full bg-red-500 rounded-full transition-all" style="width: ${safeProgress}%"></div>
            </div>
            <div class="text-[10px] text-gray-400 mt-1">${safeProgress}% estimado</div>
        </div>
    `;
}

function transcribeAudio(button, messageId, url) {
    const key = transcriptionKey(messageId, url);
    const oldText = button.textContent;
    button.disabled = true;
    button.textContent = 'Transcrevendo...';
    pendingTranscriptions[key] = {
        progress: 8,
        startedAt: Date.now(),
        timer: setInterval(() => {
            const current = pendingTranscriptions[key];
            if (!current) return;
            const elapsed = Math.floor((Date.now() - current.startedAt) / 1000);
            current.progress = Math.min(95, current.progress + (elapsed < 30 ? 6 : 2));
            renderActiveConversation();
        }, 2000)
    };
    renderActiveConversation();

    fetch('transcrever_audio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ messageId, mediaUrl: url, model: 'small' })
    })
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.error || 'Nao foi possivel transcrever o audio');
            return fetch(`api_chat.php?id=${encodeURIComponent(getActive()?.id || '')}`);
        })
        .then(response => response.json())
        .then(data => {
            const item = getActive();
            if (item && data.ok) item.mensagens = data.mensagens;
        })
        .catch(error => alert(error.message))
        .finally(() => {
            clearInterval(pendingTranscriptions[key]?.timer);
            delete pendingTranscriptions[key];
            button.disabled = false;
            button.textContent = oldText;
            renderActiveConversation();
        });
}

function renderActiveConversation(fetchFresh = false) {
    const item = getActive();
    const assumeButton = document.getElementById('assumeButton');
    const statusSelect = document.getElementById('statusSelect');
    const scheduleButton = document.getElementById('scheduleButton');
    const assignmentNotice = document.getElementById('assignmentNotice');
    const input = document.getElementById('messageInput');

    if (!item) {
        document.getElementById('chatName').textContent = 'Nenhuma conversa';
        document.getElementById('chatMeta').textContent = 'As conversas do WhatsApp aparecem aqui quando existirem.';
        document.getElementById('chatMessages').innerHTML = '<div class="h-full grid place-items-center crm-muted">Sem conversas.</div>';
        assumeButton.disabled = true;
        statusSelect.disabled = true;
        scheduleButton.disabled = true;
        assignmentNotice.classList.add('hidden');
        return;
    }

    const owner = assignmentOwner(item);
    const mine = isAssignedToMe(item);
    const other = isAssignedToOther(item);
    assumeButton.disabled = mine || other;
    statusSelect.disabled = !mine;
    scheduleButton.disabled = !mine;
    input.disabled = !mine;
    input.placeholder = mine ? 'Digite uma mensagem ou clique em uma resposta pronta...' : (other ? 'Conversa atribuida a outro atendente' : 'Assuma a conversa para responder');
    document.getElementById('emojiButton').disabled = !mine;
    document.getElementById('fileButton').disabled = !mine;
    document.getElementById('recordAudioBtn').disabled = !mine;
    document.querySelector('#messageForm button[type="submit"]').disabled = !mine;
    document.getElementById('botModeButton').disabled = !mine;
    document.getElementById('humanModeButton').disabled = other;
    assumeButton.innerHTML = mine ? '<i class="fa-solid fa-user-check"></i> Assumido' : '<i class="fa-solid fa-user-check"></i> Assumir';
    assignmentNotice.classList.remove('hidden');
    assignmentNotice.classList.toggle('border-amber-500/30', other || !mine);
    assignmentNotice.textContent = mine
        ? 'Conversa assumida por voce. Outros atendentes visualizam, mas nao interagem.'
        : (other ? `Somente visualizacao: ${owner.nome || 'outro atendente'} ja assumiu esta conversa.` : 'Conversa disponivel. Clique em Assumir para responder e alterar dados.');
    document.getElementById('chatName').textContent = item.nome;
    document.getElementById('chatMeta').textContent = `${item.numero || 'sem telefone'} • ${item.origem || 'WhatsApp'} • ${item.interesse || 'sem interesse definido'}`;
    document.getElementById('modeBadge').textContent = item.modo === 'humano' ? `Humano: ${item.atendente}` : 'Bot em atendimento';
    document.getElementById('modeBadge').className = `mt-2 crm-status ${item.modo === 'humano' ? 'crm-status-green' : ''}`;
    document.getElementById('delayBadge').textContent = item.semResposta ? `${item.tempoSemResposta} sem resposta` : 'Respondido';
    document.getElementById('delayBadge').className = `mt-2 crm-status ${item.semResposta ? 'crm-status-hot' : 'crm-status-green'}`;
    statusSelect.value = item.status in statusLabels ? item.status : 'novo';
    document.getElementById('botModeButton').classList.toggle('is-active', item.modo !== 'humano');
    document.getElementById('humanModeButton').classList.toggle('is-active', item.modo === 'humano');

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
                if (unreadCount(item) > 0) markConversationRead(item);
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
            if (!canInteract(getActive())) return;
            const reply = quickReplies.find(item => item.id === button.dataset.replyId);
            const input = document.getElementById('messageInput');
            input.value = reply?.texto || '';
            input.focus();
        });
    });
}

function openHomeDrilldown(key) {
    const overlay = document.getElementById('homeDrilldownOverlay');
    const title = document.getElementById('homeDrilldownTitle');
    const subtitle = document.getElementById('homeDrilldownSubtitle');
    const body = document.getElementById('homeDrilldownBody');
    const conversationsSorted = [...conversations].sort((a, b) => (b.ultimaTimestamp || 0) - (a.ultimaTimestamp || 0));
    const unread = conversationsSorted.filter(item => item.semResposta);
    const quente = conversationsSorted.filter(item => item.status === 'lead_quente');
    const agendado = conversationsSorted.filter(item => item.status === 'agendado');
    const humanos = conversationsSorted.filter(item => item.modo === 'humano');
    const bots = conversationsSorted.filter(item => item.modo !== 'humano');
    const formatMoney = value => `R$ ${Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const sections = {
        conversas: {
            title: 'Conversas',
            subtitle: `${homeDrilldownData.totalConversas} conversa(s) no total`,
            html: renderDrilldownList(conversationsSorted.slice(0, 8).map(item => ({
                title: item.nome || 'Cliente',
                meta: `${item.numero || 'sem telefone'} • ${item.statusLabel || item.status || 'sem status'}`,
                right: item.ultimaData || item.tempoSemResposta || '',
                note: item.ultimaMensagem || 'Sem ultima mensagem visivel.',
            })))
        },
        nao_respondidas: {
            title: 'Nao respondidas',
            subtitle: `${unread.length} conversa(s) aguardando resposta`,
            html: renderDrilldownList(unread.slice(0, 10).map(item => ({
                title: item.nome || 'Cliente',
                meta: `${item.numero || 'sem telefone'} • ${item.tempoSemResposta || 'sem tempo'}`,
                right: item.statusLabel || item.status || '',
                note: item.ultimaMensagem || 'Sem ultima mensagem visivel.',
            })))
        },
        leads_quentes: {
            title: 'Leads quentes',
            subtitle: `${quente.length} lead(s) com interesse forte`,
            html: renderDrilldownList(quente.slice(0, 10).map(item => ({
                title: item.nome || 'Cliente',
                meta: `${item.numero || 'sem telefone'} • ${item.interesse || 'sem interesse'}`,
                right: item.statusLabel || '',
                note: item.ultimaMensagem || 'Sem ultima mensagem visivel.',
            })))
        },
        agendados: {
            title: 'Agendados',
            subtitle: `${agendado.length} conversa(s) em agendamento`,
            html: renderDrilldownList(agendado.slice(0, 10).map(item => ({
                title: item.nome || 'Cliente',
                meta: `${item.numero || 'sem telefone'} • ${item.ultimaData || 'sem data'}`,
                right: item.modo === 'humano' ? 'Humano' : 'Bot',
                note: item.ultimaMensagem || 'Sem ultima mensagem visivel.',
            })))
        },
        modo_atendimento: {
            title: 'Humano / bot',
            subtitle: `${homeDrilldownData.emHumano} em humano e ${homeDrilldownData.emBot} em bot`,
            html: `
                <div class="drilldown-list">
                    <div class="drilldown-row">
                        <div>
                            <div class="drilldown-row-title">Em humano</div>
                            <div class="drilldown-row-meta">Conversas assumidas manualmente</div>
                        </div>
                        <strong>${homeDrilldownData.emHumano}</strong>
                    </div>
                    <div class="drilldown-row">
                        <div>
                            <div class="drilldown-row-title">Em bot</div>
                            <div class="drilldown-row-meta">Conversas aptas a resposta automatica</div>
                        </div>
                        <strong>${homeDrilldownData.emBot}</strong>
                    </div>
                    <div class="drilldown-row">
                        <div>
                            <div class="drilldown-row-title">Observacao</div>
                            <div class="drilldown-row-meta">Bot nao responde quando a conversa esta em modo humano.</div>
                        </div>
                    </div>
                </div>
            `,
        },
        potencial: {
            title: 'Potencial das conversas',
            subtitle: `Total estimado: ${formatMoney(homeDrilldownData.valorPotencial)}`,
            html: renderDrilldownList(conversationsSorted.slice(0, 10).map(item => ({
                title: item.nome || 'Cliente',
                meta: `${item.numero || 'sem telefone'} • ${item.statusLabel || item.status || ''}`,
                right: formatMoney(item.valor),
                note: item.interesse || 'Sem interesse definido.',
            })))
        },
        pressao_fila: {
            title: 'Pressao da fila',
            subtitle: unread.length ? `${unread.length} aguardando resposta` : 'Fila em dia',
            html: `
                <div class="drilldown-list">
                    <div class="drilldown-row">
                        <div>
                            <div class="drilldown-row-title">Sem resposta</div>
                            <div class="drilldown-row-meta">Conversas que precisam de atenção</div>
                        </div>
                        <strong>${homeDrilldownData.naoRespondidas}</strong>
                    </div>
                    <div class="drilldown-row">
                        <div>
                            <div class="drilldown-row-title">Ultimas conversas com atraso</div>
                            <div class="drilldown-row-meta">${unread.slice(0, 5).map(item => item.nome || 'Cliente').join(' · ') || 'Nenhuma'}</div>
                        </div>
                    </div>
                </div>
            `,
        },
    };

    const selected = sections[key] || sections.conversas;
    title.textContent = selected.title;
    subtitle.textContent = selected.subtitle;
    body.innerHTML = selected.html;
    overlay.classList.remove('hidden');
}

function renderDrilldownList(items) {
    if (!items.length) {
        return '<div class="crm-card p-4">Nenhum detalhe para mostrar.</div>';
    }

    return `
        <div class="drilldown-list">
            ${items.map(item => `
                <div class="drilldown-row">
                    <div class="min-w-0">
                        <div class="drilldown-row-title">${escapeHtml(item.title || '')}</div>
                        <div class="drilldown-row-meta">${escapeHtml(item.meta || '')}</div>
                        <div class="drilldown-row-meta mt-2">${escapeHtml(item.note || '')}</div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="font-black">${escapeHtml(item.right || '')}</div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function closeHomeDrilldown() {
    document.getElementById('homeDrilldownOverlay').classList.add('hidden');
}

function updateActiveFromPayload(cliente) {
    const item = getActive();
    if (!item || !cliente) return;

    item.atendente = cliente.atendente || item.atendente;
    item.atendenteId = cliente.atendente_id || cliente.atendenteId || '';
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

function showAttendanceWorkspace() {
    document.getElementById('attendanceView').classList.remove('hidden');
    document.getElementById('embeddedView').classList.add('hidden');
    document.getElementById('embeddedFrame').removeAttribute('src');
    document.querySelectorAll('[data-workspace-link]').forEach(link => link.classList.remove('is-active'));
    const attendanceLink = document.querySelector('.crm-nav a[href="index.php"]');
    if (attendanceLink) attendanceLink.classList.add('is-active');
}

function showEmbeddedWorkspace({ title, subtitle, src }) {
    document.getElementById('attendanceView').classList.add('hidden');
    document.getElementById('embeddedView').classList.remove('hidden');
    document.getElementById('embeddedTitle').innerHTML = `<i class="fa-solid fa-window-maximize"></i> ${escapeHtml(title || 'Painel')}`;
    document.getElementById('embeddedSubtitle').textContent = subtitle || 'Carregado dentro da Central de Atendimento.';
    document.getElementById('embeddedFrame').src = src;
    document.getElementById('openEmbeddedButton').href = src;

    document.querySelectorAll('.crm-nav a').forEach(link => link.classList.remove('is-active'));
    const attendanceLink = document.querySelector('.crm-nav a[href="index.php"]');
    if (attendanceLink) attendanceLink.classList.remove('is-active');
    document.querySelectorAll('[data-workspace-link]').forEach(link => {
        if (link.dataset.src === src) {
            link.classList.add('is-active');
        }
    });
}

function insertEmoji(emoji) {
    const input = document.getElementById('messageInput');
    const start = input.selectionStart;
    const end = input.selectionEnd;
    input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
    input.focus();
    input.selectionStart = input.selectionEnd = start + emoji.length;
}

function updateAttachmentPreview() {
    const input = document.getElementById('chatFile');
    const preview = document.getElementById('attachmentPreview');
    const file = recordedAudioFile || input.files?.[0];

    if (!file) {
        preview.classList.add('hidden');
        preview.innerHTML = '';
        return;
    }

    preview.classList.remove('hidden');
    preview.innerHTML = `
        <span class="truncate"><i class="fas fa-paperclip mr-2"></i>${escapeHtml(file.name)}</span>
        <button type="button" onclick="removeAttachment()" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
    `;
}

function removeAttachment() {
    const input = document.getElementById('chatFile');
    if (input) input.value = '';
    recordedAudioFile = null;
    const preview = document.getElementById('attachmentPreview');
    preview.classList.add('hidden');
    preview.innerHTML = '';
}

function updateRecordingPreview() {
    const preview = document.getElementById('attachmentPreview');
    const elapsed = Math.max(0, Math.floor((Date.now() - recordingStartedAt) / 1000));
    const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
    const seconds = String(elapsed % 60).padStart(2, '0');
    preview.classList.remove('hidden');
    preview.innerHTML = `
        <span class="flex items-center gap-2 text-red-200"><i class="fas fa-circle text-red-400 text-[10px]"></i> Gravando ${minutes}:${seconds}</span>
        <button type="button" onclick="stopAudioRecording(true)" class="text-red-200 hover:text-white text-xs">Cancelar</button>
    `;
}

async function toggleAudioRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
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
        mediaRecorder = new MediaRecorder(stream, options);
        recordedAudioChunks = [];
        recordedAudioFile = null;

        mediaRecorder.ondataavailable = event => {
            if (event.data && event.data.size > 0) recordedAudioChunks.push(event.data);
        };

        mediaRecorder.onstop = () => {
            stream.getTracks().forEach(track => track.stop());
            clearInterval(recordingTimer);
            recordingTimer = null;
            document.getElementById('recordAudioBtn').classList.remove('crm-button-primary');
        };

        recordingStartedAt = Date.now();
        mediaRecorder.start();
        document.getElementById('recordAudioBtn').classList.add('crm-button-primary');
        updateRecordingPreview();
        recordingTimer = setInterval(updateRecordingPreview, 500);
    } catch (error) {
        alert('Nao consegui acessar o microfone: ' + (error.message || error));
    }
}

function stopAudioRecording(cancel = false) {
    if (!mediaRecorder || mediaRecorder.state !== 'recording') return;

    const recorder = mediaRecorder;
    recorder.addEventListener('stop', () => {
        if (!cancel && recordedAudioChunks.length) {
            const mime = recorder.mimeType || 'audio/webm';
            const blob = new Blob(recordedAudioChunks, { type: mime });
            recordedAudioFile = new File([blob], `audio_${Date.now()}.webm`, { type: mime });
            updateAttachmentPreview();
        } else {
            recordedAudioFile = null;
            updateAttachmentPreview();
        }
        recordedAudioChunks = [];
    }, { once: true });

    recorder.stop();
}

function onlyDigits(value) {
    return String(value ?? '').replace(/\D+/g, '');
}

function setScheduleNotice(message, tone = 'amber') {
    const notice = document.getElementById('scheduleClientNotice');
    notice.textContent = message || '';
    notice.className = `text-sm mt-3 ${tone === 'green' ? 'text-emerald-300' : 'text-amber-300'}`;
}

function openScheduleOverlay() {
    const item = getActive();
    if (!item) return;
    if (!canInteract(item)) {
        alert(isAssignedToOther(item) ? 'Esta conversa ja foi assumida por outro atendente.' : 'Assuma a conversa antes de agendar.');
        return;
    }

    const form = document.getElementById('scheduleForm');
    form.reset();
    document.getElementById('scheduleClienteId').value = '';
    document.getElementById('scheduleNome').value = item.nome && item.nome !== 'Cliente WhatsApp' ? item.nome : '';
    document.getElementById('scheduleTelefone').value = item.numero || '';
    document.getElementById('scheduleDescricao').value = item.interesse || '';
    document.getElementById('schedulePomadas').value = '0';
    document.getElementById('scheduleArtist').value = defaultTattooArtistId;
    updateScheduleTotalPreview();
    document.getElementById('scheduleClientSearch').value = item.numero || item.nome || '';
    document.getElementById('scheduleClientResults').classList.add('hidden');
    document.getElementById('scheduleClientResults').innerHTML = '';
    document.getElementById('scheduleResult').classList.add('hidden');
    document.getElementById('scheduleResult').innerHTML = '';
    setScheduleNotice('Vou procurar esse telefone na ficha para evitar cadastro duplicado.');

    document.getElementById('scheduleOverlay').classList.remove('hidden');
    searchScheduleClients(item.numero || item.nome || '', true);
    setTimeout(() => document.getElementById('scheduleData').focus(), 80);
}

function updateScheduleTotalPreview() {
    const valor = Number(document.getElementById('scheduleValor').value || 0);
    const pomadas = Number(document.getElementById('schedulePomadas').value || 0);
    const total = valor + (pomadas * Number(pomadaUnitPrice || 0));
    document.getElementById('scheduleTotalPreview').textContent = formatCurrency(total);
}

function closeScheduleOverlay() {
    document.getElementById('scheduleOverlay').classList.add('hidden');
}

function scheduleSearchChanged() {
    clearTimeout(scheduleSearchTimer);
    const term = document.getElementById('scheduleClientSearch').value.trim();
    scheduleSearchTimer = setTimeout(() => searchScheduleClients(term, false), 250);
}

async function searchScheduleClients(term, autoSelectByPhone = false) {
    const results = document.getElementById('scheduleClientResults');
    results.innerHTML = '';
    results.classList.add('hidden');

    if (!term || term.length < 2) {
        setScheduleNotice('Digite pelo menos 2 caracteres para buscar na ficha.');
        return;
    }

    try {
        const response = await fetch(`agendamento_clientes.php?q=${encodeURIComponent(term)}`);
        const data = await response.json();
        const clientes = Array.isArray(data.clientes) ? data.clientes : [];

        if (!clientes.length) {
            document.getElementById('scheduleClienteId').value = '';
            setScheduleNotice('Cliente nao encontrado. Ao salvar, o sistema cria um cadastro basico com nome e telefone.');
            return;
        }

        const activeDigits = onlyDigits(document.getElementById('scheduleTelefone').value || getActive()?.numero || '');
        const exact = clientes.find(c => activeDigits && onlyDigits(c.telefone) === activeDigits);
        if (autoSelectByPhone && exact) {
            selectScheduleClient(exact);
            setScheduleNotice('Cliente ja encontrado na ficha. Vou usar esse cadastro para o agendamento.', 'green');
            return;
        }

        results.innerHTML = clientes.map((cliente, index) => `
            <button type="button" data-index="${index}" class="schedule-client-option w-full text-left crm-card px-4 py-3">
                <strong>${escapeHtml(cliente.nome || 'Cliente')}</strong>
                <span class="block text-sm text-gray-400">${escapeHtml(cliente.telefone || '')}${cliente.email ? ' · ' + escapeHtml(cliente.email) : ''}</span>
            </button>
        `).join('');
        results.querySelectorAll('.schedule-client-option').forEach(button => {
            button.addEventListener('click', () => selectScheduleClient(clientes[Number(button.dataset.index)]));
        });
        results.classList.remove('hidden');
        setScheduleNotice('Selecione o cliente correto para evitar duplicidade.');
    } catch (error) {
        setScheduleNotice('Nao consegui buscar clientes agora. Ainda da para criar cadastro basico ao salvar.');
    }
}

function selectScheduleClient(cliente) {
    document.getElementById('scheduleClienteId').value = cliente.id || '';
    document.getElementById('scheduleNome').value = cliente.nome || '';
    document.getElementById('scheduleTelefone').value = cliente.telefone || '';
    document.getElementById('scheduleClientSearch').value = `${cliente.nome || ''} ${cliente.telefone || ''}`.trim();
    document.getElementById('scheduleClientResults').classList.add('hidden');
    document.getElementById('scheduleClientResults').innerHTML = '';
    setScheduleNotice('Cliente existente selecionado. O agendamento ficara vinculado a essa ficha.', 'green');
}

async function saveSchedule(event) {
    event.preventDefault();
    const item = getActive();
    if (!item || !canInteract(item)) {
        alert('Assuma a conversa antes de salvar agendamento.');
        return;
    }

    const form = document.getElementById('scheduleForm');
    if (!form.reportValidity()) return;

    const submit = document.getElementById('scheduleSubmit');
    const resultBox = document.getElementById('scheduleResult');
    const formData = new FormData(form);

    submit.disabled = true;
    submit.textContent = 'Salvando...';

    try {
        const response = await fetch('agendamento_salvar.php', { method: 'POST', body: formData });
        const result = await response.json().catch(() => ({}));

        if (!response.ok || !result.ok) {
            alert(result.error || 'Nao foi possivel salvar o agendamento.');
            return;
        }

        const fichaUrl = new URL(result.ficha_url, window.location.href).href;
        const agendaUrl = result.agenda_url ? new URL(result.agenda_url, window.location.href).href : '';
        resultBox.classList.remove('hidden');
        resultBox.innerHTML = `
            <strong>${escapeHtml(result.message || 'Agendamento salvo.')}</strong>
            ${agendaUrl ? `<div class="mt-3"><a href="${escapeHtml(agendaUrl)}" target="_blank" class="crm-button crm-button-primary"><i class="fas fa-calendar-days"></i> Abrir na agenda</a></div>` : ''}
            <div class="mt-3 text-gray-200">Link para o cliente completar a ficha:</div>
            <div class="mt-2 flex flex-col md:flex-row gap-2">
                <input id="scheduleFichaLink" readonly value="${escapeHtml(fichaUrl)}" class="crm-input flex-1">
                <button type="button" onclick="copyScheduleFichaLink()" class="crm-button">Copiar link</button>
            </div>
        `;
    } catch (error) {
        alert('Erro de conexao ao salvar agendamento.');
    } finally {
        submit.disabled = false;
        submit.textContent = 'Salvar agendamento';
    }
}

function copyScheduleFichaLink() {
    const input = document.getElementById('scheduleFichaLink');
    input.select();
    document.execCommand('copy');
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
document.querySelectorAll('[data-workspace-link], [data-workspace-trigger]').forEach(link => {
    link.addEventListener('click', event => {
        event.preventDefault();
        showEmbeddedWorkspace({
            title: link.dataset.title,
            subtitle: link.dataset.subtitle,
            src: link.dataset.src || link.getAttribute('href')
        });
    });
});
document.querySelectorAll('[data-home-drilldown]').forEach(button => {
    button.addEventListener('click', () => openHomeDrilldown(button.dataset.homeDrilldown || 'conversas'));
});
document.getElementById('homeDrilldownOverlay').addEventListener('click', event => {
    if (event.target === event.currentTarget) closeHomeDrilldown();
});
document.getElementById('closeHomeDrilldown').addEventListener('click', closeHomeDrilldown);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        closeHomeDrilldown();
    }
});
document.getElementById('backToAttendanceButton').addEventListener('click', showAttendanceWorkspace);
document.getElementById('emojiButton').addEventListener('click', () => document.getElementById('emojiPanel').classList.toggle('hidden'));
document.querySelectorAll('#emojiPanel button').forEach(button => button.addEventListener('click', () => insertEmoji(button.dataset.emoji || '')));
document.getElementById('fileButton').addEventListener('click', () => document.getElementById('chatFile').click());
document.getElementById('chatFile').addEventListener('change', updateAttachmentPreview);
document.getElementById('recordAudioBtn').addEventListener('click', toggleAudioRecording);
document.getElementById('scheduleButton').addEventListener('click', openScheduleOverlay);
document.getElementById('closeScheduleButton').addEventListener('click', closeScheduleOverlay);
document.getElementById('cancelScheduleButton').addEventListener('click', closeScheduleOverlay);
document.getElementById('scheduleForm').addEventListener('submit', saveSchedule);
document.getElementById('scheduleValor').addEventListener('input', updateScheduleTotalPreview);
document.getElementById('schedulePomadas').addEventListener('input', updateScheduleTotalPreview);
document.getElementById('scheduleClientSearch').addEventListener('input', scheduleSearchChanged);
document.getElementById('scheduleTelefone').addEventListener('blur', () => {
    const telefone = document.getElementById('scheduleTelefone').value.trim();
    if (telefone) searchScheduleClients(telefone, true);
});
document.getElementById('messageInput').addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        document.getElementById('messageForm').requestSubmit();
    }
});

document.getElementById('assumeButton').addEventListener('click', () => {
    const item = getActive();
    if (!item) return;
    postAttendanceAction({ action: 'assumir', id: item.id })
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Erro ao assumir conversa');
            updateActiveFromPayload(data.cliente);
            markConversationRead(data.cliente || item);
        })
        .catch(error => alert(error.message));
});

document.getElementById('humanModeButton').addEventListener('click', () => {
    const item = getActive();
    if (!item) return;
    if (isAssignedToOther(item)) return;
    postAttendanceAction({ action: 'assumir', id: item.id })
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Erro ao alternar atendimento');
            updateActiveFromPayload(data.cliente);
            markConversationRead(data.cliente || item);
        })
        .catch(error => alert(error.message));
});

document.getElementById('botModeButton').addEventListener('click', () => {
    const item = getActive();
    if (!item) return;
    if (!canInteract(item)) return;
    postAttendanceAction({ action: 'bot', id: item.id })
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Erro ao alternar atendimento');
            updateActiveFromPayload(data.cliente);
        })
        .catch(error => alert(error.message));
});

document.getElementById('statusSelect').addEventListener('change', event => {
    const item = getActive();
    if (!item) return;
    if (!canInteract(item)) {
        renderActiveConversation();
        return;
    }
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
    const fileInput = document.getElementById('chatFile');
    const text = input.value.trim();
    const file = recordedAudioFile || fileInput.files?.[0];
    if (!item || (!text && !file)) return;
    if (!canInteract(item)) {
        alert(isAssignedToOther(item) ? 'Esta conversa ja foi assumida por outro atendente.' : 'Assuma a conversa antes de responder.');
        return;
    }

    const formData = new FormData();
    formData.append('numero', item.numero);
    formData.append('mensagem', text);
    if (file) {
        formData.append('arquivo', file);
        if (recordedAudioFile) {
            formData.append('ptt', '1');
        }
    }

    input.disabled = true;
    fetch('enviar.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.erro || 'Erro ao enviar mensagem');
            input.value = '';
            removeAttachment();
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
        .catch(error => alert(error.message))
        .finally(() => {
            input.disabled = false;
            input.focus();
        });
});

async function initializeAttendanceChat() {
    await loadReadState();
    renderConversationList();
    renderReplies();
    renderActiveConversation();
    const item = getActive();
    if (item && unreadCount(item) > 0) markConversationRead(item);
}

initializeAttendanceChat();

chatPollTimer = setInterval(() => {
    const item = getActive();
    if (!item) return;
    fetch(`api_chat.php?id=${encodeURIComponent(item.id)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.ok) return;
            item.mensagens = data.mensagens;
            renderActiveConversation();
            if (unreadCount(item) > 0) markConversationRead(item);
        })
        .catch(() => {});
}, 3000);
</script>
</body>
</html>
