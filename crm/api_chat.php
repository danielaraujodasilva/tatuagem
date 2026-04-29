<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$id = $_GET['id'] ?? '';
$clienteId = preg_replace('/^wa_/', '', (string)$id);
$arquivo = __DIR__ . '/data/clientes.json';

$clientes = file_exists($arquivo) ? json_decode(file_get_contents($arquivo), true) : [];
if (!is_array($clientes)) {
    $clientes = [];
}

function mensagemEnviadaPorMim($msg) {
    if (!empty($msg['fromMe'])) return true;
    if (!empty($msg['status'])) return true;

    $autor = strtolower($msg['de'] ?? $msg['autor'] ?? '');
    return in_array($autor, ['eu', 'me', 'atendente', 'humano', 'bot'], true);
}

foreach ($clientes as $cliente) {
    if ((string)($cliente['id'] ?? '') !== $clienteId) {
        continue;
    }

    $mensagens = array_map(function ($msg) {
        $data = $msg['data'] ?? '';

        return [
            'messageId' => $msg['messageId'] ?? '',
            'texto' => $msg['texto'] ?? '',
            'data' => $data,
            'hora' => $data ? date('H:i', strtotime($data)) : '',
            'fromMe' => mensagemEnviadaPorMim($msg),
            'status' => $msg['status'] ?? '',
            'transcricao' => $msg['transcricao'] ?? '',
            'transcricao_erro' => $msg['transcricao_erro'] ?? '',
            'tipo' => $msg['tipo'] ?? 'texto',
            'mediaUrl' => $msg['mediaUrl'] ?? '',
            'mediaMime' => $msg['mediaMime'] ?? '',
            'mediaFileName' => $msg['mediaFileName'] ?? '',
        ];
    }, $cliente['mensagens'] ?? []);

    echo json_encode([
        'ok' => true,
        'mensagens' => $mensagens,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => false,
    'error' => 'Cliente não encontrado',
], JSON_UNESCAPED_UNICODE);
