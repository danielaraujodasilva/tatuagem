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

function copy_tree(string $source, string $target): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        if (preg_match('#(^|[\\\\/])(\.git|storage)([\\\\/]|$)#', $relative)) {
            continue;
        }
        if (in_array(basename($relative), ['deploy.local.php', 'config.local.php'], true)) {
            continue;
        }

        $destination = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination)) {
                mkdir($destination, 0775, true);
            }
            continue;
        }

        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0775, true);
        }
        copy($item->getPathname(), $destination);
    }
}

function deploy_from_zip(string $repository, string $branch, string $deployPath): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'message' => 'Extensao ZipArchive indisponivel no PHP do servidor.'];
    }

    $url = 'https://codeload.github.com/' . $repository . '/zip/refs/heads/' . rawurlencode($branch);
    $tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'deploy_' . bin2hex(random_bytes(8));
    $zipPath = $tmpBase . '.zip';
    $extractPath = $tmpBase;

    $zipContent = @file_get_contents($url);
    if ($zipContent === false) {
        return ['ok' => false, 'message' => 'Nao foi possivel baixar o ZIP do GitHub.', 'url' => $url];
    }
    file_put_contents($zipPath, $zipContent);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        @unlink($zipPath);
        return ['ok' => false, 'message' => 'Nao foi possivel abrir o ZIP baixado.'];
    }

    mkdir($extractPath, 0775, true);
    $zip->extractTo($extractPath);
    $zip->close();

    $entries = glob($extractPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    $sourceRoot = $entries[0] ?? '';
    if ($sourceRoot === '' || !is_dir($sourceRoot)) {
        @unlink($zipPath);
        return ['ok' => false, 'message' => 'ZIP nao contem pasta raiz esperada.'];
    }

    copy_tree($sourceRoot, $deployPath);

    @unlink($zipPath);
    return ['ok' => true, 'message' => 'Arquivos sincronizados via ZIP.', 'source' => $url];
}

function deploy_from_powershell_zip(string $repository, string $branch, string $deployPath): array
{
    $url = 'https://codeload.github.com/' . $repository . '/zip/refs/heads/' . rawurlencode($branch);
    $tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'deploy_' . bin2hex(random_bytes(8));
    $zipPath = $tmpBase . '.zip';
    $extractPath = $tmpBase;
    $sourcePattern = $extractPath . DIRECTORY_SEPARATOR . '*';

    $script = [
        '$ErrorActionPreference = "Stop"',
        '$url = ' . var_export($url, true),
        '$zip = ' . var_export($zipPath, true),
        '$extract = ' . var_export($extractPath, true),
        '$dest = ' . var_export($deployPath, true),
        'New-Item -ItemType Directory -Force -Path $extract | Out-Null',
        '[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12',
        'Invoke-WebRequest -Uri $url -OutFile $zip',
        'Expand-Archive -Path $zip -DestinationPath $extract -Force',
        '$root = Get-ChildItem -Path $extract -Directory | Select-Object -First 1',
        'if (-not $root) { throw "ZIP sem pasta raiz" }',
        'robocopy $root.FullName $dest /E /XD .git storage /XF deploy.local.php config.local.php /R:2 /W:1 /NFL /NDL /NP',
        '$code = $LASTEXITCODE',
        'Remove-Item -LiteralPath $zip -Force -ErrorAction SilentlyContinue',
        'Remove-Item -LiteralPath $extract -Recurse -Force -ErrorAction SilentlyContinue',
        'if ($code -gt 7) { exit $code }',
        'exit 0',
    ];

    $plainScript = implode("\r\n", $script);
    if (function_exists('mb_convert_encoding')) {
        $powershellScript = mb_convert_encoding($plainScript, 'UTF-16LE', 'UTF-8');
    } elseif (function_exists('iconv')) {
        $powershellScript = iconv('UTF-8', 'UTF-16LE', $plainScript);
    } else {
        return ['ok' => false, 'message' => 'PHP sem mbstring/iconv para codificar comando PowerShell.'];
    }
    $encoded = base64_encode($powershellScript);
    $command = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . escapeshellarg($encoded);
    $result = run_deploy_command($command);

    return [
        'ok' => $result['exit_code'] === 0,
        'message' => $result['exit_code'] === 0 ? 'Arquivos sincronizados via PowerShell ZIP.' : 'PowerShell ZIP falhou.',
        'source' => $url,
        'result' => $result,
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
$repository = (string)($deployConfig['repository'] ?? getenv('DEPLOY_REPOSITORY') ?: (is_array($data) ? ($data['repository']['full_name'] ?? 'danielaraujodasilva/tatuagem') : 'danielaraujodasilva/tatuagem'));
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
$fallback = null;
if ($failed && !empty($deployConfig['zip_fallback_disabled']) !== true && getenv('DEPLOY_DISABLE_ZIP_FALLBACK') !== '1') {
    deploy_log('zip_fallback_start', ['delivery' => $delivery, 'repository' => $repository, 'branch' => $branch]);
    $fallback = deploy_from_zip($repository, $branch, $deployPath);
    deploy_log('zip_fallback_done', ['delivery' => $delivery, 'result' => $fallback]);
    if (!empty($fallback['ok'])) {
        $failed = false;
    } elseif (stripos(PHP_OS_FAMILY, 'Windows') !== false && getenv('DEPLOY_DISABLE_POWERSHELL_FALLBACK') !== '1') {
        deploy_log('powershell_fallback_start', ['delivery' => $delivery, 'repository' => $repository, 'branch' => $branch]);
        $fallback = deploy_from_powershell_zip($repository, $branch, $deployPath);
        deploy_log('powershell_fallback_done', ['delivery' => $delivery, 'result' => $fallback]);
        if (!empty($fallback['ok'])) {
            $failed = false;
        }
    }
}

if (!$failed && function_exists('opcache_reset')) {
    opcache_reset();
}

flock($lock, LOCK_UN);
fclose($lock);

if ($failed) {
    respond_json(['ok' => false, 'message' => 'Deploy falhou. Veja storage/logs/deploy.log.', 'results' => $results, 'fallback' => $fallback], 500);
}

$head = trim((string)($results[count($results) - 1]['output'] ?? ''));
deploy_log('success', ['delivery' => $delivery, 'head' => $head, 'fallback' => $fallback]);
respond_json(['ok' => true, 'message' => 'Deploy atualizado.', 'head' => $head, 'results' => $results, 'fallback' => $fallback]);
