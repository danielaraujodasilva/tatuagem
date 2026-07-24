const state = {
  csrf: window.PLAN_BOOT?.csrf || '',
  categories: [],
  accounts: [],
  budgets: [],
  goals: [],
  recurring: [],
  transactions: [],
  bankImports: [],
  bankTransactions: [],
  bankOverview: null,
  bankPreview: [],
  bankPreviewMeta: null,
  bankPreviewGroups: [],
  sheetImportRows: [],
  overview: null,
  charts: {},
  pendingShare: null,
  handledShareToken: '',
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
    bankImports: payload.bankImports || [],
    bankOverview: payload.bankOverview || null,
    overview: payload.overview,
  });
  renderSelects();
  renderStaticLists();
  renderOverview();
  renderReconciliation();
  renderCategoryAnalysis();
}

async function loadBankTransactions() {
  const dateFrom = document.querySelector('#movementDateFrom')?.value || '';
  const dateTo = document.querySelector('#movementDateTo')?.value || '';
  const params = new URLSearchParams({
    action: 'bank_transactions',
    month: dateFrom || dateTo ? '' : document.querySelector('#monthFilter')?.value || '',
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
  state.bankTransactions = payload.bankTransactions || [];
  state.bankOverview = payload.bankOverview || null;
  renderMovementSearchSuggestions();
  renderBanking();
  renderMovements();
  renderReconciliation();
  renderCategoryAnalysis();
}

async function loadTransactions() {
  const selectedMonth = document.querySelector('#monthFilter')?.value || '';
  const params = new URLSearchParams({
    action: 'transactions',
    month: selectedMonth,
    q: '',
    status: '',
    type: '',
  });
  const response = await fetch(`api.php?${params.toString()}`, { headers: { 'X-CSRF-Token': state.csrf } });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Erro ao carregar.');
  state.transactions = payload.transactions || [];
  if (selectedMonth && state.transactions.length === 0) {
    const fallbackResponse = await fetch('api.php?action=transactions', { headers: { 'X-CSRF-Token': state.csrf } });
    const fallbackPayload = await fallbackResponse.json();
    if (fallbackPayload.ok) {
      state.transactions = (fallbackPayload.transactions || []).filter(row => matchesMonth(row, selectedMonth));
      if (state.transactions.length) {
        payload.overview = buildClientOverview(selectedMonth, state.transactions, payload.overview);
      }
    }
  }
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
  updatePageContext(sectionId);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

const pageContexts = {
  dashboard: ['Visao geral', 'Seu dinheiro em ordem', 'Veja o que entrou, saiu e precisa da sua atencao no mes selecionado.'],
  categoryAnalysis: ['Visao geral', 'Entenda seus padroes', 'Compare categorias, percentuais e linhas sem perder o contexto.'],
  bills: ['Acompanhar', 'Contas do mes', 'Controle o que ja foi pago e o que ainda precisa de uma acao.'],
  movements: ['Acompanhar', 'Extratos reais', 'Explore o dinheiro que realmente entrou e saiu das suas contas.'],
  reconciliation: ['Acompanhar', 'Conferir o planejado contra o realizado', 'Compare suas contas da planilha com o que realmente apareceu no banco e resolva apenas os alertas.'],
  transactions: ['Importar dados', 'Planilha de planejamento', 'Traga suas contas e preserve as edicoes feitas no sistema.'],
  banking: ['Importar dados', 'Extratos bancarios', 'Importe, revise duplicidades e salve as movimentacoes do banco.'],
  budgets: ['Planejar', 'Orcamentos mensais', 'Defina limites por categoria e acompanhe suas escolhas.'],
  goals: ['Planejar', 'Metas', 'Acompanhe o progresso do que voce quer construir.'],
  recurring: ['Planejar', 'Recorrencias', 'Mantenha regras prontas para contas que se repetem.'],
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
    button.addEventListener('click', () => button.closest('dialog')?.close());
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
  document.querySelector('#monthFilter')?.addEventListener('input', debounce(async () => {
    syncMovementDatesFromMonth();
    await loadTransactions();
    await loadBankTransactions();
  }, 250));
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
  ['movementDateFrom', 'movementDateTo', 'movementSearchInput', 'movementBankFilter', 'movementDirectionFilter', 'movementMatchFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', debounce(loadBankTransactions, 250));
  });
  bindCategoryFilter('movement', () => loadBankTransactions());
  document.querySelector('#clearMovementFilters')?.addEventListener('click', async () => {
    syncMovementDatesFromMonth();
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
  document.querySelectorAll('[data-pivot-toggle]').forEach(button => {
    button.addEventListener('click', () => setPivotOpenState(button.dataset.pivotScope, button.dataset.pivotToggle === 'open'));
  });
}

function syncMovementDatesFromMonth() {
  const month = document.querySelector('#monthFilter')?.value;
  if (!month) return;
  const from = document.querySelector('#movementDateFrom');
  const to = document.querySelector('#movementDateTo');
  if (!from || !to) return;
  const lastDay = new Date(Number(month.slice(0, 4)), Number(month.slice(5, 7)), 0).getDate();
  from.value = `${month}-01`;
  to.value = `${month}-${String(lastDay).padStart(2, '0')}`;
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
    const from = document.querySelector('#movementDateFrom');
    const to = document.querySelector('#movementDateTo');
    const search = document.querySelector('#movementSearchInput');
    if (from && date) from.value = date;
    if (to && date) to.value = date;
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

  const month = target.reference_month || String(target.due_date || '').slice(0, 7);
  const monthFilter = document.querySelector('#monthFilter');
  if (monthFilter && month) monthFilter.value = month;
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
  document.querySelector('#bankFileInput')?.addEventListener('change', handleBankFiles);
  document.querySelector('#clearBankPreview')?.addEventListener('click', () => {
    state.bankPreview = [];
    state.bankPreviewMeta = null;
    state.bankPreviewGroups = [];
    renderBankPreview();
  });
  document.querySelector('#saveBankImport')?.addEventListener('click', saveBankImport);
}

function bindForms() {
  const forms = {
    transactionForm: 'save_transaction',
    categoryForm: 'save_category',
    budgetForm: 'save_budget',
    goalForm: 'save_goal',
    accountForm: 'save_account',
    recurringForm: 'save_recurring',
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
        await api(action, { method: 'POST', body: formPayload(form) });
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
      <option value="">Sem categoria</option>${parentOptions}
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
  const applySimilar = confirm(`Categoria alterada para "${label}". Quer aplicar tambem nas ocorrencias parecidas que eu encontrar?`);
  picker.querySelectorAll('select').forEach(select => { select.disabled = true; });
  try {
    await api(kind === 'bank_transaction' ? 'update_bank_transaction_category' : 'update_transaction_category', {
      method: 'POST',
      body: { id, category_id: categoryId, apply_similar: applySimilar ? 1 : 0 },
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
  const totals = state.overview?.totals || {};
  setText('kpiIncome', asMoney(totals.income));
  setText('kpiExpenses', asMoney(totals.expenses));
  setText('kpiPaid', asMoney(totals.paid));
  setText('kpiPending', asMoney(totals.pending));
  setText('balanceBadge', `Saldo ${asMoney(totals.balance)}`);
  renderWorkflowStrip();
  renderUpcoming();
  renderCharts();
  renderBankingSummary();
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

  setText('analysisExpenseTotal', asMoney(expenseTotal));
  setText('analysisIncomeTotal', asMoney(incomeTotal));
  setText('analysisNetTotal', asMoney(incomeTotal - expenseTotal));
  setText('analysisTopCategory', topGroup ? `${topGroup.category} ${formatPercent(topGroup.total, topGroup.direction === 'income' ? incomeTotal : expenseTotal)}` : '-');
  setText('analysisExpenseCount', `${expenseGroups.length} categorias`);
  setText('analysisIncomeCount', `${incomeGroups.length} categorias`);
  renderCategoryPivot('expenseCategoryPivot', expenseGroups, expenseTotal, 'expense');
  renderCategoryPivot('incomeCategoryPivot', incomeGroups, incomeTotal, 'income');
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
    acc[item.category] ||= { category: item.category, direction: item.direction, total: 0, rows: [] };
    acc[item.category].total += item.amount;
    acc[item.category].rows.push(item);
    return acc;
  }, {});
  return Object.values(grouped)
    .map(group => ({ ...group, rows: sortAnalyticsRows(group.rows, rowSort) }))
    .sort((a, b) => compareAnalyticsGroups(a, b, groupSort));
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
            <small>${group.rows.length} itens · ${pct} dos ${label}</small>
          </span>
          <span class="amount ${direction === 'income' ? 'positive' : 'negative'}">${asMoney(group.total)}</span>
        </summary>
        <div class="pivot-bar"><span style="width:${Math.min(100, total ? (group.total / total) * 100 : 0)}%"></span></div>
        <div class="pivot-rows">
          ${group.rows.map(row => `
            <button class="pivot-row" data-pivot-source="${row.sourceType}" data-pivot-id="${row.id}">
              <span>${formatDate(row.date)}</span>
              <strong>${escapeHtml(row.description)}</strong>
              <small>${escapeHtml(row.sourceLabel)}${row.meta ? ' · ' + escapeHtml(row.meta) : ''}</small>
              <em class="${direction === 'income' ? 'positive' : 'negative'}">${direction === 'income' ? '+' : '-'} ${asMoney(row.amount)}</em>
            </button>
          `).join('')}
        </div>
      </details>
    `;
  }).join('') : `<p class="muted">Nenhuma categoria de ${label} para os filtros atuais.</p>`;

  target.querySelectorAll('[data-pivot-source]').forEach(button => {
    button.addEventListener('click', () => openPivotItem(button.dataset.pivotSource, Number(button.dataset.pivotId)));
  });
}

function setPivotOpenState(scope, isOpen) {
  const selector = scope === 'all' ? '.pivot-group' : `.pivot-group.${scope}`;
  document.querySelectorAll(selector).forEach(group => {
    group.open = isOpen;
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
  if (!target) return;
  const values = [...new Set(state.bankTransactions.flatMap(row => [row.description, row.bank_name]).map(clean).filter(Boolean))]
    .sort((a, b) => a.localeCompare(b, 'pt-BR'))
    .slice(0, 80);
  target.innerHTML = values.map(value => `<option value="${escapeHtml(value)}"></option>`).join('');
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
  renderBankingSummary();
  renderBankFilter();
  renderBankTransactions();
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
  setText('movementRowsCount', `${rows.length} linhas`);
  renderMovementCategorySummary(rows);
  renderCategorizedBankTable(rows);
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
      section: 'banking',
      tone: 'success',
    },
    {
      title: 'Movimentos do banco sem correspondencia',
      meta: `${unmatchedBank.length} itens precisam ser entendidos ou categorizados`,
      amount: unmatchedBank.reduce((sum, row) => sum + Number(row.amount || 0), 0),
      action: 'Revisar movimentos',
      section: 'banking',
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
  `).join('') : '<p class="muted">Tudo que veio do extrato neste filtro ja foi conciliado ou ainda nao ha extrato importado.</p>';
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
  target.innerHTML = state.recurring.map(rule => `
    <div class="list-row">
      <div>
        <strong>${escapeHtml(rule.description)}</strong>
        <small>${escapeHtml(frequencyLabel(rule.frequency))} · proximo ${formatDate(rule.next_due_date)} · ${rule.is_active == 1 ? 'ativa' : 'inativa'}${rule.category_name ? ' · ' + escapeHtml(rule.category_name) : ''}</small>
      </div>
      <div class="row-actions">
        <span class="amount">${asMoney(rule.amount)}</span>
        <button class="icon-btn" title="Editar recorrencia" data-recurring-edit="${rule.id}">✎</button>
        <button class="icon-btn" title="Excluir recorrencia" data-recurring-delete="${rule.id}">×</button>
      </div>
    </div>
  `).join('');

  target.querySelectorAll('[data-recurring-edit]').forEach(button => {
    button.addEventListener('click', () => editRecurring(Number(button.dataset.recurringEdit)));
  });
  target.querySelectorAll('[data-recurring-delete]').forEach(button => {
    button.addEventListener('click', () => deleteRecurring(Number(button.dataset.recurringDelete)));
  });
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
  form.elements.description.value = rule?.description || '';
  form.elements.amount.value = rule?.amount ?? '';
  form.elements.category_id.value = rule?.category_id || '';
  form.elements.frequency.value = rule?.frequency || 'monthly';
  form.elements.next_due_date.value = rule?.next_due_date || '';
  form.elements.is_active.checked = rule ? rule.is_active == 1 : true;
  setText('recurringFormTitle', rule ? 'Editar recorrencia' : 'Nova recorrencia');
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
