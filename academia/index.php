<?php
declare(strict_types=1);

/**
 * Grade de horarios da academia - unidade Artur Alvim.
 *
 * Mobile-first, clean/minimalista (tema claro).
 *
 * Fonte unica de verdade: dados/grade.json (mesma pasta).
 * A pagina e somente-leitura: filtra por dia, horario, modalidade e busca livre.
 *
 * Como responder perguntas do tipo "amanha tem natacao que horario?":
 *   - "aulas" = grid (Natacao, Ginastica, Hidro): modalidade/tipo/hora/fim/dias
 *     dias: 2=seg, 3=ter, 4=qua, 5=qui, 6=sex, 7=sab
 *   - "modalidades_extra" = fora do grid (Taekwondo, Muay Thai, Karate, Funcional Kids)
 *   - "livre" = Musculacao (horario livre)
 *   - a pagina aceita ?dia=hoje|amanha|2..7&grupo=natacao|ginastica|hidro&q=texto
 */

$arquivo = __DIR__ . '/dados/grade.json';
$grade = json_decode((string)file_get_contents($arquivo), true);
if (!is_array($grade)) {
    http_response_code(500);
    exit('Nao foi possivel ler dados/grade.json');
}

$hojeId = (int)date('N'); // 1=segunda ... 7=domingo
$diasPorId = [];
foreach ($grade['dias'] as $d) {
    $diasPorId[(int)$d['id']] = $d;
}

// Recorte inicial recebido por URL (mesma lingua que o assistente usa ao responder)
$diaParam   = strtolower(trim((string)($_GET['dia'] ?? '')));
$grupoParam = strtolower(trim((string)($_GET['grupo'] ?? '')));
$qParam     = trim((string)($_GET['q'] ?? ''));

$diaInicial = $hojeId;
if ($diaParam === 'hoje') {
    $diaInicial = $hojeId;
} elseif ($diaParam === 'amanha' || $diaParam === 'amanhã') {
    $diaInicial = $hojeId >= 7 ? 2 : $hojeId + 1;
} elseif (ctype_digit($diaParam) && $diaParam >= '2' && $diaParam <= '7') {
    $diaInicial = (int)$diaParam;
}

/**
 * JSON seguro para dentro de <script>.
 * Sem isso um horario como "09:30" pode virar a sequencia </script> (o "</" +
 * "script" aparece em "...09:30","fim...") e corta o script no meio: a tabela
 * some e sobra so o cabecalho. Escapa <, >, & e as barras unicode.
 */
