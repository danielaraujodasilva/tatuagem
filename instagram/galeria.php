<?php
declare(strict_types=1);
/**
 * Galeria do acervo padronizado (MobileFirst).
 * URL: https://danieltatuador.com/instagram/galeria.php?k=SEU_TOKEN
 *
 * Le a lista via acervo.php?a=lista e monta um grid de miniaturas.
 * O arquivo grande so e carregado quando o usuario abre o item.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=utf-8');

$keyFile = __DIR__ . '/progresso-key.local.php';
$token   = is_file($keyFile) ? (string)(require $keyFile) : '';
$k       = (string)($_GET['k'] ?? '');
if ($token !== '' && !hash_equals($token, $k)) {
    http_response_code(403);
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1"><title>Acesso restrito</title></head>';
    echo '<body style="background:#0d0d11;color:#e9e9ee;font-family:system-ui,sans-serif;padding:2rem">';
    echo '<h2>Acesso restrito</h2><p>Link invalido. Use o endereco completo com o codigo <code>?k=</code>.</p></body></html>';
    exit;
}
$KV = json_encode($k, JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0d0d11">
<title>Galeria - Acervo Instagram</title>
<style>
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html, body { margin:0; background:#0d0d11; color:#e9e9ee;
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
  body { padding:0 0 88px; }

  .top { position:sticky; top:0; z-index:30; background:#0d0d11ee; backdrop-filter:blur(12px);
         border-bottom:1px solid #22222c; padding:12px 12px 10px; }
  .top h1 { font-size:1.02rem; margin:0 0 2px; font-weight:600; }
  .top .sub { font-size:.74rem; color:#8b8b99; }
  .top .sub b { color:#a9a9ba; font-weight:600; }

  .bar { display:flex; gap:6px; margin-top:10px; align-items:center; overflow-x:auto;
         scrollbar-width:none; padding-bottom:2px; }
  .bar::-webkit-scrollbar { display:none; }
  .chip { flex:0 0 auto; font-size:.78rem; padding:7px 13px; border-radius:999px; cursor:pointer;
          background:#191920; border:1px solid #2a2a36; color:#a9a9ba; }
  .chip.on { background:#2563eb22; border-color:#2563eb88; color:#93b4fd; font-weight:600; }
  .chip.sp { margin-left:auto; }

  .grid { display:grid; gap:5px; padding:8px;
          grid-template-columns:repeat(auto-fill,minmax(104px,1fr)); }
  @media (min-width:520px) { .grid { grid-template-columns:repeat(auto-fill,minmax(132px,1fr)); gap:7px; padding:10px; } }

  .tile { position:relative; aspect-ratio:1/1; border-radius:9px; overflow:hidden; background:#16161c;
          cursor:pointer; border:1px solid #22222c; }
  .tile img { width:100%; height:100%; object-fit:cover; display:block; }
  .tile .sembt { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                 font-size:.58rem; color:#6f6f7d; text-align:center; padding:6px; }
  .tile .tag { position:absolute; left:5px; top:5px; font-size:.62rem; font-weight:700;
               background:#000000aa; color:#fff; padding:2px 6px; border-radius:6px; letter-spacing:.02em; }
  .tile .mb { position:absolute; right:5px; bottom:5px; font-size:.6rem; color:#d6d6e0;
              background:#000000a8; padding:2px 5px; border-radius:5px; }
  .tile .dl { position:absolute; right:4px; top:4px; width:28px; height:28px; border-radius:50%;
              background:#000000a0; color:#fff; display:flex; align-items:center; justify-content:center;
              font-size:.85rem; line-height:1; }
  .tile .dl:active { background:#2563eb; }
  .tile .chk { position:absolute; left:5px; bottom:5px; width:21px; height:21px; border-radius:50%;
               border:2px solid #ffffffcc; background:#00000060; display:none;
               align-items:center; justify-content:center; font-size:.72rem; color:#fff; }
  body.modo .tile .chk { display:flex; }
  body.modo .tile .dl { display:none; }
  .tile.sel { outline:2px solid #3b82f6; outline-offset:-2px; }
  .tile.sel .chk { background:#3b82f6; border-color:#3b82f6; }
  .tile.sel .chk::after { content:"\2713"; }

  .selbar { position:fixed; left:0; right:0; bottom:0; z-index:40; background:#16161cf2;
            backdrop-filter:blur(12px); border-top:1px solid #2a2a36; padding:10px 12px calc(10px + env(safe-area-inset-bottom));
            display:none; gap:8px; align-items:center; }
  body.modo .selbar { display:flex; }
  .selbar .cnt { font-size:.78rem; color:#a9a9ba; margin-right:auto; white-space:nowrap; }
  .btn { font:inherit; font-size:.8rem; font-weight:600; padding:10px 14px; border-radius:10px;
         border:1px solid #2f2f3d; background:#20202a; color:#e9e9ee; cursor:pointer; white-space:nowrap; }
  .btn:active { transform:scale(.97); }
  .btn.pri { background:#2563eb; border-color:#2563eb; }
  .btn.danger { background:#7f1d1d33; border-color:#b91c1c88; color:#fca5a5; }
  .btn[disabled] { opacity:.45; }

  .view { position:fixed; inset:0; z-index:60; background:#000000f5; display:none;
          flex-direction:column; }
  .view.on { display:flex; }
  .vt { display:flex; align-items:center; gap:10px; padding:10px 12px;
        padding-top:calc(10px + env(safe-area-inset-top)); border-bottom:1px solid #ffffff1a; }
  .vt .nm { font-size:.76rem; color:#c9c9d6; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .vt .x { margin-left:auto; background:none; border:0; color:#fff; font-size:1.4rem; line-height:1; padding:4px 6px; }
  .vb { flex:1; display:flex; align-items:center; justify-content:center; position:relative; min-height:0; }
  .vb img, .vb video { max-width:100%; max-height:100%; display:block; }
  .vb .nav { position:absolute; top:50%; transform:translateY(-50%); width:44px; height:64px;
             background:#ffffff14; border:0; color:#fff; font-size:1.6rem; border-radius:10px; }
  .vb .nav.p { left:8px; } .vb .nav.n { right:8px; }
  .vb .spin { color:#8b8b99; font-size:.8rem; }
  .vf { padding:10px 12px calc(12px + env(safe-area-inset-bottom)); border-top:1px solid #ffffff1a;
        display:flex; gap:8px; align-items:center; }
  .vf .inf { font-size:.7rem; color:#8b8b99; margin-right:auto; }

  .toast { position:fixed; left:50%; bottom:96px; transform:translateX(-50%); z-index:80;
           background:#24242ef2; border:1px solid #3a3a4a; color:#e9e9ee; font-size:.78rem;
           padding:10px 16px; border-radius:999px; max-width:90vw; text-align:center; display:none; }
  .toast.on { display:block; }
  .aviso { position:fixed; left:50%; bottom:20px; transform:translateX(-50%); z-index:50; display:none;
           background:#2563eb; color:#fff; font-size:.76rem; font-weight:600; padding:9px 16px;
           border-radius:999px; border:0; }
  .aviso.on { display:block; }
  .vazio { text-align:center; color:#6f6f7d; font-size:.82rem; padding:40px 20px; }
  .foot { text-align:center; color:#5f5f6d; font-size:.7rem; padding:14px 12px 0; line-height:1.8; }
  .foot a { color:#7f9fd8; }
</style>
</head>
<body>

<div class="top">
  <h1>Acervo Instagram</h1>
  <div class="sub" id="resumo">Carregando...</div>
  <div class="bar">
    <div class="chip on" data-f="todos">Todas</div>
    <div class="chip" data-f="foto">Fotos</div>
    <div class="chip" data-f="video">V&iacute;deos</div>
    <div class="chip sp" id="bmodo">Selecionar</div>
  </div>
</div>

<div class="grid" id="grid"></div>
<div class="vazio" id="vazio" style="display:none">Nada por aqui ainda.</div>

<div class="foot">
  Toque na foto para ver em tamanho real &middot; o arquivo grande s&oacute; carrega ao abrir<br>
  <a href="progresso.php?k=<?= rawurlencode($k) ?>">ver progresso do processamento</a>
</div>

<div class="selbar">
  <span class="cnt" id="cnt">0 selecionadas</span>
  <button class="btn" id="bzipall">Baixar tudo</button>
  <button class="btn pri" id="bzip">Baixar</button>
  <button class="btn danger" id="bdel">Apagar</button>
  <button class="btn" id="bcancel">Sair</button>
</div>

<div class="view" id="view">
  <div class="vt">
    <span class="nm" id="vnm"></span>
    <button class="x" id="vx">&times;</button>
  </div>
  <div class="vb" id="vb"><span class="spin">carregando...</span></div>
  <div class="vf">
    <span class="inf" id="vinf"></span>
    <button class="btn" id="vdl">Baixar</button>
    <button class="btn danger" id="vdel">Apagar</button>
  </div>
</div>

<button class="aviso" id="aviso">Novos arquivos prontos &middot; atualizar</button>
<div class="toast" id="toast"></div>
<form id="formzip" method="post" target="ziptarget" style="display:none"></form>
<iframe name="ziptarget" style="display:none"></iframe>

<script>
const K = <?= $KV ?>;
const KQ = encodeURIComponent(K);
const API = "acervo.php";
let itens = [], filtro = "todos", sel = new Set(), vistos = -1, idxAberto = -1;

const $ = (id) => document.getElementById(id);
const enc = (n) => encodeURIComponent(n);
const tUrl = (n) => API + "?a=thumb&k=" + KQ + "&f=" + enc(n);
const aUrl = (n, dl) => API + "?a=arquivo&k=" + KQ + "&f=" + enc(n) + (dl ? "&dl=1" : "");
const mb = (b) => b >= 1048576 ? (b / 1048576).toFixed(1) + " MB" : Math.max(1, Math.round(b / 1024)) + " KB";

let toastT = null;
function toast(msg) {
  const t = $("toast"); t.textContent = msg; t.classList.add("on");
  clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove("on"), 3200);
}

function listaFiltrada() {
  return filtro === "todos" ? itens : itens.filter(i => i.t === filtro);
}

function render() {
  const lista = listaFiltrada();
  const g = $("grid");
  g.innerHTML = "";
  $("vazio").style.display = lista.length ? "none" : "block";
  const frag = document.createDocumentFragment();
  for (const it of lista) {
    const d = document.createElement("div");
    d.className = "tile" + (sel.has(it.n) ? " sel" : "");
    d.dataset.n = it.n;
    const img = document.createElement("img");
    img.loading = "lazy"; img.decoding = "async";
    img.src = tUrl(it.n); img.alt = it.n;
    img.onerror = () => {
      img.onerror = null; img.style.display = "none";
      const s = document.createElement("span");
      s.className = "sembt"; s.textContent = "sem miniatura";
      d.insertBefore(s, d.firstChild);
    };
    d.appendChild(img);
    if (it.t === "video") {
      const tg = document.createElement("span"); tg.className = "tag"; tg.textContent = "\u25B6 v\u00EDdeo";
      d.appendChild(tg);
    }
    const sz = document.createElement("span"); sz.className = "mb"; sz.textContent = mb(it.b);
    d.appendChild(sz);
    const dl = document.createElement("span"); dl.className = "dl"; dl.textContent = "\u2193"; dl.title = "Baixar";
    d.appendChild(dl);
    const ck = document.createElement("span"); ck.className = "chk";
    d.appendChild(ck);
    frag.appendChild(d);
  }
  g.appendChild(frag);
  const total = itens.length;
  const bytes = itens.reduce((s, i) => s + i.b, 0);
  const f = itens.filter(i => i.t === "foto").length;
  $("resumo").innerHTML = "<b>" + total + "</b> arquivos &middot; " + f + " fotos &middot; " +
    (total - f) + " v\u00EDdeos &middot; <b>" + (bytes / 1048576).toFixed(0) + " MB</b>";
  atualizaCnt();
}

function atualizaCnt() {
  $("cnt").textContent = sel.size + (sel.size === 1 ? " selecionada" : " selecionadas");
  $("bzip").disabled = sel.size === 0;
  $("bdel").disabled = sel.size === 0;
}

async function carregar(silencioso) {
  try {
    const r = await fetch(API + "?a=lista&k=" + KQ + "&t=" + Date.now(), { cache: "no-store" });
    const d = await r.json();
    if (!d || !d.itens) throw new Error("resposta invalida");
    itens = d.itens;
    for (const n of Array.from(sel)) if (!itens.some(i => i.n === n)) sel.delete(n);
    render();
    $("aviso").classList.remove("on");
    vistos = itens.length;
    if (!silencioso) toast(itens.length + " arquivos carregados");
  } catch (e) {
    $("resumo").textContent = "Erro ao carregar a lista. Toque para tentar de novo.";
    $("resumo").onclick = () => carregar(false);
  }
}

setInterval(async () => {
  if (document.hidden || sel.size || idxAberto >= 0) return;
  try {
    const r = await fetch(API + "?a=lista&k=" + KQ + "&t=" + Date.now(), { cache: "no-store" });
    const d = await r.json();
    if (d && d.itens && d.itens.length !== vistos) $("aviso").classList.add("on");
  } catch (e) {}
}, 60000);

$("aviso").onclick = () => carregar(false);

document.querySelectorAll(".chip[data-f]").forEach(c => c.onclick = () => {
  document.querySelectorAll(".chip[data-f]").forEach(x => x.classList.remove("on"));
  c.classList.add("on"); filtro = c.dataset.f; render();
});

$("bmodo").onclick = () => {
  const m = document.body.classList.toggle("modo");
  $("bmodo").textContent = m ? "Cancelar sele\u00E7\u00E3o" : "Selecionar";
  $("bmodo").classList.toggle("on", m);
  if (!m) { sel.clear(); render(); } else atualizaCnt();
};
$("bcancel").onclick = () => $("bmodo").click();

$("grid").addEventListener("click", (e) => {
  const tile = e.target.closest(".tile");
  if (!tile) return;
  const n = tile.dataset.n;
  if (e.target.classList.contains("dl")) { baixarUm(n); return; }
  if (document.body.classList.contains("modo")) {
    if (sel.has(n)) sel.delete(n); else sel.add(n);
    tile.classList.toggle("sel", sel.has(n));
    atualizaCnt();
    return;
  }
  abrir(n);
});

/* --------------------------------------------------------------- downloads */

