<?php
declare(strict_types=1);

require_once __DIR__ . '/banking/PluggyClient.php';
require_once __DIR__ . '/bank-core.php';

function bank_sync_config(): array
{
    global $config;
    return is_array($config['bank_sync'] ?? null) ? $config['bank_sync'] : [];
}

function bank_sync_pluggy_item_ids(array $pluggy): array
{
    $values = $pluggy['item_ids'] ?? ($pluggy['item_id'] ?? []);
    if (is_string($values)) {
        $values = explode(',', $values);
    }
    if (!is_array($values)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map(fn($value): string => trim((string)$value), $values))));
}

function ensure_bank_sync_schema(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS bank_provider_accounts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(32) NOT NULL,
        external_account_id VARCHAR(100) NOT NULL,
        external_item_id VARCHAR(100) NOT NULL,
        local_account_id INT UNSIGNED NULL,
        bank_name VARCHAR(80) NOT NULL,
        account_label VARCHAR(160) NULL,
        last_balance DECIMAL(12,2) NULL,
        last_synced_at DATETIME NULL,
        last_error VARCHAR(500) NULL,
        raw_json JSON NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_provider_account (provider, external_account_id),
        INDEX idx_provider_account_local (local_account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS bank_provider_transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(32) NOT NULL,
        external_transaction_id VARCHAR(100) NOT NULL,
        bank_transaction_id BIGINT UNSIGNED NOT NULL,
        raw_hash CHAR(64) NOT NULL,
        synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_provider_transaction (provider, external_transaction_id),
        UNIQUE KEY uniq_provider_bank_transaction (provider, bank_transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS bank_sync_runs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(32) NOT NULL,
        trigger_name VARCHAR(32) NOT NULL,
        status ENUM('running','success','failed') NOT NULL DEFAULT 'running',
        accounts_count INT UNSIGNED NOT NULL DEFAULT 0,
        fetched_rows INT UNSIGNED NOT NULL DEFAULT 0,
        inserted_rows INT UNSIGNED NOT NULL DEFAULT 0,
        updated_rows INT UNSIGNED NOT NULL DEFAULT 0,
        matched_rows INT UNSIGNED NOT NULL DEFAULT 0,
        message VARCHAR(500) NULL,
        started_at DATETIME NOT NULL,
        finished_at DATETIME NULL,
        INDEX idx_bank_sync_provider_date (provider, started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function bank_sync_status(): array
{
    ensure_bank_sync_schema();
    $settings = bank_sync_config();
    $pluggy = is_array($settings['pluggy'] ?? null) ? $settings['pluggy'] : [];
    $configured = trim((string)($pluggy['client_id'] ?? '')) !== ''
        && trim((string)($pluggy['client_secret'] ?? '')) !== ''
        && bank_sync_pluggy_item_ids($pluggy) !== [];

    $lastRun = db()->query('SELECT * FROM bank_sync_runs ORDER BY id DESC LIMIT 1')->fetch() ?: null;
    $accounts = db()->query('SELECT provider, bank_name, account_label, last_balance, last_synced_at, last_error FROM bank_provider_accounts ORDER BY bank_name, account_label')->fetchAll();

    return [
        'provider' => (string)($settings['provider'] ?? 'pluggy'),
        'enabled' => !empty($settings['enabled']),
        'configured' => $configured,
        'ready' => !empty($settings['enabled']) && $configured,
        'schedule' => 'daily',
        'lastRun' => $lastRun,
        'accounts' => $accounts,
    ];
}

function sync_pluggy_banks(string $trigger = 'manual'): array
{
    ensure_bank_schema();
    ensure_bank_sync_schema();
    $settings = bank_sync_config();
    $pluggy = is_array($settings['pluggy'] ?? null) ? $settings['pluggy'] : [];

    if (empty($settings['enabled'])) {
        throw new RuntimeException('A sincronizacao bancaria esta desligada na configuracao.');
    }

    $clientId = trim((string)($pluggy['client_id'] ?? ''));
    $clientSecret = trim((string)($pluggy['client_secret'] ?? ''));
    $itemIds = bank_sync_pluggy_item_ids($pluggy);
    if ($clientId === '' || $clientSecret === '' || $itemIds === []) {
        throw new RuntimeException('Informe Client ID, Client Secret e ao menos um Item ID da Pluggy no servidor.');
    }

    $pdo = db();
    $pdo->prepare('INSERT INTO bank_sync_runs (provider, trigger_name, started_at) VALUES (?, ?, NOW())')
        ->execute(['pluggy', $trigger]);
    $runId = (int)$pdo->lastInsertId();
    $totals = ['accounts' => 0, 'fetched' => 0, 'inserted' => 0, 'updated' => 0, 'matched' => 0];

    try {
        $client = new PluggyClient($clientId, $clientSecret);
        foreach ($itemIds as $itemId) {
            $accounts = array_values(array_filter($client->accounts($itemId), fn($account): bool => is_array($account) && strtoupper((string)($account['type'] ?? 'BANK')) === 'BANK'));
            $totals['accounts'] += count($accounts);

            foreach ($accounts as $account) {
                $result = sync_pluggy_account($client, $itemId, $account, (int)($settings['lookback_days'] ?? 365), $runId);
                foreach (['fetched', 'inserted', 'updated', 'matched'] as $key) {
                    $totals[$key] += $result[$key];
                }
            }
        }

        $message = $totals['accounts'] === 0 ? 'Nenhuma conta bancaria encontrada no Item do Meu Pluggy.' : null;
        $pdo->prepare("UPDATE bank_sync_runs SET status='success', accounts_count=?, fetched_rows=?, inserted_rows=?, updated_rows=?, matched_rows=?, message=?, finished_at=NOW() WHERE id=?")
            ->execute([$totals['accounts'], $totals['fetched'], $totals['inserted'], $totals['updated'], $totals['matched'], $message, $runId]);
        audit('sync', 'bank_provider', null, ['provider' => 'pluggy'] + $totals);
    } catch (Throwable $e) {
        $message = substr($e->getMessage(), 0, 500);
        $pdo->prepare("UPDATE bank_sync_runs SET status='failed', message=?, finished_at=NOW() WHERE id=?")->execute([$message, $runId]);
        throw $e;
    }

    return $totals + ['status' => bank_sync_status()];
}

function sync_pluggy_account(PluggyClient $client, string $itemId, array $account, int $lookbackDays, int $runId): array
{
    $externalAccountId = trim((string)($account['id'] ?? ''));
    if ($externalAccountId === '') {
        return ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'matched' => 0];
    }

    $bankName = pluggy_bank_name($account);
    $accountLabel = trim((string)($account['marketingName'] ?? $account['name'] ?? $bankName));
    $localAccountId = find_or_create_bank_account($bankName);
    $previous = db()->prepare("SELECT last_synced_at FROM bank_provider_accounts WHERE provider='pluggy' AND external_account_id=? LIMIT 1");
    $previous->execute([$externalAccountId]);
    $lastSyncedAt = $previous->fetchColumn();
    $dateFrom = $lastSyncedAt
        ? date('Y-m-d', strtotime((string)$lastSyncedAt . ' -14 days'))
        : date('Y-m-d', strtotime('-' . max(30, min(365, $lookbackDays)) . ' days'));

    $transactions = $client->transactions($externalAccountId, $dateFrom);
    $importHash = hash('sha256', 'pluggy|' . $externalAccountId . '|' . $runId);
    $sourceFile = 'Meu Pluggy - ' . $accountLabel;
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare("INSERT INTO bank_provider_accounts
            (provider, external_account_id, external_item_id, local_account_id, bank_name, account_label, last_balance, last_error, raw_json)
            VALUES ('pluggy', ?, ?, ?, ?, ?, ?, NULL, ?)
            ON DUPLICATE KEY UPDATE external_item_id=VALUES(external_item_id), local_account_id=VALUES(local_account_id), bank_name=VALUES(bank_name), account_label=VALUES(account_label), last_balance=VALUES(last_balance), last_error=NULL, raw_json=VALUES(raw_json)")
            ->execute([
                $externalAccountId,
                $itemId,
                $localAccountId,
                $bankName,
                $accountLabel,
                isset($account['balance']) ? (float)$account['balance'] : null,
                json_encode($account, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

        $pdo->prepare('INSERT INTO bank_imports (bank_name, account_id, account_label, file_name, file_hash, imported_by) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$bankName, $localAccountId, $accountLabel, $sourceFile, $importHash, $_SESSION['user_id'] ?? null]);
        $importId = (int)$pdo->lastInsertId();
        $result = ['fetched' => count($transactions), 'inserted' => 0, 'updated' => 0, 'matched' => 0];
        $dates = [];

        foreach ($transactions as $transaction) {
            $normalized = normalize_pluggy_transaction($transaction, $bankName);
            if ($normalized === null) {
                continue;
            }
            $dates[] = $normalized['transaction_date'];
            $change = upsert_pluggy_transaction($importId, $localAccountId, $sourceFile, $normalized, $transaction);
            if ($change['action'] !== null) {
                $result[$change['action']]++;
            }
            $result['matched'] += $change['matched'];
        }

        sort($dates);
        $periodStart = $dates[0] ?? null;
        $periodEnd = $dates[count($dates) - 1] ?? null;
        if ($result['inserted'] === 0) {
            $pdo->prepare('DELETE FROM bank_imports WHERE id=?')->execute([$importId]);
        } else {
            $pdo->prepare('UPDATE bank_imports SET period_start=?, period_end=?, imported_rows=?, matched_rows=? WHERE id=?')
                ->execute([$periodStart, $periodEnd, $result['inserted'], $result['matched'], $importId]);
        }
        $pdo->prepare("UPDATE bank_provider_accounts SET last_synced_at=NOW(), last_error=NULL WHERE provider='pluggy' AND external_account_id=?")
            ->execute([$externalAccountId]);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        db()->prepare("UPDATE bank_provider_accounts SET last_error=? WHERE provider='pluggy' AND external_account_id=?")
            ->execute([substr($e->getMessage(), 0, 500), $externalAccountId]);
        throw $e;
    }
}

function normalize_pluggy_transaction(array $transaction, string $bankName): ?array
{
    if (strtoupper((string)($transaction['status'] ?? 'POSTED')) !== 'POSTED') {
        return null;
    }
    $externalId = trim((string)($transaction['id'] ?? ''));
    $description = trim((string)($transaction['description'] ?? $transaction['descriptionRaw'] ?? ''));
    $dateValue = trim((string)($transaction['date'] ?? ''));
    $amount = abs((float)($transaction['amount'] ?? 0));
    if ($externalId === '' || $description === '' || $dateValue === '' || $amount <= 0) {
        return null;
    }

    try {
        $date = (new DateTimeImmutable($dateValue))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
    } catch (Throwable) {
        $date = normalize_date(substr($dateValue, 0, 10));
    }
    if (!$date) {
        return null;
    }

    $type = strtoupper((string)($transaction['type'] ?? ''));
    $direction = $type === 'CREDIT' ? 'credit' : ($type === 'DEBIT' ? 'debit' : ((float)$transaction['amount'] < 0 ? 'debit' : 'credit'));
    $balance = isset($transaction['balance']) && is_numeric($transaction['balance']) ? (float)$transaction['balance'] : null;

    return [
        'external_id' => $externalId,
        'bank_name' => $bankName,
        'transaction_date' => $date,
        'description' => $description,
        'movement_type' => trim((string)($transaction['category'] ?? $transaction['operationCategory'] ?? '')),
        'document_number' => trim((string)($transaction['providerCode'] ?? $transaction['providerId'] ?? '')),
        'direction' => $direction,
        'amount' => $amount,
        'balance' => $balance,
    ];
}

function upsert_pluggy_transaction(int $importId, int $accountId, string $sourceFile, array $row, array $raw): array
{
    $pdo = db();
    $rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $rawHash = hash('sha256', $rawJson ?: '');
    $mapping = $pdo->prepare("SELECT bank_transaction_id, raw_hash FROM bank_provider_transactions WHERE provider='pluggy' AND external_transaction_id=? LIMIT 1");
    $mapping->execute([$row['external_id']]);
    $existingMapping = $mapping->fetch();

    if ($existingMapping) {
        if (hash_equals((string)$existingMapping['raw_hash'], $rawHash)) {
            return ['action' => null, 'matched' => 0];
        }
        $pdo->prepare('UPDATE bank_transactions SET account_id=?, bank_name=?, transaction_date=?, description=?, movement_type=?, document_number=?, direction=?, amount=?, balance=?, raw_json=? WHERE id=?')
            ->execute([$accountId, $row['bank_name'], $row['transaction_date'], $row['description'], $row['movement_type'], $row['document_number'], $row['direction'], $row['amount'], $row['balance'], $rawJson, $existingMapping['bank_transaction_id']]);
        $pdo->prepare("UPDATE bank_provider_transactions SET raw_hash=? WHERE provider='pluggy' AND external_transaction_id=?")
            ->execute([$rawHash, $row['external_id']]);
        return ['action' => 'updated', 'matched' => 0];
    }

    $contentHash = hash('sha256', implode('|', [
        $row['bank_name'], $row['transaction_date'], $row['description'], $row['document_number'], $row['direction'],
        number_format($row['amount'], 2, '.', ''), $row['balance'] === null ? '' : (string)$row['balance'],
    ]));
    $existing = $pdo->prepare("SELECT bt.id
        FROM bank_transactions bt
        LEFT JOIN bank_provider_transactions bpt
          ON bpt.provider='pluggy' AND bpt.bank_transaction_id=bt.id
        WHERE bt.source_hash=? AND bpt.id IS NULL
        ORDER BY bt.id
        LIMIT 1");
    $existing->execute([$contentHash]);
    $bankTransactionId = (int)($existing->fetchColumn() ?: 0);
    $linkedExistingTransaction = $bankTransactionId > 0;
    $matchedId = null;

    if (!$bankTransactionId) {
        // IDs remotos distinguem movimentos bancarios com conteudo identico.
        $sourceHash = hash('sha256', 'pluggy|' . $row['external_id']);
        $matchedId = auto_match_transaction($row['transaction_date'], $row['amount'], $row['direction'], $row['description'], $accountId);
        $insert = $pdo->prepare('INSERT INTO bank_transactions
            (import_id, account_id, bank_name, source_file, source_hash, transaction_date, description, movement_type, document_number, direction, amount, balance, category_id, matched_transaction_id, raw_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$importId, $accountId, $row['bank_name'], $sourceFile, $sourceHash, $row['transaction_date'], $row['description'], $row['movement_type'], $row['document_number'], $row['direction'], $row['amount'], $row['balance'], guess_category_id($row['description'], $row['bank_name'], $row['direction'], $accountId), $matchedId, $rawJson]);
        $bankTransactionId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("INSERT INTO bank_provider_transactions (provider, external_transaction_id, bank_transaction_id, raw_hash) VALUES ('pluggy', ?, ?, ?)")
        ->execute([$row['external_id'], $bankTransactionId, $rawHash]);

    return ['action' => $linkedExistingTransaction ? null : 'inserted', 'matched' => $matchedId ? 1 : 0];
}

function pluggy_bank_name(array $account): string
{
    $candidates = [
        $account['institution']['name'] ?? null,
        $account['connector']['name'] ?? null,
        $account['bankData']['bankName'] ?? null,
        $account['marketingName'] ?? null,
        $account['name'] ?? null,
    ];
    $label = trim((string)(array_values(array_filter($candidates, fn($value) => is_string($value) && trim($value) !== ''))[0] ?? 'Meu Pluggy'));
    $normalized = normalize_match_text($label);
    if (str_contains($normalized, 'santander')) {
        return 'Santander';
    }
    if (str_contains($normalized, 'pagbank') || str_contains($normalized, 'pagseguro')) {
        return 'PagBank';
    }
    return substr($label, 0, 80);
}
