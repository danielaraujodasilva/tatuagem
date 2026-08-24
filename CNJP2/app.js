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

const ALL_SERVICES = [...SERVICES, TECH_SERVICES].map(item => ({
  ...item,
  metrics: item.metrics ?? BASE_METRICS[item.id]
}));

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

const state = { query: '', filters: new Set(), sortBy: 'score', direction: 'desc' };
let pendingDirection = 'desc';

const normalize = (value = '') => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const clamp = value => Math.max(0, Math.min(100, Math.round(value)));

function countNodes(items) {
  return items.reduce((acc, item) => acc + 1 + (item.children ? countNodes(item.children) : 0), 0);
}
function countLeaves(items) {
  return items.reduce((acc, item) => acc + (item.children?.length ? countLeaves(item.children) : 1), 0);
}
function collectTagCount(tag, items = ALL_SERVICES) {
  return items.reduce((acc, item) => acc + (item.tags?.includes(tag) ? 1 : 0) + (item.children ? collectTagCount(tag, item.children) : 0), 0);
}
function deriveMetrics(item, parentMetrics = null) {
  if (item.metrics) return item.metrics;
  const parent = parentMetrics ?? { score: 75, ease: 75, revenue: 70, recurring: 65, complexity: 45 };
  const tags = item.tags ?? [];
  let easeDelta = 0, complexityDelta = 0, scoreDelta = 0;
  if (tags.includes('green')) { easeDelta += 8; complexityDelta -= 8; scoreDelta += 4; }
  if (tags.includes('blue')) { easeDelta += 2; complexityDelta += 2; }
  if (tags.includes('yellow')) { easeDelta -= 12; complexityDelta += 15; scoreDelta -= 5; }
  if (tags.includes('red')) { easeDelta -= 6; complexityDelta += 8; scoreDelta -= 3; }
  if (tags.includes('purple')) { easeDelta += 4; scoreDelta += 2; }
  return {
    score: clamp(parent.score + scoreDelta),
    ease: clamp(parent.ease + easeDelta),
    revenue: clamp(parent.revenue),
    recurring: clamp(parent.recurring),
    complexity: clamp(parent.complexity + complexityDelta)
  };
}
function hydrateMetrics(items, parentMetrics = null) {
  return items.map(item => {
    const metrics = deriveMetrics(item, parentMetrics);
    const children = item.children ? hydrateMetrics(item.children, metrics) : undefined;
    return { ...item, metrics, children };
  });
}
const HYDRATED_SERVICES = hydrateMetrics(ALL_SERVICES);

