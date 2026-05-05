<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require __DIR__ . '/../ficha/config/conexao.php';
require_once __DIR__ . '/../includes/app_menu.php';
require_once __DIR__ . '/../includes/system_settings.php';

date_default_timezone_set('America/Sao_Paulo');

function financeiro_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function financeiro_money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function financeiro_data_dir(): string
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function financeiro_despesas_path(): string
{
    return financeiro_data_dir() . '/finance_expenses.json';
}

function financeiro_carregar_despesas(): array
{
    $path = financeiro_despesas_path();
    if (!is_file($path)) {
        file_put_contents($path, "[]");
    }

    $dados = json_decode((string)file_get_contents($path), true);
    return is_array($dados) ? array_values(array_filter($dados, 'is_array')) : [];
}

function financeiro_salvar_despesas(array $despesas): void
{
    $path = financeiro_despesas_path();
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode(array_values($despesas), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    copy($tmp, $path);
    unlink($tmp);
}

function financeiro_redirect(array $extra = []): void
{
    $params = array_merge([
        'inicio' => $_GET['inicio'] ?? '',
        'fim' => $_GET['fim'] ?? '',
        'embed' => $_GET['embed'] ?? '',
        'v' => '20260505-financeiro',
    ], $extra);

    header('Location: financeiro.php?' . http_build_query(array_filter($params, static fn($v): bool => $v !== '')));
    exit;
}

$embedded = !empty($_GET['embed']) || !empty($_POST['embed']);
$inicio = trim((string)($_GET['inicio'] ?? date('Y-m-01')));
$fim = trim((string)($_GET['fim'] ?? date('Y-m-t')));
$valorPomada = system_pomada_unit_price();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $despesas = financeiro_carregar_despesas();

    if ($action === 'despesa_salvar') {
        $despesas[] = [
            'id' => uniqid('exp_', true),
            'descricao' => trim((string)($_POST['descricao'] ?? '')),
            'categoria' => trim((string)($_POST['categoria'] ?? 'Geral')) ?: 'Geral',
            'valor' => max(0, (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0'))),
            'data' => trim((string)($_POST['data'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
        ];
        financeiro_salvar_despesas($despesas);
        financeiro_redirect(['embed' => $embedded ? '1' : '']);
    }

    if ($action === 'despesa_excluir') {
        $id = trim((string)($_POST['id'] ?? ''));
        $despesas = array_values(array_filter($despesas, static fn(array $d): bool => (string)($d['id'] ?? '') !== $id));
        financeiro_salvar_despesas($despesas);
        financeiro_redirect(['embed' => $embedded ? '1' : '']);
    }
}

$where = 'WHERE t.data_tatuagem BETWEEN ? AND ?';
$stmt = $conn->prepare("
    SELECT
        t.id,
        t.descricao,
        t.valor,
        t.data_tatuagem,
        t.hora_inicio,
        t.status,
        t.pomadas_anestesicas,
        c.nome AS cliente_nome,
        c.telefone AS cliente_telefone
    FROM tatuagens t
    LEFT JOIN clientes c ON c.id = t.cliente_id
    $where
    ORDER BY t.data_tatuagem DESC, t.hora_inicio DESC, t.id DESC
");
$stmt->bind_param('ss', $inicio, $fim);
$stmt->execute();
$sessoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$despesas = array_values(array_filter(financeiro_carregar_despesas(), static function (array $despesa) use ($inicio, $fim): bool {
    $data = (string)($despesa['data'] ?? '');
    return $data >= $inicio && $data <= $fim;
}));

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=financeiro_tatuagem.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['tipo', 'data', 'descricao', 'cliente', 'status', 'pomadas', 'valor'], ';');
    foreach ($sessoes as $sessao) {
        fputcsv($out, [
            'sessao',
            $sessao['data_tatuagem'],
            $sessao['descricao'],
            $sessao['cliente_nome'],
            $sessao['status'],
            (int)($sessao['pomadas_anestesicas'] ?? 0),
            number_format((float)$sessao['valor'], 2, ',', ''),
        ], ';');
    }
    foreach ($despesas as $despesa) {
        fputcsv($out, [
            'despesa',
            $despesa['data'] ?? '',
            $despesa['descricao'] ?? '',
            $despesa['categoria'] ?? '',
            '',
            '',
            number_format((float)($despesa['valor'] ?? 0), 2, ',', ''),
        ], ';');
    }
    fclose($out);
    exit;
}

$faturamento = 0.0;
$recebido = 0.0;
$aReceber = 0.0;
$cancelado = 0.0;
$receitaPomadas = 0.0;
$porStatus = [];
$porMes = [];

foreach ($sessoes as $sessao) {
    $valor = (float)($sessao['valor'] ?? 0);
    $status = (string)($sessao['status'] ?? 'agendado');
    $pomadas = (int)($sessao['pomadas_anestesicas'] ?? 0);
    $mes = substr((string)$sessao['data_tatuagem'], 0, 7);
    $receitaPomadas += $pomadas * $valorPomada;

    $porStatus[$status] = ($porStatus[$status] ?? 0) + $valor;
    $porMes[$mes] = ($porMes[$mes] ?? 0) + $valor;

    if ($status === 'cancelado') {
        $cancelado += $valor;
        continue;
    }

    $faturamento += $valor;
    if ($status === 'concluido' || $status === 'confirmado') {
        $recebido += $valor;
    } else {
        $aReceber += $valor;
    }
}

$totalDespesas = array_reduce($despesas, static fn(float $sum, array $d): float => $sum + (float)($d['valor'] ?? 0), 0.0);
$lucroEstimado = $faturamento - $totalDespesas;
$ticketMedio = count($sessoes) > 0 ? $faturamento / max(1, count(array_filter($sessoes, static fn(array $s): bool => ($s['status'] ?? '') !== 'cancelado'))) : 0.0;
$csvUrl = 'financeiro.php?' . http_build_query(['inicio' => $inicio, 'fim' => $fim, 'export' => 'csv']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/crm-theme.css?v=20260505-financeiro">
    <style>
        .finance-layout { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, .8fr); gap: 16px; }
        .finance-embedded > .crm-header, .finance-embedded .crm-sidebar { display: none; }
        .finance-embedded .crm-workspace { display: block; min-height: auto; border: 0; background: transparent; }
        .finance-embedded .crm-main { padding: 0; }
        .finance-bar { height: 8px; border-radius: 8px; background: rgba(255,255,255,.07); overflow: hidden; }
        .finance-bar span { display: block; height: 100%; background: linear-gradient(90deg,#ef4444,#b91420); }
        @media (max-width: 1180px) { .finance-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="crm-shell">
<div class="crm-page <?= $embedded ? 'finance-embedded' : '' ?>">
    <header class="crm-header">
        <div>
            <div class="crm-eyebrow"><i class="fa-solid fa-wallet"></i> Numeros que importam</div>
            <h1 class="crm-title">Tela de <strong>Financeiro</strong></h1>
            <p class="crm-subtitle">Faturamento, contas a receber, despesas, pomadas e sessoes por periodo.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="crm-button" href="index.php"><i class="fa-solid fa-comments"></i> Atendimento</a>
            <a class="crm-button" href="<?= financeiro_h($csvUrl) ?>"><i class="fa-solid fa-file-export"></i> Exportar</a>
        </div>
    </header>

    <div class="crm-workspace">
        <aside class="crm-sidebar">
            <div class="crm-brand">
                <span class="crm-brand-name">Daniel <span>Tatuador</span></span>
                <small>Dinheiro, agenda e margem</small>
            </div>
            <nav class="crm-nav" aria-label="Menu CRM">
                <a href="dashboard.php"><i class="fa-solid fa-chart-simple"></i> Dashboard</a>
                <a href="index.php"><i class="fa-solid fa-comments"></i> Atendimento</a>
                <a class="is-active" href="financeiro.php"><i class="fa-solid fa-wallet"></i> Financeiro</a>
                <a href="automacao.php"><i class="fa-solid fa-robot"></i> Automacao</a>
                <a href="../ficha/agenda/"><i class="fa-regular fa-calendar"></i> Agenda</a>
                <a href="relatorios.php"><i class="fa-solid fa-chart-line"></i> Relatorios</a>
                <a href="configuracoes.php"><i class="fa-solid fa-gear"></i> Configuracoes</a>
            </nav>
        </aside>

        <main class="crm-main">
            <form class="crm-panel p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <?php if ($embedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                <input class="crm-input" type="date" name="inicio" value="<?= financeiro_h($inicio) ?>">
                <input class="crm-input" type="date" name="fim" value="<?= financeiro_h($fim) ?>">
                <button class="crm-button crm-button-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
                <a class="crm-button" href="<?= financeiro_h($csvUrl) ?>"><i class="fa-solid fa-file-export"></i> CSV</a>
            </form>

            <section class="crm-grid-4 mb-4">
                <div class="crm-card p-4"><div class="crm-muted text-sm">Faturamento</div><div class="text-3xl font-black mt-1"><?= financeiro_money($faturamento) ?></div></div>
                <div class="crm-card p-4"><div class="crm-muted text-sm">Recebido estimado</div><div class="text-3xl font-black mt-1 text-green-400"><?= financeiro_money($recebido) ?></div></div>
                <div class="crm-card p-4"><div class="crm-muted text-sm">A receber</div><div class="text-3xl font-black mt-1 text-amber-300"><?= financeiro_money($aReceber) ?></div></div>
                <div class="crm-card p-4"><div class="crm-muted text-sm">Lucro estimado</div><div class="text-3xl font-black mt-1 text-red-400"><?= financeiro_money($lucroEstimado) ?></div></div>
            </section>

            <section class="finance-layout">
                <div class="space-y-4">
                    <section class="crm-panel">
                        <div class="crm-panel-header">
                            <h2 class="crm-panel-title"><i class="fa-solid fa-calendar-check"></i> Sessoes do periodo</h2>
                            <div class="crm-muted text-sm"><?= count($sessoes) ?> registros</div>
                        </div>
                        <div class="p-4 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="crm-muted"><tr><th class="text-left py-2">Data</th><th class="text-left py-2">Cliente</th><th class="text-left py-2">Status</th><th class="text-right py-2">Pomadas</th><th class="text-right py-2">Valor</th></tr></thead>
                                <tbody>
                                <?php foreach ($sessoes as $sessao): ?>
                                    <tr class="border-t border-white/10">
                                        <td class="py-3"><?= financeiro_h(date('d/m/Y', strtotime((string)$sessao['data_tatuagem']))) ?></td>
                                        <td class="py-3"><?= financeiro_h($sessao['cliente_nome'] ?: 'Sem cliente') ?><div class="crm-muted text-xs"><?= financeiro_h($sessao['descricao']) ?></div></td>
                                        <td class="py-3"><span class="crm-status"><?= financeiro_h($sessao['status']) ?></span></td>
                                        <td class="py-3 text-right"><?= (int)($sessao['pomadas_anestesicas'] ?? 0) ?></td>
                                        <td class="py-3 text-right font-black"><?= financeiro_money((float)$sessao['valor']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="crm-panel">
                        <div class="crm-panel-header">
                            <h2 class="crm-panel-title"><i class="fa-solid fa-chart-column"></i> Por status</h2>
                        </div>
                        <div class="p-4 space-y-3">
                            <?php foreach ($porStatus as $status => $valor): ?>
                                <div>
                                    <div class="flex justify-between text-sm mb-2"><span><?= financeiro_h($status) ?></span><strong><?= financeiro_money((float)$valor) ?></strong></div>
                                    <div class="finance-bar"><span style="width: <?= $faturamento > 0 ? min(100, ((float)$valor / $faturamento) * 100) : 0 ?>%"></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <div class="space-y-4">
                    <section class="crm-panel p-5">
                        <h2 class="crm-panel-title mb-4"><i class="fa-solid fa-spray-can-sparkles"></i> Pomadas</h2>
                        <div class="crm-card p-4 mb-3"><div class="crm-muted text-sm">Valor unitario</div><div class="text-2xl font-black"><?= financeiro_money($valorPomada) ?></div></div>
                        <div class="crm-card p-4"><div class="crm-muted text-sm">Receita no periodo</div><div class="text-2xl font-black text-green-400"><?= financeiro_money($receitaPomadas) ?></div></div>
                    </section>

                    <section class="crm-panel p-5">
                        <h2 class="crm-panel-title mb-4"><i class="fa-solid fa-receipt"></i> Despesas</h2>
                        <form method="post" class="space-y-3 mb-4">
                            <input type="hidden" name="action" value="despesa_salvar">
                            <?php if ($embedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                            <input class="crm-input" name="descricao" required placeholder="Ex: tintas, luvas, trafego">
                            <div class="grid grid-cols-2 gap-3">
                                <input class="crm-input" name="categoria" placeholder="Categoria">
                                <input class="crm-input" type="date" name="data" value="<?= date('Y-m-d') ?>">
                            </div>
                            <input class="crm-input" name="valor" type="number" step="0.01" min="0" placeholder="Valor">
                            <button class="crm-button crm-button-primary w-full" type="submit"><i class="fa-solid fa-plus"></i> Lancar despesa</button>
                        </form>
                        <div class="crm-card p-4 mb-3"><div class="crm-muted text-sm">Total despesas</div><div class="text-2xl font-black text-red-300"><?= financeiro_money($totalDespesas) ?></div></div>
                        <div class="space-y-2">
                            <?php foreach ($despesas as $despesa): ?>
                                <div class="crm-card p-3 flex items-center justify-between gap-3">
                                    <div><strong><?= financeiro_h($despesa['descricao'] ?? '') ?></strong><div class="crm-muted text-xs"><?= financeiro_h($despesa['categoria'] ?? '') ?> · <?= financeiro_h($despesa['data'] ?? '') ?></div></div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="despesa_excluir">
                                        <input type="hidden" name="id" value="<?= financeiro_h($despesa['id'] ?? '') ?>">
                                        <?php if ($embedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                                        <button class="crm-button" title="Excluir" type="submit"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </section>
        </main>
    </div>
</div>
</body>
</html>
