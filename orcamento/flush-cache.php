<?php
header('Content-Type: text/plain; charset=utf-8');

$result = [
    'ok' => false,
    'opcache_reset' => false,
    'clearstatcache' => false,
];

if (function_exists('clearstatcache')) {
    clearstatcache(true);
    $result['clearstatcache'] = true;
}

if (function_exists('opcache_reset')) {
    $result['opcache_reset'] = @opcache_reset();
}

$result['ok'] = $result['opcache_reset'] || $result['clearstatcache'];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