function baixarUm(n) {
  const a = document.createElement("a");
  a.href = aUrl(n, true); a.download = n;
  document.body.appendChild(a); a.click(); a.remove();
}

function enviarZip(nomes) {
  const f = $("formzip");
  f.action = API + "?a=zip&k=" + KQ;
  f.innerHTML = "";
  for (const n of nomes) {
    const i = document.createElement("input");
    i.type = "hidden"; i.name = "f[]"; i.value = n;
    f.appendChild(i);
  }
  f.submit();
}

const espera = (ms) => new Promise(r => setTimeout(r, ms));

function gruposZip(nomes) {
  const porNome = new Map(itens.map(i => [i.n, i]));
  const g = []; let atual = [], bytes = 0;
  for (const n of nomes) {
    const b = (porNome.get(n) || { b: 0 }).b;
    if (atual.length >= 120 || (bytes + b > 700 * 1048576 && atual.length)) {
      g.push(atual); atual = []; bytes = 0;
    }
    atual.push(n); bytes += b;
  }
  if (atual.length) g.push(atual);
  return g;
}

async function baixarLote(nomes, rotulo) {
  if (!nomes.length) { toast("Nada selecionado"); return; }
  const grupos = gruposZip(nomes);
  if (grupos.length > 1) {
    const ok = confirm(rotulo + ": " + nomes.length + " arquivos ser\u00E3o baixados em " + grupos.length +
      " partes.\nO navegador pode pedir para permitir v\u00E1rios downloads \u2014 aceite para receber tudo.");
    if (!ok) return;
  }
  for (let i = 0; i < grupos.length; i++) {
    if (grupos.length > 1) toast("Baixando parte " + (i + 1) + " de " + grupos.length + "...");
    else toast("Preparando arquivo...");
    enviarZip(grupos[i]);
    await espera(3000);
  }
}

