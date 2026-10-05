<?php
/**
 * Cliente: busca, lista e a ficha completa em camadas + historico.
 */
$studio = v2_try(static fn() => v2_studio(), null);
$temChat = $studio instanceof PDO && v2_tabela_existe($studio, 'whatsapp_conversations');
$temMsgs = $studio instanceof PDO && v2_tabela_existe($studio, 'whatsapp_messages');

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
    $lista = v2_studio_conversas($studio, [
        'busca' => $busca,
        'status' => $fstatus,
        'periodo' => $periodo,
        'ordem' => $ordem,
        'limit' => 300,
    ]);
}

/* ---------- cliente escolhido ---------- */
$cliente = null;
if ($id !== '' && $temChat) {
    foreach (v2_studio_conversas($studio, ['limit' => 1000]) as $c) {
        if ((string)$c['id'] === $id) { $cliente = $c; break; }
    }
}
if (!$cliente && $lista) {
    $cliente = $lista[0];
    $id = (string)$cliente['id'];
}

/* ---------- numeros da conversa ---------- */
$contagem = ['recebidas' => 0, 'enviadas' => 0, 'primeira' => null, 'ultima' => null];
$msgsHist = [];
if ($cliente && $temMsgs) {
    $msgsHist = v2_studio_mensagens($studio, (int)$cliente['id'], $periodo, 'desc', 80);
    $contagem = v2_studio_contagem($studio, (int)$cliente['id']);
}

/* ---------- ficha do estudio (anamnese + sessoes) ---------- */
$anamnese = null;
$tatuagens = [];
if ($studio instanceof PDO && $cliente) {
    $sqlA = "SELECT c.id, c.name AS nome, c.phone AS telefone, c.email,
                    c.birth_date AS data_nascimento, c.instagram AS instagram_cliente,
                    c.occupation AS profissao,
                    CONCAT_WS(', ', NULLIF(c.address_street, ''),
                              NULLIF(TRIM(CONCAT(COALESCE(c.address_number, ''), ' ', COALESCE(c.address_complement, ''))), ''),
                              NULLIF(c.address_neighborhood, ''), NULLIF(c.address_city, ''),
                              NULLIF(c.address_state, '')) AS endereco,
                    c.previous_tattoos AS historico_tatuagens, c.reference_style AS estilo_tatuagem,
                    c.allergies AS alergias, c.health_conditions AS tem_doencas,
                    c.medications AS uso_medicamentos, c.body_area, c.notes AS observacoes
             FROM customers c WHERE 1 = 1";
    $parA = [];
    $cid = (int)($cliente['customer_id'] ?? 0);
    if ($cid > 0) {
        $sqlA .= ' AND c.id = ?';
        $parA[] = $cid;
    } else {
        $norm = v2_norm((string)($cliente['numero'] ?? ''));
        $sqlA .= ' AND REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.phone, ""), " ", ""), "-", ""), "(", ""), ")", "") LIKE ?';
        $parA[] = '%' . substr($norm, -8) . '%';
    }
    $anamnese = v2_try(static function () use ($studio, $sqlA, $parA) {
        $st = $studio->prepare($sqlA);
        $st->execute($parA);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }, null);

    $sessoesSql = "SELECT COALESCE(NULLIF(a.title, ''), a.description) AS descricao,
                          a.description AS observacoes, a.value AS valor,
                          a.appointment_date AS data_tatuagem, a.start_time AS hora_inicio, a.status
                   FROM appointments a WHERE ";
    if ($anamnese) {
        $tatuagens = v2_try(static function () use ($studio, $sessoesSql, $anamnese) {
            $st = $studio->prepare($sessoesSql . 'a.customer_id = ? ORDER BY a.appointment_date DESC LIMIT 20');
            $st->execute([(int)$anamnese['id']]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }, []);
    } elseif ((int)($cliente['lead_id'] ?? 0) > 0) {
        $tatuagens = v2_try(static function () use ($studio, $sessoesSql, $cliente) {
            $st = $studio->prepare($sessoesSql . 'a.lead_id = ? ORDER BY a.appointment_date DESC LIMIT 20');
            $st->execute([(int)$cliente['lead_id']]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }, []);
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
