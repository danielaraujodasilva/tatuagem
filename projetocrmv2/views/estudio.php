<?php
/**
 * Tela Estúdio: os fatos que a Irene usa para responder os clientes.
 * Um fato por linha. O que ficar salvo aqui e o que ela passa a usar.
 */
$fatos = v2_estudio_fatos();
$texto = implode("\n", $fatos);
v2_layout_top('estudio', 'Estúdio');
?>
<div class="head">
  <div class="kick">o que a Irene sabe</div>
  <h2>As informações do estúdio</h2>
  <p>É isso que a Irene usa pra responder os clientes: preço, endereço, reserva, cuidados... Um fato por linha. O que ficar salvo aqui é o que ela passa a usar.</p>
</div>

<div class="card pad">
  <label class="fsep" for="estudioFatos">fatos do estúdio</label>
  <textarea id="estudioFatos" style="width:100%;min-height:260px;margin-top:8px;resize:vertical;border:1px solid var(--line);border-radius:13px;padding:11px 12px;font:inherit;font-size:.86rem;color:var(--ink);background:#fff"><?= v2_h($texto) ?></textarea>
  <div style="color:var(--muted);font-size:.76rem;margin-top:6px">Uma linha = um fato. A Irene cita exatamente o que estiver aqui, sem inventar.</div>

  <div class="crow" style="margin-top:12px">
    <button class="btn btn-a" type="button" id="estudioSalvar">&#128190; Salvar</button>
    <span class="badge b-gray" id="estudioAviso">carregado</span>
  </div>
</div>

<div class="card pad">
  <p class="sect">&#128161; Como escrever</p>
  <div class="note n-gray">Curto e direto, como você falaria no WhatsApp. Ex.: <b>Endereço: Rua Catende, 287B, Jd Nordeste, São Paulo.</b></div>
  <div class="note n-gray" style="margin-top:10px">Se a Irene não achar a resposta aqui, ela diz que vai confirmar com o Daniel — nunca inventa.</div>
</div>

<script>
(function () {
  var area = document.getElementById('estudioFatos');
  var salvar = document.getElementById('estudioSalvar');
  var aviso = document.getElementById('estudioAviso');

  salvar.addEventListener('click', async function () {
    salvar.disabled = true;
    aviso.textContent = 'salvando...';
    try {
      var r = await fetch('api/estudio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ texto: area.value })
      });
      var d = await r.json();
      if (!d.ok) { throw new Error(d.erro || 'falha ao salvar'); }
      area.value = (d.fatos || []).join('\n');
      aviso.textContent = 'salvo — a Irene já usa essas informações';
    } catch (e) {
      aviso.textContent = 'não consegui salvar: ' + e.message;
    }
    salvar.disabled = false;
  });
})();
</script>
<?php v2_layout_bottom(); ?>