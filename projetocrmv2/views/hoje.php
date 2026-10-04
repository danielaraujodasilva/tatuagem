<?php
$pdo = v2_try(static fn() => v2_crm(), null);
$ficha = v2_try(static fn() => v2_ficha(), null);

$temChat = $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_clientes');

$conversas = $temChat ? v2_q($pdo, "
    SELECT c.id, c.nome, c.numero, c.status, c.etapa, c.valor, c.data_ultimo_contato,
           (SELECT COUNT(*) FROM crm_whatsapp_mensagens m WHERE m.cliente_id = c.id AND m.from_me = 0) AS recebidas,
           (SELECT MAX(m2.data) FROM crm_whatsapp_mensagens m2 WHERE m2.cliente_id = c.id AND m2.from_me = 0) AS ultima_cliente
    FROM crm_whatsapp_clientes c
    ORDER BY c.data_ultimo_contato DESC, c.updated_at DESC
    LIMIT 8
") : [];

$aguardando = array_values(array_filter($conversas, static function (array $c): bool {
    return in_array((string)($c['status'] ?? ''), ['novo', 'lead_quente', 'em_atendimento', ''], true);
}));

$sessoes = [];
$aReceber = 0.0;
$faturadoMes = 0.0;
if ($ficha instanceof mysqli) {
    $r = $ficha->query("SELECT t.id, t.descricao, t.hora_inicio, t.valor, t.status, c.nome
                        FROM tatuagens t LEFT JOIN clientes c ON c.id = t.cliente_id
                        WHERE t.data_tatuagem = CURDATE() AND t.status <> 'cancelado'
                        ORDER BY t.hora_inicio");
    while ($row = $r->fetch_assoc()) { $sessoes[] = $row; }
    $aReceber = (float)($ficha->query("SELECT COALESCE(SUM(valor),0) v FROM tatuagens WHERE data_tatuagem >= CURDATE() AND status = 'agendado'")->fetch_assoc()['v'] ?? 0);
    $faturadoMes = (float)($ficha->query("SELECT COALESCE(SUM(valor),0) v FROM tatuagens WHERE status = 'concluido' AND DATE_FORMAT(data_tatuagem,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc()['v'] ?? 0);
}

$tarefas = [];
if (v2_instalado()) {
    $tarefas = v2_q($pdo, "SELECT * FROM v2_tarefas WHERE status IN ('pendente','aprovada') AND previsto_para <= DATE_ADD(NOW(), INTERVAL 2 DAY) ORDER BY previsto_para LIMIT 6");
}

v2_layout_top('hoje', 'Hoje');
?>
<div class="head">
  <div class="kick">o começo do dia</div>
  <h2>Bom dia, <?= v2_h(explode(' ', (string)($user['nome'] ?? 'Daniel'))[0]) ?> 👋</h2>
  <p>O que precisa de você hoje, numa tela só.</p>
</div>

<div class="grid g4" style="margin-bottom:14px">
  <div class="card kpi"><div class="k">Conversas aguardando</div><div class="v t-red"><?= count($aguardando) ?></div><div class="h t-red">de <?= count($conversas) ?> recentes</div></div>
  <div class="card kpi"><div class="k">Sessões hoje</div><div class="v t-blue"><?= count($sessoes) ?></div><div class="h t-blue"><?= $sessoes ? 'a partir das ' . v2_h(substr((string)$sessoes[0]['hora_inicio'], 0, 5)) : 'dia livre' ?></div></div>
  <div class="card kpi"><div class="k">A receber (agendado)</div><div class="v t-amber"><?= v2_money($aReceber) ?></div><div class="h t-amber">sessões futuras</div></div>
  <div class="card kpi"><div class="k">Faturado no mês</div><div class="v t-green"><?= v2_money($faturadoMes) ?></div><div class="h t-green">sessões concluídas</div></div>
</div>

<div class="grid g23">
  <div class="card pad">
    <p class="sect">💬 Precisa da sua resposta</p>
    <?php if (!$conversas): ?>
      <div class="note n-gray">Nenhuma conversa encontrada<?= $temChat ? '.' : ' (tabela de conversas indisponível neste ambiente).' ?></div>
    <?php else: foreach ($aguardando ?: $conversas as $c): ?>
      <div class="row">
        <span class="dotline dl-<?= ((int)($c['recebidas'] ?? 0) > 0) ? 'red' : 'blue' ?>"></span>
        <div class="who">
          <b><?= v2_h($c['nome'] ?: 'Cliente') ?></b>
          <small><?= v2_h($c['numero']) ?> · <?= (int)($c['recebidas'] ?? 0) ?> mensagens recebidas · últ. <?= v2_h($c['ultima_cliente'] ?: ($c['data_ultimo_contato'] ?: '—')) ?></small>
        </div>
        <a class="badge b-red" style="text-decoration:none" href="index.php?page=conversa&id=<?= urlencode((string)$c['id']) ?>">abrir</a>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="grid" style="align-content:start">
    <div class="card pad">
      <p class="sect">🗓 Agenda de hoje</p>
      <?php if (!$sessoes): ?>
        <div class="note n-gray">Nenhuma sessão agendada para hoje.</div>
      <?php else: foreach ($sessoes as $s): ?>
        <div class="row"><span class="time"><?= v2_h(substr((string)$s['hora_inicio'], 0, 5)) ?></span>
          <div class="who"><b><?= v2_h($s['nome'] ?: 'Cliente') ?></b><small><?= v2_h($s['descricao']) ?> · <?= v2_money($s['valor']) ?> · <?= v2_h($s['status']) ?></small></div></div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card pad">
      <p class="sect">🤖 Rotina</p>
      <?php if (!$tarefas): ?>
        <div class="note n-gray">Nenhuma tarefa vencendo. <?= v2_instalado() ? '' : 'Rode o schema v2 para ativar o motor.' ?></div>
      <?php else: foreach ($tarefas as $t): $atrasada = strtotime((string)$t['previsto_para']) <= time(); ?>
        <div class="row"><span class="badge <?= $atrasada ? 'b-red' : 'b-gray' ?>"><?= date('d/m H:i', strtotime((string)$t['previsto_para'])) ?></span>
          <div class="who"><small><?= v2_h($t['titulo']) ?></small></div></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php v2_layout_bottom(); ?>
