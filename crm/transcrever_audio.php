<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$messageId = trim((string)($data['messageId'] ?? ''));
$mediaUrl = trim((string)($data['mediaUrl'] ?? ''));
$model = trim((string)($data['model'] ?? 'base'));

if ($messageId === '' && $mediaUrl === '') {
    echo json_encode(['ok' => false, 'error' => 'Mensagem nao informada'], JSON_UNESCAPED_UNICODE);
    exit;
}

$clientesPath = __DIR__ . '/data/clientes.json';
$clientes = file_exists($clientesPath) ? json_decode(file_get_contents($clientesPath), true) : [];
if (!is_array($clientes)) {
    $clientes = [];
}

$found = null;
foreach ($clientes as $clienteIndex => $cliente) {
    foreach (($cliente['mensagens'] ?? []) as $msgIndex => $msg) {
        $sameMessage = $messageId !== '' && (($msg['messageId'] ?? '') === $messageId);
        $sameMedia = $mediaUrl !== '' && (($msg['mediaUrl'] ?? '') === $mediaUrl);
        if ($sameMessage || $sameMedia) {
            $found = [$clienteIndex, $msgIndex, $msg];
            break 2;
        }
    }
}

if (!$found) {
    echo json_encode(['ok' => false, 'error' => 'Audio nao encontrado'], JSON_UNESCAPED_UNICODE);
    exit;
}

[$clienteIndex, $msgIndex, $msg] = $found;

if (!empty($msg['transcricao'])) {
    echo json_encode(['ok' => true, 'text' => $msg['transcricao'], 'cached' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$relativePath = $msg['mediaUrl'] ?? '';
$audioPath = realpath(__DIR__ . '/' . $relativePath);
$mediaRoot = realpath(__DIR__ . '/data/media');

if (!$audioPath || !$mediaRoot || strpos($audioPath, $mediaRoot) !== 0 || !is_file($audioPath)) {
    echo json_encode(['ok' => false, 'error' => 'Arquivo de audio invalido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$script = __DIR__ . '/scripts/transcribe_audio.py';
$commands = [
    'py -3',
    'python',
    'python3',
];

$result = null;
$lastOutput = '';

foreach ($commands as $cmd) {
    $fullCommand = $cmd . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($audioPath) . ' ' . escapeshellarg($model) . ' 2>&1';
    $output = shell_exec($fullCommand);
    $lastOutput = $output ?: '';
    $decoded = json_decode(trim($lastOutput), true);

    if (is_array($decoded)) {
        $result = $decoded;
        if (!empty($decoded['ok'])) {
            break;
        }
    }
}

if (empty($result['ok'])) {
    echo json_encode([
        'ok' => false,
        'error' => $result['error'] ?? trim($lastOutput) ?: 'Falha ao transcrever audio',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$text = trim((string)($result['text'] ?? ''));
$clientes[$clienteIndex]['mensagens'][$msgIndex]['transcricao'] = $text;
$clientes[$clienteIndex]['mensagens'][$msgIndex]['transcrito_em'] = date('Y-m-d H:i:s');
file_put_contents($clientesPath, json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['ok' => true, 'text' => $text], JSON_UNESCAPED_UNICODE);
