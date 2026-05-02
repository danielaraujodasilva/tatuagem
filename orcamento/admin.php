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
  --card: #151515;
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
  width: min(1280px, 100%);
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
  min-width: 1160px;
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

td input[type="number"] { width: 92px; }
td input[type="checkbox"] {
  width: 20px;
  height: 20px;
  accent-color: var(--red);
}

.area-name { min-width: 150px; }
.area-text { min-width: 260px; }

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
  .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<main class="container">
  <header class="header">
    <div>
      <h1>Admin Orçamento</h1>
      <p>As alterações ficam salvas no navegador por enquanto. Depois isso pode virar JSON, MySQL ou painel com login sem mudar a lógica da página pública.</p>
    </div>
    <div class="actions">
      <a class="link" href="index.php">Voltar ao orçamento</a>
      <button class="btn danger" id="reset" type="button">Restaurar padrão</button>
      <button class="btn primary" id="save" type="button">Salvar tudo</button>
    </div>
  </header>

  <div class="notice" id="notice">Configurações salvas. Abra a página de orçamento para ver o resultado.</div>

  <section class="card">
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
      <div class="field full">
        <label for="gallery">Galeria (uma imagem por linha)</label>
        <textarea id="gallery" placeholder="../fotos/arquivo.jpg"></textarea>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Ativa</th>
            <th>Área</th>
            <th>Mín</th>
            <th>Máx</th>
            <th>Descrição</th>
            <th>Dor</th>
            <th>Funciona melhor</th>
            <th>Orientação</th>
            <th>Tamanho mínimo</th>
            <th>Aviso</th>
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
  intro: "Selecione a região, informe o tamanho e envie uma referência. O sistema monta uma prévia de orçamento e uma mensagem pronta para o WhatsApp.",
  gallery: [
    "../fotos/0f20fb8e-5fb1-499f-aead-4f6b93210dc9.jpg",
    "../fotos/18213344-55a0-4de9-88f6-9693601f6984.jpg",
    "../fotos/1ddbc90c-0eb4-4976-8406-e19c4fd273b9.jpg",
    "../fotos/248ef59a-0795-4cf4-be02-b2643415556e.jpg",
    "../fotos/2a5282e1-ad90-45f8-82d9-8f3b457bc102.jpg",
    "../fotos/35c9c706-59e5-455b-aa11-b26370b37eb4.jpg",
    "../fotos/3dbb1060-bdd3-405f-a669-dc9b4da5a1de.jpg",
    "../fotos/4e0ebe81-bced-4d86-94e7-5cb2f9973c22.jpg"
  ]
};

