<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

set_time_limit(180);

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

/* Sem escolha na tela Voz, vale o que ficou salvo (ou o config.local.php). */
$voz = v2_voz_atual();

$engine = (string)($_GET['engine'] ?? $voz['engine']);
$engine = preg_replace('/[^a-z]/', '', strtolower($engine)) ?: 'windows';

$variant = (string)($_GET['v'] ?? $voz['variacao']);
/* "calor_rapido" tem underline: sem ele o preset nao casava e o ritmo era ignorado. */
$variant = preg_replace('/[^a-z_]/', '', strtolower($variant)) ?: '';

$nomeVoz = trim(preg_replace('/[^A-Za-z0-9 _.\-:\\\\\/]/', '', (string)($_GET['voice'] ?? $voz['voz'])) ?? '');
$nomeVoz = mb_substr($nomeVoz, 0, 120);

$velocidade = isset($_GET['vel']) && $_GET['vel'] !== '' ? (int)$_GET['vel'] : (int)$voz['vel'];
$tom = isset($_GET['tom']) && $_GET['tom'] !== '' ? (int)$_GET['tom'] : (int)$voz['tom'];

/** Erro do gerador (texto normal ou JSON, quando a tela pediu). */
function v2_tts_erro(string $mensagem, int $codigo = 500): void
{
    $querJson = isset($_GET['json']);
    http_response_code($codigo);
    header('Content-Type: ' . ($querJson ? 'application/json' : 'text/plain') . '; charset=utf-8');
    echo $querJson ? json_encode(['ok' => false, 'erro' => $mensagem], JSON_UNESCAPED_UNICODE) : $mensagem;
    exit;
}

$texto = trim((string)($_GET['text'] ?? ''));
$texto = preg_replace('/\s+/u', ' ', $texto) ?? '';
$texto = mb_substr($texto, 0, 600);

if ($texto === '') {
    v2_tts_erro('Texto vazio.', 400);
}

if (!v2_tts_available($engine)) {
    $engine = 'windows';
}
if (!v2_tts_available($engine)) {
    v2_tts_erro('Nenhum motor de voz disponivel neste servidor.', 503);
}

$dir = __DIR__ . '/../data/tts';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$prosodia = v2_voz_ajustar($engine, v2_tts_prosodia($engine, $variant), $velocidade, $tom);
$key = md5($engine . '|' . $variant . '|' . $nomeVoz . '|' . json_encode($prosodia) . '|' . $texto);
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
        $vozWin = $nomeVoz !== '' ? $nomeVoz : (string)($cfg['windows_voice'] ?? 'Microsoft Maria');
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($ps)
             . ' -In ' . escapeshellarg($txt) . ' -Out ' . escapeshellarg($wav)
             . ' -Voice ' . escapeshellarg($vozWin) . ' -Rate ' . (int)($prosodia['rate'] ?? 0);
        @shell_exec($cmd);
        $ok = is_file($wav);
    } elseif ($engine === 'piper') {
        $bin = v2_piper_bin();
        $voice = is_file($nomeVoz) ? $nomeVoz : (string)($cfg['piper_voice'] ?? '');
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
                 . ' --voice ' . escapeshellarg($nomeVoz !== '' ? $nomeVoz : (string)($cfg['edge_voice'] ?? 'pt-BR-FranciscaNeural'))
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
                 . ' ' . escapeshellarg($nomeVoz !== '' ? $nomeVoz : (string)($cfg['kokoro_voice'] ?? 'pf_dora'))
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
            if (isset($_GET['json'])) {
                $destino .= '&json=1';
            }
            header('Location: ' . $destino);
            exit;
        }
        v2_tts_erro('Nao consegui gerar o audio.');
    }

    if ($engine !== 'edge' && is_file($wav)) {
        if (!v2_tts_mp3($wav, $mp3)) {
            @unlink($txt);
            v2_tts_erro('ffmpeg nao conseguiu converter o audio.');
        }
        @unlink($wav);
    }
    @unlink($txt);
}

if (!is_file($mp3)) {
    v2_tts_erro('Audio nao foi gerado.');
}

/* A tela Voz pede so o link; o audio ja esta em cache quando ela toca. */
if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'engine' => $engine,
        'voz' => $nomeVoz,
        'variacao' => $variant,
        'vel' => $velocidade,
        'tom' => $tom,
        'url' => 'tts.php?' . http_build_query([
            'engine' => $engine,
            'voice' => $nomeVoz,
            'v' => $variant,
            'vel' => (string)$velocidade,
            'tom' => (string)$tom,
            'text' => $texto,
        ], '', '&', PHP_QUERY_RFC3986),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: audio/mpeg');
header('Cache-Control: max-age=86400');
header('Content-Length: ' . (string)filesize($mp3));
readfile($mp3);
