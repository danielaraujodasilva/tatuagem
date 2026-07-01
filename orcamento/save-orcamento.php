<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Metodo nao permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'JSON invalido']);
    exit;
}

function normalize_config($input) {
    $input = is_array($input) ? $input : [];
    return [
        'whatsapp' => (string)($input['whatsapp'] ?? ''),
        'cta' => (string)($input['cta'] ?? ''),
        'intro' => (string)($input['intro'] ?? ''),
    ];
}

function normalize_area($input) {
    $input = is_array($input) ? $input : [];
    $price = (float)($input['preco'] ?? $input['min'] ?? $input['max'] ?? 0);
    $price = round($price / 50) * 50;

    return [
        'titulo' => (string)($input['titulo'] ?? ''),
        'descricao' => (string)($input['descricao'] ?? $input['desc'] ?? ''),
        'desc' => (string)($input['desc'] ?? $input['descricao'] ?? ''),
        'preco' => $price,
        'min' => $price,
        'max' => $price,
        'ativa' => ($input['ativa'] ?? true) !== false,
    ];
}

function normalize_promo($input) {
    $input = is_array($input) ? $input : [];
    $discount = (float)($input['desconto'] ?? 1);
    if ($discount > 1) {
        $discount = max(0, min(80, (float)($input['descontoPercent'] ?? 0))) / 100;
    }

    return [
        'uid' => (string)($input['uid'] ?? ''),
        'titulo' => (string)($input['titulo'] ?? ''),
        'descricao' => (string)($input['descricao'] ?? $input['desc'] ?? ''),
        'desc' => (string)($input['desc'] ?? $input['descricao'] ?? ''),
        'ids' => array_values(array_filter((array)($input['ids'] ?? []), 'is_string')),
        'desconto' => $discount > 0 && $discount <= 1 ? $discount : 1,
        'view' => ($input['view'] ?? 'frente') === 'costas' ? 'costas' : 'frente',
        'ativa' => ($input['ativa'] ?? true) !== false,
    ];
}

$state = [
    'updatedAt' => gmdate('c'),
    'config' => normalize_config($data['config'] ?? []),
    'areas' => [],
    'promos' => [],
];

if (!isset($data['areas']) || !is_array($data['areas'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Campo areas invalido']);
    exit;
}

if (!isset($data['promos']) || !is_array($data['promos'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Campo promos invalido']);
    exit;
}

foreach ($data['areas'] as $key => $value) {
    $state['areas'][(string)$key] = normalize_area($value);
}

foreach ($data['promos'] as $value) {
    $state['promos'][] = normalize_promo($value);
}

$dir = __DIR__;
$file = $dir . '/orcamento-data.json';
$backupDir = $dir . '/backups-orcamento';

if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}

if (file_exists($file)) {
    @copy($file, $backupDir . '/orcamento-' . date('Ymd-His') . '.json');
}

$json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Falha ao converter JSON']);
    exit;
}

$written = @file_put_contents($file, $json, LOCK_EX);

if ($written === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'erro' => 'Nao foi possivel escrever em orcamento-data.json',
        'diagnostico' => [
            'arquivo' => $file,
            'diretorio_existe' => is_dir($dir),
            'diretorio_gravavel' => is_writable($dir),
            'arquivo_existe' => file_exists($file),
            'arquivo_gravavel' => file_exists($file) ? is_writable($file) : null,
        ]
    ]);
    exit;
}

@chmod($file, 0664);

echo json_encode([
    'ok' => true,
    'arquivo' => 'orcamento-data.json',
    'bytes' => $written,
    'salvo_em' => date('Y-m-d H:i:s'),
]);
