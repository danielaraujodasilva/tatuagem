<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require_once __DIR__ . '/data_store.php';
require_once __DIR__ . '/../includes/app_menu.php';

date_default_timezone_set('America/Sao_Paulo');

function automacao_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function automacao_redirect(bool $embedded = false): void
{
    $query = $embedded ? '?embed=1&v=20260505-automation' : '?v=20260505-automation';
    header('Location: automacao.php' . $query);
    exit;
}

$embeddedRequest = !empty($_GET['embed']) || !empty($_POST['embed']);

$eventos = [
    'mensagem_recebida' => 'Mensagem recebida',
    'sem_resposta' => 'Sem resposta',
    'orcamento_sem_retorno' => 'Orcamento sem retorno',
    'sessao_concluida' => 'Sessao concluida',
    'dias_apos_sessao' => 'Dias apos sessao',
    'antes_da_sessao' => 'Antes da sessao',
];

$acoes = [
    'criar_lead' => 'Criar lead',
    'alerta' => 'Gerar alerta',
    'enviar_mensagem' => 'Enviar mensagem',
    'mudar_status' => 'Mudar status',
];

$statuses = [
    '' => 'Sem mudanca',
    'novo' => 'Novo',
    'lead_quente' => 'Lead quente',
    'agendado' => 'Agendado',
    'sem_retorno' => 'Sem retorno',
    'em_atendimento' => 'Em atendimento',
    'fechado' => 'Fechado',
    'perdido' => 'Perdido',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $automacoes = crmCarregarAutomacoes();

    if ($action === 'salvar') {
        $id = trim((string)($_POST['id'] ?? ''));
        $payload = [
            'id' => $id !== '' ? $id : uniqid('auto_', true),
            'titulo' => trim((string)($_POST['titulo'] ?? '')),
            'evento' => trim((string)($_POST['evento'] ?? 'mensagem_recebida')),
            'acao' => trim((string)($_POST['acao'] ?? 'alerta')),
            'palavras_chave' => trim((string)($_POST['palavras_chave'] ?? '')),
            'atraso_horas' => (int)($_POST['atraso_horas'] ?? 0),
            'status_destino' => trim((string)($_POST['status_destino'] ?? '')),
            'mensagem' => trim((string)($_POST['mensagem'] ?? '')),
            'ativo' => !empty($_POST['ativo']),
        ];

        $achou = false;
        foreach ($automacoes as &$automacao) {
            if ((string)($automacao['id'] ?? '') === $payload['id']) {
                $automacao = $payload;
                $achou = true;
                break;
            }
        }
        unset($automacao);

        if (!$achou) {
            $automacoes[] = $payload;
        }

        crmSalvarAutomacoes($automacoes);
        automacao_redirect($embeddedRequest);
    }

    if ($action === 'toggle') {
        $id = trim((string)($_POST['id'] ?? ''));
        foreach ($automacoes as &$automacao) {
            if ((string)($automacao['id'] ?? '') === $id) {
                $automacao['ativo'] = empty($automacao['ativo']);
                break;
            }
        }
        unset($automacao);

        crmSalvarAutomacoes($automacoes);
        automacao_redirect($embeddedRequest);
    }

    if ($action === 'excluir') {
        $id = trim((string)($_POST['id'] ?? ''));
        $automacoes = array_values(array_filter($automacoes, static function (array $automacao) use ($id): bool {
            return (string)($automacao['id'] ?? '') !== $id;
        }));

        crmSalvarAutomacoes($automacoes);
        automacao_redirect($embeddedRequest);
    }
}

$automacoes = crmCarregarAutomacoes();
$clientes = crmCarregarClientes();
$embedded = $embeddedRequest;
$ativas = count(array_filter($automacoes, static fn(array $a): bool => !empty($a['ativo'])));
$envioAutomatico = count(array_filter($automacoes, static fn(array $a): bool => !empty($a['ativo']) && ($a['acao'] ?? '') === 'enviar_mensagem'));
$criacaoLead = count(array_filter($automacoes, static fn(array $a): bool => !empty($a['ativo']) && ($a['acao'] ?? '') === 'criar_lead'));

