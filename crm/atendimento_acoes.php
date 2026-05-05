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

$action = (string)($_POST['action'] ?? '');
$id = (string)($_POST['id'] ?? '');
$user = current_user() ?: [];
$nomeUsuario = trim((string)($user['nome'] ?? $user['username'] ?? 'Atendente'));

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
    $status = trim((string)($_POST['status'] ?? ''));
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
