<?php
declare(strict_types=1);
/**
 * API da galeria do acervo padronizado.
 * Todas as acoes exigem o token (?k=...).
 *
 *   ?a=lista                 -> JSON com todos os arquivos
 *   ?a=thumb&f=ARQUIVO       -> miniatura (gera e guarda em cache na 1a vez)
 *   ?a=arquivo&f=ARQUIVO&dl=1-> arquivo original (com suporte a Range p/ video)
 *   ?a=zip  (POST f[])       -> zip das selecionadas
 *   ?a=apagar (POST f[])     -> move as selecionadas para _lixeira
 */
@ini_set('memory_limit', '768M');
@ini_set('zlib.output_compression', '0');
@set_time_limit(600);
while (ob_get_level() > 0) { ob_end_clean(); }

$BASE     = 'C:/Users/server_spd/Downloads/PADRONIZADO-COMPLETO';
$LIXEIRA  = $BASE . '/_lixeira';
$CACHE    = __DIR__ . '/acervo';
$THUMBS   = $CACHE . '/thumbs';
$LISTA    = $CACHE . '/lista.json';
$FFMPEG   = 'C:/ffmpeg/bin/ffmpeg.exe';
$MAX_ZIP_BYTES = 800 * 1024 * 1024;
$MAX_ZIP_ITENS = 150;

$keyFile = __DIR__ . '/progresso-key.local.php';
$token   = is_file($keyFile) ? (string)(require $keyFile) : '';
$k       = (string)($_REQUEST['k'] ?? '');
if ($token !== '' && !hash_equals($token, $k)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'erro' => 'token invalido']);
    exit;
}

$acao = (string)($_REQUEST['a'] ?? 'lista');

/* ---------------------------------------------------------------- utils */

