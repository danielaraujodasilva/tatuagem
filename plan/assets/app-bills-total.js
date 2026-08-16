const state = {
  csrf: window.PLAN_BOOT?.csrf || '',
  categories: [],
  accounts: [],
  budgets: [],
  goals: [],
  recurring: [],
  recurringMatchers: [],
  recurringMonth: '',
  recurringLocationFilter: '',
  transactions: [],
  bankImports: [],
  bankTransactions: [],
  bankOverview: null,
  bankSync: null,
  bankPreview: [],
  bankPreviewMeta: null,
  bankPreviewGroups: [],
  sheetImportRows: [],
  overview: null,
  charts: {},
  pendingShare: null,
  pendingCategoryAssignment: null,
  handledShareToken: '',
  lastAnalysis: null,
  movementView: 'grouped',
  similarTransactionGroups: [],
  movementSearchSuggestions: [],
  movementSuggestionScope: '',
  bankRequestId: 0,
};

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function asMoney(value) {
  return money.format(Number(value || 0));
}

function formPayload(form) {
  const data = Object.fromEntries(new FormData(form).entries());
  form.querySelectorAll('input[type="checkbox"]').forEach(input => {
    data[input.name] = input.checked ? 1 : 0;
  });
  return data;
}

async function api(action, options = {}) {
  const response = await fetch(`api.php?action=${action}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': state.csrf,
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const text = await response.text();
  let payload;
  try {
    payload = JSON.parse(text);
  } catch (error) {
    throw new Error(text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim() || 'O servidor respondeu em formato inesperado.');
  }
  if (!payload.ok) throw new Error(payload.message || 'Falha na requisicao.');
  return payload;
}

function bootLogin() {
  const form = document.querySelector('#loginForm');
  if (!form) return;
  const message = document.querySelector('#loginMessage');
  form.addEventListener('submit', async event => {
    event.preventDefault();
    message.textContent = '';
    try {
      await api('login', { method: 'POST', body: formPayload(form) });
      location.reload();
    } catch (error) {
      message.textContent = error.message;
    }
  });
}

async function bootApp() {
  if (!window.PLAN_BOOT?.authenticated) return;
  bindNavigation();
  bindModals();
  bindForms();
  bindFilters();
  bindBanking();
  bindSheetImport();
  bindSharing();
  await loadBootstrap();
  await loadTransactions();
  await loadBankTransactions();
  renderReconciliation();
  await handleSharedLink();
}

function bindSheetImport() {
  document.querySelector('#sheetWorkbookInput')?.addEventListener('change', handleSheetWorkbook);
}

async function loadBootstrap() {
  const payload = await api('bootstrap');
  Object.assign(state, {
    csrf: payload.csrf,
    categories: payload.categories,
    accounts: payload.accounts,
    budgets: payload.budgets,
    goals: payload.goals,
    recurring: payload.recurring,
    recurringMatchers: payload.recurringMatchers || [],
    recurringMonth: payload.recurringMonth || '',
    bankImports: payload.bankImports || [],
    bankOverview: payload.bankOverview || null,
    bankSync: payload.bankSync || null,
    overview: payload.overview,
  });
  renderSelects();
  renderStaticLists();
  renderOverview();
  renderReconciliation();
  renderCategoryAnalysis();
}

async function loadBankTransactions() {
  const requestId = ++state.bankRequestId;
  const { dateFrom, dateTo } = selectedPeriod();
  const suggestionScope = [
    dateFrom,
    dateTo,
    document.querySelector('#movementBankFilter')?.value || '',
    categoryFilterValue('movement'),
    document.querySelector('#movementDirectionFilter')?.value || '',
  ].join('|');
  if (suggestionScope !== state.movementSuggestionScope) {
    state.movementSuggestionScope = suggestionScope;
    state.movementSearchSuggestions = [];
  }
  const params = new URLSearchParams({
    action: 'bank_transactions',
    date_from: dateFrom,
    date_to: dateTo,
    q: document.querySelector('#movementSearchInput')?.value || document.querySelector('#bankSearchInput')?.value || '',
    bank: document.querySelector('#movementBankFilter')?.value || document.querySelector('#bankFilter')?.value || '',
    category_id: categoryFilterValue('movement'),
    direction: document.querySelector('#movementDirectionFilter')?.value || '',
    matched: document.querySelector('#movementMatchFilter')?.value || '',
  });
  const response = await fetch(`api.php?${params.toString()}`, { headers: { 'X-CSRF-Token': state.csrf } });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Erro ao carregar extratos.');
  if (requestId !== state.bankRequestId) return;
  state.bankTransactions = payload.bankTransactions || [];
  const suggestionValues = state.bankTransactions.flatMap(row => [row.description, row.bank_name]).map(clean).filter(Boolean);
  state.movementSearchSuggestions = [...new Set([...state.movementSearchSuggestions, ...suggestionValues])];
  state.bankOverview = payload.bankOverview || null;
  renderMovementSearchSuggestions();
  renderBanking();
  renderMovements();
  renderReconciliation();
  renderCategoryAnalysis();
  renderOverview();
}

async function loadTransactions() {
  const { dateFrom, dateTo } = selectedPeriod();
  const params = new URLSearchParams({
    action: 'transactions',
    date_from: dateFrom,
    date_to: dateTo,
    q: '',
    status: '',
    type: '',
  });
  const response = await fetch(`api.php?${params.toString()}`, { headers: { 'X-CSRF-Token': state.csrf } });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Erro ao carregar.');
  state.transactions = payload.transactions || [];
  state.overview = payload.overview;
  renderSelects();
  renderTransactions();
  renderBills();
  renderOverview();
  renderReconciliation();
  renderCategoryAnalysis();
}

function buildClientOverview(month, rows, existingOverview = {}) {
  const expenses = rows.filter(row => normalizedType(row) === 'expense' && normalizedBillStatus(row) !== 'ignored');
  const income = rows.filter(row => normalizedType(row) === 'income');
  const paid = expenses.filter(row => normalizedBillStatus(row) === 'paid');
  const pending = expenses.filter(row => ['pending', 'late'].includes(normalizedBillStatus(row)));
  const byCategoryMap = expenses.reduce((acc, row) => {
    const key = row.category_name || 'Sem categoria';
    acc[key] ||= { name: key, color: row.category_color || '#64748b', total: 0 };
    acc[key].total += Number(row.amount || 0);
    return acc;
  }, {});
  return {
    ...(existingOverview || {}),
    month,
    totals: {
      income: sumAmounts(income),
      expenses: sumAmounts(expenses),
      paid: sumAmounts(paid),
      pending: sumAmounts(pending),
      balance: sumAmounts(income) - sumAmounts(expenses),
    },
    byCategory: Object.values(byCategoryMap).sort((a, b) => b.total - a.total),
  };
}

function matchesMonth(row, month) {
  if (!month) return true;
  if (row.reference_month === month) return true;
  if (String(row.due_date || '').startsWith(month)) return true;
  const sheet = norm(row.source_sheet || '');
  if (!sheet) return false;
  const [year, monthNumber] = month.split('-');
  const names = {
    '01': 'janeiro',
    '02': 'fevereiro',
    '03': 'marco',
    '04': 'abril',
    '05': 'maio',
    '06': 'junho',
    '07': 'julho',
    '08': 'agosto',
    '09': 'setembro',
    '10': 'outubro',
    '11': 'novembro',
    '12': 'dezembro',
  };
  const monthName = names[monthNumber] || '';
  if (!monthName || !sheet.includes(monthName)) return false;
  if (sheet.includes(year)) return true;
  if (year === '2025' && !sheet.match(/20\d{2}/)) return true;
  return false;
}

function bindNavigation() {
  document.querySelectorAll('.nav-item').forEach(button => {
    button.addEventListener('click', () => navigateToSection(button.dataset.section));
  });
  document.querySelectorAll('[data-nav-target]').forEach(button => {
    button.addEventListener('click', () => navigateToSection(button.dataset.navTarget));
  });
}

function navigateToSection(sectionId) {
  document.querySelectorAll('.nav-item').forEach(item => item.classList.toggle('active', item.dataset.section === sectionId));
  document.querySelectorAll('.section').forEach(section => section.classList.toggle('is-visible', section.id === sectionId));
  const activeItem = document.querySelector(`.nav-item[data-section="${sectionId}"]`);
  const moreMenu = activeItem?.closest('.nav-more');
  const compactNav = window.matchMedia('(max-width: 980px)').matches;
  document.querySelectorAll('.nav-more').forEach(menu => { menu.open = !compactNav && menu === moreMenu; });
  updatePageContext(sectionId);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

const pageContexts = {
  dashboard: ['Resumo', 'Seu dinheiro', 'O essencial do periodo, sem complicacao.'],
  categoryAnalysis: ['Visao geral', 'Entenda seus padroes', 'Compare categorias, percentuais e linhas sem perder o contexto.'],
  bills: ['Acompanhar', 'Contas do periodo', 'Controle o que ja foi pago e o que ainda precisa de uma acao.'],
  movements: ['Acompanhar', 'Extratos reais', 'Explore o dinheiro que realmente entrou e saiu das suas contas.'],
  reconciliation: ['Acompanhar', 'Conferir o planejado contra o realizado', 'Compare suas contas da planilha com o que realmente apareceu no banco e resolva apenas os alertas.'],
  transactions: ['Importar dados', 'Planilha de planejamento', 'Traga suas contas e preserve as edicoes feitas no sistema.'],
  budgets: ['Planejar', 'Orcamentos mensais', 'Defina limites por categoria e acompanhe suas escolhas.'],
  goals: ['Planejar', 'Metas', 'Acompanhe o progresso do que voce quer construir.'],
  recurring: ['Planejar', 'Contas fixas do mes', 'Veja rapidamente o que ja foi pago e o que ainda esta pendente.'],
  accounts: ['Configurar', 'Contas e caixas', 'Organize onde o seu dinheiro fica e ajuste saldos quando precisar.'],
  categories: ['Configurar', 'Categorias', 'Use uma linguagem consistente para entender seus gastos.'],
};

function updatePageContext(sectionId) {
  const context = pageContexts[sectionId] || pageContexts.dashboard;
  setText('pageKicker', context[0]);
  setText('pageTitle', context[1]);
  setText('pageDescription', context[2]);
}

function bindModals() {
  document.querySelectorAll('[data-open-modal]').forEach(button => {
    button.addEventListener('click', () => {
      const modal = button.dataset.openModal;
      if (modal === 'transactionModal') prepareTransactionForm();
      if (modal === 'categoryModal') prepareCategoryForm();
      if (modal === 'budgetModal') prepareBudgetForm();
      if (modal === 'goalModal') prepareGoalForm();
      if (modal === 'accountModal') prepareAccountForm();
      if (modal === 'recurringModal') prepareRecurringForm();
      document.querySelector(`#${modal}`)?.showModal();
    });
  });
  document.querySelectorAll('[data-close]').forEach(button => {
    button.addEventListener('click', () => {
      const dialog = button.closest('dialog');
      if (dialog?.id === 'categoryModal') state.pendingCategoryAssignment = null;
      dialog?.close();
    });
  });
  document.querySelector('#categoryModal')?.addEventListener('cancel', () => {
    state.pendingCategoryAssignment = null;
  });
}

function bindSharing() {
  document.querySelector('#createShareLink')?.addEventListener('click', createShareLinkFromModal);
  document.querySelector('#copyShareLink')?.addEventListener('click', copyShareLink);
}

function bindShareButtons(root = document) {
  root.querySelectorAll('[data-share-type][data-share-id]').forEach(button => {
    button.addEventListener('click', () => openShareModal(button.dataset.shareType, Number(button.dataset.shareId)));
  });
}

function openShareModal(entityType, entityId) {
  const target = findShareTarget(entityType, entityId);
  state.pendingShare = { entity_type: entityType, entity_id: entityId };
  const summary = document.querySelector('#shareSummary');
  const url = document.querySelector('#shareUrl');
  const note = document.querySelector('#shareNote');
  const message = document.querySelector('#shareMessage');
  if (summary) summary.textContent = shareSummary(entityType, target);
  if (url) url.value = '';
  if (note) note.value = '';
  if (message) message.textContent = 'O link exige login e abre este item ja destacado na tela certa.';
  document.querySelector('#shareModal')?.showModal();
}

async function createShareLinkFromModal() {
  if (!state.pendingShare) return;
  const button = document.querySelector('#createShareLink');
  const message = document.querySelector('#shareMessage');
  const url = document.querySelector('#shareUrl');
  button.disabled = true;
  button.textContent = 'Gerando...';
  if (message) message.textContent = '';
  try {
    const payload = await api('create_share', {
      method: 'POST',
      body: {
        ...state.pendingShare,
        note: document.querySelector('#shareNote')?.value || '',
      },
    });
    if (url) {
      url.value = payload.url;
      url.select();
    }
    if (message) message.textContent = 'Link criado. Ele so abre para quem estiver logado.';
  } catch (error) {
    if (message) message.textContent = error.message;
  } finally {
    button.disabled = false;
    button.textContent = 'Gerar link';
  }
}

async function copyShareLink() {
  const input = document.querySelector('#shareUrl');
  const message = document.querySelector('#shareMessage');
  if (!input?.value) {
    if (message) message.textContent = 'Gere o link primeiro.';
    return;
  }
  input.select();
  try {
    if (navigator.clipboard) {
      await navigator.clipboard.writeText(input.value);
    } else {
      document.execCommand('copy');
    }
    if (message) message.textContent = 'Link copiado.';
  } catch (error) {
    if (message) message.textContent = 'Nao consegui copiar automaticamente, mas deixei o link selecionado.';
  }
}

function bindFilters() {
  syncPeriodControlVisibility();
  document.querySelector('#recurringLocationFilter')?.addEventListener('change', event => {
    state.recurringLocationFilter = event.currentTarget.value;
    renderRecurring();
  });
  document.querySelectorAll('[data-period-quick]').forEach(button => {
    button.addEventListener('click', async () => {
      const preset = document.querySelector('#periodPreset');
      if (preset) preset.value = button.dataset.periodQuick;
      applyPeriodPreset(button.dataset.periodQuick);
      syncPeriodControlVisibility();
      await reloadPeriodData();
    });
  });
  document.querySelector('#periodPreset')?.addEventListener('change', async event => {
    applyPeriodPreset(event.currentTarget.value);
    syncPeriodControlVisibility();
    await reloadPeriodData();
  });
  ['periodDateFrom', 'periodDateTo'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('change', async () => {
      normalizeSelectedPeriod(id);
      const preset = document.querySelector('#periodPreset');
      if (preset) preset.value = 'custom';
      syncPeriodControlVisibility();
      await reloadPeriodData();
    });
  });
  ['searchInput', 'statusFilter', 'typeFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', () => renderTransactions());
  });
  ['billsSearchInput', 'billsStatusFilter', 'billsOwnerFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', renderBills);
  });
  bindCategoryFilter('bills', renderBills);
  document.querySelector('#clearBillsFilters')?.addEventListener('click', () => {
    ['billsSearchInput', 'billsStatusFilter', 'billsCategoryParentFilter', 'billsCategoryFilter', 'billsOwnerFilter'].forEach(id => {
      const input = document.querySelector(`#${id}`);
      if (input) input.value = '';
    });
    syncCategoryFilter('bills');
    renderBills();
  });
  ['movementBankFilter', 'movementDirectionFilter', 'movementMatchFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', debounce(loadBankTransactions, 250));
  });
  const movementSearch = document.querySelector('#movementSearchInput');
  const debouncedMovementSearch = debounce(loadBankTransactions, 250);
  movementSearch?.addEventListener('input', () => {
    renderMovementSearchSuggestions();
    debouncedMovementSearch();
  });
  movementSearch?.addEventListener('focus', renderMovementSearchSuggestions);
  movementSearch?.addEventListener('blur', () => window.setTimeout(hideMovementSearchSuggestions, 140));
  movementSearch?.addEventListener('keydown', event => {
    if (event.key === 'Escape') hideMovementSearchSuggestions();
  });
  bindCategoryFilter('movement', () => loadBankTransactions());
  document.querySelector('#clearMovementFilters')?.addEventListener('click', async () => {
    ['movementBankFilter', 'movementCategoryParentFilter', 'movementCategoryFilter', 'movementDirectionFilter', 'movementMatchFilter', 'movementSearchInput', 'bankFilter', 'bankSearchInput'].forEach(id => {
      const input = document.querySelector(`#${id}`);
      if (input) input.value = '';
    });
    syncCategoryFilter('movement');
    await loadBankTransactions();
  });
  ['bankSearchInput', 'bankFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', debounce(() => {
      mirrorLegacyBankFilters(id);
      loadBankTransactions();
    }, 250));
  });
  document.querySelector('#refreshBtn')?.addEventListener('click', async () => {
    await loadBootstrap();
    await loadTransactions();
    await loadBankTransactions();
  });
  ['analysisSourceFilter', 'analysisDirectionFilter', 'analysisMinAmount', 'analysisMaxAmount', 'analysisGroupSort', 'analysisRowSort', 'analysisSearchInput'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', renderCategoryAnalysis);
  });
  bindCategoryFilter('analysis', renderCategoryAnalysis);
  document.querySelector('#copyAnalysisTable')?.addEventListener('click', copyAnalysisTable);
  document.querySelectorAll('[data-movement-view]').forEach(button => {
    button.addEventListener('click', () => {
      state.movementView = button.dataset.movementView === 'list' ? 'list' : 'grouped';
      renderMovements();
    });
  });
  document.querySelectorAll('[data-pivot-toggle]').forEach(button => {
    button.addEventListener('click', () => setPivotOpenState(button.dataset.pivotScope, button.dataset.pivotToggle === 'open'));
  });
}

