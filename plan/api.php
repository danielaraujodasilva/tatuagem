<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'login') {
        verify_csrf();
        $input = json_input();
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([trim((string)($input['email'] ?? ''))]);
        $user = $stmt->fetch();

        if (!$user || !password_verify((string)($input['password'] ?? ''), $user['password_hash'])) {
            json_response(['ok' => false, 'message' => 'E-mail ou senha invalidos.'], 422);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        audit('login', 'user', (int)$user['id']);
        json_response(['ok' => true]);
    }

    $user = require_auth();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        verify_csrf();
    }

    match ($action) {
        'bootstrap' => bootstrap($user),
        'transactions' => transactions(),
        'save_transaction' => save_transaction(),
        'delete_transaction' => delete_transaction(),
        'toggle_paid' => toggle_paid(),
        'save_sheet_import' => save_sheet_import(),
        'save_category' => save_category(),
        'save_budget' => save_budget(),
        'save_goal' => save_goal(),
        'save_account' => save_account(),
        'save_recurring' => save_recurring(),
        'bank_transactions' => bank_transactions(),
        'save_bank_import' => save_bank_import(),
        'overview' => overview(),
        default => json_response(['ok' => false, 'message' => 'Acao desconhecida.'], 404),
    };
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Erro interno.', 'detail' => $e->getMessage()], 500);
}

function bootstrap(array $user): never
{
    ensure_finance_schema();
    ensure_bank_schema();
    json_response([
        'ok' => true,
        'user' => $user,
        'csrf' => csrf_token(),
        'categories' => db()->query('SELECT * FROM categories ORDER BY name')->fetchAll(),
        'accounts' => db()->query('SELECT * FROM accounts ORDER BY name')->fetchAll(),
        'budgets' => db()->query('SELECT b.*, c.name category_name, c.color FROM budgets b JOIN categories c ON c.id = b.category_id ORDER BY b.month DESC, c.name')->fetchAll(),
        'goals' => db()->query('SELECT * FROM goals ORDER BY target_date IS NULL, target_date, name')->fetchAll(),
        'recurring' => db()->query('SELECT r.*, c.name category_name FROM recurring_rules r LEFT JOIN categories c ON c.id = r.category_id ORDER BY next_due_date')->fetchAll(),
        'bankImports' => db()->query('SELECT * FROM bank_imports ORDER BY imported_at DESC LIMIT 30')->fetchAll(),
        'bankOverview' => build_bank_overview(),
        'overview' => build_overview(),
    ]);
}

function transactions(): never
{
    ensure_finance_schema();
    $where = [];
    $params = [];

    foreach (['status', 'type', 'category_id', 'account_id'] as $field) {
        if (isset($_GET[$field]) && $_GET[$field] !== '') {
            $where[] = "t.$field = ?";
            $params[] = $_GET[$field];
        }
    }

    if (!empty($_GET['month'])) {
        $where[] = "(t.reference_month = ? OR (t.reference_month IS NULL AND DATE_FORMAT(t.due_date, '%Y-%m') = ?))";
        array_push($params, $_GET['month'], $_GET['month']);
    }

    if (!empty($_GET['q'])) {
        $where[] = '(t.description LIKE ? OR t.payment_code LIKE ? OR t.notes LIKE ?)';
        $term = '%' . $_GET['q'] . '%';
        array_push($params, $term, $term, $term);
    }

    $sql = "SELECT t.*, c.name category_name, c.color category_color, a.name account_name
            FROM transactions t
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN accounts a ON a.id = t.account_id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY COALESCE(t.due_date, t.created_at) DESC, t.id DESC LIMIT 600';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    json_response(['ok' => true, 'transactions' => $stmt->fetchAll(), 'overview' => build_overview()]);
}

