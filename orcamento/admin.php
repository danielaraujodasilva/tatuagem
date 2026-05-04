<?php
require_once __DIR__ . '/../auth/auth.php';
require_admin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin Orçamento Tattoo</title>
<link rel="icon" href="data:," />
<style>
:root {
  --bg: #080808;
  --line: rgba(255,255,255,.12);
  --text: #fff;
  --muted: #bbb;
  --red: #d7192a;
  --green: #25d366;
}

* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100vh;
  font-family: Arial, Helvetica, sans-serif;
  color: var(--text);
  background:
    linear-gradient(140deg, rgba(215,25,42,.18), transparent 30%),
    var(--bg);
  padding: 22px 14px;
}

button, input, textarea { font: inherit; }
button { cursor: pointer; }

.container {
  width: min(1120px, 100%);
  margin: 0 auto;
  display: grid;
  gap: 16px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: end;
  gap: 16px;
  flex-wrap: wrap;
}

h1 {
  margin: 0;
  font-size: clamp(30px, 4vw, 52px);
  text-transform: uppercase;
  line-height: .98;
}

p {
  margin: 8px 0 0;
  color: var(--muted);
  line-height: 1.45;
}

.card {
  padding: 16px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: linear-gradient(180deg, rgba(27,27,27,.98), rgba(9,9,9,.98));
  box-shadow: 0 18px 54px rgba(0,0,0,.45);
}

.actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.admin-tabs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.admin-tab {
  min-height: 48px;
  border: 1px solid rgba(255,255,255,.14);
  border-radius: 8px;
  background: rgba(255,255,255,.055);
  color: #fff;
  font-weight: 900;
}

.admin-tab.active {
  border-color: rgba(37,211,102,.48);
  background: rgba(37,211,102,.16);
  color: #d8ffe5;
}

.admin-panel[hidden] {
  display: none;
}

.btn, .link {
  display: inline-grid;
  place-items: center;
  min-height: 42px;
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 10px 13px;
  background: #121212;
  color: #fff;
  font-weight: 900;
  text-decoration: none;
}

.btn.primary {
  border-color: rgba(37,211,102,.5);
  background: var(--green);
  color: #06170b;
}