function selectedPeriod() {
  return {
    dateFrom: document.querySelector('#periodDateFrom')?.value || '',
    dateTo: document.querySelector('#periodDateTo')?.value || '',
  };
}

async function reloadPeriodData() {
  await loadTransactions();
  await loadBankTransactions();
}

function applyPeriodPreset(preset) {
  if (preset === 'custom') return;
  const today = new Date();
  const to = new Date(today.getFullYear(), today.getMonth(), today.getDate());
  let from;
  if (preset === 'yesterday') {
    to.setDate(to.getDate() - 1);
    from = new Date(to);
  } else if (preset === 'month') {
    from = new Date(today.getFullYear(), today.getMonth(), 1);
    to.setMonth(today.getMonth() + 1, 0);
  } else {
    const days = Math.max(1, Number(preset) || 30);
    from = new Date(to);
    from.setDate(from.getDate() - days + 1);
  }
  const fromInput = document.querySelector('#periodDateFrom');
  const toInput = document.querySelector('#periodDateTo');
  if (fromInput) fromInput.value = inputDate(from);
  if (toInput) toInput.value = inputDate(to);
}

function syncPeriodControlVisibility() {
  const control = document.querySelector('.period-control');
  const value = document.querySelector('#periodPreset')?.value || 'yesterday';
  control?.classList.toggle('is-custom', value === 'custom');
  document.querySelectorAll('[data-period-quick]').forEach(button => {
    button.classList.toggle('is-active', button.dataset.periodQuick === value);
  });
}

function normalizeSelectedPeriod(changedId) {
  const from = document.querySelector('#periodDateFrom');
  const to = document.querySelector('#periodDateTo');
  if (!from?.value || !to?.value || from.value <= to.value) return;
  if (changedId === 'periodDateFrom') to.value = from.value;
  else from.value = to.value;
}

function inputDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function mirrorLegacyBankFilters(changedId) {
  if (changedId === 'bankSearchInput') {
    const target = document.querySelector('#movementSearchInput');
    if (target) target.value = document.querySelector('#bankSearchInput')?.value || '';
  }
  if (changedId === 'bankFilter') {
    const target = document.querySelector('#movementBankFilter');
    if (target) target.value = document.querySelector('#bankFilter')?.value || '';
  }
}

function findShareTarget(entityType, entityId) {
  const list = entityType === 'bank_transaction' ? state.bankTransactions : state.transactions;
  return list.find(row => Number(row.id) === Number(entityId)) || null;
}

function shareSummary(entityType, row) {
  if (!row) return 'Este item sera compartilhado por link seguro e exigira login para abrir.';
  if (entityType === 'bank_transaction') {
    return `${row.description} | ${formatDate(row.transaction_date)} | ${row.bank_name || 'Banco'} | ${row.direction === 'credit' ? 'entrada' : 'saida'} ${asMoney(row.amount)}`;
  }
  return `${row.description} | ${formatDate(row.due_date)} | ${statusLabel(row.status)} | ${asMoney(row.amount)}`;
}

async function handleSharedLink() {
  const token = new URLSearchParams(location.search).get('share') || '';
  if (!token || state.handledShareToken === token) return;
  state.handledShareToken = token;
  try {
    const params = new URLSearchParams({ action: 'resolve_share', token });
    const response = await fetch(`api.php?${params.toString()}`, { headers: { 'X-CSRF-Token': state.csrf } });
    const payload = await response.json();
    if (!payload.ok) throw new Error(payload.message || 'Nao consegui abrir este compartilhamento.');
    await focusSharedTarget(payload.share, payload.target);
  } catch (error) {
    alert(error.message);
  }
}

async function focusSharedTarget(share, target) {
  if (share.entity_type === 'bank_transaction') {
    const date = target.transaction_date || '';
    const from = document.querySelector('#periodDateFrom');
    const to = document.querySelector('#periodDateTo');
    const preset = document.querySelector('#periodPreset');
    const search = document.querySelector('#movementSearchInput');
    if (from && date) from.value = date;
    if (to && date) to.value = date;
    if (preset) preset.value = 'custom';
    syncPeriodControlVisibility();
    if (search) search.value = '';
    ['movementBankFilter', 'movementCategoryParentFilter', 'movementCategoryFilter', 'movementDirectionFilter', 'movementMatchFilter', 'bankFilter', 'bankSearchInput'].forEach(id => {
      const input = document.querySelector(`#${id}`);
      if (input) input.value = '';
    });
    syncCategoryFilter('movement');
    await loadBankTransactions();
    navigateToSection('movements');
    highlightSharedElement(`[data-bank-transaction-id="${target.id}"]`, `Compartilhamento aberto: ${share.title}`);
    return;
  }

  const date = target.due_date || (target.reference_month ? `${target.reference_month}-01` : '');
  const from = document.querySelector('#periodDateFrom');
  const to = document.querySelector('#periodDateTo');
  const preset = document.querySelector('#periodPreset');
  if (from && date) from.value = date;
  if (to && date) to.value = date;
  if (preset) preset.value = 'custom';
  syncPeriodControlVisibility();
  ['searchInput', 'statusFilter', 'typeFilter'].forEach(id => {
    const input = document.querySelector(`#${id}`);
    if (input) input.value = '';
  });
  await loadTransactions();
  navigateToSection(normalizedType(target) === 'income' ? 'transactions' : 'bills');
  highlightSharedElement(`[data-transaction-id="${target.id}"]`, `Compartilhamento aberto: ${share.title}`);
}

function highlightSharedElement(selector, fallbackMessage) {
  document.querySelectorAll('.share-focus').forEach(item => item.classList.remove('share-focus'));
  requestAnimationFrame(() => {
    const target = document.querySelector(selector);
    if (!target) {
      alert(fallbackMessage);
      return;
    }
    target.classList.add('share-focus');
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => target.classList.remove('share-focus'), 9000);
  });
}

function bindBanking() {
  document.querySelector('#syncBanksNow')?.addEventListener('click', syncBanksNow);
  document.querySelector('#bankFileInput')?.addEventListener('change', handleBankFiles);
  document.querySelector('#clearBankPreview')?.addEventListener('click', () => {
    state.bankPreview = [];
    state.bankPreviewMeta = null;
    state.bankPreviewGroups = [];
    renderBankPreview();
  });
  document.querySelector('#saveBankImport')?.addEventListener('click', saveBankImport);
}

async function syncBanksNow() {
  const button = document.querySelector('#syncBanksNow');
  const message = document.querySelector('#bankSyncMessage');
  const previousText = button?.textContent || '';
  try {
    if (button) {
      button.disabled = true;
      button.textContent = 'Sincronizando...';
    }
    if (message) message.textContent = 'Buscando as movimentacoes disponiveis.';
    const payload = await api('sync_banks', { method: 'POST', body: {} });
    state.bankSync = payload.bankSync || null;
    const result = payload.result || {};
    if (message) message.textContent = `${Number(result.inserted || 0)} novas e ${Number(result.updated || 0)} atualizadas.`;
    await loadBootstrap();
    await loadBankTransactions();
  } catch (error) {
    if (message) message.textContent = error.message || 'Nao foi possivel sincronizar.';
  } finally {
    if (button) {
      button.disabled = false;
      button.textContent = previousText;
    }
    renderBankSync();
  }
}

function bindForms() {
  const forms = {
    transactionForm: 'save_transaction',
    categoryForm: 'save_category',
    budgetForm: 'save_budget',
    goalForm: 'save_goal',
    accountForm: 'save_account',
    recurringForm: 'save_recurring',
    mergeRecurringForm: 'merge_recurring',
  };
  Object.entries(forms).forEach(([id, action]) => {
    document.querySelector(`#${id}`)?.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const submitButton = form.querySelector('button[type="submit"], .primary-btn');
      const previousText = submitButton?.textContent || '';
      try {
        if (submitButton) {
          submitButton.disabled = true;
          submitButton.textContent = 'Salvando...';
        }
        const payload = await api(action, { method: 'POST', body: formPayload(form) });
        if (id === 'categoryForm' && state.pendingCategoryAssignment && payload.id) {
          const pending = state.pendingCategoryAssignment;
          const name = form.elements.name.value;
          const parent = state.categories.find(category => Number(category.id) === Number(form.elements.parent_id.value || 0));
          const label = parent ? `${parent.name} / ${name}` : name;
          state.pendingCategoryAssignment = null;
          form.reset();
          form.closest('dialog')?.close();
          const scope = await chooseCategoryScope(label);
          if (scope !== 'cancel') {
            await api(pending.kind === 'bank_transaction' ? 'update_bank_transaction_category' : 'update_transaction_category', {
              method: 'POST',
              body: { id: pending.id, category_id: payload.id, apply_similar: scope === 'similar' ? 1 : 0 },
            });
          }
          await reloadAllData();
          return;
        }
        form.reset();
        form.closest('dialog')?.close();
        await reloadAllData();
      } catch (error) {
        alert(error.message || 'Nao foi possivel salvar.');
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = previousText;
        }
      }
    });
  });
}

function renderSelects() {
  const categoryOptions = state.categories.filter(category => !isUncategorizedCategory(category)).map(category => (
    `<option value="${category.id}">${escapeHtml(categoryOptionLabel(category))}</option>`
  )).join('');
  document.querySelectorAll('[data-categories]').forEach(select => {
    select.innerHTML = '<option value="">Sem categoria</option>' + categoryOptions;
  });
  renderCategoryFilter('analysis');
  renderCategoryFilter('bills');
  renderCategoryFilter('movement');
  const owners = [...new Set(state.transactions.map(row => clean(row.owner)).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'pt-BR'));
  const billsOwner = document.querySelector('#billsOwnerFilter');
  if (billsOwner) {
    const current = billsOwner.value;
    billsOwner.innerHTML = '<option value="">Todos os responsaveis</option>' + owners.map(owner => `<option value="${escapeHtml(owner)}">${escapeHtml(owner)}</option>`).join('');
    billsOwner.value = owners.includes(current) ? current : '';
  }
  document.querySelectorAll('[data-accounts]').forEach(select => {
    select.innerHTML = '<option value="">Sem conta</option>' + state.accounts.map(account => (
      `<option value="${account.id}">${escapeHtml(account.name)}</option>`
    )).join('');
  });
  renderCategoryParentOptions();
}

const categoryFilterScopes = {
  analysis: { parent: 'analysisCategoryParentFilter', child: 'analysisCategoryFilter', allLabel: 'Todas as categorias' },
  bills: { parent: 'billsCategoryParentFilter', child: 'billsCategoryFilter', allLabel: 'Todas as categorias' },
  movement: { parent: 'movementCategoryParentFilter', child: 'movementCategoryFilter', allLabel: 'Todas as categorias' },
};

function renderCategoryFilter(scope) {
  const config = categoryFilterScopes[scope];
  if (!config) return;
  const parent = document.querySelector(`#${config.parent}`);
  const child = document.querySelector(`#${config.child}`);
  if (!parent || !child) return;
  const currentParent = parent.value;
  const currentChild = child.value;
  parent.innerHTML = `<option value="">${config.allLabel}</option>` + state.categories
    .filter(category => !category.parent_id && !isUncategorizedCategory(category))
    .map(category => `<option value="${category.id}">${escapeHtml(category.name)}</option>`)
    .join('') + '<option value="__none__">Sem categoria</option>';
  parent.value = currentParent === '__none__' || state.categories.some(category => String(category.id) === currentParent && !category.parent_id && !isUncategorizedCategory(category)) ? currentParent : '';
  syncCategoryFilter(scope, currentChild);
}

function syncCategoryFilter(scope, preferredChild = null) {
  const config = categoryFilterScopes[scope];
  if (!config) return;
  const parent = document.querySelector(`#${config.parent}`);
  const child = document.querySelector(`#${config.child}`);
  if (!parent || !child) return;
  const parentId = parent.value;
  const currentChild = preferredChild === null ? child.value : preferredChild;
  const children = state.categories.filter(category => String(category.parent_id || '') === String(parentId));
  child.innerHTML = '<option value="">Todas as subcategorias</option>' + children
    .map(category => `<option value="${category.id}">${escapeHtml(category.name)}</option>`)
    .join('');
  child.value = children.some(category => String(category.id) === String(currentChild)) ? currentChild : '';
  child.hidden = !parentId || children.length === 0;
  child.disabled = !parentId || children.length === 0;
}

function bindCategoryFilter(scope, render) {
  const config = categoryFilterScopes[scope];
  if (!config) return;
  document.querySelector(`#${config.parent}`)?.addEventListener('change', () => {
    syncCategoryFilter(scope, '');
    render();
  });
  document.querySelector(`#${config.child}`)?.addEventListener('change', render);
}

function categoryFilterValue(scope) {
  const config = categoryFilterScopes[scope];
  if (!config) return '';
  return document.querySelector(`#${config.child}`)?.value || document.querySelector(`#${config.parent}`)?.value || '';
}

function categoryOptionLabel(category) {
  return category.parent_name ? `${category.parent_name} / ${category.name}` : category.name;
}

function isUncategorizedCategory(category) {
  return norm(category?.name || '') === 'sem categoria';
}

function renderCategoryParentOptions(excludeId = 0) {
  const parents = state.categories
    .filter(category => !category.parent_id && Number(category.id) !== Number(excludeId) && !isUncategorizedCategory(category))
    .sort((a, b) => String(a.name).localeCompare(String(b.name), 'pt-BR'));
  const options = '<option value="">Nenhuma (categoria principal)</option>' + parents
    .map(category => `<option value="${category.id}">${escapeHtml(category.name)}</option>`)
    .join('');
  document.querySelectorAll('[data-category-parents]').forEach(select => {
    const current = select.value;
    select.innerHTML = options;
    select.value = current;
  });
}

function inlineCategorySelect(row, kind) {
  const storedCategory = state.categories.find(category => Number(category.id) === Number(row.category_id || 0));
  const currentId = storedCategory && !isUncategorizedCategory(storedCategory) ? Number(row.category_id || 0) : 0;
  const current = currentId ? storedCategory : null;
  const parentId = current?.parent_id ? Number(current.parent_id) : currentId;
  const children = state.categories.filter(category => Number(category.parent_id) === parentId);
  const parentOptions = state.categories.filter(category => !category.parent_id && !isUncategorizedCategory(category)).map(category => (
    `<option value="${category.id}" ${Number(category.id) === parentId ? 'selected' : ''}>${escapeHtml(category.name)}</option>`
  )).join('');
  const childOptions = children.map(category => (
    `<option value="${category.id}" ${Number(category.id) === currentId ? 'selected' : ''}>${escapeHtml(category.name)}</option>`
  )).join('');
  return `<div class="category-picker" data-category-picker data-inline-kind="${kind}" data-inline-id="${row.id}">
    <select class="inline-category" data-inline-category aria-label="Alterar categoria principal">
      <option value="">Sem categoria</option>${parentOptions}<option value="__new__">+ Nova categoria</option>
    </select>
    <select class="inline-category inline-subcategory" data-inline-subcategory aria-label="Alterar subcategoria" ${children.length ? '' : 'hidden disabled'}>
      <option value="">Escolha a subcategoria</option>${childOptions}
    </select>
  </div>`;
}

function bindInlineCategoryControls(root = document) {
  root.querySelectorAll('[data-inline-category]').forEach(select => {
    select.addEventListener('change', () => handleInlineCategoryChange(select));
  });
  root.querySelectorAll('[data-inline-subcategory]').forEach(select => {
    select.addEventListener('change', () => handleInlineCategoryChange(select));
  });
}

function handleInlineCategoryChange(select) {
  const picker = select.closest('[data-category-picker]');
  if (!picker) return;
  if (select.hasAttribute('data-inline-category')) {
    if (select.value === '__new__') {
      startInlineCategoryCreation(picker);
      return;
    }
    const parentId = Number(select.value || 0);
    const children = state.categories.filter(category => Number(category.parent_id) === parentId);
    syncInlineSubcategory(picker, parentId, '');
    if (children.length) {
      picker.querySelector('[data-inline-subcategory]')?.focus();
      return;
    }
    updateInlineCategory(picker, select.value);
    return;
  }
  if (select.value) updateInlineCategory(picker, select.value);
}

