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

function defaultProgress(): array
{
    return [
        'version' => 2,
        'mode' => 'idle',
        'active_block' => null,
        'started_at' => null,
        'updated_at' => null,
        'operator_note' => '',
        'blocks' => new stdClass(),
        'events' => [],
    ];
}

function readProgress(string $file): array
{
    if (!is_file($file)) {
        return defaultProgress();
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return defaultProgress();
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return defaultProgress();
    }

    if (isset($data['steps']) && !isset($data['blocks'])) {
        $data = defaultProgress();
    }

    return array_replace(defaultProgress(), $data);
}

function cleanText(mixed $value, int $limit = 4000): string
{
    $text = is_string($value) ? $value : '';
    $text = trim($text);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $limit, 'UTF-8');
    }
    return substr($text, 0, $limit);
}

function cleanBlock(mixed $raw): array
{
    $raw = is_array($raw) ? $raw : [];
    $status = cleanText($raw['status'] ?? 'pending', 20);
    $allowed = ['pending', 'running', 'paused', 'done', 'error'];
    if (!in_array($status, $allowed, true)) {
        $status = 'pending';
    }

    return [
        'status' => $status,
        'completed_lines' => max(0, (int) ($raw['completed_lines'] ?? 0)),
        'failed_lines' => max(0, (int) ($raw['failed_lines'] ?? 0)),
        'current_action' => cleanText($raw['current_action'] ?? '', 500),
        'error' => cleanText($raw['error'] ?? '', 2000),
        'note' => cleanText($raw['note'] ?? '', 2000),
        'started_at' => cleanText($raw['started_at'] ?? '', 40) ?: null,
        'updated_at' => cleanText($raw['updated_at'] ?? '', 40) ?: null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(readProgress($file));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    respond(['error' => 'Metodo nao permitido.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    respond(['error' => 'JSON invalido.'], 400);
}

$blocks = [];
foreach (($data['blocks'] ?? []) as $id => $block) {
    $safeId = preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
    if ($safeId === '') {
        continue;
    }
    $blocks[$safeId] = cleanBlock($block);
}

$events = [];
foreach (array_slice(($data['events'] ?? []), -80) as $event) {
    if (!is_array($event)) {
        continue;
    }
    $events[] = [
        'at' => cleanText($event['at'] ?? '', 40),
        'block' => preg_replace('/[^a-z0-9_-]/i', '', (string) ($event['block'] ?? '')),
        'type' => cleanText($event['type'] ?? 'note', 40),
        'message' => cleanText($event['message'] ?? '', 500),
    ];
}

$payload = [
    'version' => 2,
    'mode' => cleanText($data['mode'] ?? 'idle', 20),
    'active_block' => cleanText($data['active_block'] ?? '', 80) ?: null,
    'started_at' => cleanText($data['started_at'] ?? '', 40) ?: null,
    'updated_at' => gmdate('c'),
    'operator_note' => cleanText($data['operator_note'] ?? '', 2000),
    'blocks' => $blocks,
    'events' => $events,
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false || file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
    respond([
        'error' => 'Nao foi possivel gravar progress.json. Verifique a permissao de escrita da pasta witcher.'
    ], 500);
}

respond($payload);
