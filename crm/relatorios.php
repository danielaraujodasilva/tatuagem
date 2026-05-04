<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require __DIR__ . '/config.php';
require_once __DIR__ . '/data_store.php';
require_once __DIR__ . '/../includes/app_menu.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money_br($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function date_br($value): string
{
    if (!$value) {
        return '-';
    }

    $time = strtotime((string)$value);
    return $time ? date('d/m/Y', $time) : '-';
}

function normalize_filter(string $key): string
{
    return trim((string)($_GET[$key] ?? ''));
}

function push_group(array &$groups, string $key, float $value): void
{
    $key = trim($key) !== '' ? trim($key) : 'Nao informado';
    if (!isset($groups[$key])) {
        $groups[$key] = ['qtd' => 0, 'valor' => 0.0];
    }
    $groups[$key]['qtd']++;
    $groups[$key]['valor'] += $value;
}

function match_text_filter(array $lead, string $q): bool
{
    if ($q === '') {
        return true;
    }

    $haystack = strtolower(implode(' ', [
        $lead['nome'] ?? '',
        $lead['telefone'] ?? '',
        $lead['interesse'] ?? '',
        $lead['origem'] ?? '',
        $lead['status'] ?? '',
        $lead['atendente'] ?? '',
    ]), 'UTF-8');

    return strpos($haystack, strtolower($q)) !== false;
}

function match_date_range(?string $date, string $inicio, string $fim): bool
{
    if (!$date) {
        return $inicio === '' && $fim === '';
    }

    $day = substr($date, 0, 10);
    if ($inicio !== '' && $day < $inicio) {
        return false;
    }
    if ($fim !== '' && $day > $fim) {
        return false;
    }

    return true;
}

$stages = [];
try {
    $stageRows = $conn->query('SELECT id, nome FROM pipelines ORDER BY ordem, id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stageRows as $stage) {
        $stages[(string)$stage['id']] = (string)$stage['nome'];
    }
} catch (Throwable $e) {
    $stages = [];
}

$filters = [
    'inicio' => normalize_filter('inicio'),
    'fim' => normalize_filter('fim'),
    'etapa' => normalize_filter('etapa'),
    'origem' => normalize_filter('origem'),
    'status' => normalize_filter('status'),
    'atendente' => normalize_filter('atendente'),
    'valor_min' => normalize_filter('valor_min'),
    'valor_max' => normalize_filter('valor_max'),
    'q' => normalize_filter('q'),
];

$where = [];
$params = [];
if ($filters['inicio'] !== '') {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $filters['inicio'];
}
if ($filters['fim'] !== '') {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $filters['fim'];
}
if ($filters['etapa'] !== '') {
    $where[] = 'etapa_funil = ?';
    $params[] = $filters['etapa'];
}
if ($filters['origem'] !== '') {
    $where[] = 'origem = ?';
    $params[] = $filters['origem'];
}
if ($filters['status'] !== '') {
    $where[] = 'status = ?';
    $params[] = $filters['status'];
}
if ($filters['valor_min'] !== '') {
    $where[] = 'valor >= ?';
    $params[] = (float)str_replace(',', '.', $filters['valor_min']);
}
if ($filters['valor_max'] !== '') {
    $where[] = 'valor <= ?';
    $params[] = (float)str_replace(',', '.', $filters['valor_max']);
}
if ($filters['q'] !== '') {
    $where[] = '(nome LIKE ? OR telefone LIKE ? OR interesse LIKE ? OR origem LIKE ? OR status LIKE ?)';
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$sql = 'SELECT id, nome, telefone, interesse, valor, origem, status, etapa_funil AS etapa, data_ultimo_contato, created_at FROM leads';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC, id DESC';

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leads = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $row['id'] = (string)$row['id'];
    $row['tipo'] = 'Lead';
    $row['atendente'] = '';
    $row['etapa_nome'] = $stages[(string)($row['etapa'] ?? '')] ?? (string)($row['etapa'] ?? 'Nao informado');
    $leads[] = $row;
}