function syncInlineSubcategory(picker, parentId, selectedId = '') {
  const subcategory = picker.querySelector('[data-inline-subcategory]');
  if (!subcategory) return;
  const children = state.categories.filter(category => Number(category.parent_id) === Number(parentId));
  subcategory.innerHTML = '<option value="">Escolha a subcategoria</option>' + children.map(category => (
    `<option value="${category.id}">${escapeHtml(category.name)}</option>`
  )).join('');
  subcategory.value = selectedId;
  subcategory.hidden = children.length === 0;
  subcategory.disabled = children.length === 0;
  subcategory.required = children.length > 0;
}

async function updateInlineCategory(picker, categoryId) {
  const kind = picker.dataset.inlineKind;
  const id = Number(picker.dataset.inlineId);
  const category = state.categories.find(item => Number(item.id) === Number(categoryId));
  const label = category ? categoryOptionLabel(category) : 'Sem categoria';
  const scope = await chooseCategoryScope(label);
  if (scope === 'cancel') {
    await reloadAllData();
    return;
  }
  picker.querySelectorAll('select').forEach(select => { select.disabled = true; });
  try {
    await api(kind === 'bank_transaction' ? 'update_bank_transaction_category' : 'update_transaction_category', {
      method: 'POST',
      body: { id, category_id: categoryId, apply_similar: scope === 'similar' ? 1 : 0 },
    });
    await reloadAllData();
  } catch (error) {
    alert(error.message || 'Nao foi possivel atualizar a categoria.');
    await reloadAllData();
  } finally {
    picker.querySelectorAll('select').forEach(select => { select.disabled = false; });
  }
}

function renderOverview() {
  const credits = state.bankTransactions.filter(row => row.direction === 'credit');
  const debits = state.bankTransactions.filter(row => row.direction === 'debit');
  const income = sumAmounts(credits);
  const expenses = sumAmounts(debits);
  const balance = income - expenses;
  setText('dashboardIncome', asMoney(income));
  setText('dashboardExpenses', asMoney(expenses));
  setText('dashboardBalance', asMoney(balance));
  document.querySelector('#dashboardBalance')?.classList.toggle('negative', balance < 0);
  renderFixedCoverage();
  renderDashboardBreakdown('dashboardIncomeBreakdown', credits);
  renderDashboardBreakdown('dashboardExpenseBreakdown', debits);
  renderWorkflowStrip();
  renderUpcoming();
  renderCharts();
  renderBankingSummary();
}

function startInlineCategoryCreation(picker) {
  state.pendingCategoryAssignment = {
    kind: picker.dataset.inlineKind,
    id: Number(picker.dataset.inlineId),
  };
  prepareCategoryForm();
  document.querySelector('#categoryModal')?.showModal();
}

function renderFixedCoverage() {
  const activeRules = state.recurring.filter(rule => Number(rule.is_active) === 1);
  const target = sumAmounts(activeRules);
  const paid = sumAmounts(activeRules.filter(rule => Number(rule.paid_this_month) === 1));
  const remaining = Math.max(0, target - paid);
  const percent = target > 0 ? Math.min(100, (paid / target) * 100) : 0;
  setText('fixedCoveragePercent', `${Math.round(percent)}%`);
  setText('fixedCoverageRemaining', remaining > 0 ? `Faltam ${asMoney(remaining)}` : 'Contas fixas cobertas');
  setText('fixedCoverageMeta', target > 0
    ? `${asMoney(paid)} pagos de ${asMoney(target)} em contas fixas deste mes.`
    : 'Cadastre suas contas em Recorrencias para acompanhar esta meta.');
  const bar = document.querySelector('#fixedCoverageBar');
  if (bar) bar.style.width = `${percent}%`;
}

function renderDashboardBreakdown(targetId, rows) {
  const target = document.querySelector(`#${targetId}`);
  if (!target) return;
  const groups = Object.values(rows.reduce((acc, row) => {
    const label = row.category_name || 'Sem categoria';
    acc[label] ||= { label, total: 0, count: 0 };
    acc[label].total += Number(row.amount || 0);
    acc[label].count += 1;
    return acc;
  }, {})).sort((a, b) => b.total - a.total);
  const total = sumAmounts(rows);
  target.innerHTML = groups.length ? groups.map(group => `
    <details class="money-breakdown-row">
      <summary>
        <div><strong>${escapeHtml(group.label)}</strong><small>${group.count} movimentacoes</small></div>
        <span><strong>${asMoney(group.total)}</strong><small>${formatPercent(group.total, total)}</small></span>
        <div class="money-breakdown-track"><i style="width:${Math.max(3, (group.total / total) * 100)}%"></i></div>
      </summary>
      <div class="money-breakdown-items">
        ${rows.filter(row => (row.category_name || 'Sem categoria') === group.label).slice(0, 100).map(row => `
          <div class="money-breakdown-item">
            <div class="money-breakdown-item-main">
              <strong>${escapeHtml(row.description || '(sem descricao)')}</strong>
              <small>${formatBankTransactionMoment(row)}</small>
              <div class="dashboard-item-actions">
                <button type="button" class="dashboard-category-trigger" data-dashboard-category>${group.label === 'Sem categoria' ? '+ Categoria' : 'Editar categoria'}</button>
                ${row.direction === 'debit' ? `<button type="button" class="dashboard-category-trigger" data-dashboard-recurring="${row.id}">Conta fixa</button>` : ''}
              </div>
              <div class="dashboard-category-picker" data-dashboard-category-picker hidden>${inlineCategorySelect(row, 'bank_transaction')}</div>
            </div>
            <span>${asMoney(row.amount)}</span>
          </div>
        `).join('')}
        ${group.count > 100 ? `<small class="muted">Mostrando as 100 movimentacoes mais recentes.</small>` : ''}
      </div>
    </details>
  `).join('') : '<p class="muted">Nenhuma movimentacao neste periodo.</p>';
  target.querySelectorAll('[data-dashboard-category]').forEach(button => {
    button.addEventListener('click', () => {
      const picker = button.closest('.money-breakdown-item-main')?.querySelector('[data-dashboard-category-picker]');
      if (!picker) return;
      picker.hidden = false;
      button.hidden = true;
      picker.querySelector('[data-inline-category]')?.focus();
    });
  });
  target.querySelectorAll('[data-dashboard-recurring]').forEach(button => {
    button.addEventListener('click', () => {
      const row = rows.find(item => Number(item.id) === Number(button.dataset.dashboardRecurring));
      if (row) prepareRecurringFromBankRow(row);
    });
  });
  bindInlineCategoryControls(target);
}

function prepareRecurringFromBankRow(row) {
  prepareRecurringFromGroup({
    label: row.description || '(sem descricao)',
    rows: [row],
    categoryIds: new Set([row.category_id || '']),
  });
}

function formatBankTransactionMoment(row) {
  try {
    const raw = typeof row.raw_json === 'string' ? JSON.parse(row.raw_json) : row.raw_json;
    const moment = new Date(raw?.date || '');
    if (!Number.isNaN(moment.getTime())) {
      return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: 'America/Sao_Paulo',
      }).format(moment);
    }
  } catch (error) {
    // Arquivos antigos podem nao ter o JSON original completo.
  }
  return formatDate(row.transaction_date);
}

function renderCategoryAnalysis() {
  const source = document.querySelector('#analysisSourceFilter')?.value || 'bank';
  const directionFilter = document.querySelector('#analysisDirectionFilter')?.value || 'both';
  const categoryId = categoryFilterValue('analysis');
  const minAmount = parseMoney(document.querySelector('#analysisMinAmount')?.value || '');
  const maxAmount = parseMoney(document.querySelector('#analysisMaxAmount')?.value || '');
  const groupSort = document.querySelector('#analysisGroupSort')?.value || 'value_desc';
  const rowSort = document.querySelector('#analysisRowSort')?.value || 'date_desc';
  const query = norm(document.querySelector('#analysisSearchInput')?.value || '');
  const categoryIds = categoryFilterIds(categoryId);
  const items = analyticsItems(source).filter(item => {
    if (directionFilter !== 'both' && item.direction !== directionFilter) return false;
    if (categoryId && !categoryIds.has(String(item.category_id || ''))) return false;
    if (minAmount && item.amount < minAmount) return false;
    if (maxAmount && item.amount > maxAmount) return false;
    if (!query) return true;
    return norm([item.category, item.description, item.sourceLabel, item.meta].join(' ')).includes(query);
  });
  const expenses = items.filter(item => item.direction === 'expense');
  const incomes = items.filter(item => item.direction === 'income');
  const expenseGroups = groupAnalyticsByCategory(expenses, rowSort, groupSort);
  const incomeGroups = groupAnalyticsByCategory(incomes, rowSort, groupSort);
  const expenseTotal = sumAmounts(expenses);
  const incomeTotal = sumAmounts(incomes);
  const topGroup = [...expenseGroups, ...incomeGroups].sort((a, b) => b.total - a.total)[0];
  state.lastAnalysis = {
    source,
    directionFilter,
    categoryId,
    minAmount,
    maxAmount,
    groupSort,
    rowSort,
    query,
    items,
    expenseGroups,
    incomeGroups,
    expenseTotal,
    incomeTotal,
    topGroup,
  };

  setText('analysisExpenseTotal', asMoney(expenseTotal));
  setText('analysisIncomeTotal', asMoney(incomeTotal));
  setText('analysisNetTotal', asMoney(incomeTotal - expenseTotal));
  setText('analysisTopCategory', topGroup ? `${topGroup.category} ${formatPercent(topGroup.total, topGroup.direction === 'income' ? incomeTotal : expenseTotal)}` : '-');
  setText('analysisExpenseCount', countLabel(expenseGroups.length, 'categoria', 'categorias'));
  setText('analysisIncomeCount', countLabel(incomeGroups.length, 'categoria', 'categorias'));
  renderCategoryPivot('expenseCategoryPivot', expenseGroups, expenseTotal, 'expense');
  renderCategoryPivot('incomeCategoryPivot', incomeGroups, incomeTotal, 'income');
}

async function copyAnalysisTable() {
  if (!state.lastAnalysis) renderCategoryAnalysis();
  const analysis = state.lastAnalysis;
  const button = document.querySelector('#copyAnalysisTable');
  if (!analysis || !analysis.items.length) {
    flashButtonLabel(button, 'Nada para copiar');
    return;
  }
  button.disabled = true;
  flashButtonLabel(button, 'Gerando print...');
  let image = null;
  try {
    image = await buildAnalysisWhatsappImage(analysis);
    await copyImageToClipboard(image.blob);
    flashButtonLabel(button, 'Print copiado');
  } catch (error) {
    if (image?.blob) {
      downloadBlob(image.blob, image.filename);
      flashButtonLabel(button, 'Print baixado');
      return;
    }
    const text = buildAnalysisWhatsappTable(analysis);
    try {
      await copyTextToClipboard(text);
      flashButtonLabel(button, 'Texto copiado');
    } catch (fallbackError) {
      selectFallbackText(text);
      flashButtonLabel(button, 'Texto selecionado');
    }
  } finally {
    button.disabled = false;
  }
}

async function buildAnalysisWhatsappImage(analysis) {
  const exportData = analysisExportData(analysis);
  const width = 1280;
  const padding = 34;
  const gap = 14;
  const rowBaseHeight = 34;
  const detailRows = exportData.detailRows.map(row => ({
    ...row,
    wrappedDescription: wrapCanvasText(row.description, 42),
    wrappedCategory: wrapCanvasText(row.category, 28),
  }));
  const summaryHeight = 42 + (exportData.summaryRows.length * rowBaseHeight);
  const detailsHeight = 42 + detailRows.reduce((sum, row) => sum + Math.max(rowBaseHeight, 20 + (Math.max(row.wrappedDescription.length, row.wrappedCategory.length) * 18)), 0);
  const height = padding + 74 + 72 + 40 + summaryHeight + gap + detailsHeight + padding;
  const scale = Math.min(2, window.devicePixelRatio || 1);
  const canvas = document.createElement('canvas');
  canvas.width = Math.round(width * scale);
  canvas.height = Math.round(height * scale);
  canvas.style.width = `${width}px`;
  canvas.style.height = `${height}px`;
  const ctx = canvas.getContext('2d');
  ctx.scale(scale, scale);
  drawAnalysisImage(ctx, { width, height, padding, analysis, exportData, detailRows });
  const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 0.95));
  if (!blob) throw new Error('Nao foi possivel gerar o print.');
  return { blob, filename: `analise-financeira-${new Date().toISOString().slice(0, 10)}.png` };
}

function drawAnalysisImage(ctx, options) {
  const { width, height, padding, analysis, exportData, detailRows } = options;
  ctx.fillStyle = '#eef4fb';
  ctx.fillRect(0, 0, width, height);
  ctx.fillStyle = '#ffffff';
  roundRect(ctx, padding / 2, padding / 2, width - padding, height - padding, 16);
  ctx.fill();

  let y = padding + 12;
  ctx.fillStyle = '#0f172a';
  ctx.font = '800 34px Arial, sans-serif';
  ctx.fillText('Analise financeira', padding, y);
  y += 28;
  ctx.fillStyle = '#475569';
  ctx.font = '500 18px Arial, sans-serif';
  ctx.fillText(truncateText(`Filtros: ${analysisFilterSummary()}`, 118), padding, y);
  y += 32;

  const kpis = [
    { label: 'Gastos', value: asMoney(analysis.expenseTotal), color: '#dc2626' },
    { label: 'Ganhos', value: asMoney(analysis.incomeTotal), color: '#059669' },
    { label: 'Saldo', value: asMoney(analysis.incomeTotal - analysis.expenseTotal), color: '#d97706' },
    { label: 'Linhas', value: String(analysis.items.length), color: '#2563eb' },
  ];
  const cardWidth = (width - (padding * 2) - 30) / 4;
  kpis.forEach((kpi, index) => {
    const x = padding + index * (cardWidth + 10);
    ctx.fillStyle = '#f8fafc';
    roundRect(ctx, x, y, cardWidth, 62, 10);
    ctx.fill();
    ctx.fillStyle = '#64748b';
    ctx.font = '700 13px Arial, sans-serif';
    ctx.fillText(kpi.label.toUpperCase(), x + 14, y + 22);
    ctx.fillStyle = kpi.color;
    ctx.font = '900 23px Arial, sans-serif';
    ctx.fillText(kpi.value, x + 14, y + 49);
  });
  y += 92;

  y = drawImageSectionTitle(ctx, 'Resumo por categoria', padding, y);
  y = drawSummaryTable(ctx, exportData.summaryRows, padding, y, width - padding * 2);
  y += 18;
  y = drawImageSectionTitle(ctx, 'Linhas filtradas', padding, y);
  drawDetailTable(ctx, detailRows, padding, y, width - padding * 2);
}

function drawImageSectionTitle(ctx, title, x, y) {
  ctx.fillStyle = '#0f172a';
  ctx.font = '800 22px Arial, sans-serif';
  ctx.fillText(title, x, y);
  return y + 16;
}

function drawSummaryTable(ctx, rows, x, y, width) {
  const columns = [
    { label: 'Tipo', width: 92 },
    { label: 'Categoria', width: 230 },
    { label: 'Subcategoria', width: 250 },
    { label: 'Itens', width: 70 },
    { label: 'Total', width: 155, align: 'right' },
    { label: '%', width: 90, align: 'right' },
  ];
  const rowHeight = 34;
  drawTableHeader(ctx, columns, x, y, width, rowHeight);
  y += rowHeight;
  rows.forEach((row, index) => {
    drawTableRowBackground(ctx, x, y, width, rowHeight, index);
    drawTableCells(ctx, columns, x, y, [
      row.type,
      row.category,
      row.subcategory,
      row.items,
      row.total,
      row.percent,
    ], rowHeight);
    y += rowHeight;
  });
  return y;
}

function drawDetailTable(ctx, rows, x, y, width) {
  const columns = [
    { label: 'Data', width: 112 },
    { label: 'Tipo', width: 84 },
    { label: 'Categoria', width: 280 },
    { label: 'Descricao', width: 510 },
    { label: 'Valor', width: 160, align: 'right' },
  ];
  const headerHeight = 34;
  drawTableHeader(ctx, columns, x, y, width, headerHeight);
  y += headerHeight;
  rows.forEach((row, index) => {
    const lineCount = Math.max(row.wrappedDescription.length, row.wrappedCategory.length, 1);
    const rowHeight = Math.max(36, 18 + lineCount * 18);
    drawTableRowBackground(ctx, x, y, width, rowHeight, index);
    drawMultilineTableCells(ctx, columns, x, y, row, rowHeight);
    y += rowHeight;
  });
}