const DEFAULT_AREAS = {
  cabeca: area("Cabeça", 2500, 6500, "Área extrema e muito visível. Precisa de leitura forte, contraste e desenho que envelheça bem.", "Alta", "Blackwork, ornamental e projetos ousados", "Encaixe anatômico", "Médio para cima", "Área muito visível. Avaliação é obrigatória antes de fechar."),
  pescoco: area("Pescoço", 1500, 3500, "Região de alto impacto visual, boa para composições verticais e fechamentos.", "Alta", "Lettering, blackwork, ornamental", "Vertical ou anatômica", "Médio", "Região com movimento e exposição solar. Contraste ajuda muito."),
  nuca: area("Nuca", 900, 2200, "Boa para peças menores, símbolos, ornamentos e complemento de costas ou pescoço.", "Média", "Projetos pequenos e médios", "Vertical ou central", "Pequeno forte", ""),
  ombros: area("Ombros", 1800, 4200, "Excelente para encaixe anatômico, fechamentos de braço e projetos com presença.", "Média", "Realismo, mandalas, blackwork", "Circular ou anatômica", "Médio", "Muito procurado para começar fechamento de braço."),
  peito: area("Peito", 2500, 7000, "Área grande e visual, ideal para projetos simétricos ou peça central de impacto.", "Alta", "Realismo, blackwork, ornamental", "Horizontal ou central", "Grande", ""),
  abdomen: area("Abdômen", 2000, 5600, "Área ampla, sensível e com variação de elasticidade. Pede planejamento limpo.", "Alta", "Projetos verticais, centrais e fechamentos", "Vertical ou central", "Médio para grande", "Distorce mais com movimento e variação corporal."),
  costela: area("Costela", 1800, 5600, "Ótima para composições laterais, florais, lettering e projetos verticais.", "Alta", "Florais, lettering, realismo", "Vertical", "Médio", "Região sensível. Linhas muito finas precisam de respiro."),
  quadril: area("Quadril / Virilha", 1500, 4200, "Boa para peças ornamentais, sensuais ou complemento de perna e abdômen.", "Média/Alta", "Ornamental, fineline e composições laterais", "Anatômica", "Médio", ""),
  costas: area("Costas", 3200, 12000, "Área nobre para projetos grandes, painéis, simetrias e fechamentos de alto impacto.", "Média", "Realismo, blackwork, oriental e painéis", "Vertical, central ou painel", "Grande", "Costas é uma área única, sem divisão alta/baixa."),
  lombar: area("Lombar", 1500, 4200, "Boa para peças centrais, ornamentais e complementos de fechamento.", "Média/Alta", "Ornamentos, mandalas e simetria", "Horizontal ou central", "Médio", ""),
  braco: area("Braço", 1800, 4800, "Uma das melhores áreas: boa leitura, boa resistência e encaixe anatômico.", "Média", "Realismo, anime, blackwork", "Vertical ou anatômica", "Médio", "Uma das áreas mais procuradas."),
  antebraco_interno: area("Antebraço interno", 1200, 3400, "Área muito procurada, boa para projetos detalhados e leitura frontal.", "Média/Alta", "Fineline, realismo, lettering", "Vertical", "Médio", "Campeã de orçamento."),
  antebraco_externo: area("Antebraço externo", 1200, 3200, "Área resistente e excelente para contraste, com boa leitura no dia a dia.", "Média", "Projetos visíveis e fechamentos", "Vertical", "Médio", ""),
  pulso: area("Pulso", 600, 1600, "Área menor e delicada para símbolos, pulseiras, detalhes e complementos.", "Média/Alta", "Lettering, símbolos e ornamentos", "Circular ou horizontal", "Pequeno simples", "Linhas muito pequenas podem abrir com o tempo."),
  mao: area("Mão", 900, 2800, "Área muito visível e com maior desgaste. Precisa de desenho forte e manutenção consciente.", "Alta", "Blackwork, símbolos e lettering", "Encaixe anatômico", "Pequeno forte", "Mão desgasta mais. Contraste e simplicidade vencem."),
  dedos_mao: area("Dedos da mão", 400, 1300, "Área pequena, visível e com alto desgaste. Melhor para símbolos simples e letras.", "Alta", "Símbolos simples, letras e ornamentos", "Linear", "Micro, mas sem excesso", "Dedos precisam de desenho simples para não virar borrão com o tempo."),
  coxa_frontal: area("Coxa frontal", 2500, 7200, "Área ampla para peças grandes, fechamentos e projetos com bastante detalhe.", "Média", "Realismo, anime, blackwork", "Vertical", "Grande", "Ótima para projeto grande com impacto."),
  coxa_posterior: area("Coxa posterior", 2300, 6600, "Excelente para continuidade de fechamento de perna e composição vertical.", "Média/Alta", "Fechamento de perna e projetos verticais", "Vertical", "Grande", ""),
  gluteo: area("Glúteo", 1800, 4600, "Boa para composições grandes e complementos de perna ou quadril.", "Média", "Ornamental, blackwork e fechamentos", "Anatômica", "Médio", ""),
  joelho: area("Joelho", 1200, 3800, "Área complexa, dobra bastante e exige desenho inteligente.", "Alta", "Ornamentos, mandalas e blackwork", "Circular", "Médio", "Região que distorce com movimento. Desenho precisa respirar."),
  joelho_posterior: area("Parte de trás do joelho", 900, 2700, "Área sensível e delicada, geralmente usada para complementar fechamento de perna.", "Alta", "Complementos e ornamentos", "Horizontal ou anatômica", "Pequeno forte", "Dobra bastante. Evitar microdetalhe."),
  canela: area("Canela", 1800, 4800, "Área de impacto visual, ótima para projetos verticais e contraste forte.", "Alta", "Realismo, anime e blackwork", "Vertical", "Médio", ""),
  panturrilha: area("Panturrilha", 1800, 4800, "Boa curvatura para peças médias, grandes e fechamento de perna.", "Média", "Peças médias, grandes e fechamento", "Vertical ou anatômica", "Médio", ""),
  tornozelo: area("Tornozelo", 600, 1900, "Área menor, boa para detalhes, tornozeleiras e complementos.", "Média/Alta", "Ornamentos, símbolos e fineline", "Circular", "Pequeno forte", ""),
  pe: area("Pé", 900, 2800, "Área visível, sensível e com desgaste. Precisa de projeto simples e bem planejado.", "Alta", "Símbolos, ornamentos e projetos pequenos", "Horizontal ou anatômica", "Pequeno forte", "Pé desgasta bastante por atrito. Evitar detalhe minúsculo."),
  dedos_pe: area("Dedos do pé", 400, 1100, "Área pequena, sensível e com alto desgaste. Melhor para marcas simples.", "Alta", "Símbolos pequenos e letras", "Linear", "Micro simples", "Dedos do pé têm alto desgaste e pedem manutenção.")
};

