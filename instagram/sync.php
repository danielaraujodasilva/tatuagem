<?php
/**
 * Sincroniza mídias do Instagram, grava cache JSON e espelha imagens/capas em /galeria.
 *
 * O index.php atual já monta a galeria lendo arquivos da pasta /galeria.
 * Então este sync baixa as imagens/capas do Instagram para essa pasta, sem precisar mexer no layout.
 *
 * Pode ser chamado pelo feed.php ou via cron:
 * php /caminho/do/site/instagram/sync.php
 */

$configFile = __DIR__ . '/config.local.php';
$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/feed.json';
$galleryDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'galeria';

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

// Mantém compatível com seu index atual: ele lê tudo que está em /galeria.
// Como o config.local.php atual talvez não tenha estas opções, o padrão já liga o espelhamento.
$mirrorToGallery = (bool)($config['mirror_to_gallery'] ?? true);
$replaceLocalGallery = (bool)($config['replace_local_gallery'] ?? true);
$downloadTimeout = max(10, (int)($config['download_timeout'] ?? 30));

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

function ig_download_media(string $url, string $destination, int $timeout = 30): bool
{
    if ($url === '') {
        return false;
    }

    $tmp = $destination . '.tmp';
    $fp = @fopen($tmp, 'wb');
    if (!$fp) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'DanielTatuadorInstagramSync/1.0',
    ]);

    $ok = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $http < 200 || $http >= 300 || !preg_match('/image\//i', $contentType) || filesize($tmp) < 1000) {
        @unlink($tmp);
        return false;
    }

    return @rename($tmp, $destination);
}

function ig_prepare_gallery_dir(string $galleryDir, bool $replaceLocalGallery): array
{
    $result = [
        'backup_created' => null,
        'local_files_moved' => 0,
        'old_instagram_files_removed' => 0,
    ];

    if (!is_dir($galleryDir)) {
        @mkdir($galleryDir, 0755, true);
    }

    if (!is_dir($galleryDir) || !is_writable($galleryDir)) {
        return $result + ['error' => 'A pasta galeria não existe ou não tem permissão de escrita.'];
    }

    $files = glob($galleryDir . DIRECTORY_SEPARATOR . '*') ?: [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $base = basename($file);
        if (preg_match('/^instagram_[a-zA-Z0-9_-]+\.(jpe?g|png|webp)$/i', $base)) {
            @unlink($file);
            $result['old_instagram_files_removed']++;
        }
    }

    if ($replaceLocalGallery) {
        $marker = $galleryDir . DIRECTORY_SEPARATOR . '.instagram-gallery-active';
        if (!file_exists($marker)) {
            $backupDir = $galleryDir . DIRECTORY_SEPARATOR . '_backup_local_' . date('Ymd_His');
            @mkdir($backupDir, 0755, true);

            foreach ((glob($galleryDir . DIRECTORY_SEPARATOR . '*') ?: []) as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $base = basename($file);
                if (preg_match('/\.(jpe?g|png|webp|gif)$/i', $base) && !preg_match('/^instagram_/i', $base)) {
                    if (@rename($file, $backupDir . DIRECTORY_SEPARATOR . $base)) {
                        $result['local_files_moved']++;
                    }
                }
            }

            if ($result['local_files_moved'] > 0) {
                $result['backup_created'] = basename($backupDir);
            } else {
                @rmdir($backupDir);
            }

            @file_put_contents($marker, 'Instagram gallery sync active since ' . date('c'));
        }
    }

    return $result;
}

$items = [];
foreach (($decoded['data'] ?? []) as $item) {
    $mediaType = (string)($item['media_type'] ?? '');

    // Para vídeo/reels, a API normalmente entrega thumbnail_url.
    // Para imagem/carrossel, usamos media_url quando existir.
    $mediaUrl = (string)($item['media_url'] ?? '');
    $thumbUrl = (string)($item['thumbnail_url'] ?? '');
    $imageUrl = $thumbUrl !== '' ? $thumbUrl : $mediaUrl;

    if ($imageUrl === '') {
        continue;
    }

    $caption = (string)($item['caption'] ?? '');
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($item['id'] ?? uniqid('ig_', true)));

    $items[] = [
        'id' => $id,
        'caption' => $caption,
        'alt' => $caption !== '' ? mb_substr(trim(preg_replace('/\s+/', ' ', $caption)), 0, 140) : 'Post do Instagram',
        'media_type' => $mediaType,
        'media_url' => $mediaUrl,
        'thumbnail_url' => $thumbUrl,
        'image' => $imageUrl,
        'permalink' => (string)($item['permalink'] ?? ''),
        'timestamp' => (string)($item['timestamp'] ?? ''),
    ];
}

$gallerySync = null;
if ($mirrorToGallery && count($items) > 0) {
    $gallerySync = ig_prepare_gallery_dir($galleryDir, $replaceLocalGallery);
    $downloaded = 0;
    $failed = 0;

    if (empty($gallerySync['error'])) {
        foreach ($items as $pos => $post) {
            $extension = 'jpg';
            $filename = sprintf('instagram_%02d_%s.%s', $pos + 1, $post['id'], $extension);
            $destination = $galleryDir . DIRECTORY_SEPARATOR . $filename;

            if (ig_download_media((string)$post['image'], $destination, $downloadTimeout)) {
                $downloaded++;
            } else {
                $failed++;
            }
        }
    }

    $gallerySync['downloaded'] = $downloaded;
    $gallerySync['failed'] = $failed;
    $gallerySync['gallery_dir'] = $galleryDir;
}

$output = [
    'ok' => true,
    'updated_at' => date('c'),
    'count' => count($items),
    'gallery_sync' => $gallerySync,
    'data' => $items,
];

$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

@file_put_contents($cacheFile, $json);

echo $json;
