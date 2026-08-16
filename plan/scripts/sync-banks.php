<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/bank-core.php';
require __DIR__ . '/../includes/bank-sync.php';

try {
    $result = sync_pluggy_banks('scheduled');
    fwrite(STDOUT, json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
