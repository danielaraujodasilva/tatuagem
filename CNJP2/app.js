import { SERVICES, LEGEND } from './services.js';
import { TECH_SERVICES } from './technology.js';

const BASE_METRICS = {
  'documentos-cartorios': { score: 92, ease: 95, revenue: 70, recurring: 70, complexity: 25 },
  'conflitos-extrajudicial': { score: 84, ease: 78, revenue: 80, recurring: 72, complexity: 45 },
  'imobiliario': { score: 94, ease: 72, revenue: 100, recurring: 85, complexity: 68 },
  'empresarial-administrativo': { score: 93, ease: 85, revenue: 90, recurring: 95, complexity: 48 },
  'familia-sucessoes': { score: 72, ease: 50, revenue: 92, recurring: 45, complexity: 82 },
  consumidor: { score: 68, ease: 82, revenue: 55, recurring: 35, complexity: 35 },
  b2b: { score: 95, ease: 72, revenue: 100, recurring: 100, complexity: 65 },
  educacao: { score: 55, ease: 70, revenue: 75, recurring: 80, complexity: 60 }
};

const PRICE_RANGES = {
  'documentos-cartorios': [80, 450],
  'conflitos-extrajudicial': [180, 1400],
  imobiliario: [250, 3200],
  'empresarial-administrativo': [150, 1500],
  'familia-sucessoes': [450, 3800],
  consumidor: [100, 700],
  b2b: [500, 3500],
  educacao: [250, 1800],
  'Sites & Presença Digital': [250, 2200],
  'CRM & Sistemas Internos': [700, 5500],
  'WhatsApp & Atendimento': [350, 3000],
  'Automação de Processos': [350, 4500],
  'IA Aplicada a Negócios': [600, 5500],
  'Dados, Relatórios & Financeiro': [300, 2600],
  'Infraestrutura Digital': [120, 1600],
  'Pacotes de Transformação Digital': [900, 6000]
};

const PRICE_OVERRIDES = {
  'Certidão de nascimento': 100,
  'Certidão de casamento': 120,
  'Certidão de óbito': 100,
  'Certidão de protesto': 120,
  'Assessoria para reconhecimento de firma': 100,
  'Assessoria para autenticação': 100,
  'Consulta de protestos': 100,
  'Pesquisa de matrícula': 150,
  'Matrícula atualizada': 150,
  'Site institucional': 1200,
  'Landing page de captação': 700,
  'Página de serviço': 450,
  'Catálogo digital': 900,
  'Manutenção de site': 300,
  'Hospedagem e gestão técnica': 200,
  'Domínio, DNS e SSL': 250,
  'E-mail profissional': 250,
  'Acesso remoto seguro': 350,
  'Diagnóstico de processos digitais': 600,
  'Kit Presença Digital': 1800,
  'Kit Atendimento': 2800,
  'Kit Escritório Digital': 4500,
  'Kit Automação Administrativa': 3500
};

const LEGAL_CHILDREN = SERVICES.map(item => ({ ...item, metrics: item.metrics ?? BASE_METRICS[item.id], pricingKey: item.id }));
const TECH_CHILDREN = TECH_SERVICES.children.map(item => ({ ...item, pricingKey: item.title }));

const ROOT = [{
  id: 'cnjp',
  title: 'CNJP — Soluções',
  icon: 'account_tree',
  description: 'Visão completa dos serviços possíveis da empresa.',
  children: [
    { id: 'juridico', title: 'Jurídico & Extrajudicial', icon: 'balance', description: 'Serviços jurídicos, administrativos, documentais e extrajudiciais.', children: LEGAL_CHILDREN },
    { id: 'tecnologia', title: 'Tecnologia', icon: 'terminal', description: 'Sites, sistemas, automações, WhatsApp, IA, dados e infraestrutura.', children: TECH_CHILDREN }
  ]
}];

const catalog = document.querySelector('#catalog');
const filtersEl = document.querySelector('#filters');
const searchEl = document.querySelector('#search');
const resultCount = document.querySelector('#resultCount');
const summaryEl = document.querySelector('#summary');
const template = document.querySelector('#nodeTemplate');
const clearFiltersBtn = document.querySelector('#clearFilters');
const sortByEl = document.querySelector('#sortBy');
const sortDirectionBtn = document.querySelector('#sortDirection');
const sortDirectionText = document.querySelector('#sortDirectionText');
const applySortBtn = document.querySelector('#applySort');
const priceFilterEl = document.querySelector('#priceFilter');

const state = { query: '', filters: new Set(), priceBand: 'all', sortBy: 'score', direction: 'desc' };
let pendingDirection = 'desc';

const normalize = (v = '') => v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const clamp = v => Math.max(0, Math.min(100, Math.round(v)));
const money = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(v);
const round50 = v => Math.max(50, Math.round(v / 50) * 50);

