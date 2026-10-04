<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

$cfg = v2_config();

/**
 * Valor de prosodia seguro para a linha de comando.
 * No Windows o escapeshellarg() do PHP troca "%" por espaco, entao aqui
 * validamos com whitelist em vez de escapar.
 */
function v2_tts_nums(string $valor, string $defeito): string
{
    return preg_match('/^[+-][0-9]{1,3}(%|Hz)?$/', $valor) === 1 ? $valor : $defeito;
}

function v2_tts_mp3(string $wav, string $mp3): bool
{
    if (!is_file($wav)) {
        return false;
    }
    @shell_exec('ffmpeg -y -loglevel error -i ' . escapeshellarg($wav) . ' -codec:a libmp3lame -q:a 4 ' . escapeshellarg($mp3));
    return is_file($mp3);
}

$engine = (string)($_GET['engine'] ?? ($cfg['tts_engine'] ?? 'windows'));
$engine = preg_replace('/[^a-z]/', '', strtolower($engine)) ?: 'windows';
$variant = (string)($_GET['v'] ?? '');
$variant = preg_replace('/[^a-z]/', '', strtolower($variant)) ?: '';

$texto = trim((string)($_GET['text'] ?? ''));
$texto = preg_replace('/\s+/u', ' ', $texto) ?? '';
$texto = mb_substr($texto, 0, 600);

if ($texto === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Texto vazio.';
    exit;
}

if (!v2_tts_available($engine)) {
    $engine = 'windows';
}
if (!v2_tts_available($engine)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nenhum motor de voz disponivel neste servidor.';
    exit;
}

$dir = __DIR__ . '/../data/tts';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$prosodia = v2_tts_prosodia($engine, $variant);
$key = md5($engine . '|' . $variant . '|' . json_encode($prosodia) . '|' . $texto);
$mp3 = $dir . '/' . $key . '.mp3';

if (!is_file($mp3)) {
    $txt = $dir . '/' . $key . '.txt';
    $wav = $dir . '/' . $key . '.wav';
    file_put_contents($txt, $texto);
    $ok = false;

    if ($engine === 'windows') {
        $ps = $dir . '/sapi.ps1';
        if (!is_file($ps)) {
            file_put_contents($ps, "param(\$In,\$Out,\$Voice,\$Rate)\nAdd-Type -AssemblyName System.Speech\n\$s = New-Object System.Speech.Synthesis.SpeechSynthesizer\nif (\$Voice) { try { \$s.SelectVoice(\$Voice) } catch {} }\n\$s.Rate = [int]\$Rate\n\$s.SetOutputToWaveFile(\$Out)\n\$s.Speak([IO.File]::ReadAllText(\$In))\n\$s.Dispose()\n");
        }
        $voz = (string)($cfg['windows_voice'] ?? 'Microsoft Maria');
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($ps)
             . ' -In ' . escapeshellarg($txt) . ' -Out ' . escapeshellarg($wav)
             . ' -Voice ' . escapeshellarg($voz) . ' -Rate ' . (int)($prosodia['rate'] ?? 0);
        @shell_exec($cmd);
        $ok = is_file($wav);
    } elseif ($engine === 'piper') {
        $bin = v2_piper_bin();
        $voice = (string)($cfg['piper_voice'] ?? '');
        if ($bin !== '' && $voice !== '') {
            $cmd = escapeshellarg($bin)
                 . ' --model ' . escapeshellarg($voice)
                 . ' --output_file ' . escapeshellarg($wav)
                 . ' --length_scale ' . (float)($prosodia['length_scale'] ?? 1.0)
                 . ' --sentence_silence ' . (float)($prosodia['sentence_silence'] ?? 0.2)
                 . ' --noise_scale ' . (float)($prosodia['noise_scale'] ?? 0.667)
                 . ' --noise_w ' . (float)($prosodia['noise_w'] ?? 0.8)
                 . ' < ' . escapeshellarg($txt);
            @shell_exec($cmd);
            $ok = is_file($wav);
        }
    } elseif ($engine === 'edge') {
        $exe = v2_edge_exe();
        if ($exe) {
            $cmd = escapeshellarg($exe)
                 . ' --voice ' . escapeshellarg((string)($cfg['edge_voice'] ?? 'pt-BR-FranciscaNeural'))
                 . ' --rate=' . v2_tts_nums((string)($prosodia['rate'] ?? '+0%'), '+0%')
                 . ' --pitch=' . v2_tts_nums((string)($prosodia['pitch'] ?? '+0Hz'), '+0Hz')
                 . ' --volume=' . v2_tts_nums((string)($prosodia['volume'] ?? '+0%'), '+0%')
                 . ' --file ' . escapeshellarg($txt)
                 . ' --write-media ' . escapeshellarg($mp3);
            @shell_exec($cmd);
            $ok = is_file($mp3);
        }
    } elseif ($engine === 'kokoro') {
        $py = v2_python();
        $script = __DIR__ . '/kokoro_tts.py';
        if ($py && is_file($script)) {
            $cmd = escapeshellarg($py) . ' ' . escapeshellarg($script)
                 . ' ' . escapeshellarg($txt) . ' ' . escapeshellarg($wav)
                 . ' ' . escapeshellarg((string)($cfg['kokoro_voice'] ?? 'pf_dora'))
                 . ' ' . (float)($prosodia['speed'] ?? 1.0)
                 . ' ' . (int)($prosodia['pausa_ms'] ?? 0);
            @shell_exec($cmd);
            $ok = is_file($wav);
        }
    }

    if (!$ok) {
        @unlink($txt);
        if ($engine !== 'windows') {
            $destino = 'tts.php?engine=windows&text=' . urlencode($texto);
            if ($variant !== '') {
                $destino .= '&v=' . urlencode($variant);
            }
            header('Location: ' . $destino);
            exit;
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nao consegui gerar o audio.';
        exit;
    }

    if ($engine !== 'edge' && is_file($wav)) {
        if (!v2_tts_mp3($wav, $mp3)) {
            @unlink($txt);
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ffmpeg nao conseguiu converter o audio.';
            exit;
        }
        @unlink($wav);
    }
    @unlink($txt);
}

if (!is_file($mp3)) {
    http_response_code(500);
    exit;
}

header('Content-Type: audio/mpeg');
header('Cache-Control: max-age=86400');
header('Content-Length: ' . (string)filesize($mp3));
readfile($mp3);