function json_out($dados, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ext_ok(string $arq): bool {
    $e = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
    return in_array($e, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'], true);
}

/** Resolve o nome para um caminho dentro da pasta, barrando path traversal. */
function caminho(string $BASE, string $nome): ?string {
    $nome = basename(str_replace('\\', '/', $nome));
    if ($nome === '' || $nome === '.' || $nome === '..') return null;
    if (!preg_match('/^[A-Za-z0-9 _\-\.\(\)\[\]]+$/', $nome)) return null;
    if (!ext_ok($nome)) return null;
    $p = $BASE . '/' . $nome;
    $r = realpath($p);
    $rb = realpath($BASE);
    if ($r === false || $rb === false) return null;
    if (strncmp($r, $rb, strlen($rb)) !== 0) return null;
    return $r;
}

function mime_de(string $arq): string {
    $e = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
    $mapa = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
             'webp' => 'image/webp', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime'];
    return $mapa[$e] ?? 'application/octet-stream';
}

/* ---------------------------------------------------------------- lista */

function montar_lista(string $BASE): array {
    $itens = [];
    $tempo = time();
    foreach (scandir($BASE) as $n) {
        if ($n === '.' || $n === '..') continue;
        $p = $BASE . '/' . $n;
        if (!is_file($p) || !ext_ok($n)) continue;
        $e = strtolower(pathinfo($n, PATHINFO_EXTENSION));
        $foto = in_array($e, ['jpg', 'jpeg', 'png', 'webp'], true);
        $item = [
            'n' => $n,
            't' => $foto ? 'foto' : 'video',
            'b' => (int)filesize($p),
            'm' => (int)filemtime($p),
            'w' => 0,
            'h' => 0,
        ];
        if ($foto) {
            $info = @getimagesize($p);
            if ($info) { $item['w'] = (int)$info[0]; $item['h'] = (int)$info[1]; }
        }
        $itens[] = $item;
    }
    usort($itens, function ($a, $b) { return strcmp($b['n'], $a['n']); });
    $mb = 0.0;
    foreach ($itens as $i) { $mb += $i['b']; }
    return [
        'gerado_em' => date('d/m/Y H:i:s'),
        'total' => count($itens),
        'mb' => round($mb / 1048576, 1),
        'itens' => $itens,
    ];
}

if ($acao === 'lista') {
    $precisa = true;
    if (is_file($LISTA)) {
        $velho = json_decode((string)file_get_contents($LISTA), true);
        if (is_array($velho) && isset($velho['carimbo'])) {
            $agora = 0; $qtd = 0;
            foreach (scandir($BASE) as $n) {
                $p = $BASE . '/' . $n;
                if (is_file($p) && ext_ok($n)) { $qtd++; $agora = max($agora, (int)filemtime($p)); }
            }
            if ($agora === (int)$velho['carimbo'] && $qtd === (int)$velho['qtd']
                && (time() - (int)($velho['ts'] ?? 0)) < 120) {
                $precisa = false;
            }
        }
    }
    if ($precisa) {
        $dados = montar_lista($BASE);
        $carimbo = 0; $qtd = 0;
        foreach ($dados['itens'] as $i) { $carimbo = max($carimbo, $i['m']); $qtd++; }
        $dados['carimbo'] = $carimbo;
        $dados['qtd'] = $qtd;
        $dados['ts'] = time();
        if (!is_dir($CACHE)) @mkdir($CACHE, 0775, true);
        @file_put_contents($LISTA, json_encode($dados, JSON_UNESCAPED_UNICODE), LOCK_EX);
    } else {
        $dados = $velho;
    }
    json_out(['ok' => true] + $dados);
}

/* ------------------------------------------------------------- thumbnails */

function gerar_thumb(string $src, string $dst, string $ffmpeg, int $max = 420): bool {
    $e = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    if (in_array($e, ['mp4', 'mov'], true)) {
        if (!is_file($ffmpeg)) return false;
        $filtro = ' -frames:v 1 -vf "scale=' . $max . ':-2" -q:v 4 ';
        foreach (['-ss 1 -i ', '-i '] as $entrada) {
            $cmd = escapeshellarg($ffmpeg) . ' -v error -y ' . $entrada . escapeshellarg($src) . $filtro . escapeshellarg($dst);
            @exec($cmd . ' 2>&1', $o, $rc);
            if ($rc === 0 && is_file($dst)) return true;
        }
        return false;
    }
    $info = @getimagesize($src);
    if (!$info) return false;
    [$w, $h] = $info;
    if ($w < 1 || $h < 1) return false;
    $r = min($max / $w, $max / $h, 1.0);
    $nw = max(1, (int)round($w * $r));
    $nh = max(1, (int)round($h * $r));
    switch ($e) {
        case 'png':  $im = @imagecreatefrompng($src); break;
        case 'webp': $im = @imagecreatefromwebp($src); break;
        default:     $im = @imagecreatefromjpeg($src); break;
    }
    if (!$im) return false;
    $th = imagecreatetruecolor($nw, $nh);
    imagefill($th, 0, 0, imagecolorallocate($th, 16, 16, 20));
    imagecopyresampled($th, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($th, $dst, 82);
    imagedestroy($im);
    imagedestroy($th);
    return (bool)$ok;
}

if ($acao === 'thumb') {
    $src = caminho($BASE, (string)($_GET['f'] ?? ''));
    if ($src === null) { http_response_code(404); exit; }
    if (!is_dir($THUMBS)) @mkdir($THUMBS, 0775, true);
    $dst = $THUMBS . '/' . basename($src) . '.jpg';
    if (!is_file($dst) || filemtime($dst) < filemtime($src)) {
        if (!gerar_thumb($src, $dst, $FFMPEG)) { http_response_code(500); exit; }
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=604800');
    header('Content-Length: ' . filesize($dst));
    readfile($dst);
    exit;
}

/* ---------------------------------------------------------------- arquivo */

if ($acao === 'arquivo') {
    $src = caminho($BASE, (string)($_GET['f'] ?? ''));
    if ($src === null || !is_file($src)) { http_response_code(404); exit; }
    $nome = basename($src);
    $dl = isset($_GET['dl']);
    $tam = filesize($src);
    $ini = 0; $fim = $tam - 1;

    header('Content-Type: ' . mime_de($src));
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    if ($dl) {
        $seguro = preg_replace('/[^A-Za-z0-9 _\-\.]/', '_', $nome);
        header('Content-Disposition: attachment; filename="' . $seguro . '"');
    }

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') { $ini = (int)$m[1]; }
        if ($m[2] !== '') { $fim = (int)$m[2]; }
        if ($ini > $fim || $ini >= $tam) {
            http_response_code(416);
            header('Content-Range: bytes */' . $tam);
            exit;
        }
        $fim = min($fim, $tam - 1);
        http_response_code(206);
        header('Content-Range: bytes ' . $ini . '-' . $fim . '/' . $tam);
    }
    $resta = $fim - $ini + 1;
    header('Content-Length: ' . $resta);
    $fp = fopen($src, 'rb');
    if (!$fp) { http_response_code(500); exit; }
    fseek($fp, $ini);
    while ($resta > 0 && !feof($fp)) {
        $bloco = fread($fp, (int)min(262144, $resta));
        if ($bloco === false) break;
        echo $bloco;
        $resta -= strlen($bloco);
        flush();
    }
    fclose($fp);
    exit;
}

/* -------------------------------------------------------------------- zip */

if ($acao === 'zip') {
    $lista = $_POST['f'] ?? [];
    if (!is_array($lista) || !$lista) json_out(['ok' => false, 'erro' => 'nada selecionado'], 400);

    $caminhos = [];
    $total = 0;
    foreach ($lista as $nome) {
        $c = caminho($BASE, (string)$nome);
        if ($c !== null && is_file($c)) { $caminhos[] = $c; $total += filesize($c); }
    }
    if (!$caminhos) json_out(['ok' => false, 'erro' => 'arquivos nao encontrados'], 404);
    if (count($caminhos) > $MAX_ZIP_ITENS || $total > $MAX_ZIP_BYTES) {
        json_out(['ok' => false, 'erro' => sprintf(
            'Selecao grande demais para zip (%d arquivos, %.0f MB). Limite: %d arquivos / %d MB. Baixe em partes.',
            count($caminhos), $total / 1048576, $MAX_ZIP_ITENS, (int)($MAX_ZIP_BYTES / 1048576))], 413);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'acervo');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        json_out(['ok' => false, 'erro' => 'falha ao criar zip'], 500);
    }
    foreach ($caminhos as $c) {
        $n = basename($c);
        $zip->addFile($c, $n);
        $zip->setCompressionName($n, ZipArchive::CM_STORE);
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="acervo-' . date('Ymd-His') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/* ----------------------------------------------------------------- apagar */

if ($acao === 'apagar') {
    $lista = $_POST['f'] ?? [];
    if (!is_array($lista) || !$lista) json_out(['ok' => false, 'erro' => 'nada selecionado'], 400);

    $destino = $LIXEIRA . '/' . date('Ymd-His');
    if (!is_dir($destino)) @mkdir($destino, 0775, true);
    $movidos = [];
    $falhas = [];
    foreach ($lista as $nome) {
        $c = caminho($BASE, (string)$nome);
        if ($c === null || !is_file($c)) { $falhas[] = (string)$nome; continue; }
        $alvo = $destino . '/' . basename($c);
        if (@rename($c, $alvo)) {
            $movidos[] = basename($c);
            @unlink($THUMBS . '/' . basename($c) . '.jpg');
        } else {
            $falhas[] = basename($c);
        }
    }
    json_out(['ok' => true, 'movidos' => count($movidos), 'falhas' => $falhas,
              'lixeira' => $destino]);
}

json_out(['ok' => false, 'erro' => 'acao desconhecida: ' . $acao], 400);
