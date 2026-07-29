<?php
require __DIR__ . '/../plan/includes/bootstrap.php';
$user = current_user();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b0d10">
<title>CNJP | Roadmap Vivo</title>
<style>
:root{--bg:#090b0e;--panel:#11141a;--panel2:#171b22;--line:#292f3a;--text:#f2f5f7;--muted:#99a3b0;--gold:#d8b56b;--green:#79c995;--yellow:#e6b84c;--red:#df7c7c;--blue:#73a9e6;--max:1180px}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:radial-gradient(circle at 80% 0,rgba(216,181,107,.09),transparent 25%),linear-gradient(180deg,#08090c,#0c0f13 45%,#080a0d);color:var(--text);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;line-height:1.55}button,input,select,textarea{font:inherit}.wrap{width:min(var(--max),calc(100% - 28px));margin:auto}.top{position:sticky;top:0;z-index:30;background:rgba(9,11,14,.92);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}.topin{min-height:66px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{font-weight:950;letter-spacing:.12em}.brand span{color:var(--gold)}.user{display:flex;align-items:center;gap:12px;color:var(--muted);font-size:13px}.user a{color:var(--gold);text-decoration:none}.hero{padding:58px 0 24px}.eyebrow{color:var(--gold);font-size:12px;text-transform:uppercase;letter-spacing:.16em;font-weight:850}.hero h1{font-size:clamp(38px,7vw,72px);line-height:.95;letter-spacing:-.045em;margin:10px 0 16px}.lead{max-width:820px;color:#c6ccd4;font-size:clamp(17px,2vw,21px)}.principle{margin-top:22px;padding:15px 18px;border-left:3px solid var(--gold);background:#11141a;color:#d8dde4}.overview{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:28px 0}.stat{border:1px solid var(--line);background:var(--panel);border-radius:14px;padding:17px}.stat b{display:block;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em}.stat strong{display:block;font-size:29px;margin-top:4px}.progress{height:10px;border-radius:999px;background:#1a1e25;overflow:hidden;margin-top:9px}.progress i{display:block;height:100%;background:linear-gradient(90deg,#b58d46,var(--gold));width:0;transition:.25s}.toolbar{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 26px}.btn{border:1px solid var(--line);background:#151922;color:var(--text);border-radius:10px;padding:11px 14px;font-weight:800;cursor:pointer}.btn:hover{border-color:#4a5362}.btn.primary{background:var(--gold);border-color:var(--gold);color:#15120b}.btn.small{padding:7px 10px;font-size:12px}.section-title{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:34px 0 12px}.section-title h2{margin:0;font-size:26px}.section-title p{margin:4px 0 0;color:var(--muted);font-size:14px}.phase{margin:12px 0;border:1px solid var(--line);background:var(--panel);border-radius:16px;overflow:hidden}.phase>summary{cursor:pointer;list-style:none;padding:18px 56px 18px 18px;position:relative}.phase>summary::-webkit-details-marker{display:none}.phase>summary:after{content:'+';position:absolute;right:18px;top:9px;color:var(--gold);font-size:32px}.phase[open]>summary:after{content:'–'}.phase[open]>summary{border-bottom:1px solid var(--line)}.phase-head{display:flex;align-items:center;gap:12px}.phase-n{width:34px;height:34px;display:grid;place-items:center;border-radius:50%;background:var(--gold);color:#111;font-weight:950}.phase-head strong{font-size:18px}.phase-head small{display:block;color:var(--muted);margin-top:2px}.tasks{padding:12px}.task{border:1px solid var(--line);background:#0e1116;border-radius:13px;margin:10px 0;overflow:hidden}.task>summary{cursor:pointer;list-style:none;padding:15px 48px 15px 15px;position:relative}.task>summary::-webkit-details-marker{display:none}.task>summary:after{content:'›';position:absolute;right:18px;top:10px;font-size:25px;color:var(--muted);transform:rotate(90deg)}.task[open]>summary:after{transform:rotate(-90deg)}.task-title{display:flex;align-items:center;gap:10px}.status-dot{width:10px;height:10px;border-radius:50%;background:#57606c;flex:0 0 auto}.task[data-status=doing] .status-dot{background:var(--blue)}.task[data-status=done] .status-dot{background:var(--green)}.task[data-status=blocked] .status-dot{background:var(--red)}.task-title b{font-size:15px}.task-title span{margin-left:auto;color:var(--muted);font-size:11px}.task-body{padding:16px;border-top:1px solid var(--line)}.explain{color:#c8ced6;margin-top:0}.question{background:#171b22;border-left:3px solid var(--gold);padding:12px 14px;margin:14px 0}.field{margin:13px 0}.field label{display:block;font-weight:800;font-size:13px;margin-bottom:6px}.field textarea,.field input,.field select{width:100%;background:#0a0c10;border:1px solid var(--line);border-radius:9px;color:var(--text);padding:11px}.field textarea{min-height:130px;resize:vertical}.row{display:grid;grid-template-columns:200px 1fr;gap:12px}.savebar{display:flex;align-items:center;gap:10px;justify-content:flex-end}.saved{font-size:12px;color:var(--muted)}.status-todo{color:var(--muted)}.status-doing{color:var(--blue)}.status-done{color:var(--green)}.status-blocked{color:var(--red)}.panel{background:var(--panel);border:1px solid var(--line);border-radius:15px;padding:17px;margin:12px 0}.panel h3{margin:0 0 6px}.panel p{margin-top:0;color:var(--muted)}.panel textarea{width:100%;min-height:170px;background:#0a0c10;color:var(--text);border:1px solid var(--line);border-radius:10px;padding:12px;resize:vertical}.decision-form{display:grid;grid-template-columns:1fr 180px auto;gap:10px;margin-top:12px}.decision-form input,.decision-form select{background:#0a0c10;border:1px solid var(--line);border-radius:9px;color:var(--text);padding:10px}.decision-list{display:grid;gap:8px;margin-top:12px}.decision{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:start;background:#0e1116;border:1px solid var(--line);border-radius:10px;padding:12px}.decision small{color:var(--muted)}.tag{display:inline-flex;padding:3px 8px;border-radius:999px;font-size:11px;border:1px solid var(--line);margin-left:6px}.tag.decided{color:var(--green)}.tag.open{color:var(--yellow)}.tag.discarded{color:var(--red)}.roadmap{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap;background:#080a0d;border:1px solid var(--line);padding:16px;border-radius:11px;color:#dce1e6}.toast{position:fixed;right:18px;bottom:18px;background:#171b22;border:1px solid var(--line);padding:12px 15px;border-radius:10px;box-shadow:0 16px 40px #0008;display:none;z-index:50}.login-shell{min-height:100vh;display:grid;place-items:center;padding:22px}.login{width:min(450px,100%);background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:24px}.login h1{margin:0 0 8px}.login p{color:var(--muted)}.login label{display:block;margin:12px 0 5px;font-weight:800}.login input{width:100%;background:#0a0c10;border:1px solid var(--line);border-radius:9px;padding:12px;color:var(--text)}.login button{width:100%;margin-top:16px}.message{min-height:20px;color:var(--red);font-size:13px}.footer{padding:40px 0 60px;color:var(--muted);font-size:13px;border-top:1px solid var(--line);margin-top:38px}@media(max-width:800px){.overview{grid-template-columns:1fr 1fr}.row,.decision-form{grid-template-columns:1fr}.user span{display:none}.hero{padding-top:40px}.task-title span{display:none}}@media(max-width:480px){.overview{grid-template-columns:1fr}.wrap{width:min(var(--max),calc(100% - 18px))}.hero h1{font-size:42px}}
</style>
</head>
<body>
<?php if(!$user): ?>
<main class="login-shell">
  <section class="login">
    <div class="eyebrow">CNJP • area privada</div>
    <h1>Entrar</h1>
    <p>Use o mesmo login do Plan Financeiro. Uma senha a menos para a humanidade esquecer.</p>
    <form id="loginForm">
      <label>E-mail</label><input type="email" name="email" autocomplete="email" required>
      <label>Senha</label><input type="password" name="password" autocomplete="current-password" required>
      <button class="btn primary" type="submit">Acessar roadmap</button>
      <div class="message" id="loginMessage"></div>
    </form>
  </section>
</main>
<script>
const CSRF=<?=json_encode($csrf)?>;
document.getElementById('loginForm').addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(e.currentTarget);const r=await fetch('api.php?action=login',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(Object.fromEntries(f))});const j=await r.json();if(j.ok)location.reload();else document.getElementById('loginMessage').textContent=j.message||'Falha ao entrar.'});
</script>
<?php else: ?>
<header class="top"><div class="wrap topin"><div class="brand">CNJP <span>ROADMAP</span></div><div class="user"><span><?=htmlspecialchars($user['name'] ?? $user['email'])?></span><a href="../plan/logout.php">Sair</a></div></div></header>
<main class="wrap">
<section class="hero">
  <div class="eyebrow">Documento vivo da empresa</div>
  <h1>Entender → organizar → executar.</h1>
  <p class="lead">Este é o nosso roteiro de trabalho. Cada bloco explica o que precisa ser decidido, guarda a resposta de vocês e registra o estágio atual para a conversa nunca voltar à estaca zero.</p>
  <div class="principle"><strong>Regra:</strong> abrir apenas o nível que estamos trabalhando. Ideias futuras entram no estacionamento, não viram urgência só porque parecem brilhantes às 23h47.</div>
  <div class="overview">
    <div class="stat"><b>Progresso total</b><strong id="progressNumber">0%</strong><div class="progress"><i id="progressBar"></i></div></div>
    <div class="stat"><b>Concluídas</b><strong id="doneCount">0</strong></div>
    <div class="stat"><b>Em andamento</b><strong id="doingCount">0</strong></div>
    <div class="stat"><b>Bloqueadas</b><strong id="blockedCount">0</strong></div>
  </div>
  <div class="toolbar"><button class="btn primary" id="copySummary">Copiar resumo para o ChatGPT</button><button class="btn" id="openCurrent">Abrir fase atual</button><button class="btn" id="openAll">Abrir tudo</button><button class="btn" id="closeAll">Fechar tudo</button></div>
