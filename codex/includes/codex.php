<?php
declare(strict_types=1);

/** Porta da ponte Node local (codex/bridge/server.js). */
function codex_bridge_port(): int
{
    return (int)(getenv('CODEX_WEB_PORT') ?: 3051);
}

/** Token compartilhado com a ponte (gerado por ela em bridge/data/token.txt). */
function codex_bridge_token(): string
{
    $arquivo = __DIR__ . '/../bridge/data/token.txt';
    $token = @file_get_contents($arquivo);
    return $token === false ? '' : trim($token);
}

/**
 * Chama a ponte local do Codex (127.0.0.1). Devolve sempre um array; quando
 * falha vem com ok=false e message.
 */
function codex_bridge_call(string $metodo, string $rota, ?array $corpo = null, int $timeout = 20): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'Extensao cURL do PHP nao esta habilitada no php.ini.'];
    }

    $url = 'http://127.0.0.1:' . codex_bridge_port() . $rota;
    $headers = ['Accept: application/json'];
    $token = codex_bridge_token();
    if ($token !== '') {
        $headers[] = 'X-Bridge-Token: ' . $token;
    }

    $opcoes = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => $timeout,
    ];

    if ($corpo !== null) {
        $headers[] = 'Content-Type: application/json';
        $opcoes[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $opcoes[CURLOPT_HTTPHEADER] = $headers;

    $ch = curl_init($url);
    curl_setopt_array($ch, $opcoes);
    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resposta === false) {
        return ['ok' => false, 'message' => 'Ponte do Codex offline. Rode codex/start-codex-bridge.ps1. (' . $erro . ')'];
    }

    $dados = json_decode((string)$resposta, true);
    if (!is_array($dados)) {
        return ['ok' => false, 'message' => 'Resposta invalida da ponte do Codex.'];
    }
    if ($status >= 400) {
        // Mantem os campos que a ponte devolveu (ex.: runId no 409 de turno
        // ja em andamento) e informa o status para o api.php repassar.
        $dados['ok'] = false;
        $dados['message'] = (string)($dados['message'] ?? $dados['error'] ?? ('Erro ' . $status));
        $dados['bridge_status'] = $status;
        return $dados;
    }

    return $dados;
}

/** Pasta onde os anexos enviados pela pagina sao salvos. */
function codex_uploads_dir(): string
{
    $dir = realpath(__DIR__ . '/../uploads');
    return $dir !== false ? $dir : (__DIR__ . '/../uploads');
}

/**
 * Devolve o caminho absoluto do arquivo somente se ele estiver dentro da
 * pasta de uploads (evita ler qualquer arquivo da maquina a pedido do cliente).
 */
function codex_upload_path_seguro(string $caminho): ?string
{
    if ($caminho === '') {
        return null;
    }

    $base = realpath(codex_uploads_dir());
    $alvo = realpath($caminho);
    if ($base === false || $alvo === false || !is_file($alvo)) {
        return null;
    }

    $base = rtrim(str_replace('\\', '/', $base), '/');
    $alvoNormalizado = str_replace('\\', '/', $alvo);
    if ($alvoNormalizado !== $base && !str_starts_with($alvoNormalizado, $base . '/')) {
        return null;
    }

    return $alvo;
}

/** Extensoes aceitas no upload. */
function codex_extensoes_permitidas(): array
{
    return [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg',
        'pdf', 'txt', 'csv', 'json', 'md', 'log', 'xml', 'sql',
        'zip', 'rar', '7z',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'php', 'js', 'ts', 'py', 'html', 'css', 'sh', 'bat', 'ps1',
        'mp3', 'wav', 'ogg', 'mp4', 'webm', 'mov',
    ];
}

/** Remove anexos antigos da pasta de uploads (best-effort, silencioso). */
function codex_limpar_uploads(int $dias = 14): void
{
    $dir = codex_uploads_dir();
    if (!is_dir($dir)) return;
    $limite = time() - max(1, $dias) * 86400;
    foreach ((array)@scandir($dir) as $nome) {
        if ($nome === '.' || $nome === '..' || $nome === '.htaccess') continue;
        $arquivo = $dir . DIRECTORY_SEPARATOR . $nome;
        if (!is_file($arquivo)) continue;
        if ((int)@filemtime($arquivo) >= $limite) continue;
        @unlink($arquivo);
    }
}

/**
 * Repassa um stream SSE da ponte para o navegador, sem bufferizar.
 * Encerra sozinho quando o cliente desconecta.
 */
