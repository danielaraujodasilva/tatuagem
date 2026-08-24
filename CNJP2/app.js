import { SERVICES, LEGEND } from './services.js';

const catalog = document.querySelector('#catalog');
const filtersEl = document.querySelector('#filters');
const searchEl = document.querySelector('#search');
const resultCount = document.querySelector('#resultCount');
const summaryEl = document.querySelector('#summary');
const template = document.querySelector('#nodeTemplate');
const clearFiltersBtn = document.querySelector('#clearFilters');

const state = { query: '', filters: new Set() };

const normalize = (value = '') => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

function countNodes(items) {
  return items.reduce((acc, item) => acc + 1 + (item.children ? countNodes(item.children) : 0), 0);
}

function countLeaves(items) {
  return items.reduce((acc, item) => acc + (item.children?.length ? countLeaves(item.children) : 1), 0);
}

function collectTagCount(tag, items = SERVICES) {
  return items.reduce((acc, item) => acc + (item.tags?.includes(tag) ? 1 : 0) + (item.children ? collectTagCount(tag, item.children) : 0), 0);
}

function renderSummary() {
  const stats = [
    [SERVICES.length, 'grandes áreas'],
    [countLeaves(SERVICES), 'serviços mapeados'],
    [collectTagCount('green'), 'atuações administrativas'],
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

function createLeaf(item) {
  const el = document.createElement('article');
  el.className = 'leaf';
  el.innerHTML = `<div><strong>${item.title}</strong><p>${item.description ?? ''}</p></div>`;
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

  title.textContent = item.title;
  description.textContent = item.description ?? '';
  icon.textContent = item.icon ?? (depth === 0 ? 'category' : 'folder');
  tagsTarget.replaceWith(createTags(item.tags));

  if (item.stage) {
    stageBadge.hidden = false;
    stageBadge.textContent = item.stage;
  }

  item.children.forEach(child => content.appendChild(createNode(child, depth + 1)));

  if (state.query || state.filters.size) details.open = true;
  return details;
}

function render() {
  const filtered = filterTree(SERVICES);
  catalog.replaceChildren();

  if (!filtered.length) {
    catalog.innerHTML = '<div class="empty"><span class="material-symbols-rounded">search_off</span><p>Nada encontrado. A burocracia venceu esta rodada.</p></div>';
    resultCount.textContent = '0 áreas encontradas';
    return;
  }

  filtered.forEach(item => catalog.appendChild(createNode(item)));
  resultCount.textContent = `${filtered.length} de ${SERVICES.length} áreas exibidas • ${countNodes(filtered)} itens visíveis`;
}

searchEl.addEventListener('input', event => {
  state.query = event.target.value.trim();
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
render();