function deriveMetrics(item, parent = null) {
  if (item.metrics) return item.metrics;
  const p = parent ?? { score: 75, ease: 75, revenue: 70, recurring: 65, complexity: 45 };
  const tags = item.tags ?? [];
  let ease = 0, complexity = 0, score = 0;
  if (tags.includes('green')) { ease += 8; complexity -= 8; score += 4; }
  if (tags.includes('blue')) { ease += 2; complexity += 2; }
  if (tags.includes('yellow')) { ease -= 12; complexity += 15; score -= 5; }
  if (tags.includes('red')) { ease -= 6; complexity += 8; score -= 3; }
  if (tags.includes('purple')) { ease += 4; score += 2; }
  return { score: clamp(p.score + score), ease: clamp(p.ease + ease), revenue: clamp(p.revenue), recurring: clamp(p.recurring), complexity: clamp(p.complexity + complexity) };
}

function estimateLeafPrice(item, pricingKey, metrics) {
  if (PRICE_OVERRIDES[item.title]) return PRICE_OVERRIDES[item.title];
  const [min, max] = PRICE_RANGES[pricingKey] ?? [150, 1800];
  const factor = clamp(metrics.revenue * .45 + metrics.complexity * .35 + metrics.score * .20) / 100;
  return round50(min + (max - min) * factor);
}

function hydrate(items, parentMetrics = null, inheritedPricingKey = null) {
  return items.map(item => {
    const metrics = deriveMetrics(item, parentMetrics);
    const pricingKey = item.pricingKey ?? inheritedPricingKey;
    const children = item.children ? hydrate(item.children, metrics, pricingKey) : undefined;

    if (!children?.length) {
      const price = estimateLeafPrice(item, pricingKey, metrics);
      return { ...item, metrics, pricingKey, price, priceMin: price, priceMax: price, priceLabel: `~ ${money(price)}` };
    }

    const mins = children.map(c => c.priceMin).filter(Number.isFinite);
    const maxs = children.map(c => c.priceMax).filter(Number.isFinite);
    const priceMin = mins.length ? Math.min(...mins) : 0;
    const priceMax = maxs.length ? Math.max(...maxs) : 0;
    const price = round50((priceMin + priceMax) / 2);
    const priceLabel = priceMin === priceMax ? `~ ${money(priceMin)}` : `${money(priceMin)} – ${money(priceMax)}`;
    return { ...item, metrics, pricingKey, children, price, priceMin, priceMax, priceLabel };
  });
}

const DATA = hydrate(ROOT);

function countNodes(items) { return items.reduce((a, i) => a + 1 + (i.children ? countNodes(i.children) : 0), 0); }
function countLeaves(items) { return items.reduce((a, i) => a + (i.children?.length ? countLeaves(i.children) : 1), 0); }
function collectPrices(items, out = []) { items.forEach(i => i.children?.length ? collectPrices(i.children, out) : out.push(i.price)); return out; }
function collectTagCount(tag, items = DATA) { return items.reduce((a, i) => a + (i.tags?.includes(tag) ? 1 : 0) + (i.children ? collectTagCount(tag, i.children) : 0), 0); }

function renderSummary() {
  const prices = collectPrices(DATA).filter(Number.isFinite).sort((a,b)=>a-b);
  const median = prices[Math.floor(prices.length / 2)] ?? 0;
  summaryEl.innerHTML = [
    [countLeaves(DATA), 'serviços finais'],
    [countNodes(DATA), 'itens na árvore'],
    [money(median), 'valor mediano estimado'],
    [collectTagCount('yellow'), 'itens com profissional']
  ].map(([v,l]) => `<article class="stat"><strong>${v}</strong><span>${l}</span></article>`).join('');
}

function renderFilters() {
  filtersEl.innerHTML = Object.entries(LEGEND).map(([key, item]) => `<button type="button" class="chip ${key}" data-filter="${key}" title="${item.short}">${item.label}</button>`).join('');
  filtersEl.addEventListener('click', e => {
    const b = e.target.closest('[data-filter]'); if (!b) return;
    const key = b.dataset.filter;
    state.filters.has(key) ? state.filters.delete(key) : state.filters.add(key);
    b.classList.toggle('active', state.filters.has(key)); render();
  });
}

function priceMatches(price) {
  if (state.priceBand === 'all') return true;
  const [min, max] = state.priceBand.split('-').map(Number);
  return price >= min && price <= max;
}

function itemMatchesSelf(item) {
  const q = !state.query || normalize(`${item.title} ${item.description ?? ''}`).includes(normalize(state.query));
  const t = !state.filters.size || [...state.filters].every(tag => item.tags?.includes(tag));
  const p = priceMatches(item.price ?? 0);
  return q && t && p;
}

function filterTree(items) {
  return items.map(item => {
    const children = item.children ? filterTree(item.children) : [];
    if (itemMatchesSelf(item) || children.length) return { ...item, children };
    return null;
  }).filter(Boolean);
}