function codex_bridge_stream(string $rota, int $timeout = 21600): void
{
    if (!function_exists('curl_init')) {
        echo "event: fim\ndata: {\"ok\":false,\"message\":\"cURL indisponivel\"}\n\n";
        return;
    }

    $headers = ['Accept: text/event-stream'];
    $token = codex_bridge_token();
    if ($token !== '') {
        $headers[] = 'X-Bridge-Token: ' . $token;
    }

    $ch = curl_init('http://127.0.0.1:' . codex_bridge_port() . $rota);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_WRITEFUNCTION => static function ($ch, string $pedaco): int {
            echo $pedaco;
            @ob_flush();
            @flush();
            return connection_aborted() ? 0 : strlen($pedaco);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ------------------------------------------------------------------ voz

/** Porta do worker local de voz (Whisper + Kokoro). */
function codex_voz_port(): int
{
    return (int)(getenv('CODEX_VOZ_PORT') ?: 3060);
}

/** Token compartilhado com o worker de voz (voz/token.txt). */
function codex_voz_token(): string
{
    $token = @file_get_contents(__DIR__ . '/../voz/token.txt');
    return $token === false ? '' : trim($token);
}

/**
 * Chama o worker de voz em 127.0.0.1. O corpo pode ser binario (ASR) ou JSON
 * (TTS). Devolve sempre um array com ok.
 */
function codex_voz_call(
    string $metodo,
    string $rota,
    ?string $corpo = null,
    string $tipo = 'application/json',
    int $timeout = 120
): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'Extensao cURL do PHP nao esta habilitada.'];
    }

    $headers = ['Accept: application/json'];
    $token = codex_voz_token();
    if ($token !== '') {
        $headers[] = 'X-Voz-Token: ' . $token;
    }

    $opcoes = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => max(3, $timeout),
    ];

    if ($corpo !== null) {
        $headers[] = 'Content-Type: ' . $tipo;
        $headers[] = 'Content-Length: ' . strlen($corpo);
        $opcoes[CURLOPT_POSTFIELDS] = $corpo;
    }
    $opcoes[CURLOPT_HTTPHEADER] = $headers;

    $ch = curl_init('http://127.0.0.1:' . codex_voz_port() . $rota);
    curl_setopt_array($ch, $opcoes);
    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resposta === false) {
        // O detalhe do cURL fica no log; na tela basta saber que esta desligado.
        @error_log('codex voz: ' . $erro);
        return [
            'ok' => false,
            'online' => false,
            'message' => 'Servico de voz desligado. Estou tentando religar; tente de novo em alguns segundos.',
        ];
    }

    $dados = json_decode((string)$resposta, true);
    if (!is_array($dados)) {
        return ['ok' => false, 'message' => 'Resposta invalida do worker de voz.'];
    }
    if ($status >= 400) {
        return [
            'ok' => false,
            'message' => (string)($dados['message'] ?? ('Erro ' . $status)),
        ];
    }

    return $dados;
}

/** Estado do worker para a tela (nunca lanca erro). */
function codex_voz_status(): array
{
    $saude = codex_voz_call('GET', '/health', null, 'application/json', 3);
    if (!empty($saude['ok'])) {
        return ['ok' => true, 'online' => true, 'worker' => $saude];
    }
    return [
        'ok' => true,
        'online' => false,
        'message' => (string)($saude['message'] ?? 'Worker de voz offline.'),
    ];
}

/** True se o worker responde ao /health agora. */
function codex_voz_online(): bool
{
    $saude = codex_voz_call('GET', '/health', null, 'application/json', 2);
    return !empty($saude['ok']);
}

/**
 * Garante o worker de voz de pe. Se estiver desligado, chama o start-voz.ps1
 * (roda como o mesmo usuario do Apache) e espera os modelos carregarem.
 * Serve de auto-cura quando o watchdog do logon nao subiu.
 */
function codex_voz_garantir(int $espera = 20): bool
{
    if (codex_voz_online()) {
        return true;
    }

    $script = realpath(__DIR__ . '/../voz/start-voz.ps1');
    if ($script !== false && function_exists('exec')) {
        $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File '
            . escapeshellarg($script);
        @exec($cmd . ' 2>&1');
    }

    $ate = time() + max(5, $espera);
    do {
        usleep(500000);
        if (codex_voz_online()) {
            return true;
        }
    } while (time() < $ate);

    return false;
}

/** Sobe o worker em background sem esperar os modelos (pre-aquecimento). */
function codex_voz_iniciar(): bool
{
    $script = realpath(__DIR__ . '/../voz/start-voz.ps1');
    if ($script === false || !function_exists('exec')) {
        return false;
    }
    $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File '
        . escapeshellarg($script);
    @exec($cmd . ' 2>&1');
    return true;
}