function area(titulo, min, max, descricao, dor, indicacao, orientacao, minimo, aviso) {
  return { titulo, min, max, descricao, dor, indicacao, orientacao, minimo, aviso, ativa: true };
}

const $ = (id) => document.getElementById(id);
let config = load("orcamentoTattooConfig", DEFAULT_CONFIG);
let areas = load("orcamentoTattooAreas", DEFAULT_AREAS);

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
  $("gallery").value = Array.isArray(config.gallery) ? config.gallery.join("\n") : "";
}

function renderRows() {
  $("rows").innerHTML = Object.entries(areas).map(([id, item]) => `
    <tr data-id="${id}">
      <td><input data-field="ativa" type="checkbox" ${item.ativa !== false ? "checked" : ""}></td>
      <td><input class="area-name" data-field="titulo" value="${escapeAttr(item.titulo)}"></td>
      <td><input data-field="min" type="number" min="0" step="50" value="${item.min}"></td>
      <td><input data-field="max" type="number" min="0" step="50" value="${item.max}"></td>
      <td><textarea class="area-text" data-field="descricao">${escapeHtml(item.descricao)}</textarea></td>
      <td><input data-field="dor" value="${escapeAttr(item.dor)}"></td>
      <td><textarea class="area-text" data-field="indicacao">${escapeHtml(item.indicacao)}</textarea></td>
      <td><input data-field="orientacao" value="${escapeAttr(item.orientacao)}"></td>
      <td><input data-field="minimo" value="${escapeAttr(item.minimo)}"></td>
      <td><textarea class="area-text" data-field="aviso">${escapeHtml(item.aviso || "")}</textarea></td>
    </tr>
  `).join("");
}

function escapeHtml(value = "") {
  return String(value).replace(/[&<>"']/g, char => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#039;" }[char]));
}

function escapeAttr(value = "") {
  return escapeHtml(value);
}

function save() {
  config = {
    whatsapp: $("whatsapp").value.trim(),
    cta: $("cta").value.trim(),
    intro: $("intro").value.trim(),
    gallery: $("gallery").value.split("\n").map(line => line.trim()).filter(Boolean)
  };

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
  $("notice").classList.add("show");
  setTimeout(() => $("notice").classList.remove("show"), 3200);
}

function reset() {
  localStorage.removeItem("orcamentoTattooConfig");
  localStorage.removeItem("orcamentoTattooAreas");
  config = { ...DEFAULT_CONFIG };
  areas = { ...DEFAULT_AREAS };
  fillConfig();
  renderRows();
  $("notice").textContent = "Padrões restaurados.";
  $("notice").classList.add("show");
}

$("save").addEventListener("click", save);
$("reset").addEventListener("click", reset);

fillConfig();
renderRows();
</script>
</body>
</html>
