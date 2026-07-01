<?php
/**
 * Painel visual para iniciar e acompanhar a sincronização do Instagram.
 * URL:
 * https://danieltatuador.com/instagram/sync-panel.php
 */
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#050403">
  <title>Sync Instagram | Daniel Tatuador</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root{--gold:#c9964a;--gold2:#e2b871;--bg:#050403;--card:#11100e;--text:#f8f3ea;--muted:#b9afa0;--line:rgba(226,184,113,.2);--bad:#ff6b6b;--ok:#68e39b}
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(circle at 75% 10%,rgba(201,150,74,.22),transparent 28%),linear-gradient(180deg,#050403,#0d0b08 55%,#050403);color:var(--text);font-family:Inter,system-ui,sans-serif;padding:28px}
    .wrap{width:min(880px,100%)}
    .brand{display:flex;align-items:center;gap:14px;margin-bottom:22px;color:var(--gold2);font-weight:900;text-transform:uppercase;letter-spacing:.16em;font-size:12px}
    .mark{width:44px;height:44px;border:1px solid rgba(226,184,113,.55);display:grid;place-items:center;font-family:'Barlow Condensed';font-size:24px;background:rgba(0,0,0,.22)}
    .card{position:relative;overflow:hidden;border:1px solid var(--line);border-radius:28px;background:linear-gradient(145deg,rgba(255,255,255,.065),rgba(255,255,255,.025));box-shadow:0 30px 90px rgba(0,0,0,.45);padding:34px}
    .card:before{content:'';position:absolute;inset:-2px;background:radial-gradient(circle at 20% 0,rgba(226,184,113,.16),transparent 35%);pointer-events:none}
    .content{position:relative;z-index:2}
    h1{font-family:'Barlow Condensed';font-size:clamp(42px,7vw,76px);line-height:.9;text-transform:uppercase;letter-spacing:.04em;margin:0 0 12px}
    p{color:var(--muted);line-height:1.7;margin:0}
    .top{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;margin-bottom:30px}
    .badge{padding:10px 14px;border-radius:999px;border:1px solid var(--line);background:rgba(0,0,0,.25);font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.1em;color:var(--gold2);white-space:nowrap}
    .meter{margin:28px 0 20px}
    .percent{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:12px}
    .percent strong{font-family:'Barlow Condensed';font-size:54px;line-height:.8;color:var(--gold2)}
    .percent span{color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.08em;font-size:12px;text-align:right}
    .bar{height:24px;border:1px solid rgba(226,184,113,.24);background:rgba(0,0,0,.35);border-radius:999px;overflow:hidden;box-shadow:inset 0 0 20px rgba(0,0,0,.35)}
    .fill{height:100%;width:0%;border-radius:999px;background:linear-gradient(90deg,var(--gold),var(--gold2));transition:width .45s ease;box-shadow:0 0 34px rgba(226,184,113,.38)}
    .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:24px 0}
    .stat{border:1px solid rgba(226,184,113,.15);border-radius:18px;background:rgba(0,0,0,.22);padding:16px}
    .stat b{display:block;font-family:'Barlow Condensed';font-size:30px;color:var(--gold2);line-height:1}
    .stat span{display:block;margin-top:7px;color:var(--muted);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}
    .actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}
    button,a.btn{appearance:none;border:1px solid rgba(226,184,113,.55);background:rgba(0,0,0,.28);color:var(--gold2);min-height:50px;padding:0 18px;border-radius:12px;font-weight:900;text-transform:uppercase;letter-spacing:.07em;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:10px}
    button.primary{background:linear-gradient(135deg,var(--gold2),var(--gold));color:#111;border-color:transparent}
    button:disabled{opacity:.5;cursor:not-allowed}
    .log{margin-top:20px;border:1px solid rgba(226,184,113,.14);border-radius:18px;background:rgba(0,0,0,.28);padding:16px;color:var(--muted);font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px;min-height:92px;white-space:pre-wrap;overflow:auto}
    .ok{color:var(--ok)}.bad{color:var(--bad)}.spin{display:inline-block;width:10px;height:10px;border:2px solid rgba(226,184,113,.25);border-top-color:var(--gold2);border-radius:50%;animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
    @media(max-width:760px){body{padding:14px}.card{padding:24px}.top{display:block}.badge{display:inline-flex;margin-top:18px}.grid{grid-template-columns:repeat(2,1fr)}.percent strong{font-size:44px}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="brand"><div class="mark">DT</div><span>Instagram Gallery Sync</span></div>
    <div class="card">
      <div class="content">
        <div class="top">
          <div>
            <h1>Sincronizar Instagram</h1>
            <p>Dispara o sync em background e mostra o progresso aqui, numa tela só, porque abrir três URLs parecia ritual de repartição pública.</p>
          </div>
          <div class="badge" id="badge">Aguardando</div>
        </div>

        <div class="meter">
          <div class="percent">
            <strong id="percent">0%</strong>
            <span id="phase">Parado</span>
          </div>
          <div class="bar"><div class="fill" id="fill"></div></div>
        </div>

        <div class="grid">
          <div class="stat"><b id="found">0</b><span>Encontrados</span></div>
          <div class="stat"><b id="downloaded">0</b><span>Baixados</span></div>
          <div class="stat"><b id="failed">0</b><span>Falhas</span></div>
          <div class="stat"><b id="pages">0</b><span>Páginas API</span></div>
        </div>

        <div class="actions">
          <button class="primary" id="startBtn" type="button">Iniciar sincronização</button>
          <button id="refreshBtn" type="button">Atualizar status</button>
          <a class="btn" href="/" target="_blank" rel="noopener">Abrir site</a>
          <a class="btn" href="/instagram/feed.php" target="_blank" rel="noopener">Ver JSON</a>
        </div>

        <div class="log" id="log">Pronto pra sincronizar. Sim, agora com barra, porque sem barra ninguém acredita que computador está trabalhando.</div>
      </div>
    </div>
  </div>

<script>
const el = id => document.getElementById(id);
const startBtn = el('startBtn');
const refreshBtn = el('refreshBtn');
let timer = null;

function number(v){ return Number.isFinite(Number(v)) ? Number(v) : 0; }
function text(v, fallback='0'){ return (v === undefined || v === null || v === '') ? fallback : String(v); }

function render(status){
  const running = !!status.running;
  const ok = status.ok !== false;
  const result = status.result || {};
  const gallery = status.last_gallery_sync || result.gallery_sync || {};
  const percent = Math.max(0, Math.min(100, number(status.percent ?? (running ? 5 : (ok ? 100 : 0)))));

  el('fill').style.width = percent + '%';
  el('percent').textContent = Math.round(percent) + '%';
  el('phase').innerHTML = running ? '<span class="spin"></span> ' + text(status.phase, 'Rodando') : text(status.phase, ok ? 'Concluído' : 'Erro');
  el('badge').textContent = running ? 'Rodando' : (ok ? 'Pronto' : 'Erro');
  el('badge').className = 'badge ' + (ok ? 'ok' : 'bad');

  el('found').textContent = text(status.found ?? result.count ?? status.last_feed_count, '0');
  el('downloaded').textContent = text(status.downloaded ?? gallery.downloaded, '0');
  el('failed').textContent = text(status.failed ?? gallery.failed, '0');
  el('pages').textContent = text(status.pages_fetched ?? result.pages_fetched, '0');

  const current = status.current && status.total ? `\nItem atual: ${status.current}/${status.total}` : '';
  const updated = status.updated_at ? `\nAtualizado: ${status.updated_at}` : '';
  const feed = status.last_feed_updated_at ? `\nÚltimo feed: ${status.last_feed_updated_at}` : '';
  const message = status.message || (running ? 'Sincronizando...' : 'Aguardando comando.');
  const errors = status.error ? `\nErro: ${status.error}` : '';

  el('log').textContent = `${message}${current}${updated}${feed}${errors}`;
  startBtn.disabled = running;

  if(running && !timer){ timer = setInterval(loadStatus, 1500); }
  if(!running && timer){ clearInterval(timer); timer = null; }
}

async function loadStatus(){
  try{
    const r = await fetch('/instagram/sync-status.php?ts=' + Date.now(), {cache:'no-store'});
    render(await r.json());
  }catch(e){
    el('log').textContent = 'Erro ao consultar status: ' + e.message;
  }
}

async function startSync(){
  startBtn.disabled = true;
  el('log').textContent = 'Disparando sincronização em background...';
  try{
    const r = await fetch('/instagram/sync-start.php?ts=' + Date.now(), {cache:'no-store'});
    const data = await r.json();
    render(data);
    if(!timer) timer = setInterval(loadStatus, 1500);
    setTimeout(loadStatus, 800);
  }catch(e){
    startBtn.disabled = false;
    el('log').textContent = 'Erro ao iniciar sync: ' + e.message;
  }
}

startBtn.addEventListener('click', startSync);
refreshBtn.addEventListener('click', loadStatus);
loadStatus();
</script>
</body>
</html>
