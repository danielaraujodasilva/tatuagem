<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$file = __DIR__ . DIRECTORY_SEPARATOR . 'progress.json';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function readProgress(string $file): array
{
    if (!is_file($file)) {
        return ['steps' => [], 'updated_at' => null];
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return ['steps' => [], 'updated_at' => null];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['steps' => [], 'updated_at' => null];
    }

    return [
        'steps' => is_array($data['steps'] ?? null) ? $data['steps'] : [],
        'updated_at' => $data['updated_at'] ?? null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(readProgress($file));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    respond(['error' => 'Método não permitido.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data) || !is_array($data['steps'] ?? null)) {
    respond(['error' => 'JSON inválido. Envie um objeto com a chave steps.'], 400);
}

$steps = [];
foreach ($data['steps'] as $key => $value) {
    $index = filter_var($key, FILTER_VALIDATE_INT);
    if ($index === false || $index < 0 || $index > 13 || !is_bool($value)) {
        continue;
    }

    $steps[(string) $index] = $value;
}

ksort($steps, SORT_NUMERIC);

$payload = [
    'steps' => $steps,
    'updated_at' => gmdate('c'),
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false || file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
    respond([
        'error' => 'Não foi possível gravar progress.json. Verifique a permissão de escrita da pasta witcher.'
    ], 500);
}

respond($payload);
