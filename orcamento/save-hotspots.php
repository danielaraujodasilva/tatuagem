<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Metodo nao permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['frente']) || !isset($data['costas']) || !is_array($data['frente']) || !is_array($data['costas'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'JSON invalido ou incompleto']);
    exit;
}

$dir = __DIR__;
$file = $dir . '/hotspots.json';
$backupDir = $dir . '/backups-hotspots';

if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}

if (file_exists($file)) {
    @copy($file, $backupDir . '/hotspots-' . date('Ymd-His') . '.json');
}

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
        'erro' => 'Nao foi possivel escrever em hotspots.json',
        'diagnostico' => [
            'arquivo' => $file,
            'diretorio_existe' => is_dir($dir),
            'diretorio_gravavel' => is_writable($dir),
            'arquivo_existe' => file_exists($file),
            'arquivo_gravavel' => file_exists($file) ? is_writable($file) : null,
            'usuario_php' => function_exists('get_current_user') ? get_current_user() : 'indefinido'
        ]
    ]);
    exit;
}

@chmod($file, 0664);

echo json_encode([
    'ok' => true,
    'arquivo' => 'hotspots.json',
    'bytes' => $written,
    'salvo_em' => date('Y-m-d H:i:s')
]);
