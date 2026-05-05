<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require_once __DIR__ . '/data_store.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

function atendimento_json($payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function atendimento_request_data(): array
{
    $data = $_POST;

    if (!$data) {
        $raw = file_get_contents('php://input');
        $json = json_decode((string)$raw, true);
        if (is_array($json)) {
            $data = $json;
        } else {
            parse_str((string)$raw, $parsed);
            if (is_array($parsed)) {
                $data = $parsed;
            }
        }
    }

    return array_merge($_GET, $data);
}

$data = atendimento_request_data();
$action = trim((string)($data['action'] ?? ''));
$id = trim((string)($data['id'] ?? $data['cliente_id'] ?? ''));
$user = current_user() ?: [];
$nomeUsuario = trim((string)($user['nome'] ?? $user['username'] ?? 'Atendente'));

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === '' && $id === '') {
    header('Location: atendimento.php');
    exit;
}

if ($id === '') {
    atendimento_json(['ok' => false, 'message' => 'Conversa nao informada.'], 422);
}

if ($action === 'assumir') {
    $cliente = crmAtualizarClienteWhatsAppPorId($id, [
        'atendente' => $nomeUsuario,
        'modo_atendimento' => 'humano',
        'status' => 'em_atendimento',
        'data_ultimo_contato' => date('Y-m-d H:i:s'),
    ]);

    if (!$cliente) {
        atendimento_json(['ok' => false, 'message' => 'Conversa nao encontrada.'], 404);
    }

    atendimento_json(['ok' => true, 'cliente' => $cliente]);
}

if ($action === 'bot') {
    $cliente = crmAtualizarClienteWhatsAppPorId($id, [
        'atendente' => 'bot',
        'modo_atendimento' => 'bot',
    ]);

    if (!$cliente) {
        atendimento_json(['ok' => false, 'message' => 'Conversa nao encontrada.'], 404);
    }

    atendimento_json(['ok' => true, 'cliente' => $cliente]);
}

if ($action === 'status') {
    $status = trim((string)($data['status'] ?? ''));
    $permitidos = ['novo', 'lead_quente', 'agendado', 'sem_retorno', 'em_atendimento', 'fechado', 'perdido'];
    if (!in_array($status, $permitidos, true)) {
        atendimento_json(['ok' => false, 'message' => 'Status invalido.'], 422);
    }

    $cliente = crmAtualizarClienteWhatsAppPorId($id, [
        'status' => $status,
    ]);

    if (!$cliente) {
        atendimento_json(['ok' => false, 'message' => 'Conversa nao encontrada.'], 404);
    }

    atendimento_json(['ok' => true, 'cliente' => $cliente]);
}

atendimento_json(['ok' => false, 'message' => 'Acao invalida.'], 422);
