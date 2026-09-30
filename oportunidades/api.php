<?php
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow');

require dirname(__DIR__) . '/plan/includes/bootstrap.php';
require __DIR__ . '/crm.php';

if (!current_user()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Nao autenticado.']);
    exit;
}

try {
    $linhas = crm_oportunidades();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'rows' => $linhas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
