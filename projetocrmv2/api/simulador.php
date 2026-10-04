<?php
declare(strict_types=1);
/**
 * Bancada do simulador: guarda as conversas de teste, recebe anexos/audio e
 * transcreve audio (faster-whisper no Python do servidor).
 * Nada aqui fala com cliente nenhum - e so para o estudio validar a Irene.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

set_time_limit(300);

/* ---------- pastas e utilidades ---------- */

function sim_dir(): string
{
    $dir = __DIR__ . '/../data/simulador';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function sim_arquivo(string $id): string
{
    return sim_dir() . '/' . $id . '.json';
}

function sim_id_ok(string $id): bool
{
    return preg_match('/^[A-Za-z0-9_-]{4,64}$/', $id) === 1;
}

function sim_json(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function sim_recado(string $erro, int $codigo = 400): void
{
    sim_json(['ok' => false, 'erro' => $erro], $codigo);
}

/** Tipo da mensagem a partir do mime/extensao (os mesmos tipos do WhatsApp). */
function sim_tipo(string $mime, string $nome): string
{
    $mime = strtolower(trim($mime));
    $ext = strtolower((string)pathinfo($nome, PATHINFO_EXTENSION));
    if (strpos($mime, 'audio/') === 0 || in_array($ext, ['ogg', 'oga', 'opus', 'mp3', 'm4a', 'wav', 'webm', 'aac'], true)) {
        return 'audio';
    }
    if (strpos($mime, 'image/') === 0 || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true)) {
        return 'image';
    }
    if (strpos($mime, 'video/') === 0 || in_array($ext, ['mp4', 'mov', 'mkv', 'avi', '3gp'], true)) {
        return 'video';
    }
    return 'document';
}

function sim_mime(string $arquivo): string
{
    $ext = strtolower((string)pathinfo($arquivo, PATHINFO_EXTENSION));
    $mapa = [
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'opus' => 'audio/ogg',
        'wav' => 'audio/wav', 'webm' => 'audio/webm', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'gif' => 'image/gif', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
        'pdf' => 'application/pdf', 'txt' => 'text/plain',
    ];
    return $mapa[$ext] ?? 'application/octet-stream';
}

/** Roda um comando e devolve a saida, com um teto de tempo (segundos). */
function sim_exec(string $cmd, int $limite = 180): string
{
    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return '';
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $saida = '';
    $fim = time() + $limite;
    while (true) {
        $saida .= (string)stream_get_contents($pipes[1]);
        $saida .= (string)stream_get_contents($pipes[2]);
        $st = proc_get_status($proc);
        if (!$st['running'] || time() > $fim) {
            break;
        }
        usleep(150000);
    }
    $saida .= (string)stream_get_contents($pipes[1]);
    $saida .= (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_get_status($proc)['running']) {
        proc_terminate($proc);
    }
    proc_close($proc);
    return $saida;
}

/** Ultimo JSON impresso pelo script de transcricao. */
function sim_json_saida(string $saida): ?array
{
    $saida = trim($saida);
    if ($saida === '') {
        return null;
    }
    $j = json_decode($saida, true);
    if (is_array($j)) {
        return $j;
    }
    if (preg_match('/\{(?:.|\s)*\}\s*$/', $saida, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j)) {
            return $j;
        }
    }
    return null;
}

