<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();

$relativePath = trim((string)($_GET['path'] ?? ''));
$relativePath = str_replace('\\', '/', $relativePath);
$relativePath = ltrim($relativePath, '/');

$mediaRoot = realpath(__DIR__ . '/data/media');
$filePath = realpath(__DIR__ . '/' . $relativePath);

if (!$mediaRoot || !$filePath || !is_file($filePath) || strpos($filePath, $mediaRoot . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    echo 'Arquivo nao encontrado';
    exit;
}

$fileSize = filesize($filePath);
$fileName = basename($filePath);
$mime = 'application/octet-stream';

if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
} elseif (function_exists('mime_content_type')) {
    $detected = mime_content_type($filePath);
    if (is_string($detected) && $detected !== '') {
        $mime = $detected;
    }
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . addcslashes($fileName, '"\\') . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');

$start = 0;
$end = $fileSize - 1;
$statusCode = 200;

if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $matches)) {
    if ($matches[1] !== '') {
        $start = (int)$matches[1];
    }
    if ($matches[2] !== '') {
        $end = (int)$matches[2];
    }

    if ($matches[1] === '' && $matches[2] !== '') {
        $suffixLength = (int)$matches[2];
        $start = max(0, $fileSize - $suffixLength);
        $end = $fileSize - 1;
    }

    if ($start > $end || $start < 0 || $end >= $fileSize) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    $statusCode = 206;
}

$length = $end - $start + 1;
http_response_code($statusCode);
header('Content-Length: ' . $length);

if ($statusCode === 206) {
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
}

$handle = fopen($filePath, 'rb');
if (!$handle) {
    http_response_code(500);
    exit;
}

fseek($handle, $start);
$remaining = $length;

while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(8192, $remaining));
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}

fclose($handle);
exit;
