<?php
/**
 * Tela Voz: uma voz só na tela. Daniel escolhe motor, voz, sentimento, ritmo e
 * tom, ouve aqui mesmo e salva. O que fica salvo aqui é o que o sistema passa a
 * usar (conversa, simulador e áudio automático). Nada disso fala com cliente.
 */
$atual = v2_voz_atual();

$motores = [
    'kokoro' => ['nome' => 'Kokoro-82M', 'desc' => 'Neural e 100% offline. A que soa mais natural.', 'tag' => 'offline'],
    'edge' => ['nome' => 'Edge neural', 'desc' => 'Voz da Microsoft: a mais expressiva e a única com várias vozes em pt-BR.', 'tag' => 'online e grátis'],
    'piper' => ['nome' => 'Piper', 'desc' => 'Neural offline bem leve. Em pt-BR só existe voz masculina.', 'tag' => 'offline'],
    'windows' => ['nome' => 'Voz do Windows', 'desc' => 'Já vem no sistema. Qualidade média, mas instantânea.', 'tag' => 'offline'],
];

$rotulos = [
    'natural' => 'Natural — sem emoção (padrão do motor)',
    'calor' => 'Com calor — fala mais aconchegante',
    'calor_rapido' => 'Com calor, no ritmo do estúdio (a que uso hoje)',
    'animada' => 'Animada — promoção, vaga abrindo',
    'serena' => 'Serena — preço alto, cliente nervoso',
];

$vozes = [];
$variacoes = [];
$disponivel = [];
foreach (array_keys($motores) as $e) {
    $disponivel[$e] = v2_tts_available($e);
    $vozes[$e] = v2_voz_opcoes()[$e] ?? [];
    $variacoes[$e] = array_merge(['natural'], v2_tts_variacoes($e));
}
$arqPiper = (string)(v2_config()['piper_voice'] ?? '');
$vozes['piper'] = $arqPiper !== '' && is_file($arqPiper) ? [$arqPiper => basename($arqPiper) . ' · masculina'] : [];

$enginePadrao = $atual['engine'];
if (empty($disponivel[$enginePadrao])) {
    foreach ($disponivel as $e => $ok) {
        if ($ok) { $enginePadrao = $e; break; }
    }
}

$frase = 'Oi, Ricardo! Que bom que você gostou do leão. O fechamento de costas a gente faz numa sessão só, e eu já separei um domingo pra você. Me manda seu nome completo que eu reservo.';

$boot = [
    'vozes' => $vozes,
    'variacoes' => $variacoes,
    'rotulos' => $rotulos,
    'disponivel' => $disponivel,
    'engine' => $enginePadrao,
    'voz' => $atual['voz'],
    'variacao' => $atual['variacao'] !== '' ? $atual['variacao'] : 'natural',
    'vel' => (int)$atual['vel'],
    'tom' => (int)$atual['tom'],
    'salva' => (bool)$atual['salva'],
];

v2_layout_top('voz', 'Voz');
?>
<div class="head">
  <div class="kick">a voz do estúdio</div>
  <h2>Uma voz, do jeito que você quiser</h2>
  <p>Escolhe o motor, a voz, o sentimento e o ritmo, ouve aqui mesmo e salva. É essa combinação que o sistema passa a usar pra falar com os clientes.</p>
</div>

<div class="card pad" style="margin-bottom:14px">
  <div class="crow">
    <label class="fsep" for="vozMotor">motor</label>
    <select class="fsel" id="vozMotor">
      <?php foreach ($motores as $e => $info): ?>
        <option value="<?= v2_h($e) ?>"<?= $e === $enginePadrao ? ' selected' : '' ?><?= $disponivel[$e] ? '' : ' disabled' ?>>
          <?= v2_h($info['nome']) ?><?= $disponivel[$e] ? '' : ' — indisponível aqui' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label class="fsep" for="vozNome">voz</label>
    <select class="fsel" id="vozNome"></select>
    <label class="fsep" for="vozSentimento">sentimento</label>
    <select class="fsel" id="vozSentimento"></select>
  </div>

  <div class="crow" style="margin-top:14px">
    <label class="fsep" for="vozVel">ritmo <b id="vozVelVal">+0%</b></label>
    <input type="range" id="vozVel" min="-30" max="50" step="5" value="0" style="flex:1;min-width:170px">
    <label class="fsep" for="vozTom" id="vozTomWrap">tom <b id="vozTomVal">+0Hz</b></label>
    <input type="range" id="vozTom" min="-10" max="10" step="1" value="0" style="flex:1;min-width:140px">
  </div>
  <div style="color:var(--muted);font-size:.76rem;margin-top:6px">O ritmo soma sobre o sentimento escolhido (calor +30% = o ritmo que uso hoje). O tom só vale no motor Edge.</div>

  <textarea id="vozTexto" style="width:100%;min-height:88px;margin-top:14px;resize:vertical;border:1px solid var(--line);border-radius:13px;padding:11px 12px;font:inherit;font-size:.86rem;color:var(--ink);background:#fff"><?= v2_h($frase) ?></textarea>

  <div class="crow" style="margin-top:12px">
    <button class="btn btn-a" type="button" id="vozOuvir">&#9654; Ouvir</button>
    <button class="btn btn-p" type="button" id="vozSalvar">&#128190; Salvar essa voz</button>
    <span class="badge b-gray" id="vozAviso">voz em uso: <?= v2_h($atual['engine']) ?></span>
  </div>

  <audio id="vozAudio" class="audio" controls preload="none" style="display:none;margin-top:14px"></audio>