function save_transaction(): never
{
    ensure_finance_schema();
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $data = [
        'type' => in_array(($input['type'] ?? 'expense'), ['income', 'expense', 'transfer'], true) ? $input['type'] : 'expense',
        'amount' => money_to_float($input['amount'] ?? 0),
        'description' => trim((string)($input['description'] ?? '')),
        'category_id' => ($input['category_id'] ?? '') === '' ? null : (int)$input['category_id'],
        'account_id' => ($input['account_id'] ?? '') === '' ? null : (int)$input['account_id'],
        'due_date' => normalize_date($input['due_date'] ?? null),
        'paid_at' => normalize_date($input['paid_at'] ?? null),
        'status' => in_array(($input['status'] ?? 'pending'), ['pending', 'paid', 'late', 'ignored'], true) ? $input['status'] : 'pending',
        'owner' => trim((string)($input['owner'] ?? '')),
        'payment_code' => trim((string)($input['payment_code'] ?? '')),
        'source_sheet' => trim((string)($input['source_sheet'] ?? 'manual')),
        'reference_month' => trim((string)($input['reference_month'] ?? '')) ?: null,
        'notes' => trim((string)($input['notes'] ?? '')),
        'is_fixed' => !empty($input['is_fixed']) ? 1 : 0,
    ];

    if ($data['description'] === '' || $data['amount'] < 0) {
        json_response(['ok' => false, 'message' => 'Informe descricao e valor valido.'], 422);
    }

    if ($id > 0) {
        $sql = 'UPDATE transactions SET type=:type, amount=:amount, description=:description, category_id=:category_id, account_id=:account_id,
                due_date=:due_date, paid_at=:paid_at, status=:status, owner=:owner, payment_code=:payment_code, source_sheet=:source_sheet,
                reference_month=:reference_month, notes=:notes, is_fixed=:is_fixed, updated_at=NOW() WHERE id=:id';
        $data['id'] = $id;
        db()->prepare($sql)->execute($data);
        audit('update', 'transaction', $id, $data);
    } else {
        $sql = 'INSERT INTO transactions (type, amount, description, category_id, account_id, due_date, paid_at, status, owner, payment_code,
                source_sheet, reference_month, notes, is_fixed, created_at, updated_at) VALUES (:type, :amount, :description, :category_id, :account_id,
                :due_date, :paid_at, :status, :owner, :payment_code, :source_sheet, :reference_month, :notes, :is_fixed, NOW(), NOW())';
        db()->prepare($sql)->execute($data);
        $id = (int)db()->lastInsertId();
        audit('create', 'transaction', $id, $data);
    }

    json_response(['ok' => true, 'id' => $id]);
}

function delete_transaction(): never
{
    $id = (int)(json_input()['id'] ?? 0);
    db()->prepare('DELETE FROM transactions WHERE id = ?')->execute([$id]);
    audit('delete', 'transaction', $id);
    json_response(['ok' => true]);
}

function toggle_paid(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $status = ($input['status'] ?? '') === 'paid' ? 'paid' : 'pending';
    $paidAt = $status === 'paid' ? date('Y-m-d') : null;
    db()->prepare('UPDATE transactions SET status = ?, paid_at = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $paidAt, $id]);
    audit('toggle_paid', 'transaction', $id, ['status' => $status]);
    json_response(['ok' => true]);
}

function save_category(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));
    $color = trim((string)($input['color'] ?? '#2563eb'));
    if ($name === '') {
        json_response(['ok' => false, 'message' => 'Nome obrigatorio.'], 422);
    }
    if ($id > 0) {
        db()->prepare('UPDATE categories SET name = ?, color = ? WHERE id = ?')->execute([$name, $color, $id]);
    } else {
        db()->prepare('INSERT INTO categories (name, color) VALUES (?, ?)')->execute([$name, $color]);
        $id = (int)db()->lastInsertId();
    }
    json_response(['ok' => true, 'id' => $id]);
}

function save_budget(): never
{
    $input = json_input();
    db()->prepare('INSERT INTO budgets (category_id, month, limit_amount)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE limit_amount = VALUES(limit_amount)')
        ->execute([(int)$input['category_id'], (string)$input['month'], money_to_float($input['limit_amount'] ?? 0)]);
    json_response(['ok' => true]);
}

function save_goal(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $data = [
        trim((string)($input['name'] ?? '')),
        money_to_float($input['target_amount'] ?? 0),
        money_to_float($input['current_amount'] ?? 0),
        normalize_date($input['target_date'] ?? null),
    ];
    if ($data[0] === '') {
        json_response(['ok' => false, 'message' => 'Nome obrigatorio.'], 422);
    }
    if ($id > 0) {
        $data[] = $id;
        db()->prepare('UPDATE goals SET name=?, target_amount=?, current_amount=?, target_date=? WHERE id=?')->execute($data);
    } else {
        db()->prepare('INSERT INTO goals (name, target_amount, current_amount, target_date) VALUES (?, ?, ?, ?)')->execute($data);
    }
    json_response(['ok' => true]);
}

function save_account(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $data = [
        trim((string)($input['name'] ?? '')),
        trim((string)($input['type'] ?? 'corrente')),
        money_to_float($input['opening_balance'] ?? 0),
    ];
    if ($data[0] === '') {
        json_response(['ok' => false, 'message' => 'Nome obrigatorio.'], 422);
    }
    if ($id > 0) {
        $data[] = $id;
        db()->prepare('UPDATE accounts SET name=?, type=?, opening_balance=? WHERE id=?')->execute($data);
    } else {
        db()->prepare('INSERT INTO accounts (name, type, opening_balance) VALUES (?, ?, ?)')->execute($data);
    }
    json_response(['ok' => true]);
}

