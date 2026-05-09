<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/data_ai.php';

header('Content-Type: application/json; charset=utf-8');

if (($_GET['diagnostico'] ?? '') === '1') {
    echo json_encode(data_ai_ollama_diagnostic(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($_GET['debug'] ?? '') !== '1') {
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
if ($question === '' && isset($_GET['pergunta'])) {
    $question = trim((string)$_GET['pergunta']);
}

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

if (!is_array($result)) {
    $result = [
        'ok' => false,
        'error' => 'O assistente retornou uma resposta interna em formato inesperado.',
        'error_type' => 'api_resultado_invalido',
        'stage' => 'api_normalizacao',
        'details' => [
            'tipo_resultado' => gettype($result),
            'valor' => (string)$result,
        ],
    ];
}

if (empty($result['ok'])) {
    if (empty($result['error'])) {
        $result['error'] = 'A API marcou a resposta como falha, mas nao informou a mensagem do erro.';
    }
    if (empty($result['error_type'])) {
        $result['error_type'] = 'api_falha_sem_mensagem';
    }
    if (empty($result['stage'])) {
        $result['stage'] = 'api_normalizacao';
    }
    $result['details'] = array_merge([
        'resultado_bruto' => $result,
    ], is_array($result['details'] ?? null) ? $result['details'] : []);

    http_response_code(400);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
