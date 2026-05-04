<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$authPreviousConn = $GLOBALS['conn'] ?? null;
require __DIR__ . '/../ficha/config/conexao.php';
$GLOBALS['authConn'] = $conn;
if ($authPreviousConn !== null) {
    $GLOBALS['conn'] = $authPreviousConn;
}

const AUTH_ROLES = ['cliente', 'funcionario', 'adm'];

function auth_db(): mysqli
{
    return $GLOBALS['authConn'];
}

function auth_table_exists(string $table): bool
{
    $conn = auth_db();
    $stmt = $conn->prepare('SHOW TABLES LIKE ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    return $exists;
}

function auth_ensure_schema(): void
{
    $conn = auth_db();

    try {
        if (!auth_table_exists('usuarios')) {
            $conn->query("
                CREATE TABLE usuarios (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    cliente_id INT UNSIGNED NULL,
                    username VARCHAR(80) NOT NULL,
                    nome VARCHAR(150) NOT NULL,
                    email VARCHAR(150) NOT NULL DEFAULT '',
                    telefone VARCHAR(40) NOT NULL DEFAULT '',
                    senha_hash VARCHAR(255) NOT NULL,
                    role ENUM('cliente', 'funcionario', 'adm') NOT NULL DEFAULT 'cliente',
                    ativo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_usuarios_username (username),
                    KEY idx_usuarios_email (email),
                    KEY idx_usuarios_telefone (telefone),
                    KEY idx_usuarios_cliente (cliente_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (!auth_table_exists('senha_resets')) {
            $conn->query("
                CREATE TABLE senha_resets (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_senha_resets_token (token_hash),
                    KEY idx_senha_resets_usuario (usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Throwable $e) {
        error_log('Auth schema check failed: ' . $e->getMessage());
    }
}

auth_ensure_schema();

function auth_base_url(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($script, '/auth/');
    if ($pos !== false) {
        return substr($script, 0, $pos);
    }

    foreach (['/crm/', '/ficha/'] as $needle) {
        $pos = strpos($script, $needle);
        if ($pos !== false) {
            return substr($script, 0, $pos);
        }
    }

    return rtrim(dirname($script), '/\\');
}

function auth_url(string $path): string
{
    return rtrim(auth_base_url(), '/') . '/' . ltrim($path, '/');
}

function auth_redirect(string $path): void
{
    header('Location: ' . auth_url($path));
    exit;
}

function auth_wants_json(): bool
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

    return strpos($script, '/api/') !== false || strpos($accept, 'application/json') !== false;
}

function auth_json_error(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function auth_normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function auth_user_by_id(int $id): ?array
{
    $conn = auth_db();

    $stmt = $conn->prepare('SELECT id, cliente_id, username, nome, email, telefone, role, ativo FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function current_user(): ?array
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $id = (int)($_SESSION['auth_user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $user = auth_user_by_id($id);
    if (!$user || (int)$user['ativo'] !== 1) {
        $_SESSION = [];
        session_destroy();
        return null;
    }

    $cached = $user;
    return $cached;
}

function auth_has_role(array $roles): bool
{
    $user = current_user();
    return $user !== null && in_array((string)$user['role'], $roles, true);
}

function require_login(): array
{
    $user = current_user();
    if ($user) {
        return $user;
    }

    if (auth_wants_json()) {
        auth_json_error(401, 'Sessao expirada. Entre novamente.');
    }

    $next = $_SERVER['REQUEST_URI'] ?? auth_url('/ficha/minha_conta.php');
    auth_redirect('/auth/login.php?next=' . urlencode($next));
}

function require_role($roles): array
{
    $roles = is_array($roles) ? $roles : [$roles];
    $user = require_login();

    if (!in_array((string)$user['role'], $roles, true)) {
        if (auth_wants_json()) {
            auth_json_error(403, 'Acesso negado para este usuario.');
        }

        http_response_code(403);
        echo 'Acesso negado.';
        exit;
    }

    return $user;
}

function require_staff(): array
{
    return require_role(['funcionario', 'adm']);
}

function require_admin(): array
{
    return require_role('adm');
}

function auth_cliente_id_or_403(int $clienteId): void
{
    $user = require_login();
    if ($user['role'] === 'cliente' && (int)$user['cliente_id'] !== $clienteId) {
        http_response_code(403);
        echo 'Acesso negado.';
        exit;
    }
}

function auth_default_path(array $user): string
{
    if ($user['role'] === 'cliente') {
        return '/ficha/minha_conta.php';
    }

    return '/crm/index.php';
}

function auth_find_cliente_id(string $email, string $telefone): ?int
{
    $conn = auth_db();

    $email = trim($email);
    $telefoneLimpo = auth_normalize_phone($telefone);

    $stmt = $conn->prepare(
        "SELECT id FROM clientes
         WHERE (email <> '' AND LOWER(email) = LOWER(?))
            OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, ' ', ''), '-', ''), '(', ''), ')', '') = ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('ss', $email, $telefoneLimpo);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $cliente ? (int)$cliente['id'] : null;
}

function auth_send_password_reset(string $email, string $link): bool
{
    if ($email === '') {
        return false;
    }

    $subject = 'Recuperacao de senha';
    $message = "Use este link para redefinir sua senha:\n\n" . $link . "\n\nSe voce nao pediu isso, ignore esta mensagem.";
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, $subject, $message, $headers);
}