function json_para_script($valor): string
{
    return str_replace(
        ['<', '>', '&', "\u{2028}", "\u{2029}"],
        ['\\u003C', '\\u003E', '\\u0026', '\\u2028', '\\u2029'],
        (string)json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

// Rotulos amigaveis para os tipos de aula (mostrados no card)
$diaInicialRotulo = $diaParam === 'hoje' ? 'hoje' : ($diaParam === 'amanha' ? 'amanha' : (string)$diaInicial);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ffffff">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Academia">
<title>Grade de Horários — Artur Alvim</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏊</text></svg>">
<style>
  :root{
    --bg:#f7f8fa;
    --card:#ffffff;
    --txt:#12141a;
    --mut:#6b7280;
    --line:#e6e8ec;
    --line2:#eef0f4;
    --nat:#2563eb;   --nat-bg:#eff4ff;
    --gin:#7c3aed;   --gin-bg:#f5f0ff;
    --hid:#0891b2;   --hid-bg:#ecfbff;
    --acc:#12141a;
    --ok:#059669;    --ok-bg:#ecfdf5;
    --r:14px;
    --r-sm:10px;
    --shadow:0 1px 2px rgba(16,20,30,.06), 0 6px 20px -12px rgba(16,20,30,.18);
    --safe-b:env(safe-area-inset-bottom, 0px);
    --safe-t:env(safe-area-inset-top, 0px);
  }
  @media (prefers-color-scheme: dark){
    :root{
      --bg:#f7f8fa; --card:#ffffff; --txt:#12141a; --mut:#6b7280;
      --line:#e6e8ec; --line2:#eef0f4;
      --nat:#2563eb; --nat-bg:#eff4ff;
      --gin:#7c3aed; --gin-bg:#f5f0ff;
      --hid:#0891b2; --hid-bg:#ecfbff;
      --acc:#12141a;
      --ok:#059669; --ok-bg:#ecfdf5;
      --shadow:0 1px 2px rgba(16,20,30,.06), 0 6px 20px -12px rgba(16,20,30,.18);
    }
  }
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
  html,body{margin:0;padding:0}
  body{
    background:var(--bg);color:var(--txt);
    font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
    -webkit-font-smoothing:antialiased;
    padding:calc(14px + var(--safe-t)) 14px calc(96px + var(--safe-b));
  }
  .wrap{max-width:680px;margin:0 auto}

  /* ---------- cabecalho ---------- */
  .top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
  .ttl{font-size:19px;font-weight:700;letter-spacing:-.3px;margin:0}
  .ttl span{display:block;font-size:12.5px;font-weight:500;color:var(--mut);letter-spacing:0;margin-top:1px}

  /* ---------- cartao "agora" ---------- */
  .agora{
    background:var(--card);border:1px solid var(--line);border-radius:var(--r);
    padding:12px 14px;margin-bottom:12px;box-shadow:var(--shadow);
    display:flex;gap:11px;align-items:flex-start
  }
  .dot{width:9px;height:9px;border-radius:50%;background:var(--ok);flex:0 0 auto;margin-top:6px;
    box-shadow:0 0 0 0 rgba(5,150,105,.5);animation:pulse 2s infinite}
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(5,150,105,.45)}70%{box-shadow:0 0 0 9px rgba(5,150,105,0)}100%{box-shadow:0 0 0 0 rgba(5,150,105,0)}}
  .agora .lbl{font-size:11px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--mut)}
  .agora .val{font-size:15px;font-weight:600;margin-top:2px;line-height:1.4}
  .agora .val b{font-weight:700}
  .agora .hora{font-variant-numeric:tabular-nums}

  /* ---------- filtro de dias (segmented, rola no dedo) ---------- */
  .dias{
    display:flex;gap:6px;overflow-x:auto;padding:4px;margin:0 -4px 12px;
    scrollbar-width:none;-webkit-overflow-scrolling:touch
  }
  .dias::-webkit-scrollbar{display:none}
  .dchip{
    flex:0 0 auto;border:1px solid var(--line);background:var(--card);color:var(--mut);
    border-radius:999px;padding:9px 15px;font-size:14px;font-weight:600;cursor:pointer;
    transition:background .15s,color .15s,border-color .15s;min-height:40px;
    display:inline-flex;align-items:center;gap:5px
  }
  .dchip.on{background:var(--acc);border-color:var(--acc);color:var(--card)}
  .dchip small{font-size:11px;opacity:.7;font-weight:500}
  .dchip.on small{opacity:.85}

  /* ---------- busca + atividade ---------- */
  .campo{position:relative;margin-bottom:10px}
  .campo svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);opacity:.4;pointer-events:none}
  input[type=search]{
    width:100%;background:var(--card);border:1px solid var(--line);color:var(--txt);
    border-radius:12px;padding:13px 14px 13px 40px;font-size:16px;outline:none;
    font-family:inherit;min-height:48px
  }
  input[type=search]::placeholder{color:var(--mut)}
  input[type=search]:focus{border-color:var(--acc);box-shadow:0 0 0 3px rgba(18,20,26,.07)}

  .grupos{display:flex;gap:7px;overflow-x:auto;padding:2px 0 10px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
  .grupos::-webkit-scrollbar{display:none}
  .gchip{
    flex:0 0 auto;border:1px solid var(--line);background:var(--card);color:var(--mut);
    border-radius:999px;padding:8px 14px;font-size:13.5px;font-weight:600;cursor:pointer;
    min-height:38px;display:inline-flex;align-items:center;gap:6px;
    transition:background .15s,color .15s,border-color .15s
  }
  .gchip.on{color:var(--txt);border-color:currentColor;background:var(--card)}
  .gchip.on[data-grupo=natacao]{color:var(--nat);background:var(--nat-bg)}
  .gchip.on[data-grupo=ginastica]{color:var(--gin);background:var(--gin-bg)}
  .gchip.on[data-grupo=hidro]{color:var(--hid);background:var(--hid-bg)}
  .gchip.on[data-grupo=todos]{background:var(--acc);color:var(--card);border-color:var(--acc)}

  /* ---------- lista de aulas (o coracao no celular) ---------- */
  .cabeca-lista{display:flex;align-items:baseline;justify-content:space-between;margin:6px 2px 8px}
  .cabeca-lista h2{font-size:14px;font-weight:700;margin:0;letter-spacing:-.1px}
  .cabeca-lista .conta{font-size:12.5px;color:var(--mut)}
  #lista{display:flex;flex-direction:column;gap:8px}

  .item{
    background:var(--card);border:1px solid var(--line);border-radius:var(--r);
    padding:12px 14px;display:flex;gap:12px;align-items:center;box-shadow:var(--shadow)
  }
  .item.hj{border-color:var(--ok);background:var(--ok-bg)}
  .hora{
    flex:0 0 auto;font-variant-numeric:tabular-nums;font-weight:700;font-size:15px;
    letter-spacing:-.2px;min-width:96px;display:flex;flex-direction:column;line-height:1.25
  }
  .hora small{font-size:11.5px;font-weight:500;color:var(--mut);letter-spacing:0}
  .info{flex:1;min-width:0}
  .info .nome{font-weight:700;font-size:15.5px;letter-spacing:-.2px;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
  .info .meta{font-size:12.5px;color:var(--mut);margin-top:2px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
  .tag{
    font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;
    background:var(--line2);color:var(--mut);letter-spacing:.2px
  }
  .marcador{
    display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;
    color:var(--ok);background:var(--ok-bg);border-radius:999px;padding:3px 9px
  }
  .sitio{flex:0 0 auto}

  .grupo-bloco{margin-bottom:6px}
  .grupo-bloco > h3{
    font-size:12px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
    color:var(--mut);margin:16px 2px 8px
  }
  .vazio{
    background:var(--card);border:1px dashed var(--line);border-radius:var(--r);
    padding:30px 18px;text-align:center;color:var(--mut);font-size:14px
  }

  footer{margin-top:26px;text-align:center;color:var(--mut);font-size:11.5px;line-height:1.7}

  /* ---------- desktop: so centraliza e abre um pouco ---------- */
  @media (min-width:700px){
    body{padding-top:28px}
    .ttl{font-size:22px}
    .wrap{max-width:760px}
    #lista{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:9px}
    .dias,.grupos{overflow:visible;flex-wrap:wrap}
    .cabeca-lista h2{font-size:15px}
  }
  @media (prefers-reduced-motion:reduce){ .dot{animation:none} }
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <h1 class="ttl">Grade de Horários
      <span>Unidade Artur Alvim</span>
    </h1>
  </div>

  <div class="agora" id="caixaAgora" hidden>
    <div class="dot"></div>
    <div>
      <div class="lbl">Agora</div>
      <div class="val" id="txtAgora"></div>
    </div>
  </div>

  <div class="dias" id="chipsDia" role="tablist" aria-label="Filtrar por dia"></div>

  <div class="campo">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
      <circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/>
    </svg>
    <input type="search" id="busca" inputmode="search" autocomplete="off"
           placeholder="Buscar: hidro, natacao infantil, pilates…"
           value="<?= htmlspecialchars($qParam) ?>">
  </div>

  <div class="grupos" id="chipsGrupo" aria-label="Filtrar por atividade"></div>

  <div class="cabeca-lista">
    <h2 id="tituloLista">Hoje</h2>
    <span class="conta" id="conta">—</span>
  </div>

  <div id="lista" aria-live="polite"></div>
  <div class="vazio" id="vazio" hidden>Nada encontrado com esse filtro.<br>Tente outro termo ou limpe a busca.</div>

  <footer>Fonte: grade impressa da academia.<br>Horários sujeitos a alteração — confirme na recepção.</footer>
</div>

<script>
const GRADE = <?= json_para_script($grade) ?>;
const HOJE = <?= (int)$hojeId ?>;
const DIA_PARAM = <?= json_para_script($diaInicialRotulo) ?>;
const GRUPO_PARAM = <?= json_para_script($grupoParam) ?>;

const diaPorId = {};
GRADE.dias.forEach(d => diaPorId[d.id] = d);

const modalidadePorId = {};
GRADE.modalidades.forEach(m => modalidadePorId[m.id] = m);

function nomeModalidade(id){
  if (id === 'musculacao') return GRADE.livre.nome;
  if (modalidadePorId[id]) return modalidadePorId[id].nome;
  const ex = GRADE.modalidades_extra.find(m => m.id === id);
  return ex ? ex.nome : id;
}

// Classe de cor por modalidade (para o ponto de cor no card)
function classeCor(id){
  if (id === 'natacao') return 'nat';
  if (id === 'ginastica') return 'gin';
  if (id === 'hidro') return 'hid';
  return '';
}

function normalizar(s){
  return (s||'').toString().toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
}
function paraMin(hhmm){ const p = hhmm.split(':'); return +p[0]*60 + +p[1]; }
function agoraMin(){ const d = new Date(); return d.getHours()*60 + d.getMinutes(); }
function hhmm(){
  const d = new Date();
  return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}

// ---- estado dos filtros -------------------------------------------------
let fDia = DIA_PARAM;      // 'hoje' | 'amanha' | 'todos' | '2'..'7'
let fGrupo = GRUPO_PARAM || 'todos';
let fTexto = normalizar(<?= json_para_script($qParam) ?>);

function idDiaResolvido(){
  if (fDia === 'hoje') return HOJE;
  if (fDia === 'amanha') return HOJE >= 7 ? 2 : HOJE + 1;
  if (fDia === 'todos') return null;
  return parseInt(fDia, 10);
}

// ---- monta a lista de aulas para o dia escolhido ------------------------
function aulasDoDia(idDia){
  const out = [];

  if (idDia === null){
    // "Todos": uma entrada por aula/dia, agrupada no render
    GRADE.aulas.forEach(a => a.dias.forEach(d => out.push({
      hora:a.hora, fim:a.fim, mod:a.modalidade, tipo:a.tipo, dia:d
    })));
    GRADE.modalidades_extra.forEach(m => m.faixas.forEach(f => f.dias.forEach(d => out.push({
      hora:f.hora, fim:f.fim, mod:m.id, tipo:f.tipo, dia:d
    }))));
    return out;
  }

  GRADE.aulas.forEach(a => {
    if (a.dias.includes(idDia)) out.push({ hora:a.hora, fim:a.fim, mod:a.modalidade, tipo:a.tipo, dia:idDia });
  });
  GRADE.modalidades_extra.forEach(m => m.faixas.forEach(f => {
    if (f.dias.includes(idDia)) out.push({ hora:f.hora, fim:f.fim, mod:m.id, tipo:f.tipo, dia:idDia });
  }));
  return out;
}

function passaFiltros(a){
  if (fGrupo !== 'todos' && a.mod !== fGrupo) return false;
  if (fTexto !== ''){
    const alvo = normalizar(nomeModalidade(a.mod) + ' ' + a.tipo + ' ' + a.mod);
    if (alvo.indexOf(fTexto) === -1) return false;
  }
  return true;
}

// ---- render -------------------------------------------------------------
function render(){
  const lista = document.getElementById('lista');
  const idDia = idDiaResolvido();
  const agora = agoraMin();
  const emHoje = (idDia === HOJE);

  let aulas = aulasDoDia(idDia).filter(passaFiltros);
  aulas.sort((x,y) => x.hora.localeCompare(y.hora));

  lista.innerHTML = '';

  // Musculacao (horario livre) entra como um card quando o filtro permite
  const mostraLivre = (fGrupo === 'todos' || fGrupo === 'musculacao')
    && (fTexto === '' || normalizar(GRADE.livre.nome + ' ' + GRADE.livre.descricao).indexOf(fTexto) !== -1);

  if (!aulas.length && !mostraLivre || (idDia === 7 && !aulas.length && !mostraLivre)){
    document.getElementById('vazio').hidden = false;
    document.getElementById('conta').textContent = '0 aulas';
    document.getElementById('tituloLista').textContent = titulo();
    return;
  }
  document.getElementById('vazio').hidden = true;

  const desenha = (arr, diaDeCada) => {
    arr.forEach(a => {
      const el = document.createElement('div');
      el.className = 'item' + ((emHoje && agora >= paraMin(a.hora) && agora < paraMin(a.fim)) ? ' hj' : '');

      const cor = classeCor(a.mod);
      const rodando = emHoje && agora >= paraMin(a.hora) && agora < paraMin(a.fim);

      el.innerHTML =
        '<div class="hora">' + a.hora + '<small>até ' + a.fim + '</small></div>' +
        '<div class="info">' +
          '<div class="nome">' +
            (cor ? '<span style="width:8px;height:8px;border-radius:50%;background:var(--'+cor+');display:inline-block"></span>' : '') +
            nomeModalidade(a.mod) +
          '</div>' +
          '<div class="meta">' +
            '<span>' + a.tipo + '</span>' +
            (diaDeCada ? '<span class="tag">' + (diaPorId[a.dia] ? diaPorId[a.dia].curto : '') + '</span>' : '') +
          '</div>' +
        '</div>' +
        (rodando ? '<div class="sitio"><span class="marcador">● agora</span></div>' : '');
      lista.appendChild(el);
    });
  };

  desenha(aulas, idDia === null);

  if (mostraLivre && idDia !== null){
    const el = document.createElement('div');
    el.className = 'item';
    el.innerHTML =
      '<div class="hora">Livre<small>o dia todo</small></div>' +
      '<div class="info"><div class="nome">🏋️ ' + GRADE.livre.nome + '</div>' +
      '<div class="meta"><span>Sem aula marcada — entra quando quiser</span></div></div>';
    lista.appendChild(el);
  }

  document.getElementById('conta').textContent = aulas.length + (aulas.length === 1 ? ' aula' : ' aulas');
  document.getElementById('tituloLista').textContent = titulo();
}

function titulo(){
  if (fDia === 'hoje') return 'Hoje — ' + (diaPorId[HOJE] ? diaPorId[HOJE].longo : '');
  if (fDia === 'amanha'){
    const id = HOJE >= 7 ? 2 : HOJE + 1;
    return 'Amanhã — ' + (diaPorId[id] ? diaPorId[id].longo : '');
  }
  if (fDia === 'todos') return 'Semana completa';
  const d = diaPorId[parseInt(fDia,10)];
  return d ? d.longo : 'Grade';
}

// ---- chips --------------------------------------------------------------
function montarChips(){
  const cd = document.getElementById('chipsDia');
  const hoje = diaPorId[HOJE];
  const amanhaId = HOJE >= 7 ? 2 : HOJE + 1;
  const amanha = diaPorId[amanhaId];

  const opcoes = [
    { v:'hoje', rot:'Hoje', sub:hoje ? hoje.curto : '' },
    { v:'amanha', rot:'Amanhã', sub:amanha ? amanha.curto : '' }
  ];
  GRADE.dias.forEach(d => opcoes.push({ v:String(d.id), rot:d.curto, sub:'' }));
  opcoes.push({ v:'todos', rot:'Semana', sub:'' });

  cd.innerHTML = '';
  opcoes.forEach(o => {
    const b = document.createElement('button');
    b.className = 'dchip' + (String(fDia) === String(o.v) ? ' on' : '');
    b.type = 'button';
    b.innerHTML = o.rot + (o.sub ? ' <small>' + o.sub + '</small>' : '');
    b.addEventListener('click', () => {
      fDia = o.v;
      if (o.v === 'hoje' || o.v === 'amanha' || o.v === String(HOJE)) fGrupo = fGrupo; // mantem atividade
      montarChips(); render();
      window.scrollTo({top:0, behavior:'smooth'});
    });
    cd.appendChild(b);
  });

  const cg = document.getElementById('chipsGrupo');
  const grupos = [{id:'todos', nome:'Todas', emoji:'✦'}]
    .concat(GRADE.modalidades.map(m => ({id:m.id, nome:m.nome, emoji:m.emoji})))
    .concat(GRADE.modalidades_extra.map(m => ({id:m.id, nome:m.nome, emoji:m.emoji})))
    .concat([{id:'musculacao', nome:GRADE.livre.nome, emoji:GRADE.livre.emoji}]);

  cg.innerHTML = '';
  grupos.forEach(g => {
    const b = document.createElement('button');
    b.className = 'gchip' + (fGrupo === g.id ? ' on' : '');
    b.type = 'button';
    b.dataset.grupo = g.id;
    b.textContent = g.emoji + ' ' + g.nome;
    b.addEventListener('click', () => { fGrupo = g.id; montarChips(); render(); });
    cg.appendChild(b);
  });
}

// ---- faixa "agora" ------------------------------------------------------
function renderAgora(){
  const h = agoraMin();
  const agora = aulasDoDia(HOJE).filter(a => h >= paraMin(a.hora) && h < paraMin(a.fim));
  const box = document.getElementById('caixaAgora');
  if (!agora.length){ box.hidden = true; return; }
  box.hidden = false;
  document.getElementById('txtAgora').innerHTML =
    '<span class="hora">' + hhmm() + '</span> · ' +
    agora.map(a => '<b>' + nomeModalidade(a.mod) + '</b> (' + a.tipo + ') até ' + a.fim).join(' · ');
}

// ---- eventos ------------------------------------------------------------
document.getElementById('busca').addEventListener('input', (e) => {
  fTexto = normalizar(e.target.value);
  render();
});

montarChips();
render();
renderAgora();
setInterval(renderAgora, 30000);
</script>
</body>
</html>
