<?php
/**
 * Inicia a sincronização do Instagram em background e responde rápido.
 * Use esta URL no navegador/GitHub Actions para evitar timeout 524 da Cloudflare:
 * https://danieltatuador.com/instagram/sync-start.php
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$lockFile = $cacheDir . '/sync.lock';
$statusFile = $cacheDir . '/sync-status.json';
$runner = __DIR__ . '/sync-runner.php';

$lockTtl = 60 * 60 * 2;
if (file_exists($lockFile) && (time() - filemtime($lockFile) < $lockTtl)) {
    echo json_encode([
        'ok' => true,
        'started' => false,
        'running' => true,
        'message' => 'Já existe uma sincronização em andamento.',
        'status_url' => '/instagram/sync-status.php',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

@file_put_contents($statusFile, json_encode([
    'ok' => true,
    'running' => true,
    'started_at' => date('c'),
    'message' => 'Sincronização disparada em background.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

function find_php_binary(): string
{
    $candidates = [];

    if (defined('PHP_BINDIR')) {
        $candidates[] = PHP_BINDIR . DIRECTORY_SEPARATOR . (stripos(PHP_OS_FAMILY, 'Windows') !== false ? 'php.exe' : 'php');
    }

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $candidates[] = 'C:\\xampp\\php\\php.exe';
        $candidates[] = 'php.exe';
    } else {
        $candidates[] = PHP_BINARY;
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = 'php';
    }

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        if (str_contains($candidate, DIRECTORY_SEPARATOR) && file_exists($candidate)) {
            return $candidate;
        }

        if (!str_contains($candidate, DIRECTORY_SEPARATOR)) {
            return $candidate;
        }
    }

    return 'php';
}

$php = find_php_binary();

if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
    $cmd = 'cmd /C start "" /B ' . escapeshellarg($php) . ' ' . escapeshellarg($runner);
    @pclose(@popen($cmd, 'r'));
} else {
    $cmd = 'nohup ' . escapeshellcmd($php) . ' ' . escapeshellarg($runner) . ' > /dev/null 2>&1 &';
    @exec($cmd);
}

echo json_encode([
    'ok' => true,
    'started' => true,
    'running' => true,
    'message' => 'Sincronização iniciada em background. Consulte o status em /instagram/sync-status.php.',
    'status_url' => '/instagram/sync-status.php',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
