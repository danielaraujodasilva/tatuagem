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

const state = { query: '', filters: new Set(), sortBy: 'score', direction: 'desc' };

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
  let easeDelta = 0;
  let complexityDelta = 0;
  let scoreDelta = 0;

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
  filtersEl.innerHTML = Object.entries(LEGEND).map(([key, item]) => `
    <button type="button" class="chip ${key}" data-filter="${key}" title="${item.short}">${item.label}</button>
  `).join('');

  filtersEl.addEventListener('click', (event) => {
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
  const direction = state.direction === 'desc' ? -1 : 1;
  const sorted = items.map(item => ({
    ...item,
    children: item.children ? sortTree(item.children) : undefined
  }));

  return sorted.sort((a, b) => {
    if (state.sortBy === 'title') return direction * a.title.localeCompare(b.title, 'pt-BR');
    const av = a.metrics?.[state.sortBy] ?? 0;
    const bv = b.metrics?.[state.sortBy] ?? 0;
    if (av === bv) return b.metrics.score - a.metrics.score;
    return direction * (av - bv);
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
  const items = [
    ['Facilidade', metrics.ease],
    ['Receita', metrics.revenue],
    ['Recorrência', metrics.recurring],
    ['Complexidade', metrics.complexity]
  ];
  return items.map(([label, value]) => `<span title="${label}: ${value}/100"><b>${label}</b> ${value}</span>`).join('');
}

function scoreClass(score) {
  if (score >= 95) return 'score-hot';
  if (score >= 85) return 'score-good';
  if (score >= 70) return 'score-mid';
  return 'score-low';
}

function createLeaf(item) {
  const el = document.createElement('article');
  el.className = 'leaf';
  el.innerHTML = `
    <div class="leaf-copy">
      <div class="leaf-title-row">
        <strong>${item.title}</strong>
        <span class="score-badge ${scoreClass(item.metrics.score)}">${item.metrics.score}</span>
      </div>
      <p>${item.description ?? ''}</p>
      <div class="metrics-mini">${metricPills(item.metrics)}</div>
    </div>`;
  el.appendChild(createTags(item.tags));
  return el;
}

function createNode(item, depth = 0) {
  if (!item.children?.length) return createLeaf(item);

  const fragment = template.content.cloneNode(true);
  const details = fragment.querySelector('details');
  const title = fragment.querySelector('.node-title');
  const description = fragment.querySelector('.node-description');
  const icon = fragment.querySelector('.node-icon');
  const tagsTarget = fragment.querySelector('.node-tags');
  const content = fragment.querySelector('.node-content');
  const stageBadge = fragment.querySelector('.stage-badge');
  const scoreBadge = fragment.querySelector('.score-badge');
  const metricsMini = fragment.querySelector('.metrics-mini');

  title.textContent = item.title;
  description.textContent = item.description ?? '';
  icon.textContent = item.icon ?? (depth === 0 ? 'category' : 'folder');
  tagsTarget.replaceWith(createTags(item.tags));
  scoreBadge.textContent = item.metrics.score;
  scoreBadge.classList.add(scoreClass(item.metrics.score));
  scoreBadge.title = `Prioridade geral: ${item.metrics.score}/100`;
  metricsMini.innerHTML = metricPills(item.metrics);

  if (item.stage) {
    stageBadge.hidden = false;
    stageBadge.textContent = item.stage;
  }

  item.children.forEach(child => content.appendChild(createNode(child, depth + 1)));
  if (state.query || state.filters.size) details.open = true;
  return details;
}

function render() {
  const filtered = filterTree(HYDRATED_SERVICES);
  const ordered = sortTree(filtered);
  catalog.replaceChildren();

  if (!ordered.length) {
    catalog.innerHTML = '<div class="empty"><span class="material-symbols-rounded">search_off</span><p>Nada encontrado. Até a burocracia conseguiu esconder isso.</p></div>';
    resultCount.textContent = '0 áreas encontradas';
    return;
  }

  ordered.forEach(item => catalog.appendChild(createNode(item)));
  resultCount.textContent = `${ordered.length} de ${HYDRATED_SERVICES.length} áreas • ${countNodes(ordered)} itens visíveis`;
}

function updateDirectionUI() {
  const desc = state.direction === 'desc';
  sortDirectionBtn.querySelector('.material-symbols-rounded').textContent = desc ? 'south' : 'north';
  sortDirectionText.textContent = desc ? 'Maior → menor' : 'Menor → maior';
}

searchEl.addEventListener('input', event => {
  state.query = event.target.value.trim();
  render();
});

sortByEl.addEventListener('change', event => {
  state.sortBy = event.target.value;
  render();
});

sortDirectionBtn.addEventListener('click', () => {
  state.direction = state.direction === 'desc' ? 'asc' : 'desc';
  updateDirectionUI();
  render();
});

document.addEventListener('keydown', event => {
  if (event.key === '/' && document.activeElement !== searchEl) {
    event.preventDefault();
    searchEl.focus();
  }
  if (event.key === 'Escape' && document.activeElement === searchEl) {
    searchEl.value = '';
    state.query = '';
    searchEl.blur();
    render();
  }
});

document.querySelector('#expandAll').addEventListener('click', () => {
  document.querySelectorAll('details').forEach(item => item.open = true);
});

document.querySelector('#collapseAll').addEventListener('click', () => {
  document.querySelectorAll('details').forEach(item => item.open = false);
});

clearFiltersBtn.addEventListener('click', () => {
  state.filters.clear();
  state.query = '';
  searchEl.value = '';
  document.querySelectorAll('.chip.active').forEach(chip => chip.classList.remove('active'));
  render();
});

renderSummary();
renderFilters();
updateDirectionUI();
render();
