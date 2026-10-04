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
    'Oi! Quanto fica uma tatuagem no antebraço? Vocês ficam onde? Consigo ir sábado?',
    'Tenho medo da dor 😖',
    'Vou pensar e te falo depois',
    'Quero fechar! Como faço pra reservar?',
];

v2_layout_top('simulador', 'Simulador');
?>
<div class="head">
  <div class="kick">teste de atendimento</div>
  <h2>Você é o cliente. A Irene atende.</h2>
  <p>Escreva como um cliente de verdade — pode mandar várias mensagens seguidas, como no WhatsApp, que ela junta tudo e responde. Anexe foto, vídeo, documento ou mande áudio (transcrito na hora). <b>Nada disso chega a nenhum cliente.</b></p>
</div>

<div class="card pad" style="margin-bottom:14px;display:flex;flex-wrap:wrap;gap:9px;align-items:center">
  <span class="badge <?= $lmLigado ? 'b-green' : 'b-amber' ?>">
    <?php if ($lmLigado): ?>🧠 LM Studio ligado · <?= count($lmModelos) ?> modelo<?= count($lmModelos) > 1 ? 's' : '' ?>
    <?php else: ?>⚠️ LM Studio desligado — usando o playbook do estúdio<?php endif; ?>
  </span>
  <span class="badge b-gray">🎤 voz do estúdio salva em Voz</span>
  <?php if ($cerebroLigado): ?><span class="badge b-violet">🧠 meu cérebro (Codex) na lista</span><?php endif; ?>
  <label class="fsep" for="simModelo" style="margin-left:8px">cérebro</label>
  <select class="fsel" id="simModelo">
    <?php foreach (v2_irene_modelos() as $k => $rotulo): ?>
      <option value="<?= v2_h($k) ?>"<?= strpos($rotulo, '· padrão') !== false ? ' selected' : '' ?>><?= v2_h($rotulo) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="badge b-gray">🔒 simulação · nada é enviado</span>
  <span style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
    <button class="fchip" type="button" id="simSalvar">💾 salvar conversa</button>
    <button class="fchip" type="button" id="simVerSalvas">📂 conversas salvas (<span id="simSalvasN">0</span>)</button>
    <button class="fchip" type="button" id="simLimpar">↺ resetar conversa</button>
  </span>
</div>

<div class="card" id="simSalvasCard" style="display:none;margin-bottom:14px">
  <div class="pad" style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--line)">
    <b style="font-size:.92rem">Conversas salvas</b>
    <span class="badge b-gray">o que fica aqui é o que a Irene pode ir olhar depois</span>
    <span style="margin-left:auto;display:flex;gap:8px">
      <button class="fchip" type="button" id="simAtualizarSalvas">↻ atualizar</button>
      <button class="fchip" type="button" id="simFecharSalvas">✕ fechar</button>
    </span>
  </div>
  <div class="pad">
    <div id="simSalvasLista"><div class="note n-gray">nenhuma conversa salva ainda</div></div>
    <div id="simSalvasVer" style="display:none;margin-top:13px"></div>
  </div>
</div>

<div class="card thread" style="max-height:min(62vh,620px)">
  <div class="thead">
    <span class="cavi">IR</span>
    <div style="flex:1;min-width:0">
      <h3>Irene · estúdio do Daniel</h3>
      <div class="ph" id="simMotor">esperando o primeiro "oi" do cliente</div>
    </div>
    <span class="badge b-gray" id="simFila">fila vazia</span>
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
    <textarea id="simTexto" placeholder="Digite como cliente... (Enter quebra linha; envie pelo botão ou anexe abaixo)"></textarea>
    <div class="crow">
      <input type="file" id="simArquivo" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt" style="display:none">
      <button class="btn btn-g" type="button" id="simAnexarBtn">📎 anexar</button>
      <button class="btn btn-g" type="button" id="simGravar">🎤 gravar áudio</button>
      <span class="simrec" id="simRec" style="display:none"><span class="pt"></span>gravando...</span>
      <button class="btn btn-a" type="button" id="simEnviar" style="margin-left:auto">📤 Enviar como cliente</button>
    </div>
    <div class="crow">
      <span class="badge b-gray" id="simAviso">a resposta fica só nesta tela</span>
    </div>
    <audio id="simAudio" class="audio" controls preload="none" style="display:none"></audio>
  </div>