/** Audio -> texto com o mesmo Whisper que o CRM usa nas conversas reais. */
function sim_transcrever(string $arquivo): array
{
    $py = v2_python();
    if ($py === null || !is_file($py)) {
        return ['ok' => false, 'erro' => 'Python do Whisper nao encontrado'];
    }
    $script = realpath(__DIR__ . '/../../crm/scripts/transcribe_audio.py');
    if ($script === false) {
        return ['ok' => false, 'erro' => 'script de transcricao nao encontrado'];
    }
    $alvo = $arquivo;
    $ff = v2_which('ffmpeg');
    if ($ff !== null) {
        $wav = $arquivo . '.wav';
        sim_exec(escapeshellarg($ff) . ' -y -loglevel error -i ' . escapeshellarg($arquivo)
            . ' -af apad=pad_dur=1 -ar 16000 -ac 1 -vn ' . escapeshellarg($wav), 60);
        if (is_file($wav) && filesize($wav) > 0) {
            $alvo = $wav;
        }
    }
    $saida = sim_exec(escapeshellarg($py) . ' ' . escapeshellarg($script) . ' '
        . escapeshellarg($alvo) . ' base faster', 240);
    if ($alvo !== $arquivo) {
        @unlink($alvo);
    }
    $j = sim_json_saida($saida);
    if (is_array($j) && !empty($j['ok']) && trim((string)($j['text'] ?? '')) !== '') {
        return ['ok' => true, 'texto' => trim((string)$j['text'])];
    }
    return ['ok' => false, 'erro' => 'nao consegui transcrever o audio'];
}

function sim_rmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $c = $dir . '/' . $f;
        if (is_dir($c)) {
            sim_rmdir($c);
        } else {
            @unlink($c);
        }
    }
    @rmdir($dir);
}

/* ---------- entrada ---------- */

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = [];
}
$acao = strtolower(trim((string)($in['acao'] ?? $_POST['acao'] ?? $_GET['acao'] ?? '')));

/* ---------- salvar a conversa de teste ---------- */

