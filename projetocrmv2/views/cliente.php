<?php
$pdo = v2_try(static fn() => v2_crm(), null);
$ficha = v2_try(static fn() => v2_ficha(), null);

$id = (string)($_GET['id'] ?? '');
$ver = (string)($_GET['ver'] ?? 'owner');
$abrir = (int)($_GET['abrir'] ?? 1);
$staff = $ver === 'staff';

$cliente = null;
if ($id !== '' && $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_clientes')) {
    $stmt = $pdo->prepare('SELECT * FROM crm_whatsapp_clientes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$cliente && $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_clientes')) {
    $cliente = v2_q($pdo, 'SELECT * FROM crm_whatsapp_clientes ORDER BY updated_at DESC LIMIT 1')[0] ?? null;
}

$mensagens = 0;
$ultima = '—';
if ($cliente && $pdo instanceof PDO) {
    $mensagens = (int)v2_q1($pdo, 'SELECT COUNT(*) total FROM crm_whatsapp_mensagens WHERE cliente_id = ' . $pdo->quote((string)$cliente['id']));
    $ultima = (string)v2_q($pdo, 'SELECT MAX(data) d FROM crm_whatsapp_mensagens WHERE cliente_id = ' . $pdo->quote((string)$cliente['id']))[0]['d'] ?? '—';
}

$anamnese = null;
$tatuagens = [];
if ($ficha instanceof mysqli && $cliente) {
    $norm = v2_norm((string)($cliente['numero'] ?? ''));
    $like = '%' . substr($norm, -8) . '%';
    $st = $ficha->prepare('SELECT * FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone," ",""),"-",""),"(",""),")","") LIKE ? LIMIT 1');
    $st->bind_param('s', $like);
    $st->execute();
    $anamnese = $st->get_result()->fetch_assoc() ?: null;
    if ($anamnese) {
        $st2 = $ficha->prepare('SELECT descricao, valor, data_tatuagem, status FROM tatuagens WHERE cliente_id = ? ORDER BY data_tatuagem DESC LIMIT 10');
        $st2->bind_param('i', $anamnese['id']);
        $st2->execute();
        $r = $st2->get_result();
        while ($row = $r->fetch_assoc()) { $tatuagens[] = $row; }
    }
}

$nome = (string)($cliente['nome'] ?? 'Nenhum cliente encontrado');
$iniciais = strtoupper(substr(preg_replace('/[^A-Za-zÀ-ÿ ]/', '', $nome) ?: 'C', 0, 2));

function v2_layer_aberta(int $tier, int $abrir): string { return $tier === $abrir ? ' open' : ''; }

v2_layout_top('cliente', 'Cliente');
?>
<div class="head">
  <div class="kick">o cliente em camadas</div>
  <h2>Um cliente, do básico ao avançado</h2>
  <p>Quem só atende vê o essencial. Quem quer cavar abre o resto. Sem tela entupida.</p>
</div>

