<?php
$pdo = v2_try(static fn() => v2_crm(), null);
$ficha = v2_try(static fn() => v2_ficha(), null);

$temChat = $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_clientes');
$temMsgs = $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_mensagens');

$atual = v2_periodo_atual();
$periodo = $temMsgs ? v2_periodo_intervalo() : null;
$ini = $periodo[0] ?? null;
$fim = $periodo[1] ?? null;
$iniD = $ini !== null ? substr($ini, 0, 10) : null;
$fimD = $fim !== null ? substr($fim, 0, 10) : null;

/* ---------- conversas ---------- */
$aguardando = [];
if ($temChat) {
    $sql = "SELECT c.id, c.nome, c.numero, c.status, c.valor, c.data_ultimo_contato,
                   (SELECT COUNT(*) FROM crm_whatsapp_mensagens m WHERE m.cliente_id = c.id AND m.from_me = 0) AS recebidas,
                   (SELECT m.tipo FROM crm_whatsapp_mensagens m WHERE m.cliente_id = c.id ORDER BY m.data DESC, m.id DESC LIMIT 1) AS ultimo_tipo,
                   (SELECT m.texto FROM crm_whatsapp_mensagens m WHERE m.cliente_id = c.id ORDER BY m.data DESC, m.id DESC LIMIT 1) AS ultimo_texto,
                   (SELECT m.from_me FROM crm_whatsapp_mensagens m WHERE m.cliente_id = c.id ORDER BY m.data DESC, m.id DESC LIMIT 1) AS ultimo_de
            FROM crm_whatsapp_clientes c
            WHERE c.status IN ('novo','lead_quente','em_atendimento','sem_retorno')";
    $par = [];
    if ($periodo !== null) {
        $sql .= ' AND c.data_ultimo_contato >= ? AND c.data_ultimo_contato <= ?';
        array_push($par, $periodo[0], $periodo[1]);
    }
    $sql .= ' ORDER BY c.data_ultimo_contato DESC LIMIT 8';
    $aguardando = v2_try(static function () use ($pdo, $sql, $par) {
        $st = $pdo->prepare($sql);
        $st->execute($par);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

$totalAguardando = $temChat ? (int)v2_q1($pdo, "SELECT COUNT(*) total FROM crm_whatsapp_clientes WHERE status IN ('novo','lead_quente','em_atendimento','sem_retorno')") : 0;
$totalConversas = $temChat ? (int)v2_q1($pdo, 'SELECT COUNT(*) total FROM crm_whatsapp_clientes') : 0;

/* ---------- agenda do estudio ---------- */
$sessoes = [];
$valorPeriodo = 0.0;
$aReceber = 0.0;
$proxima = null;
if ($ficha instanceof mysqli) {
    if ($iniD !== null) {
        $st = $ficha->prepare("SELECT t.id, t.descricao, t.hora_inicio, t.valor, t.status, t.data_tatuagem, c.nome
                               FROM tatuagens t LEFT JOIN clientes c ON c.id = t.cliente_id
                               WHERE t.status <> 'cancelado' AND t.data_tatuagem BETWEEN ? AND ?
                               ORDER BY t.data_tatuagem DESC, t.hora_inicio LIMIT 200");
        $st->bind_param('ss', $iniD, $fimD);
        $st->execute();
        $r = $st->get_result();
    } else {
        $r = $ficha->query("SELECT t.id, t.descricao, t.hora_inicio, t.valor, t.status, t.data_tatuagem, c.nome
                            FROM tatuagens t LEFT JOIN clientes c ON c.id = t.cliente_id
                            WHERE t.status <> 'cancelado'
                            ORDER BY t.data_tatuagem DESC, t.hora_inicio LIMIT 200");
    }
    while ($row = $r->fetch_assoc()) {
        $sessoes[] = $row;
        $valorPeriodo += (float)$row['valor'];
    }

    $aReceber = (float)($ficha->query("SELECT COALESCE(SUM(valor),0) v FROM tatuagens WHERE data_tatuagem >= CURDATE() AND status IN ('agendado','confirmado')")->fetch_assoc()['v'] ?? 0);
    $prox = $ficha->query("SELECT t.data_tatuagem, t.hora_inicio, c.nome FROM tatuagens t LEFT JOIN clientes c ON c.id = t.cliente_id
                           WHERE t.data_tatuagem >= CURDATE() AND t.status IN ('agendado','confirmado')
                           ORDER BY t.data_tatuagem, t.hora_inicio LIMIT 1")->fetch_assoc();
    if ($prox) {
        $proxima = $prox;
    }
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
if (v2_instalado()) {
    if ($periodo !== null) {
        $tarefas = v2_q($pdo, 'SELECT * FROM v2_tarefas WHERE status IN (\'pendente\',\'aprovada\')
                              AND previsto_para >= ' . $pdo->quote($periodo[0]) . '
                              AND previsto_para <= ' . $pdo->quote($periodo[1]) . ' ORDER BY previsto_para LIMIT 6');
    } else {
        $tarefas = v2_q($pdo, "SELECT * FROM v2_tarefas WHERE status IN ('pendente','aprovada')
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
