<?php
/**
 * WhatsApp do sistema: lista de conversas + conversa + resposta.
 * Nesta fase NADA e enviado ao cliente: o compositor so monta rascunho e audio.
 */
$pdo = v2_try(static fn() => v2_crm(), null);
$temChat = $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_clientes');
$temMsgs = $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_mensagens');

$busca = trim((string)($_GET['busca'] ?? ''));
$fstatus = (string)($_GET['status'] ?? '');
$soAguardando = (string)($_GET['aguardando'] ?? '') === '1';
$ordem = (string)($_GET['ordem'] ?? 'recentes');
$id = (string)($_GET['id'] ?? '');
$periodo = $temMsgs ? v2_periodo_intervalo() : null;

$conversas = [];
if ($temChat && $temMsgs) {
    $sql = "SELECT c.id, c.nome, c.numero, c.status, c.modo_atendimento, c.valor, c.interesse, c.data_ultimo_contato,
                   a.recebidas, a.enviadas, a.total, a.ultima_data,
                   u.tipo AS ultimo_tipo, u.texto AS ultimo_texto, u.from_me AS ultimo_de, u.transcricao AS ultima_transcricao
            FROM crm_whatsapp_clientes c
            LEFT JOIN (
                SELECT cliente_id, SUM(from_me = 0) AS recebidas, SUM(from_me = 1) AS enviadas,
                       COUNT(*) AS total, MAX(data) AS ultima_data
                FROM crm_whatsapp_mensagens GROUP BY cliente_id
            ) a ON a.cliente_id = c.id
            LEFT JOIN crm_whatsapp_mensagens u ON u.id = (
                SELECT m.id FROM crm_whatsapp_mensagens m
                WHERE m.cliente_id = c.id ORDER BY m.data DESC, m.id DESC LIMIT 1
            )
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
    if ($soAguardando) {
        $sql .= ' AND u.from_me = 0';
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

    $conversas = v2_try(static function () use ($pdo, $sql, $par) {
        $st = $pdo->prepare($sql);
        $st->execute($par);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }, []);
}

if ($id === '' && $conversas) {
    $id = (string)$conversas[0]['id'];
}

$cliente = null;
$msgs = [];
if ($id !== '' && $temChat) {
    $stmt = $pdo->prepare('SELECT * FROM crm_whatsapp_clientes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($cliente && $temMsgs) {
    $stmt = $pdo->prepare('SELECT de, texto, data, from_me, tipo, transcricao, media_file_name, media_url
                           FROM crm_whatsapp_mensagens WHERE cliente_id = ?
                           ORDER BY data ASC, id ASC LIMIT 400');
    $stmt->execute([$id]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$ultimaEntrada = null;
foreach ($msgs as $m) {
    if ((int)$m['from_me'] === 0) {
        $ultimaEntrada = $m;
    }
}
$textoUltima = '';
if ($ultimaEntrada) {
    $textoUltima = (string)$ultimaEntrada['texto'];
    if ((string)($ultimaEntrada['tipo'] ?? '') === 'audio' && trim((string)$ultimaEntrada['transcricao']) !== '') {
        $textoUltima = (string)$ultimaEntrada['transcricao'];
    }
}
$ultimaEraAudio = $ultimaEntrada && (string)($ultimaEntrada['tipo'] ?? 'texto') === 'audio';
$sugestao = v2_sugestao_resposta($textoUltima);
$tomPadrao = 'calor_rapido';

$filtrosAtivos = ($busca !== '' ? 1 : 0) + ($fstatus !== '' ? 1 : 0) + ($soAguardando ? 1 : 0) + ($periodo !== null ? 1 : 0);

v2_layout_top('conversa', 'WhatsApp');
?>
<div class="head">
  <div class="kick">o whatsapp do estúdio</div>
  <h2>Conversar com o cliente sem sair do sistema</h2>
  <p>Tudo que chega e sai fica aqui. Você escreve, ouve no tom da Irene e só depois decide — <b>nesta fase nada é enviado</b>.</p>
</div>

<?php if (!$temChat): ?>
  <div class="card pad"><div class="note n-amber">A tabela de conversas não está disponível neste ambiente.</div></div>
<?php else: ?>

<div class="card pad" style="margin-bottom:14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
  <span class="badge b-gray">🔒 modo validação · envio desativado</span>
  <span class="tag"><?= count($conversas) ?> conversa<?= count($conversas) === 1 ? '' : 's' ?> na lista</span>
  <span class="tag">período: <?= v2_h(v2_periodo_label()) ?></span>
  <?php if ($filtrosAtivos > 0): ?><a class="fchip" href="index.php?page=conversa">✕ limpar filtros</a><?php endif; ?>
</div>

<?php v2_filtro_periodo(); ?>

<div class="inbox">
  <aside class="card ilist">
    <div class="ilisthead">
      <form method="get" class="crow" style="gap:6px">
        <?php foreach ($_GET as $k => $v): if (in_array($k, ['busca', 'status', 'ordem', 'aguardando'], true) || !is_scalar($v)) { continue; } ?>
          <input type="hidden" name="<?= v2_h((string)$k) ?>" value="<?= v2_h((string)$v) ?>">
        <?php endforeach; ?>
        <input type="text" name="busca" class="fin" value="<?= v2_h($busca) ?>" placeholder="Buscar nome, número ou interesse...">
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
        <button class="fgo" type="submit">buscar</button>
      </form>
      <div class="crow">
        <a class="fchip<?= $soAguardando ? ' on' : '' ?>" href="<?= v2_h(v2_url($soAguardando ? ['aguardando' => null] : ['aguardando' => '1'], ['id', 'v'])) ?>">⏳ só aguardando resposta</a>
        <?php if ($busca !== ''): ?><a class="fchip on" href="<?= v2_h(v2_url([], ['busca', 'id'])) ?>">busca: <?= v2_h($busca) ?> ✕</a><?php endif; ?>
      </div>
    </div>

    <div class="iconvos">
      <?php if (!$conversas): ?>
        <div class="note n-gray" style="margin:13px">Nenhuma conversa com esses filtros.</div>
      <?php else: foreach ($conversas as $c): $on = (string)$c['id'] === $id; $aguardando = (int)($c['ultimo_de'] ?? 1) === 0; ?>
        <a class="convo<?= $on ? ' on' : '' ?>" href="<?= v2_h(v2_url(['id' => (string)$c['id']], ['v'])) ?>">
          <span class="cavi"><?= v2_h(v2_iniciais((string)($c['nome'] ?: 'Cliente'))) ?></span>
          <span class="cwho">
            <b><?= v2_h($c['nome'] ?: 'Cliente') ?><?= $aguardando ? ' <span class="needle">•</span>' : '' ?></b>
            <small><?= v2_h(v2_msg_preview((string)($c['ultimo_tipo'] ?? 'texto'), (string)($c['ultimo_texto'] ?? ''), (string)($c['ultima_transcricao'] ?? ''))) ?></small>
            <small class="cmeta"><?= v2_h(v2_status_label((string)$c['status'])) ?> · <?= (int)($c['recebidas'] ?? 0) ?> recebidas · <?= v2_h(v2_desde($c['ultima_data'] ?? null)) ?></small>
          </span>
          <span class="cside">
            <?php if ($aguardando): ?><span class="badge b-red">responder</span><?php else: ?><span class="badge b-gray">ok</span><?php endif; ?>
          </span>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </aside>

  <section class="card thread">
    <?php if (!$cliente): ?>
      <div class="pad"><div class="note n-gray">Escolha uma conversa na lista ao lado.</div></div>
    <?php else: ?>
      <div class="thead">
        <span class="cavi"><?= v2_h(v2_iniciais((string)($cliente['nome'] ?: 'Cliente'))) ?></span>
        <div style="flex:1;min-width:0">
          <h3><?= v2_h($cliente['nome'] ?: 'Cliente') ?></h3>
          <div class="ph"><?= v2_h($cliente['numero']) ?> · <?= v2_h(v2_status_label((string)$cliente['status'])) ?> · <?= v2_h((string)($cliente['modo_atendimento'] ?? '')) ?> · <?= (int)($cliente['valor'] ?? 0) > 0 ? v2_money($cliente['valor']) : 'sem valor' ?></div>
        </div>
        <?php if ($ultimaEraAudio): ?><span class="badge b-violet">🎙 cliente falou por áudio</span><?php endif; ?>
        <a class="badge b-blue" style="text-decoration:none" href="index.php?page=cliente&amp;id=<?= urlencode((string)$cliente['id']) ?>">ficha completa</a>
      </div>

      <div class="tbody">
        <?php if (!$msgs): ?>
          <div class="note n-gray">Sem mensagens registradas nesta conversa.</div>
        <?php else: $diaAtual = ''; foreach ($msgs as $m):
            $d = substr((string)$m['data'], 0, 10);
            if ($d !== $diaAtual): $diaAtual = $d; ?>
              <div class="daysep"><?= v2_h($d === date('Y-m-d') ? 'Hoje' : date('d/m/Y', (int)strtotime($d))) ?></div>
            <?php endif;
            $out = (int)$m['from_me'] === 1;
            $tipo = (string)($m['tipo'] ?? 'texto');
            $rot = v2_msg_rotulo($tipo, (string)($m['media_file_name'] ?? ''));
            $corpo = '';
            if ($tipo === 'audio') {
                $corpo = trim((string)$m['transcricao']) !== '' ? (string)$m['transcricao'] : 'áudio ainda sem transcrição';
            } elseif ($tipo === 'texto') {
                $corpo = (string)$m['texto'];
            } else {
                $corpo = trim((string)$m['texto']);
            }
        ?>
          <div class="bub <?= $out ? 'out' : 'in' ?>">
            <?php if ($rot !== ''): ?><span class="mtag"><?= v2_h($rot) ?></span><?php endif; ?>
            <?php if ($corpo !== ''): ?><div class="mtxt"><?= nl2br(v2_h($corpo)) ?></div><?php endif; ?>
            <span class="t"><?= v2_h(date('d/m H:i', (int)strtotime((string)$m['data']))) ?><?= $out ? ' · enviada' : '' ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="composer">
        <div class="crow">
          <span class="badge b-gray">🔒 envio desativado (validação)</span>
          <?php if ($sugestao): ?><span class="badge b-violet">sugestão da Irene: <?= v2_h($sugestao['motivo']) ?></span><?php endif; ?>
          <span class="fsep" style="margin-left:auto"></span>
          <label class="fsep" for="ireneTom">tom</label>
          <select class="fsel" id="ireneTom">
            <option value="calor_rapido" selected>com calor (mais rápido)</option>
            <option value="calor">com calor (original)</option>
            <option value="animada">animada</option>
            <option value="serena">serena</option>
            <option value="">padrão</option>
          </select>
        </div>
        <textarea id="ireneTexto" placeholder="Escreva a resposta para <?= v2_h($cliente['nome'] ?: 'o cliente') ?>..."><?= v2_h($sugestao['texto'] ?? '') ?></textarea>
        <div class="crow">
          <button type="button" class="btn btn-p" onclick="ireneOuvir()">🔊 Ouvir no tom da Irene</button>
          <button type="button" class="btn btn-g" onclick="ireneSalvar()">💾 Salvar rascunho</button>
          <button type="button" class="btn btn-g" onclick="ireneLimpar()">🧹 Limpar</button>
          <span class="badge b-gray" id="ireneAviso">rascunho fica salvo neste navegador</span>
          <button type="button" class="btn btn-a" style="margin-left:auto" disabled title="O envio será ligado só depois da sua validação">📤 Enviar</button>
        </div>
        <audio id="ireneAudio" class="audio" controls preload="none" style="display:none"></audio>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
var TTS_ENGINE = <?= json_encode((string)(v2_config()['tts_engine'] ?? 'windows')) ?>;
var IRENE_ID = <?= json_encode($id) ?>;
var IRENE_SUGESTAO = <?= json_encode((string)($sugestao['texto'] ?? '')) ?>;
(function () {
  var ta = document.getElementById('ireneTexto');
  var av = document.getElementById('ireneAviso');
  var audio = document.getElementById('ireneAudio');
  var tom = document.getElementById('ireneTom');
  if (!ta) { return; }
  var chave = 'irene_rascunho_' + IRENE_ID;

  try {
    var salvo = window.localStorage.getItem(chave);
    if (salvo) { ta.value = salvo; av.textContent = 'rascunho recuperado deste navegador'; }
  } catch (e) {}

  window.ireneOuvir = function () {
    var t = (ta.value || '').trim();
    if (!t) { av.textContent = 'escreva algo antes de ouvir'; return; }
    audio.style.display = 'block';
    av.textContent = 'gerando áudio...';
    audio.src = 'api/tts.php?engine=' + encodeURIComponent(TTS_ENGINE)
      + '&v=' + encodeURIComponent(tom ? tom.value : '')
      + '&text=' + encodeURIComponent(t) + '&t=' + Date.now();
    audio.onerror = function () { av.textContent = 'não consegui gerar o áudio agora'; };
    audio.onloadeddata = function () { av.textContent = 'áudio pronto · nada foi enviado'; };
    var p = audio.play();
    if (p && p.catch) { p.catch(function () {}); }
  };

  window.ireneSalvar = function () {
    try {
      window.localStorage.setItem(chave, ta.value);
      av.textContent = 'rascunho salvo neste navegador';
    } catch (e) { av.textContent = 'não consegui salvar o rascunho'; }
  };

  window.ireneLimpar = function () {
    ta.value = '';
    av.textContent = 'texto limpo';
  };

  window.ireneSugestao = function () {
    ta.value = IRENE_SUGESTAO || ta.value;
  };
})();
</script>

<?php endif; ?>
<?php v2_layout_bottom(); ?>
