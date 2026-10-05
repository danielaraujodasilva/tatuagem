<?php
$studio = v2_try(static fn() => v2_studio(), null);
$meta = v2_try(static fn() => v2_crm(), null);

$temChat = $studio instanceof PDO && v2_tabela_existe($studio, 'whatsapp_conversations');
$temMsgs = $studio instanceof PDO && v2_tabela_existe($studio, 'whatsapp_messages');

$atual = v2_periodo_atual();
$periodo = $temMsgs ? v2_periodo_intervalo() : null;
$ini = $periodo[0] ?? null;
$fim = $periodo[1] ?? null;
$iniD = $ini !== null ? substr($ini, 0, 10) : null;
$fimD = $fim !== null ? substr($fim, 0, 10) : null;

/* ---------- conversas ---------- */
$aguardando = [];
$totalAguardando = 0;
$totalConversas = 0;
if ($temChat) {
    $todas = v2_studio_conversas($studio, ['limit' => 1000]);
    $totalConversas = count($todas);
    $totalAguardando = count(array_filter($todas, static fn(array $c): bool => in_array((string)$c['status'], ['novo', 'lead_quente', 'em_atendimento', 'sem_retorno'], true)));
    $aguardando = v2_studio_conversas($studio, [
        'periodo' => $periodo,
        'status_in' => ['novo', 'lead_quente', 'em_atendimento', 'sem_retorno'],
        'ordem' => 'recentes',
        'limit' => 8,
    ]);
}

