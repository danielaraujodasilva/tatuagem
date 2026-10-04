<?php
$pdo = v2_try(static fn() => v2_crm(), null);
$id = (string)($_GET['id'] ?? '');
$temChat = $pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_mensagens');

$cliente = null;
$msgs = [];
if ($temChat && $id !== '') {
    $stmt = $pdo->prepare('SELECT * FROM crm_whatsapp_clientes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare('SELECT de, texto, data, from_me, tipo, transcricao FROM crm_whatsapp_mensagens WHERE cliente_id = ? ORDER BY data ASC, id ASC LIMIT 200');
    $stmt->execute([$id]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$ultimaEntrada = null;
foreach ($msgs as $m) {
    if ((int)$m['from_me'] === 0) { $ultimaEntrada = $m; }
}
$ultimaEraAudio = $ultimaEntrada && (string)($ultimaEntrada['tipo'] ?? 'texto') === 'audio';

v2_layout_top('conversa', 'Conversa');
?>
<div class="head">
  <div class="kick">a regra do áudio</div>
  <h2>Responde em áudio só quando recebe áudio</h2>
  <p>Se o cliente escreve, a Irene escreve. Se o cliente manda áudio, ela responde em áudio.</p>
</div>

<?php if (!$temChat || !$cliente): ?>
  <div class="card pad"><div class="note n-amber">Selecione uma conversa na tela <a href="index.php?page=hoje">Hoje</a>. <?= $temChat ? '' : 'A tabela de mensagens não está disponível neste ambiente.' ?></div></div>
<?php else: ?>
<div class="grid g23" style="align-items:start">
  <div class="card pad">
    <p class="sect">💬 <?= v2_h($cliente['nome'] ?: 'Cliente') ?> · <?= v2_h($cliente['numero']) ?></p>
    <div class="chatwrap">
      <div class="cbody">
        <?php foreach ($msgs as $m): $out = (int)$m['from_me'] === 1; ?>
          <div class="bub <?= $out ? 'out' : 'in' ?>">
            <?php if ((string)($m['tipo'] ?? 'texto') === 'audio'): ?>
              🎙 <?= v2_h($m['transcricao'] ?: 'áudio') ?>
            <?php else: ?>
              <?= nl2br(v2_h((string)$m['texto'])) ?>
            <?php endif; ?>
            <span class="t"><?= v2_h(substr((string)$m['data'], 11, 5)) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$msgs): ?><div class="note n-gray">Sem mensagens registradas.</div><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="grid" style="align-content:start">
    <div class="card pad">
      <p class="sect">⚙️ Decisão da Irene</p>
      <?php if ($ultimaEraAudio): ?>
        <div class="note n-green" style="margin-bottom:10px">A última mensagem do cliente foi <b>áudio</b> → a Irene responde <b>em áudio</b>.</div>
        <audio class="audio" controls preload="none" src="api/tts.php?engine=<?= v2_h(v2_config()['tts_engine']) ?>&amp;text=<?= urlencode('Oi! Recebi seu audio e ja vou te responder. Consigo sim, e ja separei um horario pra voce.') ?>"></audio>
        <p style="color:var(--muted);font-size:.76rem;margin:8px 0 0">Áudio gerado agora pelo motor configurado.</p>
      <?php else: ?>
        <div class="note n-gray">A última mensagem foi <b>texto</b> → a Irene responde <b>em texto</b>. Nada de áudio sem motivo.</div>
      <?php endif; ?>
    </div>
    <div class="card pad">
      <p class="sect">📝 Responder</p>
      <textarea class="audio" style="height:80px;padding:10px;border:1px solid var(--line);border-radius:10px" placeholder="Escreva a resposta..."></textarea>
      <div style="margin-top:10px"><span class="badge b-gray">envio entra na próxima fase (precisa do Baileys)</span></div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php v2_layout_bottom(); ?>
