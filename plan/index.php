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
    <link rel="stylesheet" href="assets/app-bills-total.css?v=20260816-31">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js" defer></script>
    <script>
        window.PLAN_BOOT = {
            authenticated: <?= $user ? 'true' : 'false' ?>,
            csrf: <?= json_encode($csrf) ?>
        };
    </script>
    <script src="assets/app-bills-total.js?v=20260816-31" defer></script>
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
                    <p class="nav-group-label">Principal</p>
                    <button class="nav-item active" data-section="dashboard">Inicio</button>
                    <button class="nav-item" data-section="movements">Extratos</button>
                    <button class="nav-item" data-section="bills">Contas</button>
                </div>
                <details class="nav-more">
                    <summary>Mais opcoes</summary>
                    <div class="nav-more-items">
                        <button class="nav-item" data-section="categoryAnalysis">Analise detalhada</button>
                        <button class="nav-item" data-section="reconciliation">Conciliacao</button>
                        <button class="nav-item" data-section="transactions">Importar planilha</button>
                        <button class="nav-item" data-section="budgets">Orcamentos</button>
                        <button class="nav-item" data-section="goals">Metas</button>
                        <button class="nav-item" data-section="recurring">Contas fixas</button>
                        <button class="nav-item" data-section="accounts">Contas/Caixas</button>
                        <button class="nav-item" data-section="categories">Categorias</button>
                        <a class="nav-item logout-option" href="logout.php">Sair</a>
                    </div>
                </details>
            </nav>
        </aside>

        <main class="workspace">
            <header class="topbar">
                <div>
                    <p class="eyebrow" id="pageKicker">Resumo</p>
                    <h1 id="pageTitle">Seu dinheiro</h1>
                    <p class="topbar-description" id="pageDescription">O essencial do periodo, sem complicacao.</p>
                </div>
                <div class="top-actions">
                    <div class="period-control" aria-label="Periodo de analise">
                        <span class="period-label">Periodo</span>
                        <div class="period-quick-toggle" role="group" aria-label="Periodo rapido">
                            <button type="button" class="is-active" data-period-quick="yesterday">Ontem</button>
                            <button type="button" data-period-quick="month">Este mes</button>
                        </div>
                        <details class="period-more">
                            <summary>Outro periodo</summary>
                            <div class="period-more-fields">
                                <label>
                                    <span>Atalho</span>
                                    <select id="periodPreset">
                                        <option value="yesterday" selected>Ontem</option>
                                        <option value="month">Este mes</option>
                                        <option value="30">Ultimos 30 dias</option>
                                        <option value="90">Ultimos 90 dias</option>
                                        <option value="365">Ultimos 365 dias</option>
                                        <option value="custom">Personalizado</option>
                                    </select>
                                </label>
                                <label class="period-custom-date"><span>De</span><input id="periodDateFrom" type="date" value="<?= date('Y-m-d', strtotime('-1 day')) ?>"></label>
                                <label class="period-custom-date"><span>Ate</span><input id="periodDateTo" type="date" value="<?= date('Y-m-d', strtotime('-1 day')) ?>"></label>
                            </div>
                        </details>
                    </div>
                    <div class="top-actions-row">
                        <button class="ghost-btn" id="refreshBtn">Atualizar</button>
                    </div>
                </div>
            </header>

            <section class="section is-visible" id="dashboard">
                <details class="money-summary">
                    <summary class="money-balance">
                        <span>Saldo do periodo</span>
                        <strong id="dashboardBalance">R$ 0,00</strong>
                    </summary>
                    <div class="money-flow-list">
                        <details class="money-flow positive">
                            <summary class="money-flow-head">
                                <span>Entrou</span>
                                <strong id="dashboardIncome">R$ 0,00</strong>
                            </summary>
                            <div class="money-flow-content">
                                <div class="money-flow-breakdown-head">
                                    <div><p class="eyebrow">Como ganhou</p><h2>Origem das entradas</h2></div>
                                    <button class="link-btn" data-nav-target="categoryAnalysis">Detalhes</button>
                                </div>
                                <div id="dashboardIncomeBreakdown" class="money-breakdown-list"></div>
                            </div>
                        </details>
                        <details class="money-flow negative">
                            <summary class="money-flow-head">
                                <span>Saiu</span>
                                <strong id="dashboardExpenses">R$ 0,00</strong>
                            </summary>
                            <div class="money-flow-content">
                                <div class="money-flow-breakdown-head">
                                    <div><p class="eyebrow">Como gastou</p><h2>Destino das saidas</h2></div>
                                    <button class="link-btn" data-nav-target="categoryAnalysis">Detalhes</button>
                                </div>
                                <div id="dashboardExpenseBreakdown" class="money-breakdown-list"></div>
                            </div>
                        </details>
                    </div>
                </details>

                <section class="panel fixed-coverage">
                    <div class="fixed-coverage-head">
                        <div>
                            <p class="eyebrow">Contas fixas</p>
                            <h2>Quanto das contas fixas ja foi pago</h2>
                            <p id="fixedCoverageMeta">Acompanhe o que ja foi quitado neste mes.</p>
                        </div>
                        <div class="fixed-coverage-value">
                            <strong id="fixedCoveragePercent">0%</strong>
                            <span id="fixedCoverageRemaining">Faltam R$ 0,00</span>
                            <button type="button" class="small-btn" data-nav-target="recurring">Ver contas fixas</button>
                        </div>
                    </div>
                    <div class="coverage-track" aria-label="Cobertura das contas fixas">
                        <span id="fixedCoverageBar"></span>
                    </div>
                </section>

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
                    <select id="analysisCategoryParentFilter" aria-label="Categoria principal"><option value="">Todas as categorias</option></select>
                    <select id="analysisCategoryFilter" aria-label="Subcategoria" hidden disabled><option value="">Todas as subcategorias</option></select>
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
                        <button type="button" class="primary-btn" id="copyAnalysisTable">Copiar print para WhatsApp</button>
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
                        <p class="eyebrow">Contas do periodo</p>
                        <h2>Pagas e pendentes</h2>
                        <p>Esta e a tela principal para controlar boletos, pix, mensalidades e contas da planilha no periodo selecionado no topo.</p>
                    </div>
                    <button class="primary-btn" data-open-modal="transactionModal">Nova conta</button>
                </div>

                <div class="kpi-grid compact-kpis">
                    <article class="metric-card"><span>Total do periodo</span><strong id="billsMonthTotal">R$ 0,00</strong></article>
                    <article class="metric-card success"><span>Pagas</span><strong id="billsPaidTotal">R$ 0,00</strong></article>
                    <article class="metric-card warning"><span>Pendentes</span><strong id="billsPendingTotal">R$ 0,00</strong></article>
                    <article class="metric-card"><span>Quantidade</span><strong id="billsCount">0</strong></article>
                    <article class="metric-card danger"><span>Atrasadas</span><strong id="billsLateCount">0</strong></article>
                </div>

                <div class="panel bills-filters">
                    <div class="filter-heading">
                        <div>
                            <strong>Encontre uma conta</strong>
                            <span>Filtre sem sair do periodo selecionado</span>
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
                    <select id="billsCategoryParentFilter" aria-label="Categoria principal"><option value="">Todas as categorias</option></select>
                    <select id="billsCategoryFilter" aria-label="Subcategoria" hidden disabled><option value="">Todas as subcategorias</option></select>
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
                </div>

                <div class="panel movement-filters">
                    <select id="movementBankFilter"><option value="">Todos bancos</option></select>
                    <select id="movementCategoryParentFilter" aria-label="Categoria principal"><option value="">Todas as categorias</option></select>
                    <select id="movementCategoryFilter" aria-label="Subcategoria" hidden disabled><option value="">Todas as subcategorias</option></select>
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
                    <div class="movement-search-field">
                        <input id="movementSearchInput" autocomplete="off" placeholder="Buscar descricao, banco ou tipo">
                        <div id="movementSearchOptions" class="movement-search-options" role="listbox" hidden></div>
                    </div>
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
                            <div>
                                <h2 id="movementViewTitle">Transacoes semelhantes</h2>
                                <span id="movementRowsCount">0 linhas</span>
                            </div>
                            <div class="segmented-control" role="group" aria-label="Modo de visualizacao das transacoes">
                                <button type="button" class="is-active" data-movement-view="grouped">Agrupadas</button>
                                <button type="button" data-movement-view="list">Lista</button>
                            </div>
                        </div>
                        <div id="similarTransactionsView" class="similar-groups"></div>
                        <div class="table-wrap" id="movementListView" hidden>
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
                        <p class="eyebrow">Conferencia automatica</p>
                        <h2>Confira o planejado contra o realizado</h2>
                        <p>Conciliacao e a conferencia entre o que estava previsto na planilha e o que realmente apareceu no banco. O sistema tenta encontrar o par; voce revisa apenas o que ficou diferente ou sem resposta.</p>
                    </div>
                </div>

                <div class="kpi-grid compact-kpis">
                    <article class="metric-card"><span>Contas da planilha</span><strong id="reconSheetRows">0</strong></article>
                    <article class="metric-card success"><span>Ja marcadas como pagas</span><strong id="reconPaidRows">0</strong></article>
                    <article class="metric-card warning"><span>Ainda pendentes</span><strong id="reconPendingRows">0</strong></article>
                    <article class="metric-card danger"><span>Movimentos do banco sem correspondencia</span><strong id="reconUnmatchedRows">0</strong></article>
                </div>

                <section class="panel recon-guide">
                    <div class="recon-guide-head">
                        <div>
                            <p class="eyebrow">Sem complicacao</p>
                            <h2>O que fazer nesta pagina</h2>
                            <p>Conciliar nao e cadastrar uma conta nova. E confirmar se uma conta planejada foi paga e se o lancamento que apareceu no banco esta explicado no sistema.</p>
                        </div>
                        <span class="guide-badge">3 passos</span>
                    </div>
                    <div class="recon-steps">
                        <article class="recon-step">
                            <span class="recon-step-number">1</span>
                            <div>
                                <strong>Importe os dois lados</strong>
                                <p>A planilha mostra o que deveria acontecer. O extrato mostra o que aconteceu de verdade.</p>
                            </div>
                        </article>
                        <article class="recon-step">
                            <span class="recon-step-number">2</span>
                            <div>
                                <strong>Deixe o sistema procurar os pares</strong>
                                <p>Ele compara data, valor e descricao para tentar ligar uma conta a um movimento bancario.</p>
                            </div>
                        </article>
                        <article class="recon-step">
                            <span class="recon-step-number">3</span>
                            <div>
                                <strong>Resolva somente os alertas</strong>
                                <p>Abra a fila, confira o detalhe, categorize quando necessario e marque como pago apenas se tiver certeza.</p>
                            </div>
                        </article>
                    </div>
                    <div class="recon-legend">
                        <strong>Como ler os indicadores:</strong>
                        <span><b class="status paid">Pago</b> a conta ja foi marcada como paga.</span>
                        <span><b class="status pending">Pendente</b> ainda falta confirmar o pagamento.</span>
                        <span><b class="status ignored">Sem correspondencia</b> o banco trouxe algo que precisa ser entendido.</span>
                    </div>
                </section>

                <div class="recon-grid">
                    <section class="panel">
                        <div class="panel-head">
                            <h2>Origem dos lancamentos</h2>
                            <span>Periodo selecionado</span>
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
                                <h2>Movimentos do banco que precisam de voce</h2>
                                <span>Abra cada item, veja se reconhece a movimentacao e decida se ela deve virar uma conta, transferencia ou categoria.</span>
                            </div>
                            <button class="ghost-btn" data-nav-target="movements">Ver extratos</button>
                        </div>
                        <div id="unmatchedBankList" class="bank-match-list"></div>
                    </section>
                </div>

                <section class="panel recon-goals-panel">
                    <div class="panel-head wrap">
                        <div>
                            <h2>Depois de conferir, acompanhe suas metas</h2>
                            <span>Use o que foi confirmado para enxergar se voce esta avancando no que quer construir.</span>
                        </div>
                        <button class="small-btn" data-nav-target="goals">Gerenciar metas</button>
                    </div>
                    <div id="reconGoalsList" class="recon-goals-list"></div>
                </section>
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
                        <div>
                            <h2>Categorias e subcategorias</h2>
                            <span>Exemplo: Estudio / Marketing, Estudio / Insumos</span>
                        </div>
                        <button class="small-btn" data-open-modal="categoryModal">Nova</button>
                    </div>
                    <p class="section-hint">Crie uma categoria principal e, quando precisar de mais detalhe, escolha essa categoria no campo "Categoria principal".</p>
                    <div id="categoriesList" class="category-tree"></div>
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
                    <div class="panel-head wrap">
                        <div>
                            <h2>Contas fixas do mes</h2>
                            <span id="recurringMonthLabel"><?= date('m/Y') ?></span>
                        </div>
                        <div class="row-actions">
                            <select id="recurringLocationFilter" aria-label="Filtrar contas fixas por local">
                                <option value="">Todos os locais</option>
                                <option value="apartment">Apartamento</option>
                                <option value="studio">Estudio</option>
                                <option value="other">Outras</option>
                            </select>
                            <button class="small-btn" data-open-modal="recurringModal">Nova regra</button>
                        </div>
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
            <label class="wide">Categoria principal<select name="parent_id" data-category-parents></select></label>
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
            <input type="hidden" name="source_bank_transaction_id">
            <h2 id="recurringFormTitle">Nova conta fixa</h2>
            <label id="recurringTargetField" class="wide" hidden>Vincular com<select name="target_recurring_id"></select></label>
            <label>Descricao<input name="description" required></label>
            <label>Valor<input name="amount" inputmode="decimal" required></label>
            <label>Categoria<select name="category_id" data-categories></select></label>
            <label>Frequencia<select name="frequency"><option value="monthly">Mensal</option><option value="weekly">Semanal</option><option value="yearly">Anual</option></select></label>
            <label>Proximo vencimento<input name="next_due_date" type="date"></label>
            <label class="check-row"><input name="is_active" type="checkbox" checked> Ativa</label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Salvar</button></div>
        </form>
    </dialog>

    <dialog id="mergeRecurringModal" class="modal">
        <form id="mergeRecurringForm" method="dialog" class="form-grid compact">
            <input type="hidden" name="source_id">
            <h2>Mesclar contas fixas</h2>
            <p id="mergeRecurringDescription" class="wide muted"></p>
            <label class="wide">Manter como<select name="target_id" required></select></label>
            <div class="modal-actions"><button type="button" class="ghost-btn" data-close>Cancelar</button><button class="primary-btn">Mesclar</button></div>
        </form>
    </dialog>

    <dialog id="categoryScopeModal" class="modal">
        <div class="form-grid compact">
            <h2>Alterar categoria</h2>
            <p id="categoryScopeMessage" class="wide muted"></p>
            <div class="modal-actions wide category-scope-actions">
                <button type="button" class="ghost-btn" data-category-scope="cancel">Cancelar</button>
                <button type="button" class="ghost-btn" data-category-scope="single">Somente esta</button>
                <button type="button" class="primary-btn" data-category-scope="similar">Todas parecidas</button>
            </div>
        </div>
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
