<?php
$arquivo = __DIR__ . '/hotspots.json';
$hotspots = file_exists($arquivo) ? file_get_contents($arquivo) : '{"frente":[],"costas":[]}';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ajustar Hotspots - Orçamento</title>
<style>
:root{--bg:#050505;--panel:#111114;--line:rgba(255,255,255,.14);--red:#e7332f;--txt:#fff;--muted:#aaa;--green:#25d366}*{box-sizing:border-box}body{margin:0;background:#050505;color:#fff;font-family:Arial,Helvetica,sans-serif;padding:14px}.wrap{max-width:1500px;margin:auto}.top{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:14px}.top h1{margin:0;text-transform:uppercase;font-size:26px}.badge{font-size:12px;color:#ff7068;border:1px solid rgba(231,51,47,.5);border-radius:999px;padding:5px 9px}.grid{display:grid;grid-template-columns:1fr 390px;gap:16px}.stage,.side{background:linear-gradient(180deg,#151519,#09090a);border:1px solid var(--line);border-radius:14px;padding:16px}.bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}button,select,input,textarea{font:inherit}button{border:1px solid var(--line);background:#0b0b0d;color:#fff;border-radius:8px;padding:10px 12px;font-weight:900;cursor:pointer}.active{background:linear-gradient(#e7332f,#8c1411)!important}.save{background:linear-gradient(#25d366,#148b3f)!important;color:#06170b}.map{position:relative;width:min(100%,640px);height:760px;margin:auto;background:#020202;border:1px solid var(--line);border-radius:12px;overflow:hidden}.map img{position:absolute;left:50%;top:50%;height:96%;max-width:78%;transform:translate(-50%,-50%);object-fit:contain;user-select:none;pointer-events:none}.spot{position:absolute;border:2px solid rgba(255,80,70,.75);background:rgba(231,51,47,.18);border-radius:999px;color:transparent;cursor:move;box-shadow:0 0 18px rgba(231,51,47,.45);touch-action:none}.spot:hover{background:rgba(231,51,47,.30)}.spot.sel{border-color:#fff;background:rgba(231,51,47,.48);box-shadow:0 0 28px rgba(255,75,69,.95)}.handle{position:absolute;right:-8px;bottom:-8px;width:18px;height:18px;border-radius:50%;background:#fff;border:3px solid var(--red);cursor:nwse-resize;z-index:5;box-shadow:0 0 10px rgba(0,0,0,.8)}.side h2{margin:0 0 12px;text-transform:uppercase}.field{display:grid;gap:5px;margin-bottom:10px}.field label{font-size:11px;color:#bbb;text-transform:uppercase;font-weight:900}.field input,.field select,.field textarea{width:100%;border:1px solid var(--line);background:#070707;color:#fff;border-radius:8px;padding:10px}.row{display:grid;grid-template-columns:1fr 1fr;gap:8px}.list{max-height:260px;overflow:auto;display:grid;gap:6px;margin-top:10px}.item{border:1px solid var(--line);background:#080808;border-radius:8px;padding:8px;text-align:left}.item.sel{border-color:#fff;background:rgba(231,51,47,.18)}textarea{min-height:180px;font-family:monospace;font-size:12px}.help{font-size:13px;color:#bbb;line-height:1.45}.status{margin-top:10px;color:#bbb;white-space:pre-wrap}.danger{color:#ff756d}.ok{color:#75ff9f}@media(max-width:1050px){.grid{grid-template-columns:1fr}.map{height:620px}.side{order:-1}}
</style>
</head>
<body>
<div class="wrap">
  <div class="top"><h1>Ajustar hotspots <span class="badge">orcamento</span></h1><div class="help">Arraste a área para mover. Arraste a bolinha branca para redimensionar.</div></div>
  <div class="grid">
    <main class="stage">
      <div class="bar"><button id="btnFrente" class="active">Frente</button><button id="btnCostas">Costas</button><button id="toggleGhost">Mostrar/Ocultar áreas</button><button id="save" class="save">Salvar hotspots.json</button></div>
      <div class="map" id="map"><img id="body" src="assets/body-front-muscular.png?v=1" alt="manequim"></div>
      <p class="help">Dica: clique em uma área e use as setas do teclado. Com Shift anda 5x. Campos numéricos servem para ajuste cirúrgico.</p>
    </main>
    <aside class="side">
      <h2>Região selecionada</h2>
      <div class="field"><label>Hotspot</label><select id="hotspotSelect"></select></div>
      <div class="row"><div class="field"><label>Left %</label><input id="left" type="number" step="0.1"></div><div class="field"><label>Top %</label><input id="top" type="number" step="0.1"></div></div>
      <div class="row"><div class="field"><label>Width %</label><input id="width" type="number" step="0.1"></div><div class="field"><label>Height %</label><input id="height" type="number" step="0.1"></div></div>
      <div class="row"><button id="apply">Aplicar valores</button><button id="copy">Copiar JSON</button></div>
      <div class="status" id="status"></div>
      <div class="list" id="list"></div>
      <div class="field" style="margin-top:12px"><label>JSON atual</label><textarea id="jsonOut" spellcheck="false"></textarea></div>
    </aside>
  </div>
</div>
<script>
let data = <?php echo $hotspots ?: '{"frente":[],"costas":[]}'; ?>;
let view = 'frente', selected = null, visible = true, drag = null;
const $ = id => document.getElementById(id);
function img(){ return view === 'frente' ? 'assets/body-front-muscular.png?v=1' : 'assets/body-back-muscular.png?v=1'; }
function getArr(){ return data[view] || []; }
function render(){
  $('body').src = img();
  $('map').querySelectorAll('.spot').forEach(e=>e.remove());
  getArr().forEach((h,i)=>{
    const s=document.createElement('div');
    s.className='spot';
    if(selected===i)s.classList.add('sel');
    s.dataset.i=i;
    s.style.left=h[3]+'%'; s.style.top=h[4]+'%'; s.style.width=h[5]+'%'; s.style.height=h[6]+'%';
    s.style.opacity=visible?1:0;
    s.title=h[2];
    const handle=document.createElement('span');
    handle.className='handle';
    handle.title='Redimensionar';
    handle.addEventListener('mousedown',ev=>start(ev,i,true));
    handle.addEventListener('touchstart',ev=>start(ev,i,true),{passive:false});
    s.appendChild(handle);
    s.addEventListener('mousedown',ev=>start(ev,i,false));
    s.addEventListener('touchstart',ev=>start(ev,i,false),{passive:false});
    s.addEventListener('click',ev=>{ev.stopPropagation(); select(i)});
    $('map').appendChild(s);
  });
  renderSide();
}
function renderSide(){
  const arr=getArr();
  if(selected===null && arr.length) selected=0;
  $('hotspotSelect').innerHTML = arr.map((h,i)=>`<option value="${i}" ${i===selected?'selected':''}>${h[2]} - ${h[0]}</option>`).join('');
  const h=arr[selected];
  ['left','top','width','height'].forEach((id,idx)=>$(id).value = h ? h[idx+3] : '');
  $('list').innerHTML = arr.map((h,i)=>`<button class="item ${i===selected?'sel':''}" onclick="select(${i})">${h[2]}<br><small>${h[0]} | ${h[3]}, ${h[4]}, ${h[5]}, ${h[6]}</small></button>`).join('');
  $('jsonOut').value = JSON.stringify(data,null,2);
}
function pointer(ev){ const p=ev.touches ? ev.touches[0] : ev; return {x:p.clientX,y:p.clientY}; }
function select(i){ selected=Number(i); render(); }
function start(ev,i,isResize){
  ev.preventDefault(); ev.stopPropagation(); selected=Number(i);
  const p=pointer(ev), rect=$('map').getBoundingClientRect(), h=getArr()[i];
  drag={i,isResize,rect,startX:p.x,startY:p.y,orig:[h[3],h[4],h[5],h[6]]};
  renderSide();
}
function move(ev){
  if(!drag)return;
  ev.preventDefault();
  const p=pointer(ev), h=getArr()[drag.i], dx=(p.x-drag.startX)/drag.rect.width*100, dy=(p.y-drag.startY)/drag.rect.height*100;
  if(drag.isResize){ h[5]=round(Math.max(1,drag.orig[2]+dx)); h[6]=round(Math.max(1,drag.orig[3]+dy)); }
  else { h[3]=round(drag.orig[0]+dx); h[4]=round(drag.orig[1]+dy); }
  paintOnly();
}
function stop(){ if(drag){ drag=null; render(); } }
window.addEventListener('mousemove',move); window.addEventListener('touchmove',move,{passive:false}); window.addEventListener('mouseup',stop); window.addEventListener('touchend',stop);
function paintOnly(){
  document.querySelectorAll('.spot').forEach(s=>{
    const h=getArr()[Number(s.dataset.i)]; if(!h)return;
    s.style.left=h[3]+'%'; s.style.top=h[4]+'%'; s.style.width=h[5]+'%'; s.style.height=h[6]+'%';
    s.classList.toggle('sel',Number(s.dataset.i)===selected);
  });
  renderSide();
}
window.addEventListener('keydown',ev=>{
  if(selected===null || ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName))return;
  const h=getArr()[selected], step=ev.shiftKey?1:.2;
  if(ev.key==='ArrowLeft')h[3]=round(h[3]-step); else if(ev.key==='ArrowRight')h[3]=round(h[3]+step); else if(ev.key==='ArrowUp')h[4]=round(h[4]-step); else if(ev.key==='ArrowDown')h[4]=round(h[4]+step); else return;
  ev.preventDefault(); render();
});
function round(n){return Math.round(n*10)/10}
$('btnFrente').onclick=()=>{view='frente';selected=0;$('btnFrente').classList.add('active');$('btnCostas').classList.remove('active');render()};
$('btnCostas').onclick=()=>{view='costas';selected=0;$('btnCostas').classList.add('active');$('btnFrente').classList.remove('active');render()};
$('toggleGhost').onclick=()=>{visible=!visible;render()};
$('hotspotSelect').onchange=e=>select(e.target.value);
$('apply').onclick=()=>{const h=getArr()[selected]; if(!h)return; h[3]=Number($('left').value); h[4]=Number($('top').value); h[5]=Number($('width').value); h[6]=Number($('height').value); render()};
$('copy').onclick=async()=>{await navigator.clipboard.writeText(JSON.stringify(data,null,2)); $('status').innerText='JSON copiado.'};
$('save').onclick=async()=>{
  $('status').innerText='Salvando...';
  try{
    const r=await fetch('save-hotspots.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    const text=await r.text();
    let j; try{j=JSON.parse(text)}catch(e){throw new Error('Resposta nao era JSON: '+text.slice(0,300));}
    if(j.ok){ $('status').innerHTML='<span class="ok">Salvo em hotspots.json.</span>\nBytes: '+j.bytes+'\nHorario: '+j.salvo_em; }
    else { $('status').innerHTML='<span class="danger">Erro ao salvar:</span> '+(j.erro||'desconhecido')+'\n'+JSON.stringify(j.diagnostico||{},null,2); }
  } catch(e){ $('status').innerHTML='<span class="danger">Falhou ao salvar.</span>\n'+e.message+'\nCopie o JSON manualmente se precisar.'; }
};
render();
</script>
</body>
</html>