/* ---------- agenda do estudio ---------- */
$sessoes = [];
$valorPeriodo = 0.0;
$aReceber = 0.0;
$proxima = null;
if ($studio instanceof PDO) {
    $sqlS = "SELECT a.id, COALESCE(NULLIF(a.title, ''), a.description) AS descricao,
                    a.start_time AS hora_inicio, a.value AS valor, a.status,
                    a.appointment_date AS data_tatuagem,
                    COALESCE(NULLIF(cu.name, ''), NULLIF(l.name, ''), 'Cliente') AS nome
             FROM appointments a
             LEFT JOIN customers cu ON cu.id = a.customer_id
             LEFT JOIN leads l ON l.id = a.lead_id
             WHERE a.status <> 'cancelado'";
    $parS = [];
    if ($iniD !== null) {
        $sqlS .= ' AND a.appointment_date BETWEEN ? AND ?';
        array_push($parS, $iniD, $fimD);
    }
    $sqlS .= ' ORDER BY a.appointment_date DESC, a.start_time LIMIT 200';
    $sessoes = v2_try(static function () use ($studio, $sqlS, $parS) {
        $st = $studio->prepare($sqlS);
        $st->execute($parS);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }, []);
    foreach ($sessoes as $s) {
        $valorPeriodo += (float)$s['valor'];
    }

    $aReceber = (float)v2_try(static function () use ($studio) {
        $st = $studio->query("SELECT COALESCE(SUM(value), 0) v FROM appointments
                              WHERE appointment_date >= CURDATE() AND status IN ('confirmado', 'pre_agendado')");
        return (float)$st->fetchColumn();
    }, 0.0);
    $proxima = v2_try(static function () use ($studio) {
        $st = $studio->query("SELECT a.appointment_date, a.start_time,
                                     COALESCE(NULLIF(cu.name, ''), NULLIF(l.name, ''), 'Cliente') AS nome
                              FROM appointments a
                              LEFT JOIN customers cu ON cu.id = a.customer_id
                              LEFT JOIN leads l ON l.id = a.lead_id
                              WHERE a.appointment_date >= CURDATE() AND a.status IN ('confirmado', 'pre_agendado')
                              ORDER BY a.appointment_date, a.start_time LIMIT 1");
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }, null);
}

$sessoesHoje = 0;
foreach ($sessoes as $s) {
    if ((string)$s['data_tatuagem'] === date('Y-m-d')) {
        $sessoesHoje++;
    }
}
$rotuloSessoes = $atual === 'hoje' ? 'Sessões de hoje' : 'Sessões no período';

/* ---------- rotina ---------- */
$tarefas = [];
if (v2_instalado() && $meta instanceof PDO) {
    if ($periodo !== null) {
        $tarefas = v2_q($meta, 'SELECT * FROM v2_tarefas WHERE status IN (\'pendente\',\'aprovada\')
                              AND previsto_para >= ' . $meta->quote($periodo[0]) . '
                              AND previsto_para <= ' . $meta->quote($periodo[1]) . ' ORDER BY previsto_para LIMIT 6');
    } else {
        $tarefas = v2_q($meta, "SELECT * FROM v2_tarefas WHERE status IN ('pendente','aprovada')
                               AND previsto_para <= DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY previsto_para LIMIT 6");
    }
}

v2_layout_top('hoje', 'Hoje');
?>
<div class="head">
  <div class="kick">o começo do dia</div>
  <h2>Bom dia, <?= v2_h(explode(' ', (string)($user['nome'] ?? 'Daniel'))[0]) ?> 👋</h2>
  <p>O que precisa de você, numa tela só. Período atual: <b><?= v2_h(v2_periodo_label()) ?></b> — troque abaixo.</p>
</div>

<?php v2_filtro_periodo(); ?>

<div class="grid g4" style="margin-bottom:14px">
  <div class="card kpi"><div class="k">Conversas aguardando</div><div class="v t-red"><?= $totalAguardando ?></div><div class="h t-red">de <?= $totalConversas ?> clientes no total</div></div>
  <div class="card kpi"><div class="k"><?= v2_h($rotuloSessoes) ?></div><div class="v t-blue"><?= count($sessoes) ?></div><div class="h t-blue"><?= $sessoesHoje > 0 ? $sessoesHoje . ' hoje' : ($proxima ? 'próxima ' . v2_h(date('d/m', (int)strtotime((string)$proxima['data_tatuagem']))) . ' ' . v2_h(substr((string)$proxima['hora_inicio'], 0, 5)) : 'agenda livre') ?></div></div>
  <div class="card kpi"><div class="k">Valor das sessões</div><div class="v t-amber"><?= v2_money($valorPeriodo) ?></div><div class="h t-amber">soma no período</div></div>
  <div class="card kpi"><div class="k">A receber (agendado)</div><div class="v t-green"><?= v2_money($aReceber) ?></div><div class="h t-green"><?= $aReceber > 0 ? 'sessões futuras' : 'nada agendado pra frente' ?></div></div>
</div>

<div class="grid g23">
  <div class="card pad">
    <p class="sect">💬 Precisa da sua resposta <span class="tag" style="margin-left:auto"><?= $totalAguardando ?> em aberto</span></p>
    <?php if (!$aguardando): ?>
      <div class="note n-gray">Nenhuma conversa em aberto<?= $atual === 'tudo' ? '' : ' nesse período' ?>. <a href="<?= v2_h(v2_url(['periodo' => 'tudo'])) ?>">ver tudo</a></div>
    <?php else: foreach ($aguardando as $c): $esperando = (int)($c['ultimo_de'] ?? 1) === 0; ?>
      <div class="row">
        <span class="dotline dl-<?= $esperando ? 'red' : 'blue' ?>"></span>
        <div class="who">
          <b><?= v2_h($c['nome'] ?: 'Cliente') ?></b>
          <small><?= v2_h(v2_msg_preview((string)($c['ultimo_tipo'] ?? 'texto'), (string)($c['ultimo_texto'] ?? ''), null)) ?: v2_h((string)$c['numero']) ?></small>
          <small><?= v2_h(v2_status_label((string)$c['status'])) ?> · <?= (int)($c['recebidas'] ?? 0) ?> recebidas · últ. <?= v2_h(v2_desde($c['data_ultimo_contato'] ?? null)) ?></small>
        </div>
        <a class="badge <?= $esperando ? 'b-red' : 'b-gray' ?>" style="text-decoration:none" href="index.php?page=conversa&amp;id=<?= urlencode((string)$c['id']) ?>"><?= $esperando ? 'responder' : 'abrir' ?></a>
      </div>
    <?php endforeach; endif; ?>
    <div style="margin-top:12px"><a class="badge b-blue" style="text-decoration:none" href="<?= v2_h(v2_url(['page' => 'conversa'])) ?>">💬 abrir o WhatsApp do estúdio</a></div>
  </div>

  <div class="grid" style="align-content:start">
    <div class="card pad">
      <p class="sect">🗓 <?= v2_h($atual === 'hoje' ? 'Agenda de hoje' : 'Sessões do período') ?> <span class="tag" style="margin-left:auto"><?= count($sessoes) ?></span></p>
      <?php if (!$sessoes): ?>
        <div class="note n-gray">Nenhuma sessão nesse período.</div>
      <?php else: foreach (array_slice($sessoes, 0, 6) as $s): ?>
        <div class="row"><span class="badge b-gray"><?= v2_h(date('d/m', (int)strtotime((string)$s['data_tatuagem']))) ?> <?= v2_h(substr((string)$s['hora_inicio'], 0, 5)) ?></span>
          <div class="who"><b><?= v2_h($s['nome'] ?: 'Cliente') ?></b><small><?= v2_h((string)$s['descricao']) ?> · <?= v2_money($s['valor']) ?> · <?= v2_h((string)$s['status']) ?></small></div></div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card pad">
      <p class="sect">🤖 Rotina</p>
      <?php if (!$tarefas): ?>
        <div class="note n-gray">Nenhuma tarefa nesse período. <?= v2_instalado() ? '' : 'Rode o schema v2 para ativar o motor.' ?></div>
      <?php else: foreach ($tarefas as $t): $atrasada = strtotime((string)$t['previsto_para']) <= time(); ?>
        <div class="row"><span class="badge <?= $atrasada ? 'b-red' : 'b-gray' ?>"><?= date('d/m H:i', strtotime((string)$t['previsto_para'])) ?></span>
          <div class="who"><small><?= v2_h((string)$t['titulo']) ?></small></div></div>
      <?php endforeach; endif; ?>
      <div style="margin-top:12px"><a class="badge b-violet" style="text-decoration:none" href="<?= v2_h(v2_url(['page' => 'rotina'])) ?>">ver o motor de rotina</a></div>
    </div>
  </div>
</div>
<?php v2_layout_bottom(); ?>
