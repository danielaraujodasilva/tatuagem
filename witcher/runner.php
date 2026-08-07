<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$webRoot = __DIR__;
$runtimeDir = $webRoot . DIRECTORY_SEPARATOR . 'runtime';
$jobFile = $runtimeDir . DIRECTORY_SEPARATOR . 'job.json';
$controlFile = $runtimeDir . DIRECTORY_SEPARATOR . 'control.json';
$worker = $webRoot . DIRECTORY_SEPARATOR . 'worker.ps1';
$projectRoot = getenv('WITCHER_DUB_ROOT') ?: 'C:\\witcher-dub-br';

$blocks = [
    'prologue' => ['ready' => true, 'limit' => 100],
    'first_phase' => ['ready' => true, 'limit' => 100],
    'chapter1' => ['ready' => false],
    'chapter2' => ['ready' => false],
    'chapter3' => ['ready' => false],
    'chapter4' => ['ready' => false],
    'chapter5_epilogue' => ['ready' => false],
    'qa_packaging' => ['ready' => false],
];

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function ensureRuntime(string $runtimeDir): void
{
    if (!is_dir($runtimeDir)) {
        @mkdir($runtimeDir, 0755, true);
    }
    $deny = $runtimeDir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($deny)) {
        @file_put_contents($deny, "Require all denied\n");
    }
}

function readJsonFile(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function tailFile(string $path, int $bytes = 6000): string
{
    if ($path === '' || !is_file($path)) {
        return '';
    }
    $size = filesize($path);
    if ($size === false) {
        return '';
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return '';
    }
    fseek($handle, max(0, $size - $bytes));
    $text = (string) stream_get_contents($handle);
    fclose($handle);
    return safeOutput($text, $bytes);
}

function safeOutput(string $text, int $limit = 4000): string
{
    $text = str_replace("\0", '', $text);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, UTF-16LE, Windows-1252, ISO-8859-1');
    }
    return substr($text, 0, $limit);
}

function parsePid(string $output): int
{
    if (preg_match('/^\s*(\d{2,})/m', $output, $match) === 1) {
        return (int) $match[1];
    }
    return 0;
}

function psQuote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

function runPowerShell(string $script): string
{
    if (function_exists('mb_convert_encoding')) {
        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
    } else {
        $encoded = base64_encode((string) iconv('UTF-8', 'UTF-16LE', $script));
    }
    $cmd = 'powershell.exe -NoProfile -EncodedCommand ' . $encoded . ' 2>&1';
    return trim((string) shell_exec($cmd));
}

function isWindows(): bool
{
    return stripos(PHP_OS_FAMILY, 'Windows') !== false;
}

function processRunning(int $pid): bool
{
    if ($pid <= 0 || !isWindows()) {
        return false;
    }
    $script = '$p = Get-Process -Id ' . $pid . ' -ErrorAction SilentlyContinue; if ($p) { "1" }';
    return trim(runPowerShell($script)) === '1';
}

function currentStatus(
    string $runtimeDir,
    string $jobFile,
    string $worker,
    string $projectRoot
): array {
    ensureRuntime($runtimeDir);
    $job = readJsonFile($jobFile);
    $active = false;
    if ($job && isset($job['pid'])) {
        $active = processRunning((int) $job['pid']);
        if (!$active && (($job['status'] ?? '') === 'running')) {
            $job['status'] = 'unknown';
            $job['message'] = 'O processo nao aparece mais ativo; confira o log.';
        }
    }
    $logTail = tailFile((string) ($job['log'] ?? ''));
    $enabled = isWindows() && is_file($worker) && is_dir($projectRoot) && is_dir($projectRoot . DIRECTORY_SEPARATOR . 'scripts');
    return [
        'ok' => true,
        'enabled' => $enabled,
        'active' => $active,
        'detached' => true,
        'refresh_seconds' => 10,
        'job' => $job,
        'log_tail' => $logTail,
        'message' => $enabled ? 'Ponte local pronta. Jobs rodam em background no servidor.' : 'Este servidor nao consegue acessar C:\\witcher-dub-br ou worker.ps1.',
    ];
}

ensureRuntime($runtimeDir);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(currentStatus($runtimeDir, $jobFile, $worker, $projectRoot));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    respond(['ok' => false, 'error' => 'Metodo nao permitido.'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['ok' => false, 'error' => 'JSON invalido.'], 400);
}

$action = (string) ($input['action'] ?? '');
$block = preg_replace('/[^a-z0-9_-]/i', '', (string) ($input['block'] ?? ''));
$status = currentStatus($runtimeDir, $jobFile, $worker, $projectRoot);

if ($action === 'pause') {
    if ($block === '') {
        respond(['ok' => false, 'error' => 'Bloco ausente.'], 400);
    }
    file_put_contents($controlFile, json_encode([
        'pause_requested' => true,
        'block' => $block,
        'requested_at' => gmdate('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $status = currentStatus($runtimeDir, $jobFile, $worker, $projectRoot);
    $status['message'] = 'Pausa solicitada. O worker vai parar ao concluir a etapa atual.';
    respond($status);
}

if ($action !== 'start') {
    respond(['ok' => false, 'error' => 'Acao desconhecida.'], 400);
}

if (!$status['enabled']) {
    respond(['ok' => false, 'error' => $status['message']], 503);
}

if ($block === '' || !isset($blocks[$block])) {
    respond(['ok' => false, 'error' => 'Bloco desconhecido.'], 400);
}

if (empty($blocks[$block]['ready'])) {
    respond([
        'ok' => false,
        'error' => 'Este bloco ainda nao tem escopo seguro na pipeline local. Recusei executar para nao processar falas fora do bloco.'
    ], 409);
}

if ($status['active']) {
    respond(['ok' => false, 'error' => 'Ja existe um job rodando. Pause ou aguarde terminar.'], 409);
}

@unlink($controlFile);
$limit = (int) ($blocks[$block]['limit'] ?? 100);
$args = [
    '-NoProfile',
    '-ExecutionPolicy',
    'Bypass',
    '-File',
    $worker,
    '-BlockId',
    $block,
    '-ProjectRoot',
    $projectRoot,
    '-WebRoot',
    $webRoot,
    '-Limit',
    (string) $limit,
];
$argList = '@(' . implode(', ', array_map('psQuote', $args)) . ')';
$script = '$p = Start-Process -FilePath "powershell.exe" -ArgumentList ' . $argList . ' -PassThru -WindowStyle Hidden; $p.Id';
$output = runPowerShell($script);
$pid = parsePid($output);
if ($pid <= 0) {
    respond([
        'ok' => false,
        'error' => 'Nao foi possivel iniciar o worker PowerShell.',
        'output' => safeOutput($output),
    ], 500);
}

file_put_contents($jobFile, json_encode([
    'pid' => $pid,
    'block' => $block,
    'status' => 'running',
    'stage' => 'starting',
    'started_at' => gmdate('c'),
    'message' => 'Worker iniciado pelo painel.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

$status = currentStatus($runtimeDir, $jobFile, $worker, $projectRoot);
$status['message'] = 'Worker iniciado em background no servidor. Voce pode fechar a pagina sem parar o job.';
respond($status);
