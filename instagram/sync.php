<?php
/**
 * Sincroniza mídias do Instagram, grava cache JSON e espelha imagens/capas em /galeria.
 * Também escreve progresso em instagram/cache/sync-status.json para a página sync-panel.php.
 */

@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@ignore_user_abort(true);
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

$configFile = __DIR__ . '/config.local.php';
$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/feed.json';
$statusFile = $cacheDir . '/sync-status.json';
$galleryDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'galeria';

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

function ig_status(string $phase, int $percent, string $message, array $extra = []): void
{
    global $statusFile;

    $payload = array_merge([
        'ok' => true,
        'running' => true,
        'phase' => $phase,
        'percent' => max(0, min(100, $percent)),
        'message' => $message,
        'updated_at' => date('c'),
    ], $extra);

    @file_put_contents($statusFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function ig_finish(bool $ok, string $message, array $extra = []): void
{
    global $statusFile;

    $payload = array_merge([
        'ok' => $ok,
        'running' => false,
        'phase' => $ok ? 'done' : 'error',
        'percent' => $ok ? 100 : 0,
        'message' => $message,
        'updated_at' => date('c'),
    ], $extra);

    @file_put_contents($statusFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

ig_status('start', 1, 'Preparando sincronização...');

if (!file_exists($configFile)) {
    $error = ['ok' => false, 'error' => 'Arquivo instagram/config.local.php não encontrado.'];
    ig_finish(false, $error['error'], ['error' => $error['error']]);
    if (PHP_SAPI !== 'cli') http_response_code(500);
    echo json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$config = require $configFile;
$accessToken = trim((string)($config['access_token'] ?? ''));

$configLimit = (int)($config['limit'] ?? 0);
$fetchAll = $configLimit <= 0;
$maxItems = $fetchAll ? PHP_INT_MAX : max(1, $configLimit);
$pageSize = max(1, min((int)($config['page_size'] ?? 50), 50));
$maxPages = max(1, min((int)($config['max_pages'] ?? 20), 100));

$mirrorToGallery = (bool)($config['mirror_to_gallery'] ?? true);
$replaceLocalGallery = (bool)($config['replace_local_gallery'] ?? true);
$downloadTimeout = max(8, (int)($config['download_timeout'] ?? 12));

if ($accessToken === '' || $accessToken === 'COLE_SEU_TOKEN_AQUI') {
    $error = ['ok' => false, 'error' => 'Access token vazio em instagram/config.local.php.'];
    ig_finish(false, $error['error'], ['error' => $error['error']]);
    if (PHP_SAPI !== 'cli') http_response_code(500);
    echo json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ig_fetch_json(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Erro cURL ao chamar Instagram.', 'details' => $curlError];
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'Instagram respondeu com erro.',
            'http_code' => $httpCode,
            'response' => $decoded ?: $response,
        ];
    }

    return ['ok' => true, 'body' => $decoded];
}

function ig_download_media(string $url, string $destination, int $timeout = 12): bool
{
    if ($url === '') return false;

    $tmp = $destination . '.tmp';
    $fp = @fopen($tmp, 'wb');
    if (!$fp) return false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'DanielTatuadorInstagramSync/1.0',
        CURLOPT_LOW_SPEED_LIMIT => 1024,
        CURLOPT_LOW_SPEED_TIME => 8,
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
    $result = ['backup_created' => null, 'local_files_moved' => 0, 'old_instagram_files_removed' => 0];

    if (!is_dir($galleryDir)) @mkdir($galleryDir, 0755, true);
    if (!is_dir($galleryDir) || !is_writable($galleryDir)) {
        return $result + ['error' => 'A pasta galeria não existe ou não tem permissão de escrita.'];
    }

    foreach ((glob($galleryDir . DIRECTORY_SEPARATOR . '*') ?: []) as $file) {
        if (!is_file($file)) continue;
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
                if (!is_file($file)) continue;
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

function ig_normalize_item(array $item): ?array
{
    $mediaType = (string)($item['media_type'] ?? '');
    $mediaUrl = (string)($item['media_url'] ?? '');
    $thumbUrl = (string)($item['thumbnail_url'] ?? '');
    $imageUrl = $thumbUrl !== '' ? $thumbUrl : $mediaUrl;
    if ($imageUrl === '') return null;

    $caption = (string)($item['caption'] ?? '');
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($item['id'] ?? uniqid('ig_', true)));

    return [
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

$fields = implode(',', ['id', 'caption', 'media_type', 'media_url', 'permalink', 'thumbnail_url', 'timestamp']);
$nextUrl = 'https://graph.instagram.com/v24.0/me/media?' . http_build_query([
    'fields' => $fields,
    'limit' => min($pageSize, $maxItems),
    'access_token' => $accessToken,
]);

$items = [];
$pagesFetched = 0;
$seenIds = [];

ig_status('fetch', 5, 'Buscando posts no Instagram...', ['pages_fetched' => 0, 'found' => 0]);

while ($nextUrl !== '' && $pagesFetched < $maxPages && count($items) < $maxItems) {
    $result = ig_fetch_json($nextUrl);

    if (!$result['ok']) {
        ig_finish(false, $result['error'] ?? 'Erro ao buscar dados do Instagram.', $result);
        if (PHP_SAPI !== 'cli') http_response_code(502);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $decoded = $result['body'];
    $pagesFetched++;

    foreach (($decoded['data'] ?? []) as $rawItem) {
        if (count($items) >= $maxItems) break;
        $post = ig_normalize_item($rawItem);
        if (!$post || isset($seenIds[$post['id']])) continue;
        $seenIds[$post['id']] = true;
        $items[] = $post;
    }

    $fetchPercent = $fetchAll ? min(35, 5 + ($pagesFetched * 3)) : min(35, 5 + (int)((count($items) / max(1, $maxItems)) * 30));
    ig_status('fetch', $fetchPercent, 'Buscando posts no Instagram...', [
        'pages_fetched' => $pagesFetched,
        'found' => count($items),
        'max_pages' => $maxPages,
    ]);

    $nextUrl = (string)($decoded['paging']['next'] ?? '');
}

$gallerySync = null;
if ($mirrorToGallery && count($items) > 0) {
    ig_status('prepare', 38, 'Preparando pasta da galeria...', ['found' => count($items)]);
    $gallerySync = ig_prepare_gallery_dir($galleryDir, $replaceLocalGallery);
    $downloaded = 0;
    $failed = 0;
    $total = count($items);

    if (empty($gallerySync['error'])) {
        foreach ($items as $pos => $post) {
            $current = $pos + 1;
            $percent = 40 + (int)(($pos / max(1, $total)) * 58);
            ig_status('download', $percent, 'Baixando imagens e capas para a galeria...', [
                'current' => $current,
                'total' => $total,
                'downloaded' => $downloaded,
                'failed' => $failed,
                'current_id' => $post['id'],
            ]);

            $filename = sprintf('instagram_%03d_%s.jpg', $current, $post['id']);
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

    ig_status('download', 99, 'Finalizando sincronização...', [
        'current' => $total,
        'total' => $total,
        'downloaded' => $downloaded,
        'failed' => $failed,
    ]);
}

$output = [
    'ok' => true,
    'updated_at' => date('c'),
    'count' => count($items),
    'pages_fetched' => $pagesFetched,
    'fetch_all' => $fetchAll,
    'configured_limit' => $configLimit,
    'gallery_sync' => $gallerySync,
    'data' => $items,
];

$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
@file_put_contents($cacheFile, $json);
ig_finish(true, 'Sincronização concluída.', ['percent' => 100, 'result' => $output]);

echo $json;
