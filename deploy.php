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


$secret = getenv('DEPLOY_WEBHOOK_SECRET') ?: '';
if ($secret === '') {
    http_response_code(500);
    echo 'Deploy nao configurado.';
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

$deployPath = getenv('DEPLOY_PATH') ?: __DIR__;
$output = shell_exec('cd ' . escapeshellarg($deployPath) . ' && git pull 2>&1');
echo "<pre>$output</pre>";
