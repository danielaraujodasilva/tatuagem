<?php
require_once __DIR__ . '/../../auth/auth.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');

function whatsapp_node_status_url(): string {
    return 'http://127.0.0.1:3001/status';
}

$context = stream_context_create([
    'http' => [
        'timeout' => 3,
        'ignore_errors' => true,
    ],
]);

$json = @file_get_contents(whatsapp_node_status_url(), false, $context);
if ($json === false || trim($json) === '') {
    echo json_encode(['ok' => false, 'error' => 'WhatsApp nao respondeu'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo $json;