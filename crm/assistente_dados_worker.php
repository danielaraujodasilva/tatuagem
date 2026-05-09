<?php
declare(strict_types=1);

require_once __DIR__ . '/data_ai.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Acesso negado.';
    exit(1);
}

$jobId = (string)($argv[1] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
    fwrite(STDERR, "Job invalido.\n");
    exit(1);
}

if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

$job = data_ai_job_read($jobId);
if (!$job) {
    fwrite(STDERR, "Job nao encontrado.\n");
    exit(1);
}

$question = trim((string)($job['question'] ?? ''));
data_ai_job_update($jobId, [
    'status' => 'running',
    'stage' => 'iniciando',
    'stage_label' => 'Worker iniciado em segundo plano.',
    'progress' => 10,
    'started_at' => date('Y-m-d H:i:s'),
]);

try {
    $result = data_ai_ask($question, static function (string $stage, string $label, int $percent, array $details = []) use ($jobId): void {
        data_ai_job_update($jobId, [
            'status' => 'running',
            'stage' => $stage,
            'stage_label' => $label,
            'progress' => max(1, min(99, $percent)),
            'details' => $details,
        ]);
    });

    if (!empty($result['ok'])) {
        data_ai_job_update($jobId, [
            'ok' => true,
            'status' => 'done',
            'stage' => 'concluido',
            'stage_label' => 'Resposta pronta.',
            'progress' => 100,
            'result' => $result,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        exit(0);
    }

    data_ai_job_update($jobId, [
        'ok' => false,
        'status' => 'error',
        'stage' => (string)($result['stage'] ?? 'erro'),
        'stage_label' => (string)($result['error'] ?? 'Falha ao gerar resposta.'),
        'progress' => 100,
        'result' => $result,
        'error' => (string)($result['error'] ?? 'Falha ao gerar resposta.'),
        'error_type' => (string)($result['error_type'] ?? 'worker_result_error'),
        'finished_at' => date('Y-m-d H:i:s'),
    ]);
    exit(0);
} catch (Throwable $e) {
    data_ai_job_update($jobId, [
        'ok' => false,
        'status' => 'error',
        'stage' => 'worker_exception',
        'stage_label' => 'Erro interno no worker.',
        'progress' => 100,
        'error' => $e->getMessage(),
        'error_type' => 'worker_exception',
        'details' => [
            'exception' => get_class($e),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ],
        'finished_at' => date('Y-m-d H:i:s'),
    ]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
