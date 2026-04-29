<?php
header('Content-Type: text/plain; charset=utf-8');

function tailFile($path, $lines = 80) {
    if (!file_exists($path)) {
        return "(arquivo não existe)\n";
    }

    $data = file($path, FILE_IGNORE_NEW_LINES);
    return implode("\n", array_slice($data, -$lines)) . "\n";
}

echo "== status_debug.log ==\n";
echo tailFile(__DIR__ . '/data/status_debug.log');

echo "\n== status_pending.json ==\n";
echo file_exists(__DIR__ . '/data/status_pending.json')
    ? file_get_contents(__DIR__ . '/data/status_pending.json')
    : "(arquivo não existe)\n";

echo "\n\n== transcricao_debug.log ==\n";
echo tailFile(__DIR__ . '/data/transcricao_debug.log');

echo "\n== últimas mensagens fromMe ==\n";
$clientes = file_exists(__DIR__ . '/data/clientes.json') ? json_decode(file_get_contents(__DIR__ . '/data/clientes.json'), true) : [];
$resumo = [];
foreach (is_array($clientes) ? $clientes : [] as $cliente) {
    foreach (array_slice($cliente['mensagens'] ?? [], -8) as $msg) {
        if (!empty($msg['fromMe']) || !empty($msg['status'])) {
            $resumo[] = [
                'cliente' => $cliente['numero'] ?? '',
                'texto' => mb_substr($msg['texto'] ?? '', 0, 50),
                'fromMe' => !empty($msg['fromMe']),
                'messageId' => $msg['messageId'] ?? '',
                'remoteJid' => $msg['remoteJid'] ?? '',
                'status' => $msg['status'] ?? '',
                'fallback' => $msg['status_match_fallback'] ?? '',
                'transcricao' => mb_substr($msg['transcricao'] ?? '', 0, 80),
                'transcricao_erro' => mb_substr($msg['transcricao_erro'] ?? '', 0, 160),
            ];
        }
    }
}

echo json_encode(array_slice($resumo, -20), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
