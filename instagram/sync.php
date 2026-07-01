<?php
/**
 * Sincroniza mídias do Instagram e grava cache JSON.
 * Pode ser chamado pelo feed.php ou via cron:
 * php /caminho/do/site/instagram/sync.php
 */

$configFile = __DIR__ . '/config.local.php';
$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/feed.json';

if (!file_exists($configFile)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Arquivo instagram/config.local.php não encontrado.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$config = require $configFile;
$accessToken = trim((string)($config['access_token'] ?? ''));
$limit = max(1, min((int)($config['limit'] ?? 12), 50));

if ($accessToken === '' || $accessToken === 'COLE_SEU_TOKEN_AQUI') {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Access token vazio em instagram/config.local.php.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$fields = implode(',', [
    'id',
    'caption',
    'media_type',
    'media_url',
    'permalink',
    'thumbnail_url',
    'timestamp',
]);

$url = 'https://graph.instagram.com/v24.0/me/media?' . http_build_query([
    'fields' => $fields,
    'limit' => $limit,
    'access_token' => $accessToken,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(502);
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Erro cURL ao chamar Instagram.',
        'details' => $curlError,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$decoded = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(502);
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Instagram respondeu com erro.',
        'http_code' => $httpCode,
        'response' => $decoded ?: $response,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$items = [];
foreach (($decoded['data'] ?? []) as $item) {
    $mediaType = (string)($item['media_type'] ?? '');

    // Para carrossel, às vezes media_url não vem do jeito esperado dependendo do tipo/permissão.
    // Mantemos o item se tiver pelo menos permalink e alguma mídia/thumbnail.
    $mediaUrl = (string)($item['media_url'] ?? '');
    $thumbUrl = (string)($item['thumbnail_url'] ?? '');

    if ($mediaUrl === '' && $thumbUrl === '') {
        continue;
    }

    $caption = (string)($item['caption'] ?? '');

    $items[] = [
        'id' => (string)($item['id'] ?? ''),
        'caption' => $caption,
        'alt' => $caption !== '' ? mb_substr(trim(preg_replace('/\s+/', ' ', $caption)), 0, 140) : 'Post do Instagram',
        'media_type' => $mediaType,
        'media_url' => $mediaUrl,
        'thumbnail_url' => $thumbUrl,
        'image' => $thumbUrl !== '' ? $thumbUrl : $mediaUrl,
        'permalink' => (string)($item['permalink'] ?? ''),
        'timestamp' => (string)($item['timestamp'] ?? ''),
    ];
}

$output = [
    'ok' => true,
    'updated_at' => date('c'),
    'count' => count($items),
    'data' => $items,
];

$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

@file_put_contents($cacheFile, $json);

echo $json;
