const state = {
  csrf: window.PLAN_BOOT?.csrf || '',
  categories: [],
  accounts: [],
  accountSummaries: [],
  accountHistory: null,
  accountHistoryId: null,
  transactionHistory: null,
  transactionHistoryId: null,
  transactionSuggestions: [],
  budgets: [],
  goals: [],
  recurring: [],
  transactions: [],
  overview: null,
  charts: {},
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
  const payload = await response.json();
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
  await loadBootstrap();
  await loadAccountSummaries();
  await loadTransactions();
}

async function loadBootstrap() {
  const payload = await api('bootstrap');
  Object.assign(state, {
    csrf: payload.csrf,
    categories: payload.categories,
    accounts: payload.accounts,
    transactionSuggestions: payload.transaction_suggestions || [],
    budgets: payload.budgets,
    goals: payload.goals,
    recurring: payload.recurring,
    overview: payload.overview,
  });
  renderSelects();
  renderDatalists();
  renderStaticLists();
  renderOverview();
}

async function loadTransactions() {
  const params = new URLSearchParams({
    action: 'transactions',
    month: document.querySelector('#monthFilter')?.value || '',
    q: document.querySelector('#searchInput')?.value || '',
    status: document.querySelector('#statusFilter')?.value || '',
    type: document.querySelector('#typeFilter')?.value || '',
    category_id: document.querySelector('#categoryFilter')?.value || '',
    account_id: document.querySelector('#accountFilter')?.value || '',
    is_fixed: document.querySelector('#fixedFilter')?.value || '',
    due_from: document.querySelector('#dueFromFilter')?.value || '',
    due_to: document.querySelector('#dueToFilter')?.value || '',
    amount_min: document.querySelector('#amountMinFilter')?.value || '',
    amount_max: document.querySelector('#amountMaxFilter')?.value || '',
  });
  const response = await fetch(`api.php?${params.toString()}`, { headers: { 'X-CSRF-Token': state.csrf } });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Erro ao carregar.');
  state.transactions = payload.transactions;
  state.overview = payload.overview;
  renderTransactions();
  renderOverview();
}

async function loadAccountSummaries() {
  const params = new URLSearchParams({
    action: 'account_summaries',
    month: document.querySelector('#accountMonthFilter')?.value || '',
    q: document.querySelector('#accountSearchInput')?.value || '',
    status: document.querySelector('#accountStatusFilter')?.value || '',
    type: document.querySelector('#accountTransactionTypeFilter')?.value || '',
    category_id: document.querySelector('#accountCategoryFilter')?.value || '',
    account_type: document.querySelector('#accountTypeFilter')?.value || '',
    due_from: document.querySelector('#accountDueFromFilter')?.value || '',
    due_to: document.querySelector('#accountDueToFilter')?.value || '',
  });
  const response = await fetch(`api.php?${params.toString()}`, { headers: { 'X-CSRF-Token': state.csrf } });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Erro ao carregar contas.');
  state.accountSummaries = payload.accounts;
  renderAccounts();
  renderAccountTotals();
}

function bindNavigation() {
  document.querySelectorAll('.nav-item').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
      document.querySelectorAll('.section').forEach(section => section.classList.remove('is-visible'));
      button.classList.add('active');
      document.querySelector(`#${button.dataset.section}`)?.classList.add('is-visible');
    });
  });
}

function bindModals() {
  document.querySelectorAll('[data-open-modal]').forEach(button => {
    button.addEventListener('click', () => {
      if (button.dataset.openModal === 'categoryModal') {
        prepareCategoryForm();
      }
      if (button.dataset.openModal === 'budgetModal') {
        prepareBudgetForm();
      }
      if (button.dataset.openModal === 'accountModal') {
        prepareAccountForm();
      }
      if (button.dataset.openModal === 'recurringModal') {
        prepareRecurringForm();
      }
      document.querySelector(`#${button.dataset.openModal}`)?.showModal();
    });
  });
  document.querySelectorAll('[data-close]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
  });
}