$("bzip").onclick = () => baixarLote(Array.from(sel), "Baixar selecionadas");
$("bzipall").onclick = () => baixarLote(listaFiltrada().map(i => i.n), filtro === "todos" ? "Baixar tudo" : "Baixar este filtro");

/* ----------------------------------------------------------------- apagar */

async function apagar(nomes) {
  if (!nomes.length) return;
  const ok = confirm("Apagar " + nomes.length + (nomes.length === 1 ? " arquivo?" : " arquivos?") +
    "\n\nEles v\u00E3o para a pasta _lixeira (d\u00E1 para recuperar depois).");
  if (!ok) return;
  const fd = new FormData();
  for (const n of nomes) fd.append("f[]", n);
  try {
    const r = await fetch(API + "?a=apagar&k=" + KQ, { method: "POST", body: fd });
    const d = await r.json();
    if (!d.ok) { toast("Falha: " + (d.erro || "erro")); return; }
    sel.clear(); fecharViewer();
    itens = itens.filter(i => !nomes.includes(i.n));
    render();
    toast(d.movidos + (d.movidos === 1 ? " arquivo apagado" : " arquivos apagados") +
      (d.falhas && d.falhas.length ? " (" + d.falhas.length + " falharam)" : "") + " \u2014 na _lixeira");
  } catch (e) { toast("Erro ao apagar"); }
}