function drawTableHeader(ctx, columns, x, y, width, height) {
  ctx.fillStyle = '#0f172a';
  roundRect(ctx, x, y, width, height, 8);
  ctx.fill();
  ctx.fillStyle = '#ffffff';
  ctx.font = '800 13px Arial, sans-serif';
  let cursor = x;
  columns.forEach(column => {
    drawAlignedText(ctx, column.label, cursor + 10, y + 22, column.width - 20, column.align);
    cursor += column.width;
  });
}

function drawTableRowBackground(ctx, x, y, width, height, index) {
  ctx.fillStyle = index % 2 ? '#ffffff' : '#f8fafc';
  ctx.fillRect(x, y, width, height);
  ctx.strokeStyle = '#dbe5f0';
  ctx.beginPath();
  ctx.moveTo(x, y + height);
  ctx.lineTo(x + width, y + height);
  ctx.stroke();
}

function drawTableCells(ctx, columns, x, y, values, height) {
  ctx.fillStyle = '#0f172a';
  ctx.font = '600 14px Arial, sans-serif';
  let cursor = x;
  columns.forEach((column, index) => {
    drawAlignedText(ctx, truncateText(values[index], Math.max(8, Math.floor(column.width / 8))), cursor + 10, y + Math.round(height / 2) + 5, column.width - 20, column.align);
    cursor += column.width;
  });
}

function drawMultilineTableCells(ctx, columns, x, y, row, height) {
  const values = [
    [formatDate(row.date)],
    [row.type],
    row.wrappedCategory,
    row.wrappedDescription,
    [`${row.direction === 'income' ? '+' : '-'} ${asMoney(row.amount)}`],
  ];
  ctx.font = '600 14px Arial, sans-serif';
  let cursor = x;
  columns.forEach((column, index) => {
    const color = index === 4 ? (row.direction === 'income' ? '#059669' : '#dc2626') : '#0f172a';
    ctx.fillStyle = color;
    values[index].forEach((line, lineIndex) => {
      drawAlignedText(ctx, line, cursor + 10, y + 22 + lineIndex * 18, column.width - 20, column.align);
    });
    cursor += column.width;
  });
}

function drawAlignedText(ctx, text, x, y, width, align) {
  const value = String(text ?? '');
  if (align === 'right') {
    ctx.textAlign = 'right';
    ctx.fillText(value, x + width, y);
  } else {
    ctx.textAlign = 'left';
    ctx.fillText(value, x, y);
  }
}

function roundRect(ctx, x, y, width, height, radius) {
  const r = Math.min(radius, width / 2, height / 2);
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + width, y, x + width, y + height, r);
  ctx.arcTo(x + width, y + height, x, y + height, r);
  ctx.arcTo(x, y + height, x, y, r);
  ctx.arcTo(x, y, x + width, y, r);
  ctx.closePath();
}

function analysisExportData(analysis) {
  const groups = [
    ...analysis.expenseGroups.map(group => ({ ...group, label: 'Gasto', grandTotal: analysis.expenseTotal })),
    ...analysis.incomeGroups.map(group => ({ ...group, label: 'Ganho', grandTotal: analysis.incomeTotal })),
  ];
  const summaryRows = [];
  const detailRows = [];
  groups.forEach(group => {
    const entries = group.hasSubcategories
      ? group.subgroups.map(subgroup => ({ subgroup, category: group.category, subcategory: subgroup.category, rows: subgroup.rows, total: subgroup.total }))
      : [{ subgroup: null, category: group.category, subcategory: '-', rows: group.rows, total: group.total }];
    entries.forEach(entry => {
      summaryRows.push({
        type: group.label,
        category: entry.category,
        subcategory: entry.subcategory,
        items: entry.rows.length,
        total: asMoney(entry.total),
        percent: formatPercent(entry.total, group.grandTotal),
      });
      entry.rows.forEach(row => {
        detailRows.push({ ...row, type: group.label, category: entry.subcategory === '-' ? entry.category : `${entry.category} / ${entry.subcategory}` });
      });
    });
  });
  return { groups, summaryRows, detailRows };
}

function wrapCanvasText(value, maxChars) {
  const words = String(value ?? '').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean);
  const lines = [];
  let current = '';
  words.forEach(word => {
    const next = current ? `${current} ${word}` : word;
    if (next.length > maxChars && current) {
      lines.push(current);
      current = word;
    } else {
      current = next;
    }
  });
  if (current) lines.push(current);
  return lines.length ? lines.slice(0, 4) : ['-'];
}

function truncateText(value, maxChars) {
  const text = String(value ?? '').replace(/\s+/g, ' ').trim();
  return text.length > maxChars ? `${text.slice(0, Math.max(0, maxChars - 3))}...` : text;
}

async function copyImageToClipboard(blob) {
  if (!navigator.clipboard || typeof ClipboardItem === 'undefined') {
    throw new Error('Clipboard de imagem indisponivel.');
  }
  await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function buildAnalysisWhatsappTable(analysis) {
  const lines = [];
  const groups = [
    ...analysis.expenseGroups.map(group => ({ ...group, label: 'Gasto', grandTotal: analysis.expenseTotal })),
    ...analysis.incomeGroups.map(group => ({ ...group, label: 'Ganho', grandTotal: analysis.incomeTotal })),
  ];
  lines.push('ANALISE FINANCEIRA');
  lines.push(`Filtros: ${analysisFilterSummary()}`);
  lines.push(`Gastos: ${asMoney(analysis.expenseTotal)} | Ganhos: ${asMoney(analysis.incomeTotal)} | Saldo: ${asMoney(analysis.incomeTotal - analysis.expenseTotal)}`);
  lines.push('');
  lines.push('RESUMO POR CATEGORIA');
  lines.push(tableLine(['Tipo', 'Categoria', 'Subcategoria', 'Itens', 'Total', '%'], [7, 18, 18, 5, 14, 7]));
  lines.push(tableDivider([7, 18, 18, 5, 14, 7]));
  groups.forEach(group => {
    if (group.hasSubcategories) {
      group.subgroups.forEach(subgroup => {
        lines.push(tableLine([
          group.label,
          group.category,
          subgroup.category,
          subgroup.rows.length,
          asMoney(subgroup.total),
          formatPercent(subgroup.total, group.grandTotal),
        ], [7, 18, 18, 5, 14, 7]));
      });
      return;
    }
    lines.push(tableLine([
      group.label,
      group.category,
      '-',
      group.rows.length,
      asMoney(group.total),
      formatPercent(group.total, group.grandTotal),
    ], [7, 18, 18, 5, 14, 7]));
  });
  lines.push('');
  lines.push('LINHAS');
  lines.push(tableLine(['Data', 'Tipo', 'Categoria', 'Descricao', 'Valor'], [10, 7, 24, 34, 14]));
  lines.push(tableDivider([10, 7, 24, 34, 14]));
  groups.forEach(group => {
    const rows = group.hasSubcategories ? group.subgroups.flatMap(subgroup => (
      subgroup.rows.map(row => ({ ...row, exportCategory: `${group.category} / ${subgroup.category}`, exportType: group.label }))
    )) : group.rows.map(row => ({ ...row, exportCategory: group.category, exportType: group.label }));
    rows.forEach(row => {
      lines.push(tableLine([
        formatDate(row.date),
        row.exportType,
        row.exportCategory,
        row.description,
        `${row.direction === 'income' ? '+' : '-'} ${asMoney(row.amount)}`,
      ], [10, 7, 24, 34, 14]));
    });
  });
  return '```' + lines.join('\n') + '```';
}

function analysisFilterSummary() {
  const { dateFrom, dateTo } = selectedPeriod();
  const labels = [
    dateFrom && dateTo ? `${formatDate(dateFrom)} a ${formatDate(dateTo)}` : '',
    selectLabel('analysisSourceFilter'),
    selectLabel('analysisDirectionFilter'),
    selectLabel('analysisCategoryParentFilter'),
    selectLabel('analysisCategoryFilter'),
  ].filter(label => label && !/^Todas /.test(label));
  const min = document.querySelector('#analysisMinAmount')?.value;
  const max = document.querySelector('#analysisMaxAmount')?.value;
  const query = document.querySelector('#analysisSearchInput')?.value?.trim();
  if (min) labels.push(`min ${min}`);
  if (max) labels.push(`max ${max}`);
  if (query) labels.push(`busca "${query}"`);
  return labels.length ? labels.join(' | ') : 'sem filtros adicionais';
}

function selectLabel(id) {
  const select = document.querySelector(`#${id}`);
  if (!select || select.disabled || !select.value) return '';
  return select.selectedOptions?.[0]?.textContent?.trim() || '';
}

function tableLine(values, widths) {
  return values.map((value, index) => fitCell(value, widths[index])).join(' | ');
}

function tableDivider(widths) {
  return widths.map(width => '-'.repeat(width)).join('-+-');
}

function fitCell(value, width) {
  const text = String(value ?? '').replace(/\s+/g, ' ').trim();
  const clean = text.length > width ? text.slice(0, Math.max(0, width - 3)) + '...' : text;
  return clean.padEnd(width, ' ');
}

async function copyTextToClipboard(text) {
  if (navigator.clipboard) {
    await navigator.clipboard.writeText(text);
    return;
  }
  const textarea = selectFallbackText(text);
  document.execCommand('copy');
  textarea.remove();
}

function selectFallbackText(text) {
  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.setAttribute('readonly', 'readonly');
  textarea.style.position = 'fixed';
  textarea.style.inset = '0 auto auto 0';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  return textarea;
}

function flashButtonLabel(button, label) {
  if (!button) return;
  const original = button.dataset.originalLabel || button.textContent;
  button.dataset.originalLabel = original;
  button.textContent = label;
  window.clearTimeout(button._labelTimer);
  button._labelTimer = window.setTimeout(() => {
    button.textContent = original;
  }, 1800);
}

function categoryFilterIds(selectedId) {
  const ids = new Set(selectedId ? [String(selectedId)] : []);
  if (selectedId === '__none__') {
    ids.add('');
    const uncategorized = state.categories.find(category => isUncategorizedCategory(category));
    if (uncategorized) ids.add(String(uncategorized.id));
  }
  let changed = true;
  while (changed) {
    changed = false;
    state.categories.forEach(category => {
      if (category.parent_id && ids.has(String(category.parent_id)) && !ids.has(String(category.id))) {
        ids.add(String(category.id));
        changed = true;
      }
    });
  }
  return ids;
}

function analyticsItems(source) {
  const transactionItems = state.transactions.map(row => {
    const type = normalizedType(row);
    if (type === 'transfer' || normalizedBillStatus(row) === 'ignored') return null;
    return {
      id: row.id,
      sourceType: 'transaction',
      sourceLabel: 'Contas',
      direction: type === 'income' ? 'income' : 'expense',
      amount: Number(row.amount || 0),
      category_id: row.category_id || '',
      category: row.category_name || 'Sem categoria',
      category_leaf_name: row.category_leaf_name || row.category_name || 'Sem categoria',
      category_parent_id: row.category_parent_id || '',
      category_parent_name: row.category_parent_name || '',
      date: row.due_date,
      description: row.description || '(sem descricao)',
      meta: [row.source_sheet || 'Manual', statusLabel(row.status), row.owner || ''].filter(Boolean).join(' · '),
    };
  }).filter(Boolean);

  const bankItems = state.bankTransactions.map(row => ({
    id: row.id,
    sourceType: 'bank_transaction',
    sourceLabel: row.bank_name || 'Extrato',
    direction: row.direction === 'credit' ? 'income' : 'expense',
    amount: Number(row.amount || 0),
    category_id: row.category_id || '',
    category: row.category_name || 'Sem categoria',
    category_leaf_name: row.category_leaf_name || row.category_name || 'Sem categoria',
    category_parent_id: row.category_parent_id || '',
    category_parent_name: row.category_parent_name || '',
    date: row.transaction_date,
    description: row.description || '(sem descricao)',
    meta: [row.movement_type || row.document_number || row.source_file || '', row.matched_transaction_id ? 'Conciliado' : 'Sem conciliacao'].filter(Boolean).join(' · '),
  }));

  if (source === 'transactions') return transactionItems;
  if (source === 'combined') return [...bankItems, ...transactionItems];
  return bankItems;
}

function groupAnalyticsByCategory(items, rowSort = 'date_desc', groupSort = 'value_desc') {
  const grouped = items.reduce((acc, item) => {
    const parent = analyticsParentCategory(item);
    const leaf = analyticsLeafCategory(item, parent);
    acc[parent.key] ||= { ...parent, category: parent.name, direction: item.direction, total: 0, rows: [], subgroups: {}, hasSubcategories: parent.hasSubcategories };
    acc[parent.key].hasSubcategories ||= parent.hasSubcategories;
    acc[parent.key].total += item.amount;
    acc[parent.key].rows.push(item);
    acc[parent.key].subgroups[leaf.key] ||= { ...leaf, category: leaf.name, direction: item.direction, total: 0, rows: [] };
    acc[parent.key].subgroups[leaf.key].total += item.amount;
    acc[parent.key].subgroups[leaf.key].rows.push(item);
    return acc;
  }, {});
  return Object.values(grouped)
    .map(group => ({
      ...group,
      rows: sortAnalyticsRows(group.rows, rowSort),
      subgroups: Object.values(group.subgroups)
        .map(subgroup => ({ ...subgroup, rows: sortAnalyticsRows(subgroup.rows, rowSort) }))
        .sort((a, b) => compareAnalyticsGroups(a, b, groupSort)),
    }))
    .sort((a, b) => compareAnalyticsGroups(a, b, groupSort));
}

function analyticsParentCategory(item) {
  if (item.category_parent_id) {
    return { key: `parent:${item.category_parent_id}`, id: item.category_parent_id, name: item.category_parent_name || item.category || 'Sem categoria', hasSubcategories: true };
  }
  const id = item.category_id || '__none__';
  return { key: `category:${id}`, id, name: item.category || 'Sem categoria', hasSubcategories: id !== '__none__' && categoryHasChildren(id) };
}

function analyticsLeafCategory(item, parent) {
  if (item.category_parent_id) {
    const id = item.category_id || '__none__';
    return { key: `leaf:${id}`, id, name: item.category_leaf_name || item.category || 'Sem subcategoria' };
  }
  if (parent.id && parent.id !== '__none__' && categoryHasChildren(parent.id)) {
    return { key: `direct:${parent.id}`, id: '', name: 'Sem subcategoria' };
  }
  return { key: `direct:${parent.key}`, id: '', name: parent.name };
}

function categoryHasChildren(categoryId) {
  return state.categories.some(category => Number(category.parent_id) === Number(categoryId));
}

function sortAnalyticsRows(rows, sortMode) {
  const sorted = [...rows];
  const byDate = (a, b) => String(a.date || '').localeCompare(String(b.date || '')) || a.description.localeCompare(b.description);
  const byAmount = (a, b) => a.amount - b.amount || byDate(a, b);
  sorted.sort((a, b) => {
    if (sortMode === 'date_asc') return byDate(a, b);
    if (sortMode === 'value_desc') return -byAmount(a, b);
    if (sortMode === 'value_asc') return byAmount(a, b);
    if (sortMode === 'description_asc') return a.description.localeCompare(b.description) || byDate(a, b);
    return -byDate(a, b);
  });
  return sorted;
}

function compareAnalyticsGroups(a, b, sortMode) {
  if (sortMode === 'value_asc') return a.total - b.total || a.category.localeCompare(b.category);
  if (sortMode === 'name_asc') return a.category.localeCompare(b.category);
  if (sortMode === 'name_desc') return b.category.localeCompare(a.category);
  if (sortMode === 'count_desc') return b.rows.length - a.rows.length || b.total - a.total;
  return b.total - a.total || a.category.localeCompare(b.category);
}

function renderCategoryPivot(targetId, groups, total, direction) {
  const target = document.querySelector(`#${targetId}`);
  if (!target) return;
  const label = direction === 'income' ? 'ganhos' : 'gastos';
  target.innerHTML = groups.length ? groups.map((group, index) => {
    const pct = formatPercent(group.total, total);
    return `
      <details class="pivot-group ${direction}" ${index < 4 ? 'open' : ''}>
        <summary>
          <span>
            <strong>${escapeHtml(group.category)}</strong>
            <small>${countLabel(group.rows.length, 'item', 'itens')} · ${pct} dos ${label}</small>
          </span>
          <span class="amount ${direction === 'income' ? 'positive' : 'negative'}">${asMoney(group.total)}</span>
        </summary>
        <div class="pivot-bar"><span style="width:${Math.min(100, total ? (group.total / total) * 100 : 0)}%"></span></div>
        ${renderPivotGroupBody(group, direction, index)}
      </details>
    `;
  }).join('') : `<p class="muted">Nenhuma categoria de ${label} para os filtros atuais.</p>`;

  target.querySelectorAll('[data-pivot-source]').forEach(button => {
    button.addEventListener('click', () => openPivotItem(button.dataset.pivotSource, Number(button.dataset.pivotId)));
  });
  target.querySelectorAll('[data-pivot-subtoggle]').forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      const group = button.closest('.pivot-group');
      if (!group) return;
      group.querySelectorAll('.pivot-subgroup').forEach(subgroup => {
        subgroup.open = button.dataset.pivotSubtoggle === 'open';
      });
    });
  });
}

