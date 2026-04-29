<?php
require 'config.php';
date_default_timezone_set('America/Sao_Paulo');

$data = json_decode(file_get_contents("php://input"), true);
$arquivoClientes = __DIR__ . '/data/clientes.json';
$clientes = file_exists($arquivoClientes) ? json_decode(file_get_contents($arquivoClientes), true) : [];
if (!is_array($clientes)) {
    $clientes = [];
}

function salvarClientes($arquivo, $clientes) {
    file_put_contents($arquivo, json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function normalizarStatusMensagem($status) {
    $status = (string)$status;
    $map = [
        '0' => 'error',
        '1' => 'pending',
        '2' => 'sent',
        '3' => 'delivered',
        '4' => 'read',
        '5' => 'played',
        'server_ack' => 'sent',
        'delivery_ack' => 'delivered',
        'read' => 'read',
        'played' => 'played',
        'error' => 'error',
        'pending' => 'pending',
        'sent' => 'sent',
        'delivered' => 'delivered',
        'read' => 'read',
        'played' => 'played',
    ];

    return $map[$status] ?? '';
}

if (!empty($data['statusUpdate'])) {
    $statusMessageId = trim((string)($data['messageId'] ?? ''));
    $status = normalizarStatusMensagem($data['status'] ?? '');

    if ($statusMessageId !== '' && $status !== '') {
        foreach ($clientes as &$cliente) {
            foreach (($cliente['mensagens'] ?? []) as &$msg) {
                if (($msg['messageId'] ?? '') === $statusMessageId) {
                    $msg['status'] = $status;
                    $msg['status_updated_at'] = date('Y-m-d H:i:s');
                    salvarClientes($arquivoClientes, $clientes);
                    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Mensagem nao encontrada'], JSON_UNESCAPED_UNICODE);
    exit;
}

$numero = $data['numero'] ?? '';
$mensagemOriginal = trim($data['mensagem'] ?? '');
$mensagem = strtolower($mensagemOriginal);
$fromMe = !empty($data['fromMe']);
$messageId = trim((string)($data['messageId'] ?? ''));
$tipoMensagem = trim((string)($data['tipoMensagem'] ?? 'texto'));
$mediaBase64 = $data['mediaBase64'] ?? '';
$mediaMime = trim((string)($data['mediaMime'] ?? ''));
$mediaFileName = trim((string)($data['mediaFileName'] ?? ''));
$timestamp = isset($data['timestamp']) ? (int)$data['timestamp'] : time();
$dataMensagem = date('Y-m-d H:i:s', $timestamp > 0 ? $timestamp : time());

if (!$numero || (!$mensagem && !$mediaBase64)) {
    exit;
}

$config = json_decode(file_get_contents("data/config.json"), true);
$mensagem_trigger = strtolower(trim($config['mensagem_trigger'] ?? 'oi'));

function normalizarNumero($num) {
    return preg_replace('/\D/', '', (string)$num);
}

function numerosIguais($a, $b) {
    $a = normalizarNumero($a);
    $b = normalizarNumero($b);

    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;

    $min = min(strlen($a), strlen($b));
    return $min >= 10 && substr($a, -$min) === substr($b, -$min);
}

function primeiraEtapaDoFunil($conn) {
    $stmt = $conn->query("SELECT id, nome FROM pipelines ORDER BY ordem, id");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (trim((string)($row['nome'] ?? '')) !== '') {
            return (string)$row['id'];
        }
    }

    return '1';
}

function extensaoPorMime($mime, $fileName) {
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext) return preg_replace('/[^a-z0-9]/', '', $ext);

    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'application/pdf' => 'pdf',
    ];

    return $map[$mime] ?? 'bin';
}

function salvarMidia($base64, $mime, $fileName) {
    if (!$base64) return null;

    $bytes = base64_decode($base64, true);
    if ($bytes === false) return null;

    $dir = __DIR__ . '/data/media';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ext = extensaoPorMime($mime, $fileName);
    $name = uniqid('wa_', true) . '.' . $ext;
    file_put_contents($dir . '/' . $name, $bytes);

    return 'data/media/' . $name;
}

$clienteIndex = null;

// 🔍 procurar cliente existente
foreach ($clientes as $index => $c) {
    if (numerosIguais($c['numero'] ?? '', $numero)) {
        $clienteIndex = $index;
        break;
    }
}

// 🧠 se NÃO existir, cria novo
if ($clienteIndex === null) {

    // só cria se for mensagem gatilho
    if ($fromMe || $mensagem !== $mensagem_trigger) {
        exit;
    }

    $novo = [
        "id" => uniqid(),
        "numero" => $numero,
        "nome" => "Cliente",
        "status" => "novo",
        "etapa" => primeiraEtapaDoFunil($conn),
        "atendente" => "bot",
        "mensagens" => []
    ];

    $clientes[] = $novo;
    $clienteIndex = count($clientes) - 1;
}

// 💬 adiciona mensagem no histórico
if ($messageId !== '') {
    foreach (($clientes[$clienteIndex]['mensagens'] ?? []) as $msg) {
        if (($msg['messageId'] ?? '') === $messageId) {
            echo json_encode(['ok' => true, 'duplicated' => true]);
            exit;
        }
    }
}

$mediaUrl = salvarMidia($mediaBase64, $mediaMime, $mediaFileName);

$clientes[$clienteIndex]['mensagens'][] = [
    "de" => $fromMe ? "atendente" : "cliente",
    "texto" => $mensagemOriginal,
    "data" => $dataMensagem,
    "fromMe" => $fromMe,
    "messageId" => $messageId,
    "status" => $fromMe ? "sent" : "",
    "tipo" => $tipoMensagem,
    "mediaUrl" => $mediaUrl,
    "mediaMime" => $mediaMime,
    "mediaFileName" => $mediaFileName
];

// 💾 salva tudo
salvarClientes($arquivoClientes, $clientes);
