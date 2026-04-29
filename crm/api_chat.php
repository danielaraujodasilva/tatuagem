<?php
require_once __DIR__ . '/data_store.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$id = $_GET['id'] ?? '';
$clienteId = preg_replace('/^wa_/', '', (string)$id);

$clientes = crmCarregarClientes();

function mensagemEnviadaPorMim($msg) {
    if (!empty($msg['fromMe'])) return true;

    $autor = strtolower($msg['de'] ?? $msg['autor'] ?? '');
    return in_array($autor, ['eu', 'me', 'atendente', 'humano', 'bot'], true);
}

function pesoStatusMensagemChat($status) {
    $pesos = [
        '' => 0,
        'pending' => 1,
        'sent' => 2,
        'delivered' => 3,
        'read' => 4,
        'played' => 5,
        'error' => 0,
    ];

    return $pesos[$status] ?? 0;
}

function normalizarRemoteJidChat($jid) {
    return preg_replace('/:\d+(?=@)/', '', (string)$jid);
}

function aplicarStatusPendenteChat(&$msg, &$pendentes) {
    $chaves = array_unique(array_filter([
        $msg['messageId'] ?? '',
        $msg['remoteJid'] ?? '',
        normalizarRemoteJidChat($msg['remoteJid'] ?? ''),
    ]));

    $melhorChave = null;
    $melhorStatus = $msg['status'] ?? '';

    foreach ($chaves as $chave) {
        if (!empty($pendentes[$chave]['status']) && pesoStatusMensagemChat($pendentes[$chave]['status']) > pesoStatusMensagemChat($melhorStatus)) {
            $melhorChave = $chave;
            $melhorStatus = $pendentes[$chave]['status'];
        }
    }

    if ($melhorChave !== null) {
        $msg['status'] = $melhorStatus;
        $msg['status_updated_at'] = date('Y-m-d H:i:s');
        unset($pendentes[$melhorChave]);
        return true;
    }

    return false;
}

$pendentesPath = __DIR__ . '/data/status_pending.json';
$pendentes = file_exists($pendentesPath) ? json_decode(file_get_contents($pendentesPath), true) : [];
$pendentes = is_array($pendentes) ? $pendentes : [];

foreach ($clientes as $clienteIndex => &$cliente) {
    if ((string)($cliente['id'] ?? '') !== $clienteId) {
        continue;
    }

    $mudou = false;
    if (empty($cliente['mensagens']) || !is_array($cliente['mensagens'])) {
        $cliente['mensagens'] = [];
    }

    foreach ($cliente['mensagens'] as &$msg) {
        if (aplicarStatusPendenteChat($msg, $pendentes)) {
            $mudou = true;
        }
    }
    unset($msg);

    if ($mudou) {
        $clientes[$clienteIndex] = $cliente;
        crmSalvarClientes($clientes);
        file_put_contents($pendentesPath, json_encode($pendentes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
            'status_updated_at' => $msg['status_updated_at'] ?? '',
            'de' => $msg['de'] ?? '',
            'rawFromMe' => !empty($msg['fromMe']),
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
