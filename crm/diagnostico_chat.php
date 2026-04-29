<?php
require_once __DIR__ . '/data_store.php';

header('Content-Type: text/plain; charset=utf-8');

function tailFile($path, $lines = 80) {
    if (!file_exists($path)) {
        return "(arquivo nao existe)\n";
    }

    $data = file($path, FILE_IGNORE_NEW_LINES);
    return implode("\n", array_slice($data, -$lines)) . "\n";
}

function cortar($texto, $limite) {
    return mb_substr((string)$texto, 0, $limite);
}

echo "== status_debug.log ==\n";
echo tailFile(__DIR__ . '/data/status_debug.log');

echo "\n== status_pending.json ==\n";
echo file_exists(__DIR__ . '/data/status_pending.json')
    ? file_get_contents(__DIR__ . '/data/status_pending.json')
    : "(arquivo nao existe)\n";

echo "\n\n== transcricao_debug.log ==\n";
echo tailFile(__DIR__ . '/data/transcricao_debug.log');

echo "\n== python_whisper_debug.log ==\n";
echo tailFile(__DIR__ . '/data/python_whisper_debug.log');

$clientes = crmCarregarClientes();

echo "\n== arquivos de clientes ==\n";
echo "runtime: " . crmClientesPath() . " (" . (file_exists(crmClientesPath()) ? filesize(crmClientesPath()) : 0) . " bytes)\n";
echo "legacy: " . crmClientesLegacyPath() . " (" . (file_exists(crmClientesLegacyPath()) ? filesize(crmClientesLegacyPath()) : 0) . " bytes)\n";

echo "\n== ultimas mensagens com status/fromMe/transcricao ==\n";
$resumo = [];
foreach ($clientes as $cliente) {
    foreach (($cliente['mensagens'] ?? []) as $msg) {
        if (!empty($msg['fromMe']) || !empty($msg['status']) || !empty($msg['transcricao']) || !empty($msg['transcricao_erro'])) {
            $resumo[] = [
                'cliente' => $cliente['numero'] ?? '',
                'texto' => cortar($msg['texto'] ?? '', 50),
                'de' => $msg['de'] ?? '',
                'fromMe' => !empty($msg['fromMe']),
                'messageId' => $msg['messageId'] ?? '',
                'remoteJid' => $msg['remoteJid'] ?? '',
                'status' => $msg['status'] ?? '',
                'status_updated_at' => $msg['status_updated_at'] ?? '',
                'fallback' => $msg['status_match_fallback'] ?? '',
                'tipo' => $msg['tipo'] ?? '',
                'mediaMime' => $msg['mediaMime'] ?? '',
                'mediaUrl' => $msg['mediaUrl'] ?? '',
                'transcricao' => cortar($msg['transcricao'] ?? '', 80),
                'transcricao_erro' => cortar($msg['transcricao_erro'] ?? '', 220),
                'data' => $msg['data'] ?? '',
            ];
        }
    }
}

usort($resumo, function ($a, $b) {
    return strcmp($a['data'] ?? '', $b['data'] ?? '');
});

echo json_encode(array_slice($resumo, -30), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n== ultimas mensagens gerais ==\n";
$geral = [];
foreach ($clientes as $cliente) {
    foreach (($cliente['mensagens'] ?? []) as $msg) {
        $geral[] = [
            'cliente' => $cliente['numero'] ?? '',
            'texto' => cortar($msg['texto'] ?? '', 50),
            'de' => $msg['de'] ?? '',
            'fromMe' => !empty($msg['fromMe']),
            'messageId' => $msg['messageId'] ?? '',
            'remoteJid' => $msg['remoteJid'] ?? '',
            'status' => $msg['status'] ?? '',
            'tipo' => $msg['tipo'] ?? '',
            'mediaMime' => $msg['mediaMime'] ?? '',
            'mediaUrl' => $msg['mediaUrl'] ?? '',
            'transcricao' => cortar($msg['transcricao'] ?? '', 80),
            'transcricao_erro' => cortar($msg['transcricao_erro'] ?? '', 220),
            'data' => $msg['data'] ?? '',
        ];
    }
}

usort($geral, function ($a, $b) {
    return strcmp($a['data'] ?? '', $b['data'] ?? '');
});

echo json_encode(array_slice($geral, -30), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