</div>

<div class="card pad">
  <p class="sect">&#129504; Como o sistema usa isso</p>
  <div class="note n-green"><b>Falo só quando o cliente manda áudio.</b> Cliente escreveu, eu escrevo. Cliente mandou áudio, eu respondo em áudio — com a voz que estiver salva aqui.</div>
  <div class="note n-gray" style="margin-top:10px">Motores nesta máquina:
    <?php foreach ($motores as $e => $info): ?>
      <span class="badge <?= $disponivel[$e] ? 'b-green' : 'b-gray' ?>" style="margin-left:6px"><?= v2_h($info['nome']) ?></span>
    <?php endforeach; ?>
  </div>
</div>

<script>
var VOZ_BOOT = <?= json_encode($boot, JSON_UNESCAPED_UNICODE) ?>;
(function () {
  var motor = document.getElementById('vozMotor');
  var nome = document.getElementById('vozNome');
  var sentimento = document.getElementById('vozSentimento');
  var vel = document.getElementById('vozVel');
  var tom = document.getElementById('vozTom');
  var texto = document.getElementById('vozTexto');
  var aviso = document.getElementById('vozAviso');
  var audio = document.getElementById('vozAudio');
  var ouvir = document.getElementById('vozOuvir');
  var salvar = document.getElementById('vozSalvar');
  var tomWrap = document.getElementById('vozTomWrap');

  function sinal(n, sufixo) { return (n >= 0 ? '+' : '') + n + sufixo; }

  function preencherVozes() {
    var lista = VOZ_BOOT.vozes[motor.value] || {};
    nome.innerHTML = '';
    Object.keys(lista).forEach(function (valor) {
      var o = document.createElement('option');
      o.value = valor;
      o.textContent = lista[valor];
      if (valor === VOZ_BOOT.voz) { o.selected = true; }
      nome.appendChild(o);
    });
  }

  function preencherSentimentos() {
    var lista = VOZ_BOOT.variacoes[motor.value] || ['natural'];
    sentimento.innerHTML = '';
    lista.forEach(function (v) {
      var o = document.createElement('option');
      o.value = v;
      o.textContent = VOZ_BOOT.rotulos[v] || v;
      if (v === VOZ_BOOT.variacao) { o.selected = true; }
      sentimento.appendChild(o);
    });
    var soEdge = motor.value === 'edge';
    tomWrap.style.display = soEdge ? '' : 'none';
    tom.style.display = soEdge ? '' : 'none';
  }

  function mostrarRitmo() {
    document.getElementById('vozVelVal').textContent = sinal(parseInt(vel.value, 10) || 0, '%');
    document.getElementById('vozTomVal').textContent = sinal(parseInt(tom.value, 10) || 0, 'Hz');
  }

  function consulta() {
    return 'engine=' + encodeURIComponent(motor.value)
      + '&voice=' + encodeURIComponent(nome.value)
      + '&v=' + encodeURIComponent(sentimento.value)
      + '&vel=' + encodeURIComponent(vel.value)
      + '&tom=' + encodeURIComponent(tom.value)
      + '&text=' + encodeURIComponent((texto.value || '').trim());
  }

  motor.addEventListener('change', function () { preencherVozes(); preencherSentimentos(); });
  vel.addEventListener('input', mostrarRitmo);
  tom.addEventListener('input', mostrarRitmo);

  ouvir.addEventListener('click', async function () {
    if (!(texto.value || '').trim()) { aviso.textContent = 'escreve alguma frase antes de ouvir'; return; }
    ouvir.disabled = true;
    aviso.textContent = 'gerando o áudio... (a primeira vez de cada motor demora mais)';
    audio.style.display = 'none';
    try {
      var r = await fetch('api/tts.php?json=1&' + consulta() + '&t=' + Date.now());
      var d = await r.json();
      if (!d.ok) { throw new Error(d.erro || 'falha na geração'); }
      audio.src = d.url + '&t=' + Date.now();
      audio.style.display = 'block';
      aviso.textContent = 'pronto — dá o play pra ouvir';
      var p = audio.play();
      if (p && p.catch) { p.catch(function () {}); }
    } catch (e) {
      aviso.textContent = 'não consegui gerar o áudio: ' + e.message;
    }
    ouvir.disabled = false;
  });

  salvar.addEventListener('click', async function () {
    salvar.disabled = true;
    aviso.textContent = 'salvando...';
    try {
      var r = await fetch('api/voz.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'salvar',
          engine: motor.value,
          voz: nome.value,
          variacao: sentimento.value,
          vel: parseInt(vel.value, 10) || 0,
          tom: parseInt(tom.value, 10) || 0
        })
      });
      var d = await r.json();
      if (!d.ok) { throw new Error(d.erro || 'falha ao salvar'); }
      aviso.textContent = 'voz salva — é a que o sistema passa a usar';
      VOZ_BOOT.voz = d.voz.voz;
      VOZ_BOOT.variacao = d.voz.variacao || 'natural';
    } catch (e) {
      aviso.textContent = 'não consegui salvar: ' + e.message;
    }
    salvar.disabled = false;
  });

  motor.value = VOZ_BOOT.engine;
  vel.value = VOZ_BOOT.vel;
  tom.value = VOZ_BOOT.tom;
  preencherVozes();
  preencherSentimentos();
  mostrarRitmo();
  if (VOZ_BOOT.salva) { aviso.textContent = 'voz salva em uso: ' + VOZ_BOOT.engine; }
})();
</script>
<?php v2_layout_bottom(); ?>
