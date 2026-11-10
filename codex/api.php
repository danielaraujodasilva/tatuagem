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

    json_response(codex_bridge_call('POST', '/send', [
        'prompt' => $prompt,
        'threadId' => $threadId !== '' ? $threadId : null,
        'fonte' => (string)($input['fonte'] ?? ''),
        'cwd' => (string)($input['cwd'] ?? ''),
        'images' => $images,
    ], 20));
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