$("bdel").onclick = () => apagar(Array.from(sel));

/* ----------------------------------------------------------------- viewer */

function abrir(n) {
  const lista = listaFiltrada();
  idxAberto = lista.findIndex(i => i.n === n);
  if (idxAberto < 0) return;
  mostraViewer();
}

function mostraViewer() {
  const lista = listaFiltrada();
  const it = lista[idxAberto];
  if (!it) return;
  const vb = $("vb");
  vb.innerHTML = '<span class="spin">carregando...</span>';
  $("vnm").textContent = it.n;
  $("vinf").textContent = mb(it.b) + (it.w ? " \u00B7 " + it.w + "\u00D7" + it.h : "");
  $("view").classList.add("on");
  document.body.style.overflow = "hidden";

  let media;
  if (it.t === "video") {
    media = document.createElement("video");
    media.controls = true; media.playsInline = true; media.preload = "metadata";
    media.src = aUrl(it.n, false);
  } else {
    media = document.createElement("img");
    media.src = aUrl(it.n, false);
    media.alt = it.n;
  }
  media.onload = media.onloadeddata = () => { vb.innerHTML = ""; vb.appendChild(media); };
  media.onerror = () => { vb.innerHTML = '<span class="spin">n\u00E3o foi poss\u00EDvel carregar</span>'; };

  if (lista.length > 1) {
    if (idxAberto > 0) {
      const b = document.createElement("button"); b.className = "nav p"; b.textContent = "\u2039";
      b.onclick = () => { idxAberto--; mostraViewer(); };
      vb.appendChild(b);
    }
    if (idxAberto < lista.length - 1) {
      const b = document.createElement("button"); b.className = "nav n"; b.textContent = "\u203A";
      b.onclick = () => { idxAberto++; mostraViewer(); };
      vb.appendChild(b);
    }
  }
  const chave = encodeURIComponent(it.n);
  history.replaceState(null, "", "#" + chave);
}