function renderSummary() {
  const topScore = [...HYDRATED_SERVICES].sort((a, b) => b.metrics.score - a.metrics.score)[0];
  const stats = [
    [HYDRATED_SERVICES.length, 'grandes áreas'],
    [countLeaves(HYDRATED_SERVICES), 'serviços mapeados'],
    [topScore.metrics.score, `melhor nota: ${topScore.title}`],
    [collectTagCount('yellow'), 'itens com profissional']
  ];
  summaryEl.innerHTML = stats.map(([value, label]) => `<article class="stat"><strong>${value}</strong><span>${label}</span></article>`).join('');
}
function renderFilters() {
  filtersEl.innerHTML = Object.entries(LEGEND).map(([key, item]) => `<button type="button" class="chip ${key}" data-filter="${key}" title="${item.short}">${item.label}</button>`).join('');
  filtersEl.addEventListener('click', event => {
    const button = event.target.closest('[data-filter]');
    if (!button) return;
    const key = button.dataset.filter;
    state.filters.has(key) ? state.filters.delete(key) : state.filters.add(key);
    button.classList.toggle('active', state.filters.has(key));
    render();
  });
}
function itemMatchesSelf(item) {
  const haystack = normalize(`${item.title} ${item.description ?? ''}`);
  const queryMatch = !state.query || haystack.includes(normalize(state.query));
  const filterMatch = !state.filters.size || [...state.filters].every(tag => item.tags?.includes(tag));
  return queryMatch && filterMatch;
}
function filterTree(items) {
  return items.map(item => {
    const children = item.children ? filterTree(item.children) : [];
    const selfMatch = itemMatchesSelf(item);
    if (selfMatch || children.length) return { ...item, children };
    return null;
  }).filter(Boolean);
}
function sortTree(items) {
  const multiplier = state.direction === 'desc' ? -1 : 1;
  const sorted = items.map(item => ({ ...item, children: item.children ? sortTree(item.children) : undefined }));
  return sorted.sort((a, b) => {
    if (state.sortBy === 'title') return multiplier * a.title.localeCompare(b.title, 'pt-BR');
    const av = Number(a.metrics?.[state.sortBy] ?? 0);
    const bv = Number(b.metrics?.[state.sortBy] ?? 0);
    if (av === bv) return (b.metrics?.score ?? 0) - (a.metrics?.score ?? 0);
    return multiplier * (av - bv);
  });
}
function createTags(tags = []) {
  const wrap = document.createElement('div');
  wrap.className = 'node-tags';
  tags.forEach(tag => {
    const badge = document.createElement('span');
    badge.className = `tag ${tag}`;
    badge.textContent = LEGEND[tag]?.label ?? tag;
    badge.title = LEGEND[tag]?.short ?? '';
    wrap.appendChild(badge);
  });
  return wrap;
}
function metricPills(metrics) {
  const safe = metrics ?? { ease: 0, revenue: 0, recurring: 0, complexity: 0 };
  const items = [['Facilidade', safe.ease], ['Receita', safe.revenue], ['Recorrência', safe.recurring], ['Complexidade', safe.complexity]];
  return items.map(([label, value]) => `<span title="${label}: ${value}/100"><b>${label}</b> ${value}</span>`).join('');
}
function scoreClass(score = 0) {
  if (score >= 95) return 'score-hot';
  if (score >= 85) return 'score-good';
  if (score >= 70) return 'score-mid';
  return 'score-low';
}
function createLeaf(item) {
  const el = document.createElement('article');
  const score = item.metrics?.score ?? 0;
  el.className = 'leaf';
  el.innerHTML = `<div class="leaf-copy"><div class="leaf-title-row"><strong>${item.title}</strong><span class="score-badge ${scoreClass(score)}">${score}</span></div><p>${item.description ?? ''}</p><div class="metrics-mini">${metricPills(item.metrics)}</div></div>`;
  el.appendChild(createTags(item.tags));
  return el;
}
function createNode(item, depth = 0) {
  if (!item.children?.length) return createLeaf(item);
  const fragment = template.content.cloneNode(true);
  const details = fragment.querySelector('details');
  fragment.querySelector('.node-title').textContent = item.title;
  fragment.querySelector('.node-description').textContent = item.description ?? '';
  fragment.querySelector('.node-icon').textContent = item.icon ?? (depth === 0 ? 'category' : 'folder');
  fragment.querySelector('.node-tags').replaceWith(createTags(item.tags));
  const score = item.metrics?.score ?? 0;
  const scoreBadge = fragment.querySelector('.score-badge');
  scoreBadge.textContent = score;
  scoreBadge.classList.add(scoreClass(score));
  scoreBadge.title = `Prioridade geral: ${score}/100`;
  fragment.querySelector('.metrics-mini').innerHTML = metricPills(item.metrics);
  const stageBadge = fragment.querySelector('.stage-badge');
  if (item.stage) { stageBadge.hidden = false; stageBadge.textContent = item.stage; }
  const content = fragment.querySelector('.node-content');
  item.children.forEach(child => content.appendChild(createNode(child, depth + 1)));
  if (state.query || state.filters.size) details.open = true;
  return details;
}
function sortLabel() {
  const option = sortByEl.querySelector(`option[value="${state.sortBy}"]`);
  return option?.textContent ?? state.sortBy;
}
function render() {
  const ordered = sortTree(filterTree(HYDRATED_SERVICES));
  catalog.replaceChildren();
  if (!ordered.length) {
    catalog.innerHTML = '<div class="empty"><span class="material-symbols-rounded">search_off</span><p>Nada encontrado. Até a burocracia conseguiu esconder isso.</p></div>';
    resultCount.textContent = '0 áreas encontradas';
    return;
  }
  ordered.forEach(item => catalog.appendChild(createNode(item)));
  const dirText = state.direction === 'desc' ? 'maior → menor' : 'menor → maior';
  resultCount.textContent = `${ordered.length} de ${HYDRATED_SERVICES.length} áreas • ${countNodes(ordered)} itens • ordenado por ${sortLabel()} (${dirText})`;
}
function updateDirectionUI() {
  const desc = pendingDirection === 'desc';
  sortDirectionBtn.querySelector('.material-symbols-rounded').textContent = desc ? 'south' : 'north';
  sortDirectionText.textContent = desc ? 'Maior → menor' : 'Menor → maior';
}
function markSortPending() {
  applySortBtn.classList.add('active');
  applySortBtn.title = 'Clique para aplicar esta ordenação';
}
function clearSortPending() {
  applySortBtn.classList.remove('active');
  applySortBtn.title = '';
}

searchEl.addEventListener('input', event => { state.query = event.target.value.trim(); render(); });
sortByEl.addEventListener('change', markSortPending);
sortDirectionBtn.addEventListener('click', () => {
  pendingDirection = pendingDirection === 'desc' ? 'asc' : 'desc';
  updateDirectionUI();
  markSortPending();
});
applySortBtn.addEventListener('click', () => {
  state.sortBy = sortByEl.value;
  state.direction = pendingDirection;
  clearSortPending();
  render();
});

document.addEventListener('keydown', event => {
  if (event.key === '/' && document.activeElement !== searchEl) { event.preventDefault(); searchEl.focus(); }
  if (event.key === 'Escape' && document.activeElement === searchEl) { searchEl.value = ''; state.query = ''; searchEl.blur(); render(); }
});
document.querySelector('#expandAll').addEventListener('click', () => document.querySelectorAll('details').forEach(item => item.open = true));
document.querySelector('#collapseAll').addEventListener('click', () => document.querySelectorAll('details').forEach(item => item.open = false));
clearFiltersBtn.addEventListener('click', () => {
  state.filters.clear(); state.query = ''; searchEl.value = '';
  document.querySelectorAll('.chip.active').forEach(chip => chip.classList.remove('active'));
  render();
});

renderSummary();
renderFilters();
updateDirectionUI();
render();