function save_recurring(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $data = [
        trim((string)($input['description'] ?? '')),
        money_to_float($input['amount'] ?? 0),
        ($input['category_id'] ?? '') === '' ? null : (int)$input['category_id'],
        trim((string)($input['frequency'] ?? 'monthly')),
        normalize_date($input['next_due_date'] ?? null),
        !empty($input['is_active']) ? 1 : 0,
    ];
    if ($data[0] === '') {
        json_response(['ok' => false, 'message' => 'Descricao obrigatoria.'], 422);
    }
    if ($id > 0) {
        $data[] = $id;
        db()->prepare('UPDATE recurring_rules SET description=?, amount=?, category_id=?, frequency=?, next_due_date=?, is_active=? WHERE id=?')->execute($data);
    } else {
        db()->prepare('INSERT INTO recurring_rules (description, amount, category_id, frequency, next_due_date, is_active) VALUES (?, ?, ?, ?, ?, ?)')->execute($data);
    }
    json_response(['ok' => true]);
}

function ensure_finance_schema(): void
{
    $stmt = db()->prepare("SELECT COUNT(*) total FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'reference_month'");
    $stmt->execute();
    if ((int)($stmt->fetch()['total'] ?? 0) === 0) {
        db()->exec("ALTER TABLE transactions ADD COLUMN reference_month CHAR(7) NULL AFTER source_sheet");
        db()->exec("ALTER TABLE transactions ADD INDEX idx_transactions_reference_month (reference_month)");
    }
}

