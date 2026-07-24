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
    <link rel="stylesheet" href="assets/app-bills-total.css?v=20260724-8">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js" defer></script>
    <script>
        window.PLAN_BOOT = {
            authenticated: <?= $user ? 'true' : 'false' ?>,
            csrf: <?= json_encode($csrf) ?>
        };
    </script>
    <script src="assets/app-bills-total.js?v=20260724-8" defer></script>
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
            <nav aria-label="Navegacao principal">
                <div class="nav-group">
                    <p class="nav-group-label">Visao geral</p>
                    <button class="nav-item active" data-section="dashboard">Painel</button>
                    <button class="nav-item" data-section="categoryAnalysis">Analise</button>
                </div>
                <div class="nav-group">
                    <p class="nav-group-label">Acompanhar</p>
                    <button class="nav-item" data-section="bills">Contas do mes</button>
                    <button class="nav-item" data-section="movements">Extratos</button>
                    <button class="nav-item" data-section="reconciliation">Conciliacao</button>
                </div>
                <div class="nav-group">
                    <p class="nav-group-label">Importar dados</p>
                    <button class="nav-item" data-section="transactions">Importar planilha</button>
                    <button class="nav-item" data-section="banking">Importar extratos</button>
                </div>
                <div class="nav-group">
                    <p class="nav-group-label">Planejar</p>
                    <button class="nav-item" data-section="budgets">Orcamentos</button>
                    <button class="nav-item" data-section="goals">Metas</button>
                    <button class="nav-item" data-section="recurring">Recorrencias</button>
                </div>
                <div class="nav-group">
                    <p class="nav-group-label">Configurar</p>
                    <button class="nav-item" data-section="accounts">Contas/Caixas</button>
                    <button class="nav-item" data-section="categories">Categorias</button>
                </div>
            </nav>
            <a class="logout" href="logout.php">Sair</a>
        </aside>

        <main class="workspace">
            <header class="topbar">
                <div>
                    <p class="eyebrow" id="pageKicker">Visao geral</p>
                    <h1 id="pageTitle">Seu dinheiro em ordem</h1>
                    <p class="topbar-description" id="pageDescription">Veja o que entrou, saiu e precisa da sua atencao no mes selecionado.</p>
                </div>
                <div class="top-actions">
                    <label class="month-control">
                        <span>Mes de referencia</span>
                        <input id="monthFilter" type="month" value="<?= date('Y-m') ?>">
                    </label>
                    <div class="top-actions-row">
                        <button class="ghost-btn" id="refreshBtn">Atualizar</button>
                        <button class="primary-btn" data-open-modal="transactionModal">Novo lancamento</button>
                    </div>
                </div>
            </header>

            <section class="section is-visible" id="dashboard">
                <div class="kpi-grid">
                    <article class="metric-card"><span>Receitas</span><strong id="kpiIncome">R$ 0,00</strong></article>
                    <article class="metric-card danger"><span>Despesas</span><strong id="kpiExpenses">R$ 0,00</strong></article>
                    <article class="metric-card success"><span>Pago</span><strong id="kpiPaid">R$ 0,00</strong></article>
                    <article class="metric-card warning"><span>Falta pagar</span><strong id="kpiPending">R$ 0,00</strong></article>
                </div>

                <div class="workflow-strip">
                    <button class="workflow-card" data-nav-target="transactions">
                        <span>1. Planilha</span>
                        <strong id="sheetFlowCount">0 lancamentos</strong>
                        <small>Base de contas, vencimentos e categorias</small>
                    </button>
                    <button class="workflow-card" data-nav-target="movements">
                        <span>2. Extratos</span>
                        <strong id="bankFlowCount">0 movimentacoes</strong>
                        <small>Pagamentos reais importados dos bancos</small>
                    </button>
                    <button class="workflow-card action" data-nav-target="reconciliation">
                        <span>3. Conciliacao</span>
                        <strong id="matchFlowCount">0 pendencias</strong>
                        <small>Veja o que bateu e o que precisa revisar</small>
                    </button>
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
                            <button class="small-btn" data-nav-target="goals">Gerenciar</button>
                        </div>
                        <div id="goalsList" class="stack-list"></div>
                    </section>
                </div>
            </section>

            <section class="section" id="categoryAnalysis">
                <div class="section-intro">
                    <div>
                        <p class="eyebrow">Tabela dinamica</p>
                        <h2>Analise por categoria</h2>
                        <p>Compare gastos e ganhos sem perder o detalhe. Abra uma categoria para ver valor, percentual e cada linha que compoe o total.</p>
                    </div>
                </div>

                <div class="panel analysis-filters">
                    <select id="analysisSourceFilter">
                        <option value="bank">Extratos bancarios</option>
                        <option value="transactions">Contas e planilha</option>
                        <option value="combined">Consolidado: extratos + contas</option>
                    </select>
                    <select id="analysisDirectionFilter">
                        <option value="both">Gastos e ganhos</option>
                        <option value="expense">Somente gastos</option>
                        <option value="income">Somente ganhos</option>
                    </select>
                    <select id="analysisCategoryFilter"><option value="">Todas categorias</option></select>
                    <input id="analysisMinAmount" inputmode="decimal" placeholder="Valor minimo">
                    <input id="analysisMaxAmount" inputmode="decimal" placeholder="Valor maximo">
                    <select id="analysisGroupSort">
                        <option value="value_desc">Categorias: maior valor</option>
                        <option value="value_asc">Categorias: menor valor</option>
                        <option value="name_asc">Categorias: A-Z</option>
                        <option value="name_desc">Categorias: Z-A</option>
                        <option value="count_desc">Categorias: mais itens</option>
                    </select>
                    <select id="analysisRowSort">
                        <option value="date_desc">Linhas: mais recentes</option>
                        <option value="date_asc">Linhas: mais antigas</option>
                        <option value="value_desc">Linhas: maior valor</option>
                        <option value="value_asc">Linhas: menor valor</option>
                        <option value="description_asc">Linhas: descricao A-Z</option>
                    </select>
                    <input id="analysisSearchInput" placeholder="Buscar categoria, descricao, banco ou origem">
                    <div class="analysis-actions">
                        <button type="button" class="ghost-btn" data-pivot-toggle="open" data-pivot-scope="all">Abrir tudo</button>
                        <button type="button" class="ghost-btn" data-pivot-toggle="close" data-pivot-scope="all">Fechar tudo</button>
                    </div>
                </div>

                <div class="kpi-grid compact-kpis">
                    <article class="metric-card danger"><span>Gastos filtrados</span><strong id="analysisExpenseTotal">R$ 0,00</strong></article>
                    <article class="metric-card success"><span>Ganhos filtrados</span><strong id="analysisIncomeTotal">R$ 0,00</strong></article>
                    <article class="metric-card warning"><span>Saldo filtrado</span><strong id="analysisNetTotal">R$ 0,00</strong></article>
                    <article class="metric-card"><span>Maior categoria</span><strong id="analysisTopCategory">-</strong></article>
                </div>

                <p class="section-hint">Dica: use o consolidado para investigar; para tomar decisoes, prefira separar o dinheiro real do planejamento.</p>

                <div class="analysis-grid">
                    <section class="panel">
                        <div class="panel-head wrap">
                            <div>
                                <h2>Gastos por categoria</h2>
                                <span id="analysisExpenseCount">0 categorias</span>
                            </div>
                            <div class="pivot-actions">
                                <button type="button" class="small-btn" data-pivot-toggle="open" data-pivot-scope="expense">Abrir gastos</button>
                                <button type="button" class="small-btn" data-pivot-toggle="close" data-pivot-scope="expense">Fechar gastos</button>
                            </div>
                        </div>
                        <div id="expenseCategoryPivot" class="pivot-list"></div>
                    </section>
                    <section class="panel">
                        <div class="panel-head wrap">
                            <div>
                                <h2>Ganhos por categoria</h2>
                                <span id="analysisIncomeCount">0 categorias</span>
                            </div>
                            <div class="pivot-actions">
                                <button type="button" class="small-btn" data-pivot-toggle="open" data-pivot-scope="income">Abrir ganhos</button>
                                <button type="button" class="small-btn" data-pivot-toggle="close" data-pivot-scope="income">Fechar ganhos</button>
                            </div>
                        </div>
                        <div id="incomeCategoryPivot" class="pivot-list"></div>
                    </section>
                </div>
            </section>

            <section class="section" id="bills">
                <div class="section-intro">
                    <div>
                        <p class="eyebrow">Contas do mes</p>
                        <h2>Pagas e pendentes</h2>
                        <p>Esta e a tela principal para controlar boletos, pix, mensalidades e contas da planilha no mes selecionado no topo.</p>
                    </div>
                    <button class="primary-btn" data-open-modal="transactionModal">Nova conta</button>
                </div>

                <div class="kpi-grid compact-kpis">
                    <article class="metric-card"><span>Total do mes</span><strong id="billsMonthTotal">R$ 0,00</strong></article>
                    <article class="metric-card success"><span>Pagas</span><strong id="billsPaidTotal">R$ 0,00</strong></article>
                    <article class="metric-card warning"><span>Pendentes</span><strong id="billsPendingTotal">R$ 0,00</strong></article>
                    <article class="metric-card"><span>Quantidade</span><strong id="billsCount">0</strong></article>
                    <article class="metric-card danger"><span>Atrasadas</span><strong id="billsLateCount">0</strong></article>
                </div>

                <div class="panel bills-filters">
                    <div class="filter-heading">
                        <div>
                            <strong>Encontre uma conta</strong>
                            <span>Filtre sem sair do mes selecionado</span>
                        </div>
                        <button type="button" class="link-btn" id="clearBillsFilters">Limpar filtros</button>
                    </div>
                    <input id="billsSearchInput" placeholder="Buscar conta, responsavel ou origem">
                    <select id="billsStatusFilter">
                        <option value="">Todos os status</option>
                        <option value="pending">Pendentes</option>
                        <option value="paid">Pagas</option>
                        <option value="late">Atrasadas</option>
                    </select>
                    <select id="billsCategoryFilter"><option value="">Todas as categorias</option></select>
                    <select id="billsOwnerFilter"><option value="">Todos os responsaveis</option></select>
                </div>

                <div class="bill-board">
                    <section class="panel bill-column">
                        <div class="panel-head">
                            <h2>Pendentes</h2>
                            <span id="pendingBillsCount">0 contas</span>
                        </div>
                        <div id="pendingBillsList" class="bill-list"></div>
                    </section>
                    <section class="panel bill-column">
                        <div class="panel-head">
                            <h2>Pagas</h2>
                            <span id="paidBillsCount">0 contas</span>
                        </div>
                        <div id="paidBillsList" class="bill-list"></div>
                    </section>
                </div>
            </section>

            <section class="section" id="movements">
                <div class="section-intro">
                    <div>
                        <p class="eyebrow">Extrato consolidado</p>
                        <h2>Transacoes categorizadas</h2>
                        <p>Use esta tela para entender para onde o dinheiro foi: periodo, banco, categoria, entradas, saidas e itens conciliados.</p>
                    </div>
                    <button class="primary-btn" data-nav-target="banking">Importar extrato</button>
                </div>

                <div class="panel movement-filters">
                    <input id="movementDateFrom" type="date" value="<?= date('Y-m-01') ?>">
                    <input id="movementDateTo" type="date" value="<?= date('Y-m-t') ?>">
                    <select id="movementBankFilter"><option value="">Todos bancos</option></select>
                    <select id="movementCategoryFilter"><option value="">Todas categorias</option></select>
                    <select id="movementDirectionFilter">
                        <option value="">Entradas e saidas</option>
                        <option value="debit">Saidas</option>
                        <option value="credit">Entradas</option>
                    </select>
                    <select id="movementMatchFilter">
                        <option value="">Todos status</option>
                        <option value="yes">Conciliadas</option>
                        <option value="no">Sem conciliacao</option>
                    </select>
                    <input id="movementSearchInput" list="movementSearchOptions" placeholder="Buscar descricao, banco ou tipo">
                    <datalist id="movementSearchOptions"></datalist>
                    <button type="button" class="secondary-btn" id="clearMovementFilters">Limpar filtros</button>
                </div>

                <div class="kpi-grid banking-kpis">
                    <article class="metric-card"><span>Entradas filtradas</span><strong id="movementCredits">R$ 0,00</strong></article>
                    <article class="metric-card danger"><span>Saidas filtradas</span><strong id="movementDebits">R$ 0,00</strong></article>
                    <article class="metric-card warning"><span>Saldo do periodo</span><strong id="movementNet">R$ 0,00</strong></article>
                    <article class="metric-card success"><span>Conciliadas</span><strong id="movementMatched">0</strong></article>
                </div>

                <div class="movement-grid">
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Resumo por categoria</h2>
                            <span>Filtro atual</span>
                        </div>
                        <div id="movementCategorySummary" class="source-list"></div>
                    </section>
                    <section class="panel wide-panel">
                        <div class="panel-head wrap">
                            <h2>Transacoes</h2>
                            <span id="movementRowsCount">0 linhas</span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Banco</th>
                                        <th>Descricao</th>
                                        <th>Categoria</th>
                                        <th>Status</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>
                                <tbody id="categorizedBankBody"></tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>

            <section class="section" id="reconciliation">
                <div class="section-intro">
                    <div>
                        <p class="eyebrow">Depois da importacao</p>
                        <h2>Central de uso dos dados</h2>
                        <p>Aqui fica claro para onde cada importacao foi e qual e o proximo passo: revisar contas da planilha, conferir pagamentos encontrados no extrato e resolver itens sem conciliacao.</p>
                    </div>
                    <button class="primary-btn" data-nav-target="banking">Importar novo extrato</button>
                </div>

                <div class="kpi-grid compact-kpis">
                    <article class="metric-card"><span>Da planilha neste mes</span><strong id="reconSheetRows">0</strong></article>
                    <article class="metric-card success"><span>Pagos na planilha</span><strong id="reconPaidRows">0</strong></article>
                    <article class="metric-card warning"><span>Pendentes na planilha</span><strong id="reconPendingRows">0</strong></article>
                    <article class="metric-card danger"><span>Extrato sem match</span><strong id="reconUnmatchedRows">0</strong></article>
                </div>

                <div class="recon-grid">
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Origem dos lancamentos</h2>
                            <span>Mes selecionado</span>
                        </div>
                        <div id="sourceBreakdown" class="source-list"></div>
                    </section>
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Fila de revisao</h2>
                            <span>Acao sugerida</span>
                        </div>
                        <div id="reviewQueue" class="stack-list"></div>
                    </section>
                    <section class="panel wide-panel">
                        <div class="panel-head wrap">
                            <div>
                                <h2>Movimentacoes bancarias sem conciliacao</h2>
                                <span>Use para descobrir pagamentos que ainda nao existem ou nao bateram com a planilha</span>
                            </div>
                            <button class="ghost-btn" data-nav-target="banking">Ver extratos</button>
                        </div>
                        <div id="unmatchedBankList" class="bank-match-list"></div>
                    </section>
                </div>
            </section>

            <section class="section" id="transactions">
                <div class="panel sheet-import-panel">
                    <div>
                        <p class="eyebrow">Carga completa</p>
                        <h2>Importar planilha original do Google Sheets</h2>
                        <p>Exporte a planilha como XLSX e envie aqui. O sistema lê todas as abas, ignora Resumo, usa o nome da aba como mês de referência e substitui a carga anterior vinda da planilha.</p>
                    </div>
                    <label class="mini-upload">
                        <input id="sheetWorkbookInput" type="file" accept=".xlsx,.xls">
                        <strong>Selecionar XLSX</strong>
                        <span id="sheetImportStatus">Nenhum arquivo selecionado</span>
                    </label>
                </div>
                <div class="panel">
                    <div class="panel-head wrap">
                        <h2>Lancamentos</h2>
                        <div class="filters">
                            <input id="searchInput" placeholder="Buscar descricao, pix ou boleto">
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
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Vencimento</th>
                                    <th>Descricao</th>
                                    <th>Origem</th>
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

            <section class="section" id="banking">
                <div class="bank-hero panel">
                    <div>
                        <p class="eyebrow">Importacao bancaria</p>
                        <h2>Extratos PagBank e Santander no mesmo lugar</h2>
                        <p>Suba arquivos `.xlsx` ou `.xls`, revise a pre-visualizacao e salve. O sistema identifica o banco, evita duplicados e tenta marcar lancamentos pendentes como pagos.</p>
                    </div>
                    <label class="upload-zone" id="bankUploadZone">
                        <input id="bankFileInput" type="file" accept=".xlsx,.xls,.csv" multiple>
                        <strong>Selecionar extratos</strong>
                        <span>PagBank `.xlsx`, Santander `.xls` ou arquivos parecidos</span>
                    </label>
                </div>

                <div class="kpi-grid banking-kpis">
                    <article class="metric-card"><span>Entradas importadas</span><strong id="bankCredits">R$ 0,00</strong></article>
                    <article class="metric-card danger"><span>Saidas importadas</span><strong id="bankDebits">R$ 0,00</strong></article>
                    <article class="metric-card success"><span>Conciliados</span><strong id="bankMatched">0</strong></article>
                    <article class="metric-card warning"><span>Ultimo saldo</span><strong id="bankLatestBalance">R$ 0,00</strong></article>
                </div>

                <section class="panel" id="bankPreviewPanel" hidden>
                    <div class="panel-head wrap">
                        <div>
                            <h2>Previa da importacao</h2>
                            <span id="bankPreviewSummary">0 movimentacoes</span>
                        </div>
                        <div class="filters">
                            <select id="bankPreviewAccount"></select>
                            <button class="ghost-btn" id="clearBankPreview">Limpar</button>
                            <button class="primary-btn" id="saveBankImport">Salvar importacao</button>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Banco</th>
                                    <th>Data</th>
                                    <th>Descricao</th>
                                    <th>Tipo</th>
                                    <th>Direcao</th>
                                    <th>Valor</th>
                                    <th>Saldo</th>
                                </tr>
                            </thead>
                            <tbody id="bankPreviewBody"></tbody>
                        </table>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-head wrap">
                        <h2>Movimentacoes bancarias</h2>
                        <div class="filters">
                            <input id="bankSearchInput" placeholder="Buscar no extrato">
                            <select id="bankFilter"><option value="">Todos bancos</option></select>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Banco</th>
                                    <th>Data</th>
                                    <th>Descricao</th>
                                    <th>Categoria</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody id="bankTransactionsBody"></tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section class="section" id="accounts">
                <section class="panel">
                    <div class="panel-head">
                        <h2>Contas/Caixas</h2>
                        <button class="small-btn" data-open-modal="accountModal">Nova</button>
                    </div>
                    <div id="accountsList" class="stack-list"></div>
                </section>
            </section>

            <section class="section" id="categories">
                <section class="panel">
                    <div class="panel-head">
                        <h2>Categorias</h2>
                        <button class="small-btn" data-open-modal="categoryModal">Nova</button>
                    </div>
                    <div id="categoriesList" class="chip-list"></div>
                </section>
            </section>

            <section class="section" id="budgets">
                <section class="panel">
                    <div class="panel-head">
                        <h2>Orcamentos mensais</h2>
                        <button class="small-btn" data-open-modal="budgetModal">Adicionar</button>
                    </div>
                    <div id="budgetsList" class="stack-list"></div>
                </section>
            </section>

            <section class="section" id="goals">
                <section class="panel">
                    <div class="panel-head">
                        <h2>Metas</h2>
                        <button class="small-btn" data-open-modal="goalModal">Nova</button>
                    </div>
                    <div id="goalsManageList" class="stack-list"></div>
                </section>
            </section>

            <section class="section" id="recurring">
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
            <input type="hidden" name="id">
            <h2 id="goalFormTitle">Nova meta</h2>
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
            <h2 id="accountFormTitle">Nova conta/caixa</h2>
            <label>Nome<input name="name" required></label>
            <label>Tipo<select name="type"><option value="corrente">Corrente</option><option value="credito">Cartao</option><option value="investimento">Investimento</option><option value="dinheiro">Dinheiro</option></select></label>
            <label>Saldo inicial<input name="opening_balance" inputmode="decimal" value="0"></label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>

    <dialog id="recurringModal" class="modal">
        <form id="recurringForm" method="dialog" class="form-grid compact">
            <input type="hidden" name="id">
            <h2 id="recurringFormTitle">Nova recorrencia</h2>
            <label>Descricao<input name="description" required></label>
            <label>Valor<input name="amount" inputmode="decimal" required></label>
            <label>Categoria<select name="category_id" data-categories></select></label>
            <label>Frequencia<select name="frequency"><option value="monthly">Mensal</option><option value="weekly">Semanal</option><option value="yearly">Anual</option></select></label>
            <label>Proximo vencimento<input name="next_due_date" type="date"></label>
            <label class="check-row"><input name="is_active" type="checkbox" checked> Ativa</label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>

    <dialog id="shareModal" class="modal">
        <div class="form-grid compact">
            <h2>Compartilhar item</h2>
            <p id="shareSummary" class="share-summary wide"></p>
            <label class="wide">Mensagem opcional para quem abrir
                <textarea id="shareNote" rows="3" placeholder="Ex.: Fran, olha essa conta de aluguel: ela ainda esta pendente."></textarea>
            </label>
            <label class="wide">Link gerado
                <input id="shareUrl" readonly placeholder="Clique em Gerar link para criar">
            </label>
            <p id="shareMessage" class="form-message wide"></p>
            <div class="modal-actions">
                <button type="button" class="ghost-btn" data-close>Fechar</button>
                <button type="button" class="ghost-btn" id="copyShareLink">Copiar link</button>
                <button type="button" class="primary-btn" id="createShareLink">Gerar link</button>
            </div>
        </div>
    </dialog>
<?php endif; ?>
</body>
</html>
