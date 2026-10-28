<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/auth.php';
require_once __DIR__ . '/dados.php';

$usuario = require_staff();
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$mesSolicitado = trim((string)($_GET['mes'] ?? ''));
$periodo = relatorio_periodo($mesSolicitado !== '' ? $mesSolicitado : date('Y-m'));
$mes = (string)$periodo['month'];

$erro = null;
$relatorio = null;
$anterior = null;
try {
    $relatorio = relatorio_carregar($mes);
    $anterior = relatorio_carregar((string)$periodo['prev_month']);
} catch (Throwable $e) {
    error_log('Relatorios: ' . $e->getMessage());
    $erro = 'Não foi possível carregar os dados agora. Tente novamente em instantes.';
}

$intervaloMin = date('Y-m');
try {
    $intervaloMin = (string)relatorio_scalar(
        relatorio_db(),
        "SELECT DATE_FORMAT(MIN(last_message_at), '%Y-%m') FROM whatsapp_conversations WHERE merged_into_id IS NULL",
        [],
        date('Y-m')
    );
} catch (Throwable $e) {
    error_log('Relatorios: intervalo indisponivel: ' . $e->getMessage());
}
$intervaloMax = date('Y-m');

function ui_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ui_num(int|float|null $value, int $decimals = 0): string
{
    return number_format((float)$value, $decimals, ',', '.');
}

function ui_money(int|float|null $value): string
{
    return 'R$ ' . number_format((float)$value, 0, ',', '.');
}

