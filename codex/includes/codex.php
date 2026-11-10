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
        return ['ok' => false, 'message' => (string)($dados['error'] ?? ('Erro ' . $status))];
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