if ($acao === 'salvar') {
    $msgs = is_array($in['mensagens'] ?? null) ? $in['mensagens'] : [];
    if (!$msgs) {
        sim_recado('conversa vazia');
    }
    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $registro = [
        'id' => $id,
        'criado_em' => date('c'),
        'titulo' => mb_substr(trim((string)($in['titulo'] ?? '')), 0, 140),
        'modelo' => mb_substr(trim((string)($in['modelo'] ?? '')), 0, 120),
        'mensagens' => $msgs,
    ];
    $bytes = @file_put_contents(
        sim_arquivo($id),
        (string)json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    if ($bytes === false) {
        sim_recado('nao consegui salvar a conversa', 500);
    }
    sim_json(['ok' => true, 'id' => $id]);
}

/* ---------- lista das conversas salvas ---------- */

if ($acao === 'listar') {
    $lista = [];
    foreach (glob(sim_dir() . '/*.json') ?: [] as $f) {
        $j = json_decode((string)@file_get_contents($f), true);
        if (!is_array($j)) {
            continue;
        }
        $msgs = is_array($j['mensagens'] ?? null) ? $j['mensagens'] : [];
        $motores = [];
        $audios = 0;
        foreach ($msgs as $m) {
            $mo = trim((string)($m['motor'] ?? ''));
            if ($mo !== '' && !in_array($mo, $motores, true)) {
                $motores[] = $mo;
            }
            if ((string)($m['tipo'] ?? '') === 'audio') {
                $audios++;
            }
        }
        $lista[] = [
            'id' => (string)($j['id'] ?? basename($f, '.json')),
            'criado_em' => (string)($j['criado_em'] ?? ''),
            'titulo' => (string)($j['titulo'] ?? ''),
            'modelo' => (string)($j['modelo'] ?? ''),
            'mensagens' => count($msgs),
            'motores' => $motores,
            'audios' => $audios,
        ];
    }
    usort($lista, static fn($a, $b) => strcmp((string)$b['criado_em'], (string)$a['criado_em']));
    sim_json(['ok' => true, 'conversas' => $lista]);
}

/* ---------- abrir uma conversa salva ---------- */

if ($acao === 'ler') {
    $id = (string)($in['id'] ?? $_GET['id'] ?? '');
    if (!sim_id_ok($id) || !is_file(sim_arquivo($id))) {
        sim_recado('conversa nao encontrada', 404);
    }
    $j = json_decode((string)@file_get_contents(sim_arquivo($id)), true);
    if (!is_array($j)) {
        sim_recado('conversa corrompida', 500);
    }
    sim_json(['ok' => true, 'conversa' => $j]);
}

/* ---------- apagar uma conversa salva ---------- */

if ($acao === 'apagar') {
    $id = (string)($in['id'] ?? $_POST['id'] ?? '');
    if (!sim_id_ok($id) || !is_file(sim_arquivo($id))) {
        sim_recado('conversa nao encontrada', 404);
    }
    $j = json_decode((string)@file_get_contents(sim_arquivo($id)), true);
    foreach ((is_array($j['mensagens'] ?? null) ? $j['mensagens'] : []) as $m) {
        $sessao = (string)($m['anexo']['sessao'] ?? '');
        if ($sessao !== '' && sim_id_ok($sessao)) {
            sim_rmdir(sim_dir() . '/midia/' . $sessao);
        }
    }
    @unlink(sim_arquivo($id));
    sim_json(['ok' => true]);
}

/* ---------- anexo (foto, video, documento ou audio do cliente) ---------- */

if ($acao === 'anexar') {
    $sessao = (string)($_POST['sessao'] ?? '');
    if (!sim_id_ok($sessao)) {
        sim_recado('sessao invalida');
    }
    $arq = $_FILES['arquivo'] ?? null;
    if (!is_array($arq) || (int)($arq['error'] ?? 1) !== UPLOAD_ERR_OK) {
        sim_recado('nao recebi o arquivo');
    }
    if ((int)($arq['size'] ?? 0) > 25 * 1024 * 1024) {
        sim_recado('arquivo grande demais (limite 25MB)');
    }
    $nome = (string)($arq['name'] ?? 'arquivo');
    $tipo = sim_tipo((string)($arq['type'] ?? ''), $nome);
    $ext = preg_replace('/[^a-z0-9]/', '', strtolower((string)pathinfo($nome, PATHINFO_EXTENSION))) ?: 'dat';
    $dir = sim_dir() . '/midia/' . $sessao;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $guardado = date('His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!@move_uploaded_file((string)$arq['tmp_name'], $dir . '/' . $guardado)) {
        sim_recado('nao consegui guardar o arquivo', 500);
    }
    $limpo = preg_replace('/[^\p{L}\p{N} ._\-()]/u', '', $nome);
    $resposta = [
        'ok' => true,
        'sessao' => $sessao,
        'arquivo' => $guardado,
        'nome' => mb_substr(trim((string)($limpo ?? $nome)) !== '' ? (string)$limpo : 'arquivo', 0, 80),
        'tipo' => $tipo,
        'url' => 'api/simulador.php?acao=midia&id=' . rawurlencode($sessao) . '&arq=' . rawurlencode($guardado),
        'texto' => '',
        'transcrito' => false,
    ];
    if ($tipo === 'audio') {
        $t = sim_transcrever($dir . '/' . $guardado);
        $resposta['texto'] = (string)($t['texto'] ?? '');
        $resposta['transcrito'] = !empty($t['ok']);
    }
    sim_json($resposta);
}

/* ---------- entrega a midia guardada (so para quem esta logado) ---------- */

if ($acao === 'midia') {
    $sessao = (string)($_GET['id'] ?? '');
    $arq = basename((string)($_GET['arq'] ?? ''));
    if (!sim_id_ok($sessao) || $arq === '' || strpos($arq, '.') === 0) {
        sim_recado('arquivo invalido', 404);
    }
    $caminho = sim_dir() . '/midia/' . $sessao . '/' . $arq;
    if (!is_file($caminho)) {
        sim_recado('arquivo nao encontrado', 404);
    }
    header('Content-Type: ' . sim_mime($caminho));
    header('Content-Length: ' . (string)filesize($caminho));
    header('Content-Disposition: inline; filename="' . rawurlencode($arq) . '"');
    header('Cache-Control: private, max-age=600');
    readfile($caminho);
    exit;
}

sim_recado('acao desconhecida');
