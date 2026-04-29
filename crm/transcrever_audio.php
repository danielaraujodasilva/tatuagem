<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');
set_time_limit(0);

function logTranscricao($dados) {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents(
        $dir . '/transcricao_debug.log',
        '[' . date('Y-m-d H:i:s') . '] ' . json_encode($dados, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$messageId = trim((string)($data['messageId'] ?? ''));
$mediaUrl = trim((string)($data['mediaUrl'] ?? ''));
$model = trim((string)($data['model'] ?? 'tiny'));

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
    logTranscricao(['error' => 'Arquivo de audio invalido', 'mediaUrl' => $relativePath, 'audioPath' => $audioPath]);
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
$lastError = '';

function jsonFromOutput($output) {
    $trimmed = trim($output);
    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{(?:.|\s)*\}\s*$/', $trimmed, $matches)) {
        $decoded = json_decode($matches[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

foreach ($commands as $cmd) {
    $errFile = tempnam(sys_get_temp_dir(), 'whisper_err_');
    $fullCommand = $cmd . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($audioPath) . ' ' . escapeshellarg($model) . ' 2> ' . escapeshellarg($errFile);
    logTranscricao(['command' => $fullCommand]);
    $output = shell_exec($fullCommand);
    $lastOutput = $output ?: '';
    $lastError = is_file($errFile) ? file_get_contents($errFile) : '';
    if (is_file($errFile)) {
        unlink($errFile);
    }
    logTranscricao([
        'stdout' => mb_substr($lastOutput, 0, 4000),
        'stderr' => mb_substr($lastError, 0, 4000),
    ]);
    $decoded = jsonFromOutput($lastOutput);

    if (is_array($decoded)) {
        $result = $decoded;
        if (!empty($decoded['ok'])) {
            break;
        }
    }
}

if (empty($result['ok'])) {
    $erro = $result['error'] ?? trim($lastError) ?: trim($lastOutput) ?: 'Falha ao transcrever audio';
    logTranscricao(['error' => $erro]);
    $clientes[$clienteIndex]['mensagens'][$msgIndex]['transcricao_erro'] = $erro;
    $clientes[$clienteIndex]['mensagens'][$msgIndex]['transcrito_em'] = date('Y-m-d H:i:s');
    file_put_contents($clientesPath, json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode([
        'ok' => false,
        'error' => $erro,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$text = trim((string)($result['text'] ?? ''));
$clientes[$clienteIndex]['mensagens'][$msgIndex]['transcricao'] = $text;
$clientes[$clienteIndex]['mensagens'][$msgIndex]['transcrito_em'] = date('Y-m-d H:i:s');
file_put_contents($clientesPath, json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['ok' => true, 'text' => $text], JSON_UNESCAPED_UNICODE);