function fecharViewer() {
  $("view").classList.remove("on");
  $("vb").innerHTML = "";
  document.body.style.overflow = "";
  idxAberto = -1;
  history.replaceState(null, "", location.pathname + location.search);
}

$("vx").onclick = fecharViewer;
$("view").addEventListener("click", (e) => { if (e.target.id === "vb" || e.target.id === "view") fecharViewer(); });
document.addEventListener("keydown", (e) => {
  if (idxAberto < 0) return;
  if (e.key === "Escape") fecharViewer();
  if (e.key === "ArrowLeft" && idxAberto > 0) { idxAberto--; mostraViewer(); }
  if (e.key === "ArrowRight" && idxAberto < listaFiltrada().length - 1) { idxAberto++; mostraViewer(); }
});
$("vdl").onclick = () => { const it = listaFiltrada()[idxAberto]; if (it) baixarUm(it.n); };
$("vdel").onclick = () => { const it = listaFiltrada()[idxAberto]; if (it) apagar([it.n]); };

/* ------------------------------------------------------------------- init */

window.addEventListener("hashchange", () => {
  const h = location.hash.slice(1);
  if (!h) { if (idxAberto >= 0) fecharViewer(); return; }
  const n = decodeURIComponent(h);
  if (itens.some(i => i.n === n)) abrir(n);
});

if (location.hash.length > 1) {
  const n = decodeURIComponent(location.hash.slice(1));
  const iv = setInterval(() => {
    if (!itens.length) return;
    clearInterval(iv);
    if (itens.some(i => i.n === n)) abrir(n);
  }, 200);
  setTimeout(() => clearInterval(iv), 8000);
}
carregar(true);
</script>
</body>
</html>

