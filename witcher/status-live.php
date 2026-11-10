<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$statusFile = 'C:\\witcher-dub-br\\progress-ui\\status.json';
if (!is_file($statusFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'Status ainda nao disponivel.']);
    exit;
}

$raw = file_get_contents($statusFile);
$status = $raw === false ? null : json_decode($raw, true);
if (!is_array($status)) {
    http_response_code(503);
    echo json_encode(['error' => 'Status temporariamente invalido.']);
    exit;
}

echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