<div class="card pad">
  <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px">
    <div class="switch">
      <a class="<?= $staff ? '' : 'on' ?>" href="?page=cliente&amp;id=<?= urlencode($id) ?>&amp;ver=owner&amp;abrir=<?= $abrir ?>">👨‍🎨 Daniel</a>
      <a class="<?= $staff ? 'on' : '' ?>" href="?page=cliente&amp;id=<?= urlencode($id) ?>&amp;ver=staff&amp;abrir=<?= $abrir ?>">💼 Secretária</a>
    </div>
    <span class="chip"><?= $staff ? 'secretária vê o essencial (camadas 1 e 2)' : 'todas as camadas liberadas' ?></span>
  </div>

  <div class="clienthead">
    <div class="avi"><?= v2_h($iniciais) ?></div>
    <div><h3><?= v2_h($nome) ?></h3>
      <div class="ph"><?= v2_h($cliente['numero'] ?? '—') ?> · status <?= v2_h($cliente['status'] ?? '—') ?> · ficha <?= $anamnese ? 'vinculada (#' . (int)$anamnese['id'] . ')' : 'não vinculada' ?></div></div>
  </div>

  <div class="layer<?= v2_layer_aberta(1, $abrir) ?>" data-t="1">
    <div class="lhead"><span class="n">camada 1</span><span class="nm">O essencial</span><span class="wh">todo mundo vê</span></div>
    <div class="lbody"><div class="kv">
      <div class="c"><div class="k">Status</div><div class="v"><?= v2_h($cliente['status'] ?? '—') ?></div></div>
      <div class="c"><div class="k">Último contato</div><div class="v"><?= v2_h($cliente['data_ultimo_contato'] ?? '—') ?></div></div>
      <div class="c"><div class="k">Mensagens</div><div class="v"><?= $mensagens ?></div></div>
      <div class="c"><div class="k">Valor</div><div class="v"><?= v2_money($cliente['valor'] ?? 0) ?></div></div>
    </div></div>
  </div>

  <div class="layer<?= v2_layer_aberta(2, $abrir) ?>" data-t="2">
    <div class="lhead"><span class="n">camada 2</span><span class="nm">Atendimento</span><span class="wh">quem responde</span></div>
    <div class="lbody"><div class="kv">
      <div class="c"><div class="k">Última mensagem</div><div class="v"><?= v2_h($ultima) ?></div></div>
      <div class="c"><div class="k">Modo</div><div class="v"><?= v2_h($cliente['modo_atendimento'] ?? '—') ?></div></div>
      <div class="c"><div class="k">Atendente</div><div class="v"><?= v2_h($cliente['atendente'] ?: '—') ?></div></div>
      <div class="c"><div class="k">Interesse</div><div class="v"><?= v2_h($cliente['interesse'] ?: '—') ?></div></div>
    </div>
    <div style="margin-top:12px"><a class="badge b-red" style="text-decoration:none" href="index.php?page=conversa&amp;id=<?= urlencode((string)($cliente['id'] ?? '')) ?>">abrir conversa</a></div>
    </div>
  </div>

  <div class="layer<?= v2_layer_aberta(3, $abrir) ?><?= $staff ? ' lock' : '' ?>" data-t="3">
    <div class="lhead"><span class="n">camada 3</span><span class="nm">Estúdio &amp; técnico</span><span class="wh">tatuador</span></div>
    <div class="lbody"><div class="kv">
      <div class="c"><div class="k">Alergias</div><div class="v"><?= v2_h($anamnese['alergias'] ?? '—') ?></div></div>
      <div class="c"><div class="k">Doenças</div><div class="v"><?= v2_h($anamnese['tem_doencas'] ?? '—') ?></div></div>
      <div class="c"><div class="k">Medicamentos</div><div class="v"><?= v2_h($anamnese['uso_medicamentos'] ?? '—') ?></div></div>
      <div class="c"><div class="k">Sessões</div><div class="v"><?= count($tatuagens) ?></div></div>
    </div>
    <?php if ($tatuagens): ?><div style="margin-top:12px"><?php foreach ($tatuagens as $t): ?>
      <div class="row"><span class="badge b-gray"><?= v2_h($t['data_tatuagem']) ?></span><div class="who"><small><?= v2_h($t['descricao']) ?> · <?= v2_money($t['valor']) ?> · <?= v2_h($t['status']) ?></small></div></div>
    <?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="lockn">🔒 Camada técnica — liberada para quem tatua.</div>
  </div>

  <div class="layer<?= v2_layer_aberta(4, $abrir) ?><?= $staff ? ' lock' : '' ?>" data-t="4">
    <div class="lhead"><span class="n">camada 4</span><span class="nm">Negócio &amp; avançado</span><span class="wh">dono</span></div>
    <div class="lbody"><div class="kv">
      <div class="c"><div class="k">Origem</div><div class="v"><?= v2_h($cliente['origem'] ?? '—') ?></div></div>
      <div class="c"><div class="k">Ticket</div><div class="v"><?= v2_money($cliente['valor'] ?? 0) ?></div></div>
      <div class="c"><div class="k">Etapa</div><div class="v"><?= v2_h($cliente['etapa'] ?: '—') ?></div></div>
      <div class="c"><div class="k">Criado em</div><div class="v"><?= v2_h($cliente['created_at'] ?? '—') ?></div></div>
    </div></div>
    <div class="lockn">🔒 Camada de negócio — liberada para o dono.</div>
  </div>
</div>
<?php v2_layout_bottom(); ?>
