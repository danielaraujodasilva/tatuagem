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
  await loadBootstrap();
  await loadTransactions();
  await loadBankTransactions();
  renderReconciliation();
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
}

async function loadBankTransactions() {
  const params = new URLSearchParams({
    action: 'bank_transactions',
    month: document.querySelector('#monthFilter')?.value || '',
    q: document.querySelector('#bankSearchInput')?.value || '',
    bank: document.querySelector('#bankFilter')?.value || '',
  });
  const response = await fetch(`api.php?${params.toString()}`, { headers: { 'X-CSRF-Token': state.csrf } });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Erro ao carregar extratos.');
  state.bankTransactions = payload.bankTransactions || [];
  state.bankOverview = payload.bankOverview || null;
  renderBanking();
  renderReconciliation();
}

async function loadTransactions() {
  const params = new URLSearchParams({
    action: 'transactions',
    month: document.querySelector('#monthFilter')?.value || '',
    q: document.querySelector('#searchInput')?.value || '',
    status: document.querySelector('#statusFilter')?.value || '',
    type: document.querySelector('#typeFilter')?.value || '',
  });
  const response = await fetch(`api.php?${params.toString()}`, { headers: { 'X-CSRF-Token': state.csrf } });
  const payload = await response.json();
  if (!payload.ok) throw new Error(payload.message || 'Erro ao carregar.');
  state.transactions = payload.transactions;
  state.overview = payload.overview;
  renderTransactions();
  renderOverview();
  renderReconciliation();
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
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function bindModals() {
  document.querySelectorAll('[data-open-modal]').forEach(button => {
    button.addEventListener('click', () => document.querySelector(`#${button.dataset.openModal}`)?.showModal());
  });
  document.querySelectorAll('[data-close]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
  });
}

