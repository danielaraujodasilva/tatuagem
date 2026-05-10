<?php
require_once __DIR__ . '/../../auth/auth.php';
require_staff();
require_once __DIR__ . '/../data_store.php';
require_once __DIR__ . '/../../includes/team_settings.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

function read_state_path(): string
{
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir . '/read_state.json';
}

function read_state_load(): array
{
    $path = read_state_path();
    if (!is_file($path)) {
        return [];
    }

    $state = json_decode((string)file_get_contents($path), true);
    return is_array($state) ? $state : [];
}

function read_state_save(array $state): void
{
    $path = read_state_path();
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    copy($tmp, $path);
    unlink($tmp);
}

function read_state_cliente_por_id(string $id): ?array
{
    foreach (crmCarregarClientes() as $cliente) {
        if ((string)($cliente['id'] ?? '') === $id) {
            return $cliente;
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'read' => read_state_load()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo nao permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = preg_replace('/^wa_/', '', trim((string)($payload['id'] ?? '')));
if ($id === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Conversa nao informada'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cliente = read_state_cliente_por_id($id);
$atendenteAtual = team_current_attendant(current_user() ?: []);
if (!$cliente || !team_conversation_owned_by($cliente, $atendenteAtual)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Assuma a conversa antes de marcar como lida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$state = read_state_load();
$state[$id] = date('Y-m-d H:i:s');
read_state_save($state);

echo json_encode(['ok' => true, 'id' => $id, 'read_at' => $state[$id]], JSON_UNESCAPED_UNICODE);