function renderPivotGroupBody(group, direction, groupIndex) {
  if (!group.hasSubcategories) {
    return `<div class="pivot-rows">${renderPivotRows(group.rows, direction)}</div>`;
  }
  return `
    <div class="pivot-subactions">
      <span>${countLabel(group.subgroups.length, 'subcategoria', 'subcategorias')}</span>
      <button type="button" class="small-btn" data-pivot-subtoggle="open">Abrir subcategorias</button>
      <button type="button" class="small-btn" data-pivot-subtoggle="close">Fechar subcategorias</button>
    </div>
    <div class="pivot-subgroups">
      ${group.subgroups.map((subgroup, index) => renderPivotSubgroup(subgroup, group.total, direction, groupIndex < 4 && index < 4)).join('')}
    </div>
  `;
}

function renderPivotSubgroup(subgroup, parentTotal, direction, isOpen) {
  const pct = formatPercent(subgroup.total, parentTotal);
  return `
    <details class="pivot-subgroup ${direction}" ${isOpen ? 'open' : ''}>
      <summary>
        <span>
          <strong>${escapeHtml(subgroup.category)}</strong>
          <small>${countLabel(subgroup.rows.length, 'item', 'itens')} · ${pct} dentro da categoria</small>
        </span>
        <span class="amount ${direction === 'income' ? 'positive' : 'negative'}">${asMoney(subgroup.total)}</span>
      </summary>
      <div class="pivot-subbar"><span style="width:${Math.min(100, parentTotal ? (subgroup.total / parentTotal) * 100 : 0)}%"></span></div>
      <div class="pivot-rows">${renderPivotRows(subgroup.rows, direction)}</div>
    </details>
  `;
}

function renderPivotRows(rows, direction) {
  return rows.map(row => `
    <button class="pivot-row" data-pivot-source="${row.sourceType}" data-pivot-id="${row.id}">
      <span>${formatDate(row.date)}</span>
      <strong>${escapeHtml(row.description)}</strong>
      <small>${escapeHtml(row.sourceLabel)}${row.meta ? ' · ' + escapeHtml(row.meta) : ''}</small>
      <em class="${direction === 'income' ? 'positive' : 'negative'}">${direction === 'income' ? '+' : '-'} ${asMoney(row.amount)}</em>
    </button>
  `).join('');
}

function setPivotOpenState(scope, isOpen) {
  const selector = scope === 'all' ? '.pivot-group' : `.pivot-group.${scope}`;
  document.querySelectorAll(selector).forEach(group => {
    group.open = isOpen;
    group.querySelectorAll('.pivot-subgroup').forEach(subgroup => {
      subgroup.open = isOpen;
    });
  });
}

function openPivotItem(sourceType, id) {
  if (sourceType === 'bank_transaction') {
    navigateToSection('movements');
    highlightSharedElement(`[data-bank-transaction-id="${id}"]`, 'Nao encontrei esta linha na tela atual de extratos.');
    return;
  }
  const row = state.transactions.find(item => Number(item.id) === id);
  navigateToSection(row && normalizedType(row) === 'income' ? 'transactions' : 'bills');
  highlightSharedElement(`[data-transaction-id="${id}"]`, 'Nao encontrei este lancamento na tela atual.');
}

function formatPercent(value, total) {
  if (!total) return '0%';
  return `${((Number(value || 0) / Number(total || 1)) * 100).toFixed(1).replace('.', ',')}%`;
}

function countLabel(count, singular, plural) {
  return `${count} ${Number(count) === 1 ? singular : plural}`;
}

function renderWorkflowStrip() {
  const sheetRows = state.transactions.filter(row => row.source_sheet && row.source_sheet !== 'manual').length;
  const bankRows = state.bankTransactions.length;
  const unmatched = state.bankTransactions.filter(row => !row.matched_transaction_id).length;
  setText('sheetFlowCount', `${sheetRows} lancamentos`);
  setText('bankFlowCount', `${bankRows} movimentacoes`);
  setText('matchFlowCount', `${unmatched} sem match`);
}

function renderUpcoming() {
  const target = document.querySelector('#upcomingList');
  if (!target) return;
  const rows = state.overview?.upcoming || [];
  target.innerHTML = rows.length ? rows.map(row => `
    <div class="list-row">
      <div><strong>${escapeHtml(row.description)}</strong><small>${formatDate(row.due_date)} · ${escapeHtml(row.category_name || 'Sem categoria')}</small></div>
      <span class="amount">${asMoney(row.amount)}</span>
    </div>
  `).join('') : '<p class="muted">Nada vencendo nos proximos dias.</p>';
}

function renderCharts() {
  if (!window.Chart || !state.overview) return;
  const monthly = state.overview.monthly || [];
  const categories = state.overview.byCategory || [];
  drawChart('monthlyChart', 'bar', {
    labels: monthly.map(item => item.month),
    datasets: [
      { label: 'Despesas', data: monthly.map(item => item.expenses), backgroundColor: '#dc2626' },
      { label: 'Pendentes', data: monthly.map(item => item.pending), backgroundColor: '#d97706' },
      { label: 'Receitas', data: monthly.map(item => item.income), backgroundColor: '#059669' },
    ],
  });
  drawChart('categoryChart', 'doughnut', {
    labels: categories.map(item => item.name),
    datasets: [{ data: categories.map(item => item.total), backgroundColor: categories.map(item => item.color || '#64748b') }],
  });
}

function drawChart(id, type, data) {
  const canvas = document.querySelector(`#${id}`);
  if (!canvas) return;
  state.charts[id]?.destroy();
  state.charts[id] = new Chart(canvas, {
    type,
    data,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { labels: { boxWidth: 12, usePointStyle: true } } },
      scales: type === 'bar' ? { y: { ticks: { callback: value => asMoney(value) } } } : undefined,
    },
  });
}

function renderTransactions() {
  const body = document.querySelector('#transactionsBody');
  if (!body) return;
  const rows = filteredTransactions();
  body.innerHTML = rows.length ? rows.map(row => `
    <tr data-transaction-id="${row.id}">
      <td>${formatDate(row.due_date)}</td>
      <td><strong>${escapeHtml(row.description)}</strong><br><small>${row.payment_code ? escapeHtml(firstWords(row.payment_code, 6)) : 'Sem codigo de pagamento'} ${row.owner ? '· ' + escapeHtml(row.owner) : ''}</small></td>
      <td>${originBadge(row)}<br><small>${row.reference_month ? escapeHtml(row.reference_month) : formatDate(row.due_date)}</small></td>
      <td>${inlineCategorySelect(row, 'transaction')}</td>
      <td><span class="status ${row.status}">${statusLabel(row.status)}</span></td>
      <td class="amount">${asMoney(row.amount)}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" title="Marcar pago/pendente" data-toggle="${row.id}" data-status="${row.status === 'paid' ? 'pending' : 'paid'}">✓</button>
          <button class="icon-btn" title="Compartilhar link" data-share-type="transaction" data-share-id="${row.id}">↗</button>
          <button class="icon-btn" title="Editar" data-edit="${row.id}">✎</button>
          <button class="icon-btn" title="Excluir" data-delete="${row.id}">×</button>
        </div>
      </td>
    </tr>
  `).join('') : '<tr><td colspan="7" class="empty-cell">Nenhum lancamento encontrado para os filtros atuais.</td></tr>';

  body.querySelectorAll('[data-toggle]').forEach(button => {
    button.addEventListener('click', async () => {
      await api('toggle_paid', { method: 'POST', body: { id: button.dataset.toggle, status: button.dataset.status } });
      await loadTransactions();
    });
  });
  body.querySelectorAll('[data-delete]').forEach(button => {
    button.addEventListener('click', async () => {
      if (!confirm('Excluir este lancamento?')) return;
      await api('delete_transaction', { method: 'POST', body: { id: button.dataset.delete } });
      await loadTransactions();
    });
  });
  body.querySelectorAll('[data-edit]').forEach(button => {
    button.addEventListener('click', () => editTransaction(Number(button.dataset.edit)));
  });
  bindInlineCategoryControls(body);
  bindShareButtons(body);
}

function renderStaticLists() {
  renderCategories();
  renderBudgets();
  renderGoals();
  renderAccounts();
  renderRecurring();
  renderBankAccountSelect();
  renderBankFilter();
}

function filteredTransactions() {
  const q = norm(document.querySelector('#searchInput')?.value || '');
  const status = document.querySelector('#statusFilter')?.value || '';
  const type = document.querySelector('#typeFilter')?.value || '';
  return state.transactions.filter(row => {
    if (status && row.status !== status) return false;
    if (type && row.type !== type) return false;
    if (q) {
      const haystack = norm([row.description, row.payment_code, row.source_sheet, row.owner, row.category_name].join(' '));
      if (!haystack.includes(q)) return false;
    }
    return true;
  });
}

function filteredBills() {
  const query = norm(document.querySelector('#billsSearchInput')?.value || '');
  const status = document.querySelector('#billsStatusFilter')?.value || '';
  const category = categoryFilterValue('bills');
  const owner = document.querySelector('#billsOwnerFilter')?.value || '';
  const categoryIds = categoryFilterIds(category);
  return state.transactions.filter(row => {
    if (normalizedType(row) === 'income' || normalizedBillStatus(row) === 'ignored') return false;
    const rowStatus = normalizedBillStatus(row) === 'pending' && isPastDate(row.due_date) ? 'late' : normalizedBillStatus(row);
    if (status && rowStatus !== status) return false;
    if (category && !categoryIds.has(String(row.category_id || ''))) return false;
    if (owner && clean(row.owner) !== owner) return false;
    if (query && !norm([row.description, row.owner, row.source_sheet, row.category_name].join(' ')).includes(query)) return false;
    return true;
  });
}

function renderBills() {
  const billRows = filteredBills();
  const paid = billRows.filter(row => normalizedBillStatus(row) === 'paid');
  const pending = billRows.filter(row => normalizedBillStatus(row) !== 'paid');
  const late = pending.filter(row => normalizedBillStatus(row) === 'late' || isPastDate(row.due_date));
  setText('billsMonthTotal', asMoney(sumAmounts(paid) + sumAmounts(pending)));
  setText('billsPaidTotal', asMoney(sumAmounts(paid)));
  setText('billsPendingTotal', asMoney(sumAmounts(pending)));
  setText('billsCount', String(paid.length + pending.length));
  setText('billsLateCount', String(late.length));
  setText('pendingBillsCount', `${pending.length} contas`);
  setText('paidBillsCount', `${paid.length} contas`);
  renderBillList('pendingBillsList', pending, 'pending');
  renderBillList('paidBillsList', paid, 'paid');
}

function renderBillList(targetId, rows, mode) {
  const target = document.querySelector(`#${targetId}`);
  if (!target) return;
  const sorted = [...rows].sort((a, b) => String(a.due_date || '').localeCompare(String(b.due_date || '')));
  target.innerHTML = sorted.length ? sorted.map(row => `
    <article class="bill-card ${mode}" data-transaction-id="${row.id}">
      <div>
        <strong>${escapeHtml(row.description)}</strong>
        <small>${formatDate(row.due_date)}</small>
        <div class="bill-category">${inlineCategorySelect(row, 'transaction')}</div>
        <small>${originBadge(row)} ${row.payment_code ? '· ' + escapeHtml(compactPaymentCode(row.payment_code)) : ''}</small>
      </div>
      <div class="bill-card-side">
        <span class="amount">${asMoney(row.amount)}</span>
        <button class="small-btn" data-toggle="${row.id}" data-status="${normalizedBillStatus(row) === 'paid' ? 'pending' : 'paid'}">${normalizedBillStatus(row) === 'paid' ? 'Reabrir' : 'Marcar pago'}</button>
        <div class="row-actions">
          <button class="icon-btn" title="Compartilhar link" data-share-type="transaction" data-share-id="${row.id}">↗</button>
          <button class="icon-btn" title="Editar conta" data-bill-edit="${row.id}">✎</button>
          <button class="icon-btn" title="Excluir conta" data-bill-delete="${row.id}">×</button>
        </div>
      </div>
    </article>
  `).join('') : `<p class="muted">Nenhuma conta ${mode === 'paid' ? 'paga' : 'pendente'} neste mes.</p>`;
  target.querySelectorAll('[data-toggle]').forEach(button => {
    button.addEventListener('click', async () => {
      await api('toggle_paid', { method: 'POST', body: { id: button.dataset.toggle, status: button.dataset.status } });
      await loadTransactions();
    });
  });
  target.querySelectorAll('[data-bill-edit]').forEach(button => {
    button.addEventListener('click', () => editTransaction(Number(button.dataset.billEdit)));
  });
  target.querySelectorAll('[data-bill-delete]').forEach(button => {
    button.addEventListener('click', async () => {
      if (!confirm('Excluir esta conta do mes?')) return;
      await api('delete_transaction', { method: 'POST', body: { id: button.dataset.billDelete } });
      await reloadAllData();
    });
  });
  bindInlineCategoryControls(target);
  bindShareButtons(target);
}

function renderMovementSearchSuggestions() {
  const target = document.querySelector('#movementSearchOptions');
  const search = document.querySelector('#movementSearchInput');
  if (!target || !search) return;
  const query = norm(search.value || '');
  if (!query || document.activeElement !== search) {
    target.hidden = true;
    return;
  }
  const values = state.movementSearchSuggestions
    .filter(value => norm(value).includes(query))
    .sort((a, b) => a.localeCompare(b, 'pt-BR'))
    .slice(0, 12);
  target.innerHTML = values.map(value => `<button type="button" role="option" data-movement-suggestion="${escapeHtml(value)}">${escapeHtml(value)}</button>`).join('');
  target.hidden = values.length === 0;
  target.querySelectorAll('[data-movement-suggestion]').forEach(button => {
    button.addEventListener('mousedown', event => event.preventDefault());
    button.addEventListener('click', async () => {
      search.value = button.dataset.movementSuggestion || '';
      search.setSelectionRange?.(search.value.length, search.value.length);
      hideMovementSearchSuggestions();
      await loadBankTransactions();
      hideMovementSearchSuggestions();
    });
  });
}

function hideMovementSearchSuggestions() {
  const target = document.querySelector('#movementSearchOptions');
  if (target) target.hidden = true;
}

function normalizedBillStatus(row) {
  const status = norm(row.status || '');
  if (status.includes('pago') || status === 'paid') return 'paid';
  if (status.includes('atras') || status === 'late') return 'late';
  if (status.includes('ignorado') || status.includes('nao pagar') || status === 'ignored') return 'ignored';
  return 'pending';
}

function normalizedType(row) {
  const type = norm(row.type || '');
  if (type.includes('entrada') || type.includes('receita') || type === 'income') return 'income';
  if (type.includes('transfer') || type === 'transfer') return 'transfer';
  return 'expense';
}

function compactPaymentCode(value) {
  const text = clean(value);
  if (!text || text === '-----------') return '';
  if (text.length <= 34) return text;
  return `${text.slice(0, 18)}...${text.slice(-10)}`;
}

function renderBanking() {
  renderBankSync();
  renderBankingSummary();
  renderBankFilter();
  renderBankTransactions();
}

function renderBankSync() {
  const sync = state.bankSync || {};
  const stateElement = document.querySelector('#bankSyncState');
  const description = document.querySelector('#bankSyncDescription');
  const accounts = document.querySelector('#bankSyncAccounts');
  const button = document.querySelector('#syncBanksNow');
  if (!stateElement || !description || !accounts || !button) return;

  stateElement.className = 'sync-state';
  if (sync.ready && sync.lastRun?.status === 'failed') {
    stateElement.textContent = 'Precisa de atencao';
    stateElement.classList.add('is-error');
    description.textContent = sync.lastRun.message || 'A ultima sincronizacao falhou.';
  } else if (sync.ready) {
    stateElement.textContent = 'Ativo';
    stateElement.classList.add('is-ready');
    const lastRun = sync.lastRun;
    description.textContent = lastRun
      ? `Ultima tentativa: ${formatDateTime(lastRun.finished_at || lastRun.started_at)}. Atualizacao da origem: diaria.`
      : 'Configuracao pronta. A primeira sincronizacao ainda nao foi executada.';
  } else if (sync.configured) {
    stateElement.textContent = 'Desligado';
    stateElement.classList.add('is-paused');
    description.textContent = 'As credenciais estao salvas, mas a integracao esta desligada no servidor.';
  } else {
    stateElement.textContent = 'Aguardando configuracao';
    stateElement.classList.add('is-paused');
    description.textContent = 'Conecte os bancos no Meu Pluggy e adicione as tres credenciais no servidor.';
  }

  button.disabled = !sync.ready;
  const rows = Array.isArray(sync.accounts) ? sync.accounts : [];
  accounts.innerHTML = rows.length ? rows.map(account => `
    <div class="bank-sync-account">
      <span><strong>${escapeHtml(account.bank_name)}</strong><small>${escapeHtml(account.account_label || 'Conta bancaria')}</small></span>
      <span><strong>${account.last_balance === null ? 'Saldo indisponivel' : asMoney(account.last_balance)}</strong><small>${account.last_synced_at ? formatDateTime(account.last_synced_at) : 'Ainda nao sincronizada'}</small></span>
    </div>
  `).join('') : '<small>Nenhuma conta sincronizada ainda.</small>';
}

