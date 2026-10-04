<?php
$cfg = v2_config();
$atual = (string)($cfg['tts_engine'] ?? 'windows');

$engines = [
    'kokoro' => ['nome' => 'Kokoro-82M (neural, offline)', 'desc' => 'A mais natural que roda 100% offline na CPU. Pausa entre frases.', 'tags' => [['mais natural offline', 'best'], ['offline', 'ok'], ['grátis', 'ok'], ['feminina', 'ok']], 'demo' => 'irene-kokoro-calor'],
    'edge' => ['nome' => 'Edge neural (online)', 'desc' => 'Voz da Microsoft, sem chave e sem custo. A mais expressiva das quatro.', 'tags' => [['muito natural', 'best'], ['grátis', 'ok'], ['online', '']], 'demo' => 'irene-edge-calor'],
    'piper' => ['nome' => 'Piper (neural, offline)', 'desc' => 'Leve e rápido. Em pt-BR só existe voz masculina.', 'tags' => [['offline', 'ok'], ['grátis', 'ok'], ['masculina', '']], 'demo' => 'irene-piper-calor'],
    'windows' => ['nome' => 'Voz do Windows (nativa)', 'desc' => 'Já instalada, zero setup, qualidade média.', 'tags' => [['offline', 'ok'], ['grátis', 'ok'], ['instantânea', 'ok']], 'demo' => 'irene-windows-calor'],
];

$rotulos = ['natural' => 'como está hoje', 'calor' => 'com calor', 'animada' => 'animada', 'serena' => 'serena'];
$frase = 'Oi, Ricardo! Que bom que você gostou do leão. O fechamento de costas a gente faz numa sessão só, e eu já separei um domingo pra você. Me manda seu nome completo que eu reservo.';

$link = static function (string $engine, string $variacao, string $frase): string {
    $url = 'api/tts.php?engine=' . urlencode($engine) . '&text=' . urlencode($frase);
    if ($variacao !== 'natural') {
        $url .= '&v=' . urlencode($variacao);
    }
    return $url;
};

$demo = static function (string $arquivo): ?string {
    $caminho = __DIR__ . '/../assets/voz/' . $arquivo . '.mp3';
    return is_file($caminho) ? 'assets/voz/' . $arquivo . '.mp3' : null;
};

$algumMotor = false;
foreach (array_keys($engines) as $e) {
    if (v2_tts_available($e)) { $algumMotor = true; }
}
$atualDisponivel = v2_tts_available($atual);

v2_layout_top('voz', 'Voz');
?>
<div class="head">
  <div class="kick">a voz (e o teste)</div>
  <h2>Mesma voz, sentimentos diferentes</h2>
  <p>O que faltava não era o robô — era a prosódia. Mesma frase, mesma voz, quatro ritmos e tons.</p>
</div>

<?php if (!$algumMotor): ?>
  <div class="note n-amber" style="margin-bottom:14px">
    ⚠️ <b>Este servidor não tem nenhum motor de voz instalado.</b> Os áudios abaixo são as amostras que eu já gerei na sua máquina — dá para ouvir e comparar normalmente. A geração ao vivo precisa de um motor rodando no servidor (precisa de acesso SSH/cPanel).
  </div>
<?php endif; ?>

