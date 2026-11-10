<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth.php';
require_staff();

date_default_timezone_set('America/Sao_Paulo');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');

const AUTOCHAT_MIDIA_DADOS_PADRAO = 'C:\\Users\\server_spd\\Documents\\whatsapp-origin-bridge\\dados';

function autochat_midia_erro(int $status, string $mensagem): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensagem);
}

$relativo = str_replace('\\', '/', trim((string)($_GET['m'] ?? '')));
if ($relativo === '' || str_contains($relativo, "\0") || str_starts_with($relativo, '/') || preg_match('#(^|/)\.\.(/|$)#', $relativo)) {
    autochat_midia_erro(400, 'Midia invalida.');
}

$partes = array_values(array_filter(explode('/', $relativo), static fn(string $parte): bool => $parte !== ''));
$permitidas = ['imagens', 'videos', 'audios', 'documentos'];
if (count($partes) < 2 || !in_array(strtolower($partes[0]), $permitidas, true)) {
    autochat_midia_erro(404, 'Midia nao encontrada.');
}

$dadosDir = rtrim((string)(getenv('AUTOCHAT_DADOS_DIR') ?: AUTOCHAT_MIDIA_DADOS_PADRAO), '\\/');
$baseReal = realpath($dadosDir);
if ($baseReal === false) {
    autochat_midia_erro(404, 'Midia nao encontrada.');
}

$caminhoRelativo = implode(DIRECTORY_SEPARATOR, $partes);
$arquivoReal = realpath($baseReal . DIRECTORY_SEPARATOR . $caminhoRelativo);
$basePrefixo = rtrim(strtolower(str_replace('/', DIRECTORY_SEPARATOR, $baseReal)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$arquivoNormalizado = strtolower(str_replace('/', DIRECTORY_SEPARATOR, (string)$arquivoReal));
if ($arquivoReal === false || !is_file($arquivoReal) || !str_starts_with($arquivoNormalizado, $basePrefixo)) {
    autochat_midia_erro(404, 'Midia nao encontrada.');
}

$extensao = strtolower(pathinfo($arquivoReal, PATHINFO_EXTENSION));
$mimes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm',
    'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'opus' => 'audio/ogg', 'mp3' => 'audio/mpeg',
    'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav', 'pdf' => 'application/pdf',
];
$mime = $mimes[$extensao] ?? (function_exists('mime_content_type') ? (string)mime_content_type($arquivoReal) : 'application/octet-stream');

$tamanho = filesize($arquivoReal);
if ($tamanho === false) {
    autochat_midia_erro(404, 'Midia nao encontrada.');
}

$inicio = 0;
$fim = max(0, $tamanho - 1);
$status = 200;
$range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/i', $range, $match) === 1) {
    if ($match[1] === '' && $match[2] !== '') {
        $sufixo = max(1, (int)$match[2]);
        $inicio = max(0, $tamanho - $sufixo);
    } elseif ($match[1] !== '') {
        $inicio = max(0, (int)$match[1]);
        if ($match[2] !== '') {
            $fim = min($fim, (int)$match[2]);
        }
    }
    if ($tamanho <= 0 || $inicio > $fim || $inicio >= $tamanho) {
        header('Content-Range: bytes */' . $tamanho);
        autochat_midia_erro(416, 'Intervalo invalido.');
    }
    $status = 206;
}

$comprimento = $tamanho > 0 ? ($fim - $inicio + 1) : 0;
http_response_code($status);
header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);
header('Content-Length: ' . $comprimento);
header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($arquivoReal)) . '"');
if ($status === 206) {
    header('Content-Range: bytes ' . $inicio . '-' . $fim . '/' . $tamanho);
}

if ($_SERVER['REQUEST_METHOD'] === 'HEAD' || $comprimento === 0) {
    exit;
}

$handle = fopen($arquivoReal, 'rb');
if ($handle === false) {
    autochat_midia_erro(404, 'Midia nao encontrada.');
}
if ($inicio > 0) {
    fseek($handle, $inicio);
}

$restante = $comprimento;
while ($restante > 0 && !feof($handle)) {
    $bloco = fread($handle, min(65536, $restante));
    if ($bloco === false || $bloco === '') {
        break;
    }
    echo $bloco;
    $restante -= strlen($bloco);
    flush();
}
fclose($handle);