function renderBankingSummary() {
  const banks = state.bankOverview?.byBank || [];
  const balances = state.bankOverview?.latestBalances || [];
  const credits = banks.reduce((sum, item) => sum + Number(item.credits || 0), 0);
  const debits = banks.reduce((sum, item) => sum + Number(item.debits || 0), 0);
  const matched = state.bankTransactions.filter(item => item.matched_transaction_id).length;
  const latestBalance = balances.reduce((sum, item) => sum + Number(item.balance || 0), 0);
  setText('bankCredits', asMoney(credits));
  setText('bankDebits', asMoney(debits));
  setText('bankMatched', String(matched));
  setText('bankLatestBalance', asMoney(latestBalance));
  renderWorkflowStrip();
}

function renderBankFilter() {
  const select = document.querySelector('#bankFilter');
  const movementSelect = document.querySelector('#movementBankFilter');
  const banks = [...new Set((state.bankOverview?.byBank || []).map(item => item.bank_name))];
  [select, movementSelect].filter(Boolean).forEach(item => {
    const current = item.value;
    item.innerHTML = '<option value="">Todos bancos</option>' + banks.map(bank => `<option value="${escapeHtml(bank)}">${escapeHtml(bank)}</option>`).join('');
    item.value = banks.includes(current) ? current : '';
  });
}

function renderBankAccountSelect() {
  const select = document.querySelector('#bankPreviewAccount');
  if (!select) return;
  select.innerHTML = '<option value="">Criar/usar conta do banco automaticamente</option>' + state.accounts.map(account => (
    `<option value="${account.id}">${escapeHtml(account.name)}</option>`
  )).join('');
}

function renderBankTransactions() {
  const body = document.querySelector('#bankTransactionsBody');
  if (!body) return;
  body.innerHTML = state.bankTransactions.length ? state.bankTransactions.map(row => `
    <tr data-bank-transaction-id="${row.id}">
      <td><span class="bank-pill">${escapeHtml(row.bank_name)}</span></td>
      <td>${formatDate(row.transaction_date)}</td>
      <td>
        <div class="table-title-row">
          <strong>${escapeHtml(row.description)}</strong>
          <button class="link-btn" title="Compartilhar link" data-share-type="bank_transaction" data-share-id="${row.id}">Link</button>
        </div>
        <small>${escapeHtml(row.movement_type || row.source_file || '')}</small>
      </td>
      <td>${inlineCategorySelect(row, 'bank_transaction')}</td>
      <td>${row.matched_transaction_id ? `<span class="status paid">Conciliado</span><br><small>${escapeHtml(row.matched_description || '')}</small>` : '<span class="status pending">Sem match</span>'}</td>
      <td class="amount ${row.direction === 'credit' ? 'positive' : 'negative'}">${row.direction === 'credit' ? '+' : '-'} ${asMoney(row.amount)}</td>
    </tr>
  `).join('') : '<tr><td colspan="6" class="empty-cell">Nenhuma movimentacao bancaria encontrada para os filtros atuais.</td></tr>';
  bindInlineCategoryControls(body);
  bindShareButtons(body);
}

function renderMovements() {
  const rows = state.bankTransactions;
  const credits = rows.filter(row => row.direction === 'credit');
  const debits = rows.filter(row => row.direction === 'debit');
  const matched = rows.filter(row => row.matched_transaction_id);
  setText('movementCredits', asMoney(sumAmounts(credits)));
  setText('movementDebits', asMoney(sumAmounts(debits)));
  setText('movementNet', asMoney(sumAmounts(credits) - sumAmounts(debits)));
  setText('movementMatched', String(matched.length));
  renderMovementCategorySummary(rows);
  const grouped = state.movementView === 'grouped';
  document.querySelectorAll('[data-movement-view]').forEach(button => {
    button.classList.toggle('is-active', button.dataset.movementView === state.movementView);
  });
  const groupedView = document.querySelector('#similarTransactionsView');
  const listView = document.querySelector('#movementListView');
  if (groupedView) groupedView.hidden = !grouped;
  if (listView) listView.hidden = grouped;
  setText('movementViewTitle', grouped ? 'Transacoes semelhantes' : 'Transacoes');
  if (grouped) {
    const groups = renderSimilarTransactionGroups(rows);
    setText('movementRowsCount', `${rows.length} transacoes · ${groups.length} grupos`);
  } else {
    setText('movementRowsCount', `${rows.length} linhas`);
    renderCategorizedBankTable(rows);
  }
}

function chooseCategoryScope(label) {
  const dialog = document.querySelector('#categoryScopeModal');
  if (!dialog) return Promise.resolve('single');
  setText('categoryScopeMessage', `Aplicar "${label}" somente nesta movimentacao ou tambem nas ocorrencias parecidas?`);
  dialog.showModal();
  return new Promise(resolve => {
    const finish = scope => {
      dialog.onclick = null;
      dialog.oncancel = null;
      dialog.close();
      resolve(scope);
    };
    dialog.onclick = event => {
      const button = event.target.closest('[data-category-scope]');
      if (button) finish(button.dataset.categoryScope);
    };
    dialog.oncancel = event => {
      event.preventDefault();
      finish('cancel');
    };
  });
}

function renderSimilarTransactionGroups(rows) {
  const target = document.querySelector('#similarTransactionsView');
  if (!target) return [];
  const groups = groupSimilarTransactions(rows);
  state.similarTransactionGroups = groups;
  target.innerHTML = groups.length ? groups.map((group, index) => {
    const directionClass = group.direction === 'credit' ? 'positive' : 'negative';
    const sign = group.direction === 'credit' ? '+' : '-';
    const dateLabel = group.dateFrom === group.dateTo
      ? formatDate(group.dateFrom)
      : `${formatDate(group.dateFrom)} a ${formatDate(group.dateTo)}`;
    return `
      <details class="similar-group">
        <summary>
          <div class="similar-group-main">
            <strong>${escapeHtml(group.label)}</strong>
            <small>${group.rows.length} transacoes · ${escapeHtml(dateLabel)} · ${escapeHtml(group.bank)}</small>
          </div>
          <span class="amount ${directionClass}">${sign} ${asMoney(group.total)}</span>
          <div class="similar-group-controls">
            <label class="similar-category-control">
              <span>Categoria do grupo</span>
              ${similarGroupCategorySelect(group, index)}
            </label>
            ${group.direction === 'debit' ? `<button type="button" class="small-btn" data-similar-recurring="${index}">${similarGroupHasRecurringRule(group) ? 'Vincular a outra' : 'Tornar conta fixa'}</button>` : ''}
          </div>
        </summary>
        <div class="similar-group-rows">
          ${group.rows.map(row => `
            <div class="similar-transaction-row" data-bank-transaction-id="${row.id}">
              <span>${formatDate(row.transaction_date)}</span>
              <div><strong>${escapeHtml(row.description)}</strong><small>${escapeHtml(row.category_name || 'Sem categoria')}</small></div>
              <span class="amount ${directionClass}">${sign} ${asMoney(row.amount)}</span>
            </div>
          `).join('')}
        </div>
      </details>`;
  }).join('') : '<p class="muted">Nenhuma transacao para os filtros atuais.</p>';
  target.querySelectorAll('[data-similar-category]').forEach(select => {
    select.addEventListener('click', event => event.stopPropagation());
    select.addEventListener('change', () => updateSimilarGroupCategory(select));
  });
  target.querySelectorAll('.similar-group-controls').forEach(control => {
    control.addEventListener('click', event => event.stopPropagation());
  });
  target.querySelectorAll('[data-similar-recurring]').forEach(button => {
    const group = groups[Number(button.dataset.similarRecurring)];
    button.addEventListener('click', () => prepareRecurringFromGroup(group));
  });
  return groups;
}

function similarGroupHasRecurringRule(group) {
  if (!group) return false;
  const key = transactionSimilarityKey(group.label);
  return state.recurringMatchers.some(matcher => matcher.match_key === key);
}

function prepareRecurringFromGroup(group) {
  if (!group?.rows?.length) return;
  const latest = [...group.rows].sort((a, b) => String(b.transaction_date).localeCompare(String(a.transaction_date)))[0];
  prepareRecurringForm();
  const form = document.querySelector('#recurringForm');
  if (!form) return;
  form.elements.description.value = group.label || latest.description || '';
  form.elements.amount.value = Number(latest.amount || 0).toFixed(2).replace('.', ',');
  form.elements.category_id.value = group.categoryIds.size === 1 ? ([...group.categoryIds][0] || '') : '';
  form.elements.frequency.value = 'monthly';
  form.elements.next_due_date.value = nextMonthlyDate(latest.transaction_date);
  form.elements.is_active.checked = true;
  form.elements.source_bank_transaction_id.value = latest.id;
  const targetField = document.querySelector('#recurringTargetField');
  form.elements.target_recurring_id.innerHTML = '<option value="">Criar nova conta fixa</option>' + state.recurring
    .filter(rule => Number(rule.is_active) === 1)
    .map(rule => `<option value="${rule.id}">${escapeHtml(rule.description)}</option>`).join('');
  targetField.hidden = false;
  setText('recurringFormTitle', 'Confirmar conta fixa');
  document.querySelector('#recurringModal')?.showModal();
}

function nextMonthlyDate(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
  if (!match) return '';
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const lastDay = new Date(year, month + 1, 0).getDate();
  return inputDate(new Date(year, month, Math.min(day, lastDay)));
}

function groupSimilarTransactions(rows) {
  const grouped = rows.reduce((acc, row) => {
    const similarity = transactionSimilarityKey(row.description) || `transacao ${row.id}`;
    const key = `${row.bank_account_id || row.bank_name || 'bank'}|${row.direction}|${similarity}`;
    acc[key] ||= {
      key,
      direction: row.direction,
      label: row.description || '(sem descricao)',
      bank: row.bank_name || 'Banco',
      total: 0,
      rows: [],
      categoryIds: new Set(),
      dateFrom: row.transaction_date,
      dateTo: row.transaction_date,
    };
    const group = acc[key];
    group.total += Number(row.amount || 0);
    group.rows.push(row);
    group.categoryIds.add(String(row.category_id || ''));
    if (row.transaction_date < group.dateFrom) group.dateFrom = row.transaction_date;
    if (row.transaction_date > group.dateTo) group.dateTo = row.transaction_date;
    return acc;
  }, {});
  return Object.values(grouped).sort((a, b) => {
    const aUncategorized = a.categoryIds.has('') ? 1 : 0;
    const bUncategorized = b.categoryIds.has('') ? 1 : 0;
    return bUncategorized - aUncategorized || b.rows.length - a.rows.length || b.total - a.total;
  });
}

