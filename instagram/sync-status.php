<?php
/**
 * Status da sincronização em background.
 * URL:
 * https://danieltatuador.com/instagram/sync-status.php
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$cacheDir = __DIR__ . '/cache';
$lockFile = $cacheDir . '/sync.lock';
$statusFile = $cacheDir . '/sync-status.json';
$feedFile = $cacheDir . '/feed.json';

$status = [
    'ok' => true,
    'running' => false,
    'message' => 'Nenhuma sincronização encontrada ainda.',
];

if (file_exists($statusFile)) {
    $decoded = json_decode((string)file_get_contents($statusFile), true);
    if (is_array($decoded)) {
        $status = $decoded;
    }
}

$status['running'] = file_exists($lockFile) && (time() - filemtime($lockFile) < 60 * 60 * 2);

if (file_exists($feedFile)) {
    $feed = json_decode((string)file_get_contents($feedFile), true);
    if (is_array($feed)) {
        $status['last_feed_count'] = $feed['count'] ?? null;
        $status['last_feed_updated_at'] = $feed['updated_at'] ?? null;
        $status['last_gallery_sync'] = $feed['gallery_sync'] ?? null;
    }
}

echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
