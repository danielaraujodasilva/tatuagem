<?php
/**
 * Cliente: busca, lista e a ficha completa em camadas + historico.
 */
$pdo = v2_try(static fn() => v2_crm(), null);
$ficha = v2_try(static fn() => v2_ficha(), null);
$temChat = $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_clientes');
$temMsgs = $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_mensagens');

$id = (string)($_GET['id'] ?? '');
$ver = (string)($_GET['ver'] ?? 'owner');
$abrir = (int)($_GET['abrir'] ?? 1);
$staff = $ver === 'staff';

$busca = trim((string)($_GET['busca'] ?? ''));
$fstatus = (string)($_GET['status'] ?? '');
$ordem = (string)($_GET['ordem'] ?? 'recentes');
$periodo = $temMsgs ? v2_periodo_intervalo() : null;

/* ---------- lista ---------- */
$lista = [];
if ($temChat) {
    $sql = "SELECT c.id, c.nome, c.numero, c.status, c.valor, c.origem, c.interesse, c.data_ultimo_contato,
                   a.recebidas, a.enviadas, a.total, a.ultima_data
            FROM crm_whatsapp_clientes c
            LEFT JOIN (
                SELECT cliente_id, SUM(from_me = 0) AS recebidas, SUM(from_me = 1) AS enviadas,
                       COUNT(*) AS total, MAX(data) AS ultima_data
                FROM crm_whatsapp_mensagens GROUP BY cliente_id
            ) a ON a.cliente_id = c.id
            WHERE 1 = 1";
    $par = [];
    if ($busca !== '') {
        $sql .= ' AND (c.nome LIKE ? OR c.numero LIKE ? OR c.interesse LIKE ?)';
        $like = '%' . $busca . '%';
        array_push($par, $like, $like, $like);
    }
    if ($fstatus !== '' && array_key_exists($fstatus, v2_status_opcoes())) {
        $sql .= ' AND c.status = ?';
        $par[] = $fstatus;
    }
    if ($periodo !== null) {
        $sql .= ' AND a.ultima_data >= ? AND a.ultima_data <= ?';
        array_push($par, $periodo[0], $periodo[1]);
    }
    if ($ordem === 'nome') {
        $sql .= ' ORDER BY c.nome ASC';
    } elseif ($ordem === 'valor') {
        $sql .= ' ORDER BY c.valor DESC, a.ultima_data DESC';
    } elseif ($ordem === 'parados') {
        $sql .= ' ORDER BY (a.ultima_data IS NULL), a.ultima_data ASC';
    } else {
        $sql .= ' ORDER BY (a.ultima_data IS NULL), a.ultima_data DESC';
    }
    $sql .= ' LIMIT 300';

    $lista = v2_try(static function () use ($pdo, $sql, $par) {
        $st = $pdo->prepare($sql);
        $st->execute($par);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

/* ---------- cliente escolhido ---------- */
$cliente = null;
if ($id !== '' && $temChat) {
    $stmt = $pdo->prepare('SELECT * FROM crm_whatsapp_clientes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$cliente && $lista) {
    $id = (string)$lista[0]['id'];
    $stmt = $pdo->prepare('SELECT * FROM crm_whatsapp_clientes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: $lista[0];
}

/* ---------- numeros da conversa ---------- */
$contagem = ['recebidas' => 0, 'enviadas' => 0, 'primeira' => null, 'ultima' => null];
$msgsHist = [];
if ($cliente && $temMsgs) {
    $cid = (string)$cliente['id'];
    $sql = 'SELECT de, texto, data, from_me, tipo, transcricao FROM crm_whatsapp_mensagens WHERE cliente_id = ?';
    $par = [$cid];
    if ($periodo !== null) {
        $sql .= ' AND data >= ? AND data <= ?';
        array_push($par, $periodo[0], $periodo[1]);
    }
    $sql .= ' ORDER BY data DESC, id DESC LIMIT 80';
    $msgsHist = v2_try(static function () use ($pdo, $sql, $par) {
        $st = $pdo->prepare($sql);
        $st->execute($par);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }, []);

    $tot = v2_q($pdo, 'SELECT SUM(from_me = 0) recebidas, SUM(from_me = 1) enviadas,
                              MIN(data) primeira, MAX(data) ultima
                       FROM crm_whatsapp_mensagens WHERE cliente_id = ' . $pdo->quote($cid));
    if ($tot) {
        $contagem['recebidas'] = (int)($tot[0]['recebidas'] ?? 0);
        $contagem['enviadas'] = (int)($tot[0]['enviadas'] ?? 0);
        $contagem['primeira'] = $tot[0]['primeira'] ?? null;
        $contagem['ultima'] = $tot[0]['ultima'] ?? null;
    }
}

/* ---------- ficha do estudio (anamnese + sessoes) ---------- */
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
        $st2 = $ficha->prepare('SELECT descricao, observacoes, valor, data_tatuagem, hora_inicio, status FROM tatuagens WHERE cliente_id = ? ORDER BY data_tatuagem DESC LIMIT 20');
        $st2->bind_param('i', $anamnese['id']);
        $st2->execute();
        $r = $st2->get_result();
        while ($row = $r->fetch_assoc()) {
            $tatuagens[] = $row;
        }
    }
}

/* ---------- linha do tempo (conversa + sessoes) ---------- */
$linha = [];
foreach ($msgsHist as $m) {
    $linha[] = ['tipo' => 'msg', 'quando' => (string)$m['data'], 'm' => $m];
}
foreach ($tatuagens as $t) {
    $linha[] = ['tipo' => 'sessao', 'quando' => (string)($t['data_tatuagem'] ?? '') . ' 12:00:00', 't' => $t];
}
usort($linha, static fn(array $a, array $b): int => strcmp($b['quando'], $a['quando']));

$nome = (string)($cliente['nome'] ?? 'Nenhum cliente encontrado');
$filtrosAtivos = ($busca !== '' ? 1 : 0) + ($fstatus !== '' ? 1 : 0) + ($periodo !== null ? 1 : 0);

function v2_layer_aberta(int $tier, int $abrir): string { return $tier === $abrir ? ' open' : ''; }

v2_layout_top('cliente', 'Cliente');
?>
<div class="head">
  <div class="kick">o cliente em camadas</div>
  <h2>Do básico ao avançado, sem tela entupida</h2>
  <p>Quem só atende vê o essencial. Quem quer cavar abre o resto — inclusive o histórico inteiro.</p>
</div>

<?php v2_barra_busca(); ?>
<?php v2_filtro_periodo(); ?>

<div class="inbox">
  <aside class="card ilist">
    <div class="ilisthead">
      <form method="get" class="crow" style="gap:6px">
        <?php foreach ($_GET as $k => $v): if (in_array($k, ['status', 'ordem', 'abrir'], true) || !is_scalar($v)) { continue; } ?>
          <input type="hidden" name="<?= v2_h((string)$k) ?>" value="<?= v2_h((string)$v) ?>">
        <?php endforeach; ?>
        <select class="fsel" name="status" onchange="this.form.submit()">
          <option value="">todos os status</option>
          <?php foreach (v2_status_opcoes() as $k => $label): ?>
            <option value="<?= v2_h($k) ?>"<?= $k === $fstatus ? ' selected' : '' ?>><?= v2_h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="fsel" name="ordem" onchange="this.form.submit()">
          <?php foreach (['recentes' => 'mais recentes', 'parados' => 'mais parados', 'valor' => 'maior valor', 'nome' => 'ordem alfabética'] as $k => $label): ?>
            <option value="<?= v2_h($k) ?>"<?= $k === $ordem ? ' selected' : '' ?>><?= v2_h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="fgo" type="submit">filtrar</button>
      </form>
      <div class="crow">
        <span class="tag"><?= count($lista) ?> cliente<?= count($lista) === 1 ? '' : 's' ?></span>
        <?php if ($filtrosAtivos > 0): ?><a class="fchip" href="index.php?page=cliente">✕ limpar filtros</a><?php endif; ?>
      </div>
    </div>
    <div class="iconvos">
      <?php if (!$lista): ?>
        <div class="note n-gray" style="margin:13px">Nenhum cliente com esses filtros.</div>
      <?php else: foreach ($lista as $c): ?>
        <a class="convo<?= (string)$c['id'] === $id ? ' on' : '' ?>" href="<?= v2_h(v2_url(['id' => (string)$c['id']])) ?>">
          <span class="cavi"><?= v2_h(v2_iniciais((string)($c['nome'] ?: 'Cliente'))) ?></span>
          <span class="cwho">
            <b><?= v2_h($c['nome'] ?: 'Cliente') ?></b>
            <small><?= v2_h($c['numero']) ?> · <?= v2_h(v2_status_label((string)$c['status'])) ?></small>
            <small class="cmeta"><?= (int)($c['total'] ?? 0) ?> mensagens · últ. <?= v2_h(v2_desde($c['ultima_data'] ?? null)) ?></small>
          </span>
          <span class="cside"><span class="badge <?= v2_h(v2_status_classe((string)$c['status'])) ?>"><?= v2_h(v2_status_label((string)$c['status'])) ?></span></span>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </aside>

  <div class="grid" style="align-content:start">
  <?php if (!$cliente): ?>
    <div class="card pad"><div class="note n-amber">Nenhum cliente selecionado.</div></div>
  <?php else: ?>
    <div class="card pad">
      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div class="switch">
          <a class="<?= $staff ? '' : 'on' ?>" href="<?= v2_h(v2_url(['ver' => 'owner'])) ?>">👨‍🎨 Daniel</a>
          <a class="<?= $staff ? 'on' : '' ?>" href="<?= v2_h(v2_url(['ver' => 'staff'])) ?>">💼 Secretária</a>
        </div>
        <span class="chip"><?= $staff ? 'secretária vê o essencial (camadas 1 e 2)' : 'todas as camadas liberadas' ?></span>
      </div>

      <div class="clienthead">
        <div class="avi"><?= v2_h(v2_iniciais($nome)) ?></div>
        <div style="flex:1;min-width:0"><h3><?= v2_h($nome) ?></h3>
          <div class="ph"><?= v2_h($cliente['numero'] ?? '—') ?> · <?= v2_h(v2_status_label((string)($cliente['status'] ?? ''))) ?> · ficha <?= $anamnese ? 'vinculada (#' . (int)$anamnese['id'] . ')' : 'não vinculada' ?></div></div>
        <a class="badge b-blue" style="text-decoration:none" href="<?= v2_h(v2_url(['page' => 'conversa'], ['ver', 'abrir'])) ?>">💬 abrir no WhatsApp</a>
      </div>

      <div class="kv" style="margin-bottom:14px">
        <div class="c"><div class="k">Mensagens</div><div class="v"><?= $contagem['recebidas'] + $contagem['enviadas'] ?></div></div>
        <div class="c"><div class="k">Recebidas / enviadas</div><div class="v"><?= $contagem['recebidas'] ?> / <?= $contagem['enviadas'] ?></div></div>
        <div class="c"><div class="k">Sessões</div><div class="v"><?= count($tatuagens) ?></div></div>
        <div class="c"><div class="k">Valor</div><div class="v"><?= v2_money($cliente['valor'] ?? 0) ?></div></div>
        <div class="c"><div class="k">1ª mensagem</div><div class="v"><?= v2_h($contagem['primeira'] ? date('d/m/Y', (int)strtotime((string)$contagem['primeira'])) : '—') ?></div></div>
        <div class="c"><div class="k">Última mensagem</div><div class="v"><?= v2_h($contagem['ultima'] ? date('d/m/Y', (int)strtotime((string)$contagem['ultima'])) : '—') ?></div></div>
      </div>

      <div class="layer<?= v2_layer_aberta(1, $abrir) ?>" data-t="1">
        <div class="lhead" data-toggle><span class="n">camada 1</span><span class="nm">O essencial</span><span class="wh">todo mundo vê</span></div>
        <div class="lbody"><div class="kv">
          <div class="c"><div class="k">Status</div><div class="v"><?= v2_h(v2_status_label((string)($cliente['status'] ?? ''))) ?></div></div>
          <div class="c"><div class="k">Último contato</div><div class="v"><?= v2_h($cliente['data_ultimo_contato'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Telefone</div><div class="v"><?= v2_h($cliente['numero'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Valor</div><div class="v"><?= v2_money($cliente['valor'] ?? 0) ?></div></div>
        </div></div>
      </div>

      <div class="layer<?= v2_layer_aberta(2, $abrir) ?>" data-t="2">
        <div class="lhead" data-toggle><span class="n">camada 2</span><span class="nm">Atendimento</span><span class="wh">quem responde</span></div>
        <div class="lbody"><div class="kv">
          <div class="c"><div class="k">Modo</div><div class="v"><?= v2_h($cliente['modo_atendimento'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Atendente</div><div class="v"><?= v2_h(($cliente['atendente'] ?? '') ?: '—') ?></div></div>
          <div class="c"><div class="k">Interesse</div><div class="v"><?= v2_h($cliente['interesse'] ?: '—') ?></div></div>
          <div class="c"><div class="k">Origem</div><div class="v"><?= v2_h($cliente['origem'] ?? '—') ?></div></div>
        </div>
        <div style="margin-top:12px"><a class="badge b-red" style="text-decoration:none" href="<?= v2_h(v2_url(['page' => 'conversa'], ['ver', 'abrir'])) ?>">abrir conversa</a></div>
        </div>
      </div>

      <div class="layer<?= v2_layer_aberta(3, $abrir) ?><?= $staff ? ' lock' : '' ?>" data-t="3">
        <div class="lhead" data-toggle><span class="n">camada 3</span><span class="nm">Estúdio &amp; técnico</span><span class="wh">tatuador</span></div>
        <div class="lbody"><div class="kv">
          <div class="c"><div class="k">Alergias</div><div class="v"><?= v2_h($anamnese['alergias'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Doenças</div><div class="v"><?= v2_h($anamnese['tem_doencas'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Medicamentos</div><div class="v"><?= v2_h($anamnese['uso_medicamentos'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Estilo preferido</div><div class="v"><?= v2_h($anamnese['estilo_tatuagem'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Histórico de tatuagens</div><div class="v"><?= v2_h($anamnese['historico_tatuagens'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Sessões</div><div class="v"><?= count($tatuagens) ?></div></div>
        </div>
        <?php if ($tatuagens): ?><div style="margin-top:12px"><?php foreach ($tatuagens as $t): ?>
          <div class="row"><span class="badge b-gray"><?= v2_h($t['data_tatuagem']) ?></span><div class="who"><small><?= v2_h((string)$t['descricao']) ?> · <?= v2_money($t['valor']) ?> · <?= v2_h((string)$t['status']) ?></small></div></div>
        <?php endforeach; ?></div><?php endif; ?>
        </div>
        <div class="lockn">🔒 Camada técnica — liberada para quem tatua.</div>
      </div>

      <div class="layer<?= v2_layer_aberta(4, $abrir) ?><?= $staff ? ' lock' : '' ?>" data-t="4">
        <div class="lhead" data-toggle><span class="n">camada 4</span><span class="nm">Perfil &amp; negócio</span><span class="wh">dono</span></div>
        <div class="lbody"><div class="kv">
          <div class="c"><div class="k">E-mail</div><div class="v"><?= v2_h($anamnese['email'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Nascimento</div><div class="v"><?= v2_h($anamnese['data_nascimento'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Instagram</div><div class="v"><?= v2_h($anamnese['instagram_cliente'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Profissão</div><div class="v"><?= v2_h($anamnese['profissao'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Endereço</div><div class="v"><?= v2_h($anamnese['endereco'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Hobbies</div><div class="v"><?= v2_h($anamnese['hobbies'] ?? '—') ?></div></div>
          <div class="c"><div class="k">Ticket</div><div class="v"><?= v2_money($cliente['valor'] ?? 0) ?></div></div>
          <div class="c"><div class="k">Cliente desde</div><div class="v"><?= v2_h(substr((string)($cliente['created_at'] ?? ''), 0, 10) ?: '—') ?></div></div>
        </div></div>
        <div class="lockn">🔒 Camada de negócio — liberada para o dono.</div>
      </div>

      <div class="layer<?= v2_layer_aberta(5, $abrir) ?>" data-t="5">
        <div class="lhead" data-toggle><span class="n">camada 5</span><span class="nm">Histórico completo</span><span class="wh">conversa + sessões</span></div>
        <div class="lbody">
          <div class="note n-gray" style="margin-top:13px">Período: <b><?= v2_h(v2_periodo_label()) ?></b> · <?= count($linha) ?> registro<?= count($linha) === 1 ? '' : 's' ?> · <?= count($msgsHist) ?> mensagens carregadas</div>
          <?php if (!$linha): ?>
            <div class="note n-gray" style="margin-top:10px">Nada registrado nesse período.</div>
          <?php else: ?>
            <div class="timeline">
            <?php foreach ($linha as $ev): ?>
              <?php if ($ev['tipo'] === 'msg'): $m = $ev['m']; $out = (int)$m['from_me'] === 1; ?>
                <div class="tl">
                  <span class="tln <?= $out ? 'tl-out' : 'tl-in' ?>"><?= $out ? 'saiu' : 'chegou' ?></span>
                  <div class="tlb">
                    <b><?= v2_h(date('d/m/Y H:i', (int)strtotime((string)$m['data']))) ?></b>
                    <?php $rot = v2_msg_rotulo((string)($m['tipo'] ?? 'texto')); ?>
                    <?php if ($rot !== ''): ?><div class="tlm"><?= v2_h($rot) ?></div><?php endif; ?>
                    <?php $corpo = (string)($m['tipo'] === 'audio' ? ($m['transcricao'] ?: '') : $m['texto']); ?>
                    <?php if (trim($corpo) !== ''): ?><div class="tlx"><?= nl2br(v2_h(mb_substr($corpo, 0, 400))) ?></div><?php endif; ?>
                  </div>
                </div>
              <?php else: $t = $ev['t']; ?>
                <div class="tl">
                  <span class="tln tl-ses">sessão</span>
                  <div class="tlb">
                    <b><?= v2_h((string)$t['data_tatuagem']) ?><?= trim((string)$t['hora_inicio']) !== '' ? ' · ' . v2_h(substr((string)$t['hora_inicio'], 0, 5)) : '' ?></b>
                    <div class="tlx"><?= v2_h((string)$t['descricao']) ?> · <?= v2_money($t['valor']) ?> · <?= v2_h((string)$t['status']) ?></div>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var cabecas = document.querySelectorAll('.lhead[data-toggle]');
  for (var i = 0; i < cabecas.length; i++) {
    cabecas[i].addEventListener('click', function () {
      this.parentNode.classList.toggle('open');
    });
  }
})();
</script>
<?php v2_layout_bottom(); ?>
