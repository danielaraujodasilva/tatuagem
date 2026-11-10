<?php
declare(strict_types=1);

const AUTOCHAT_CONTROL_PADRAO = 'C:\\Users\\server_spd\\Documents\\whatsapp-origin-bridge\\dados\\autochat-control.json';

function autochat_control_path(): string
{
    $caminho = getenv('AUTOCHAT_CONTROL_PATH');
    return $caminho !== false && trim($caminho) !== '' ? trim($caminho) : AUTOCHAT_CONTROL_PADRAO;
}

function autochat_control_defaults(): array
{
    return [
        'enabled' => false,
        'updated_at' => '',
        'updated_by' => '',
        'enabled_at' => '',
        'backfill_from' => (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format(DATE_ATOM),
    ];
}

function autochat_control_normalizar(array $dados): array
{
    return array_merge(autochat_control_defaults(), [
        'enabled' => !empty($dados['enabled']),
        'updated_at' => trim((string)($dados['updated_at'] ?? '')),
        'updated_by' => mb_substr(trim((string)($dados['updated_by'] ?? '')), 0, 120),
        'enabled_at' => trim((string)($dados['enabled_at'] ?? '')),
        'backfill_from' => trim((string)($dados['backfill_from'] ?? '')) ?: autochat_control_defaults()['backfill_from'],
    ]);
}

function autochat_control_load(): array
{
    $caminho = autochat_control_path();
    if (!is_file($caminho)) {
        return autochat_control_defaults();
    }

    $handle = @fopen($caminho, 'rb');
    if ($handle === false) {
        return autochat_control_defaults();
    }

    try {
        @flock($handle, LOCK_SH);
        $conteudo = stream_get_contents($handle);
    } finally {
        @flock($handle, LOCK_UN);
        fclose($handle);
    }

    $dados = json_decode((string)$conteudo, true);
    return autochat_control_normalizar(is_array($dados) ? $dados : []);
}

function autochat_control_is_enabled(): bool
{
    return !empty(autochat_control_load()['enabled']);
}

function autochat_control_save(array $dados): array
{
    $caminho = autochat_control_path();
    $dir = dirname($caminho);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Pasta do controle do AutoChat indisponivel.');
    }

    $dados = autochat_control_normalizar($dados);
    $handle = @fopen($caminho, 'c+b');
    if ($handle === false) {
        throw new RuntimeException('Nao foi possivel gravar o controle do AutoChat.');
    }

    try {
        if (!@flock($handle, LOCK_EX)) {
            throw new RuntimeException('Nao foi possivel bloquear o controle do AutoChat.');
        }
        $atual = json_decode((string)stream_get_contents($handle), true);
        if (is_array($atual)) {
            $dados = autochat_control_normalizar(array_merge($atual, $dados));
        }
        $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Falha ao gerar o controle do AutoChat.');
        }
        rewind($handle);
        ftruncate($handle, 0);
        if (fwrite($handle, $json . PHP_EOL) === false) {
            throw new RuntimeException('Falha ao salvar o controle do AutoChat.');
        }
        fflush($handle);
    } finally {
        @flock($handle, LOCK_UN);
        fclose($handle);
    }

    return $dados;
}

function autochat_control_set_enabled(bool $enabled, string $por = ''): array
{
    $atual = autochat_control_load();
    $estavaLigado = !empty($atual['enabled']);
    $agora = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format(DATE_ATOM);
    $atual['enabled'] = $enabled;
    $atual['updated_at'] = $agora;
    $atual['updated_by'] = $por;
    if ($enabled && (!$estavaLigado || empty($atual['enabled_at']))) {
        $atual['enabled_at'] = $agora;
    }
    return autochat_control_save($atual);
}

function autochat_control_csrf_token(): string
{
    if (empty($_SESSION['autochat_control_csrf'])) {
        $_SESSION['autochat_control_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['autochat_control_csrf'];
}

function autochat_control_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(autochat_control_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function autochat_control_csrf_verify(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(autochat_control_csrf_token(), $token)) {
        http_response_code(419);
        exit('Sessao expirada. Volte e tente novamente.');
    }
}