/** Caminho do ffmpeg (usado para converter o audio do navegador). */
function codex_ffmpeg(): ?string
{
    $candidatos = [
        (string)(getenv('CODEX_FFMPEG') ?: ''),
        'C:\\ffmpeg\\bin\\ffmpeg.exe',
    ];
    foreach ($candidatos as $candidato) {
        if ($candidato !== '' && is_file($candidato)) {
            return $candidato;
        }
    }
    if (function_exists('shell_exec')) {
        $onde = trim((string)@shell_exec('where ffmpeg 2>NUL'));
        if ($onde !== '') {
            $primeira = trim((string)strtok($onde, "\r\n"));
            if ($primeira !== '' && is_file($primeira)) {
                return $primeira;
            }
        }
    }
    return null;
}

// ------------------------------------------------------------------ acesso

/** IP do cliente (usa o header do Cloudflare quando existe). */
function codex_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $chave) {
        $valor = trim((string)($_SERVER[$chave] ?? ''));
        if ($valor === '') continue;
        $primeiro = trim(explode(',', $valor)[0]);
        if ($primeiro !== '') return $primeiro;
    }
    return '';
}

/** Checa se o IP esta dentro de um bloco CIDR IPv4 (ex.: 203.0.113.0/24). */
function codex_ip_em_cidr(string $ip, string $cidr): bool
{
    [$rede, $bits] = array_pad(explode('/', $cidr, 2), 2, '');
    if ($bits === '' || !ctype_digit($bits)) return false;
    $ipLong = ip2long($ip);
    $redeLong = ip2long($rede);
    if ($ipLong === false || $redeLong === false) return false;
    $mascara = (int)$bits === 0 ? 0 : (-1 << (32 - (int)$bits));
    return ($ipLong & $mascara) === ($redeLong & $mascara);
}

/**
 * Allowlist opcional via CODEX_WEB_ALLOW_IPS (IPs e CIDR IPv4 separados por
 * virgula). Sem a variavel, todo mundo passa - nada muda por padrao.
 */
function codex_ip_permitido(): bool
{
    $bruto = trim((string)(getenv('CODEX_WEB_ALLOW_IPS') ?: ''));
    if ($bruto === '') return true;

    $ip = codex_client_ip();
    if ($ip === '') return false;
    foreach (preg_split('/[\s,]+/', $bruto) ?: [] as $permitido) {
        $permitido = trim($permitido);
        if ($permitido === '') continue;
        if ($permitido === $ip) return true;
        if (str_contains($permitido, '/') && codex_ip_em_cidr($ip, $permitido)) return true;
    }
    return false;
}

// ------------------------------------------------------- limite de tentativas

function codex_data_dir(): string
{
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

function codex_login_ler(): array
{
    $arquivo = codex_data_dir() . '/login_attempts.json';
    $bruto = @file_get_contents($arquivo);
    $dados = $bruto === false ? [] : json_decode($bruto, true);
    return is_array($dados) ? $dados : [];
}

function codex_login_gravar(array $dados): void
{
    $arquivo = codex_data_dir() . '/login_attempts.json';
    @file_put_contents($arquivo, json_encode($dados), LOCK_EX);
}

function codex_login_chave(string $email): string
{
    $ip = codex_client_ip();
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
        $ip = 'local';
    }
    return hash('sha256', $ip . '|' . strtolower(trim($email)));
}

/** Segundos de bloqueio restantes (0 = liberado). */
function codex_login_bloqueado(string $chave, int $max = 8, int $janela = 900): int
{
    $registro = codex_login_ler()[$chave] ?? null;
    if (!is_array($registro)) return 0;
    $agora = time();
    $ultimo = (int)($registro['ultimo'] ?? 0);
    if ($agora - $ultimo > $janela) return 0;
    if ((int)($registro['tentativas'] ?? 0) < $max) return 0;
    return max(0, $janela - ($agora - $ultimo));
}

function codex_login_falhou(string $chave): void
{
    $dados = codex_login_ler();
    $agora = time();
    $registro = $dados[$chave] ?? null;
    if (!is_array($registro) || $agora - (int)($registro['inicio'] ?? 0) > 900) {
        $registro = ['tentativas' => 0, 'inicio' => $agora];
    }
    $registro['tentativas'] = (int)$registro['tentativas'] + 1;
    $registro['ultimo'] = $agora;
    $dados[$chave] = $registro;
    foreach ($dados as $k => $v) {
        if ($agora - (int)($v['ultimo'] ?? 0) > 3600) unset($dados[$k]);
    }
    codex_login_gravar($dados);
}

function codex_login_ok(string $chave): void
{
    $dados = codex_login_ler();
    if (!isset($dados[$chave])) return;
    unset($dados[$chave]);
    codex_login_gravar($dados);
}
