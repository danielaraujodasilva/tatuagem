<?php
/**
 * Endpoint público para o site consumir o feed do Instagram.
 * URL:
 * https://danieltatuador.com/instagram/feed.php
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

$configFile = __DIR__ . '/config.local.php';
$cacheFile = __DIR__ . '/cache/feed.json';

if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Arquivo instagram/config.local.php não encontrado.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$config = require $configFile;
$limit = max(1, min((int)($config['limit'] ?? 12), 50));
$ttl = max(300, (int)($config['cache_ttl'] ?? 21600));

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
    readfile($cacheFile);
    exit;
}

require __DIR__ . '/sync.php';