foreach (crmCarregarClientes() as $cliente) {
    $lead = [
        'id' => 'wa_' . ($cliente['id'] ?? ''),
        'tipo' => 'WhatsApp',
        'nome' => $cliente['nome'] ?? 'Cliente',
        'telefone' => $cliente['numero'] ?? '',
        'interesse' => $cliente['interesse'] ?? '',
        'valor' => (float)($cliente['valor'] ?? 0),
        'origem' => $cliente['origem'] ?? 'WhatsApp',
        'status' => $cliente['status'] ?? 'novo',
        'etapa' => (string)($cliente['etapa'] ?? ''),
        'atendente' => $cliente['atendente'] ?? '',
        'data_ultimo_contato' => $cliente['data_ultimo_contato'] ?? '',
        'created_at' => $cliente['created_at'] ?? '',
    ];
    $lead['etapa_nome'] = $stages[(string)$lead['etapa']] ?? ((string)$lead['etapa'] !== '' ? (string)$lead['etapa'] : 'Nao informado');

    if (!match_date_range($lead['created_at'], $filters['inicio'], $filters['fim'])) {
        continue;
    }
    if ($filters['etapa'] !== '' && (string)$lead['etapa'] !== $filters['etapa']) {
        continue;
    }
    if ($filters['origem'] !== '' && (string)$lead['origem'] !== $filters['origem']) {
        continue;
    }
    if ($filters['status'] !== '' && (string)$lead['status'] !== $filters['status']) {
        continue;
    }
    if ($filters['atendente'] !== '' && (string)$lead['atendente'] !== $filters['atendente']) {
        continue;
    }
    if ($filters['valor_min'] !== '' && (float)$lead['valor'] < (float)str_replace(',', '.', $filters['valor_min'])) {
        continue;
    }
    if ($filters['valor_max'] !== '' && (float)$lead['valor'] > (float)str_replace(',', '.', $filters['valor_max'])) {
        continue;
    }
    if (!match_text_filter($lead, $filters['q'])) {
        continue;
    }

    $leads[] = $lead;
}

usort($leads, static function (array $a, array $b): int {
    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
});

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_crm.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['tipo', 'nome', 'telefone', 'interesse', 'valor', 'origem', 'status', 'etapa', 'atendente', 'ultimo_contato', 'criado_em'], ';');
    foreach ($leads as $lead) {
        fputcsv($out, [
            $lead['tipo'],
            $lead['nome'],
            $lead['telefone'],
            $lead['interesse'],
            number_format((float)$lead['valor'], 2, ',', ''),
            $lead['origem'],
            $lead['status'],
            $lead['etapa_nome'],
            $lead['atendente'],
            $lead['data_ultimo_contato'],
            $lead['created_at'],
        ], ';');
    }
    exit;
}

$totalLeads = count($leads);
$valorTotal = array_reduce($leads, static fn(float $sum, array $lead): float => $sum + (float)$lead['valor'], 0.0);
$ticketMedio = $totalLeads > 0 ? $valorTotal / $totalLeads : 0;
$semContato = 0;
$frios = 0;
$muitoFrios = 0;
$ganhos = 0;
$perdidos = 0;
$porEtapa = [];
$porOrigem = [];
$porStatus = [];
$porAtendente = [];
$porMes = [];
$faixas = [
    'Ate R$ 300' => ['qtd' => 0, 'valor' => 0.0],
    'R$ 301 a R$ 700' => ['qtd' => 0, 'valor' => 0.0],
    'R$ 701 a R$ 1.500' => ['qtd' => 0, 'valor' => 0.0],
    'Acima de R$ 1.500' => ['qtd' => 0, 'valor' => 0.0],
];

$today = new DateTimeImmutable('today');
foreach ($leads as $lead) {
    $value = (float)$lead['valor'];
    push_group($porEtapa, (string)$lead['etapa_nome'], $value);
    push_group($porOrigem, (string)$lead['origem'], $value);
    push_group($porStatus, (string)$lead['status'], $value);
    push_group($porAtendente, (string)$lead['atendente'], $value);
    push_group($porMes, substr((string)($lead['created_at'] ?? ''), 0, 7) ?: 'Sem data', $value);

    if ($value <= 300) {
        $bucket = 'Ate R$ 300';
    } elseif ($value <= 700) {
        $bucket = 'R$ 301 a R$ 700';
    } elseif ($value <= 1500) {
        $bucket = 'R$ 701 a R$ 1.500';
    } else {
        $bucket = 'Acima de R$ 1.500';
    }
    $faixas[$bucket]['qtd']++;
    $faixas[$bucket]['valor'] += $value;

    $statusEtapa = strtolower((string)($lead['status'] . ' ' . $lead['etapa_nome']));
    if (strpos($statusEtapa, 'ganh') !== false || strpos($statusEtapa, 'conclu') !== false || strpos($statusEtapa, 'fech') !== false) {
        $ganhos++;
    }
    if (strpos($statusEtapa, 'perd') !== false || strpos($statusEtapa, 'cancel') !== false) {
        $perdidos++;
    }

    if (empty($lead['data_ultimo_contato'])) {
        $semContato++;
        continue;
    }
    $last = DateTimeImmutable::createFromFormat('Y-m-d', substr((string)$lead['data_ultimo_contato'], 0, 10));
    if ($last) {
        $days = (int)$last->diff($today)->format('%r%a');
        if ($days > 15) {
            $muitoFrios++;
        } elseif ($days > 7) {
            $frios++;
        }
    }
}