function bindFilters() {
  ['monthFilter', 'searchInput', 'statusFilter', 'typeFilter', 'categoryFilter', 'accountFilter', 'fixedFilter', 'dueFromFilter', 'dueToFilter', 'amountMinFilter', 'amountMaxFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', debounce(loadTransactions, 250));
  });
  ['accountSearchInput', 'accountMonthFilter', 'accountStatusFilter', 'accountTransactionTypeFilter', 'accountCategoryFilter', 'accountTypeFilter', 'accountDueFromFilter', 'accountDueToFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', debounce(loadAccountSummaries, 250));
  });
  document.querySelector('#refreshBtn')?.addEventListener('click', async () => {
    await loadBootstrap();
    await loadAccountSummaries();
    await loadTransactions();
  });
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
      const payload = formPayload(event.currentTarget);
      if (id === 'transactionForm') {
        payload.apply_category_rule = shouldApplyTransactionCategoryRule(payload) ? 1 : 0;
      }
      await api(action, { method: 'POST', body: payload });
      event.currentTarget.reset();
      event.currentTarget.closest('dialog')?.close();
      await loadBootstrap();
      await loadAccountSummaries();
      await loadTransactions();
    });
  });
}

function renderSelects() {
  document.querySelectorAll('[data-categories]').forEach(select => {
    select.innerHTML = '<option value="">Sem categoria</option>' + state.categories.map(category => (
      `<option value="${category.id}">${escapeHtml(category.name)}</option>`
    )).join('');
  });
  document.querySelectorAll('[data-accounts]').forEach(select => {
    select.innerHTML = '<option value="">Sem conta</option>' + state.accounts.map(account => (
      `<option value="${account.id}">${escapeHtml(account.name)}</option>`
    )).join('');
  });
  document.querySelectorAll('[data-filter-categories]').forEach(select => {
    select.innerHTML = '<option value="">Todas categorias</option>' + state.categories.map(category => (
      `<option value="${category.id}">${escapeHtml(category.name)}</option>`
    )).join('');
  });
  document.querySelectorAll('[data-filter-accounts]').forEach(select => {
    select.innerHTML = '<option value="">Todas contas</option>' + state.accounts.map(account => (
      `<option value="${account.id}">${escapeHtml(account.name)}</option>`
    )).join('');
  });
}

function renderDatalists() {
  const target = document.querySelector('#transactionSearchOptions');
  if (!target) return;
  const suggestions = [
    ...state.transactionSuggestions.map(item => item.description),
    ...state.accounts.map(account => account.name),
    ...state.categories.map(category => category.name),
  ].filter(Boolean);
  target.innerHTML = [...new Set(suggestions)].slice(0, 300).map(value => (
    `<option value="${escapeHtml(value)}"></option>`
  )).join('');
}

function renderOverview() {
  const totals = state.overview?.totals || {};
  setText('kpiIncome', asMoney(totals.income));
  setText('kpiExpenses', asMoney(totals.expenses));
  setText('kpiPaid', asMoney(totals.paid));
  setText('kpiPending', asMoney(totals.pending));
  setText('balanceBadge', `Saldo ${asMoney(totals.balance)}`);
  renderUpcoming();
  renderCharts();
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
  body.innerHTML = state.transactions.map(row => `
    <tr>
      <td>${formatDate(row.due_date)}</td>
      <td><strong>${escapeHtml(row.description)}</strong><br><small>${escapeHtml(row.source_sheet || 'manual')} ${row.owner ? '· ' + escapeHtml(row.owner) : ''}</small></td>
      <td><span class="tag" style="border-color:${row.category_color || '#dbe3ef'}">${escapeHtml(row.category_name || 'Sem categoria')}</span></td>
      <td><span class="status ${row.status}">${statusLabel(row.status)}</span></td>
      <td class="amount">${asMoney(row.amount)}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" title="Marcar pago/pendente" data-toggle="${row.id}" data-status="${row.status === 'paid' ? 'pending' : 'paid'}">✓</button>
          <button class="icon-btn" title="Historico" data-transaction-history="${row.id}">↺</button>
          <button class="icon-btn" title="Editar" data-edit="${row.id}">✎</button>
          <button class="icon-btn" title="Excluir" data-delete="${row.id}">×</button>
        </div>
      </td>
    </tr>
  `).join('');

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
  body.querySelectorAll('[data-transaction-history]').forEach(button => {
    button.addEventListener('click', () => openTransactionHistory(Number(button.dataset.transactionHistory)));
  });
}

