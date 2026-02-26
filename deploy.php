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


$secret = 'Luna*102030';

$headers = getallheaders();
$signature = $headers['X-Hub-Signature-256'] ?? '';

$payload = file_get_contents('php://input');
$hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($hash, $signature)) {
    http_response_code(403);
    echo 'Nope.';
    exit;
}

$output = shell_exec('cd C:/xampp/htdocs/site && git pull 2>&1');
echo "<pre>$output</pre>";
