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

/** Nunca devolver o numero pessoal antigo nas legendas publicas. */
function ig_limpar_telefone(string $texto): string
{
    $t = $texto;
    $t = preg_replace('/\(?\s*11\s*\)?\s*9?4757[-\s]?3311/iu', '(11) 99997-9785', $t);
    $t = preg_replace('/\b11947573311\b/', '11999979785', $t);
    $t = preg_replace('/\b94757[-\s]?3311\b/', '99997-9785', $t);
    return $t;
}

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
    $bruto = (string)file_get_contents($cacheFile);
    $json = json_decode($bruto, true);
    if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
        foreach ($json['data'] as &$item) {
            if (isset($item['caption'])) $item['caption'] = ig_limpar_telefone((string)$item['caption']);
            if (isset($item['alt'])) $item['alt'] = ig_limpar_telefone((string)$item['alt']);
        }
        unset($item);
        echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo $bruto;
    }
    exit;
}

require __DIR__ . '/sync.php';
