<?php
/**
 * Simulador de atendimento: o Daniel faz o papel do cliente e a Irene atende.
 * Nada sai para cliente: a conversa vive so nesta tela.
 */
$engine = (string)(v2_config()['tts_engine'] ?? 'windows');
$lmModelos = v2_lmstudio_modelos();
$lmLigado = count($lmModelos) > 0;
$cerebroLigado = (bool)v2_cerebro()['ligado'];

$cenarios = [
    '1',
    '2',
    'Oi! Quanto fica uma tatuagem no antebraço?',
    'Vocês ficam onde? Consigo chegar de metrô?',
    'Consigo fazer sábado?',
    'Tenho medo da dor 😖',
    'Vou pensar e te falo depois',
    'Quero fechar! Como faço pra reservar?',
];

v2_layout_top('simulador', 'Simulador');
?>
<div class="head">
  <div class="kick">teste de atendimento</div>
  <h2>Você é o cliente. A Irene atende.</h2>
  <p>Escreva como se fosse um cliente de verdade e veja como ela responde — texto, tom e o áudio na voz escolhida. <b>Nada disso chega a nenhum cliente.</b> Toda conversa nova começa com a mensagem padrão do estúdio (a Irene se apresenta e oferece 1 ou 2).</p>
</div>

<div class="card pad" style="margin-bottom:14px;display:flex;flex-wrap:wrap;gap:9px;align-items:center">
  <span class="badge <?= $lmLigado ? 'b-green' : 'b-amber' ?>">
    <?php if ($lmLigado): ?>🧠 LM Studio ligado · <?= count($lmModelos) ?> modelo<?= count($lmModelos) > 1 ? 's' : '' ?>
    <?php else: ?>⚠️ LM Studio desligado — usando o playbook do estúdio<?php endif; ?>
  </span>
  <span class="badge b-gray">🎤 voz: <?= v2_h($engine) ?> · ritmo do estúdio</span>
  <?php if ($cerebroLigado): ?><span class="badge b-violet">🧠 meu cérebro (Codex) na lista</span><?php endif; ?>
  <label class="fsep" for="simModelo" style="margin-left:8px">cérebro</label>
  <select class="fsel" id="simModelo">
    <?php foreach (v2_irene_modelos() as $k => $rotulo): ?>
      <option value="<?= v2_h($k) ?>"<?= strpos($rotulo, '· padrão') !== false ? ' selected' : '' ?>><?= v2_h($rotulo) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="badge b-gray">🔒 simulação · nada é enviado</span>
  <button class="fchip" type="button" id="simLimpar" style="margin-left:auto">↺ reiniciar conversa</button>
</div>

<div class="card thread" style="max-height:min(62vh,620px)">
  <div class="thead">
    <span class="cavi">IR</span>
    <div style="flex:1;min-width:0">
      <h3>Irene · estúdio do Daniel</h3>
      <div class="ph" id="simMotor">esperando o primeiro "oi" do cliente</div>
    </div>
  </div>
  <div class="tbody" id="simBody">
    <div class="note n-gray">Escreva a primeira mensagem como se você fosse o cliente.</div>
  </div>
  <div class="composer">
    <div class="crow" id="simCenarios">
      <?php foreach ($cenarios as $c): ?>
        <button class="fchip" type="button" data-cena="<?= v2_h($c) ?>"><?= v2_h($c) ?></button>
      <?php endforeach; ?>
    </div>
    <textarea id="simTexto" placeholder="Digite como cliente... (Enter quebra linha; envie pelo botão)"></textarea>
    <div class="crow">
      <button class="btn btn-a" type="button" id="simEnviar">📤 Enviar como cliente</button>
      <span class="badge b-gray" id="simAviso">a resposta fica só nesta tela</span>
    </div>
    <audio id="simAudio" class="audio" controls preload="none" style="display:none"></audio>
  </div>
</div>

