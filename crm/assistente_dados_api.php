<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/data_ai.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo nao permitido.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$raw = (string)file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$question = trim((string)($input['pergunta'] ?? $input['question'] ?? ''));

try {
    $result = data_ai_ask($question);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'error' => 'Erro interno no PHP ao processar o assistente.',
        'error_type' => 'php_exception',
        'stage' => 'php_runtime',
        'details' => [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ],
        'queries' => function_exists('data_ai_public_queries') ? data_ai_public_queries() : [],
    ];
}

if (empty($result['ok'])) {
    http_response_code(400);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
