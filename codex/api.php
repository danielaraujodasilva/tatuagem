<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/codex.php';

$action = (string)($_GET['action'] ?? '');

if (!codex_ip_permitido()) {
    json_response(['ok' => false, 'message' => 'Acesso nao permitido deste endereco.'], 403);
}

try {
    if ($action === 'login') {
        verify_csrf();
        $input = json_input();
        $email = trim((string)($input['email'] ?? ''));
        $chave = codex_login_chave($email);

        $bloqueio = codex_login_bloqueado($chave);
        if ($bloqueio > 0) {
            json_response([
                'ok' => false,
                'message' => 'Muitas tentativas. Tente de novo em ' . (int)ceil($bloqueio / 60) . ' min.',
            ], 429);
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify((string)($input['password'] ?? ''), (string)$user['password_hash'])) {
            codex_login_falhou($chave);
            json_response(['ok' => false, 'message' => 'E-mail ou senha invalidos.'], 422);
        }

        codex_login_ok($chave);
        $_SESSION['user_id'] = (int)$user['id'];
        audit('login', 'user', (int)$user['id']);
        json_response(['ok' => true]);
    }

    $user = require_auth();

    if ($action === 'stream') {
        // Libera o lock da sessao: sem isso o SSE segura todas as outras chamadas.
        session_write_close();
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-store');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        @set_time_limit(0);
        ignore_user_abort(false);
        codex_bridge_stream('/run/stream?id=' . urlencode((string)($_GET['id'] ?? '')), 21600);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        verify_csrf();
    }

    match ($action) {
        'logout' => sair(),
        'bootstrap' => json_response([
            'ok' => true,
            'user' => ['name' => $user['name'], 'email' => $user['email']],
            'bridge' => codex_bridge_call('GET', '/health', null, 4),
        ]),
        'sessions' => json_response(codex_bridge_call('GET', '/sessions', null, 25)),
        'session' => json_response(codex_bridge_call('GET', '/session?id=' . urlencode((string)($_GET['id'] ?? '')), null, 30)),
        'session-rename' => renomear_sessao(),
        'session-delete' => apagar_sessao(),
        'session-restore' => restaurar_sessao(),
        'lixeira' => json_response(codex_bridge_call('GET', '/lixeira', null, 10)),
        'search' => json_response(codex_bridge_call('GET', '/search?q=' . urlencode((string)($_GET['q'] ?? '')), null, 45)),
        'send' => enviar(),
        'run' => json_response(codex_bridge_call('GET', '/run?id=' . urlencode((string)($_GET['id'] ?? '')), null, 15)),
        'cancel' => cancelar(),
        'voz' => transcrever_voz(),
        'falar' => falar_texto(),
        'voz-status' => json_response(codex_voz_status()),
        'voz-aquecer' => aquecer_voz(),
        default => json_response(['ok' => false, 'message' => 'Acao desconhecida.'], 404),
    };
} catch (Throwable $e) {
    global $config;
    $payload = ['ok' => false, 'message' => 'Erro interno.'];
    if (!empty($config['debug'])) {
        $payload['detail'] = $e->getMessage();
    }
    json_response($payload, 500);
}

function sair(): never
{
    $_SESSION = [];
    session_destroy();
    json_response(['ok' => true]);
}

function enviar(): never
{
    $input = json_input();
    $prompt = trim((string)($input['prompt'] ?? ''));

    $images = [];
    foreach ((array)($input['images'] ?? []) as $caminho) {
        $seguro = codex_upload_path_seguro((string)$caminho);
        if ($seguro !== null) {
            $images[] = $seguro;
        }
    }

    if ($prompt === '' && !$images) {
        json_response(['ok' => false, 'message' => 'Escreva uma mensagem ou anexe um arquivo.'], 422);
    }
    if ($prompt === '') {
        $prompt = 'Analise o(s) anexo(s):';
    }

    $threadId = trim((string)($input['threadId'] ?? ''));
    $usuario = current_user();

    $resposta = codex_bridge_call('POST', '/send', [
        'prompt' => $prompt,
        'threadId' => $threadId !== '' ? $threadId : null,
        'fonte' => (string)($input['fonte'] ?? ''),
        'cwd' => (string)($input['cwd'] ?? ''),
        'images' => $images,
        // Ficam gravados junto da mensagem para o modal "informacoes".
        'usuario' => $usuario ? [
            'id' => (int)$usuario['id'],
            'nome' => (string)$usuario['name'],
            'email' => (string)$usuario['email'],
        ] : null,
        'ip' => codex_client_ip(),
        'userAgent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
    ], 20);

    // 409 = a conversa ja tem um turno rodando (outra aba, por exemplo).
    // Devolve o runId para a pagina conseguir acompanhar esse turno.
    $status = (int)($resposta['bridge_status'] ?? 0);
    unset($resposta['bridge_status']);
    if ($status >= 400) {
        json_response($resposta, $status === 409 ? 409 : 502);
    }
    json_response($resposta);
}

function cancelar(): never
{
    $input = json_input();
    json_response(codex_bridge_call('POST', '/cancel', [
        'runId' => (string)($input['runId'] ?? ''),
    ], 10));
}