function transactionSimilarityKey(description) {
  return norm(description)
    .replace(/\b\d{1,2}[\s/-]\d{1,2}(?:[\s/-]\d{2,4})?\b/g, ' ')
    .replace(/\b\d{4,}\b/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function similarGroupCategorySelect(group, index) {
  const categoryIds = [...group.categoryIds];
  const selected = categoryIds.length === 1 ? categoryIds[0] : '__mixed__';
  const mixed = selected === '__mixed__' ? '<option value="__mixed__" selected disabled>Categorias diferentes</option>' : '';
  const uncategorized = `<option value="" ${selected === '' ? 'selected' : ''}>Sem categoria</option>`;
  const options = state.categories
    .filter(category => !isUncategorizedCategory(category))
    .map(category => `<option value="${category.id}" ${selected === String(category.id) ? 'selected' : ''}>${escapeHtml(categoryOptionLabel(category))}</option>`)
    .join('');
  return `<select data-similar-category data-similar-group-index="${index}">${mixed}${uncategorized}${options}</select>`;
}

async function updateSimilarGroupCategory(select) {
  const group = state.similarTransactionGroups[Number(select.dataset.similarGroupIndex)];
  if (!group || select.value === '__mixed__') return;
  const category = state.categories.find(item => String(item.id) === String(select.value));
  const label = category ? categoryOptionLabel(category) : 'Sem categoria';
  if (!confirm(`Aplicar "${label}" em todas as ocorrencias semelhantes desta conta, inclusive fora do periodo selecionado?`)) {
    renderMovements();
    return;
  }
  select.disabled = true;
  try {
    await api('update_bank_transaction_category', {
      method: 'POST',
      body: { id: Number(group.rows[0].id), category_id: select.value, apply_similar: 1 },
    });
    await loadBankTransactions();
  } catch (error) {
    alert(error.message || 'Nao foi possivel categorizar o grupo.');
    await loadBankTransactions();
  }
}

function renderMovementCategorySummary(rows) {
  const target = document.querySelector('#movementCategorySummary');
  if (!target) return;
  const grouped = rows.reduce((acc, row) => {
    const key = row.category_name || 'A categorizar';
    acc[key] ||= { count: 0, credits: 0, debits: 0 };
    acc[key].count += 1;
    if (row.direction === 'credit') acc[key].credits += Number(row.amount || 0);
    else acc[key].debits += Number(row.amount || 0);
    return acc;
  }, {});
  const entries = Object.entries(grouped).sort((a, b) => (b[1].credits + b[1].debits) - (a[1].credits + a[1].debits));
  target.innerHTML = entries.length ? entries.map(([category, item]) => `
    <div class="source-row">
      <div><strong>${escapeHtml(category)}</strong><small>${item.count} transacoes · entradas ${asMoney(item.credits)}</small></div>
      <span class="amount negative">${asMoney(item.debits)}</span>
    </div>
  `).join('') : '<p class="muted">Nenhuma transacao para os filtros atuais.</p>';
}

function renderCategorizedBankTable(rows) {
  const body = document.querySelector('#categorizedBankBody');
  if (!body) return;
  body.innerHTML = rows.length ? rows.map(row => `
    <tr data-bank-transaction-id="${row.id}">
      <td>${formatDate(row.transaction_date)}</td>
      <td><span class="bank-pill">${escapeHtml(row.bank_name)}</span></td>
      <td>
        <div class="table-title-row">
          <strong>${escapeHtml(row.description)}</strong>
          <button class="link-btn" title="Compartilhar link" data-share-type="bank_transaction" data-share-id="${row.id}">Link</button>
        </div>
        <small>${escapeHtml(row.movement_type || row.document_number || row.source_file || '')}</small>
      </td>
      <td>${inlineCategorySelect(row, 'bank_transaction')}</td>
      <td>${row.matched_transaction_id ? `<span class="status paid">Conciliada</span><br><small>${escapeHtml(row.matched_description || '')}</small>` : '<span class="status pending">Sem conciliacao</span>'}</td>
      <td class="amount ${row.direction === 'credit' ? 'positive' : 'negative'}">${row.direction === 'credit' ? '+' : '-'} ${asMoney(row.amount)}</td>
    </tr>
  `).join('') : '<tr><td colspan="6" class="empty-cell">Nenhuma transacao encontrada para os filtros atuais.</td></tr>';
  bindInlineCategoryControls(body);
  bindShareButtons(body);
}

function renderReconciliation() {
  renderWorkflowStrip();
  const sheetRows = state.transactions.filter(row => row.source_sheet && row.source_sheet !== 'manual');
  const paidRows = sheetRows.filter(row => row.status === 'paid');
  const pendingRows = sheetRows.filter(row => row.status === 'pending' || row.status === 'late');
  const unmatchedBank = state.bankTransactions.filter(row => !row.matched_transaction_id);
  const matchedBank = state.bankTransactions.filter(row => row.matched_transaction_id);

  setText('reconSheetRows', String(sheetRows.length));
  setText('reconPaidRows', String(paidRows.length));
  setText('reconPendingRows', String(pendingRows.length));
  setText('reconUnmatchedRows', String(unmatchedBank.length));

  renderSourceBreakdown(sheetRows);
  renderReviewQueue({ sheetRows, pendingRows, unmatchedBank, matchedBank });
  renderUnmatchedBankList(unmatchedBank);
  renderReconciliationGoals();
}

function renderReconciliationGoals() {
  const target = document.querySelector('#reconGoalsList');
  if (!target) return;
  const rows = state.goals.map(goal => {
    const pct = Math.min(100, Math.round((Number(goal.current_amount) / Math.max(1, Number(goal.target_amount))) * 100));
    return { goal, pct };
  });
  target.innerHTML = rows.length
    ? rows.slice(0, 4).map(({ goal, pct }) => goalSummaryRow(goal, pct)).join('')
    : '<p class="muted">Nenhuma meta cadastrada. Depois de conferir os dados, crie uma para acompanhar um objetivo.</p>';
}

function renderSourceBreakdown(rows) {
  const target = document.querySelector('#sourceBreakdown');
  if (!target) return;
  const grouped = rows.reduce((acc, row) => {
    const key = row.source_sheet || 'Manual';
    acc[key] ||= { count: 0, total: 0, paid: 0, pending: 0 };
    acc[key].count += 1;
    acc[key].total += Number(row.amount || 0);
    if (row.status === 'paid') acc[key].paid += 1;
    if (row.status === 'pending' || row.status === 'late') acc[key].pending += 1;
    return acc;
  }, {});
  const entries = Object.entries(grouped).sort((a, b) => b[1].total - a[1].total);
  target.innerHTML = entries.length ? entries.map(([source, item]) => `
    <div class="source-row">
      <div>
        <strong>${escapeHtml(source)}</strong>
        <small>${item.count} lancamentos · ${item.paid} pagos · ${item.pending} pendentes</small>
      </div>
      <span class="amount">${asMoney(item.total)}</span>
    </div>
  `).join('') : '<p class="muted">Importe a planilha para ver as abas aqui.</p>';
}

function renderReviewQueue({ pendingRows, unmatchedBank, matchedBank }) {
  const target = document.querySelector('#reviewQueue');
  if (!target) return;
  const items = [
    {
      title: 'Contas planejadas ainda pendentes',
      meta: `${pendingRows.length} itens precisam de confirmacao de pagamento`,
      amount: pendingRows.reduce((sum, row) => sum + Number(row.amount || 0), 0),
      action: 'Conferir contas',
      section: 'transactions',
      tone: 'warning',
    },
    {
      title: 'Pagamentos ja reconhecidos',
      meta: `${matchedBank.length} movimentacoes do banco ja foram ligadas a contas`,
      amount: matchedBank.reduce((sum, row) => sum + Number(row.amount || 0), 0),
      action: 'Ver extratos',
      section: 'movements',
      tone: 'success',
    },
    {
      title: 'Movimentos do banco sem correspondencia',
      meta: `${unmatchedBank.length} itens precisam ser entendidos ou categorizados`,
      amount: unmatchedBank.reduce((sum, row) => sum + Number(row.amount || 0), 0),
      action: 'Revisar movimentos',
      section: 'movements',
      tone: 'danger',
    },
  ];
  target.innerHTML = items.map(item => `
    <button class="review-row ${item.tone}" data-nav-target="${item.section}">
      <div><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(item.meta)}</small></div>
      <span>${asMoney(item.amount)}</span>
      <em>${escapeHtml(item.action)}</em>
    </button>
  `).join('');
  target.querySelectorAll('[data-nav-target]').forEach(button => {
    button.addEventListener('click', () => navigateToSection(button.dataset.navTarget));
  });
}

function renderUnmatchedBankList(rows) {
  const target = document.querySelector('#unmatchedBankList');
  if (!target) return;
  target.innerHTML = rows.length ? rows.slice(0, 12).map(row => `
    <div class="bank-match-row" data-bank-transaction-id="${row.id}">
      <span class="bank-pill">${escapeHtml(row.bank_name)}</span>
      <div>
        <strong>${escapeHtml(row.description)}</strong>
        <small>${formatDate(row.transaction_date)} · ${escapeHtml(row.movement_type || row.source_file || '')}</small>
        <div class="bill-category">${inlineCategorySelect(row, 'bank_transaction')}</div>
      </div>
      <div class="bank-match-actions">
        <span class="amount ${row.direction === 'credit' ? 'positive' : 'negative'}">${row.direction === 'credit' ? '+' : '-'} ${asMoney(row.amount)}</span>
        <button class="link-btn" title="Compartilhar link" data-share-type="bank_transaction" data-share-id="${row.id}">Link</button>
      </div>
    </div>
  `).join('') : '<p class="muted">Todas as movimentacoes sincronizadas neste filtro ja foram conciliadas ou ainda nao ha dados bancarios.</p>';
  bindInlineCategoryControls(target);
  bindShareButtons(target);
}

function originBadge(row) {
  const source = row.source_sheet && row.source_sheet !== 'manual' ? row.source_sheet : 'Manual';
  const label = row.source_sheet && row.source_sheet !== 'manual' ? 'Planilha' : 'Manual';
  return `<span class="source-badge">${label}</span> <small>${escapeHtml(source)}</small>`;
}

async function handleBankFiles(event) {
  const files = [...event.target.files];
  if (!files.length) return;
  const groups = [];
  for (const file of files) {
    const result = await parseBankFile(file);
    groups.push(result);
  }
  state.bankPreviewGroups = groups;
  state.bankPreview = groups.flatMap(group => group.rows);
  state.bankPreviewMeta = groups.length === 1 ? groups[0].meta : { bank: 'Multibanco', fileName: `${groups.length} arquivos`, fileHash: groups.map(group => group.meta.fileHash).join('|') };
  renderBankPreview();
  event.target.value = '';
}

async function parseBankFile(file) {
  if (!window.XLSX) {
    throw new Error('Leitor de planilhas ainda carregando. Tente novamente em alguns segundos.');
  }
  const buffer = await file.arrayBuffer();
  const workbook = XLSX.read(buffer, { type: 'array', cellDates: false });
  const sheet = workbook.Sheets[workbook.SheetNames[0]];
  const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: false, defval: '' });
  const text = rows.flat().join(' ').toLowerCase();
  const bank = text.includes('pagseguro') || text.includes('pagbank') ? 'PagBank' : text.includes('santander') ? 'Santander' : guessBankFromFile(file.name);
  const normalized = bank === 'PagBank' ? parsePagBankRows(rows, file.name) : parseSantanderRows(rows, file.name, bank);
  const hash = await fileHash(file);
  return { rows: normalized, meta: { bank, fileName: file.name, fileHash: hash } };
}

function parsePagBankRows(rows, fileName) {
  const headerIndex = rows.findIndex(row => row.some(cell => norm(cell) === 'data') && row.some(cell => norm(cell) === 'tipo') && row.some(cell => norm(cell).includes('descricao')));
  if (headerIndex < 0) throw new Error(`Nao encontrei cabecalho no arquivo ${fileName}.`);
  const header = rows[headerIndex].map(cell => norm(cell));
  return rows.slice(headerIndex + 1).map(row => rowToObject(header, row)).filter(item => item.data).map(item => {
    const movementType = clean(item.tipo);
    const description = clean(item.descricao || item.descrição || movementType);
    if (norm(movementType) === 'saldo do dia') return null;
    const credit = parseMoney(item.entradas);
    const debit = Math.abs(parseMoney(item.saidas));
    const amount = credit > 0 ? credit : debit;
    if (!amount) return null;
    return {
      bank_name: 'PagBank',
      source_file: fileName,
      transaction_date: parseDate(item.data),
      movement_type: movementType,
      description,
      document_number: '',
      direction: credit > 0 ? 'credit' : 'debit',
      amount,
      balance: item.saldo ? parseMoney(item.saldo) : '',
    };
  }).filter(Boolean);
}

function parseSantanderRows(rows, fileName, bank) {
  const headerIndex = rows.findIndex(row => row.some(cell => norm(cell) === 'data') && row.some(cell => norm(cell).includes('descricao')) && row.some(cell => norm(cell).includes('credito')));
  if (headerIndex < 0) throw new Error(`Nao encontrei cabecalho no arquivo ${fileName}.`);
  const header = rows[headerIndex].map(cell => norm(cell));
  return rows.slice(headerIndex + 1).map(row => rowToObject(header, row)).filter(item => item.data).map(item => {
    const description = clean(item.descricao || item.descrição);
    if (!description || norm(description).includes('saldo anterior') || norm(description) === 'total') return null;
    const credit = parseMoney(item['credito r'] || item['credito (r$)'] || item.credito);
    const debit = Math.abs(parseMoney(item['debito r'] || item['debito (r$)'] || item.debito));
    const amount = credit > 0 ? credit : debit;
    if (!amount) return null;
    return {
      bank_name: bank,
      source_file: fileName,
      transaction_date: parseDate(item.data),
      movement_type: firstWords(description, 4),
      description,
      document_number: clean(item.docto || item.documento),
      direction: credit > 0 ? 'credit' : 'debit',
      amount,
      balance: item['saldo r'] || item['saldo (r$)'] || item.saldo ? parseMoney(item['saldo r'] || item['saldo (r$)'] || item.saldo) : '',
    };
  }).filter(Boolean);
}

function rowToObject(header, row) {
  const object = {};
  header.forEach((key, index) => {
    if (key) object[key] = row[index] ?? '';
  });
  return object;
}

function renderBankPreview() {
  const panel = document.querySelector('#bankPreviewPanel');
  const body = document.querySelector('#bankPreviewBody');
  if (!panel || !body) return;
  panel.hidden = state.bankPreview.length === 0;
  setText('bankPreviewSummary', `${state.bankPreview.length} movimentacoes detectadas`);
  body.innerHTML = state.bankPreview.slice(0, 80).map(row => `
    <tr>
      <td><span class="bank-pill">${escapeHtml(row.bank_name)}</span></td>
      <td>${formatDate(row.transaction_date)}</td>
      <td>${escapeHtml(row.description)}</td>
      <td>${escapeHtml(row.movement_type || '')}</td>
      <td><span class="status ${row.direction === 'credit' ? 'paid' : 'pending'}">${row.direction === 'credit' ? 'Entrada' : 'Saida'}</span></td>
      <td class="amount ${row.direction === 'credit' ? 'positive' : 'negative'}">${asMoney(row.amount)}</td>
      <td>${row.balance === '' ? '' : asMoney(row.balance)}</td>
    </tr>
  `).join('');
}

async function saveBankImport() {
  if (!state.bankPreview.length || !state.bankPreviewGroups.length) return;
  const button = document.querySelector('#saveBankImport');
  button.disabled = true;
  button.textContent = 'Salvando...';
  try {
    let imported = 0;
    let matched = 0;
    for (const group of state.bankPreviewGroups) {
      const payload = await api('save_bank_import', {
        method: 'POST',
        body: {
          bank_name: group.meta.bank,
          file_name: group.meta.fileName,
          file_hash: group.meta.fileHash,
          account_id: document.querySelector('#bankPreviewAccount')?.value || '',
          rows: group.rows,
        },
      });
      imported += Number(payload.imported || 0);
      matched += Number(payload.matched || 0);
    }
    alert(`Importacao salva: ${imported} linhas, ${matched} conciliadas. Vou abrir a central de conciliacao para voce revisar o que ficou pendente.`);
    state.bankPreview = [];
    state.bankPreviewMeta = null;
    state.bankPreviewGroups = [];
    renderBankPreview();
    await loadBootstrap();
    await loadTransactions();
    await loadBankTransactions();
    navigateToSection('reconciliation');
  } finally {
    button.disabled = false;
    button.textContent = 'Salvar importacao';
  }
}

async function fileHash(file) {
  const buffer = await file.arrayBuffer();
  const digest = await crypto.subtle.digest('SHA-256', buffer);
  return [...new Uint8Array(digest)].map(byte => byte.toString(16).padStart(2, '0')).join('');
}

function parseMoney(value) {
  if (value === null || value === undefined || value === '') return 0;
  if (typeof value === 'number') return value;
  let text = String(value).replace(/\u00a0/g, ' ').replace(/R\$/gi, '').trim();
  text = text.replace(/[^\d,.\-]/g, '');
  if (!text || text === '-') return 0;
  const hasComma = text.includes(',');
  const hasDot = text.includes('.');
  if (hasComma && hasDot) text = text.replace(/\./g, '').replace(',', '.');
  else if (hasComma) text = text.replace(',', '.');
  else if (hasDot && /^\-?\d{1,3}(\.\d{3})+$/.test(text)) text = text.replace(/\./g, '');
  const parsed = Number(text);
  return Number.isFinite(parsed) ? parsed : 0;
}

function parseDate(value) {
  if (!value) return '';
  if (value instanceof Date) return value.toISOString().slice(0, 10);
  const text = String(value).trim().slice(0, 10);
  const match = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if (match) return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
  if (/^\d{4}-\d{2}-\d{2}/.test(text)) return text.slice(0, 10);
  return '';
}

function norm(value) {
  return clean(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}

function clean(value) {
  return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function firstWords(value, count) {
  return clean(value).split(' ').slice(0, count).join(' ');
}

function guessBankFromFile(fileName) {
  const name = fileName.toLowerCase();
  if (name.includes('santander')) return 'Santander';
  if (name.includes('pag')) return 'PagBank';
  return 'Banco';
}

async function handleSheetWorkbook(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  const status = document.querySelector('#sheetImportStatus');
  status.textContent = 'Lendo arquivo...';
  try {
    const rows = await parseOriginalBudgetWorkbook(file);
    state.sheetImportRows = rows;
    if (!rows.length) throw new Error('Nao encontrei lancamentos validos nas abas.');
    const confirmed = confirm(`Encontrei ${rows.length} lancamentos em ${new Set(rows.map(row => row.source_sheet)).size} abas. Deseja substituir a carga atual da planilha no sistema?`);
    if (!confirmed) {
      status.textContent = `${rows.length} linhas lidas, importacao cancelada.`;
      return;
    }
    status.textContent = 'Salvando no sistema...';
    const payload = await api('save_sheet_import', { method: 'POST', body: { rows } });
    status.textContent = `${payload.imported} lancamentos importados.`;
    await loadBootstrap();
    await loadTransactions();
    await loadBankTransactions();
    navigateToSection('reconciliation');
  } catch (error) {
    status.textContent = error.message;
    alert(error.message);
  } finally {
    event.target.value = '';
  }
}

async function parseOriginalBudgetWorkbook(file) {
  if (!window.XLSX) {
    throw new Error('Leitor de planilhas ainda carregando. Tente novamente em alguns segundos.');
  }
  const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', raw: false, cellDates: false });
  const allRows = [];
  workbook.SheetNames.forEach(sheetName => {
    if (norm(sheetName) === 'resumo') return;
    const sheet = workbook.Sheets[sheetName];
    const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: false, defval: '' });
    const headerIndex = rows.findIndex(row => row.some(cell => norm(cell) === 'valor') && row.some(cell => norm(cell).includes('descricao')) && row.some(cell => norm(cell).includes('pendente')));
    if (headerIndex < 0) return;
    const header = rows[headerIndex].map(cell => norm(cell));
    const referenceMonth = referenceMonthFromSheet(sheetName);
    rows.slice(headerIndex + 1).forEach((row, offset) => {
      const item = rowToObject(header, row);
      const amount = parseMoney(item.valor);
      const description = clean(item.descricao || item.descrição);
      const category = clean(item.categoria);
      const dueDate = parseDate(item.vencimento);
      const paymentCode = clean(item['boleto pix'] || item['boleto / pix'] || item.boleto || item.pix);
      const status = clean(item['pendente pago'] || item['pendente / pago'] || item.pendente);
      const type = clean(item['entrada saida'] || item['entrada/saida'] || item.tipo || 'Saida');
      const owner = clean(item['fran daniel'] || item['fran/daniel'] || item.responsavel);
      const extra = clean(row.slice(6).join(' '));
      if (!description && !paymentCode && !dueDate && !amount && !status) return;
      if (norm(description).includes('total de dividas') || norm(description).includes('ja pago') || norm(description).includes('falta pagar')) return;
      allRows.push({
        amount,
        description,
        category,
        due_date: dueDate,
        payment_code: paymentCode,
        status,
        type,
        owner,
        source_sheet: sheetName,
        reference_month: referenceMonth,
        row_number: headerIndex + offset + 2,
        is_fixed: 0,
        extra,
      });
    });
  });
  return allRows;
}

function referenceMonthFromSheet(sheetName) {
  const normalized = norm(sheetName);
  const months = {
    janeiro: 1,
    fevereiro: 2,
    marco: 3,
    abril: 4,
    maio: 5,
    junho: 6,
    julho: 7,
    agosto: 8,
    setembro: 9,
    outubro: 10,
    novembro: 11,
    dezembro: 12,
  };
  const monthName = Object.keys(months).find(name => normalized.includes(name));
  const month = months[monthName] || 1;
  const explicitYear = normalized.match(/20\d{2}/)?.[0];
  let year = explicitYear ? Number(explicitYear) : 2025;
  if (!explicitYear && ['dezembro'].includes(monthName || '') && normalized === 'dezembro') year = 2024;
  return `${year}-${String(month).padStart(2, '0')}`;
}

