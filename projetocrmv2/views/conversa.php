<?php
/**
 * WhatsApp do sistema: lista de conversas + conversa + resposta.
 * Mesmo layout da tela studio_whatsapp_baileys do CRM (classes m2-*).
 * Nesta fase NADA e enviado ao cliente: o compositor so monta rascunho e audio.
 */
$studio = v2_try(static fn() => v2_studio(), null);
$temChat = $studio instanceof PDO && v2_tabela_existe($studio, 'whatsapp_conversations');
$temMsgs = $studio instanceof PDO && v2_tabela_existe($studio, 'whatsapp_messages');

$busca = trim((string)($_GET['busca'] ?? ''));
$fstatus = (string)($_GET['status'] ?? '');
$soAguardando = (string)($_GET['aguardando'] ?? '') === '1';
$ordem = (string)($_GET['ordem'] ?? 'recentes');
$id = (string)($_GET['id'] ?? '');
$periodo = $temMsgs ? v2_periodo_intervalo() : null;

$conversas = [];
if ($temChat && $temMsgs) {
    $conversas = v2_studio_conversas($studio, [
        'busca' => $busca,
        'status' => $fstatus,
        'periodo' => $periodo,
        'ordem' => $ordem,
        'aguardando' => $soAguardando,
        'limit' => 300,
    ]);
}

if ($id === '' && $conversas) {
    $id = (string)$conversas[0]['id'];
}