$alertas = [];
$agora = time();
foreach ($clientes as $cliente) {
    $mensagens = $cliente['mensagens'] ?? [];
    $ultima = end($mensagens);
    if (!$ultima || !empty($ultima['fromMe'])) {
        continue;
    }

    $data = strtotime((string)($ultima['data'] ?? ''));
    if (!$data) {
        continue;
    }

    $horas = (int)floor(($agora - $data) / 3600);
    if ($horas >= 24) {
        $alertas[] = [
            'nome' => $cliente['nome'] ?? 'Cliente',
            'numero' => $cliente['numero'] ?? '',
            'horas' => $horas,
            'status' => $cliente['status'] ?? 'novo',
        ];
    }
}

$porEvento = [];
foreach ($automacoes as $automacao) {
    $evento = (string)($automacao['evento'] ?? 'mensagem_recebida');
    $porEvento[$evento] = ($porEvento[$evento] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automacao CRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/crm-theme.css?v=20260505-automation">
    <style>
        .automation-layout {
            display: grid;
            grid-template-columns: minmax(320px, 440px) minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        .automation-rule {
            padding: 16px;
        }

        .automation-message {
            white-space: pre-wrap;
        }

        .automation-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 28px;
            padding: 0 10px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.045);
            color: #d7d9df;
            font-size: 0.78rem;
            font-weight: 850;
        }

        .automation-flow {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .automation-flow-card {
            min-height: 104px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.035);
            padding: 14px;
        }

        @media (max-width: 1200px) {
            .automation-layout,
            .automation-flow {
                grid-template-columns: 1fr;
            }
        }

        .automation-embedded {
            padding: 0;
        }

        .automation-embedded > .crm-header,
        .automation-embedded .crm-sidebar {
            display: none;
        }

        .automation-embedded .crm-workspace {
            display: block;
            min-height: auto;
            border: 0;
            background: transparent;
        }

        .automation-embedded .crm-main {
            padding: 0;
        }
    </style>
</head>
<body class="crm-shell">
<div class="crm-page <?= $embedded ? 'automation-embedded' : '' ?>">
    <header class="crm-header">
        <div>
            <div class="crm-eyebrow"><i class="fa-solid fa-robot"></i> Funcionario invisivel</div>
            <h1 class="crm-title">Tela de <strong>Automacao</strong></h1>
            <p class="crm-subtitle">Regras para criar leads, alertar conversas paradas, acionar follow-up e preparar mensagens de agenda e pos-atendimento.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="crm-button" href="index.php"><i class="fa-solid fa-comments"></i> Atendimento</a>
            <a class="crm-button" href="respostas_rapidas.php"><i class="fa-solid fa-bolt"></i> Respostas</a>
        </div>
    </header>

    <div class="crm-workspace">
        <aside class="crm-sidebar">
            <div class="crm-brand">
                <span class="crm-brand-name">Daniel <span>Tatuador</span></span>
                <small>Regras, alertas e mensagens automaticas</small>
            </div>
            <nav class="crm-nav" aria-label="Menu CRM">
                <a href="dashboard.php"><i class="fa-solid fa-chart-simple"></i> Dashboard</a>
                <a href="index.php"><i class="fa-solid fa-comments"></i> Atendimento</a>
                <a href="respostas_rapidas.php"><i class="fa-solid fa-bolt"></i> Respostas Rapidas</a>
                <a class="is-active" href="automacao.php"><i class="fa-solid fa-robot"></i> Automacao</a>
                <a href="../ficha/agenda/"><i class="fa-regular fa-calendar"></i> Agenda</a>
                <a href="../ficha/index.php"><i class="fa-regular fa-clipboard"></i> Ficha / Anamnese</a>
                <a href="relatorios.php"><i class="fa-solid fa-chart-line"></i> Relatorios</a>
                <a href="configuracoes.php"><i class="fa-solid fa-gear"></i> Configuracoes</a>
            </nav>
        </aside>

        <main class="crm-main">
            <section class="crm-grid-4 mb-4">
                <div class="crm-card p-4">
                    <div class="crm-muted text-sm">Regras</div>
                    <div class="text-3xl font-black mt-1"><?= count($automacoes) ?></div>
                </div>
                <div class="crm-card p-4">
                    <div class="crm-muted text-sm">Ativas</div>
                    <div class="text-3xl font-black mt-1 text-green-400"><?= $ativas ?></div>
                </div>
                <div class="crm-card p-4">
                    <div class="crm-muted text-sm">Criacao de lead</div>
                    <div class="text-3xl font-black mt-1 text-red-400"><?= $criacaoLead ?></div>
                </div>
                <div class="crm-card p-4">
                    <div class="crm-muted text-sm">Alertas 24h+</div>
                    <div class="text-3xl font-black mt-1 text-amber-300"><?= count($alertas) ?></div>
                </div>
            </section>

            <section class="crm-panel mb-4 p-4">
                <div class="crm-panel-header px-0 pt-0">
                    <h2 class="crm-panel-title"><i class="fa-solid fa-diagram-project"></i> Fluxo esperado</h2>
                </div>
                <div class="automation-flow">
                    <div class="automation-flow-card">
                        <div class="crm-muted text-sm">Entrada</div>
                        <div class="font-black mt-2">Cliente chama no WhatsApp</div>
                    </div>
                    <div class="automation-flow-card">
                        <div class="crm-muted text-sm">Regra</div>
                        <div class="font-black mt-2">Palavra-chave, tempo ou agenda disparam acao</div>
                    </div>
                    <div class="automation-flow-card">
                        <div class="crm-muted text-sm">Saida</div>
                        <div class="font-black mt-2">Lead, alerta, status ou mensagem preparada</div>
                    </div>
                </div>
            </section>

            <section class="automation-layout">
                <form method="post" class="crm-panel p-5" id="automationForm">
                    <input type="hidden" name="action" value="salvar">
                    <input type="hidden" name="id" id="ruleId">
                    <?php if ($embedded): ?>
                        <input type="hidden" name="embed" value="1">
                    <?php endif; ?>

                    <div class="flex items-center justify-between gap-3 mb-5">
                        <h2 class="crm-panel-title"><i class="fa-solid fa-sliders"></i> Regra</h2>
                        <button class="crm-button" type="button" onclick="resetAutomationForm()"><i class="fa-solid fa-plus"></i> Nova</button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="crm-muted text-sm block mb-2" for="titulo">Titulo</label>
                            <input class="crm-input" id="titulo" name="titulo" required placeholder="Ex: Lembrete 24h antes da sessao">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="crm-muted text-sm block mb-2" for="evento">Gatilho</label>
                                <select class="crm-input" id="evento" name="evento">
                                    <?php foreach ($eventos as $key => $label): ?>
                                        <option value="<?= automacao_h($key) ?>"><?= automacao_h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="crm-muted text-sm block mb-2" for="acao">Acao</label>
                                <select class="crm-input" id="acao" name="acao">
                                    <?php foreach ($acoes as $key => $label): ?>
                                        <option value="<?= automacao_h($key) ?>"><?= automacao_h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="crm-muted text-sm block mb-2" for="palavras_chave">Palavras-chave</label>
                            <input class="crm-input" id="palavras_chave" name="palavras_chave" placeholder="orcamento, valor, tattoo">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="crm-muted text-sm block mb-2" for="atraso_horas">Atraso em horas</label>
                                <input class="crm-input" id="atraso_horas" name="atraso_horas" type="number" min="0" value="0">
                            </div>
                            <div>
                                <label class="crm-muted text-sm block mb-2" for="status_destino">Status destino</label>
                                <select class="crm-input" id="status_destino" name="status_destino">
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?= automacao_h($key) ?>"><?= automacao_h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="crm-muted text-sm block mb-2" for="mensagem">Mensagem / observacao</label>
                            <textarea class="crm-textarea" id="mensagem" name="mensagem" rows="6" placeholder="Texto que sera usado pela automacao ou instrucao interna..."></textarea>
                        </div>

                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" id="ativo" name="ativo" value="1" checked class="accent-red-600">
                            <span>Regra ativa</span>
                        </label>

                        <button class="crm-button crm-button-primary w-full" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar automacao
                        </button>
                    </div>
                </form>

                <div class="space-y-4">
                    <section class="crm-panel">
                        <div class="crm-panel-header">
                            <h2 class="crm-panel-title"><i class="fa-solid fa-list-check"></i> Regras configuradas</h2>
                            <div class="crm-muted text-sm"><?= $envioAutomatico ?> com envio de mensagem</div>
                        </div>
                        <div class="p-4 grid grid-cols-1 xl:grid-cols-2 gap-3">
                            <?php foreach ($automacoes as $automacao): ?>
                                <article class="crm-card automation-rule">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="font-black text-lg"><?= automacao_h($automacao['titulo'] ?? '') ?></h3>
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                <span class="automation-pill"><?= automacao_h($eventos[$automacao['evento'] ?? ''] ?? ($automacao['evento'] ?? '')) ?></span>
                                                <span class="automation-pill"><?= automacao_h($acoes[$automacao['acao'] ?? ''] ?? ($automacao['acao'] ?? '')) ?></span>
                                                <?php if ((int)($automacao['atraso_horas'] ?? 0) > 0): ?>
                                                    <span class="automation-pill"><?= (int)$automacao['atraso_horas'] ?>h</span>
                                                <?php endif; ?>
                                                <span class="crm-status <?= !empty($automacao['ativo']) ? 'crm-status-green' : 'crm-status-amber' ?>">
                                                    <?= !empty($automacao['ativo']) ? 'Ativa' : 'Inativa' ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button class="crm-button" type="button"
                                                    onclick='editAutomation(<?= json_encode($automacao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'
                                                    title="Editar">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <form method="post">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= automacao_h($automacao['id'] ?? '') ?>">
                                                <?php if ($embedded): ?>
                                                    <input type="hidden" name="embed" value="1">
                                                <?php endif; ?>
                                                <button class="crm-button" type="submit" title="Ativar ou pausar">
                                                    <i class="fa-solid <?= !empty($automacao['ativo']) ? 'fa-pause' : 'fa-play' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Excluir esta automacao?')">
                                                <input type="hidden" name="action" value="excluir">
                                                <input type="hidden" name="id" value="<?= automacao_h($automacao['id'] ?? '') ?>">
                                                <?php if ($embedded): ?>
                                                    <input type="hidden" name="embed" value="1">
                                                <?php endif; ?>
                                                <button class="crm-button" type="submit" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>

                                    <?php if (!empty($automacao['palavras_chave'])): ?>
                                        <div class="crm-muted text-sm mt-4">Palavras: <?= automacao_h($automacao['palavras_chave']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($automacao['mensagem'])): ?>
                                        <p class="automation-message crm-muted mt-3 text-sm"><?= automacao_h($automacao['mensagem']) ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="crm-panel">
                        <div class="crm-panel-header">
                            <h2 class="crm-panel-title"><i class="fa-solid fa-triangle-exclamation"></i> Alertas atuais</h2>
                            <div class="crm-muted text-sm">Conversas com ultima mensagem do cliente ha 24h+</div>
                        </div>
                        <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php if (!$alertas): ?>
                                <div class="crm-card p-4 crm-muted">Nenhuma conversa critica agora.</div>
                            <?php endif; ?>
                            <?php foreach (array_slice($alertas, 0, 8) as $alerta): ?>
                                <div class="crm-card p-4">
                                    <div class="font-black"><?= automacao_h($alerta['nome']) ?></div>
                                    <div class="crm-muted text-sm mt-1"><?= automacao_h($alerta['numero']) ?></div>
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        <span class="crm-status crm-status-hot"><?= (int)$alerta['horas'] ?>h sem resposta</span>
                                        <span class="crm-status"><?= automacao_h($alerta['status']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
function editAutomation(rule) {
    document.getElementById('ruleId').value = rule.id || '';
    document.getElementById('titulo').value = rule.titulo || '';
    document.getElementById('evento').value = rule.evento || 'mensagem_recebida';
    document.getElementById('acao').value = rule.acao || 'alerta';
    document.getElementById('palavras_chave').value = rule.palavras_chave || '';
    document.getElementById('atraso_horas').value = rule.atraso_horas || 0;
    document.getElementById('status_destino').value = rule.status_destino || '';
    document.getElementById('mensagem').value = rule.mensagem || '';
    document.getElementById('ativo').checked = Boolean(rule.ativo);
    document.getElementById('titulo').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetAutomationForm() {
    document.getElementById('automationForm').reset();
    document.getElementById('ruleId').value = '';
    document.getElementById('ativo').checked = true;
    document.getElementById('titulo').focus();
}
</script>
</body>
</html>