function sortTree(items, depth = 0) {
  const mapped = items.map(i => ({ ...i, children: i.children ? sortTree(i.children, depth + 1) : undefined }));
  if (depth < 2) return mapped;
  const mult = state.direction === 'desc' ? -1 : 1;
  return mapped.sort((a,b) => {
    if (state.sortBy === 'title') return mult * a.title.localeCompare(b.title, 'pt-BR');
    const key = state.sortBy === 'price' ? 'price' : state.sortBy;
    const av = Number(a[key] ?? a.metrics?.[key] ?? 0), bv = Number(b[key] ?? b.metrics?.[key] ?? 0);
    return av === bv ? (b.metrics?.score ?? 0) - (a.metrics?.score ?? 0) : mult * (av - bv);
  });
}

function createTags(tags = []) {
  const wrap = document.createElement('div'); wrap.className = 'node-tags';
  tags.forEach(tag => { const s = document.createElement('span'); s.className = `tag ${tag}`; s.textContent = LEGEND[tag]?.label ?? tag; wrap.appendChild(s); });
  return wrap;
}

function metricPills(m) {
  return [['Facilidade',m.ease],['Receita',m.revenue],['Recorrência',m.recurring],['Complexidade',m.complexity]].map(([l,v])=>`<span><b>${l}</b> ${v}</span>`).join('');
}
function scoreClass(s=0){ return s>=95?'score-hot':s>=85?'score-good':s>=70?'score-mid':'score-low'; }

function createLeaf(item) {
  const el = document.createElement('article'); el.className = 'leaf';
  el.innerHTML = `<div class="leaf-copy"><div class="leaf-title-row"><strong>${item.title}</strong><span class="score-badge ${scoreClass(item.metrics.score)}">${item.metrics.score}</span><span class="price-badge">${item.priceLabel}</span></div><p>${item.description ?? ''}</p><div class="metrics-mini">${metricPills(item.metrics)}</div></div>`;
  el.appendChild(createTags(item.tags)); return el;
}

function createNode(item, depth = 0) {
  if (!item.children?.length) return createLeaf(item);
  const f = template.content.cloneNode(true), d = f.querySelector('details');
  f.querySelector('.node-title').textContent = item.title;
  f.querySelector('.node-description').textContent = item.description ?? '';
  f.querySelector('.node-icon').textContent = item.icon ?? (depth === 0 ? 'account_tree' : 'folder');
  f.querySelector('.node-tags').replaceWith(createTags(item.tags));
  const sb = f.querySelector('.score-badge'); sb.textContent = item.metrics.score; sb.classList.add(scoreClass(item.metrics.score));
  f.querySelector('.price-badge').textContent = item.priceLabel;
  f.querySelector('.metrics-mini').innerHTML = metricPills(item.metrics);
  const stage = f.querySelector('.stage-badge'); if(item.stage){stage.hidden=false;stage.textContent=item.stage;}
  const content = f.querySelector('.node-content'); item.children.forEach(c => content.appendChild(createNode(c, depth+1)));
  if (depth === 0 || state.query || state.filters.size || state.priceBand !== 'all') d.open = true;
  return d;
}

function render() {
  const ordered = sortTree(filterTree(DATA)); catalog.replaceChildren();
  if(!ordered.length){catalog.innerHTML='<div class="empty">Nada encontrado com esses filtros.</div>';resultCount.textContent='0 itens';return;}
  ordered.forEach(i=>catalog.appendChild(createNode(i)));
  const label = sortByEl.options[sortByEl.selectedIndex]?.textContent ?? state.sortBy;
  resultCount.textContent = `${countNodes(ordered)} itens visíveis • ordenação: ${label} (${state.direction === 'desc' ? 'maior → menor' : 'menor → maior'})`;
}

function updateDirectionUI(){const desc=pendingDirection==='desc';sortDirectionBtn.querySelector('.material-symbols-rounded').textContent=desc?'south':'north';sortDirectionText.textContent=desc?'Maior → menor':'Menor → maior';}
function markPending(){applySortBtn.classList.add('pending');}

searchEl.addEventListener('input',e=>{state.query=e.target.value.trim();render();});
priceFilterEl.addEventListener('change',e=>{state.priceBand=e.target.value;render();});
sortByEl.addEventListener('change',markPending);
sortDirectionBtn.addEventListener('click',()=>{pendingDirection=pendingDirection==='desc'?'asc':'desc';updateDirectionUI();markPending();});
applySortBtn.addEventListener('click',()=>{state.sortBy=sortByEl.value;state.direction=pendingDirection;applySortBtn.classList.remove('pending');render();});
document.addEventListener('keydown',e=>{if(e.key==='/'&&document.activeElement!==searchEl){e.preventDefault();searchEl.focus();}if(e.key==='Escape'&&document.activeElement===searchEl){searchEl.value='';state.query='';searchEl.blur();render();}});
document.querySelector('#expandAll').addEventListener('click',()=>document.querySelectorAll('details').forEach(d=>d.open=true));
document.querySelector('#collapseAll').addEventListener('click',()=>document.querySelectorAll('details').forEach((d,i)=>d.open=i===0));
clearFiltersBtn.addEventListener('click',()=>{state.filters.clear();state.query='';state.priceBand='all';searchEl.value='';priceFilterEl.value='all';document.querySelectorAll('.chip.active').forEach(c=>c.classList.remove('active'));render();});

renderSummary();renderFilters();updateDirectionUI();render();
