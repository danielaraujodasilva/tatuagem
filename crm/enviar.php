<?php
require_once __DIR__ . '/data_store.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$isMultipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
$data = $isMultipart ? $_POST : (json_decode(file_get_contents("php://input"), true) ?: []);

$numero = $data['numero'] ?? '';
$mensagem = $data['mensagem'] ?? '';
$ptt = !empty($data['ptt']);
$media = null;
$mediaLocal = null;
$arquivoConvertidoTemporario = null;

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

function logEnvioWhatsApp($dados) {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents(
        $dir . '/envio_debug.log',
        '[' . date('Y-m-d H:i:s') . '] ' . json_encode($dados, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

function comandoDisponivel($funcao) {
    if (!function_exists($funcao)) {
        return false;
    }

    $desabilitadas = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array($funcao, $desabilitadas, true);
}

function converterAudioVoz($tmp) {
    if (!comandoDisponivel('exec')) {
        return null;
    }

    $saida = tempnam(sys_get_temp_dir(), 'wa_ptt_');
    if ($saida === false) {
        return null;
    }
    if (is_file($saida)) {
        unlink($saida);
    }
    $saida .= '.ogg';

    $stdoutFile = tempnam(sys_get_temp_dir(), 'ffmpeg_ptt_out_');
    $stderrFile = tempnam(sys_get_temp_dir(), 'ffmpeg_ptt_err_');
    $cmd = 'ffmpeg -y -i ' . escapeshellarg($tmp)
        . ' -vn -c:a libopus -b:a 32k -ar 48000 -ac 1 '
        . escapeshellarg($saida)
        . ' > ' . escapeshellarg($stdoutFile)
        . ' 2> ' . escapeshellarg($stderrFile);

    $output = [];
    $exitCode = null;
    exec($cmd, $output, $exitCode);
    $stdout = is_file($stdoutFile) ? file_get_contents($stdoutFile) : '';
    $stderr = is_file($stderrFile) ? file_get_contents($stderrFile) : '';

    if (is_file($stdoutFile)) {
        unlink($stdoutFile);
    }
    if (is_file($stderrFile)) {
        unlink($stderrFile);
    }

    logEnvioWhatsApp([
        'ffmpeg_ptt' => true,
        'exitCode' => $exitCode,
        'stdout' => mb_substr($stdout, 0, 1000),
        'stderr' => mb_substr($stderr, 0, 2000),
        'output' => mb_substr(implode(PHP_EOL, $output), 0, 1000),
        'saida' => $saida,
        'saidaSize' => is_file($saida) ? filesize($saida) : 0,
    ]);

    if ($exitCode === 0 && is_file($saida) && filesize($saida) > 0) {
        return $saida;
    }

    if (is_file($saida)) {
        unlink($saida);
    }
    return null;
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

function normalizarRemoteJidStatus($jid) {
    return preg_replace('/:\d+(?=@)/', '', (string)$jid);
}

function aplicarStatusPendente(&$mensagem, $messageId, $remoteJid) {
    $arquivo = __DIR__ . '/data/status_pending.json';
    $pendentes = file_exists($arquivo) ? json_decode(file_get_contents($arquivo), true) : [];
    if (!is_array($pendentes)) {
        return;
    }

    $melhorChave = null;
    $melhorStatus = '';

    if ($messageId !== '' && !empty($pendentes[$messageId]['status']) && pesoStatusMensagem($pendentes[$messageId]['status']) >= pesoStatusMensagem($melhorStatus)) {
        $melhorChave = $messageId;
        $melhorStatus = $pendentes[$messageId]['status'];
    }

    foreach (array_unique(array_filter([$remoteJid, normalizarRemoteJidStatus($remoteJid)])) as $chave) {
        if (!empty($pendentes[$chave]['status']) && pesoStatusMensagem($pendentes[$chave]['status']) >= pesoStatusMensagem($melhorStatus)) {
            $statusPendente = $pendentes[$chave]['status'];
            if (pesoStatusMensagem($statusPendente) <= pesoStatusMensagem('delivered')) {
                $melhorChave = $chave;
                $melhorStatus = $statusPendente;
            }
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
    if ($ptt && (!str_starts_with($mime, 'audio/') || $mime === 'application/octet-stream')) {
        $mime = $_FILES['arquivo']['type'] ?: 'audio/webm';
    }

    if ($ptt) {
        $convertido = converterAudioVoz($tmp);
        if ($convertido) {
            $arquivoConvertidoTemporario = $convertido;
            $tmp = $convertido;
            $mime = 'audio/ogg; codecs=opus';
            $fileName = preg_replace('/\.[^.]+$/', '', $fileName) . '.ogg';
        } else {
            echo json_encode(['ok' => false, 'erro' => 'Nao consegui converter o audio para enviar ao WhatsApp'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $media = [
        'base64' => base64_encode(file_get_contents($tmp)),
        'mime' => $mime,
        'fileName' => $fileName,
        'ptt' => $ptt,
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
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$curlHttpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

$res = json_decode($response, true);
logEnvioWhatsApp([
    'numero' => $numeroLimpo,
    'ptt' => $ptt,
    'mediaMime' => $media['mime'] ?? '',
    'mediaFileName' => $media['fileName'] ?? '',
    'curlHttpCode' => $curlHttpCode,
    'curlError' => $curlError,
    'nodeResponse' => mb_substr((string)$response, 0, 2000),
]);

if (!empty($res['ok'])) {
    $messageId = trim((string)($res['messageId'] ?? ''));
    $remoteJid = trim((string)($res['remoteJid'] ?? ''));

    $clientes = crmCarregarClientes();

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

    crmSalvarClientes($clientes);
}

echo json_encode($res ?: ['ok' => false, 'erro' => 'Erro ao enviar'], JSON_UNESCAPED_UNICODE);

if ($arquivoConvertidoTemporario && is_file($arquivoConvertidoTemporario)) {
    unlink($arquivoConvertidoTemporario);
}