</section>

<section>
  <div class="section-title"><div><h2>Roadmap</h2><p>As tarefas abaixo sao gravadas no banco. Status, respostas e observacoes ficam registradas entre acessos.</p></div></div>
  <div id="phases"></div>
</section>

<section>
  <div class="section-title"><div><h2>Decisoes e pendencias</h2><p>Use para registrar coisas que precisam ser decididas sem poluir as tarefas.</p></div></div>
  <div class="panel">
    <form id="decisionForm" class="decision-form"><input id="decisionTitle" placeholder="Ex.: CNJP Tech tera marca separada"><select id="decisionStatus"><option value="open">Em aberto</option><option value="decided">Decidido</option><option value="discarded">Descartado</option></select><button class="btn primary" type="submit">Registrar</button></form>
    <div class="decision-list" id="decisionList"></div>
  </div>
</section>

<section>
  <div class="section-title"><div><h2>Estacionamento de ideias</h2><p>Coisas boas que nao devemos executar agora. O cemiterio de prioridades agradece.</p></div></div>
  <div class="panel"><textarea id="parking" placeholder="Ex.: planos B2B recorrentes, CNJP Tech, arbitragem contratual, rede de parceiros..."></textarea><div class="savebar"><span class="saved" id="parkingSaved"></span><button class="btn small" data-save-note="parking">Salvar ideias</button></div></div>
