<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plan Financeiro</title>
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="assets/app.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js" defer></script>
    <script>
        window.PLAN_BOOT = {
            authenticated: <?= $user ? 'true' : 'false' ?>,
            csrf: <?= json_encode($csrf) ?>
        };
    </script>
    <script src="assets/app.js" defer></script>
</head>
<body>
<?php if (!$user): ?>
    <main class="login-shell">
        <section class="login-visual">
            <div class="brand-mark">P</div>
            <h1>Plan Financeiro</h1>
            <p>Controle completo dos registros importados da sua planilha, com painel mensal, vencimentos, metas, categorias e orcamentos.</p>
            <div class="login-metrics">
                <span>Sheets</span>
                <span>MySQL</span>
                <span>Dashboard</span>
            </div>
        </section>
        <section class="login-panel">
            <form id="loginForm" class="form-stack">
                <h2>Entrar</h2>
                <label>
                    E-mail
                    <input name="email" type="email" value="danielaraujodasilva@gmail.com" autocomplete="email" required>
                </label>
                <label>
                    Senha
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>
                <button class="primary-btn" type="submit">Acessar sistema</button>
                <p class="form-message" id="loginMessage"></p>
            </form>
        </section>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="logo" href="index.php">
                <span>P</span>
                <strong>Plan</strong>
            </a>
            <nav>
                <button class="nav-item active" data-section="dashboard">Painel</button>
                <button class="nav-item" data-section="transactions">Lancamentos</button>
                <button class="nav-item" data-section="planning">Planejamento</button>
                <button class="nav-item" data-section="automation">Recorrencias</button>
            </nav>
            <a class="logout" href="logout.php">Sair</a>
        </aside>

        <main class="workspace">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Vida financeira</p>
                    <h1>Controle financeiro pessoal</h1>
                </div>
                <div class="top-actions">
                    <input id="monthFilter" type="month" value="<?= date('Y-m') ?>">
                    <button class="ghost-btn" id="refreshBtn">Atualizar</button>
                    <button class="primary-btn" data-open-modal="transactionModal">Novo lancamento</button>
                </div>
            </header>

            <section class="section is-visible" id="dashboard">
                <div class="kpi-grid">
                    <article class="metric-card"><span>Receitas</span><strong id="kpiIncome">R$ 0,00</strong></article>
                    <article class="metric-card danger"><span>Despesas</span><strong id="kpiExpenses">R$ 0,00</strong></article>
                    <article class="metric-card success"><span>Pago</span><strong id="kpiPaid">R$ 0,00</strong></article>
                    <article class="metric-card warning"><span>Falta pagar</span><strong id="kpiPending">R$ 0,00</strong></article>
                </div>

                <div class="dashboard-grid">
                    <section class="panel chart-panel">
                        <div class="panel-head">
                            <h2>Evolucao mensal</h2>
                            <span id="balanceBadge">Saldo R$ 0,00</span>
                        </div>
                        <canvas id="monthlyChart"></canvas>
                    </section>
                    <section class="panel chart-panel">
                        <div class="panel-head">
                            <h2>Gastos por categoria</h2>
                            <span>Mes selecionado</span>
                        </div>
                        <canvas id="categoryChart"></canvas>
                    </section>
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Proximos vencimentos</h2>
                            <span>21 dias</span>
                        </div>
                        <div id="upcomingList" class="stack-list"></div>
                    </section>
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Metas</h2>
                            <button class="small-btn" data-open-modal="goalModal">Nova</button>
                        </div>
                        <div id="goalsList" class="stack-list"></div>
                    </section>
                </div>
            </section>

            <section class="section" id="transactions">
                <div class="panel">
                    <div class="panel-head wrap">
                        <h2>Lancamentos</h2>
                        <div class="filters">
                            <input id="searchInput" list="transactionSearchOptions" placeholder="Buscar descricao, pix, boleto, responsavel">
                            <datalist id="transactionSearchOptions"></datalist>
                            <select id="statusFilter">
                                <option value="">Todos status</option>
                                <option value="pending">Pendente</option>
                                <option value="paid">Pago</option>
                                <option value="late">Atrasado</option>
                                <option value="ignored">Ignorado</option>
                            </select>
                            <select id="typeFilter">
                                <option value="">Todos tipos</option>
                                <option value="expense">Despesa</option>
                                <option value="income">Receita</option>
                                <option value="transfer">Transferencia</option>
                            </select>
                            <select id="categoryFilter" data-filter-categories></select>
                            <select id="accountFilter" data-filter-accounts></select>
                            <select id="fixedFilter">
                                <option value="">Fixos e avulsos</option>
                                <option value="1">Somente fixos</option>
                                <option value="0">Somente avulsos</option>
                            </select>
                            <input id="dueFromFilter" type="date" title="Vencimento inicial">
                            <input id="dueToFilter" type="date" title="Vencimento final">
                            <input id="amountMinFilter" inputmode="decimal" placeholder="Valor min.">
                            <input id="amountMaxFilter" inputmode="decimal" placeholder="Valor max.">
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Vencimento</th>
                                    <th>Descricao</th>
                                    <th>Categoria</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="transactionsBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="section" id="planning">
                <div class="split-grid">
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Orcamentos mensais</h2>
                            <button class="small-btn" data-open-modal="budgetModal">Adicionar</button>
                        </div>
                        <div id="budgetsList" class="stack-list"></div>
                    </section>
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Categorias</h2>
                            <button class="small-btn" data-open-modal="categoryModal">Nova</button>
                        </div>
                        <div id="categoriesList" class="chip-list"></div>
                    </section>
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Contas</h2>
                            <button class="small-btn" data-open-modal="accountModal">Nova</button>
                        </div>
                        <div class="filters account-filters">
                            <input id="accountSearchInput" list="transactionSearchOptions" placeholder="Buscar conta, descricao, pix">
                            <input id="accountMonthFilter" type="month" value="<?= date('Y-m') ?>">
                            <select id="accountStatusFilter">
                                <option value="">Todos status</option>
                                <option value="pending">Pendente</option>
                                <option value="paid">Pago</option>
                                <option value="late">Atrasado</option>
                                <option value="ignored">Ignorado</option>
                            </select>
                            <select id="accountTransactionTypeFilter">
                                <option value="">Todos tipos</option>
                                <option value="expense">Despesa</option>
                                <option value="income">Receita</option>
                                <option value="transfer">Transferencia</option>
                            </select>
                            <select id="accountCategoryFilter" data-filter-categories></select>
                            <select id="accountTypeFilter">
                                <option value="">Todos tipos de conta</option>
                                <option value="corrente">Corrente</option>
                                <option value="credito">Cartao</option>
                                <option value="investimento">Investimento</option>
                                <option value="dinheiro">Dinheiro</option>
                            </select>
                            <input id="accountDueFromFilter" type="date" title="Vencimento inicial">
                            <input id="accountDueToFilter" type="date" title="Vencimento final">
                        </div>
                        <div id="accountTotals" class="account-total-bar"></div>
                        <div id="accountsList" class="stack-list"></div>
                    </section>
                </div>
            </section>

            <section class="section" id="automation">
                <div class="panel">
                    <div class="panel-head">
                        <h2>Despesas recorrentes</h2>
                        <button class="small-btn" data-open-modal="recurringModal">Nova regra</button>
                    </div>
                    <div id="recurringList" class="stack-list"></div>
                </div>
            </section>
        </main>
    </div>

    <dialog id="transactionModal" class="modal">
        <form id="transactionForm" method="dialog" class="form-grid">
            <input type="hidden" name="id">
            <h2>Lancamento</h2>
            <label>Tipo<select name="type"><option value="expense">Despesa</option><option value="income">Receita</option><option value="transfer">Transferencia</option></select></label>
            <label>Valor<input name="amount" inputmode="decimal" placeholder="150,00" required></label>
            <label class="wide">Descricao<input name="description" required></label>
            <label>Categoria<select name="category_id" data-categories></select></label>
            <label>Conta<select name="account_id" data-accounts></select></label>
            <label>Vencimento<input name="due_date" type="date"></label>
            <label>Status<select name="status"><option value="pending">Pendente</option><option value="paid">Pago</option><option value="late">Atrasado</option><option value="ignored">Ignorado</option></select></label>
            <label>Responsavel<input name="owner" placeholder="Daniel, Fran..."></label>
            <label class="wide">Boleto / Pix<textarea name="payment_code" rows="3"></textarea></label>
            <label class="wide">Notas<textarea name="notes" rows="2"></textarea></label>
            <label class="check-row"><input name="is_fixed" type="checkbox"> Fixo mensal</label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>

    <dialog id="transactionHistoryModal" class="modal">
        <div class="form-grid compact">
            <h2 id="transactionHistoryTitle">Historico do lancamento</h2>
            <div id="transactionHistoryBody" class="wide"></div>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Fechar</button></div>
        </div>
    </dialog>

    <dialog id="categoryModal" class="modal">
        <form id="categoryForm" method="dialog" class="form-grid compact">
            <input type="hidden" name="id">
            <h2 id="categoryFormTitle">Nova categoria</h2>
            <label>Nome<input name="name" required></label>
            <label>Cor<input name="color" type="color" value="#2563eb"></label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>

    <dialog id="budgetModal" class="modal">
        <form id="budgetForm" method="dialog" class="form-grid compact">
            <input type="hidden" name="id">
            <h2 id="budgetFormTitle">Novo orcamento</h2>
            <label>Mes<input name="month" type="month" value="<?= date('Y-m') ?>" required></label>
            <label>Categoria<select name="category_id" data-categories required></select></label>
            <label>Limite<input name="limit_amount" inputmode="decimal" required></label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>

    <dialog id="goalModal" class="modal">
        <form id="goalForm" method="dialog" class="form-grid compact">
            <h2>Meta</h2>
            <label>Nome<input name="name" required></label>
            <label>Objetivo<input name="target_amount" inputmode="decimal" required></label>
            <label>Atual<input name="current_amount" inputmode="decimal" value="0"></label>
            <label>Data alvo<input name="target_date" type="date"></label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>

    <dialog id="accountModal" class="modal">
        <form id="accountForm" method="dialog" class="form-grid compact">
            <input type="hidden" name="id">
            <h2 id="accountFormTitle">Nova conta</h2>
            <label>Nome<input name="name" required></label>
            <label>Tipo<select name="type"><option value="corrente">Corrente</option><option value="credito">Cartao</option><option value="investimento">Investimento</option><option value="dinheiro">Dinheiro</option></select></label>
            <label>Saldo inicial<input name="opening_balance" inputmode="decimal" value="0"></label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>

    <dialog id="accountHistoryModal" class="modal">
        <div class="form-grid compact">
            <h2 id="accountHistoryTitle">Historico da conta</h2>
            <div id="accountHistoryBody" class="wide"></div>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Fechar</button></div>
        </div>
    </dialog>

    <dialog id="recurringModal" class="modal">
        <form id="recurringForm" method="dialog" class="form-grid compact">
            <h2>Recorrencia</h2>
            <label>Descricao<input name="description" required></label>
            <label>Valor<input name="amount" inputmode="decimal" required></label>
            <label>Categoria<select name="category_id" data-categories></select></label>
            <label>Frequencia<select name="frequency"><option value="monthly">Mensal</option><option value="weekly">Semanal</option><option value="yearly">Anual</option></select></label>
            <label>Proximo vencimento<input name="next_due_date" type="date"></label>
            <label class="check-row"><input name="is_active" type="checkbox" checked> Ativa</label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>
<?php endif; ?>
</body>
</html>