$cliente = null;
$msgs = [];
if ($id !== '' && $temChat) {
    foreach ($conversas as $c) {
        if ((string)$c['id'] === $id) { $cliente = $c; break; }
    }
    if (!$cliente) {
        foreach (v2_studio_conversas($studio, ['limit' => 1000]) as $c) {
            if ((string)$c['id'] === $id) { $cliente = $c; break; }
        }
    }
}
if ($cliente && $temMsgs) {
    // Historico pode passar de 400 mensagens (ponte): carrega as 400 mais recentes e exibe em ordem cronologica.
    $msgs = array_reverse(v2_studio_mensagens($studio, (int)$cliente['id'], null, 'desc', 400));
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

$filtrosAtivos = ($busca !== '' ? 1 : 0) + ($fstatus !== '' ? 1 : 0) + ($periodo !== null ? 1 : 0);
$periodoAtual = v2_periodo_atual();
$de = trim((string)($_GET['de'] ?? ''));
$ate = trim((string)($_GET['ate'] ?? ''));
$emojis = ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😍','😘','😗','😙','😚','😋','😛','😜','🤪','😎','🥳','😏','😒','😞','😔','😢','😭','😤','😡','🤯','🥺','😬','😴','🤔','🫠','🤝','👍','👎','👌','🤌','👏','🙌','🙏','💪','🤘','✌️','👀','🫶','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','💕','💞','💘','🔥','✨','⭐','💫','💥','💯','✅','❌','⚠️','📅','⏰','📍','💰','💳','📸','🎉','🎁'];

function v2_conversa_avatar(?string $nome): string
{
    return v2_iniciais((string)($nome ?: 'Cliente'));
}

v2_layout_top('conversa', 'WhatsApp');
?>
<?php if (!$temChat): ?>
  <div class="card pad"><div class="note n-amber">A tabela de conversas não está disponível neste ambiente.</div></div>
<?php else: ?>

<div class="m2-shell<?= $cliente ? ' has-chat' : '' ?>" id="m2Shell">
  <aside class="m2-list has-filters" id="m2ListPanel">
    <header class="m2-top">
      <strong>WhatsApp</strong>
      <span class="m2-top-actions"></span>
    </header>

    <div class="m2-toggle-row">
      <a class="m2-toggle<?= $soAguardando ? '' : ' on' ?>" href="<?= v2_h(v2_url(['aguardando' => null])) ?>">Todas</a>
      <a class="m2-toggle<?= $soAguardando ? ' on' : '' ?>" href="<?= v2_h(v2_url(['aguardando' => '1'])) ?>">Sem resposta</a>
    </div>

    <div class="m2-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input id="m2Search" type="search" placeholder="Buscar conversa" autocomplete="off">
    </div>

    <details class="m2-filter-menu"<?= $filtrosAtivos > 0 ? ' open' : '' ?>>
      <summary class="m2-filter-toggle"><i class="fa-solid fa-sliders"></i><span>Filtros</span><?= $filtrosAtivos > 0 ? '<b>Ativo</b>' : '' ?></summary>
      <div class="m2-filter-panel">
        <div class="m2-filter-group">
          <strong>Status</strong>
          <div class="m2-filter-chip-row">
            <a class="m2-filter-chip<?= $fstatus === '' ? ' active' : '' ?>" href="<?= v2_h(v2_url(['status' => null])) ?>">todos</a>
            <?php foreach (v2_status_opcoes() as $k => $label): ?>
              <a class="m2-filter-chip<?= $k === $fstatus ? ' active' : '' ?>" href="<?= v2_h(v2_url(['status' => $k])) ?>"><?= v2_h($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="m2-filter-group">
          <strong>Ordem</strong>
          <div class="m2-filter-chip-row">
            <?php foreach (['recentes' => 'mais recentes', 'parados' => 'mais parados', 'valor' => 'maior valor', 'nome' => 'ordem alfabética'] as $k => $label): ?>
              <a class="m2-filter-chip<?= $k === $ordem ? ' active' : '' ?>" href="<?= v2_h(v2_url(['ordem' => $k])) ?>"><?= v2_h($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="m2-filter-group">
          <strong>Período</strong>
          <div class="m2-filter-chip-row">
            <?php foreach (v2_periodo_opcoes() as $k => $label): ?>
              <a class="m2-filter-chip<?= $k === $periodoAtual ? ' active' : '' ?>" href="<?= v2_h(v2_url(['periodo' => $k], ['de', 'ate'])) ?>"><?= v2_h($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="m2-filter-group">
          <strong>Período exato</strong>
          <form class="m2-range-form" method="get">
            <?php foreach ($_GET as $k => $v): if (in_array($k, ['de', 'ate', 'periodo'], true) || !is_scalar($v)) { continue; } ?>
              <input type="hidden" name="<?= v2_h((string)$k) ?>" value="<?= v2_h((string)$v) ?>">
            <?php endforeach; ?>
            <div class="m2-range-grid">
              <label><span>De</span><input type="date" name="de" value="<?= v2_h($de) ?>"></label>
              <label><span>Até</span><input type="date" name="ate" value="<?= v2_h($ate) ?>"></label>
            </div>
            <small class="m2-range-help">Escolha as duas datas para limitar as conversas ao intervalo.</small>
            <div class="m2-range-actions">
              <button class="m2-range-apply" type="submit">Aplicar período</button>
              <a class="m2-range-clear" href="<?= v2_h(v2_url([], ['de', 'ate', 'periodo'])) ?>">Limpar</a>
            </div>
          </form>
        </div>

        <div class="m2-filter-group">
          <strong>Busca</strong>
          <form class="m2-range-form" method="get">
            <?php foreach ($_GET as $k => $v): if (in_array($k, ['busca'], true) || !is_scalar($v)) { continue; } ?>
              <input type="hidden" name="<?= v2_h((string)$k) ?>" value="<?= v2_h((string)$v) ?>">
            <?php endforeach; ?>
            <div class="m2-range-grid">
              <label><span>Nome, número ou interesse</span><input type="text" name="busca" value="<?= v2_h($busca) ?>"></label>
            </div>
            <div class="m2-range-actions">
              <button class="m2-range-apply" type="submit">Buscar</button>
              <?php if ($busca !== ''): ?><a class="m2-range-clear" href="<?= v2_h(v2_url([], ['busca'])) ?>">Limpar</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </details>

    <nav class="m2-items" id="m2Items">
      <?php if (!$conversas): ?>
        <div class="m2-empty"><i class="fa-regular fa-comment-dots"></i><p>Nenhuma conversa com esses filtros.</p></div>
      <?php else: foreach ($conversas as $c): $on = (string)$c['id'] === $id; $aguardando = (int)($c['nao_lida'] ?? ((int)($c['ultimo_de'] ?? 1) === 0 ? 1 : 0)) === 1;
        $dataCurta = !empty($c['ultima_data']) ? date('d/m H:i', (int)strtotime((string)$c['ultima_data'])) : '';
        $procurar = mb_strtolower(($c['nome'] ?: '') . ' ' . (string)$c['numero'] . ' ' . (string)($c['ultimo_texto'] ?? '') . ' ' . (string)($c['interesse'] ?? ''), 'UTF-8');
      ?>
        <a class="m2-item<?= $on ? ' active' : '' ?><?= $aguardando ? ' is-unread' : '' ?>" href="<?= v2_h(v2_url(['id' => (string)$c['id']])) ?>" data-search="<?= v2_h($procurar) ?>">
          <span class="m2-avatar"><?= v2_h(v2_conversa_avatar((string)$c['nome'])) ?><?= $aguardando ? '<i class="m2-unread-dot" aria-label="Aguardando resposta"></i>' : '' ?></span>
          <span>
            <strong><?= v2_h($c['nome'] ?: 'Cliente') ?></strong>
            <small><?= v2_h(v2_msg_preview((string)($c['ultimo_tipo'] ?? 'texto'), (string)($c['ultimo_texto'] ?? ''), (string)($c['ultima_transcricao'] ?? ''))) ?></small>
            <small class="m2-item-badges">
              <b class="<?= $aguardando ? 'warn' : 'ok' ?>"><?= $aguardando ? 'sem resposta' : 'respondida' ?></b>
              <?php if ($dataCurta !== ''): ?><b class="muted"><?= v2_h($dataCurta) ?></b><?php endif; ?>
            </small>
          </span>
          <em><?= v2_h((string)$c['numero']) ?></em>
        </a>
      <?php endforeach; endif; ?>
    </nav>
  </aside>

  <section class="m2-chat" id="m2ChatPanel">
    <header class="m2-chat-head">
      <?php if ($cliente): ?>
        <a class="m2-icon" href="<?= v2_h(v2_url(['id' => '0'])) ?>" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <span class="m2-avatar"><?= v2_h(v2_conversa_avatar((string)$cliente['nome'])) ?></span>
        <span class="m2-title">
          <strong><?= v2_h($cliente['nome'] ?: 'Cliente') ?></strong>
          <small><?= v2_h((string)$cliente['numero']) ?> · <?= v2_h(v2_status_label((string)$cliente['status'])) ?> · <?= v2_h((string)($cliente['modo_atendimento'] ?? 'human')) ?> · <?= (int)($cliente['valor'] ?? 0) > 0 ? v2_money($cliente['valor']) : 'sem valor' ?></small>
        </span>
        <button class="m2-icon" type="button" data-action="painel" aria-label="Ações" title="Painel da conversa"><i class="fa-solid fa-ellipsis-vertical"></i></button>
      <?php else: ?>
        <span class="m2-title"><strong>WhatsApp</strong><small>escolha uma conversa na lista</small></span>
      <?php endif; ?>
    </header>

    <div class="m2-messages" id="m2Messages">
      <?php if (!$cliente): ?>
        <div class="m2-empty"><i class="fa-regular fa-comments"></i><p>Escolha uma conversa na lista ao lado.</p></div>
      <?php elseif (!$msgs): ?>
        <div class="m2-empty"><i class="fa-regular fa-comment"></i><p>Sem mensagens registradas nesta conversa.</p></div>
      <?php else: $diaAtual = ''; foreach ($msgs as $m):
          $d = substr((string)$m['data'], 0, 10);
          $out = (int)$m['from_me'] === 1;
          $tipo = (string)($m['tipo'] ?? 'texto');
          $transcricao = trim((string)($m['transcricao'] ?? ''));
          if ($tipo === 'audio') {
              $corpo = $transcricao !== '' ? '' : 'áudio ainda sem transcrição';
          } else {
              $corpo = (string)$m['texto'];
          }
          $media = trim((string)($m['media_url'] ?? ''));
          $previa = trim($corpo !== '' ? $corpo : ($transcricao !== '' ? $transcricao : '[' . $tipo . ']'));
      ?>
        <?php if ($d !== $diaAtual): $diaAtual = $d; ?>
          <div class="m2-dia"><?= v2_h($d === date('Y-m-d') ? 'Hoje' : date('d/m/Y', (int)strtotime($d))) ?></div>
        <?php endif; ?>
        <article class="m2-msg <?= $out ? 'out' : 'in' ?>" data-message-preview="<?= v2_h(mb_substr($previa, 0, 200, 'UTF-8')) ?>" data-message-sender="<?= v2_h($out ? 'Você' : (string)($cliente['nome'] ?: 'Cliente')) ?>">
          <div class="m2-bubble">
            <?php if ($media !== '' && in_array($tipo, ['image', 'imagem'], true)): ?>
              <img class="m2-media" src="<?= v2_h($media) ?>" alt="<?= v2_h((string)($m['media_file_name'] ?? 'imagem')) ?>">
            <?php elseif ($media !== '' && $tipo === 'video'): ?>
              <video class="m2-media" src="<?= v2_h($media) ?>" controls></video>
            <?php endif; ?>
            <?php if ($corpo !== ''): ?><p><?= nl2br(v2_h($corpo)) ?></p><?php endif; ?>
            <?php if ($transcricao !== '' && $transcricao !== $corpo): ?><div class="m2-transcript"><?= v2_h($transcricao) ?></div><?php endif; ?>
            <div class="m2-bubble-foot">
              <time><?= v2_h(date('d/m/Y H:i', (int)strtotime((string)$m['data']))) ?></time>
              <button class="m2-reply-action" type="button" aria-label="Responder mensagem"><i class="fa-solid fa-reply"></i></button>
            </div>
          </div>
        </article>
      <?php endforeach; endif; ?>
    </div>

    <div class="m2-emoji hidden" id="m2EmojiPanel" aria-label="Emojis">
      <?php foreach ($emojis as $e): ?><button type="button" data-emoji="<?= v2_h($e) ?>"><?= $e ?></button><?php endforeach; ?>
    </div>

    <div class="m2-reply-preview hidden" id="m2ReplyPreview">
      <div><span>Responder</span><strong id="m2ReplyPreviewSender">Mensagem</strong><p id="m2ReplyPreviewText"></p></div>
      <button type="button" data-action="cancelar-resposta" aria-label="Cancelar resposta"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <form class="m2-composer" id="m2Composer" method="post" onsubmit="return false;">
      <button type="button" data-action="emoji" aria-label="Emoji" title="Emoji"><i class="fa-regular fa-face-smile"></i></button>
      <button type="button" data-action="sugestao" aria-label="Sugestão da Irene" title="Usar a sugestão da Irene"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
      <button type="button" disabled aria-label="Anexar" title="Anexar entra quando o envio for ligado"><i class="fa-solid fa-paperclip"></i></button>
      <textarea id="m2Message" name="message" rows="1" placeholder="<?= $cliente ? 'Escreva a resposta para ' . v2_h($cliente['nome'] ?: 'o cliente') . '...' : 'Escolha uma conversa na lista...' ?>"<?= $cliente ? '' : ' disabled' ?>></textarea>
      <button type="button" data-action="ouvir" aria-label="Ouvir no tom da Irene" title="Ouvir no tom da Irene"><i class="fa-solid fa-volume-high"></i></button>
      <button type="submit" disabled aria-label="Enviar" title="O envio será ligado só depois da sua validação"><i class="fa-solid fa-paper-plane"></i></button>
    </form>
    <div class="m2-notice"><i class="fa-solid fa-lock"></i> modo validação: nada é enviado ao cliente — o rascunho fica só neste navegador.</div>

    <div class="m2-drawer hidden" id="m2ToolsPanel">
      <div class="m2-drawer-head">
        <strong>Painel da conversa</strong>
        <button type="button" data-action="fechar-painel" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="m2-drawer-body">
        <?php if ($cliente): ?>
          <section class="m2-card">
            <div class="m2-card-head"><h3>Resumo</h3><span><?= v2_h((string)($cliente['modo_atendimento'] ?? 'human')) === 'bot' ? 'IA ativa' : 'humano' ?></span></div>
            <div class="m2-metrics">
              <span><strong><?= (int)($cliente['total'] ?? count($msgs)) ?></strong><small>Msgs</small></span>
              <span><strong><?= (int)($cliente['recebidas'] ?? 0) ?></strong><small>Recebidas</small></span>
              <span><strong><?= (int)($cliente['enviadas'] ?? 0) ?></strong><small>Enviadas</small></span>
            </div>
            <div class="m2-tool-grid">
              <a href="index.php?page=cliente&amp;id=<?= urlencode((string)$cliente['id']) ?>">Ficha do cliente</a>
            </div>
          </section>

          <section class="m2-card">
            <div class="m2-card-head"><h3>Rascunho e voz</h3><span id="ireneAviso">fica neste navegador</span></div>
            <form class="m2-form">
              <label>Tom da voz
                <select id="ireneTom">
                  <option value="calor_rapido" selected>com calor · ritmo do estúdio</option>
                  <option value="calor">com calor (mais devagar)</option>
                  <option value="animada">animada</option>
                  <option value="serena">serena</option>
                  <option value="">padrão</option>
                </select>
              </label>
            </form>
            <div class="m2-tool-grid">
              <button type="button" data-action="sugestao">Sugestão da Irene</button>
              <button type="button" data-action="ouvir">Ouvir no tom</button>
              <button type="button" data-action="salvar">Salvar rascunho</button>
              <button type="button" data-action="limpar">Limpar</button>
            </div>
            <?php if ($sugestao): ?><small class="m2-range-help">sugestão: <?= v2_h($sugestao['motivo']) ?></small><?php endif; ?>
            <?php if ($ultimaEraAudio): ?><small class="m2-range-help">a última mensagem do cliente foi um áudio.</small><?php endif; ?>
            <audio id="ireneAudio" preload="none" style="display:none"></audio>
          </section>
        <?php else: ?>
          <section class="m2-card"><p>Escolha uma conversa na lista para ver o resumo.</p></section>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<script>
var VOZ = <?= json_encode(v2_voz_atual(), JSON_UNESCAPED_UNICODE) ?>;
var IRENE_ID = <?= json_encode((string)$id) ?>;
var IRENE_SUGESTAO = <?= json_encode((string)($sugestao['texto'] ?? '')) ?>;
(function () {
  var shell = document.getElementById('m2Shell');
  if (!shell) { return; }
  var ta = document.getElementById('m2Message');
  var av = document.getElementById('ireneAviso');
  var audio = document.getElementById('ireneAudio');
  var painelEmoji = document.getElementById('m2EmojiPanel');
  var barraResposta = document.getElementById('m2ReplyPreview');
  var painel = document.getElementById('m2ToolsPanel');
  var tom = document.getElementById('ireneTom');
  var chave = 'irene_rascunho_' + IRENE_ID;
  var citado = '';

  function aviso(t) { if (av) { av.textContent = t; } }

  if (ta) {
    try {
      var salvo = window.localStorage.getItem(chave);
      if (salvo) { ta.value = salvo; aviso('rascunho recuperado'); }
    } catch (e) {}
    ta.addEventListener('input', function () {
      ta.style.height = 'auto';
      ta.style.height = Math.min(ta.scrollHeight, 150) + 'px';
      try { window.localStorage.setItem(chave, ta.value); } catch (e) {}
    });
  }

  window.ireneSalvar = function () {
    if (!ta) { return; }
    try { window.localStorage.setItem(chave, ta.value); aviso('rascunho salvo neste navegador'); }
    catch (e) { aviso('não consegui salvar o rascunho'); }
  };

  window.ireneLimpar = function () {
    if (!ta) { return; }
    ta.value = '';
    ta.style.height = 'auto';
    cancelarResposta();
    try { window.localStorage.removeItem(chave); } catch (e) {}
    aviso('texto limpo');
  };

  window.ireneSugestao = function () {
    if (!ta || !IRENE_SUGESTAO) { aviso('sem sugestão para esta conversa'); return; }
    ta.value = IRENE_SUGESTAO;
    ta.dispatchEvent(new Event('input'));
    aviso('sugestão da Irene no rascunho');
  };

  window.ireneOuvir = function () {
    if (!ta || !audio) { return; }
    var t = (ta.value || '').trim();
    if (!t) { aviso('escreva algo antes de ouvir'); return; }
    audio.style.display = 'block';
    aviso('gerando áudio...');
    audio.src = 'api/tts.php?engine=' + encodeURIComponent(VOZ.engine)
      + '&voice=' + encodeURIComponent(VOZ.voz)
      + '&v=' + encodeURIComponent(tom && tom.value ? tom.value : VOZ.variacao)
      + '&vel=' + encodeURIComponent(VOZ.vel)
      + '&tom=' + encodeURIComponent(VOZ.tom)
      + '&text=' + encodeURIComponent(t) + '&t=' + Date.now();
    audio.onerror = function () { aviso('não consegui gerar o áudio agora'); };
    audio.onloadeddata = function () { aviso('áudio pronto · nada foi enviado'); };
    var p = audio.play();
    if (p && p.catch) { p.catch(function () {}); }
  };

  function inserirEmoji(e) {
    if (!ta || !e) { return; }
    var i = (typeof ta.selectionStart === 'number') ? ta.selectionStart : ta.value.length;
    var f = (typeof ta.selectionEnd === 'number') ? ta.selectionEnd : ta.value.length;
    ta.value = ta.value.slice(0, i) + e + ta.value.slice(f);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = i + e.length;
    ta.dispatchEvent(new Event('input'));
  }

  function limparCitacao() {
    if (citado && ta && ta.value.indexOf(citado) === 0) {
      ta.value = ta.value.slice(citado.length);
      ta.dispatchEvent(new Event('input'));
    }
    citado = '';
    if (barraResposta) { barraResposta.classList.add('hidden'); }
  }

  function responder(previa, quem) {
    if (!ta || !previa) { return; }
    limparCitacao();
    var texto = previa.replace(/\s+/g, ' ').slice(0, 160);
    citado = '> ' + texto + '\n';
    ta.value = citado + ta.value;
    ta.dispatchEvent(new Event('input'));
    if (barraResposta) {
      var alvo = document.getElementById('m2ReplyPreviewText');
      var dono = document.getElementById('m2ReplyPreviewSender');
      if (dono) { dono.textContent = quem || 'Mensagem'; }
      if (alvo) { alvo.textContent = texto; }
      barraResposta.classList.remove('hidden');
    }
  }

  var cancelarResposta = limparCitacao;
  function fecharPainel() { if (painel) { painel.classList.add('hidden'); } }
  function abrirPainel() { if (painel) { painel.classList.remove('hidden'); } }

  shell.addEventListener('click', function (ev) {
    var origem = ev.target;
    if (!origem || !origem.closest) { return; }

    var botaoEmoji = origem.closest('[data-emoji]');
    if (botaoEmoji) { inserirEmoji(botaoEmoji.getAttribute('data-emoji') || ''); return; }

    var botaoResponder = origem.closest('.m2-reply-action');
    if (botaoResponder) {
      var artigo = botaoResponder.closest('.m2-msg');
      if (artigo) { responder(artigo.getAttribute('data-message-preview') || '', artigo.getAttribute('data-message-sender') || ''); }
      return;
    }

    var alvo = origem.closest('[data-action]');
    if (!alvo) { return; }
    var acao = alvo.getAttribute('data-action');
    if (acao === 'emoji') { if (painelEmoji) { painelEmoji.classList.toggle('hidden'); } }
    else if (acao === 'sugestao') { window.ireneSugestao(); fecharPainel(); }
    else if (acao === 'ouvir') { window.ireneOuvir(); }
    else if (acao === 'salvar') { window.ireneSalvar(); }
    else if (acao === 'limpar') { window.ireneLimpar(); }
    else if (acao === 'painel') { abrirPainel(); }
    else if (acao === 'fechar-painel') { fecharPainel(); }
    else if (acao === 'cancelar-resposta') { cancelarResposta(); }
  });

  document.addEventListener('click', function (ev) {
    if (!painelEmoji || painelEmoji.classList.contains('hidden')) { return; }
    if (!ev.target || !ev.target.closest) { return; }
    if (ev.target.closest('#m2EmojiPanel')) { return; }
    if (ev.target.closest('[data-action="emoji"]')) { return; }
    painelEmoji.classList.add('hidden');
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') { return; }
    if (painelEmoji) { painelEmoji.classList.add('hidden'); }
    fecharPainel();
  });

  var campoBusca = document.getElementById('m2Search');
  function filtrarLista() {
    if (!campoBusca) { return; }
    var termo = campoBusca.value.trim().toLowerCase();
    var itens = document.querySelectorAll('#m2Items .m2-item');
    for (var i = 0; i < itens.length; i++) {
      var texto = itens[i].getAttribute('data-search') || '';
      itens[i].style.display = (!termo || texto.indexOf(termo) !== -1) ? '' : 'none';
    }
  }
  if (campoBusca) { campoBusca.addEventListener('input', filtrarLista); }

  var INTERVALO = 5000;
  var versaoAtual = null;
  var ocupado = false;

  function trocarLista(doc) {
    var atual = document.getElementById('m2Items');
    var nova = doc.getElementById('m2Items');
    if (!atual || !nova) { return; }
    var rolagem = atual.scrollTop;
    atual.innerHTML = nova.innerHTML;
    atual.scrollTop = rolagem;
    filtrarLista();
  }

  function trocarConversa(doc) {
    var atualThread = document.getElementById('m2Messages');
    var novaThread = doc.getElementById('m2Messages');
    if (atualThread && novaThread) {
      var noFim = (atualThread.scrollHeight - atualThread.scrollTop - atualThread.clientHeight) < 90;
      var topo = atualThread.scrollTop;
      atualThread.innerHTML = novaThread.innerHTML;
      atualThread.scrollTop = noFim ? atualThread.scrollHeight : topo;
    }
    var atualHead = document.querySelector('.m2-chat-head');
    var novoHead = doc.querySelector('.m2-chat-head');
    if (atualHead && novoHead) { atualHead.innerHTML = novoHead.innerHTML; }
  }

  function recarregar() {
    return fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : null; })
      .then(function (html) {
        if (!html) { return; }
        var doc = new DOMParser().parseFromString(html, 'text/html');
        trocarLista(doc);
        trocarConversa(doc);
      })
      .catch(function () {});
  }

  function tick() {
    if (ocupado || document.hidden) { return; }
    ocupado = true;
    fetch('api/conversa_feed.php', { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.ok) { return; }
        if (versaoAtual === null) { versaoAtual = j.versao; return; }
        if (j.versao !== versaoAtual) { versaoAtual = j.versao; return recarregar(); }
      })
      .catch(function () {})
      .then(function () { ocupado = false; });
  }

  setInterval(tick, INTERVALO);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { tick(); } });

  var thread = document.getElementById('m2Messages');
  if (thread) { thread.scrollTop = thread.scrollHeight; }
})();
</script>

<?php endif; ?>
<?php v2_layout_bottom(); ?>