function renderCategories() {
  const target = document.querySelector('#categoriesList');
  if (!target) return;
  const parents = state.categories.filter(category => !category.parent_id);
  target.innerHTML = parents.map(parent => {
    const children = state.categories.filter(category => Number(category.parent_id) === Number(parent.id));
    const addChildButton = isUncategorizedCategory(parent) ? '' : `<button class="icon-btn" title="Nova subcategoria" data-category-add-child="${parent.id}">+</button>`;
    return `
      <div class="category-group">
        <div class="category-group-head">
          <div><strong>${escapeHtml(parent.name)}</strong><small>Categoria principal</small></div>
          <div class="category-actions">
            ${addChildButton}
            <button class="icon-btn" title="Editar categoria" data-category-edit="${parent.id}">✎</button>
            <button class="icon-btn" title="Excluir categoria" data-category-delete="${parent.id}">×</button>
          </div>
        </div>
        <div class="category-children">
          ${children.length ? children.map(child => `
            <div class="category-child">
              <span class="category-child-label">${escapeHtml(child.name)}</span>
              <span class="category-actions">
                <button class="icon-btn" title="Editar subcategoria" data-category-edit="${child.id}">✎</button>
                <button class="icon-btn" title="Excluir subcategoria" data-category-delete="${child.id}">×</button>
              </span>
            </div>
          `).join('') : '<span class="category-empty">Sem subcategorias. Use + para criar uma.</span>'}
        </div>
      </div>
    `;
  }).join('');

  target.querySelectorAll('[data-category-edit]').forEach(button => {
    button.addEventListener('click', () => editCategory(Number(button.dataset.categoryEdit)));
  });
  target.querySelectorAll('[data-category-add-child]').forEach(button => {
    button.addEventListener('click', () => {
      prepareCategoryForm(null, Number(button.dataset.categoryAddChild));
      document.querySelector('#categoryModal')?.showModal();
    });
  });
  target.querySelectorAll('[data-category-delete]').forEach(button => {
    button.addEventListener('click', () => deleteCategory(Number(button.dataset.categoryDelete)));
  });
}

function renderBudgets() {
  const target = document.querySelector('#budgetsList');
  if (!target) return;
  target.innerHTML = state.budgets.map(item => `
    <div class="list-row">
      <div><strong>${escapeHtml(item.category_name)}</strong><small>${escapeHtml(item.month)}</small></div>
      <div class="row-actions">
        <span class="amount">${asMoney(item.limit_amount)}</span>
        <button class="icon-btn" title="Editar orcamento" data-budget-edit="${item.id}">✎</button>
        <button class="icon-btn" title="Excluir orcamento" data-budget-delete="${item.id}">×</button>
      </div>
    </div>
  `).join('');

  target.querySelectorAll('[data-budget-edit]').forEach(button => {
    button.addEventListener('click', () => editBudget(Number(button.dataset.budgetEdit)));
  });
  target.querySelectorAll('[data-budget-delete]').forEach(button => {
    button.addEventListener('click', () => deleteBudget(Number(button.dataset.budgetDelete)));
  });
}

function renderGoals() {
  const summary = document.querySelector('#goalsList');
  const manager = document.querySelector('#goalsManageList');
  const rows = state.goals.map(goal => {
    const pct = Math.min(100, Math.round((Number(goal.current_amount) / Math.max(1, Number(goal.target_amount))) * 100));
    return { goal, pct };
  });

  if (summary) {
    summary.innerHTML = rows.length ? rows.slice(0, 4).map(({ goal, pct }) => goalSummaryRow(goal, pct)).join('') : '<p class="muted">Nenhuma meta cadastrada ainda.</p>';
  }

  if (!manager) return;
  manager.innerHTML = rows.length ? rows.map(({ goal, pct }) => `
    <div class="list-row">
      <div>
        <strong>${escapeHtml(goal.name)}</strong>
        <small>${asMoney(goal.current_amount)} de ${asMoney(goal.target_amount)}${goal.target_date ? ' · alvo ' + formatDate(goal.target_date) : ''}</small>
        <div class="progress"><span style="width:${pct}%"></span></div>
      </div>
      <div class="row-actions">
        <span class="amount">${pct}%</span>
        <button class="icon-btn" title="Editar meta" data-goal-edit="${goal.id}">✎</button>
        <button class="icon-btn" title="Excluir meta" data-goal-delete="${goal.id}">×</button>
      </div>
    </div>
  `).join('') : '<p class="muted">Nenhuma meta cadastrada ainda.</p>';

  manager.querySelectorAll('[data-goal-edit]').forEach(button => {
    button.addEventListener('click', () => editGoal(Number(button.dataset.goalEdit)));
  });
  manager.querySelectorAll('[data-goal-delete]').forEach(button => {
    button.addEventListener('click', () => deleteGoal(Number(button.dataset.goalDelete)));
  });
}

function goalSummaryRow(goal, pct) {
  return `<div class="list-row"><div><strong>${escapeHtml(goal.name)}</strong><small>${asMoney(goal.current_amount)} de ${asMoney(goal.target_amount)}</small><div class="progress"><span style="width:${pct}%"></span></div></div><span class="amount">${pct}%</span></div>`;
}

function renderAccounts() {
  const target = document.querySelector('#accountsList');
  if (!target) return;
  target.innerHTML = state.accounts.map(account => `
    <div class="list-row">
      <div><strong>${escapeHtml(account.name)}</strong><small>${escapeHtml(account.type)}</small></div>
      <div class="row-actions">
        <span class="amount">${asMoney(account.opening_balance)}</span>
        <button class="icon-btn" title="Editar conta" data-account-edit="${account.id}">✎</button>
        <button class="icon-btn" title="Excluir conta" data-account-delete="${account.id}">×</button>
      </div>
    </div>
  `).join('');

  target.querySelectorAll('[data-account-edit]').forEach(button => {
    button.addEventListener('click', () => editAccount(Number(button.dataset.accountEdit)));
  });
  target.querySelectorAll('[data-account-delete]').forEach(button => {
    button.addEventListener('click', () => deleteAccount(Number(button.dataset.accountDelete)));
  });
}

function renderRecurring() {
  const target = document.querySelector('#recurringList');
  if (!target) return;
  setText('recurringMonthLabel', formatReferenceMonth(state.recurringMonth));
  const rules = state.recurring.filter(rule => !state.recurringLocationFilter || recurringLocation(rule) === state.recurringLocationFilter);
  target.innerHTML = rules.length ? rules.map(rule => {
    const paid = Number(rule.paid_this_month) === 1;
    const matcherCount = state.recurringMatchers.filter(matcher => Number(matcher.rule_id) === Number(rule.id)).length;
    return `
    <div class="list-row recurring-payment-row">
      <div>
        <strong>${escapeHtml(rule.description)}</strong>
        <small>${escapeHtml(frequencyLabel(rule.frequency))} · proximo ${formatDate(rule.next_due_date)} · ${rule.is_active == 1 ? 'ativa' : 'inativa'}${rule.category_name ? ' · ' + escapeHtml(rule.category_name) : ''}${matcherCount ? ` · ${matcherCount} ${matcherCount === 1 ? 'forma reconhecida' : 'formas reconhecidas'}` : ''}</small>
      </div>
      <div class="row-actions">
        <span class="amount">${asMoney(rule.amount)}</span>
        <span class="status ${paid ? 'paid' : 'pending'}">${paid ? (rule.match_method === 'automatic' ? 'Paga automaticamente' : 'Paga') : 'Pendente'}</span>
        <button class="small-btn" data-recurring-paid="${rule.id}" data-paid="${paid ? '0' : '1'}">${paid ? 'Desmarcar' : 'Marcar paga'}</button>
        <button class="small-btn" data-recurring-merge="${rule.id}">Mesclar</button>
        <button class="icon-btn" title="Editar recorrencia" data-recurring-edit="${rule.id}">✎</button>
        <button class="icon-btn" title="Excluir recorrencia" data-recurring-delete="${rule.id}">×</button>
      </div>
    </div>
  `; }).join('') : '<p class="muted">Nenhuma conta fixa neste local.</p>';

  target.querySelectorAll('[data-recurring-paid]').forEach(button => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        await api('toggle_recurring_paid', {
          method: 'POST',
          body: { id: Number(button.dataset.recurringPaid), month: state.recurringMonth, paid: Number(button.dataset.paid) },
        });
        await loadBootstrap();
      } catch (error) {
        alert(error.message || 'Nao foi possivel atualizar a conta fixa.');
        button.disabled = false;
      }
    });
  });

  target.querySelectorAll('[data-recurring-edit]').forEach(button => {
    button.addEventListener('click', () => editRecurring(Number(button.dataset.recurringEdit)));
  });
  target.querySelectorAll('[data-recurring-merge]').forEach(button => {
    button.addEventListener('click', () => prepareRecurringMerge(Number(button.dataset.recurringMerge)));
  });
  target.querySelectorAll('[data-recurring-delete]').forEach(button => {
    button.addEventListener('click', () => deleteRecurring(Number(button.dataset.recurringDelete)));
  });
}

function recurringLocation(rule) {
  const category = norm(rule.category_name || '');
  if (category === 'moradia' || category.startsWith('moradia /')) return 'apartment';
  if (category === 'estudio' || category.startsWith('estudio /')) return 'studio';
  return 'other';
}

function editTransaction(id) {
  const row = state.transactions.find(item => Number(item.id) === id);
  if (!row) return;
  const form = document.querySelector('#transactionForm');
  Object.entries(row).forEach(([key, value]) => {
    const field = form.elements[key];
    if (!field) return;
    if (field.type === 'checkbox') field.checked = value == 1;
    else field.value = value ?? '';
  });
  document.querySelector('#transactionModal')?.showModal();
}

function prepareTransactionForm() {
  const form = document.querySelector('#transactionForm');
  if (!form) return;
  form.reset();
  form.elements.id.value = '';
}

function editCategory(id) {
  const category = state.categories.find(item => Number(item.id) === id);
  if (!category) return;
  prepareCategoryForm(category);
  document.querySelector('#categoryModal')?.showModal();
}

function prepareCategoryForm(category = null, parentId = '') {
  const form = document.querySelector('#categoryForm');
  if (!form) return;
  form.reset();
  form.elements.id.value = category?.id || '';
  form.elements.name.value = category?.name || '';
  renderCategoryParentOptions(category?.id || 0);
  form.elements.parent_id.value = parentId || category?.parent_id || '';
  form.elements.color.value = category?.color || '#2563eb';
  setText('categoryFormTitle', category ? 'Editar categoria' : parentId ? 'Nova subcategoria' : 'Nova categoria');
}

async function deleteCategory(id) {
  const category = state.categories.find(item => Number(item.id) === id);
  if (!category) return;
  if (!confirm(`Excluir a categoria "${category.name}"? Lancamentos e extratos vinculados ficarao sem categoria.`)) return;
  await api('delete_category', { method: 'POST', body: { id } });
  await reloadAllData();
}

function editBudget(id) {
  const budget = state.budgets.find(item => Number(item.id) === id);
  if (!budget) return;
  prepareBudgetForm(budget);
  document.querySelector('#budgetModal')?.showModal();
}

function prepareBudgetForm(budget = null) {
  const form = document.querySelector('#budgetForm');
  if (!form) return;
  form.reset();
  form.elements.id.value = budget?.id || '';
  form.elements.month.value = budget?.month || form.elements.month.defaultValue;
  form.elements.category_id.value = budget?.category_id || '';
  form.elements.limit_amount.value = budget?.limit_amount ?? '';
  setText('budgetFormTitle', budget ? 'Editar orcamento' : 'Novo orcamento');
}

async function deleteBudget(id) {
  const budget = state.budgets.find(item => Number(item.id) === id);
  if (!budget) return;
  if (!confirm(`Excluir o orcamento de ${budget.category_name} em ${budget.month}?`)) return;
  await api('delete_budget', { method: 'POST', body: { id } });
  await reloadAllData();
}

function editGoal(id) {
  const goal = state.goals.find(item => Number(item.id) === id);
  if (!goal) return;
  prepareGoalForm(goal);
  document.querySelector('#goalModal')?.showModal();
}

function prepareGoalForm(goal = null) {
  const form = document.querySelector('#goalForm');
  if (!form) return;
  form.reset();
  form.elements.id.value = goal?.id || '';
  form.elements.name.value = goal?.name || '';
  form.elements.target_amount.value = goal?.target_amount ?? '';
  form.elements.current_amount.value = goal?.current_amount ?? '0';
  form.elements.target_date.value = goal?.target_date || '';
  setText('goalFormTitle', goal ? 'Editar meta' : 'Nova meta');
}

async function deleteGoal(id) {
  const goal = state.goals.find(item => Number(item.id) === id);
  if (!goal) return;
  if (!confirm(`Excluir a meta "${goal.name}"?`)) return;
  await api('delete_goal', { method: 'POST', body: { id } });
  await reloadAllData();
}

function editAccount(id) {
  const account = state.accounts.find(item => Number(item.id) === id);
  if (!account) return;
  prepareAccountForm(account);
  document.querySelector('#accountModal')?.showModal();
}

function prepareAccountForm(account = null) {
  const form = document.querySelector('#accountForm');
  if (!form) return;
  form.reset();
  form.elements.id.value = account?.id || '';
  form.elements.name.value = account?.name || '';
  form.elements.type.value = account?.type || 'corrente';
  form.elements.opening_balance.value = account?.opening_balance ?? '0';
  setText('accountFormTitle', account ? 'Editar conta/caixa' : 'Nova conta/caixa');
}

async function deleteAccount(id) {
  const account = state.accounts.find(item => Number(item.id) === id);
  if (!account) return;
  if (!confirm(`Excluir a conta/caixa "${account.name}"? Lancamentos e extratos vinculados ficarao sem conta.`)) return;
  await api('delete_account', { method: 'POST', body: { id } });
  await reloadAllData();
}

function editRecurring(id) {
  const rule = state.recurring.find(item => Number(item.id) === id);
  if (!rule) return;
  prepareRecurringForm(rule);
  document.querySelector('#recurringModal')?.showModal();
}

function prepareRecurringForm(rule = null) {
  const form = document.querySelector('#recurringForm');
  if (!form) return;
  form.reset();
  form.elements.id.value = rule?.id || '';
  form.elements.source_bank_transaction_id.value = '';
  form.elements.target_recurring_id.value = '';
  document.querySelector('#recurringTargetField').hidden = true;
  form.elements.description.value = rule?.description || '';
  form.elements.amount.value = rule?.amount ?? '';
  form.elements.category_id.value = rule?.category_id || '';
  form.elements.frequency.value = rule?.frequency || 'monthly';
  form.elements.next_due_date.value = rule?.next_due_date || '';
  form.elements.is_active.checked = rule ? rule.is_active == 1 : true;
  setText('recurringFormTitle', rule ? 'Editar conta fixa' : 'Nova conta fixa');
}

function prepareRecurringMerge(sourceId) {
  const source = state.recurring.find(item => Number(item.id) === sourceId);
  const form = document.querySelector('#mergeRecurringForm');
  if (!source || !form) return;
  form.reset();
  form.elements.source_id.value = sourceId;
  form.elements.target_id.innerHTML = '<option value="">Selecione</option>' + state.recurring
    .filter(rule => Number(rule.id) !== sourceId)
    .map(rule => `<option value="${rule.id}">${escapeHtml(rule.description)}</option>`).join('');
  setText('mergeRecurringDescription', `Os pagamentos e reconhecimentos de "${source.description}" serao levados para a conta escolhida.`);
  document.querySelector('#mergeRecurringModal')?.showModal();
}

async function deleteRecurring(id) {
  const rule = state.recurring.find(item => Number(item.id) === id);
  if (!rule) return;
  if (!confirm(`Excluir a regra recorrente "${rule.description}"?`)) return;
  await api('delete_recurring', { method: 'POST', body: { id } });
  await reloadAllData();
}

async function reloadAllData() {
  await loadBootstrap();
  await loadTransactions();
  await loadBankTransactions();
}

function setText(id, value) {
  const element = document.querySelector(`#${id}`);
  if (element) element.textContent = value;
}

function sumAmounts(rows) {
  return rows.reduce((sum, row) => sum + Number(row.amount || 0), 0);
}

function isPastDate(value) {
  if (!value) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const date = new Date(`${value}T00:00:00`);
  return date < today;
}

function formatDate(value) {
  if (!value) return 'Sem data';
  const [year, month, day] = value.split('-');
  return `${day}/${month}/${year}`;
}

function formatReferenceMonth(month) {
  if (!/^\d{4}-\d{2}$/.test(month || '')) return 'Mes atual';
  const [year, monthNumber] = month.split('-').map(Number);
  return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(new Date(year, monthNumber - 1, 1));
}

function formatDateTime(value) {
  if (!value) return 'Sem registro';
  const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T');
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function statusLabel(status) {
  return { paid: 'Pago', pending: 'Pendente', late: 'Atrasado', ignored: 'Ignorado' }[status] || status;
}

function frequencyLabel(frequency) {
  return { monthly: 'Mensal', weekly: 'Semanal', yearly: 'Anual' }[frequency] || frequency;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, match => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[match]);
}

function debounce(fn, wait) {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn(...args), wait);
  };
}

bootLogin();
bootApp().catch(error => alert(error.message));
