<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$isMultipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
$data = $isMultipart ? $_POST : (json_decode(file_get_contents("php://input"), true) ?: []);

$numero = $data['numero'] ?? '';
$mensagem = $data['mensagem'] ?? '';
$media = null;
$mediaLocal = null;

function normalizarNumero($num) {
    return preg_replace('/[^0-9]/', '', (string)$num);
}

function numerosIguais($a, $b) {
    $a = normalizarNumero($a);
    $b = normalizarNumero($b);

    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;

    $min = min(strlen($a), strlen($b));
    return $min >= 10 && substr($a, -$min) === substr($b, -$min);
}

function salvarAnexoLocal($tmp, $mime, $fileName) {
    $dir = __DIR__ . '/data/media';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!$ext) {
        $ext = explode('/', $mime ?: 'application/octet-stream')[1] ?? 'bin';
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
    }

    $name = uniqid('wa_out_', true) . '.' . $ext;
    $target = $dir . '/' . $name;
    copy($tmp, $target);

    return 'data/media/' . $name;
}

function pesoStatusMensagem($status) {
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

function aplicarStatusPendente(&$mensagem, $messageId, $remoteJid) {
    $arquivo = __DIR__ . '/data/status_pending.json';
    $pendentes = file_exists($arquivo) ? json_decode(file_get_contents($arquivo), true) : [];
    if (!is_array($pendentes)) {
        return;
    }

    $melhorChave = null;
    $melhorStatus = '';

    foreach (array_filter([$messageId, $remoteJid]) as $chave) {
        if (!empty($pendentes[$chave]['status']) && pesoStatusMensagem($pendentes[$chave]['status']) >= pesoStatusMensagem($melhorStatus)) {
            $melhorChave = $chave;
            $melhorStatus = $pendentes[$chave]['status'];
        }
    }

    if ($melhorStatus) {
        $mensagem['status'] = $melhorStatus;
        $mensagem['status_updated_at'] = date('Y-m-d H:i:s');
        if ($melhorChave) {
            unset($pendentes[$melhorChave]);
            file_put_contents($arquivo, json_encode($pendentes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}

if (!empty($_FILES['arquivo']) && is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
    $tmp = $_FILES['arquivo']['tmp_name'];
    $mime = mime_content_type($tmp) ?: ($_FILES['arquivo']['type'] ?? 'application/octet-stream');
    $fileName = $_FILES['arquivo']['name'] ?? 'arquivo';

    $media = [
        'base64' => base64_encode(file_get_contents($tmp)),
        'mime' => $mime,
        'fileName' => $fileName,
    ];
    $mediaLocal = [
        'url' => salvarAnexoLocal($tmp, $mime, $fileName),
        'mime' => $mime,
        'fileName' => $fileName,
        'tipo' => explode('/', $mime)[0] ?? 'document',
    ];
}

$numeroLimpo = normalizarNumero($numero);

$ch = curl_init("http://localhost:3001/enviar");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "numero" => $numeroLimpo,
    "mensagem" => $mensagem,
    "media" => $media,
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);

if (!empty($res['ok'])) {
    $arquivo = __DIR__ . "/data/clientes.json";
    $messageId = trim((string)($res['messageId'] ?? ''));
    $remoteJid = trim((string)($res['remoteJid'] ?? ''));

    $clientes = file_exists($arquivo) ? json_decode(file_get_contents($arquivo), true) : [];
    if (!is_array($clientes)) {
        $clientes = [];
    }

    $mensagemSalva = [
        "de" => "atendente",
        "texto" => $mensagem,
        "data" => date('Y-m-d H:i:s'),
        "fromMe" => true,
        "messageId" => $messageId,
        "remoteJid" => $remoteJid,
        "status" => "sent",
    ];
    aplicarStatusPendente($mensagemSalva, $messageId, $remoteJid);

    if ($mediaLocal) {
        $mensagemSalva["tipo"] = $mediaLocal['tipo'];
        $mensagemSalva["mediaUrl"] = $mediaLocal['url'];
        $mensagemSalva["mediaMime"] = $mediaLocal['mime'];
        $mensagemSalva["mediaFileName"] = $mediaLocal['fileName'];
    }

    $achou = false;
    foreach ($clientes as &$c) {
        if (numerosIguais($c['numero'] ?? '', $numeroLimpo)) {
            $c['mensagens'][] = $mensagemSalva;
            $achou = true;
            break;
        }
    }

    if (!$achou) {
        $clientes[] = [
            "id" => uniqid(),
            "numero" => $numeroLimpo,
            "nome" => "Cliente",
            "status" => "novo",
            "atendente" => "humano",
            "mensagens" => [$mensagemSalva]
        ];
    }

    file_put_contents($arquivo, json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode($res ?: ['ok' => false, 'erro' => 'Erro ao enviar'], JSON_UNESCAPED_UNICODE);