function save_sheet_import(): never
{
    ensure_finance_schema();
    $input = json_input();
    $rows = is_array($input['rows'] ?? null) ? $input['rows'] : [];
    if (!$rows) {
        json_response(['ok' => false, 'message' => 'Nenhuma linha valida para importar.'], 422);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM transactions WHERE source_sheet IS NOT NULL AND source_sheet <> '' AND source_sheet <> 'manual'");

        $categoryStmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
        $insertCategory = $pdo->prepare('INSERT INTO categories (name, color) VALUES (?, ?)');
        $insert = $pdo->prepare('INSERT INTO transactions
            (type, amount, description, category_id, account_id, due_date, paid_at, status, owner, payment_code, source_sheet, reference_month, notes, is_fixed, created_at, updated_at)
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');

        $count = 0;
        foreach ($rows as $row) {
            $description = trim((string)($row['description'] ?? ''));
            $sourceSheet = trim((string)($row['source_sheet'] ?? ''));
            if ($description === '' && trim((string)($row['payment_code'] ?? '')) === '') {
                continue;
            }

            $categoryName = normalize_category_name(trim((string)($row['category'] ?? 'Sem categoria')));
            $categoryStmt->execute([$categoryName]);
            $category = $categoryStmt->fetch();
            if (!$category) {
                $insertCategory->execute([$categoryName, category_color($categoryName)]);
                $categoryId = (int)$pdo->lastInsertId();
            } else {
                $categoryId = (int)$category['id'];
            }

            $status = normalize_status((string)($row['status'] ?? 'pending'));
            $type = normalize_transaction_type((string)($row['type'] ?? 'expense'));
            $date = normalize_date($row['due_date'] ?? null);
            $paidAt = $status === 'paid' ? ($date ?: date('Y-m-d')) : null;
            $amount = max(0, money_to_float($row['amount'] ?? 0));
            $notes = 'Importado automaticamente da planilha Google em ' . date('Y-m-d H:i:s');
            if (!empty($row['row_number'])) {
                $notes .= ' | Linha original: ' . (int)$row['row_number'];
            }

            $insert->execute([
                $type,
                $amount,
                $description !== '' ? $description : '(sem descricao)',
                $categoryId,
                $date,
                $paidAt,
                $status,
                trim((string)($row['owner'] ?? '')),
                trim((string)($row['payment_code'] ?? '')),
                $sourceSheet,
                trim((string)($row['reference_month'] ?? '')) ?: null,
                $notes,
                !empty($row['is_fixed']) ? 1 : 0,
            ]);
            $count++;
        }

        audit('replace_google_sheet_import', 'transaction', null, ['rows' => $count]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_response(['ok' => true, 'imported' => $count]);
}

function normalize_category_name(string $category): string
{
    if ($category === '') {
        return 'Sem categoria';
    }
    $map = [
        'Saúde' => 'Saude',
        'Alimentação' => 'Alimentacao',
        'Educação' => 'Educacao',
        'Serviços de utilidade pública' => 'Servicos',
        'contabilidade' => 'Servicos',
        'contabilidade ' => 'Servicos',
        'Categoria personalizada 1' => 'Pessoal',
    ];
    return $map[$category] ?? $category;
}

function category_color(string $category): string
{
    return [
        'Moradia' => '#2563eb',
        'Pessoal' => '#7c3aed',
        'Saude' => '#059669',
        'Alimentacao' => '#ea580c',
        'Transporte' => '#0891b2',
        'Educacao' => '#9333ea',
        'Impostos' => '#dc2626',
        'Servicos' => '#0f766e',
        'Investimentos' => '#16a34a',
    ][$category] ?? '#64748b';
}

function normalize_status(string $status): string
{
    $normalized = strtolower(trim($status));
    if (strpos($normalized, 'pago') !== false) {
        return 'paid';
    }
    if (strpos($normalized, 'ignorado') !== false || strpos($normalized, 'nao pagar') !== false || strpos($normalized, 'não pagar') !== false) {
        return 'ignored';
    }
    return 'pending';
}

function normalize_transaction_type(string $type): string
{
    $normalized = strtolower(trim($type));
    if (strpos($normalized, 'entrada') !== false || strpos($normalized, 'receita') !== false) {
        return 'income';
    }
    if (strpos($normalized, 'transfer') !== false) {
        return 'transfer';
    }
    return 'expense';
}

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

function bank_transactions(): never
{
    ensure_bank_schema();
    $where = [];
    $params = [];

    if (!empty($_GET['bank'])) {
        $where[] = 'bt.bank_name = ?';
        $params[] = $_GET['bank'];
    }
    if (!empty($_GET['month'])) {
        $where[] = "DATE_FORMAT(bt.transaction_date, '%Y-%m') = ?";
        $params[] = $_GET['month'];
    }
    if (!empty($_GET['q'])) {
        $where[] = '(bt.description LIKE ? OR bt.movement_type LIKE ? OR bt.document_number LIKE ?)';
        $term = '%' . $_GET['q'] . '%';
        array_push($params, $term, $term, $term);
    }

    $sql = "SELECT bt.*, a.name account_name, c.name category_name, t.description matched_description
            FROM bank_transactions bt
            LEFT JOIN accounts a ON a.id = bt.account_id
            LEFT JOIN categories c ON c.id = bt.category_id
            LEFT JOIN transactions t ON t.id = bt.matched_transaction_id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY bt.transaction_date DESC, bt.id DESC LIMIT 800';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    json_response([
        'ok' => true,
        'bankTransactions' => $stmt->fetchAll(),
        'bankOverview' => build_bank_overview(),
    ]);
}

function save_bank_import(): never
{
    ensure_bank_schema();
    $input = json_input();
    $rows = is_array($input['rows'] ?? null) ? $input['rows'] : [];
    $bankName = trim((string)($input['bank_name'] ?? ''));
    $fileName = trim((string)($input['file_name'] ?? 'extrato'));
    $fileHash = hash('sha256', (string)($input['file_hash'] ?? $fileName . json_encode($rows)));
    $accountId = isset($input['account_id']) && $input['account_id'] !== '' ? (int)$input['account_id'] : null;

    if ($bankName === '' || !$rows) {
        json_response(['ok' => false, 'message' => 'Arquivo sem movimentacoes validas.'], 422);
    }

    if (!$accountId) {
        $accountId = find_or_create_bank_account($bankName);
    }

    $existing = db()->prepare('SELECT id FROM bank_imports WHERE file_hash = ? LIMIT 1');
    $existing->execute([$fileHash]);
    if ($existing->fetch()) {
        json_response(['ok' => false, 'message' => 'Este arquivo ja foi importado.'], 409);
    }

    $dates = array_values(array_filter(array_map(fn($row) => normalize_date($row['transaction_date'] ?? ''), $rows)));
    sort($dates);
    $periodStart = $dates[0] ?? null;
    $periodEnd = $dates[count($dates) - 1] ?? null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO bank_imports (bank_name, account_id, account_label, file_name, file_hash, period_start, period_end, imported_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$bankName, $accountId, $input['account_label'] ?? null, $fileName, $fileHash, $periodStart, $periodEnd, $_SESSION['user_id'] ?? null]);
        $importId = (int)$pdo->lastInsertId();

        $insert = $pdo->prepare('INSERT IGNORE INTO bank_transactions
            (import_id, account_id, bank_name, source_file, source_hash, transaction_date, description, movement_type, document_number, direction, amount, balance, category_id, matched_transaction_id, raw_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $imported = 0;
        $matched = 0;
        foreach ($rows as $row) {
            $date = normalize_date($row['transaction_date'] ?? '');
            $description = trim((string)($row['description'] ?? ''));
            $direction = ($row['direction'] ?? '') === 'credit' ? 'credit' : 'debit';
            $amount = money_to_float($row['amount'] ?? 0);
            if (!$date || $description === '' || $amount <= 0) {
                continue;
            }

            $sourceHash = hash('sha256', implode('|', [
                $bankName,
                $date,
                $description,
                (string)($row['document_number'] ?? ''),
                $direction,
                number_format($amount, 2, '.', ''),
                (string)($row['balance'] ?? ''),
            ]));

            $matchedId = auto_match_transaction($date, $amount, $direction, $description, $accountId);
            if ($matchedId) {
                $matched++;
            }

            $insert->execute([
                $importId,
                $accountId,
                $bankName,
                $fileName,
                $sourceHash,
                $date,
                $description,
                trim((string)($row['movement_type'] ?? '')),
                trim((string)($row['document_number'] ?? '')),
                $direction,
                $amount,
                isset($row['balance']) && $row['balance'] !== '' ? money_to_float($row['balance']) : null,
                guess_category_id($description),
                $matchedId,
                json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $imported += $insert->rowCount() > 0 ? 1 : 0;
        }

        $pdo->prepare('UPDATE bank_imports SET imported_rows = ?, matched_rows = ? WHERE id = ?')->execute([$imported, $matched, $importId]);
        audit('import', 'bank_import', $importId, ['bank' => $bankName, 'rows' => $imported, 'matched' => $matched]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_response(['ok' => true, 'imported' => $imported, 'matched' => $matched]);
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

function overview(): never
{
    json_response(['ok' => true, 'overview' => build_overview()]);
}

function build_overview(): array
{
    ensure_finance_schema();
    $month = $_GET['month'] ?? date('Y-m');
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    $stmt = db()->prepare("SELECT
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) income,
        SUM(CASE WHEN type = 'expense' AND status <> 'ignored' THEN amount ELSE 0 END) expenses,
        SUM(CASE WHEN type = 'expense' AND status = 'paid' THEN amount ELSE 0 END) paid,
        SUM(CASE WHEN type = 'expense' AND status IN ('pending','late') THEN amount ELSE 0 END) pending
        FROM transactions WHERE (reference_month = ? OR (reference_month IS NULL AND due_date BETWEEN ? AND ?))");
    $stmt->execute([$month, $monthStart, $monthEnd]);
    $totals = $stmt->fetch() ?: [];

    $byCategory = db()->prepare("SELECT COALESCE(c.name, 'Sem categoria') name, COALESCE(c.color, '#64748b') color, SUM(t.amount) total
        FROM transactions t LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.type = 'expense' AND t.status <> 'ignored' AND (t.reference_month = ? OR (t.reference_month IS NULL AND t.due_date BETWEEN ? AND ?))
        GROUP BY name, color ORDER BY total DESC");
    $byCategory->execute([$month, $monthStart, $monthEnd]);

    $monthly = db()->query("SELECT COALESCE(reference_month, DATE_FORMAT(due_date, '%Y-%m')) month,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) income,
        SUM(CASE WHEN type = 'expense' AND status <> 'ignored' THEN amount ELSE 0 END) expenses,
        SUM(CASE WHEN type = 'expense' AND status IN ('pending','late') THEN amount ELSE 0 END) pending
        FROM transactions WHERE reference_month IS NOT NULL OR due_date IS NOT NULL GROUP BY month ORDER BY month")->fetchAll();

    $upcoming = db()->prepare("SELECT t.*, c.name category_name FROM transactions t LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.status IN ('pending','late') AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL 21 DAY)
        ORDER BY t.due_date ASC LIMIT 10");
    $upcoming->execute();

    return [
        'month' => $month,
        'totals' => [
            'income' => (float)($totals['income'] ?? 0),
            'expenses' => (float)($totals['expenses'] ?? 0),
            'paid' => (float)($totals['paid'] ?? 0),
            'pending' => (float)($totals['pending'] ?? 0),
            'balance' => (float)($totals['income'] ?? 0) - (float)($totals['expenses'] ?? 0),
        ],
        'byCategory' => $byCategory->fetchAll(),
        'monthly' => $monthly,
        'upcoming' => $upcoming->fetchAll(),
    ];
}
