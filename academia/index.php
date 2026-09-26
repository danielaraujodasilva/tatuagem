<?php
declare(strict_types=1);

/**
 * Grade de horarios da academia - unidade Artur Alvim.
 *
 * Fonte unica de verdade: dados/grade.json (mesma pasta).
 * A pagina e somente-leitura: filtra por dia, horario, modalidade e busca livre.
 *
 * Como responder perguntas do tipo "hoje que horas tem hidro?":
 *   - "aulas" = grid (Natação, Ginástica, Hidro): modalidade/tipo/hora/fim/dias
 *     dias: 2=seg, 3=ter, 4=qua, 5=qui, 6=sex, 7=sab
 *   - "modalidades_extra" = fora do grid (Taekwondo, Muay Thai, Karaté, Funcional Kids)
 *   - "livre" = Musculação (horário livre)
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

$gruposPorModalidade = [];
foreach ($grade['modalidades'] as $m) {
    $gruposPorModalidade[$m['id']] = $m;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Grade de Horários - Artur Alvim</title>
<style>
  :root{
    --bg:#0e1016; --card:#171a23; --card2:#1e2230; --line:#2b3144;
    --txt:#eef1f8; --mut:#98a0b5; --acc:#ff4d6d; --acc2:#ffb03a;
    --hi:#3ddc97; --blue:#5b8cff; --violet:#a06bff; --water:#38bdf8;
    --r:16px; --sh:0 10px 30px rgba(0,0,0,.35);
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{
    background:
      radial-gradient(1100px 500px at 12% -8%, rgba(255,77,109,.18), transparent 60%),
      radial-gradient(900px 480px at 92% 0%, rgba(91,140,255,.16), transparent 60%),
      var(--bg);
    color:var(--txt);
    font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
    min-height:100vh;
    padding:22px 14px calc(40px + env(safe-area-inset-bottom));
  }
  .wrap{max-width:1120px;margin:0 auto}

  header.top{
    display:flex;align-items:center;gap:14px;flex-wrap:wrap;
    margin-bottom:6px
  }
  .brand{
    width:52px;height:52px;border-radius:14px;flex:0 0 auto;
    background:linear-gradient(135deg,var(--acc),var(--acc2));
    display:grid;place-items:center;font-size:26px;box-shadow:var(--sh)
  }
  h1{margin:0;font-size:25px;letter-spacing:-.4px}
  .sub{color:var(--mut);font-size:13.5px;margin-top:2px}

  .hero{
    margin:18px 0 14px;padding:16px 18px;border-radius:var(--r);
    background:linear-gradient(135deg,rgba(255,77,109,.14),rgba(91,140,255,.12));
    border:1px solid var(--line);
  }
  .hero .q{font-size:13px;color:var(--mut);text-transform:uppercase;letter-spacing:.9px;font-weight:700}
  .hero .a{font-size:19px;margin-top:6px;font-weight:600;line-height:1.45}
  .hero .a b{color:var(--acc2)}

  .controles{
    background:var(--card);border:1px solid var(--line);border-radius:var(--r);
    padding:14px;box-shadow:var(--sh);margin-bottom:16px
  }
  .linha{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  .linha + .linha{margin-top:12px}
  .rotulo{font-size:11.5px;color:var(--mut);font-weight:700;letter-spacing:.8px;text-transform:uppercase;min-width:78px}

  .chip{
    border:1px solid var(--line);background:var(--card2);color:var(--txt);
    padding:8px 13px;border-radius:999px;cursor:pointer;font-size:13.5px;
    font-weight:600;transition:.15s;user-select:none;white-space:nowrap
  }
  .chip:hover{border-color:var(--acc);transform:translateY(-1px)}
  .chip.on{background:linear-gradient(135deg,var(--acc),var(--acc2));border-color:transparent;color:#14161d}

  input[type=search]{
    flex:1;min-width:210px;background:var(--card2);border:1px solid var(--line);
    color:var(--txt);border-radius:11px;padding:11px 14px;font-size:14.5px;outline:none
  }
  input[type=search]:focus{border-color:var(--acc);box-shadow:0 0 0 3px rgba(255,77,109,.16)}
  input[type=time]{
    background:var(--card2);border:1px solid var(--line);color:var(--txt);
    border-radius:11px;padding:10px 12px;font-size:14px
  }
  .limpar{
    background:transparent;border:1px solid var(--line);color:var(--mut);
    border-radius:999px;padding:8px 14px;cursor:pointer;font-size:13px;font-weight:600
  }
  .limpar:hover{color:var(--txt);border-color:var(--acc)}

  .agora{
    display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--mut);
    background:rgba(61,220,151,.09);border:1px solid rgba(61,220,151,.28);
    padding:7px 12px;border-radius:999px;margin-bottom:14px
  }
  .pulse{width:8px;height:8px;border-radius:50%;background:var(--hi);box-shadow:0 0 0 0 rgba(61,220,151,.7);animation:p 1.9s infinite}
  @keyframes p{0%{box-shadow:0 0 0 0 rgba(61,220,151,.65)}70%{box-shadow:0 0 0 11px rgba(61,220,151,0)}100%{box-shadow:0 0 0 0 rgba(61,220,151,0)}}

  .tabela-box{
    background:var(--card);border:1px solid var(--line);border-radius:var(--r);
    box-shadow:var(--sh);overflow:hidden;margin-bottom:18px
  }
  .tabela-cab{
    display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
    padding:14px 16px;border-bottom:1px solid var(--line)
  }
  .tabela-cab h2{margin:0;font-size:16.5px}
  .conta{font-size:12.5px;color:var(--mut)}

  .rolagem{overflow-x:auto}
  table{border-collapse:separate;border-spacing:0;width:100%;min-width:820px}
  th,td{padding:0;font-size:13.5px}
  thead th{
    position:sticky;top:0;z-index:2;background:var(--card2);
    padding:11px 8px;font-size:12.5px;letter-spacing:.4px;text-transform:uppercase;
    color:var(--mut);border-bottom:1px solid var(--line);text-align:center
  }
  thead th.hora{text-align:left;padding-left:16px;min-width:112px}
  thead th.hoje{color:var(--acc2);background:rgba(255,176,58,.10)}
  tbody td.hora{
    padding:9px 8px 9px 16px;color:var(--mut);font-variant-numeric:tabular-nums;
    border-bottom:1px solid var(--line);white-space:nowrap;font-size:12.5px;font-weight:600
  }
  tbody td.cel{border-bottom:1px solid var(--line);padding:5px;vertical-align:top;text-align:center}
  tbody td.col-hoje{background:rgba(255,176,58,.045)}
  tr.linha-oculta{display:none}

  .aula{
    display:inline-block;border-radius:10px;padding:6px 9px;margin:1px;
    font-size:12.5px;font-weight:600;line-height:1.25;cursor:default;
    border:1px solid transparent;white-space:nowrap
  }
  .aula small{display:block;font-weight:500;font-size:11px;opacity:.85}
  .m-natacao{background:rgba(91,140,255,.18);border-color:rgba(91,140,255,.42);color:#cfe0ff}
  .m-ginastica{background:rgba(160,107,255,.17);border-color:rgba(160,107,255,.42);color:#e2d6ff}
  .m-hidro{background:rgba(56,189,248,.17);border-color:rgba(56,189,248,.45);color:#cdefff}
  .aula.destaque{outline:2px solid var(--acc2);outline-offset:1px;box-shadow:0 0 16px rgba(255,176,58,.35)}

  .extras{display:grid;grid-template-columns:repeat(auto-fit,minmax(232px,1fr));gap:12px}
  .extra{
    background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px
  }
  .extra h3{margin:0 0 8px;font-size:15px;display:flex;align-items:center;gap:8px}
  .extra .faixa{
    display:flex;justify-content:space-between;gap:10px;font-size:13px;
    padding:6px 0;border-top:1px dashed var(--line);color:var(--mut)
  }
  .extra .faixa:first-of-type{border-top:0}
  .extra .faixa b{color:var(--txt);font-weight:600}
  .extra.destacada{border-color:var(--acc2);box-shadow:0 0 18px rgba(255,176,58,.22)}

  .secao-titulo{
    margin:22px 0 10px;font-size:12.5px;color:var(--mut);text-transform:uppercase;
    letter-spacing:1px;font-weight:700
  }
  .vazio{
    padding:38px 16px;text-align:center;color:var(--mut);font-size:14px
  }
  footer{margin-top:26px;text-align:center;color:#6b7488;font-size:12px;line-height:1.7}
  @media (max-width:520px){
    h1{font-size:21px}
    .hero .a{font-size:16.5px}
    .rotulo{min-width:100%}
  }
</style>
</head>
<body>
<div class="wrap">

  <header class="top">
    <div class="brand">🏊</div>
    <div>
      <h1>Grade de Horários</h1>
      <div class="sub">Unidade <b>Artur Alvim</b> · atualizado em <?= htmlspecialchars((string)($grade['atualizado_em'] ?? '')) ?></div>
    </div>
  </header>

  <div class="agora">
    <span class="pulse"></span>
    <span id="agoraTxt">Agora: carregando…</span>
  </div>

  <div class="hero">
    <div class="q">Hoje (<?= htmlspecialchars($diasPorId[$hojeId]['longo'] ?? '') ?>)</div>
    <div class="a" id="resumoHoje">…</div>
  </div>

  <div class="controles">
    <div class="linha">
      <span class="rotulo">Dia</span>
      <button class="chip" data-dia="hoje">Hoje</button>
      <button class="chip" data-dia="amanha">Amanhã</button>
      <?php foreach ($grade['dias'] as $d): ?>
        <button class="chip" data-dia="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['curto']) ?></button>
      <?php endforeach; ?>
      <button class="chip on" data-dia="todos">Todos</button>
    </div>
    <div class="linha">
      <span class="rotulo">Atividade</span>
      <button class="chip on" data-grupo="todos">Todas</button>
      <?php foreach ($grade['modalidades'] as $m): ?>
        <button class="chip" data-grupo="<?= htmlspecialchars($m['id']) ?>"><?= $m['emoji'] ?> <?= htmlspecialchars($m['nome']) ?></button>
      <?php endforeach; ?>
      <?php foreach ($grade['modalidades_extra'] as $m): ?>
        <button class="chip" data-grupo="<?= htmlspecialchars($m['id']) ?>"><?= $m['emoji'] ?> <?= htmlspecialchars($m['nome']) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="linha">
      <span class="rotulo">Buscar</span>
      <input type="search" id="busca" placeholder='Ex.: hidro, natação infantil, pilates, bike, karatê…' value="<?= htmlspecialchars($qParam) ?>">
      <input type="time" id="horaDe" title="A partir de que horário">
      <button class="limpar" id="btnLimpar">Limpar</button>
    </div>
  </div>

  <div class="tabela-box">
    <div class="tabela-cab">
      <h2 id="tituloTabela">Grade da semana</h2>
      <span class="conta" id="conta">—</span>
    </div>
    <div class="rolagem">
      <table id="tabela">
        <thead>
          <tr>
            <th class="hora">Horário</th>
            <?php foreach ($grade['dias'] as $d): ?>
              <th class="<?= ((int)$d['id'] === $hojeId) ? 'hoje' : '' ?>"><?= htmlspecialchars($d['curto']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="corpo"></tbody>
      </table>
    </div>
    <div class="vazio" id="vazio" style="display:none">Nada encontrado com esse filtro. Tente outro termo ou limpe os filtros.</div>
  </div>

  <div class="secao-titulo">Modalidades fora do grid</div>
  <div class="extras" id="extras"></div>

  <div class="secao-titulo">Sem aula marcada</div>
  <div class="extras" id="livre"></div>

  <footer>
    Fonte: foto da grade impressa (<?= htmlspecialchars((string)($grade['origem'] ?? '')) ?>).<br>
    Dados em <code>dados/grade.json</code> — edite lá para corrigir qualquer horário.
  </footer>
</div>

<script>
const GRADE = <?= json_encode($grade, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const HOJE = <?= (int)$hojeId ?>;
const PERIODO_ROMANO = {1:'Domingo'};

const el = (id) => document.getElementById(id);
const dias = GRADE.dias;
const diaPorId = {};
dias.forEach(d => diaPorId[d.id] = d);

// Monta a matriz horarios x dias a partir do JSON
function montarMatriz(){
  const porHora = new Map();
  GRADE.aulas.forEach(a => {
    const k = a.hora + '-' + a.fim;
    if (!porHora.has(k)) porHora.set(k, { hora: a.hora, fim: a.fim, celulas: {} });
    const linha = porHora.get(k);
    a.dias.forEach(d => {
      if (!linha.celulas[d]) linha.celulas[d] = [];
      linha.celulas[d].push({ modalidade: a.modalidade, tipo: a.tipo });
    });
  });
  return Array.from(porHora.values()).sort((a,b) => a.hora.localeCompare(b.hora));
}

const MATRIZ = montarMatriz();
const modalidadePorId = {};
GRADE.modalidades.forEach(m => modalidadePorId[m.id] = m);

let filtroDia = 'todos';
let filtroGrupo = 'todos';
let filtroTexto = <?= json_encode(mb_strtolower($qParam)) ?>;
let filtroHora = '';

// Normaliza texto: sem acento, sem caixa — "natacao" casa com "Natação"
function normalizar(s){
  return (s||'').toString().toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
}

function nomeModalidade(id){
  if (modalidadePorId[id]) return modalidadePorId[id].nome;
  const ex = GRADE.modalidades_extra.find(m => m.id === id);
  if (ex) return ex.nome;
  if (GRADE.livre && GRADE.livre.nome) return GRADE.livre.nome;
  return id;
}

function aulaCasa(item, idModalidade){
  if (filtroGrupo !== 'todos'){
    if (idModalidade !== filtroGrupo) return false;
    // Hidro nao tem "tipo"; demais ids de extra/livre tambem nao entram no grid
  }
  if (filtroTexto !== ''){
    const alvo = normalizar([nomeModalidade(idModalidade), item.tipo, item.modalidade, 'aula'].join(' '));
    if (alvo.indexOf(filtroTexto) === -1) return false;
  }
  return true;
}

function linhaVisivel(linha){
  if (filtroHora && linha.fim <= filtroHora) return false;
  if (filtroDia !== 'todos' && filtroDia !== 'hoje' && filtroDia !== 'amanha'){
    const d = parseInt(filtroDia, 10);
    const lista = linha.celulas[d] || [];
    if (!lista.length) return false;
    if (filtroGrupo !== 'todos' && !lista.some(c => c.modalidade === filtroGrupo)) return false;
    if (filtroTexto !== '' && !lista.some(c => aulaCasa(c, c.modalidade))) return false;
    return true;
  }
  return true;
}

function render(){
  const corpo = el('corpo');
  corpo.innerHTML = '';
  let totalAulas = 0;

  MATRIZ.forEach(linha => {
    const tr = document.createElement('tr');
    if (!linhaVisivel(linha)){ tr.className = 'linha-oculta'; }

    const tdHora = document.createElement('td');
    tdHora.className = 'hora';
    tdHora.textContent = linha.hora + ' – ' + linha.fim;
    tr.appendChild(tdHora);

    dias.forEach(d => {
      const td = document.createElement('td');
      td.className = 'cel' + (d.id === HOJE ? ' col-hoje' : '');
      (linha.celulas[d.id] || []).forEach(c => {
        if (filtroDia !== 'todos' && filtroDia !== 'hoje' && filtroDia !== 'amanha'
            && parseInt(filtroDia,10) !== d.id) return;
        if (filtroDia === 'hoje' && d.id !== HOJE) return;
        if (!aulaCasa(c, c.modalidade)) return;
        totalAulas++;
        const b = document.createElement('span');
        b.className = 'aula m-' + c.modalidade;
        b.innerHTML = '<span>' + nomeModalidade(c.modalidade) + '</span><small>' + c.tipo + '</small>';
        const agora = agoraNaFaixa(linha.hora, linha.fim) && d.id === HOJE;
        if (agora) b.className += ' destaque';
        td.appendChild(b);
      });
      tr.appendChild(td);
    });
    corpo.appendChild(tr);
  });

  el('conta').textContent = totalAulas + (totalAulas === 1 ? ' aula listada' : ' aulas listadas');
  const tabela = el('tabela');
  const linhasVazias = Array.from(corpo.querySelectorAll('tr'))
    .every(tr => tr.className === 'linha-oculta');
  tabela.style.display = linhasVazias ? 'none' : '';
  el('vazio').style.display = linhasVazias ? '' : 'none';
  atualizarTitulo();
  renderResumoHoje();
}

function atualizarTitulo(){
  if (filtroDia === 'hoje' || filtroDia === 'amanha'){
    const id = filtroDia === 'hoje' ? HOJE : (HOJE >= 7 ? 2 : HOJE + 1);
    const d = diaPorId[id];
    el('tituloTabela').textContent = (filtroDia === 'hoje' ? 'Hoje' : 'Amanhã') + ' — ' + (d ? d.longo : '');
  } else if (filtroDia !== 'todos'){
    const d = diaPorId[parseInt(filtroDia,10)];
    el('tituloTabela').textContent = (d ? d.longo : 'Grade') + ' — grade do dia';
  } else {
    el('tituloTabela').textContent = 'Grade da semana';
  }
}

function paraMinutos(hhmm){
  const p = hhmm.split(':');
  return parseInt(p[0],10)*60 + parseInt(p[1],10);
}
function agoraNaFaixa(ini, fim){
  const agora = new Date();
  const h = agora.getHours()*60 + agora.getMinutes();
  return h >= paraMinutos(ini) && h < paraMinutos(fim);
}

// Resumo do dia de hoje: o que tem na hora atual + a lista do resto do dia
function renderResumoHoje(){
  const agora = new Date();
  const hAgora = agora.getHours()*60 + agora.getMinutes();
  const nomeDia = diaPorId[HOJE] ? diaPorId[HOJE].longo : '';

  const itens = [];
  GRADE.aulas.forEach(a => {
    if (!a.dias.includes(HOJE)) return;
    itens.push({ hora:a.hora, fim:a.fim, modalidade:a.modalidade, tipo:a.tipo });
  });
  GRADE.modalidades_extra.forEach(m => {
    m.faixas.forEach(f => {
      if (!f.dias.includes(HOJE)) return;
      itens.push({ hora:f.hora, fim:f.fim, modalidade:m.id, tipo:f.tipo });
    });
  });
  itens.sort((a,b) => a.hora.localeCompare(b.hora));

  const agoraAgora = itens.filter(i => hAgora >= paraMinutos(i.hora) && hAgora < paraMinutos(i.fim));
  const proximas = itens.filter(i => paraMinutos(i.hora) > hAgora).slice(0, 4);

  let txt = '';
  if (agoraAgora.length){
    txt = 'Acontecendo agora: ' + agoraAgora.map(i =>
      '<b>' + nomeModalidade(i.modalidade) + '</b> (' + i.tipo + ') até ' + i.fim).join(' · ');
  } else if (proximas.length){
    txt = 'A seguir: ' + proximas.map(i =>
      '<b>' + i.hora + '</b> ' + nomeModalidade(i.modalidade) + ' (' + i.tipo + ')').join(' · ');
  } else {
    txt = 'Nenhuma aula no grid agora — musculação segue em horário livre.';
  }
  el('resumoHoje').innerHTML = txt + '<br><span style="color:var(--mut);font-size:13px">' +
    itens.length + ' aulas hoje (' + nomeDia + ')</span>';
}

// Card "agora": o que esta rolando neste minuto em qualquer modalidade
function renderAgora(){
  const agora = new Date();
  const h = agora.getHours()*60 + agora.getMinutes();
  const ativos = [];
  GRADE.aulas.forEach(a => {
    if (!a.dias.includes(HOJE)) return;
    if (h >= paraMinutos(a.hora) && h < paraMinutos(a.fim)) ativos.push(a.modalidade + ':' + a.tipo);
  });
  GRADE.modalidades_extra.forEach(m => {
    m.faixas.forEach(f => {
      if (!f.dias.includes(HOJE)) return;
      if (h >= paraMinutos(f.hora) && h < paraMinutos(f.fim)) ativos.push(m.id + ':' + f.tipo);
    });
  });
  const unicos = Array.from(new Set(ativos));
  const hhmm = String(agora.getHours()).padStart(2,'0') + ':' + String(agora.getMinutes()).padStart(2,'0');
  el('agoraTxt').textContent = unicos.length
    ? hhmm + ' · rolando: ' + unicos.map(u => nomeModalidade(u.split(':')[0]) + ' (' + u.split(':')[1] + ')').join(', ')
    : hhmm + ' · nenhuma aula em andamento';
}

// Extras (Taekwondo, Muay Thai, Karate, Funcional Kids) com o mesmo filtro
function renderExtras(){
  const box = el('extras');
  box.innerHTML = '';
  GRADE.modalidades_extra.forEach(m => {
    if (filtroGrupo !== 'todos' && filtroGrupo !== m.id) return;
    if (filtroTexto !== ''){
      const alvo = normalizar(m.nome + ' ' + m.faixas.map(f => f.tipo).join(' '));
      if (alvo.indexOf(filtroTexto) === -1) return;
    }
    const div = document.createElement('div');
    div.className = 'extra';
    if (filtroGrupo === m.id) div.className += ' destacada';
    let html = '<h3>' + m.emoji + ' ' + m.nome + '</h3>';
    m.faixas.forEach(f => {
      const nomes = f.dias.map(d => diaPorId[d] ? diaPorId[d].curto : d).join(' e ');
      html += '<div class="faixa"><span>' + nomes + ' · ' + f.tipo + '</span><b>' + f.hora + '–' + f.fim + '</b></div>';
    });
    div.innerHTML = html;
    box.appendChild(div);
  });
  if (!box.children.length){
    box.innerHTML = '<div class="vazio" style="grid-column:1/-1">Nenhuma modalidade extra com esse filtro.</div>';
  }
}

function renderLivre(){
  const box = el('livre');
  const l = GRADE.livre;
  if (filtroGrupo !== 'todos' && filtroGrupo !== 'musculacao'){ box.innerHTML = ''; return; }
  if (filtroTexto !== '' && normalizar(l.nome + ' ' + l.descricao).indexOf(filtroTexto) === -1){ box.innerHTML = ''; return; }
  box.innerHTML = '<div class="extra"><h3>' + l.emoji + ' ' + l.nome + '</h3>' +
    '<div class="faixa"><span>' + l.descricao + '</span></div></div>';
}

// Eventos dos chips
document.querySelectorAll('.chip[data-dia]').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.chip[data-dia]').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    filtroDia = b.dataset.dia;
    render(); renderExtras(); renderLivre();
  });
});
document.querySelectorAll('.chip[data-grupo]').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.chip[data-grupo]').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    filtroGrupo = b.dataset.grupo;
    render(); renderExtras(); renderLivre();
  });
});
el('busca').addEventListener('input', (e) => {
  filtroTexto = normalizar(e.target.value);
  render(); renderExtras(); renderLivre();
});
el('horaDe').addEventListener('change', (e) => {
  filtroHora = e.target.value || '';
  render();
});
el('btnLimpar').addEventListener('click', () => {
  filtroDia = 'todos'; filtroGrupo = 'todos'; filtroTexto = ''; filtroHora = '';
  el('busca').value = ''; el('horaDe').value = '';
  document.querySelectorAll('.chip[data-dia]').forEach(x => x.classList.toggle('on', x.dataset.dia === 'todos'));
  document.querySelectorAll('.chip[data-grupo]').forEach(x => x.classList.toggle('on', x.dataset.grupo === 'todos'));
  render(); renderExtras(); renderLivre();
});

// Recorte inicial vindo da URL (?dia=&grupo=&q=)
(function aplicarUrl(){
  if (filtroGrupo !== 'todos'){ filtroTexto = filtroTexto; }
})();

// Primeiro desenho
document.querySelectorAll('.chip[data-grupo]').forEach(x => {
  x.classList.toggle('on', x.dataset.grupo === (<?= json_encode($grupoParam !== '' ? $grupoParam : 'todos') ?>));
});
filtroGrupo = <?= json_encode($grupoParam !== '' ? $grupoParam : 'todos') ?>;
filtroDia = <?= json_encode($diaInicial === $hojeId && $diaParam === '' ? 'todos' : ($diaParam === 'hoje' ? 'hoje' : ($diaParam === 'amanha' ? 'amanha' : (string)$diaInicial))) ?>;
document.querySelectorAll('.chip[data-dia]').forEach(x => x.classList.toggle('on', x.dataset.dia === filtroDia));
render(); renderExtras(); renderLivre(); renderAgora();
setInterval(renderAgora, 30000);
</script>
</body>
</html>