function bindFilters() {
  ['monthFilter', 'searchInput', 'statusFilter', 'typeFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', debounce(loadTransactions, 250));
  });
  document.querySelector('#monthFilter')?.addEventListener('input', debounce(loadBankTransactions, 250));
  ['bankSearchInput', 'bankFilter'].forEach(id => {
    document.querySelector(`#${id}`)?.addEventListener('input', debounce(loadBankTransactions, 250));
  });
  document.querySelector('#refreshBtn')?.addEventListener('click', async () => {
    await loadBootstrap();
    await loadTransactions();
    await loadBankTransactions();
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
      await api(action, { method: 'POST', body: formPayload(event.currentTarget) });
      event.currentTarget.reset();
      event.currentTarget.closest('dialog')?.close();
      await loadBootstrap();
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
  body.innerHTML = state.transactions.length ? state.transactions.map(row => `
    <tr>
      <td>${formatDate(row.due_date)}</td>
      <td><strong>${escapeHtml(row.description)}</strong><br><small>${row.payment_code ? escapeHtml(firstWords(row.payment_code, 6)) : 'Sem codigo de pagamento'} ${row.owner ? '· ' + escapeHtml(row.owner) : ''}</small></td>
      <td>${originBadge(row)}<br><small>${row.reference_month ? escapeHtml(row.reference_month) : formatDate(row.due_date)}</small></td>
      <td><span class="tag" style="border-color:${row.category_color || '#dbe3ef'}">${escapeHtml(row.category_name || 'Sem categoria')}</span></td>
      <td><span class="status ${row.status}">${statusLabel(row.status)}</span></td>
      <td class="amount">${asMoney(row.amount)}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" title="Marcar pago/pendente" data-toggle="${row.id}" data-status="${row.status === 'paid' ? 'pending' : 'paid'}">✓</button>
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
  if (!select) return;
  const current = select.value;
  const banks = [...new Set((state.bankOverview?.byBank || []).map(item => item.bank_name))];
  select.innerHTML = '<option value="">Todos bancos</option>' + banks.map(bank => `<option value="${escapeHtml(bank)}">${escapeHtml(bank)}</option>`).join('');
  select.value = banks.includes(current) ? current : '';
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
    <tr>
      <td><span class="bank-pill">${escapeHtml(row.bank_name)}</span></td>
      <td>${formatDate(row.transaction_date)}</td>
      <td><strong>${escapeHtml(row.description)}</strong><br><small>${escapeHtml(row.movement_type || row.source_file || '')}</small></td>
      <td>${escapeHtml(row.category_name || 'A categorizar')}</td>
      <td>${row.matched_transaction_id ? `<span class="status paid">Conciliado</span><br><small>${escapeHtml(row.matched_description || '')}</small>` : '<span class="status pending">Sem match</span>'}</td>
      <td class="amount ${row.direction === 'credit' ? 'positive' : 'negative'}">${row.direction === 'credit' ? '+' : '-'} ${asMoney(row.amount)}</td>
    </tr>
  `).join('') : '<tr><td colspan="6" class="empty-cell">Nenhuma movimentacao bancaria encontrada para os filtros atuais.</td></tr>';
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
      title: 'Contas pendentes da planilha',
      meta: `${pendingRows.length} itens ainda aparecem como pendentes`,
      amount: pendingRows.reduce((sum, row) => sum + Number(row.amount || 0), 0),
      action: 'Abrir lancamentos',
      section: 'transactions',
      tone: 'warning',
    },
    {
      title: 'Pagamentos encontrados no banco',
      meta: `${matchedBank.length} movimentacoes ja bateram com lancamentos`,
      amount: matchedBank.reduce((sum, row) => sum + Number(row.amount || 0), 0),
      action: 'Abrir extratos',
      section: 'banking',
      tone: 'success',
    },
    {
      title: 'Movimentacoes sem correspondencia',
      meta: `${unmatchedBank.length} itens do extrato precisam de revisao`,
      amount: unmatchedBank.reduce((sum, row) => sum + Number(row.amount || 0), 0),
      action: 'Revisar agora',
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
    <div class="bank-match-row">
      <span class="bank-pill">${escapeHtml(row.bank_name)}</span>
      <div>
        <strong>${escapeHtml(row.description)}</strong>
        <small>${formatDate(row.transaction_date)} · ${escapeHtml(row.movement_type || row.source_file || '')}</small>
      </div>
      <span class="amount ${row.direction === 'credit' ? 'positive' : 'negative'}">${row.direction === 'credit' ? '+' : '-'} ${asMoney(row.amount)}</span>
    </div>
  `).join('') : '<p class="muted">Tudo que veio do extrato neste filtro ja foi conciliado ou ainda nao ha extrato importado.</p>';
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
  target.innerHTML = state.categories.map(category => `<span class="chip" style="background:${category.color}">${escapeHtml(category.name)}</span>`).join('');
}

function renderBudgets() {
  const target = document.querySelector('#budgetsList');
  if (!target) return;
  target.innerHTML = state.budgets.map(item => `
    <div class="list-row">
      <div><strong>${escapeHtml(item.category_name)}</strong><small>${escapeHtml(item.month)}</small></div>
      <span class="amount">${asMoney(item.limit_amount)}</span>
    </div>
  `).join('');
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
  target.innerHTML = state.accounts.map(account => `
    <div class="list-row"><div><strong>${escapeHtml(account.name)}</strong><small>${escapeHtml(account.type)}</small></div><span class="amount">${asMoney(account.opening_balance)}</span></div>
  `).join('');
}

function renderRecurring() {
  const target = document.querySelector('#recurringList');
  if (!target) return;
  target.innerHTML = state.recurring.map(rule => `
    <div class="list-row"><div><strong>${escapeHtml(rule.description)}</strong><small>${escapeHtml(rule.frequency)} · proximo ${formatDate(rule.next_due_date)}</small></div><span class="amount">${asMoney(rule.amount)}</span></div>
  `).join('');
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

function setText(id, value) {
  const element = document.querySelector(`#${id}`);
  if (element) element.textContent = value;
}

function formatDate(value) {
  if (!value) return 'Sem data';
  const [year, month, day] = value.split('-');
  return `${day}/${month}/${year}`;
}

function statusLabel(status) {
  return { paid: 'Pago', pending: 'Pendente', late: 'Atrasado', ignored: 'Ignorado' }[status] || status;
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