.btn.danger {
  border-color: rgba(215,25,42,.6);
  background: rgba(215,25,42,.18);
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.field {
  display: grid;
  gap: 6px;
}

.field.full { grid-column: 1 / -1; }
.section-head {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.section-head h2 {
  margin: 0;
  font-size: 24px;
}

.section-head p {
  margin: 4px 0 0;
}

label {
  color: #ddd;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

input, textarea {
  width: 100%;
  border: 1px solid rgba(255,255,255,.13);
  border-radius: 6px;
  background: #0d0d0d;
  color: #fff;
  padding: 10px;
  outline: none;
}

textarea {
  min-height: 78px;
  resize: vertical;
}

.table-wrap {
  overflow: auto;
  border: 1px solid var(--line);
  border-radius: 8px;
}

table {
  width: 100%;
  min-width: 860px;
  border-collapse: collapse;
}

th, td {
  border-bottom: 1px solid rgba(255,255,255,.08);
  padding: 8px;
  text-align: left;
  vertical-align: top;
}

th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #101010;
  color: #ddd;
  font-size: 12px;
  text-transform: uppercase;
}

td input[type="number"] { width: 100px; }
td input[type="checkbox"] {
  width: 20px;
  height: 20px;
  accent-color: var(--red);
}

.area-name { min-width: 170px; }
.area-text { min-width: 360px; }

.promo-list {
  display: grid;
  gap: 12px;
}

.promo-card {
  display: grid;
  gap: 12px;
  padding: 14px;
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 8px;
  background: rgba(255,255,255,.035);
}

.promo-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.promo-card-title {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  font-weight: 900;
}

.promo-card-title input {
  width: 20px;
  height: 20px;
  accent-color: var(--red);
}

.promo-form-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.promo-form-grid .wide { grid-column: span 2; }
.promo-form-grid .full { grid-column: 1 / -1; }

.promo-card select {
  width: 100%;
  border: 1px solid rgba(255,255,255,.13);
  border-radius: 6px;
  background: #0d0d0d;
  color: #fff;
  padding: 10px;
  outline: none;
}

.promo-areas {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.promo-piece {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  padding: 9px;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 6px;
  background: #0d0d0d;
  color: #eee;
  font-size: 13px;
  font-weight: 800;
  text-transform: none;
}

.promo-piece input {
  width: 18px;
  height: 18px;
  accent-color: var(--green);
}

.promo-preview {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.promo-preview-box {
  padding: 12px;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px;
  background: #0d0d0d;
}

.promo-preview-box span {
  display: block;
  color: var(--muted);
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

.promo-preview-box strong {
  display: block;
  margin-top: 4px;
  color: #fff;
  font-size: 18px;
}

.promo-preview-box.final strong {
  color: #8dffad;
}

.mini-help {
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.35;
}

.notice {
  display: none;
  border-left: 3px solid var(--green);
  border-radius: 4px;
  padding: 10px 12px;
  background: rgba(37,211,102,.1);
  color: #d8ffe5;
}

.notice.show { display: block; }

@media (max-width: 760px) {
  body { padding: 14px 10px; }
  .admin-tabs { grid-template-columns: 1fr; }
  .grid { grid-template-columns: 1fr; }
  .section-head { align-items: stretch; flex-direction: column; }
  .promo-form-grid,
  .promo-preview,
  .promo-areas { grid-template-columns: 1fr; }
  .promo-form-grid .wide { grid-column: 1; }
}
</style>
</head>
<body>
<main class="container">
  <header class="header">
    <div>
      <h1>Admin Orçamento</h1>
      <p>Página separada para ajustar o orçamento. As alterações ficam salvas no navegador por enquanto.</p>
    </div>
    <div class="actions">
      <a class="link" href="index.php">Ver orçamento</a>
      <button class="btn danger" id="reset" type="button">Restaurar padrão</button>
      <button class="btn primary" id="save" type="button">Salvar tudo</button>
    </div>
  </header>

  <nav class="admin-tabs" aria-label="Seções do admin">
    <button class="admin-tab active" type="button" data-admin-tab="geral">Configurações</button>
    <button class="admin-tab" type="button" data-admin-tab="promocoes">Promoções</button>
    <button class="admin-tab" type="button" data-admin-tab="valores">Valores das peças</button>
  </nav>

  <div class="notice" id="notice">Configurações salvas.</div>

  <section class="card admin-panel" data-admin-panel="geral">
    <div class="grid">
      <div class="field">
        <label for="whatsapp">WhatsApp</label>
        <input id="whatsapp" type="text" placeholder="5511999999999" />
      </div>
      <div class="field">
        <label for="cta">Texto do botão</label>
        <input id="cta" type="text" />
      </div>
      <div class="field full">
        <label for="intro">Texto de abertura</label>
        <textarea id="intro"></textarea>
      </div>
    </div>
  </section>

  <section class="card admin-panel" data-admin-panel="promocoes" hidden>
    <div class="section-head">
      <div>
        <h2>Promoções</h2>
        <p>Edite o desconto em percentual e veja na hora a faixa estimada com desconto.</p>
      </div>
      <button class="btn primary" id="addPromo" type="button">Nova promoção</button>
    </div>
    <div class="promo-list" id="promoRows"></div>
  </section>

  <section class="card admin-panel" data-admin-panel="valores" hidden>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Ativa</th>
            <th>Área</th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Descrição</th>
          </tr>
        </thead>
        <tbody id="rows"></tbody>
      </table>
    </div>
  </section>
</main>

<script>
const DEFAULT_CONFIG = {
  whatsapp: "5511947573311",
  cta: "Quero orçamento agora",
  intro: "Selecione a região, envie uma referência e receba uma prévia de orçamento direto no WhatsApp."
};

const DEFAULT_PROMOS = [
  promo("Fechamento de braço externo esquerdo", "Antebraço externo + braço externo + ombro", ["antebraco_esq_externo", "braco_esq_costas", "ombro_esq_costas"], .85, "costas"),
  promo("Fechamento de braço externo direito", "Antebraço externo + braço externo + ombro", ["antebraco_dir_externo", "braco_dir_costas", "ombro_dir_costas"], .85, "costas"),
  promo("Fechamento de braço interno esquerdo", "Antebraço interno + braço interno", ["antebraco_esq_interno", "braco_esq_frente"], .9, "frente"),
  promo("Fechamento de braço interno direito", "Antebraço interno + braço interno", ["antebraco_dir_interno", "braco_dir_frente"], .9, "frente"),
  promo("Fechamento de perna frontal esquerda", "Coxa + joelho + canela + tornozelo", ["coxa_esq_frontal", "joelho_esq", "canela_esq", "tornozelo_esq"], .85, "frente"),
  promo("Fechamento de perna frontal direita", "Coxa + joelho + canela + tornozelo", ["coxa_dir_frontal", "joelho_dir", "canela_dir", "tornozelo_dir"], .85, "frente"),
  promo("Fechamento de perna posterior esquerda", "Coxa posterior + joelho posterior + panturrilha + tornozelo", ["coxa_esq_posterior", "joelho_esq_posterior", "panturrilha_esq", "tornozelo_esq_costas"], .85, "costas"),
  promo("Fechamento de perna posterior direita", "Coxa posterior + joelho posterior + panturrilha + tornozelo", ["coxa_dir_posterior", "joelho_dir_posterior", "panturrilha_dir", "tornozelo_dir_costas"], .85, "costas"),
  promo("Fechamento de costas", "Costas completa", ["costas_esq_alta", "costas_dir_alta", "costas_esq_baixa", "costas_dir_baixa", "lombar"], .82, "costas"),
  promo("Fechamento de peitoral", "Peito esquerdo + peito direito", ["peito_esq", "peito_dir"], .9, "frente"),
  promo("Fechamento frontal", "Peitoral completo + abdômen", ["peito_esq", "peito_dir", "abdomen"], .85, "frente")
];

const DEFAULT_AREAS = {
  cabeca: area("Cabeça", 2500, 6500, "Área extrema e muito visível. Precisa de leitura forte, contraste e desenho que envelheça bem."),
  pescoco: area("Pescoço", 1500, 3500, "Região de alto impacto visual, boa para composições verticais e fechamentos."),
  nuca: area("Nuca", 900, 2200, "Boa para peças menores, símbolos, ornamentos e complemento de costas ou pescoço."),
  ombros: area("Ombros", 1800, 4200, "Excelente para encaixe anatômico, fechamentos de braço e projetos com presença."),
  peito: area("Peito", 2500, 7000, "Área grande e visual, ideal para projetos simétricos ou peça central de impacto."),
  abdomen: area("Abdômen", 2000, 5600, "Área ampla, sensível e com variação de elasticidade. Pede planejamento limpo."),
  costela: area("Costela", 1800, 5600, "Ótima para composições laterais, florais, lettering e projetos verticais."),
  quadril: area("Quadril / Virilha", 1500, 4200, "Boa para peças ornamentais, sensuais ou complemento de perna e abdômen."),
  costas: area("Costas", 3200, 12000, "Área nobre para projetos grandes, painéis, simetrias e fechamentos de alto impacto."),
  lombar: area("Lombar", 1500, 4200, "Boa para peças centrais, ornamentais e complementos de fechamento."),
  braco: area("Braço", 1800, 4800, "Uma das melhores áreas: boa leitura, boa resistência e encaixe anatômico."),
  antebraco_interno: area("Antebraço interno", 1200, 3400, "Área muito procurada, boa para projetos detalhados e leitura frontal."),
  antebraco_externo: area("Antebraço externo", 1200, 3200, "Área resistente e excelente para contraste, com boa leitura no dia a dia."),
  pulso: area("Pulso", 600, 1600, "Área menor e delicada para símbolos, pulseiras, detalhes e complementos."),
  mao: area("Mão", 900, 2800, "Área muito visível e com maior desgaste. Precisa de desenho forte e manutenção consciente."),
  dedos_mao: area("Dedos da mão", 400, 1300, "Área pequena, visível e com alto desgaste. Melhor para símbolos simples e letras."),
  coxa_frontal: area("Coxa frontal", 2500, 7200, "Área ampla para peças grandes, fechamentos e projetos com bastante detalhe."),
  coxa_posterior: area("Coxa posterior", 2300, 6600, "Excelente para continuidade de fechamento de perna e composição vertical."),
  gluteo: area("Glúteo", 1800, 4600, "Boa para composições grandes e complementos de perna ou quadril."),
  joelho: area("Joelho", 1200, 3800, "Área complexa, dobra bastante e exige desenho inteligente."),
  joelho_posterior: area("Parte de trás do joelho", 900, 2700, "Área sensível e delicada, geralmente usada para complementar fechamento de perna."),
  canela: area("Canela", 1800, 4800, "Área de impacto visual, ótima para projetos verticais e contraste forte."),
  panturrilha: area("Panturrilha", 1800, 4800, "Boa curvatura para peças médias, grandes e fechamento de perna."),
  tornozelo: area("Tornozelo", 600, 1900, "Área menor, boa para detalhes, tornozeleiras e complementos."),
  pe: area("Pé", 900, 2800, "Área visível, sensível e com desgaste. Precisa de projeto simples e bem planejado."),
  dedos_pe: area("Dedos do pé", 400, 1100, "Área pequena, sensível e com alto desgaste. Melhor para marcas simples.")
};

function area(titulo, min, max, descricao) {
  return { titulo, min, max, descricao, ativa: true };
}

function promo(titulo, descricao, ids, desconto, view) {
  return { titulo, descricao, ids, desconto, view, ativa: true };
}

const $ = (id) => document.getElementById(id);
let config = load("orcamentoTattooConfig", DEFAULT_CONFIG);
let areas = load("orcamentoTattooAreas", DEFAULT_AREAS);
let promotions = loadPromos();

function load(key, fallback) {
  try {
    return { ...fallback, ...JSON.parse(localStorage.getItem(key) || "{}") };
  } catch (e) {
    return { ...fallback };
  }
}

function fillConfig() {
  $("whatsapp").value = config.whatsapp || "";
  $("cta").value = config.cta || "";
  $("intro").value = config.intro || "";
}

function loadPromos() {
  try {
    const saved = JSON.parse(localStorage.getItem("orcamentoTattooPromos") || "[]");
    return Array.isArray(saved) && saved.length ? saved : DEFAULT_PROMOS;
  } catch (e) {
    return DEFAULT_PROMOS;
  }
}

function renderRows() {
  $("rows").innerHTML = Object.entries(areas).map(([id, item]) => `
    <tr data-id="${id}">
      <td><input data-field="ativa" type="checkbox" ${item.ativa !== false ? "checked" : ""}></td>
      <td><input class="area-name" data-field="titulo" value="${escapeHtml(item.titulo)}"></td>
      <td><input data-field="min" type="number" min="0" step="50" value="${Number(item.min || 0)}"></td>
      <td><input data-field="max" type="number" min="0" step="50" value="${Number(item.max || 0)}"></td>
      <td><textarea class="area-text" data-field="descricao">${escapeHtml(item.descricao || "")}</textarea></td>
    </tr>
  `).join("");
}

function renderPromoRows() {
  $("promoRows").innerHTML = promotions.map((item, index) => {
    const descontoPercent = Math.round((1 - Number(item.desconto || 1)) * 100);
    return `
      <article class="promo-card" data-promo-index="${index}">
        <div class="promo-card-head">
          <label class="promo-card-title">
            <input data-promo-field="ativa" type="checkbox" ${item.ativa !== false ? "checked" : ""}>
            Promoção ativa
          </label>
          <button class="btn danger" type="button" data-remove-promo="${index}">Remover</button>
        </div>
        <div class="promo-form-grid">
          <div class="field wide">
            <label>Título</label>
            <input data-promo-field="titulo" value="${escapeHtml(item.titulo || "")}">
          </div>
          <div class="field">
            <label>Desconto (%)</label>
            <input data-promo-field="descontoPercent" type="number" min="0" max="80" step="1" value="${descontoPercent}">
          </div>
          <div class="field">
            <label>Visual do boneco</label>
            <select data-promo-field="view">
              <option value="frente" ${item.view === "frente" ? "selected" : ""}>Frente</option>
              <option value="costas" ${item.view === "costas" ? "selected" : ""}>Costas</option>
            </select>
          </div>
          <div class="field full">
            <label>Descrição</label>
            <input data-promo-field="descricao" value="${escapeHtml(item.descricao || "")}">
          </div>
          <div class="field full">
            <label>Peças da promoção</label>
            <div class="promo-areas" data-promo-pieces>
              ${renderPieceOptions(item.ids || [])}
            </div>
            <p class="mini-help">Marque as peças que fazem parte da promoção. O valor é calculado automaticamente com base nas áreas marcadas.</p>
          </div>
        </div>
        <div class="promo-preview" data-promo-preview></div>
      </article>
    `;
  }).join("");
  updatePromoPreviews();
}

function allPromoPieces() {
  const defaults = DEFAULT_PROMOS.flatMap(item => item.ids || []);
  const saved = promotions.flatMap(item => item.ids || []);
  return [...new Set([...defaults, ...saved])].sort((a, b) => pieceLabel(a).localeCompare(pieceLabel(b), "pt-BR"));
}

function renderPieceOptions(selectedIds) {
  const selectedSet = new Set(selectedIds || []);
  return allPromoPieces().map(id => `
    <label class="promo-piece">
      <input type="checkbox" data-piece-id="${escapeHtml(id)}" ${selectedSet.has(id) ? "checked" : ""}>
      ${escapeHtml(pieceLabel(id))}
    </label>
  `).join("");
}

function pieceLabel(id) {
  const value = String(id || "");
  const region = regionFromPartId(value);
  const base = areas[region]?.titulo || value.replace(/_/g, " ");
  const side = value.includes("_esq") ? " esquerdo" : value.includes("_dir") ? " direito" : "";
  const view = /costas|posterior|panturrilha|nuca|lombar|gluteo|externo/.test(value) ? " - costas" : " - frente";
  return `${base}${side}${view}`;
}

function escapeHtml(value = "") {
  return String(value).replace(/[&<>"']/g, char => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#039;" }[char]));
}

function money(value) {
  return Number(value || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function roundPrice(value) {
  return Math.round((Number(value || 0)) / 50) * 50;
}

function currentAreasFromRows() {
  const nextAreas = { ...areas };
  document.querySelectorAll("#rows tr").forEach(row => {
    const id = row.dataset.id;
    const next = { ...nextAreas[id] };
    row.querySelectorAll("[data-field]").forEach(input => {
      const field = input.dataset.field;
      if (field === "ativa") next.ativa = input.checked;
      else if (field === "min" || field === "max") next[field] = Number(input.value || 0);
      else next[field] = input.value;
    });
    nextAreas[id] = next;
  });
  return nextAreas;
}

function regionFromPartId(id) {
  const value = String(id || "");
  if (value.includes("antebraco") && value.includes("interno")) return "antebraco_interno";
  if (value.includes("antebraco") && value.includes("externo")) return "antebraco_externo";
  if (value.includes("coxa") && value.includes("posterior")) return "coxa_posterior";
  if (value.includes("coxa")) return "coxa_frontal";
  if (value.includes("joelho") && value.includes("posterior")) return "joelho_posterior";
  if (value.includes("joelho")) return "joelho";
  if (value.includes("panturrilha")) return "panturrilha";
  if (value.includes("tornozelo")) return "tornozelo";
  if (value.includes("canela")) return "canela";
  if (value.includes("ombro")) return "ombros";
  if (value.includes("braco")) return "braco";
  if (value.includes("peito")) return "peito";
  if (value.includes("abdomen")) return "abdomen";
  if (value.includes("costas")) return "costas";
  if (value.includes("lombar")) return "lombar";
  return "";
}

function promoIdsFromTextarea(value) {
  return String(value || "")
    .split(/[\n,]+/)
    .map(item => item.trim())
    .filter(Boolean);
}

function selectedPromoIds(card) {
  return [...card.querySelectorAll("[data-piece-id]:checked")].map(input => input.dataset.pieceId);
}

function updatePromoPreviews() {
  const areaDraft = currentAreasFromRows();
  document.querySelectorAll("[data-promo-index]").forEach(card => {
    const ids = selectedPromoIds(card);
    const discountPercent = Math.min(80, Math.max(0, Number(card.querySelector('[data-promo-field="descontoPercent"]').value || 0)));
    const factor = 1 - (discountPercent / 100);
    const total = ids.reduce((sum, id) => {
      const region = regionFromPartId(id);
      const data = areaDraft[region];
      return {
        min: sum.min + Number(data?.min || 0),
        max: sum.max + Number(data?.max || 0)
      };
    }, { min: 0, max: 0 });
    const finalMin = roundPrice(total.min * factor);
    const finalMax = roundPrice(total.max * factor);
    const preview = card.querySelector("[data-promo-preview]");
    preview.innerHTML = `
      <div class="promo-preview-box"><span>Sem desconto</span><strong>${money(total.min)} a ${money(total.max)}</strong></div>
      <div class="promo-preview-box"><span>Desconto</span><strong>${discountPercent}% OFF</strong></div>
      <div class="promo-preview-box final"><span>Com desconto</span><strong>${money(finalMin)} a ${money(finalMax)}</strong></div>
    `;
  });
}

function collectPromosFromForm() {
  return [...document.querySelectorAll("[data-promo-index]")].map(card => {
    const discountPercent = Math.min(80, Math.max(0, Number(card.querySelector('[data-promo-field="descontoPercent"]').value || 0)));
    return {
      titulo: card.querySelector('[data-promo-field="titulo"]').value.trim(),
      descricao: card.querySelector('[data-promo-field="descricao"]').value.trim(),
      ids: selectedPromoIds(card),
      desconto: Number((1 - (discountPercent / 100)).toFixed(2)),
      view: card.querySelector('[data-promo-field="view"]').value,
      ativa: card.querySelector('[data-promo-field="ativa"]').checked
    };
  }).filter(item => item.titulo && item.ids.length);
}

function save() {
  config = {
    whatsapp: $("whatsapp").value.trim(),
    cta: $("cta").value.trim(),
    intro: $("intro").value.trim()
  };

  promotions = collectPromosFromForm();

  document.querySelectorAll("#rows tr").forEach(row => {
    const id = row.dataset.id;
    const next = { ...areas[id] };
    row.querySelectorAll("[data-field]").forEach(input => {
      const field = input.dataset.field;
      if (field === "ativa") next.ativa = input.checked;
      else if (field === "min" || field === "max") next[field] = Number(input.value || 0);
      else next[field] = input.value;
    });
    areas[id] = next;
  });

  localStorage.setItem("orcamentoTattooConfig", JSON.stringify(config));
  localStorage.setItem("orcamentoTattooAreas", JSON.stringify(areas));
  localStorage.setItem("orcamentoTattooPromos", JSON.stringify(promotions));
  $("notice").textContent = "Configurações salvas.";
  $("notice").classList.add("show");
  setTimeout(() => $("notice").classList.remove("show"), 3200);
}

function reset() {
  localStorage.removeItem("orcamentoTattooConfig");
  localStorage.removeItem("orcamentoTattooAreas");
  localStorage.removeItem("orcamentoTattooPromos");
  config = { ...DEFAULT_CONFIG };
  areas = { ...DEFAULT_AREAS };
  promotions = [...DEFAULT_PROMOS];
  fillConfig();
  renderRows();
  renderPromoRows();
  $("notice").textContent = "Padrões restaurados.";
  $("notice").classList.add("show");
}

$("save").addEventListener("click", save);
$("reset").addEventListener("click", reset);
$("addPromo").addEventListener("click", () => {
  promotions.push(promo("Nova promoção", "Descreva o pacote", [], .9, "frente"));
  renderPromoRows();
});
$("promoRows").addEventListener("input", updatePromoPreviews);
$("promoRows").addEventListener("change", updatePromoPreviews);
$("promoRows").addEventListener("click", event => {
  const button = event.target.closest("[data-remove-promo]");
  if (!button) return;
  promotions = collectPromosFromForm();
  promotions.splice(Number(button.dataset.removePromo), 1);
  renderPromoRows();
});
$("rows").addEventListener("input", updatePromoPreviews);
$("rows").addEventListener("change", updatePromoPreviews);
document.querySelectorAll("[data-admin-tab]").forEach(button => {
  button.addEventListener("click", () => {
    document.querySelectorAll("[data-admin-tab]").forEach(item => item.classList.toggle("active", item === button));
    document.querySelectorAll("[data-admin-panel]").forEach(panel => {
      panel.hidden = panel.dataset.adminPanel !== button.dataset.adminTab;
    });
  });
});

fillConfig();
renderRows();
renderPromoRows();
</script>
</body>
</html>
