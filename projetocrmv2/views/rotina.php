<?php
$pdo = v2_try(static fn() => v2_crm(), null);

$regras = [];
if (v2_instalado()) {
    $regras = v2_q($pdo, 'SELECT id, titulo, evento, acao, atraso_horas, status_destino, mensagem, ativo FROM v2_regras ORDER BY atraso_horas, titulo');
}
$json = __DIR__ . '/../../crm/data/automation_rules.json';
if (is_file($json)) {
    $dados = json_decode((string)file_get_contents($json), true);
    if (is_array($dados)) { $regras = $dados; }
}
if (!$regras) {
    $regras = [
        ['titulo' => 'Boas-vindas ao 1º contato', 'evento' => 'novo_contato', 'atraso_horas' => 0, 'ativo' => true],
        ['titulo' => 'Follow-up se ficou sem resposta', 'evento' => 'sem_resposta', 'atraso_horas' => 24, 'ativo' => true],
        ['titulo' => 'Lembrete 24h antes da sessão', 'evento' => 'antes_da_sessao', 'atraso_horas' => 24, 'ativo' => true],
        ['titulo' => 'Cuidados pós-tattoo', 'evento' => 'sessao_concluida', 'atraso_horas' => 0, 'ativo' => true],
        ['titulo' => 'Acompanhar cicatrização (D+7)', 'evento' => 'dias_apos_sessao', 'atraso_horas' => 168, 'ativo' => true],
        ['titulo' => 'Pedir avaliação (D+30)', 'evento' => 'dias_apos_sessao', 'atraso_horas' => 720, 'ativo' => true],
        ['titulo' => 'Oferecer nova arte (D+90)', 'evento' => 'dias_apos_sessao', 'atraso_horas' => 2160, 'ativo' => true],
        ['titulo' => 'Reativar cliente frio (6 meses)', 'evento' => 'sem_resposta', 'atraso_horas' => 4320, 'ativo' => true],
    ];
}

$periodo = v2_periodo_intervalo();
$tarefas = [];
if (v2_instalado()) {
    if ($periodo !== null) {
        $tarefas = v2_q($pdo, 'SELECT * FROM v2_tarefas WHERE previsto_para >= ' . $pdo->quote($periodo[0])
            . ' AND previsto_para <= ' . $pdo->quote($periodo[1]) . ' ORDER BY previsto_para LIMIT 20');
    } else {
        $tarefas = v2_q($pdo, 'SELECT * FROM v2_tarefas ORDER BY previsto_para LIMIT 20');
    }
}

v2_layout_top('rotina', 'Rotina');
?>
<div class="head">
  <div class="kick">o motor de rotina</div>
  <h2>As regras viram tarefas com data</h2>
  <p>O sistema lembra por você: follow-up, pós-tattoo, cicatrização, avaliação, nova arte e reativação.</p>
</div>

<?php v2_filtro_periodo(); ?>

<div class="grid g2" style="align-items:start">
  <div class="card pad">
    <p class="sect">🔁 Regras do estúdio (<?= count($regras) ?>)</p>
    <?php foreach ($regras as $r): ?>
      <div class="row">
        <span class="badge <?= !empty($r['ativo']) ? 'b-green' : 'b-gray' ?>"><?= !empty($r['ativo']) ? 'ativa' : 'off' ?></span>
        <div class="who"><b><?= v2_h($r['titulo'] ?? '—') ?></b><small><?= v2_h($r['evento'] ?? '—') ?> · +<?= (int)($r['atraso_horas'] ?? 0) ?>h</small></div>
      </div>
    <?php endforeach; ?>
    <div class="note n-amber" style="margin-top:12px">Estas regras já existem na tela de Automação do CRM atual — aqui elas ganham <b>fila e data</b> para disparar de verdade.</div>
  </div>

  <div class="card pad">
    <p class="sect">📋 Fila de tarefas <span class="tag" style="margin-left:auto"><?= v2_h(v2_periodo_label()) ?></span></p>
    <?php if (!$tarefas): ?>
      <div class="note n-gray">Nenhuma tarefa na fila ainda. <?= v2_instalado() ? 'O motor gera as tarefas a partir dos eventos (agendamento, sessão concluída).' : 'Rode <code>database/schema_v2.sql</code> para criar a tabela de tarefas.' ?></div>
    <?php else: foreach ($tarefas as $t): ?>
      <div class="task"><span class="s" style="background:var(--blue)"></span>
        <div style="flex:1"><b><?= v2_h($t['titulo']) ?></b><div style="color:var(--muted);font-size:.76rem"><?= date('d/m H:i', strtotime((string)$t['previsto_para'])) ?> · <?= v2_h($t['status']) ?><?= !empty($t['responder_com_audio']) ? ' · responder em áudio' : '' ?></div></div>
        <span class="badge b-gray"><?= v2_h($t['canal']) ?></span></div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php v2_layout_bottom(); ?>
