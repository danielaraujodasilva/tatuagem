<?php
declare(strict_types=1);

require_once __DIR__ . '/bank-core.php';

function recurring_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function recurring_index_exists(string $table, string $index): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function ensure_recurring_payment_schema(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS recurring_rule_payments (
        rule_id INT UNSIGNED NOT NULL,
        month CHAR(7) NOT NULL,
        paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) NOT NULL DEFAULT 'paid',
        match_method VARCHAR(20) NOT NULL DEFAULT 'manual',
        source_bank_transaction_id BIGINT UNSIGNED NULL,
        PRIMARY KEY (rule_id, month),
        INDEX idx_recurring_payment_source (source_bank_transaction_id),
        CONSTRAINT fk_recurring_payment_rule FOREIGN KEY (rule_id) REFERENCES recurring_rules(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!recurring_column_exists('recurring_rule_payments', 'status')) {
        db()->exec("ALTER TABLE recurring_rule_payments ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'paid' AFTER paid_at");
    }
    if (!recurring_column_exists('recurring_rule_payments', 'match_method')) {
        db()->exec("ALTER TABLE recurring_rule_payments ADD COLUMN match_method VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER status");
    }
    if (!recurring_column_exists('recurring_rule_payments', 'source_bank_transaction_id')) {
        db()->exec('ALTER TABLE recurring_rule_payments ADD COLUMN source_bank_transaction_id BIGINT UNSIGNED NULL AFTER match_method');
        db()->exec('ALTER TABLE recurring_rule_payments ADD INDEX idx_recurring_payment_source (source_bank_transaction_id)');
    }

    db()->exec("CREATE TABLE IF NOT EXISTS recurring_rule_matchers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        rule_id INT UNSIGNED NOT NULL,
        match_key VARCHAR(255) NOT NULL,
        account_id INT UNSIGNED NULL,
        bank_name VARCHAR(80) NULL,
        source_bank_transaction_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_recurring_match (rule_id, match_key, account_id),
        INDEX idx_recurring_match_key (match_key),
        INDEX idx_recurring_match_account (account_id),
        CONSTRAINT fk_recurring_match_rule FOREIGN KEY (rule_id) REFERENCES recurring_rules(id) ON DELETE CASCADE,
        CONSTRAINT fk_recurring_match_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!recurring_column_exists('recurring_rule_matchers', 'id')) {
        if (!recurring_index_exists('recurring_rule_matchers', 'idx_recurring_match_rule')) {
            db()->exec('ALTER TABLE recurring_rule_matchers ADD INDEX idx_recurring_match_rule (rule_id)');
        }
        db()->exec('ALTER TABLE recurring_rule_matchers DROP PRIMARY KEY, ADD COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        db()->exec('ALTER TABLE recurring_rule_matchers ADD UNIQUE KEY uniq_recurring_match (rule_id, match_key, account_id)');
    }
}

function save_recurring_matcher(int $ruleId, int $bankTransactionId): void
{
    ensure_recurring_payment_schema();
    $stmt = db()->prepare("SELECT id, account_id, bank_name, description FROM bank_transactions
        WHERE id = ? AND direction = 'debit' LIMIT 1");
    $stmt->execute([$bankTransactionId]);
    $transaction = $stmt->fetch();
    if (!$transaction) {
        throw new RuntimeException('A transacao usada para reconhecer a conta fixa nao foi encontrada.');
    }

    $key = recurring_category_key((string)$transaction['description']);
    if ($key === '') {
        throw new RuntimeException('A descricao dessa transacao nao permite o reconhecimento automatico.');
    }

    db()->prepare("INSERT INTO recurring_rule_matchers
        (rule_id, match_key, account_id, bank_name, source_bank_transaction_id)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE bank_name=VALUES(bank_name), source_bank_transaction_id=VALUES(source_bank_transaction_id)")
        ->execute([$ruleId, $key, $transaction['account_id'], $transaction['bank_name'], $transaction['id']]);
}

function backfill_recurring_matchers(): int
{
    $rules = db()->query("SELECT r.id, r.description FROM recurring_rules r
        LEFT JOIN recurring_rule_matchers m ON m.rule_id = r.id
        WHERE r.is_active = 1 AND m.rule_id IS NULL")->fetchAll();
    if (!$rules) {
        return 0;
    }

    $transactions = db()->query("SELECT id, account_id, bank_name, transaction_date, description
        FROM bank_transactions WHERE direction = 'debit' ORDER BY transaction_date DESC, id DESC")->fetchAll();
    $byKey = [];
    foreach ($transactions as $transaction) {
        $key = recurring_category_key((string)$transaction['description']);
        if ($key !== '' && !isset($byKey[$key])) {
            $byKey[$key] = $transaction;
        }
    }

    $created = 0;
    foreach ($rules as $rule) {
        $key = recurring_category_key((string)$rule['description']);
        if ($key === '' || !isset($byKey[$key])) {
            continue;
        }
        save_recurring_matcher((int)$rule['id'], (int)$byKey[$key]['id']);
        $created++;
    }
    return $created;
}

function sync_recurring_payments(?int $onlyRuleId = null): array
{
    ensure_bank_schema();
    ensure_recurring_payment_schema();
    if ($onlyRuleId === null) {
        backfill_recurring_matchers();
    }

    $sql = "SELECT r.id rule_id, r.amount expected_amount, m.match_key, m.account_id, m.bank_name
        FROM recurring_rules r JOIN recurring_rule_matchers m ON m.rule_id = r.id
        WHERE r.is_active = 1";
    $params = [];
    if ($onlyRuleId !== null) {
        $sql .= ' AND r.id = ?';
        $params[] = $onlyRuleId;
    }
    $rulesStmt = db()->prepare($sql);
    $rulesStmt->execute($params);
    $rules = $rulesStmt->fetchAll();
    if (!$rules) {
        return ['matched' => 0];
    }

    $transactions = db()->query("SELECT id, account_id, bank_name, transaction_date, description, amount
        FROM bank_transactions WHERE direction = 'debit' ORDER BY transaction_date, id")->fetchAll();
    $insert = db()->prepare("INSERT IGNORE INTO recurring_rule_payments
        (rule_id, month, paid_at, status, match_method, source_bank_transaction_id)
        VALUES (?, ?, ?, 'paid', 'automatic', ?)");
    $patterns = [];
    foreach ($rules as $rule) {
        $patternKey = ($rule['account_id'] ?? 'any') . '|' . $rule['match_key'];
        $patterns[$patternKey]['rules'][(int)$rule['rule_id']] = $rule;
    }
    foreach ($transactions as $transaction) {
        $key = recurring_category_key((string)$transaction['description']);
        foreach ([($transaction['account_id'] ?? 'any') . '|' . $key, 'any|' . $key] as $patternKey) {
            if (!isset($patterns[$patternKey])) {
                continue;
            }
            $month = substr((string)$transaction['transaction_date'], 0, 7);
            $fingerprint = implode('|', [$transaction['account_id'], $transaction['transaction_date'], $key, $transaction['amount']]);
            $patterns[$patternKey]['months'][$month][$fingerprint] ??= $transaction;
        }
    }

    $matched = 0;
    foreach ($patterns as $pattern) {
        foreach (($pattern['months'] ?? []) as $month => $monthTransactions) {
            $available = array_values($monthTransactions);
            $monthRules = array_values($pattern['rules']);
            usort($monthRules, fn(array $a, array $b): int => (float)$a['expected_amount'] <=> (float)$b['expected_amount']);
            foreach ($monthRules as $rule) {
                if (!$available) {
                    break;
                }
                usort($available, fn(array $a, array $b): int =>
                    abs((float)$a['amount'] - (float)$rule['expected_amount']) <=> abs((float)$b['amount'] - (float)$rule['expected_amount']));
                $transaction = array_shift($available);
                $insert->execute([
                    (int)$rule['rule_id'],
                    $month,
                    (string)$transaction['transaction_date'] . ' 12:00:00',
                    (int)$transaction['id'],
                ]);
                $matched += $insert->rowCount();
            }
        }
    }
    return ['matched' => $matched];
}
