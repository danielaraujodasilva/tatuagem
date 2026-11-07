<?php
/**
 * Gera derivados WebP da galeria para o site.
 *
 * Uso (somente CLI):
 *   php tools/gerar_thumbs_galeria.php
 *   php tools/gerar_thumbs_galeria.php --so-thumbs
 *
 * - thumbs/: 480px de largura, usados no grid (leve)
 * - large/:  1600px de largura, usados no modal (opcional; --so-thumbs ignora)
 *
 * O site funciona sem os derivados (cai na imagem original), mas a versão
 * otimizada só usa thumbs/large quando os arquivos existem.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
set_time_limit(0);
ini_set('memory_limit', '512M');

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "GD sem suporte a WebP. Habilite a extensão WebP no PHP.\n");
    exit(1);
}

$onlyThumbs = in_array('--so-thumbs', $argv, true);
$root = dirname(__DIR__);
$g = $root . '/galeria';
$thumbDir = $g . '/thumbs';
$largeDir = $g . '/large';

if (!is_dir($g)) {
    fwrite(STDERR, "Pasta da galeria não encontrada: {$g}\n");
    exit(1);
}
foreach (array_filter([$thumbDir, $onlyThumbs ? null : $largeDir]) as $d) {
    if (!is_dir($d) && !mkdir($d, 0775, true)) {
        fwrite(STDERR, "Não consegui criar: {$d}\n");
        exit(1);
    }
}

$files = [];
foreach (scandir($g) as $f) {
    if (preg_match('/\.(jpe?g|png|webp|gif)$/i', $f)) { $files[] = $f; }
}
natsort($files);
$files = array_values($files);
$total = count($files);
echo "Galeria: {$total} imagens\n";
echo $onlyThumbs ? "Modo: somente thumbs\n" : "Modo: thumbs + large\n";

$ok = 0; $skip = 0; $fail = 0;
$start = microtime(true);

foreach ($files as $i => $file) {
    $src = $g . '/' . $file;
    $hash = substr(md5($file), 0, 16);
    $thumb = $thumbDir . '/' . $hash . '.webp';
    $large = $largeDir . '/' . $hash . '.webp';

    $needThumb = !is_file($thumb);
    $needLarge = !$onlyThumbs && !is_file($large);
    if (!$needThumb && !$needLarge) { $skip++; continue; }

    $info = @getimagesize($src);
    if (!$info) { $fail++; echo "FAIL info: {$file}\n"; continue; }
    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($w < 1 || $h < 1) { $fail++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $im = null;
    if ($ext === 'jpg' || $ext === 'jpeg') { $im = @imagecreatefromjpeg($src); }
    elseif ($ext === 'png') { $im = @imagecreatefrompng($src); }
    elseif ($ext === 'webp') { $im = @imagecreatefromwebp($src); }
    elseif ($ext === 'gif') { $im = @imagecreatefromgif($src); }
    if (!$im) { $fail++; echo "FAIL read: {$file}\n"; continue; }

    if ($ext === 'png' || $ext === 'gif') {
        @imagepalettetotruecolor($im);
        @imagealphablending($im, true);
        @imagesavealpha($im, true);
    }

    try {
        if ($needThumb) {
            $tw = min(480, $w);
            $th = max(1, (int)round($h * ($tw / $w)));
            $t = imagescale($im, $tw, $th, IMG_BILINEAR_FIXED);
            if ($t !== false) { imagewebp($t, $thumb, 78); imagedestroy($t); }
        }
        if ($needLarge) {
            $lw = min(1600, $w);
            $lh = max(1, (int)round($h * ($lw / $w)));
            $l = imagescale($im, $lw, $lh, IMG_BILINEAR_FIXED);
            if ($l !== false) { imagewebp($l, $large, 82); imagedestroy($l); }
        }
        $ok++;
    } catch (Throwable $e) {
        $fail++;
        echo "FAIL scale: {$file} " . $e->getMessage() . "\n";
    } finally {
        imagedestroy($im);
    }

    if (($i + 1) % 25 === 0) {
        $el = round(microtime(true) - $start, 1);
        echo "progress " . ($i + 1) . "/{$total} ok={$ok} skip={$skip} fail={$fail} {$el}s\n";
        flush();
    }
}

echo "DONE ok={$ok} skip={$skip} fail={$fail} tempo=" . round(microtime(true) - $start, 1) . "s\n";
