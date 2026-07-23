<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

session_name('plan_finance_session');
session_start();

function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    foreach (['host', 'database', 'username'] as $field) {
        if (($config[$field] ?? '') === '') {
            throw new RuntimeException('Configuracao incompleta. Crie plan/config.local.php ou defina PLAN_DB_* no servidor.');
        }
    }

    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
        json_response(['ok' => false, 'message' => 'Sessao expirada. Atualize a pagina.'], 419);
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'message' => 'Nao autenticado.'], 401);
    }

    return $user;
}

function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function money_to_float(mixed $value): float
{
    if (is_numeric($value)) {
        return (float)$value;
    }

    $value = trim((string)$value);
    $value = str_replace(['R$', ' ', '.'], '', $value);
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? (float)$value : 0.0;
}

function normalize_date(?string $date): ?string
{
    $date = trim((string)$date);
    if ($date === '') {
        return null;
    }

    foreach (['d/m/Y', 'd/n/Y', 'j/m/Y', 'j/n/Y', 'Y-m-d'] as $format) {
        $parsed = DateTime::createFromFormat($format, $date);
        if ($parsed instanceof DateTime) {
            return $parsed->format('Y-m-d');
        }
    }

    return null;
}

function audit(string $action, string $entity, ?int $entityId, array $changes = []): void
{
    $stmt = db()->prepare('INSERT INTO audit_log (user_id, action, entity, entity_id, changes_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $entity,
        $entityId,
        json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}
