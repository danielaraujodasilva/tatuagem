<?php
declare(strict_types=1);

function ensure_bank_schema(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS bank_imports (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        bank_name VARCHAR(80) NOT NULL,
        account_id INT UNSIGNED NULL,
        account_label VARCHAR(160) NULL,
        file_name VARCHAR(220) NOT NULL,
        file_hash CHAR(64) NOT NULL,
        period_start DATE NULL,
        period_end DATE NULL,
        imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
        matched_rows INT UNSIGNED NOT NULL DEFAULT 0,
        imported_by INT UNSIGNED NULL,
        imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_bank_import_file (file_hash),
        INDEX idx_bank_imports_bank_date (bank_name, imported_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS bank_transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        import_id INT UNSIGNED NOT NULL,
        account_id INT UNSIGNED NULL,
        bank_name VARCHAR(80) NOT NULL,
        source_file VARCHAR(220) NOT NULL,
        source_hash CHAR(64) NOT NULL,
        transaction_date DATE NOT NULL,
        description VARCHAR(255) NOT NULL,
        movement_type VARCHAR(120) NULL,
        document_number VARCHAR(80) NULL,
        direction ENUM('credit','debit') NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        balance DECIMAL(12,2) NULL,
        category_id INT UNSIGNED NULL,
        matched_transaction_id INT UNSIGNED NULL,
        raw_json JSON NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_bank_transaction_hash (source_hash),
        INDEX idx_bank_transactions_date (transaction_date),
        INDEX idx_bank_transactions_bank (bank_name),
        INDEX idx_bank_transactions_match (matched_transaction_id),
        CONSTRAINT fk_bank_transactions_import FOREIGN KEY (import_id) REFERENCES bank_imports(id) ON DELETE CASCADE,
        CONSTRAINT fk_bank_transactions_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
        CONSTRAINT fk_bank_transactions_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        CONSTRAINT fk_bank_transactions_match FOREIGN KEY (matched_transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function find_or_create_bank_account(string $bankName): int
{
    $stmt = db()->prepare('SELECT id FROM accounts WHERE name = ? LIMIT 1');
    $stmt->execute([$bankName]);
    $account = $stmt->fetch();
    if ($account) {
        return (int)$account['id'];
    }

    db()->prepare('INSERT INTO accounts (name, type, opening_balance) VALUES (?, ?, 0.00)')->execute([$bankName, 'corrente']);
    return (int)db()->lastInsertId();
}

function auto_match_transaction(string $date, float $amount, string $direction, string $description, ?int $accountId): ?int
{
    $type = $direction === 'credit' ? 'income' : 'expense';
    $stmt = db()->prepare("SELECT id, description FROM transactions
        WHERE type = ? AND status IN ('pending','late') AND ABS(amount - ?) < 0.01
        AND due_date BETWEEN DATE_SUB(?, INTERVAL 7 DAY) AND DATE_ADD(?, INTERVAL 7 DAY)
        ORDER BY ABS(DATEDIFF(due_date, ?)) ASC, id ASC LIMIT 5");
    $stmt->execute([$type, $amount, $date, $date, $date]);
    $candidates = $stmt->fetchAll();

    if (!$candidates) {
        return null;
    }

    $best = null;
    $bestScore = 0;
    foreach ($candidates as $candidate) {
        similar_text(normalize_match_text($description), normalize_match_text($candidate['description']), $score);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = (int)$candidate['id'];
        }
    }

    if ($best && ($bestScore >= 18 || count($candidates) === 1)) {
        db()->prepare('UPDATE transactions SET status = ?, paid_at = ?, account_id = COALESCE(account_id, ?), updated_at = NOW() WHERE id = ?')
            ->execute(['paid', $date, $accountId, $best]);
        audit('auto_match_paid', 'transaction', $best, ['bank_description' => $description, 'score' => $bestScore]);
        return $best;
    }

    return null;
}

function normalize_match_text(string $value): string
{
    $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower) ?: $lower;
    return preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
}

function guess_category_id(string $description): ?int
{
    $rules = [
        'Transporte' => ['autopass', 'posto', 'uber', '99 ', 'combustivel'],
        'Alimentacao' => ['acougue', 'lanchonet', 'mercado', 'nagumo', 'adega'],
        'Servicos' => ['google', 'facebook', 'pagseguro', 'seguro conta'],
        'Saude' => ['farmacia', 'promofarma', 'dent', 'terapia'],
        'Educacao' => ['faculdade', 'principia', 'sesi'],
        'Investimentos' => ['rendimento', 'previdencia'],
    ];
    $normalized = normalize_match_text($description);
    foreach ($rules as $category => $needles) {
        foreach ($needles as $needle) {
            if (strpos($normalized, trim($needle)) !== false) {
                $stmt = db()->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
                $stmt->execute([$category]);
                $row = $stmt->fetch();
                return $row ? (int)$row['id'] : null;
            }
        }
    }
    return null;
}

function build_bank_overview(): array
{
    ensure_bank_schema();
    $byBank = db()->query("SELECT bank_name,
        COUNT(*) rows_count,
        SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) credits,
        SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) debits,
        MAX(transaction_date) latest_date
        FROM bank_transactions GROUP BY bank_name ORDER BY bank_name")->fetchAll();

    $latestBalances = db()->query("SELECT bt.bank_name, bt.balance, bt.transaction_date
        FROM bank_transactions bt
        JOIN (
            SELECT bank_name, MAX(CONCAT(transaction_date, LPAD(id, 12, '0'))) marker
            FROM bank_transactions WHERE balance IS NOT NULL GROUP BY bank_name
        ) latest ON latest.bank_name = bt.bank_name AND latest.marker = CONCAT(bt.transaction_date, LPAD(bt.id, 12, '0'))
        ORDER BY bt.bank_name")->fetchAll();

    return ['byBank' => $byBank, 'latestBalances' => $latestBalances];
}
