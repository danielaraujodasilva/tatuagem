<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

session_name('plan_finance_session');
session_start();

function db(): PDO
{
    static $pdo = null;
    static $schemaReady = false;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    foreach (['host', 'database', 'username'] as $field) {
        if (($config[$field] ?? '') === '') {
            http_response_code(500);
            die('Configuracao incompleta. Crie plan/config.local.php ou defina PLAN_DB_* no servidor.');
        }
    }

    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if (!$schemaReady) {
        ensure_plan_schema($pdo);
        $schemaReady = true;
    }

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

function normalize_datetime(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d'] as $format) {
        $parsed = DateTime::createFromFormat($format, $value);
        if ($parsed instanceof DateTime) {
            return $parsed->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
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

function ensure_plan_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_versions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        account_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        action VARCHAR(40) NOT NULL,
        source_mode VARCHAR(20) NOT NULL DEFAULT 'manual',
        source_updated_at DATETIME NULL,
        before_json JSON NULL,
        after_json JSON NULL,
        changes_json JSON NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_account_versions_account_date (account_id, created_at),
        INDEX idx_account_versions_user_date (user_id, created_at),
        CONSTRAINT fk_account_versions_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
        CONSTRAINT fk_account_versions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS account_import_conflicts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        account_id INT UNSIGNED NULL,
        import_key VARCHAR(120) NULL,
        source_updated_at DATETIME NULL,
        payload_json JSON NOT NULL,
        current_json JSON NULL,
        conflict_reason VARCHAR(255) NOT NULL,
        resolution VARCHAR(32) NULL,
        resolved_by INT UNSIGNED NULL,
        resolved_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_account_conflicts_account_date (account_id, created_at),
        INDEX idx_account_conflicts_resolution (resolution, created_at),
        CONSTRAINT fk_account_conflicts_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
        CONSTRAINT fk_account_conflicts_user FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $accountColumns = [
        'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'source_key' => "VARCHAR(120) NULL",
        'source_updated_at' => "DATETIME NULL",
        'last_manual_edit_at' => "DATETIME NULL",
        'last_imported_at' => "DATETIME NULL",
        'last_change_source' => "ENUM('manual','sheet') NOT NULL DEFAULT 'manual'",
    ];

    foreach ($accountColumns as $column => $definition) {
        if (!column_exists($pdo, 'accounts', $column)) {
            $pdo->exec("ALTER TABLE accounts ADD COLUMN {$column} {$definition}");
        }
    }
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    $stmt->execute([$table, $column]);

    return (bool)$stmt->fetchColumn();
}