</section>

<section>
  <div class="section-title"><div><h2>Mapa geral</h2><p>Visao curta para lembrar por que cada fase existe.</p></div></div>
  <div class="roadmap">FASE 1 — INVENTÁRIO
Descobrir o que já existe, quem sabe fazer o quê e quais ativos temos.

FASE 2 — POSSIBILIDADES
Levantar serviços, limites e problemas reais de clientes.

FASE 3 — PRODUTOS
Reduzir dezenas de possibilidades para poucos produtos claros e vendáveis.

FASE 4 — OPERAÇÃO
Definir fluxo, responsabilidades, documentos e parceiros.

FASE 5 — SISTEMAS
CRM, WhatsApp, automações, IA e indicadores.

FASE 6 — MARCA E SITE
Explicar cada braço da CNJP sem parecer um supermercado de CNAEs.

FASE 7 — AQUISIÇÃO
Campanhas, prospecção, parceiros e lançamento controlado.</div>
</section>
</main>
<div class="toast" id="toast"></div>
<footer class="footer"><div class="wrap">CNJP Roadmap • dados persistidos no mesmo MySQL do Plan • autenticação compartilhada</div></footer>
<script>
let BOOT={tasks:[],notes:{},decisions:[],csrf:<?=json_encode($csrf)?>};
const phaseNames={1:['Inventário','Descobrir o que já existe.'],2:['Possibilidades','Mapear oportunidades e limites.'],3:['Produtos','Escolher o que realmente será vendido.'],4:['Operação','Definir como cada entrega funciona.'],5:['Sistemas','Automatizar apenas o que já faz sentido.'],6:['Marca e site','Explicar a empresa com clareza.'],7:['Aquisição','Colocar clientes para dentro.']};
function esc(v=''){return String(v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function toast(t){const e=document.getElementById('toast');e.textContent=t;e.style.display='block';clearTimeout(window.__toast);window.__toast=setTimeout(()=>e.style.display='none',2200)}
async function api(action,method='GET',body=null){const opt={method,headers:{'X-CSRF-Token':BOOT.csrf}};if(body){opt.headers['Content-Type']='application/json';opt.body=JSON.stringify(body)}const r=await fetch(`api.php?action=${action}`,opt);const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||'Erro');if(j.csrf)BOOT.csrf=j.csrf;return j}
async function load(){const j=await api('bootstrap');BOOT=j;render();}
function render(){renderPhases();renderStats();renderDecisions();document.getElementById('parking').value=BOOT.notes?.parking||''}
function renderPhases(){const holder=document.getElementById('phases');holder.innerHTML='';for(let p=1;p<=7;p++){const tasks=BOOT.tasks.filter(t=>Number(t.phase)===p),done=tasks.filter(t=>t.status==='done').length;const d=document.createElement('details');d.className='phase';d.dataset.phase=p;if(p===currentPhase())d.open=true;d.innerHTML=`<summary><div class="phase-head"><span class="phase-n">${p}</span><div><strong>${phaseNames[p][0]}</strong><small>${phaseNames[p][1]} • ${done}/${tasks.length} concluídas</small></div></div></summary><div class="tasks">${tasks.map(taskHtml).join('')}</div>`;holder.appendChild(d)}bindTasks()}
function taskHtml(t){const statusLabel={todo:'A fazer',doing:'Em andamento',done:'Concluída',blocked:'Bloqueada'}[t.status]||'A fazer';return `<details class="task" data-key="${esc(t.task_key)}" data-status="${esc(t.status)}"><summary><div class="task-title"><i class="status-dot"></i><b>${esc(t.title)}</b><span class="status-${esc(t.status)}">${statusLabel}</span></div></summary><div class="task-body"><p class="explain">${esc(t.explanation)}</p><div class="question"><strong>O que precisamos registrar:</strong><br>${esc(t.question)}</div><div class="row"><div class="field"><label>Status</label><select class="task-status"><option value="todo" ${t.status==='todo'?'selected':''}>A fazer</option><option value="doing" ${t.status==='doing'?'selected':''}>Em andamento</option><option value="done" ${t.status==='done'?'selected':''}>Concluída</option><option value="blocked" ${t.status==='blocked'?'selected':''}>Bloqueada</option></select></div><div class="field"><label>Resposta / estado atual</label><textarea class="task-answer" placeholder="Preencha aqui...">${esc(t.answer||'')}</textarea></div></div><div class="field"><label>Observações, dúvidas e próximos passos</label><textarea class="task-notes" placeholder="O que ficou pendente? O que precisamos pesquisar?">${esc(t.notes||'')}</textarea></div><div class="savebar"><span class="saved">${t.updated_at?`Última alteração: ${esc(t.updated_at)}`:''}</span><button class="btn small task-save">Salvar tarefa</button></div></div></details>`}
function bindTasks(){document.querySelectorAll('.task-save').forEach(btn=>btn.onclick=async()=>{const el=btn.closest('.task'),key=el.dataset.key;btn.disabled=true;try{await api('save_task','POST',{task_key:key,status:el.querySelector('.task-status').value,answer:el.querySelector('.task-answer').value,notes:el.querySelector('.task-notes').value});toast('Tarefa salva');await load()}catch(e){toast(e.message)}finally{btn.disabled=false}})}
function renderStats(){const total=BOOT.tasks.length,done=BOOT.tasks.filter(t=>t.status==='done').length,doing=BOOT.tasks.filter(t=>t.status==='doing').length,blocked=BOOT.tasks.filter(t=>t.status==='blocked').length,pct=total?Math.round(done/total*100):0;document.getElementById('progressNumber').textContent=pct+'%';document.getElementById('progressBar').style.width=pct+'%';document.getElementById('doneCount').textContent=done;document.getElementById('doingCount').textContent=doing;document.getElementById('blockedCount').textContent=blocked}
function currentPhase(){for(let p=1;p<=7;p++){const ts=BOOT.tasks.filter(t=>Number(t.phase)===p);if(ts.some(t=>t.status!=='done'))return p}return 7}
function renderDecisions(){const h=document.getElementById('decisionList');h.innerHTML=BOOT.decisions.length?BOOT.decisions.map(d=>`<div class="decision"><div><strong>${esc(d.title)}</strong><span class="tag ${esc(d.decision_status)}">${d.decision_status==='open'?'Em aberto':d.decision_status==='decided'?'Decidido':'Descartado'}</span><small>${d.description?'<br>'+esc(d.description):''}${d.created_by_name?'<br>por '+esc(d.created_by_name):''}</small></div><button class="btn small" data-del-decision="${d.id}">Excluir</button></div>`).join(''):'<small>Nenhuma decisão registrada ainda.</small>';document.querySelectorAll('[data-del-decision]').forEach(b=>b.onclick=async()=>{await api('delete_decision','POST',{id:Number(b.dataset.delDecision)});await load();toast('Registro removido')})}
document.getElementById('decisionForm').addEventListener('submit',async e=>{e.preventDefault();const title=document.getElementById('decisionTitle').value.trim();if(!title)return;await api('save_decision','POST',{title,decision_status:document.getElementById('decisionStatus').value});document.getElementById('decisionTitle').value='';await load();toast('Decisão registrada')});
document.querySelector('[data-save-note="parking"]').onclick=async()=>{await api('save_project_note','POST',{note_key:'parking',content:document.getElementById('parking').value});document.getElementById('parkingSaved').textContent='Salvo agora';toast('Ideias salvas')};
document.getElementById('openAll').onclick=()=>document.querySelectorAll('details').forEach(d=>d.open=true);document.getElementById('closeAll').onclick=()=>document.querySelectorAll('details').forEach(d=>d.open=false);document.getElementById('openCurrent').onclick=()=>{document.querySelectorAll('.phase').forEach(d=>d.open=Number(d.dataset.phase)===currentPhase());document.querySelector(`.phase[data-phase="${currentPhase()}"]`)?.scrollIntoView({behavior:'smooth',block:'start'})};
document.getElementById('copySummary').onclick=async()=>{const lines=['CNJP — ESTADO ATUAL DO ROADMAP',`Progresso: ${BOOT.tasks.filter(t=>t.status==='done').length}/${BOOT.tasks.length} tarefas concluídas`,`Fase atual: ${currentPhase()} — ${phaseNames[currentPhase()][0]}`,''];for(let p=1;p<=7;p++){lines.push(`## FASE ${p} — ${phaseNames[p][0]}`);BOOT.tasks.filter(t=>Number(t.phase)===p).forEach(t=>{lines.push(`- [${t.status}] ${t.title}`);if((t.answer||'').trim())lines.push(`  Resposta: ${(t.answer||'').trim()}`);if((t.notes||'').trim())lines.push(`  Observações: ${(t.notes||'').trim()}`)});lines.push('')}if(BOOT.decisions.length){lines.push('## DECISÕES / PENDÊNCIAS');BOOT.decisions.forEach(d=>lines.push(`- [${d.decision_status}] ${d.title}${d.description?': '+d.description:''}`));lines.push('')}if((BOOT.notes?.parking||'').trim()){lines.push('## ESTACIONAMENTO DE IDEIAS',BOOT.notes.parking)}lines.push('','Use este estado como contexto e me ajude a avançar somente para o próximo passo lógico, sem pular etapas.');await navigator.clipboard.writeText(lines.join('\n'));toast('Resumo copiado para colar no ChatGPT')};
load().catch(e=>toast(e.message));
</script>
<?php endif; ?>
</body>
</html>