<script>
(function () {
  var corpo = document.getElementById('simBody');
  var campo = document.getElementById('simTexto');
  var aviso = document.getElementById('simAviso');
  var motor = document.getElementById('simMotor');
  var audio = document.getElementById('simAudio');
  var historico = [];
  var ocupado = false;

  function vazio() {
    corpo.innerHTML = '<div class="note n-gray">Escreva a primeira mensagem como se você fosse o cliente.</div>';
  }

  function bolha(papel, texto, info) {
    var d = document.createElement('div');
    d.className = 'bub ' + (papel === 'irene' ? 'out' : 'in');
    var t = document.createElement('div');
    t.className = 'mtxt';
    t.textContent = texto;
    d.appendChild(t);
    if (papel === 'irene' && info && info.audio) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'ouvir';
      b.textContent = '🔊 ouvir';
      b.addEventListener('click', function () {
        audio.style.display = 'block';
        audio.src = info.audio;
        var p = audio.play();
        if (p && p.catch) { p.catch(function () {}); }
      });
      d.appendChild(b);
    }
    var h = document.createElement('span');
    h.className = 't';
    h.textContent = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
      + (papel === 'irene' ? ' · Irene' : ' · você (cliente)');
    d.appendChild(h);
    return d;
  }

  function render() {
    corpo.innerHTML = '';
    if (!historico.length) { vazio(); return; }
    for (var i = 0; i < historico.length; i++) {
      var m = historico[i];
      corpo.appendChild(bolha(m.papel, m.texto, m));
    }
    corpo.scrollTop = corpo.scrollHeight;
  }

  function digitando() {
    var d = document.createElement('div');
    d.className = 'dig';
    d.id = 'simDig';
    d.innerHTML = '<span></span><span></span><span></span>';
    corpo.appendChild(d);
    corpo.scrollTop = corpo.scrollHeight;
  }

  function tirarDigitando() {
    var d = document.getElementById('simDig');
    if (d) { d.remove(); }
  }

  async function enviar() {
    var texto = (campo.value || '').trim();
    if (!texto || ocupado) { return; }
    ocupado = true;
    campo.value = '';
    historico.push({ papel: 'cliente', texto: texto });
    render();
    tirarDigitando();
    digitando();
    aviso.textContent = 'a Irene está pensando...';

    try {
      var r = await fetch('api/irene.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ historico: historico, modelo: (document.getElementById('simModelo') || {}).value || '' })
      });
      var d = await r.json();
      tirarDigitando();
      if (!d.ok) { throw new Error(d.erro || 'falha na resposta'); }
      historico.push({ papel: 'irene', texto: d.resposta, audio: d.audio });
      var estado = d.estado === 'humano' ? ' · ⏳ aguardando a Hellen/Daniel'
        : (d.estado === 'menu' ? ' · 🖐 abertura' : '');
      motor.textContent = 'motor: ' + d.motor + estado + ' · ' + historico.length + ' mensagens no histórico';
      if (d.aviso) { aviso.textContent = '⚠️ ' + d.aviso; }
      if (!d.aviso) { aviso.textContent = 'resposta gerada · nada foi enviado'; }
    } catch (e) {
      tirarDigitando();
      aviso.textContent = 'erro: ' + e.message;
    }
    ocupado = false;
    render();
  }

  document.getElementById('simEnviar').addEventListener('click', enviar);
  campo.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); campo.value += '\n'; }
  });
  var cenas = document.querySelectorAll('#simCenarios [data-cena]');
  for (var i = 0; i < cenas.length; i++) {
    cenas[i].addEventListener('click', function () {
      campo.value = this.getAttribute('data-cena');
      campo.focus();
    });
  }
  document.getElementById('simLimpar').addEventListener('click', function () {
    historico = [];
    motor.textContent = 'esperando o primeiro "oi" do cliente';
    aviso.textContent = 'a resposta fica só nesta tela';
    audio.style.display = 'none';
    render();
  });
})();
</script>
<?php v2_layout_bottom(); ?>
