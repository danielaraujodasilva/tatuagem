<section class="m2-chat" id="m2ChatPanel" data-conversation-id="<?= v2_h($cliente ? (string)$id : '') ?>" data-suggestion="<?= v2_h((string)($sugestao['texto'] ?? '')) ?>">
    <header class="m2-chat-head">
      <?php if ($cliente): ?>
        <a class="m2-icon" href="<?= v2_h(v2_url(['id' => '0'])) ?>" data-conversa-id="0" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <span class="m2-avatar"><?= v2_h(v2_conversa_avatar((string)$cliente['nome'])) ?></span>
        <span class="m2-title">
          <strong><?= v2_h($cliente['nome'] ?: 'Cliente') ?></strong>
          <small><?= v2_h((string)$cliente['numero']) ?> · <?= v2_h(v2_status_label((string)$cliente['status'])) ?> · <?= v2_h((string)($cliente['modo_atendimento'] ?? 'human')) ?> · <?= (int)($cliente['valor'] ?? 0) > 0 ? v2_money($cliente['valor']) : 'sem valor' ?></small>
        </span>
        <button class="m2-icon" type="button" data-action="nao-lida" aria-label="Marcar como não lida" title="Marcar como não lida"><i class="fa-regular fa-envelope"></i></button>
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
