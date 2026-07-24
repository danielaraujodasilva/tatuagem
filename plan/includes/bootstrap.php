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

function normalize_text_for_signature(string $value): string
{
    $value = strtoupper(trim($value));
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($transliterated) && $transliterated !== '') {
        $value = $transliterated;
    }

    $value = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/u', ' ', $value) ?? $value;
    $value = preg_replace('/\b\d{4}\b/u', ' ', $value) ?? $value;
    $value = preg_replace('/\b\d+\b/u', ' ', $value) ?? $value;
    $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function transaction_signature(string $description): string
{
    return normalize_text_for_signature($description);
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

    $transactionColumns = [
        'source_key' => "VARCHAR(160) NULL",
        'source_updated_at' => "DATETIME NULL",
        'last_manual_edit_at' => "DATETIME NULL",
        'last_imported_at' => "DATETIME NULL",
        'last_change_source' => "ENUM('manual','sheet') NOT NULL DEFAULT 'manual'",
        'description_signature' => "VARCHAR(255) NULL",
    ];

    foreach ($transactionColumns as $column => $definition) {
        if (!column_exists($pdo, 'transactions', $column)) {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN {$column} {$definition}");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_versions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT UNSIGNED NULL,
        user_id INT UNSIGNED NULL,
        action VARCHAR(40) NOT NULL,
        source_mode VARCHAR(20) NOT NULL DEFAULT 'manual',
        source_updated_at DATETIME NULL,
        before_json JSON NULL,
        after_json JSON NULL,
        changes_json JSON NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transaction_versions_transaction_date (transaction_id, created_at),
        INDEX idx_transaction_versions_user_date (user_id, created_at),
        CONSTRAINT fk_transaction_versions_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
        CONSTRAINT fk_transaction_versions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_import_conflicts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT UNSIGNED NULL,
        import_key VARCHAR(160) NULL,
        source_updated_at DATETIME NULL,
        payload_json JSON NOT NULL,
        current_json JSON NULL,
        conflict_reason VARCHAR(255) NOT NULL,
        resolution VARCHAR(32) NULL,
        resolved_by INT UNSIGNED NULL,
        resolved_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transaction_conflicts_transaction_date (transaction_id, created_at),
        INDEX idx_transaction_conflicts_resolution (resolution, created_at),
        CONSTRAINT fk_transaction_conflicts_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
        CONSTRAINT fk_transaction_conflicts_user FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_category_rules (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        signature VARCHAR(255) NOT NULL,
        example_description VARCHAR(220) NOT NULL,
        category_id INT UNSIGNED NOT NULL,
        created_from_transaction_id INT UNSIGNED NULL,
        hit_count INT UNSIGNED NOT NULL DEFAULT 0,
        last_matched_at DATETIME NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_transaction_rule_signature (signature),
        INDEX idx_transaction_rule_category (category_id),
        CONSTRAINT fk_transaction_rule_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        CONSTRAINT fk_transaction_rule_transaction FOREIGN KEY (created_from_transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    backfill_transaction_signatures($pdo);
    seed_transaction_rules_from_transactions($pdo);
    seed_default_transaction_category_rules($pdo);
    categorize_existing_statement_rows($pdo);
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    $stmt->execute([$table, $column]);

    return (bool)$stmt->fetchColumn();
}

function backfill_transaction_signatures(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT id, description FROM transactions WHERE description_signature IS NULL OR description_signature = ''");
    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!$rows) {
        return;
    }

    $update = $pdo->prepare('UPDATE transactions SET description_signature = ? WHERE id = ?');
    foreach ($rows as $row) {
        $update->execute([transaction_signature((string)($row['description'] ?? '')), (int)$row['id']]);
    }
}

function seed_transaction_rules_from_transactions(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM transaction_category_rules')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = $pdo->query("SELECT description_signature, category_id, COUNT(*) occurrences
        FROM transactions
        WHERE category_id IS NOT NULL AND description_signature IS NOT NULL AND description_signature <> ''
        GROUP BY description_signature, category_id
        ORDER BY occurrences DESC");
    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!$rows) {
        return;
    }

    $chosen = [];
    foreach ($rows as $row) {
        $signature = (string)($row['description_signature'] ?? '');
        if ($signature === '' || isset($chosen[$signature])) {
            continue;
        }
        $chosen[$signature] = [
            'category_id' => (int)$row['category_id'],
            'occurrences' => (int)$row['occurrences'],
        ];
    }

    $insert = $pdo->prepare('INSERT INTO transaction_category_rules (signature, example_description, category_id, created_from_transaction_id, hit_count, last_matched_at, is_active, created_at, updated_at)
        VALUES (?, ?, ?, NULL, ?, NOW(), 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), hit_count = VALUES(hit_count), last_matched_at = VALUES(last_matched_at), updated_at = NOW()');
    $lookup = $pdo->prepare('SELECT description FROM transactions WHERE description_signature = ? AND category_id = ? ORDER BY id ASC LIMIT 1');
    foreach ($chosen as $signature => $data) {
        $lookup->execute([$signature, $data['category_id']]);
        $example = (string)($lookup->fetchColumn() ?: $signature);
        $insert->execute([$signature, $example, $data['category_id'], $data['occurrences']]);
    }
}

function seed_default_transaction_category_rules(PDO $pdo): void
{
    $rules = [
        ['PIX ENVIADO FACEBOOK SERVICOS ONLINE', 'PIX ENVIADO FACEBOOK SERVICOS ONLINE', 'Servicos'],
        ['DEBITO VISA ELECTRON BRASIL 23/07 AUTO POSTO AGUIA SX', 'DEBITO VISA ELECTRON BRASIL 23/07 AUTO POSTO AGUIA SX', 'Transporte'],
        ['MARKETING ESTUDIO', 'marketing Estudio', 'Pessoal'],
        ['MAZINHO', 'Mazinho', 'Pessoal'],
        ['CONDOMINIO', 'Condominio', 'Moradia'],
        ['APARTAMENTO', 'Apartamento', 'Moradia'],
        ['BOLETO IMPOSTO DE RENDA', 'Boleto Imposto de Renda', 'Impostos'],
        ['FACULDADE MONITOR PIXCOPCOLA', 'faculdade monitor-pixcopcola', 'Educacao'],
        ['PREVIDENCIA CAIXA', 'PREVIDENCIA CAIXA', 'Investimentos'],
        ['VIVO PIXCOPICOLA', 'Vivo pixcopicola', 'Moradia'],
        ['LUZ', 'luz', 'Moradia'],
        ['AGUA CATENDE ERIKA', 'agua catende erika', 'Moradia'],
        ['CONVENIO DENTISTA', 'convenio dentista', 'Saude'],
        ['CONVENIO DENTISTA PIX COPIACOLA', 'convenio dentista-pix copiacola', 'Saude'],
        ['CONVENIO DENTE COD BARRA', 'convenio dente cod barra', 'Saude'],
        ['MARISA TERAPIA', 'Marisa Terapia', 'Saude'],
        ['MARIANA TERAPIA', 'Mariana Terapia', 'Saude'],
    ];

    $category = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO transaction_category_rules (signature, example_description, category_id, hit_count, last_matched_at, is_active, created_at, updated_at)
        VALUES (?, ?, ?, 0, NOW(), 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE example_description = VALUES(example_description), category_id = VALUES(category_id), is_active = 1, updated_at = NOW()');

    foreach ($rules as [$description, $example, $categoryName]) {
        $category->execute([$categoryName]);
        $categoryId = $category->fetchColumn();
        if (!$categoryId) {
            continue;
        }
        $insert->execute([transaction_signature($description), $example, (int)$categoryId]);
    }
}

function categorize_existing_statement_rows(PDO $pdo): void
{
    $uncategorizedId = null;
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $stmt->execute(['Sem categoria']);
    $uncategorized = $stmt->fetchColumn();
    if ($uncategorized) {
        $uncategorizedId = (int)$uncategorized;
    }

    $where = $uncategorizedId
        ? 't.category_id IS NULL OR t.category_id = ?'
        : 't.category_id IS NULL';
    $params = $uncategorizedId ? [$uncategorizedId] : [];

    $select = $pdo->prepare("SELECT t.id, r.category_id
        FROM transactions t
        JOIN transaction_category_rules r ON r.signature = t.description_signature AND r.is_active = 1
        WHERE {$where}");
    $select->execute($params);
    $rows = $select->fetchAll();
    if (!$rows) {
        return;
    }

    $update = $pdo->prepare('UPDATE transactions SET category_id = ? WHERE id = ?');
    foreach ($rows as $row) {
        $update->execute([(int)$row['category_id'], (int)$row['id']]);
    }
}