function renderStaticLists() {
  renderCategories();
  renderBudgets();
  renderGoals();
  renderAccounts();
  renderRecurring();
}

function renderCategories() {
  const target = document.querySelector('#categoriesList');
  if (!target) return;
  target.innerHTML = state.categories.map(category => `
    <span class="chip editable-chip" style="background:${category.color}">
      <span>${escapeHtml(category.name)}</span>
      <button type="button" class="chip-btn" title="Editar categoria" data-category-edit="${category.id}">✎</button>
      <button type="button" class="chip-btn" title="Excluir categoria" data-category-delete="${category.id}">×</button>
    </span>
  `).join('');

  target.querySelectorAll('[data-category-edit]').forEach(button => {
    button.addEventListener('click', () => editCategory(Number(button.dataset.categoryEdit)));
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
  const target = document.querySelector('#goalsList');
  if (!target) return;
  target.innerHTML = state.goals.map(goal => {
    const pct = Math.min(100, Math.round((Number(goal.current_amount) / Math.max(1, Number(goal.target_amount))) * 100));
    return `<div class="list-row"><div><strong>${escapeHtml(goal.name)}</strong><small>${asMoney(goal.current_amount)} de ${asMoney(goal.target_amount)}</small><div class="progress"><span style="width:${pct}%"></span></div></div><span class="amount">${pct}%</span></div>`;
  }).join('');
}

function renderAccounts() {
  const target = document.querySelector('#accountsList');
  if (!target) return;
  const rows = state.accountSummaries.length ? state.accountSummaries : state.accounts;
  target.innerHTML = rows.map(account => `
    <div class="list-row">
      <div>
        <strong>${escapeHtml(account.name)}</strong>
        <small>${escapeHtml(account.type)} · ${Number(account.transaction_count || 0)} lancamento(s)${account.updated_at ? ' · ' + formatDateTime(account.updated_at) : ''}</small>
      </div>
      <div class="row-actions">
        <span class="amount">${asMoney(account.balance ?? account.opening_balance)}</span>
        <button class="icon-btn" title="Historico" data-account-history="${account.id}">↺</button>
        <button class="icon-btn" title="Editar" data-account-edit="${account.id}">✎</button>
        <button class="icon-btn" title="Excluir" data-account-delete="${account.id}">×</button>
      </div>
    </div>
  `).join('');

  target.querySelectorAll('[data-account-edit]').forEach(button => {
    button.addEventListener('click', () => editAccount(Number(button.dataset.accountEdit)));
  });
  target.querySelectorAll('[data-account-history]').forEach(button => {
    button.addEventListener('click', () => openAccountHistory(Number(button.dataset.accountHistory)));
  });
  target.querySelectorAll('[data-account-delete]').forEach(button => {
    button.addEventListener('click', () => deleteAccount(Number(button.dataset.accountDelete)));
  });
}

function renderAccountTotals() {
  const target = document.querySelector('#accountTotals');
  if (!target) return;
  const rows = state.accountSummaries || [];
  const totals = rows.reduce((acc, row) => {
    acc.income += Number(row.income || 0);
    acc.expenses += Number(row.expenses || 0);
    acc.paid += Number(row.paid || 0);
    acc.pending += Number(row.pending || 0);
    acc.balance += Number(row.balance || 0);
    acc.count += Number(row.transaction_count || 0);
    return acc;
  }, { income: 0, expenses: 0, paid: 0, pending: 0, balance: 0, count: 0 });

  target.innerHTML = `
    <span><strong>${asMoney(totals.income)}</strong><small>Receitas</small></span>
    <span><strong>${asMoney(totals.expenses)}</strong><small>Despesas</small></span>
    <span><strong>${asMoney(totals.paid)}</strong><small>Pago</small></span>
    <span><strong>${asMoney(totals.pending)}</strong><small>Pendente</small></span>
    <span><strong>${asMoney(totals.balance)}</strong><small>Saldo filtrado</small></span>
    <span><strong>${totals.count}</strong><small>Lancamentos</small></span>
  `;
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

function editCategory(id) {
  const category = state.categories.find(item => Number(item.id) === id);
  if (!category) return;
  prepareCategoryForm(category);
  document.querySelector('#categoryModal')?.showModal();
}

function prepareCategoryForm(category = null) {
  const form = document.querySelector('#categoryForm');
  if (!form) return;
  form.reset();
  form.elements.id.value = category?.id || '';
  form.elements.name.value = category?.name || '';
  form.elements.color.value = category?.color || '#2563eb';
  const title = document.querySelector('#categoryFormTitle');
  if (title) {
    title.textContent = category ? 'Editar categoria' : 'Nova categoria';
  }
}

async function deleteCategory(id) {
  const category = state.categories.find(item => Number(item.id) === id);
  if (!category) return;
  if (!confirm(`Excluir a categoria "${category.name}"? Os lancamentos vinculados ficarao sem categoria.`)) return;
  await api('delete_category', { method: 'POST', body: { id } });
  await reloadPlanningData();
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
  const title = document.querySelector('#budgetFormTitle');
  if (title) {
    title.textContent = budget ? 'Editar orcamento' : 'Novo orcamento';
  }
}

async function deleteBudget(id) {
  const budget = state.budgets.find(item => Number(item.id) === id);
  if (!budget) return;
  if (!confirm(`Excluir o orcamento de ${budget.category_name} em ${budget.month}?`)) return;
  await api('delete_budget', { method: 'POST', body: { id } });
  await reloadPlanningData();
}

function shouldApplyTransactionCategoryRule(payload) {
  if (!payload.id || !payload.category_id) return false;
  const current = state.transactions.find(item => Number(item.id) === Number(payload.id));
  if (!current || String(current.category_id || '') === String(payload.category_id || '')) return false;
  const signature = current.description_signature || transactionSignature(current.description || '');
  const matches = state.transactions.filter(item => (
    Number(item.id) !== Number(payload.id) &&
    (item.description_signature || transactionSignature(item.description || '')) === signature &&
    String(item.category_id || '') !== String(payload.category_id || '')
  ));
  const countText = matches.length ? `${matches.length} ocorrencia(s) parecida(s)` : 'as proximas ocorrencias parecidas';

  return confirm(`Aplicar esta categoria tambem para ${countText}?`);
}

async function openTransactionHistory(id) {
  state.transactionHistoryId = id;
  const response = await fetch(`api.php?action=transaction_history&id=${id}`, {
    headers: { 'X-CSRF-Token': state.csrf },
  });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Falha ao carregar historico.');
  state.transactionHistory = payload;
  renderTransactionHistory();
  document.querySelector('#transactionHistoryModal')?.showModal();
}

function renderTransactionHistory() {
  const target = document.querySelector('#transactionHistoryBody');
  const title = document.querySelector('#transactionHistoryTitle');
  if (!target || !state.transactionHistory) return;

  const transaction = state.transactionHistory.transaction || {};
  if (title) {
    title.textContent = `Historico: ${transaction.description || 'Lancamento'}`;
  }

  const versions = state.transactionHistory.versions || [];
  const conflicts = state.transactionHistory.conflicts || [];

  target.innerHTML = `
    <section class="stack-list">
      <div class="list-row">
        <div>
          <strong>Versões registradas</strong>
          <small>${versions.length} atualização(ões) salvas no histórico</small>
        </div>
      </div>
      ${versions.length ? versions.map(version => `
        <div class="list-row">
          <div>
            <strong>${escapeHtml(version.action || 'update')}</strong>
            <small>${escapeHtml(version.source_mode || 'manual')} · ${formatDateTime(version.created_at)}${version.user_name ? ' · ' + escapeHtml(version.user_name) : ''}</small>
          </div>
          <span class="amount">${version.changes_json ? 'ajustes' : 'snapshot'}</span>
        </div>
      `).join('') : '<p class="muted">Nenhuma versão registrada ainda.</p>'}
    </section>
    <section class="stack-list" style="margin-top:16px">
      <div class="list-row">
        <div>
          <strong>Conflitos de importação</strong>
          <small>${conflicts.length} conflito(s) detectado(s)</small>
        </div>
      </div>
      ${conflicts.length ? conflicts.map(conflict => `
        <div class="list-row">
          <div>
            <strong>${escapeHtml(conflict.conflict_reason)}</strong>
            <small>${formatDateTime(conflict.created_at)}${conflict.resolution ? ' · Resolvido' : ' · Pendente'}</small>
          </div>
          <div class="row-actions">
            ${conflict.resolution ? `<span class="status ${conflict.resolution === 'accept_import' ? 'paid' : 'ignored'}">${conflict.resolution === 'accept_import' ? 'Importação aceita' : 'Mantido local'}</span>` : `
              <button class="ghost-btn" data-transaction-conflict-keep="${conflict.id}">Manter local</button>
              <button class="primary-btn" data-transaction-conflict-accept="${conflict.id}">Aceitar importação</button>
            `}
          </div>
        </div>
      `).join('') : '<p class="muted">Nenhum conflito pendente.</p>'}
    </section>
  `;

  target.querySelectorAll('[data-transaction-conflict-keep]').forEach(button => {
    button.addEventListener('click', async () => {
      await resolveTransactionConflict(Number(button.dataset.transactionConflictKeep), 'keep_local');
    });
  });
  target.querySelectorAll('[data-transaction-conflict-accept]').forEach(button => {
    button.addEventListener('click', async () => {
      await resolveTransactionConflict(Number(button.dataset.transactionConflictAccept), 'accept_import');
    });
  });
}

async function resolveTransactionConflict(conflictId, resolution) {
  await api('resolve_transaction_conflict', {
    method: 'POST',
    body: { conflict_id: conflictId, resolution },
  });
  await loadBootstrap();
  await loadTransactions();
  if (state.transactionHistoryId) {
    await openTransactionHistory(state.transactionHistoryId);
  }
}

function editAccount(id) {
  const row = state.accounts.find(item => Number(item.id) === id);
  if (!row) return;
  prepareAccountForm(row);
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
  const title = document.querySelector('#accountFormTitle');
  if (title) {
    title.textContent = account ? 'Editar conta' : 'Nova conta';
  }
}

async function deleteAccount(id) {
  const account = state.accounts.find(item => Number(item.id) === id);
  if (!account) return;
  if (!confirm(`Excluir a conta "${account.name}"? Os lancamentos vinculados ficarao sem conta.`)) return;
  await api('delete_account', { method: 'POST', body: { id } });
  await reloadPlanningData();
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
  const title = document.querySelector('#recurringFormTitle');
  if (title) {
    title.textContent = rule ? 'Editar recorrencia' : 'Nova recorrencia';
  }
}

async function deleteRecurring(id) {
  const rule = state.recurring.find(item => Number(item.id) === id);
  if (!rule) return;
  if (!confirm(`Excluir a regra recorrente "${rule.description}"?`)) return;
  await api('delete_recurring', { method: 'POST', body: { id } });
  await reloadPlanningData();
}

async function reloadPlanningData() {
  await loadBootstrap();
  await loadAccountSummaries();
  await loadTransactions();
}

async function openAccountHistory(id) {
  state.accountHistoryId = id;
  const response = await fetch(`api.php?action=account_history&id=${id}`, {
    headers: { 'X-CSRF-Token': state.csrf },
  });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Falha ao carregar historico.');
  state.accountHistory = payload;
  renderAccountHistory();
  document.querySelector('#accountHistoryModal')?.showModal();
}

function renderAccountHistory() {
  const target = document.querySelector('#accountHistoryBody');
  const title = document.querySelector('#accountHistoryTitle');
  if (!target || !state.accountHistory) return;

  const account = state.accountHistory.account || {};
  if (title) {
    title.textContent = `Historico: ${account.name || 'Conta'}`;
  }

  const versions = state.accountHistory.versions || [];
  const conflicts = state.accountHistory.conflicts || [];

  target.innerHTML = `
    <section class="stack-list">
      <div class="list-row">
        <div>
          <strong>Versões registradas</strong>
          <small>${versions.length} atualização(ões) salvas no histórico</small>
        </div>
      </div>
      ${versions.length ? versions.map(version => `
        <div class="list-row">
          <div>
            <strong>${escapeHtml(version.action || 'update')}</strong>
            <small>${escapeHtml(version.source_mode || 'manual')} · ${formatDateTime(version.created_at)}${version.user_name ? ' · ' + escapeHtml(version.user_name) : ''}</small>
          </div>
          <span class="amount">${version.changes_json ? 'ajustes' : 'snapshot'}</span>
        </div>
      `).join('') : '<p class="muted">Nenhuma versão registrada ainda.</p>'}
    </section>
    <section class="stack-list" style="margin-top:16px">
      <div class="list-row">
        <div>
          <strong>Conflitos de importação</strong>
          <small>${conflicts.length} conflito(s) detectado(s)</small>
        </div>
      </div>
      ${conflicts.length ? conflicts.map(conflict => `
        <div class="list-row">
          <div>
            <strong>${escapeHtml(conflict.conflict_reason)}</strong>
            <small>${formatDateTime(conflict.created_at)}${conflict.resolution ? ' · Resolvido' : ' · Pendente'}</small>
          </div>
          <div class="row-actions">
            ${conflict.resolution ? `<span class="status ${conflict.resolution === 'accept_import' ? 'paid' : 'ignored'}">${conflict.resolution === 'accept_import' ? 'Importação aceita' : 'Mantido local'}</span>` : `
              <button class="ghost-btn" data-conflict-keep="${conflict.id}">Manter local</button>
              <button class="primary-btn" data-conflict-accept="${conflict.id}">Aceitar importação</button>
            `}
          </div>
        </div>
      `).join('') : '<p class="muted">Nenhum conflito pendente.</p>'}
    </section>
  `;

  target.querySelectorAll('[data-conflict-keep]').forEach(button => {
    button.addEventListener('click', async () => {
      await resolveAccountConflict(Number(button.dataset.conflictKeep), 'keep_local');
    });
  });
  target.querySelectorAll('[data-conflict-accept]').forEach(button => {
    button.addEventListener('click', async () => {
      await resolveAccountConflict(Number(button.dataset.conflictAccept), 'accept_import');
    });
  });
}

async function resolveAccountConflict(conflictId, resolution) {
  await api('resolve_account_conflict', {
    method: 'POST',
    body: { conflict_id: conflictId, resolution },
  });
  await loadBootstrap();
  if (state.accountHistoryId) {
    await openAccountHistory(state.accountHistoryId);
  }
}

function setText(id, value) {
  const element = document.querySelector(`#${id}`);
  if (element) element.textContent = value;
}

function formatDate(value) {
  if (!value) return 'Sem data';
  const [year, month, day] = value.split('-');
  return `${day}/${month}/${year}`;
}

function formatDateTime(value) {
  if (!value) return 'Sem data';
  const parsed = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(parsed.getTime())) return String(value);
  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(parsed);
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

function transactionSignature(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/\b\d{1,2}[/-]\d{1,2}(?:[/-]\d{2,4})?\b/g, ' ')
    .replace(/\b\d{4}\b/g, ' ')
    .replace(/\b\d+\b/g, ' ')
    .replace(/[^A-Z0-9]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
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