<div class="card pad" style="margin-bottom:14px">
  <p class="sect">🎚 O ajuste que faltava (amostras reais)</p>
  <p style="color:var(--muted);font-size:.85rem;margin:0 0 12px">&ldquo;<?= v2_h($frase) ?>&rdquo;</p>
  <div class="grid g2">
    <div class="vcard">
      <div class="vt"><div class="ic">🎚</div><div><h4>Como estava</h4><p>Ritmo e tom padrão do motor. É o que você ouviu e não gostou.</p></div></div>
      <audio class="audio" controls preload="none" src="assets/voz/voz-edge-natural.mp3"></audio>
    </div>
    <div class="vcard" style="border-color:var(--brand)">
      <div class="vt"><div class="ic">✨</div><div><h4>Com calor</h4><p>9% mais devagar, tom 3Hz mais baixo. É a que eu usaria com cliente.</p></div></div>
      <div class="tags"><span class="tag best">recomendada</span><span class="tag ok">-9% ritmo</span><span class="tag ok">-3Hz tom</span></div>
      <audio class="audio" controls preload="none" src="assets/voz/voz-edge-calor.mp3"></audio>
    </div>
    <div class="vcard">
      <div class="vt"><div class="ic">🌙</div><div><h4>Serena</h4><p>Para preço alto, cliente nervoso ou notícia ruim.</p></div></div>
      <audio class="audio" controls preload="none" src="assets/voz/voz-edge-serena.mp3"></audio>
    </div>
    <div class="vcard">
      <div class="vt"><div class="ic">🔥</div><div><h4>Animada</h4><p>Para promoção, vaga abrindo, &ldquo;bora fechar&rdquo;.</p></div></div>
      <audio class="audio" controls preload="none" src="assets/voz/voz-edge-animada.mp3"></audio>
    </div>
  </div>
</div>

<div class="grid g2">
  <?php foreach ($engines as $key => $info): $disp = v2_tts_available($key); $arq = $disp ? null : $demo($info['demo']); ?>
    <div class="vcard" style="border-color:<?= $key === $atual ? 'var(--brand)' : 'var(--line)' ?>">
      <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
        <h4><?= v2_h($info['nome']) ?></h4>
        <?php if ($key === $atual): ?><span class="tag best">preferida</span><?php endif; ?>
        <span class="tag <?= $disp ? 'ok' : '' ?>"><?= $disp ? 'gerando ao vivo' : 'amostra' ?></span>
      </div>
      <p><?= v2_h($info['desc']) ?></p>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($info['tags'] as [$t, $c]): ?><span class="tag <?= $c ?>"><?= v2_h($t) ?></span><?php endforeach; ?>
      </div>

      <?php if ($disp): ?>
        <?php foreach (array_merge(['natural'], v2_tts_variacoes($key)) as $variacao): ?>
          <div>
            <span class="tag"><?= v2_h($rotulos[$variacao] ?? $variacao) ?></span>
            <audio class="audio" controls preload="none" src="<?= v2_h($link($key, $variacao, $frase)) ?>"></audio>
          </div>
        <?php endforeach; ?>
      <?php elseif ($arq): ?>
        <audio class="audio" controls preload="none" src="<?= v2_h($arq) ?>"></audio>
        <div style="color:var(--muted);font-size:.76rem">amostra &ldquo;com calor&rdquo; gerada na máquina de origem</div>
      <?php else: ?>
        <div class="note n-gray">Configure o motor em <code>config.local.php</code> para habilitar.</div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="card pad" style="margin-top:14px">
  <p class="sect">⚙️ Comportamento da Irene</p>
  <div class="note n-green"><b>Responder em áudio só quando recebe áudio.</b> Você escreveu → escrevo. Você mandou áudio → respondo em áudio.</div>
  <?php if ($atualDisponivel): ?>
    <div class="note n-amber" style="margin-top:10px">Motor em uso neste servidor: <code><?= v2_h($atual) ?></code>. Troque em <code>config.local.php</code>.</div>
  <?php else: ?>
    <div class="note n-amber" style="margin-top:10px">Motor preferido: <code><?= v2_h($atual) ?></code> — indisponível neste servidor. Trocas de prosódia ficam em cache próprio, então dá para comparar quantas vezes quiser.</div>
  <?php endif; ?>
  <div class="note n-violet" style="margin-top:10px">🎭 Voz clonada (a sua ou a da Ellen): XTTS/Chatterbox exigem GPU — é o próximo degrau.</div>
</div>
<?php v2_layout_bottom(); ?>
