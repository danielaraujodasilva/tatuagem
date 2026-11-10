<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/codex.php';

header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo nao permitido.'], 405);
}

require_auth();
verify_csrf();

if (empty($_FILES['arquivo']) && empty($_FILES['arquivos'])) {
    json_response(['ok' => false, 'message' => 'Nenhum arquivo recebido.'], 422);
}

$dir = codex_uploads_dir();
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    json_response(['ok' => false, 'message' => 'Pasta de uploads indisponivel.'], 500);
}

$permitidas = codex_extensoes_permitidas();
$maxMb = max(1, (int)(getenv('CODEX_WEB_MAX_UPLOAD_MB') ?: ($_SERVER['CODEX_WEB_MAX_UPLOAD_MB'] ?? 25)));
$maxBytes = $maxMb * 1024 * 1024;
$recebidos = [];
if (!empty($_FILES['arquivo'])) {
    $recebidos[] = $_FILES['arquivo'];
}
if (!empty($_FILES['arquivos']) && is_array($_FILES['arquivos']['name'])) {
    foreach ($_FILES['arquivos']['name'] as $i => $nome) {
        $recebidos[] = [
            'name' => $nome,
            'tmp_name' => $_FILES['arquivos']['tmp_name'][$i] ?? '',
            'error' => $_FILES['arquivos']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES['arquivos']['size'][$i] ?? 0,
        ];
    }
}

$anexos = [];
$erros = [];

foreach ($recebidos as $arquivo) {
    $erro = (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($erro !== UPLOAD_ERR_OK) {
        $erros[] = ($arquivo['name'] ?? 'arquivo') . ': falha no envio (' . $erro . ')';
        continue;
    }

    $nomeOriginal = (string)($arquivo['name'] ?? 'arquivo');
    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    if ($extensao === '' || !in_array($extensao, $permitidas, true)) {
        $erros[] = $nomeOriginal . ': extensao nao permitida';
        continue;
    }

    if ((int)($arquivo['size'] ?? 0) > $maxBytes) {
        $erros[] = $nomeOriginal . ': maior que ' . $maxMb . ' MB';
        continue;
    }

    $base = pathinfo($nomeOriginal, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? '';
    $base = trim(substr($base, 0, 60), '-._');
    if ($base === '') {
        $base = 'arquivo';
    }

    $final = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '-' . $base . '.' . $extensao;
    $destino = $dir . DIRECTORY_SEPARATOR . $final;

    $tmp = (string)($arquivo['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp) || !move_uploaded_file($tmp, $destino)) {
        $erros[] = $nomeOriginal . ': nao consegui salvar';
        continue;
    }
    @chmod($destino, 0644);

    $isImagem = in_array($extensao, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true);
    $anexos[] = [
        'name' => $nomeOriginal,
        'arquivo' => $final,
        'path' => $destino,
        'ext' => $extensao,
        'size' => (int)filesize($destino),
        'isImage' => $isImagem,
    ];
}

if (!$anexos) {
    json_response(['ok' => false, 'message' => $erros ? implode('; ', $erros) : 'Nenhum arquivo aceito.'], 422);
}

codex_limpar_uploads((int)(getenv('CODEX_WEB_UPLOAD_DIAS') ?: 14));

json_response(['ok' => true, 'attachments' => $anexos, 'errors' => $erros]);