uasort($porEtapa, static fn(array $a, array $b): int => $b['qtd'] <=> $a['qtd']);
uasort($porOrigem, static fn(array $a, array $b): int => $b['qtd'] <=> $a['qtd']);
uasort($porStatus, static fn(array $a, array $b): int => $b['qtd'] <=> $a['qtd']);
uasort($porAtendente, static fn(array $a, array $b): int => $b['qtd'] <=> $a['qtd']);
ksort($porMes);

$origens = array_keys($porOrigem);
$statuses = array_keys($porStatus);
$atendentes = array_filter(array_keys($porAtendente), static fn($v) => $v !== 'Nao informado');
$topValor = $leads;
usort($topValor, static fn(array $a, array $b): int => (float)$b['valor'] <=> (float)$a['valor']);
$topValor = array_slice($topValor, 0, 12);
$recentes = array_slice($leads, 0, 12);
$queryString = $_GET;
$queryString['export'] = 'csv';
$csvUrl = 'relatorios.php?' . http_build_query($queryString);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatorios do CRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100">
    <main class="max-w-7xl mx-auto px-4 py-8">
        <header class="mb-8">
            <div class="flex flex-col gap-3">
                <span class="text-sm font-bold uppercase tracking-wide text-sky-300">CRM</span>
                <h1 class="text-3xl md:text-4xl font-bold">Relatorios</h1>
                <p class="max-w-3xl text-gray-400">Analise funil, origem, status, valores, contatos frios, desempenho por periodo e exporte a base filtrada.</p>
            </div>
            <?php app_menu_render('relatorios'); ?>
        </header>

        <form method="get" class="bg-gray-900 border border-gray-800 rounded-2xl p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                <input name="q" value="<?= h($filters['q']) ?>" placeholder="Buscar nome, telefone, interesse..." class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                <input type="date" name="inicio" value="<?= h($filters['inicio']) ?>" class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                <input type="date" name="fim" value="<?= h($filters['fim']) ?>" class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                <select name="etapa" class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                    <option value="">Todas as etapas</option>
                    <?php foreach ($stages as $id => $name): ?>
                        <option value="<?= h($id) ?>" <?= $filters['etapa'] === (string)$id ? 'selected' : '' ?>><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="origem" class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                    <option value="">Todas as origens</option>
                    <?php foreach ($origens as $origem): ?>
                        <option value="<?= h($origem) ?>" <?= $filters['origem'] === $origem ? 'selected' : '' ?>><?= h($origem) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                    <option value="">Todos os status</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="atendente" class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                    <option value="">Todos os atendentes</option>
                    <?php foreach ($atendentes as $atendente): ?>
                        <option value="<?= h($atendente) ?>" <?= $filters['atendente'] === $atendente ? 'selected' : '' ?>><?= h($atendente) ?></option>
                    <?php endforeach; ?>
                </select>
                <input name="valor_min" value="<?= h($filters['valor_min']) ?>" placeholder="Valor min." class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                <input name="valor_max" value="<?= h($filters['valor_max']) ?>" placeholder="Valor max." class="bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                <div class="flex gap-2">
                    <button class="flex-1 rounded-xl bg-sky-400 text-gray-950 font-bold px-4 py-3">Filtrar</button>
                    <a href="relatorios.php" class="rounded-xl bg-gray-800 border border-gray-700 font-bold px-4 py-3">Limpar</a>
                </div>
            </div>
        </form>

        <section class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <?php foreach ([
                ['Leads', number_format($totalLeads, 0, ',', '.')],
                ['Valor total', money_br($valorTotal)],
                ['Ticket medio', money_br($ticketMedio)],
                ['Ganhos', $ganhos],
                ['Perdidos', $perdidos],
                ['Sem contato', $semContato],
            ] as $card): ?>
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4">
                    <div class="text-sm text-gray-400"><?= h($card[0]) ?></div>
                    <div class="mt-2 text-2xl font-black"><?= h($card[1]) ?></div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
                <h2 class="font-bold text-lg mb-4">Funil por etapa</h2>
                <?php foreach ($porEtapa as $name => $group): ?>
                    <div class="mb-4">
                        <div class="flex justify-between text-sm"><span><?= h($name) ?></span><span><?= (int)$group['qtd'] ?> leads</span></div>
                        <div class="mt-2 h-2 rounded-full bg-gray-800"><div class="h-2 rounded-full bg-sky-400" style="width: <?= $totalLeads ? min(100, ($group['qtd'] / $totalLeads) * 100) : 0 ?>%"></div></div>
                        <div class="mt-1 text-xs text-gray-400"><?= money_br($group['valor']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
                <h2 class="font-bold text-lg mb-4">Origem dos leads</h2>
                <?php foreach (array_slice($porOrigem, 0, 10, true) as $name => $group): ?>
                    <div class="flex justify-between border-b border-gray-800 py-2 text-sm">
                        <span><?= h($name) ?></span>
                        <span class="text-gray-300"><?= (int)$group['qtd'] ?> / <?= money_br($group['valor']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
                <h2 class="font-bold text-lg mb-4">Temperatura da base</h2>
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl bg-gray-800 p-4 text-center"><div class="text-2xl font-black"><?= $frios ?></div><div class="text-xs text-gray-400">7+ dias</div></div>
                    <div class="rounded-xl bg-gray-800 p-4 text-center"><div class="text-2xl font-black"><?= $muitoFrios ?></div><div class="text-xs text-gray-400">15+ dias</div></div>
                    <div class="rounded-xl bg-gray-800 p-4 text-center"><div class="text-2xl font-black"><?= $semContato ?></div><div class="text-xs text-gray-400">sem data</div></div>
                </div>
                <h3 class="font-bold mt-5 mb-2">Faixas de valor</h3>
                <?php foreach ($faixas as $name => $group): ?>
                    <div class="flex justify-between border-b border-gray-800 py-2 text-sm">
                        <span><?= h($name) ?></span>
                        <span class="text-gray-300"><?= (int)$group['qtd'] ?> leads</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 overflow-x-auto">
                <h2 class="font-bold text-lg mb-4">Maiores oportunidades</h2>
                <table class="w-full text-sm">
                    <thead class="text-gray-400"><tr><th class="text-left py-2">Lead</th><th class="text-left py-2">Etapa</th><th class="text-right py-2">Valor</th></tr></thead>
                    <tbody>
                        <?php foreach ($topValor as $lead): ?>
                            <tr class="border-t border-gray-800">
                                <td class="py-3"><?= h($lead['nome']) ?><div class="text-xs text-gray-500"><?= h($lead['origem']) ?></div></td>
                                <td class="py-3"><?= h($lead['etapa_nome']) ?></td>
                                <td class="py-3 text-right"><?= money_br($lead['valor']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 overflow-x-auto">
                <div class="flex justify-between gap-3 mb-4">
                    <h2 class="font-bold text-lg">Leads recentes</h2>
                    <a class="text-sky-300 font-bold text-sm" href="<?= h($csvUrl) ?>">Exportar CSV</a>
                </div>
                <table class="w-full text-sm">
                    <thead class="text-gray-400"><tr><th class="text-left py-2">Lead</th><th class="text-left py-2">Status</th><th class="text-left py-2">Criado</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentes as $lead): ?>
                            <tr class="border-t border-gray-800">
                                <td class="py-3"><?= h($lead['nome']) ?><div class="text-xs text-gray-500"><?= h($lead['telefone']) ?></div></td>
                                <td class="py-3"><?= h($lead['status'] ?: '-') ?></td>
                                <td class="py-3"><?= date_br($lead['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-gray-900 border border-gray-800 rounded-2xl p-5 overflow-x-auto">
            <h2 class="font-bold text-lg mb-4">Evolucao mensal</h2>
            <table class="w-full text-sm">
                <thead class="text-gray-400"><tr><th class="text-left py-2">Mes</th><th class="text-right py-2">Leads</th><th class="text-right py-2">Valor</th><th class="text-right py-2">Ticket medio</th></tr></thead>
                <tbody>
                    <?php foreach ($porMes as $month => $group): ?>
                        <tr class="border-t border-gray-800">
                            <td class="py-3"><?= h($month) ?></td>
                            <td class="py-3 text-right"><?= (int)$group['qtd'] ?></td>
                            <td class="py-3 text-right"><?= money_br($group['valor']) ?></td>
                            <td class="py-3 text-right"><?= money_br($group['qtd'] ? $group['valor'] / $group['qtd'] : 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
