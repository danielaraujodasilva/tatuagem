<?php
require_once __DIR__ . '/data_store.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');
set_time_limit(0);

function responderJson($payload) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $erro = error_get_last();
    if (!$erro || !in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    logTranscricao([
        'fatal' => true,
        'message' => $erro['message'] ?? '',
        'file' => $erro['file'] ?? '',
        'line' => $erro['line'] ?? '',
    ]);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Erro fatal na transcricao: ' . ($erro['message'] ?? 'erro desconhecido'),
    ], JSON_UNESCAPED_UNICODE);
});

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

function comandoDisponivel($funcao) {
    if (!function_exists($funcao)) {
        return false;
    }

    $desabilitadas = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array($funcao, $desabilitadas, true);
}

function executarComando($command) {
    if (comandoDisponivel('exec')) {
        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);
        return [
            'exitCode' => $exitCode,
            'output' => implode(PHP_EOL, $output),
            'runner' => 'exec',
        ];
    }

    if (comandoDisponivel('shell_exec')) {
        $output = shell_exec($command);
        return [
            'exitCode' => $output === null ? 1 : 0,
            'output' => (string)$output,
            'runner' => 'shell_exec',
        ];
    }

    return [
        'exitCode' => 1,
        'output' => 'exec e shell_exec estao desabilitados no PHP',
        'runner' => 'none',
    ];
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$messageId = trim((string)($data['messageId'] ?? ''));
$mediaUrl = trim((string)($data['mediaUrl'] ?? ''));
$model = trim((string)($data['model'] ?? 'tiny'));

if ($messageId === '' && $mediaUrl === '') {
    responderJson(['ok' => false, 'error' => 'Mensagem nao informada']);
}

$clientes = crmCarregarClientes();

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
    responderJson(['ok' => false, 'error' => 'Audio nao encontrado']);
}

[$clienteIndex, $msgIndex, $msg] = $found;

if (!empty($msg['transcricao'])) {
    responderJson(['ok' => true, 'text' => $msg['transcricao'], 'cached' => true]);
}

$relativePath = $msg['mediaUrl'] ?? '';
$audioPath = realpath(__DIR__ . '/' . $relativePath);
$mediaRoot = realpath(__DIR__ . '/data/media');

if (!$audioPath || !$mediaRoot || strpos($audioPath, $mediaRoot) !== 0 || !is_file($audioPath)) {
    logTranscricao(['error' => 'Arquivo de audio invalido', 'mediaUrl' => $relativePath, 'audioPath' => $audioPath]);
    responderJson(['ok' => false, 'error' => 'Arquivo de audio invalido']);
}

$script = __DIR__ . '/scripts/transcribe_audio.py';
$commands = [
    'py -3',
    'python',
    'python3',
];
$engines = [
    'openai',
    'faster',
];

$result = null;
$lastOutput = '';
$lastError = '';
$lastExitCode = null;
$lastRunner = '';

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
        . ' -af ' . escapeshellarg('apad=pad_dur=1')
        . ' -ar 16000 -ac 1 -vn ' . escapeshellarg($convertedCandidate)
        . ' > ' . escapeshellarg($ffmpegOut)
        . ' 2> ' . escapeshellarg($ffmpegErr);

    logTranscricao(['ffmpegCommand' => $ffmpegCommand]);
    $ffmpegRun = executarComando($ffmpegCommand);
    $ffmpegExitCode = $ffmpegRun['exitCode'];
    $ffmpegStdout = is_file($ffmpegOut) ? file_get_contents($ffmpegOut) : '';
    $ffmpegStderr = is_file($ffmpegErr) ? file_get_contents($ffmpegErr) : '';
    logTranscricao([
        'ffmpegExitCode' => $ffmpegExitCode,
        'ffmpegStdout' => mb_substr($ffmpegStdout, 0, 1200),
        'ffmpegStderr' => mb_substr($ffmpegStderr, 0, 2000),
        'runner' => $ffmpegRun['runner'] ?? '',
        'runnerOutput' => mb_substr($ffmpegRun['output'] ?? '', 0, 1200),
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

foreach ($engines as $engine) {
    foreach ($commands as $cmd) {
        $stdoutFile = tempnam(sys_get_temp_dir(), 'whisper_out_');
        $stderrFile = tempnam(sys_get_temp_dir(), 'whisper_err_');
        $fullCommand = $cmd . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($audioForWhisper) . ' ' . escapeshellarg($model) . ' ' . escapeshellarg($engine)
            . ' > ' . escapeshellarg($stdoutFile)
            . ' 2> ' . escapeshellarg($stderrFile);
        logTranscricao(['command' => $fullCommand, 'engine' => $engine]);
        $run = executarComando($fullCommand);
        $exitCode = $run['exitCode'];
        $lastExitCode = $exitCode;
        $lastRunner = $run['runner'] ?? '';
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
            'runner' => $lastRunner,
            'stdout' => mb_substr($lastOutput, 0, 4000),
            'stderr' => mb_substr($lastError, 0, 4000),
            'runnerOutput' => mb_substr($run['output'] ?? '', 0, 4000),
        ]);
        $decoded = jsonFromOutput($lastOutput);

        if (is_array($decoded)) {
            $result = $decoded;
            if (!empty($decoded['ok'])) {
                break 2;
            }
        }
    }
}

if ($convertedAudio && is_file($convertedAudio)) {
    unlink($convertedAudio);
}

if (empty($result['ok'])) {
    $erro = $result['error'] ?? trim($lastError) ?: trim($lastOutput) ?: ('Falha ao transcrever audio. Runner: ' . $lastRunner . '. Exit code: ' . (string)$lastExitCode);
    logTranscricao(['error' => $erro]);
    $clientes[$clienteIndex]['mensagens'][$msgIndex]['transcricao_erro'] = $erro;
    $clientes[$clienteIndex]['mensagens'][$msgIndex]['transcrito_em'] = date('Y-m-d H:i:s');
    crmSalvarClientes($clientes);
    responderJson([
        'ok' => false,
        'error' => $erro,
    ]);
}

$text = trim((string)($result['text'] ?? ''));
$clientes[$clienteIndex]['mensagens'][$msgIndex]['transcricao'] = $text;
$clientes[$clienteIndex]['mensagens'][$msgIndex]['transcrito_em'] = date('Y-m-d H:i:s');
crmSalvarClientes($clientes);

responderJson(['ok' => true, 'text' => $text, 'engine' => $result['engine'] ?? '']);