</div>

<script>
var VOZ = <?= json_encode(v2_voz_atual(), JSON_UNESCAPED_UNICODE) ?>;
(function () {
  var corpo = document.getElementById('simBody');
  var campo = document.getElementById('simTexto');
  var aviso = document.getElementById('simAviso');
  var motor = document.getElementById('simMotor');
  var filaBadge = document.getElementById('simFila');
  var audio = document.getElementById('simAudio');
  var arquivo = document.getElementById('simArquivo');
  var btnGravar = document.getElementById('simGravar');
  var recAviso = document.getElementById('simRec');

  var historico = [];
  var pendentes = 0;
  var enviando = false;
  var timer = null;
  var gravador = null;
  var pedacos = [];
  var sessao = 'sim-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

  var MARCA = {
    image: '[cliente enviou uma imagem]',
    video: '[cliente enviou um vídeo]',
    document: '[cliente enviou um documento]',
    sticker: '[cliente enviou uma figurinha]'
  };

  function escolha() {
    var s = document.getElementById('simModelo');
    return s ? s.value : '';
  }

  function hora() {
    return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  function rotulo(tipo, nome) {
    if (tipo === 'audio') { return '🎙 áudio'; }
    if (tipo === 'image') { return '📷 imagem'; }
    if (tipo === 'video') { return '🎬 vídeo'; }
    if (tipo === 'document') { return '📄 ' + (nome || 'documento'); }
    if (tipo === 'sticker') { return '🌟 figurinha'; }
    return '';
  }

  function anexoEl(a) {
    var el;
    if (a.tipo === 'image') {
      el = document.createElement('img');
      el.className = 'anx';
      el.src = a.url;
      el.alt = a.nome || 'imagem';
    } else if (a.tipo === 'video') {
      el = document.createElement('video');
      el.className = 'anx';
      el.controls = true;
      el.src = a.url;
    } else if (a.tipo === 'audio') {
      el = document.createElement('audio');
      el.className = 'audio';
      el.controls = true;
      el.preload = 'none';
      el.src = a.url;
    } else {
      el = document.createElement('a');
      el.className = 'doc';
      el.href = a.url;
      el.target = '_blank';
      el.textContent = '📄 ' + (a.nome || 'arquivo');
    }
    return el;
  }

  function bolha(m) {
    var d = document.createElement('div');
    d.className = 'bub ' + (m.papel === 'irene' ? 'out' : 'in');
    var tipo = m.anexo ? m.anexo.tipo : (m.tipo || 'texto');

    if (m.papel === 'irene' && m.voz) {
      var tg = document.createElement('span');
      tg.className = 'mtag';
      tg.textContent = '🎙 resposta em áudio';
      d.appendChild(tg);
      var au = document.createElement('audio');
      au.className = 'audio';
      au.controls = true;
      au.preload = 'none';
      au.src = m.audio;
      d.appendChild(au);
    } else if (m.anexo) {
      var tg2 = document.createElement('span');
      tg2.className = 'mtag';
      tg2.textContent = rotulo(tipo, m.anexo.nome);
      d.appendChild(tg2);
      d.appendChild(anexoEl(m.anexo));
    }

    var texto = m.papel === 'irene' ? (m.texto || '') : (m.legenda || (m.tipo === 'audio' ? m.texto : ''));
    if (texto && !(m.anexo && m.anexo.tipo === 'audio' && m.legenda)) {
      var t = document.createElement('div');
      t.className = 'mtxt';
      t.textContent = texto;
      d.appendChild(t);
    }

    if (m.papel === 'irene' && !m.voz && m.audio) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'ouvir';
      b.textContent = '🔊 ouvir no tom do estúdio';
      b.addEventListener('click', function () {
        audio.style.display = 'block';
        audio.src = m.audio;
        var pr = audio.play();
        if (pr && pr.catch) { pr.catch(function () {}); }
      });
      d.appendChild(b);
    }

    var meta = document.createElement('span');
    meta.className = 't';
    var linha = hora();
    if (m.papel === 'irene') {
      linha += ' · Irene';
      if (m.motor) { linha += ' · ' + m.motor; }
      if (m.ms) { linha += ' · ' + (m.ms / 1000).toFixed(1) + 's'; }
    } else {
      linha += ' · você (cliente)';
    }
    meta.textContent = linha;
    d.appendChild(meta);
    return d;
  }

  function vazio() {
    corpo.innerHTML = '<div class="note n-gray">Escreva a primeira mensagem como se você fosse o cliente.</div>';
  }

  function digitando() {
    var d = document.createElement('div');
    d.className = 'dig';
    d.innerHTML = '<span></span><span></span><span></span>';
    return d;
  }

  function render() {
    corpo.innerHTML = '';
    if (!historico.length && !enviando) { vazio(); }
    for (var i = 0; i < historico.length; i++) {
      corpo.appendChild(bolha(historico[i]));
    }
    if (enviando) { corpo.appendChild(digitando()); }
    corpo.scrollTop = corpo.scrollHeight;
    filaBadge.textContent = pendentes > 0 ? (pendentes + ' pergunta' + (pendentes > 1 ? 's' : '') + ' na fila') : 'fila vazia';
  }

  function adicionarCliente(msg) {
    historico.push(msg);
    pendentes++;
    render();
    agendar();
  }

  function agendar() {
    if (timer) { return; }
    aviso.textContent = 'a Irene responde quando você parar de escrever...';
    timer = setTimeout(function () { timer = null; responder(); }, 1100);
  }

  async function responder() {
    if (enviando || !pendentes) { return; }
    enviando = true;
    pendentes = 0;
    render();
    aviso.textContent = 'a Irene está pensando...';
    var ultimo = historico[historico.length - 1] || {};
    var t0 = Date.now();
    try {
      var r = await fetch('api/irene.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          historico: historico,
          modelo: escolha(),
          ultimo_audio: ultimo.tipo === 'audio',
          preview: 1
        })
      });
      var d = await r.json();
      if (!d.ok) { throw new Error(d.erro || 'falha na resposta'); }
      historico.push({
        papel: 'irene',
        texto: d.resposta,
        motor: d.motor,
        estado: d.estado,
        audio: d.audio,
        voz: !!d.respondeu_audio,
        tipo: d.respondeu_audio ? 'audio' : 'texto',
        ms: Date.now() - t0
      });
      var extra = d.estado === 'humano' ? ' · ⏳ aguardando a Hellen/Daniel'
        : (d.estado === 'menu' ? ' · 🖐 abertura' : '');
      motor.textContent = 'motor: ' + d.motor + extra + ' · ' + historico.length + ' mensagens no histórico';
      aviso.textContent = d.aviso ? '⚠️ ' + d.aviso : 'resposta gerada · nada foi enviado';
    } catch (e) {
      historico.push({ papel: 'irene', texto: '(não consegui responder: ' + e.message + ')', motor: 'erro', ms: Date.now() - t0 });
      aviso.textContent = 'erro: ' + e.message;
    }
    enviando = false;
    render();
    if (pendentes) { agendar(); }
  }

  function enviarTexto() {
    var texto = (campo.value || '').trim();
    if (!texto) { return; }
    campo.value = '';
    adicionarCliente({ papel: 'cliente', texto: texto, tipo: 'texto' });
  }

  async function enviarArquivo(f, legenda) {
    aviso.textContent = 'enviando o arquivo...';
    var fd = new FormData();
    fd.append('acao', 'anexar');
    fd.append('sessao', sessao);
    fd.append('arquivo', f, f.name || 'arquivo');
    try {
      var r = await fetch('api/simulador.php?acao=anexar', { method: 'POST', body: fd });
      var d = await r.json();
      if (!d.ok) { throw new Error(d.erro || 'falha no anexo'); }
      var msg = {
        papel: 'cliente',
        tipo: d.tipo,
        anexo: { url: d.url, nome: d.nome, tipo: d.tipo, sessao: d.sessao, arquivo: d.arquivo },
        legenda: legenda || ''
      };
      if (d.tipo === 'audio') {
        msg.texto = d.texto || '[cliente mandou um áudio que não deu pra ouvir]';
        aviso.textContent = d.transcrito ? 'áudio transcrito ✔' : 'não consegui transcrever — a Irene vai pedir por texto';
      } else {
        msg.texto = (legenda ? legenda + ' ' : '') + (MARCA[d.tipo] || '[cliente enviou um arquivo]');
        aviso.textContent = 'arquivo anexado ✔';
      }
      adicionarCliente(msg);
    } catch (e) {
      aviso.textContent = 'erro: ' + e.message;
    }
  }

  document.getElementById('simEnviar').addEventListener('click', enviarTexto);

  document.getElementById('simAnexarBtn').addEventListener('click', function () { arquivo.click(); });
  arquivo.addEventListener('change', function () {
    var f = arquivo.files && arquivo.files[0];
    if (!f) { return; }
    arquivo.value = '';
    var legenda = (campo.value || '').trim();
    campo.value = '';
    enviarArquivo(f, legenda);
  });

  btnGravar.addEventListener('click', async function () {
    if (gravador) { gravador.stop(); return; }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      aviso.textContent = 'este navegador não deixa gravar áudio';
      return;
    }
    try {
      var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      pedacos = [];
      var rec = new MediaRecorder(stream);
      gravador = rec;
      rec.ondataavailable = function (e) { if (e.data && e.data.size) { pedacos.push(e.data); } };
      rec.onstop = function () {
        var tipo = rec.mimeType || 'audio/webm';
        stream.getTracks().forEach(function (t) { t.stop(); });
        gravador = null;
        btnGravar.textContent = '🎤 gravar áudio';
        recAviso.style.display = 'none';
        enviarArquivo(new Blob(pedacos, { type: tipo }), '');
      };
      rec.start();
      btnGravar.textContent = '⏹ parar e enviar';
      recAviso.style.display = 'inline-flex';
      aviso.textContent = 'gravando... clique em parar quando terminar';
    } catch (e) {
      aviso.textContent = 'não consegui usar o microfone: ' + e.message;
    }
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
    pendentes = 0;
    enviando = false;
    timer = null;
    sessao = 'sim-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    motor.textContent = 'esperando o primeiro "oi" do cliente';
    aviso.textContent = 'conversa resetada · a resposta fica só nesta tela';
    audio.style.display = 'none';
    audio.pause();
    render();
  });

  /* ---------- conversas salvas ---------- */

  var cardSalvas = document.getElementById('simSalvasCard');
  var listaSalvas = document.getElementById('simSalvasLista');
  var verSalvas = document.getElementById('simSalvasVer');
  var nSalvas = document.getElementById('simSalvasN');

  function quando(iso) {
    if (!iso) { return 'sem data'; }
    var d = new Date(iso);
    return isNaN(d.getTime()) ? iso : d.toLocaleString('pt-BR');
  }

  async function carregarSalvas() {
    listaSalvas.innerHTML = '<div class="note n-gray">carregando...</div>';
    try {
      var r = await fetch('api/simulador.php?acao=listar');
      var d = await r.json();
      if (!d.ok) { throw new Error(d.erro || 'falha'); }
      nSalvas.textContent = String(d.conversas.length);
      if (!d.conversas.length) {
        listaSalvas.innerHTML = '<div class="note n-gray">nenhuma conversa salva ainda — clique em 💾 salvar conversa</div>';
        return;
      }
      listaSalvas.innerHTML = '';
      d.conversas.forEach(function (c) {
        var item = document.createElement('div');
        item.className = 'simsalva';
        item.style.marginBottom = '8px';
        var info = document.createElement('div');
        info.style.flex = '1';
        var b = document.createElement('b');
        b.textContent = c.titulo || '(sem título)';
        info.appendChild(b);
        var s1 = document.createElement('small');
        s1.textContent = quando(c.criado_em) + ' · ' + c.mensagens + ' mensagens'
          + (c.audios ? ' · ' + c.audios + ' áudio(s)' : '');
        info.appendChild(s1);
        var s2 = document.createElement('small');
        s2.textContent = 'modelos: ' + (c.motores.length ? c.motores.join(' | ') : (c.modelo || '—'));
        info.appendChild(s2);
        item.appendChild(info);

        var abrir = document.createElement('button');
        abrir.type = 'button';
        abrir.className = 'fchip';
        abrir.textContent = 'ver';
        abrir.addEventListener('click', function (ev) { ev.stopPropagation(); abrirSalva(c.id); });
        item.appendChild(abrir);

        var usar = document.createElement('button');
        usar.type = 'button';
        usar.className = 'fchip';
        usar.textContent = '↩ carregar no simulador';
        usar.addEventListener('click', function (ev) { ev.stopPropagation(); carregarNoSim(c.id); });
        item.appendChild(usar);

        var apagar = document.createElement('button');
        apagar.type = 'button';
        apagar.className = 'fchip';
        apagar.textContent = '🗑';
        apagar.addEventListener('click', async function (ev) {
          ev.stopPropagation();
          await fetch('api/simulador.php?acao=apagar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'apagar', id: c.id })
          });
          carregarSalvas();
        });
        item.appendChild(apagar);

        item.addEventListener('click', function () { abrirSalva(c.id); });
        listaSalvas.appendChild(item);
      });
    } catch (e) {
      listaSalvas.innerHTML = '<div class="note n-amber">não consegui listar: ' + e.message + '</div>';
    }
  }

  async function pegarSalva(id) {
    var r = await fetch('api/simulador.php?acao=ler&id=' + encodeURIComponent(id));
    var d = await r.json();
    if (!d.ok) { throw new Error(d.erro || 'falha ao abrir'); }
    return d.conversa;
  }

  async function abrirSalva(id) {
    verSalvas.style.display = 'block';
    verSalvas.innerHTML = '<div class="note n-gray">abrindo...</div>';
    try {
      var c = await pegarSalva(id);
      verSalvas.innerHTML = '';
      var cab = document.createElement('div');
      cab.className = 'crow';
      cab.style.marginBottom = '9px';
      cab.innerHTML = '<b style="font-size:.88rem">' + (c.titulo || '(sem título)') + '</b>'
        + '<span class="badge b-gray">' + quando(c.criado_em) + '</span>'
        + '<span class="badge b-violet">modelo escolhido: ' + (c.modelo || '—') + '</span>';
      verSalvas.appendChild(cab);
      var caixa = document.createElement('div');
      caixa.className = 'tbody';
      caixa.style.cssText = 'background:#e9edf2;border-radius:13px;max-height:52vh;padding:14px;display:flex;flex-direction:column;gap:9px;overflow-y:auto';
      (c.mensagens || []).forEach(function (m) { caixa.appendChild(bolha(m)); });
      verSalvas.appendChild(caixa);
    } catch (e) {
      verSalvas.innerHTML = '<div class="note n-amber">não consegui abrir: ' + e.message + '</div>';
    }
  }

  async function carregarNoSim(id) {
    try {
      var c = await pegarSalva(id);
      historico = (c.mensagens || []).slice();
      pendentes = 0;
      timer = null;
      render();
      motor.textContent = 'conversa carregada · ' + historico.length + ' mensagens';
      aviso.textContent = 'conversa salva carregada — pode continuar de onde parou';
      cardSalvas.style.display = 'none';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) {
      aviso.textContent = 'erro: ' + e.message;
    }
  }

  document.getElementById('simSalvar').addEventListener('click', async function () {
    if (!historico.length) { aviso.textContent = 'nada para salvar ainda'; return; }
    var titulo = '';
    for (var i = 0; i < historico.length; i++) {
      if (historico[i].papel === 'cliente' && historico[i].texto) { titulo = historico[i].texto.slice(0, 80); break; }
    }
    aviso.textContent = 'salvando...';
    try {
      var r = await fetch('api/simulador.php?acao=salvar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'salvar', titulo: titulo, modelo: escolha(), mensagens: historico })
      });
      var d = await r.json();
      if (!d.ok) { throw new Error(d.erro || 'falha ao salvar'); }
      aviso.textContent = 'conversa salva ✔ (' + d.id + ') — a Irene consegue ler ela depois';
      carregarSalvas();
    } catch (e) {
      aviso.textContent = 'erro: ' + e.message;
    }
  });

  document.getElementById('simVerSalvas').addEventListener('click', function () {
    cardSalvas.style.display = 'block';
    carregarSalvas();
  });
  document.getElementById('simFecharSalvas').addEventListener('click', function () {
    cardSalvas.style.display = 'none';
  });
  document.getElementById('simAtualizarSalvas').addEventListener('click', carregarSalvas);

  carregarSalvas();
  render();
})();
</script>
<?php v2_layout_bottom(); ?>
