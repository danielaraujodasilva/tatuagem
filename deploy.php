<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    exit;
}

file_put_contents(
    __DIR__ . '/deploy.log',
    date('Y-m-d H:i:s') . " deploy executado\n",
    FILE_APPEND
);

$localConfig = __DIR__ . '/deploy.local.php';
$deployConfig = is_file($localConfig) ? require $localConfig : [];
if (!is_array($deployConfig)) {
    $deployConfig = [];
}

$secret = getenv('DEPLOY_WEBHOOK_SECRET') ?: (string)($deployConfig['secret'] ?? '');
if ($secret === '') {
    http_response_code(500);
    echo 'Deploy nao configurado. Defina DEPLOY_WEBHOOK_SECRET ou deploy.local.php.';
    exit;
}

$headers = getallheaders();
$signature = $headers['X-Hub-Signature-256'] ?? '';

$payload = file_get_contents('php://input');
$hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($hash, $signature)) {
    http_response_code(403);
    echo 'Nope.';
    exit;
}

$deployPath = getenv('DEPLOY_PATH') ?: (string)($deployConfig['path'] ?? __DIR__);
$output = shell_exec('cd ' . escapeshellarg($deployPath) . ' && git pull 2>&1');
echo "<pre>$output</pre>";