function renomear_sessao(): never
{
    $input = json_input();
    $id = trim((string)($input['id'] ?? ''));
    $nome = trim((string)($input['nome'] ?? ''));
    if ($id === '' || $nome === '') {
        json_response(['ok' => false, 'message' => 'Informe o nome da conversa.'], 422);
    }
    json_response(codex_bridge_call('POST', '/session-rename', ['id' => $id, 'nome' => $nome], 15));
}

function apagar_sessao(): never
{
    $input = json_input();
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') {
        json_response(['ok' => false, 'message' => 'Conversa invalida.'], 422);
    }
    json_response(codex_bridge_call('POST', '/session-delete', ['id' => $id], 20));
}

function restaurar_sessao(): never
{
    $input = json_input();
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') {
        json_response(['ok' => false, 'message' => 'Conversa invalida.'], 422);
    }
    json_response(codex_bridge_call('POST', '/session-restore', ['id' => $id], 20));
}

/** Pre-aquece o worker de voz em background (chamado ao abrir a tela). */
function aquecer_voz(): never
{
    set_time_limit(30);
    session_write_close();
    codex_voz_iniciar();
    json_response(['ok' => true]);
}

/**
 * Recebe o audio gravado no navegador (webm/ogg/mp4), converte para WAV
 * 16 kHz mono e manda para o Whisper local. Devolve o texto.
 */
function transcrever_voz(): never
{
    set_time_limit(180);
    session_write_close();

    if (empty($_FILES['audio']) || !is_array($_FILES['audio'])) {
        json_response(['ok' => false, 'message' => 'Nenhum audio recebido.'], 422);
    }
    $arquivo = $_FILES['audio'];
    $erro = (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($erro !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'message' => 'Falha no envio do audio (' . $erro . ').'], 422);
    }

    $tmp = (string)($arquivo['tmp_name'] ?? '');
    $tamanho = (int)($arquivo['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp) || $tamanho <= 0) {
        json_response(['ok' => false, 'message' => 'Audio invalido.'], 422);
    }
    if ($tamanho > 20 * 1024 * 1024) {
        json_response(['ok' => false, 'message' => 'Audio maior que 20 MB.'], 413);
    }

    $ffmpeg = codex_ffmpeg();
    if ($ffmpeg === null) {
        json_response(['ok' => false, 'message' => 'ffmpeg nao encontrado no servidor.'], 503);
    }
    if (!function_exists('exec')) {
        json_response(['ok' => false, 'message' => 'exec() desabilitado no PHP.'], 503);
    }

    $base = tempnam(sys_get_temp_dir(), 'codexvoz_');
    if ($base === false) {
        json_response(['ok' => false, 'message' => 'Sem espaco temporario.'], 500);
    }
    $entrada = $base . '.audio';
    $wav = $base . '.wav';
    @unlink($base);

    if (!move_uploaded_file($tmp, $entrada)) {
        @unlink($entrada);
        json_response(['ok' => false, 'message' => 'Nao consegui salvar o audio.'], 500);
    }

    $comando = escapeshellarg($ffmpeg)
        . ' -y -loglevel error -i ' . escapeshellarg($entrada)
        . ' -t 90 -vn -ac 1 -ar 16000 -f wav ' . escapeshellarg($wav);
    $saida = [];
    $codigo = 0;
    @exec($comando . ' 2>&1', $saida, $codigo);
    @unlink($entrada);

    if ($codigo !== 0 || !is_file($wav) || (int)@filesize($wav) <= 64) {
        @unlink($wav);
        $detalhe = trim(implode(' ', array_slice($saida, -2)));
        json_response([
            'ok' => false,
            'message' => 'Nao consegui converter o audio.' . ($detalhe !== '' ? ' ' . $detalhe : ''),
        ], 422);
    }

    $binario = @file_get_contents($wav);
    @unlink($wav);
    if ($binario === false || strlen($binario) <= 64) {
        json_response(['ok' => false, 'message' => 'Audio convertido vazio.'], 422);
    }

    if (!codex_voz_garantir()) {
        json_response([
            'ok' => false,
            'message' => 'Nao consegui ligar o servico de voz agora. Tente de novo em instantes.',
        ], 503);
    }

    json_response(codex_voz_call('POST', '/asr', $binario, 'audio/wav', 120));
}

/** Gera a voz de um trecho de resposta (base64 WAV) para a fila do navegador. */
function falar_texto(): never
{
    set_time_limit(180);
    session_write_close();

    $input = json_input();
    $texto = trim((string)($input['texto'] ?? ''));
    if ($texto === '') {
        json_response(['ok' => false, 'message' => 'Texto vazio.'], 422);
    }
    if (mb_strlen($texto) > 1200) {
        $texto = mb_substr($texto, 0, 1200);
    }
    $voz = trim((string)($input['voz'] ?? 'pf_dora'));
    $velocidade = (float)($input['velocidade'] ?? 1.0);

    $corpo = json_encode([
        'texto' => $texto,
        'voz' => $voz,
        'velocidade' => $velocidade,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($corpo === false) {
        json_response(['ok' => false, 'message' => 'Texto invalido.'], 422);
    }

    if (!codex_voz_garantir()) {
        json_response([
            'ok' => false,
            'message' => 'Nao consegui ligar o servico de voz agora. Tente de novo em instantes.',
        ], 503);
    }

    json_response(codex_voz_call('POST', '/tts', $corpo, 'application/json', 120));
}
