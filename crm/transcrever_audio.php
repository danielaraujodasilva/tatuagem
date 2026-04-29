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
$lastExitCode = null;

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

$audioForWhisper = $audioPath;
$convertedAudio = null;
$ffmpegOut = tempnam(sys_get_temp_dir(), 'ffmpeg_out_');
$ffmpegErr = tempnam(sys_get_temp_dir(), 'ffmpeg_err_');
$convertedCandidate = tempnam(sys_get_temp_dir(), 'whisper_wav_');
if ($convertedCandidate !== false) {
    if (is_file($convertedCandidate)) {
        unlink($convertedCandidate);
    }
    $convertedCandidate .= '.wav';
    $ffmpegCommand = 'ffmpeg -y -i ' . escapeshellarg($audioPath)
        . ' -ar 16000 -ac 1 -vn ' . escapeshellarg($convertedCandidate)
        . ' > ' . escapeshellarg($ffmpegOut)
        . ' 2> ' . escapeshellarg($ffmpegErr);

    logTranscricao(['ffmpegCommand' => $ffmpegCommand]);
    exec($ffmpegCommand, $ffmpegUnused, $ffmpegExitCode);
    $ffmpegStdout = is_file($ffmpegOut) ? file_get_contents($ffmpegOut) : '';
    $ffmpegStderr = is_file($ffmpegErr) ? file_get_contents($ffmpegErr) : '';
    logTranscricao([
        'ffmpegExitCode' => $ffmpegExitCode,
        'ffmpegStdout' => mb_substr($ffmpegStdout, 0, 1200),
        'ffmpegStderr' => mb_substr($ffmpegStderr, 0, 2000),
        'convertedAudio' => is_file($convertedCandidate) ? $convertedCandidate : '',
        'convertedSize' => is_file($convertedCandidate) ? filesize($convertedCandidate) : 0,
    ]);

    if ($ffmpegExitCode === 0 && is_file($convertedCandidate) && filesize($convertedCandidate) > 0) {
        $audioForWhisper = $convertedCandidate;
        $convertedAudio = $convertedCandidate;
    } elseif (is_file($convertedCandidate)) {
        unlink($convertedCandidate);
    }
}
if (is_file($ffmpegOut)) {
    unlink($ffmpegOut);
}
if (is_file($ffmpegErr)) {
    unlink($ffmpegErr);
}

foreach ($commands as $cmd) {
    $stdoutFile = tempnam(sys_get_temp_dir(), 'whisper_out_');
    $stderrFile = tempnam(sys_get_temp_dir(), 'whisper_err_');
    $fullCommand = $cmd . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($audioForWhisper) . ' ' . escapeshellarg($model)
        . ' > ' . escapeshellarg($stdoutFile)
        . ' 2> ' . escapeshellarg($stderrFile);
    logTranscricao(['command' => $fullCommand]);
    exec($fullCommand, $unusedOutput, $exitCode);
    $lastExitCode = $exitCode;
    $lastOutput = is_file($stdoutFile) ? file_get_contents($stdoutFile) : '';
    $lastError = is_file($stderrFile) ? file_get_contents($stderrFile) : '';
    if (is_file($stdoutFile)) {
        unlink($stdoutFile);
    }
    if (is_file($stderrFile)) {
        unlink($stderrFile);
    }
    logTranscricao([
        'exitCode' => $exitCode,
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

if ($convertedAudio && is_file($convertedAudio)) {
    unlink($convertedAudio);
}

if (empty($result['ok'])) {
    $erro = $result['error'] ?? trim($lastError) ?: trim($lastOutput) ?: ('Falha ao transcrever audio. Exit code: ' . (string)$lastExitCode);
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