function ui_duration(?int $seconds): string
{
    if ($seconds === null) {
        return '—';
    }
    if ($seconds < 60) {
        return $seconds . 's';
    }
    if ($seconds < 3600) {
        return max(1, (int)round($seconds / 60)) . ' min';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = (int)round(($seconds % 3600) / 60);
    return $minutes > 0 ? $hours . 'h ' . $minutes . 'min' : $hours . 'h';
}

function ui_delta(array $delta, string $suffix = '%', bool $invert = false): string
{
    if ($delta['value'] === null) {
        return '<span class="delta delta-flat">sem base</span>';
    }
    $direction = (string)$delta['direction'];
    $good = $invert ? $direction === 'down' : $direction === 'up';
    $class = $direction === 'flat' ? 'delta-flat' : ($good ? 'delta-good' : 'delta-bad');
    $icon = $direction === 'up' ? 'fa-arrow-trend-up' : ($direction === 'down' ? 'fa-arrow-trend-down' : 'fa-minus');
    $prefix = (float)$delta['value'] > 0 ? '+' : '';
    return '<span class="delta ' . $class . '"><i class="fa-solid ' . $icon . '"></i> ' . $prefix . ui_num((float)$delta['value'], 1) . $suffix . '</span>';
}

$renderError = $erro !== null || $relatorio === null;
$o = $relatorio['overview'] ?? [];
$anteriorO = $anterior['overview'] ?? [];
$agendaDate = $relatorio['agenda']['date'] ?? [];
$agendaCreated = $relatorio['agenda']['created'] ?? [];

$dContatos = relatorio_delta($o['contacts'] ?? 0, $anteriorO['contacts'] ?? 0);
$dMensagens = relatorio_delta($o['messages'] ?? 0, $anteriorO['messages'] ?? 0);
$dLeads = relatorio_delta($o['with_lead'] ?? 0, $anteriorO['with_lead'] ?? 0);
$dConversao = relatorio_delta($o['positive_conv_pct'] ?? 0, $anteriorO['positive_conv_pct'] ?? 0, true);
$dAgenda = relatorio_delta($agendaCreated['retained'] ?? 0, $anterior['agenda']['created']['retained'] ?? 0);
$dResposta = relatorio_delta($o['first_response_median'] ?? 0, $anteriorO['first_response_median'] ?? 0);
$dFila = relatorio_delta($o['waiting_leads'] ?? 0, $anteriorO['waiting_leads'] ?? 0);
$dParados = relatorio_delta($o['stale'] ?? 0, $anteriorO['stale'] ?? 0);

$startLabel = (new DateTimeImmutable($periodo['start_date']))->format('d/m');
$endLabel = $periodo['is_current'] ? date('d/m') : (new DateTimeImmutable($periodo['end_date']))->modify('-1 day')->format('d/m');
$usuariosNome = (string)($usuario['nome'] ?? 'Equipe');
$baseUrl = auth_base_url();
$logoutUrl = auth_url('/auth/logout.php');
$crmUrl = auth_url('/crm/index.php');
$reportUrl = $baseUrl . '/relatorios/';
$reportJson = json_encode($relatorio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#08080b">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= ui_h($periodo['label']) ?> · Relatório do estúdio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= ui_h($baseUrl) ?>/relatorios/assets/relatorios.css?v=20261028-6">
</head>
<body>
<div class="ambient ambient-one" aria-hidden="true"></div>
<div class="ambient ambient-two" aria-hidden="true"></div>
<div class="grain" aria-hidden="true"></div>

<header class="topbar">
    <a class="brand" href="<?= ui_h($crmUrl) ?>" aria-label="Voltar ao CRM">
        <span class="brand-mark">DA</span>
        <span class="brand-copy"><strong>Daniel Araujo</strong><small>Relatório do estúdio</small></span>
    </a>
    <div class="top-actions">
        <span class="user-chip"><i class="fa-regular fa-circle-user"></i><span><?= ui_h($usuariosNome) ?></span></span>
        <a class="icon-btn" href="<?= ui_h($crmUrl) ?>" title="Voltar ao CRM"><i class="fa-solid fa-arrow-left"></i><span class="sr-only">Voltar ao CRM</span></a>
        <a class="icon-btn" href="<?= ui_h($logoutUrl) ?>" title="Sair"><i class="fa-solid fa-arrow-right-from-bracket"></i><span class="sr-only">Sair</span></a>
    </div>
</header>

<main class="shell">
    <section class="hero reveal">
        <div class="hero-copy">
            <span class="eyebrow"><span class="pulse-dot"></span>Visão de <?= ui_h($periodo['label']) ?></span>
            <h1>O mês em <span>uma olhada.</span></h1>
            <p>Conversas, conversão, agenda e os sinais que mais influenciam as vendas do estúdio.</p>
        </div>
        <form class="period-form" method="get" action="<?= ui_h($reportUrl) ?>">
            <label for="mes">Período</label>
            <div class="period-control">
                <input id="mes" name="mes" type="month" value="<?= ui_h($mes) ?>" min="<?= ui_h($intervaloMin) ?>" max="<?= ui_h($intervaloMax) ?>">
                <button type="submit"><i class="fa-solid fa-rotate"></i><span>Atualizar</span></button>
            </div>
            <div class="period-meta">
                <span><i class="fa-regular fa-calendar"></i> <?= ui_h($startLabel) ?> — <?= ui_h($endLabel) ?></span>
                <a href="<?= ui_h($baseUrl) ?>/relatorios/">Mês atual</a>
            </div>
        </form>
    </section>

<?php if ($renderError): ?>
    <section class="error-card reveal">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong>Dados indisponíveis</strong><p><?= ui_h($erro ?? 'Não foi possível ler o relatório.') ?></p></div>
    </section>
<?php else: ?>
    <div id="report-data" class="report-data" aria-hidden="true"><?= $reportJson ?></div>

    <section class="signal-grid reveal" aria-label="Sinais do mês">
<?php foreach (array_slice($relatorio['insights'] ?? [], 0, 4) as $insight): ?>
        <article class="signal signal-<?= ui_h($insight['tone']) ?>">
            <span class="signal-icon"><i class="fa-solid <?= ui_h($insight['icon']) ?>"></i></span>
            <div><strong><?= ui_h($insight['title']) ?></strong><p><?= ui_h($insight['text']) ?></p></div>
        </article>
<?php endforeach; ?>
    </section>

    <section class="section-block reveal" aria-labelledby="kpi-title">
        <div class="section-head">
            <div><span class="section-kicker">Resumo executivo</span><h2 id="kpi-title">Os números que importam</h2></div>
            <span class="live-note"><span class="pulse-dot"></span>Leitura somente · <?= ui_h($relatorio['meta']['generated_label']) ?></span>
        </div>
        <div class="kpi-grid">
            <article class="kpi-card kpi-primary">
                <span class="kpi-icon"><i class="fa-solid fa-comments"></i></span>
                <div class="kpi-label">Contatos atendidos</div>
                <div class="kpi-value" data-countup="<?= (int)$o['contacts'] ?>"><?= ui_num($o['contacts']) ?></div>
                <?= ui_delta($dContatos) ?> <span class="kpi-context">vs. mês anterior</span>
                <p><?= ui_num($o['new_contacts']) ?> novos · <?= ui_num($o['returning_contacts']) ?> retornos</p>
            </article>
            <article class="kpi-card">
                <span class="kpi-icon"><i class="fa-solid fa-user-tag"></i></span>
                <div class="kpi-label">Leads qualificados</div>
                <div class="kpi-value" data-countup="<?= (int)$o['with_lead'] ?>"><?= ui_num($o['with_lead']) ?></div>
                <?= ui_delta($dLeads) ?> <span class="kpi-context">vs. mês anterior</span>
                <p><?= ui_num($o['lead_coverage_pct'], 1) ?>% dos contatos têm lead</p>
            </article>
            <article class="kpi-card kpi-accent">
                <span class="kpi-icon"><i class="fa-solid fa-arrow-trend-up"></i></span>
                <div class="kpi-label">Conversão em agenda</div>
                <div class="kpi-value"><span data-countup="<?= (float)$o['positive_conv_pct'] ?>" data-decimals="1"><?= ui_num($o['positive_conv_pct'], 1) ?></span><small>%</small></div>
                <?= ui_delta($dConversao, ' p.p.', false) ?> <span class="kpi-context">vs. mês anterior</span>
                <p><?= ui_num($o['positive_conv']) ?> conversas com agenda positiva</p>
            </article>
            <article class="kpi-card">
                <span class="kpi-icon"><i class="fa-regular fa-calendar-check"></i></span>
                <div class="kpi-label">Agenda criada</div>
                <div class="kpi-value" data-countup="<?= (int)$agendaCreated['total'] ?>"><?= ui_num($agendaCreated['total']) ?></div>
                <?= ui_delta($dAgenda) ?> <span class="kpi-context">vs. mês anterior</span>
                <p><?= ui_num($agendaCreated['retained']) ?> válidos · <?= ui_num($agendaCreated['retained_pct'], 1) ?>% de retenção</p>
            </article>
            <article class="kpi-card">
                <span class="kpi-icon"><i class="fa-solid fa-message"></i></span>
                <div class="kpi-label">Mensagens</div>
                <div class="kpi-value" data-countup="<?= (int)$o['messages'] ?>"><?= ui_num($o['messages']) ?></div>
                <?= ui_delta($dMensagens) ?> <span class="kpi-context">vs. mês anterior</span>
                <p><?= ui_num($o['inbound']) ?> recebidas · <?= ui_num($o['outbound']) ?> enviadas</p>
            </article>
            <article class="kpi-card">
                <span class="kpi-icon"><i class="fa-solid fa-stopwatch"></i></span>
                <div class="kpi-label">1ª resposta · leads</div>
                <div class="kpi-value kpi-time" data-duration="<?= (int)($o['first_response_median'] ?? 0) ?>"><?= ui_h(ui_duration($o['first_response_median'])) ?></div>
                <?= ui_delta($dResposta, '', true) ?> <span class="kpi-context">vs. mês anterior</span>
                <p><?= ui_num($o['response_over_1h_pct'], 1) ?>% passam de 1h</p>
            </article>
            <article class="kpi-card kpi-warning">
                <span class="kpi-icon"><i class="fa-solid fa-headset"></i></span>
                <div class="kpi-label">Aguardando estúdio</div>
                <div class="kpi-value" data-countup="<?= (int)$o['waiting_leads'] ?>"><?= ui_num($o['waiting_leads']) ?></div>
                <?= ui_delta($dFila, '', true) ?> <span class="kpi-context">vs. mês anterior</span>
                <p><?= ui_num($o['waiting_studio']) ?> no total, incluindo sem lead</p>
            </article>
            <article class="kpi-card kpi-critical">
                <span class="kpi-icon"><i class="fa-solid fa-hourglass-half"></i></span>
                <div class="kpi-label">Leads parados 48h+</div>
                <div class="kpi-value" data-countup="<?= (int)$o['stale'] ?>"><?= ui_num($o['stale']) ?></div>
                <?= ui_delta($dParados, '', true) ?> <span class="kpi-context">vs. mês anterior</span>
                <p><?= ui_num($o['stale_last_studio']) ?> com a última mensagem do estúdio</p>
            </article>
        </div>
    </section>

    <section class="dashboard-grid reveal" aria-label="Gráficos do relatório">
        <article class="chart-card span-12 chart-activity">
            <div class="card-head">
                <div><span class="section-kicker">Ritmo do mês</span><h2>Movimento diário</h2><p>Mensagens recebidas, enviadas e novos contatos por dia.</p></div>
                <span class="chart-badge"><i class="fa-solid fa-wave-square"></i> WhatsApp</span>
            </div>
            <div class="chart-wrap chart-tall"><canvas id="activityChart" aria-label="Gráfico de atividade diária"></canvas></div>
        </article>

        <article class="chart-card span-5">
            <div class="card-head">
                <div><span class="section-kicker">Aquisição</span><h2>De onde vêm</h2><p>Origem informada no lead.</p></div>
            </div>
            <div class="chart-wrap chart-doughnut"><canvas id="sourceChart" aria-label="Gráfico de origem dos contatos"></canvas><div class="donut-center" id="sourceCenter"><strong><?= ui_num($o['contacts']) ?></strong><span>contatos</span></div></div>
            <div class="chart-foot"><i class="fa-solid fa-circle-info"></i> “Sem lead” também inclui contatos que ainda não entraram no funil.</div>
        </article>

        <article class="chart-card span-7">
            <div class="card-head">
                <div><span class="section-kicker">Canais</span><h2>Conversão por origem</h2><p>Conversas com pelo menos um agendamento confirmado ou concluído.</p></div>
            </div>
            <div class="chart-wrap chart-medium"><canvas id="channelChart" aria-label="Gráfico de conversão por canal"></canvas></div>
        </article>

        <article class="chart-card span-5">
            <div class="card-head">
                <div><span class="section-kicker">Agenda</span><h2>Sessões do mês</h2><p><?= ui_money($agendaDate['retained_value'] ?? 0) ?> em agenda válida.</p></div>
            </div>
            <div class="chart-wrap chart-doughnut"><canvas id="agendaChart" aria-label="Gráfico de status da agenda"></canvas><div class="donut-center" id="agendaCenter"><strong><?= ui_num($agendaDate['retained_pct'] ?? 0, 1) ?>%</strong><span>retidas</span></div></div>
            <div class="mini-stats">
                <div><span class="dot dot-teal"></span><strong><?= ui_num($agendaDate['retained'] ?? 0) ?></strong><small>válidas</small></div>
                <div><span class="dot dot-amber"></span><strong><?= ui_num($agendaDate['lost'] ?? 0) ?></strong><small>perdidas</small></div>
                <div><span class="dot dot-red"></span><strong><?= ui_money($agendaDate['lost_value'] ?? 0) ?></strong><small>em risco</small></div>
            </div>
        </article>

        <article class="chart-card span-7">
            <div class="card-head">
                <div><span class="section-kicker">Objeções e dúvidas</span><h2>O que trava a conversa</h2><p>Conversas qualificadas em que o tema apareceu. Uma conversa pode ter mais de um tema.</p></div>
            </div>
            <div class="chart-wrap chart-tall"><canvas id="objectionChart" aria-label="Gráfico de objeções e dúvidas"></canvas></div>
        </article>

        <article class="chart-card span-5">
            <div class="card-head">
                <div><span class="section-kicker">Velocidade</span><h2>Tempo de resposta</h2><p><?= ui_num($o['response_turns']) ?> respostas em conversas com lead.</p></div>
            </div>
            <div class="chart-wrap chart-medium"><canvas id="responseChart" aria-label="Gráfico de tempo de resposta"></canvas></div>
            <div class="chart-foot"><i class="fa-solid fa-bolt"></i> <?= ui_num($relatorio['response'][0]['pct'] ?? 0, 1) ?>% das respostas saem em até 5 minutos.</div>
        </article>

        <article class="chart-card span-7">
            <div class="card-head">
                <div><span class="section-kicker">CRM</span><h2>Pipeline registrado</h2><p>Como os contatos estão classificados hoje dentro do funil.</p></div>
            </div>
            <div class="chart-wrap chart-medium"><canvas id="pipelineChart" aria-label="Gráfico de etapas do pipeline"></canvas></div>
        </article>

        <article class="chart-card span-5 data-health">
            <div class="card-head">
                <div><span class="section-kicker">Confiabilidade</span><h2>Saúde dos dados</h2><p>O que dá para medir com segurança.</p></div>
            </div>
            <div class="health-list">
                <div><span>Cobertura de lead</span><strong><?= ui_num($o['lead_coverage_pct'], 1) ?>%</strong><i><b style="width:<?= min(100, (float)$o['lead_coverage_pct']) ?>%"></b></i></div>
                <div><span>Contatos sem lead</span><strong><?= ui_num($o['without_lead']) ?></strong><i><b style="width:<?= min(100, 100 - (float)$o['lead_coverage_pct']) ?>%"></b></i></div>
                <div><span>Fechado no CRM</span><strong><?= ui_num($o['crm_closed']) ?></strong><i><b style="width:<?= min(100, (float)$o['crm_closed_pct']) ?>%"></b></i></div>
                <div><span>Agenda comprovada</span><strong><?= ui_num($o['positive_conv']) ?></strong><i><b style="width:<?= min(100, (float)$o['positive_conv_pct']) ?>%"></b></i></div>
            </div>
            <div class="health-note"><i class="fa-solid fa-scale-balanced"></i><p>A IA marcou <strong><?= ui_num($o['ai_closed']) ?></strong> vendas em <?= ui_num($o['ai_analyzed']) ?> conversas (<?= ui_num($o['ai_closed_pct'], 1) ?>%). Como só <strong><?= ui_num($o['positive_conv']) ?></strong> têm agenda positiva, o dashboard usa a agenda como fonte principal.</p></div>
        </article>
    </section>

    <section class="section-block reveal" aria-labelledby="channels-title">
        <div class="section-head">
            <div><span class="section-kicker">Leitura por canal</span><h2 id="channels-title">Onde o volume encontra a conversão</h2></div>
            <span class="table-hint"><i class="fa-solid fa-arrows-left-right"></i> deslize no mobile</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Origem</th><th>Conversas</th><th>Agenda positiva</th><th>Conversão</th><th>Peso</th></tr></thead>
                <tbody>
<?php foreach ($relatorio['channels'] as $channel): ?>
                    <tr>
                        <td><span class="channel-name"><span class="channel-dot channel-<?= ui_h($channel['key']) ?>"></span><?= ui_h($channel['label']) ?></span></td>
                        <td><?= ui_num($channel['conversations']) ?></td>
                        <td><?= ui_num($channel['positive']) ?></td>
                        <td><strong class="<?= (float)$channel['conversion_pct'] >= 8 ? 'text-good' : 'text-muted' ?>"><?= ui_num($channel['conversion_pct'], 1) ?>%</strong></td>
                        <td><span class="weight-bar"><i style="width:<?= min(100, (float)($channel['conversations'] / max(1, (int)$o['contacts']) * 100)) ?>%"></i></span></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-note">A origem “Google Agenda” representa contatos que já chegaram com compromisso no calendário; não é comparável às origens de aquisição.</p>
    </section>

    <section class="section-block reveal" aria-labelledby="priority-title">
        <div class="section-head">
            <div><span class="section-kicker">Plano de ação</span><h2 id="priority-title">O que atacar primeiro</h2></div>
            <span class="live-note">Prioridades geradas pelos dados do mês</span>
        </div>
        <div class="priority-grid">
<?php foreach ($relatorio['priorities'] as $priority): ?>
            <article class="priority-card">
                <span class="priority-number"><?= ui_h($priority['number']) ?></span>
                <h3><?= ui_h($priority['title']) ?></h3>
                <p><?= ui_h($priority['text']) ?></p>
                <span class="priority-metric"><?= ui_num($priority['metric']) ?> no radar</span>
            </article>
<?php endforeach; ?>
        </div>
        <div class="goal-banner">
            <div><span class="section-kicker">Meta prática</span><h3>Transformar velocidade em venda</h3><p>Responder 80% dos novos contatos em até 10 minutos e fazer três retornos após cada orçamento.</p></div>
            <div class="goal-steps"><span>D0</span><i></i><span>D+1</span><i></i><span>D+3</span></div>
        </div>
    </section>

    <section class="method-card reveal">
        <details>
            <summary><span><i class="fa-solid fa-circle-question"></i> Como os números foram calculados</span><i class="fa-solid fa-chevron-down"></i></summary>
            <div class="method-content">
                <p><strong>Contatos:</strong> conversas únicas com mensagem no período, sem duplicatas de LID.</p>
                <p><strong>Lead qualificado:</strong> conversa vinculada a um registro em <code>leads</code>. A conversão leva em conta também vínculos por telefone para reduzir falhas do CRM.</p>
                <p><strong>Agenda positiva:</strong> conversa com pelo menos um compromisso confirmado, concluído ou finalizado.</p>
                <p><strong>Objeções:</strong> leitura por palavras-chave nas mensagens recebidas; os temas podem se sobrepor. É um termômetro, não uma classificação perfeita.</p>
                <p><strong>Tempo de resposta:</strong> mede o intervalo entre a mensagem do cliente e a primeira resposta do estúdio, apenas em conversas vinculadas a um lead.</p>
                <p><strong>Parados 48h:</strong> leads cuja última mensagem tem mais de 48 horas, contado apenas no mês atual.</p>
                <p><strong>Privacidade:</strong> a tela é protegida por login de funcionário/administrador, não é indexada e não exibe nomes, telefones ou mensagens.</p>
            </div>
        </details>
    </section>

    <footer class="footer reveal">
        <div><span class="brand-mark small">DA</span><p><strong>Relatório do estúdio</strong><small>Daniel Araujo Tatuador</small></p></div>
        <div class="footer-links"><a href="<?= ui_h($crmUrl) ?>"><i class="fa-solid fa-table-columns"></i> CRM</a><a href="<?= ui_h($reportUrl) ?>"><i class="fa-solid fa-rotate"></i> Atualizar</a></div>
    </footer>
<?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script src="<?= ui_h($baseUrl) ?>/relatorios/assets/relatorios.js?v=20261028-6"></script>
</body>
</html>




