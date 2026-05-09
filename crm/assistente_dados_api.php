<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/data_ai.php';

header('Content-Type: application/json; charset=utf-8');

function data_ai_api_send(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_GET['diagnostico'] ?? '') === '1') {
    data_ai_api_send(data_ai_ollama_diagnostic());
}

$jobId = trim((string)($_GET['job'] ?? ''));
if ($jobId !== '') {
    try {
        $job = data_ai_job_read($jobId);
    } catch (Throwable $e) {
        data_ai_api_send([
            'ok' => false,
            'error' => 'Job invalido.',
            'error_type' => 'job_invalido',
            'stage' => 'job_read',
            'details' => ['message' => $e->getMessage()],
        ], 400);
    }

    if (!$job) {
        data_ai_api_send([
            'ok' => false,
            'error' => 'Job nao encontrado.',
            'error_type' => 'job_nao_encontrado',
            'stage' => 'job_read',
        ], 404);
    }

    $updatedAt = strtotime((string)($job['updated_at'] ?? '')) ?: time();
    $age = time() - $updatedAt;
    $status = (string)($job['status'] ?? '');
    if ($status === 'queued' && $age > 30) {
        data_ai_job_update($jobId, [
            'ok' => false,
            'status' => 'error',
            'stage' => 'worker_sem_resposta',
            'stage_label' => 'O worker nao iniciou dentro do tempo esperado.',
            'progress' => 100,
            'error' => 'O processamento em segundo plano nao iniciou. Verifique se o PHP CLI pode ser executado pelo Apache.',
            'error_type' => 'worker_not_started',
            'details' => [
                'seconds_since_update' => $age,
                'job' => $job,
            ],
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        $job = data_ai_job_read($jobId) ?? $job;
    }

    data_ai_api_send($job);
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
    $result = data_ai_create_job($question, current_user() ?? []);
    if (!empty($result['job_id'])) {
        $result['poll_url'] = 'assistente_dados_api.php?job=' . rawurlencode((string)$result['job_id']);
    }
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

    data_ai_api_send($result, 400);
}

data_ai_api_send($result);
