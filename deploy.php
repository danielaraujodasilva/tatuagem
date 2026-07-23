<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    exit;
}

$localConfig = __DIR__ . '/deploy.local.php';
$deployConfig = is_file($localConfig) ? require $localConfig : [];
if (!is_array($deployConfig)) {
    $deployConfig = [];
}

$logDir = (string)($deployConfig['log_dir'] ?? getenv('DEPLOY_LOG_DIR') ?: __DIR__ . '/storage/logs');
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/deploy.log';

function deploy_log(string $message, array $context = []): void
{
    global $logFile;
    $line = date('Y-m-d H:i:s') . ' ' . $message;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function request_headers_lower(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (!$headers) {
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
    }

    $normalized = [];
    foreach ($headers as $key => $value) {
        $normalized[strtolower((string)$key)] = (string)$value;
    }

    return $normalized;
}

function respond_json(array $payload, int $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function run_deploy_command(string $command): array
{
    $lines = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $lines, $exitCode);

    return [
        'command' => preg_replace('/\s+/', ' ', $command),
        'exit_code' => $exitCode,
        'output' => implode("\n", $lines),
    ];
}

$headers = request_headers_lower();
$delivery = $headers['x-github-delivery'] ?? bin2hex(random_bytes(6));
$event = $headers['x-github-event'] ?? 'unknown';
$payload = file_get_contents('php://input') ?: '';

deploy_log('received', [
    'delivery' => $delivery,
    'event' => $event,
    'content_length' => strlen($payload),
]);

$secret = getenv('DEPLOY_WEBHOOK_SECRET') ?: (string)($deployConfig['secret'] ?? '');
if ($secret === '') {
    deploy_log('missing_secret', ['delivery' => $delivery]);
    respond_json(['ok' => false, 'message' => 'Deploy nao configurado. Defina DEPLOY_WEBHOOK_SECRET ou deploy.local.php.'], 500);
}

$signature = $headers['x-hub-signature-256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expected, $signature)) {
    deploy_log('invalid_signature', ['delivery' => $delivery]);
    respond_json(['ok' => false, 'message' => 'Assinatura invalida.'], 403);
}

if ($event === 'ping') {
    deploy_log('ping_ok', ['delivery' => $delivery]);
    respond_json(['ok' => true, 'message' => 'pong']);
}

$data = json_decode($payload, true);
$branch = (string)($deployConfig['branch'] ?? getenv('DEPLOY_BRANCH') ?: 'main');
$expectedRef = 'refs/heads/' . $branch;
$ref = is_array($data) ? (string)($data['ref'] ?? '') : '';

if ($event !== 'push' || $ref !== $expectedRef) {
    deploy_log('ignored_event', ['delivery' => $delivery, 'event' => $event, 'ref' => $ref]);
    respond_json(['ok' => true, 'message' => 'Evento ignorado.', 'event' => $event, 'ref' => $ref], 202);
}

$configuredPath = getenv('DEPLOY_PATH') ?: (string)($deployConfig['path'] ?? __DIR__);
$deployPath = realpath($configuredPath);
if (!$deployPath || !is_dir($deployPath)) {
    deploy_log('invalid_path', ['delivery' => $delivery, 'path' => $configuredPath]);
    respond_json(['ok' => false, 'message' => 'DEPLOY_PATH invalido.'], 500);
}

$lockFile = $logDir . '/deploy.lock';
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    deploy_log('locked', ['delivery' => $delivery]);
    respond_json(['ok' => false, 'message' => 'Deploy ja em andamento.'], 409);
}

$git = (string)($deployConfig['git_bin'] ?? getenv('DEPLOY_GIT_BIN') ?: 'git');
$gitCmd = escapeshellarg($git);
$pathArg = escapeshellarg($deployPath);
$branchArg = escapeshellarg($branch);
$remoteRefArg = escapeshellarg('origin/' . $branch);

$commands = [
    $gitCmd . ' -C ' . $pathArg . ' rev-parse --is-inside-work-tree',
    $gitCmd . ' -C ' . $pathArg . ' fetch --prune origin ' . $branchArg,
    $gitCmd . ' -C ' . $pathArg . ' checkout ' . $branchArg,
    $gitCmd . ' -C ' . $pathArg . ' reset --hard ' . $remoteRefArg,
    $gitCmd . ' -C ' . $pathArg . ' rev-parse --short HEAD',
];

$results = [];
$failed = false;
foreach ($commands as $command) {
    $result = run_deploy_command($command);
    $results[] = $result;
    deploy_log('command', [
        'delivery' => $delivery,
        'exit_code' => $result['exit_code'],
        'command' => $result['command'],
        'output' => $result['output'],
    ]);

    if ($result['exit_code'] !== 0) {
        $failed = true;
        break;
    }
}

clearstatcache(true);
if (!$failed && function_exists('opcache_reset')) {
    opcache_reset();
}

flock($lock, LOCK_UN);
fclose($lock);

if ($failed) {
    respond_json(['ok' => false, 'message' => 'Deploy falhou. Veja storage/logs/deploy.log.', 'results' => $results], 500);
}

$head = trim((string)($results[count($results) - 1]['output'] ?? ''));
deploy_log('success', ['delivery' => $delivery, 'head' => $head]);
respond_json(['ok' => true, 'message' => 'Deploy atualizado.', 'head' => $head, 'results' => $results]);
