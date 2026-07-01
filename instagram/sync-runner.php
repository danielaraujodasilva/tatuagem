<?php
/**
 * Runner interno para executar o sync demorado fora da requisição do navegador/Cloudflare.
 * Não é para chamar no front; quem chama isso é o sync-start.php em background.
 */

@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$lockFile = $cacheDir . '/sync.lock';
$statusFile = $cacheDir . '/sync-status.json';
$logFile = $cacheDir . '/sync.log';

$lockTtl = 60 * 60 * 2;
if (file_exists($lockFile) && (time() - filemtime($lockFile) < $lockTtl)) {
    file_put_contents($logFile, '[' . date('c') . "] Sync ignorado: já existe outro processo rodando.\n", FILE_APPEND);
    exit;
}

file_put_contents($lockFile, json_encode([
    'started_at' => date('c'),
    'pid' => getmypid(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

file_put_contents($statusFile, json_encode([
    'ok' => true,
    'running' => true,
    'started_at' => date('c'),
    'message' => 'Sincronização em andamento.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

ob_start();

register_shutdown_function(function () use ($lockFile, $statusFile, $logFile) {
    $output = ob_get_contents();
    if (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $error = error_get_last();
    $decoded = json_decode((string)$output, true);

    $status = [
        'ok' => is_array($decoded) ? (bool)($decoded['ok'] ?? false) : false,
        'running' => false,
        'finished_at' => date('c'),
        'message' => 'Sincronização finalizada.',
        'result' => is_array($decoded) ? $decoded : null,
    ];

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $status['ok'] = false;
        $status['message'] = 'Erro fatal durante a sincronização.';
        $status['fatal_error'] = $error;
    }

    if (!is_array($decoded) && trim((string)$output) !== '') {
        $status['raw_output'] = mb_substr((string)$output, 0, 5000);
    }

    @file_put_contents($statusFile, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    @file_put_contents($logFile, '[' . date('c') . "]\n" . (string)$output . "\n\n", FILE_APPEND);
    @unlink($lockFile);
});

require __DIR__ . '/sync.php';
