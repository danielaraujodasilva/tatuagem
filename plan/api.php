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
        'save_category' => save_category(),
        'save_budget' => save_budget(),
        'save_goal' => save_goal(),
        'save_account' => save_account(),
        'import_accounts' => import_accounts(),
        'account_history' => account_history(),
        'resolve_account_conflict' => resolve_account_conflict(),
        'save_recurring' => save_recurring(),
        'overview' => overview(),
        default => json_response(['ok' => false, 'message' => 'Acao desconhecida.'], 404),
    };
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Erro interno.', 'detail' => $e->getMessage()], 500);
}

function bootstrap(array $user): never
{
    json_response([
        'ok' => true,
        'user' => $user,
        'csrf' => csrf_token(),
        'categories' => db()->query('SELECT * FROM categories ORDER BY name')->fetchAll(),
        'accounts' => db()->query('SELECT * FROM accounts ORDER BY name')->fetchAll(),
        'budgets' => db()->query('SELECT b.*, c.name category_name, c.color FROM budgets b JOIN categories c ON c.id = b.category_id ORDER BY b.month DESC, c.name')->fetchAll(),
        'goals' => db()->query('SELECT * FROM goals ORDER BY target_date IS NULL, target_date, name')->fetchAll(),
        'recurring' => db()->query('SELECT r.*, c.name category_name FROM recurring_rules r LEFT JOIN categories c ON c.id = r.category_id ORDER BY next_due_date')->fetchAll(),
        'overview' => build_overview(),
    ]);
}

function transactions(): never
{
    $where = [];
    $params = [];

    foreach (['status', 'type', 'category_id', 'account_id'] as $field) {
        if (isset($_GET[$field]) && $_GET[$field] !== '') {
            $where[] = "t.$field = ?";
            $params[] = $_GET[$field];
        }
    }

    if (!empty($_GET['month'])) {
        $where[] = "DATE_FORMAT(t.due_date, '%Y-%m') = ?";
        $params[] = $_GET['month'];
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
        'notes' => trim((string)($input['notes'] ?? '')),
        'is_fixed' => !empty($input['is_fixed']) ? 1 : 0,
    ];

    if ($data['description'] === '' || $data['amount'] < 0) {
        json_response(['ok' => false, 'message' => 'Informe descricao e valor valido.'], 422);
    }

    if ($id > 0) {
        $sql = 'UPDATE transactions SET type=:type, amount=:amount, description=:description, category_id=:category_id, account_id=:account_id,
                due_date=:due_date, paid_at=:paid_at, status=:status, owner=:owner, payment_code=:payment_code, source_sheet=:source_sheet,
                notes=:notes, is_fixed=:is_fixed, updated_at=NOW() WHERE id=:id';
        $data['id'] = $id;
        db()->prepare($sql)->execute($data);
        audit('update', 'transaction', $id, $data);
    } else {
        $sql = 'INSERT INTO transactions (type, amount, description, category_id, account_id, due_date, paid_at, status, owner, payment_code,
                source_sheet, notes, is_fixed, created_at, updated_at) VALUES (:type, :amount, :description, :category_id, :account_id,
                :due_date, :paid_at, :status, :owner, :payment_code, :source_sheet, :notes, :is_fixed, NOW(), NOW())';
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
        $current = fetch_account($id);
        if (!$current) {
            json_response(['ok' => false, 'message' => 'Conta nao encontrada.'], 404);
        }

        $before = account_snapshot($current);
        $update = [
            'name' => $data[0],
            'type' => $data[1],
            'opening_balance' => $data[2],
            'id' => $id,
        ];
        db()->prepare('UPDATE accounts SET name=:name, type=:type, opening_balance=:opening_balance, last_manual_edit_at=NOW(), last_change_source=\'manual\', updated_at=NOW() WHERE id=:id')->execute($update);
        $after = fetch_account($id);
        save_account_version($id, 'update', 'manual', null, $before, account_snapshot($after ?: []));
        audit('update', 'account', $id, account_change_diff($before, account_snapshot($after ?: [])));
    } else {
        db()->prepare('INSERT INTO accounts (name, type, opening_balance, last_manual_edit_at, last_change_source, updated_at) VALUES (?, ?, ?, NOW(), \'manual\', NOW())')->execute($data);
        $id = (int)db()->lastInsertId();
        $after = fetch_account($id);
        save_account_version($id, 'create', 'manual', null, null, account_snapshot($after ?: []));
        audit('create', 'account', $id, account_snapshot($after ?: []));
    }
    json_response(['ok' => true, 'id' => $id]);
}

function import_accounts(): never
{
    $input = json_input();
    $rows = $input['rows'] ?? null;
    if (!is_array($rows)) {
        $rows = [$input];
    }

    $result = [
        'imported' => 0,
        'conflicts' => 0,
        'items' => [],
    ];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $result['items'][] = import_account_row($row, false, $result);
    }

    json_response(['ok' => true, 'result' => $result]);
}

function account_history(): never
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Conta invalida.'], 422);
    }

    $account = fetch_account($id);
    if (!$account) {
        json_response(['ok' => false, 'message' => 'Conta nao encontrada.'], 404);
    }

    $versions = db()->prepare('SELECT v.*, u.name user_name
        FROM account_versions v
        LEFT JOIN users u ON u.id = v.user_id
        WHERE v.account_id = ?
        ORDER BY v.created_at DESC, v.id DESC');
    $versions->execute([$id]);

    $conflicts = db()->prepare('SELECT c.*, u.name resolved_by_name
        FROM account_import_conflicts c
        LEFT JOIN users u ON u.id = c.resolved_by
        WHERE c.account_id = ?
        ORDER BY c.created_at DESC, c.id DESC');
    $conflicts->execute([$id]);

    json_response([
        'ok' => true,
        'account' => account_snapshot($account),
        'versions' => $versions->fetchAll(),
        'conflicts' => $conflicts->fetchAll(),
    ]);
}

function resolve_account_conflict(): never
{
    $input = json_input();
    $conflictId = (int)($input['conflict_id'] ?? 0);
    $resolution = (string)($input['resolution'] ?? '');

    if ($conflictId <= 0 || !in_array($resolution, ['keep_local', 'accept_import'], true)) {
        json_response(['ok' => false, 'message' => 'Conflito invalido.'], 422);
    }

    $stmt = db()->prepare('SELECT * FROM account_import_conflicts WHERE id = ? LIMIT 1');
    $stmt->execute([$conflictId]);
    $conflict = $stmt->fetch();
    if (!$conflict) {
        json_response(['ok' => false, 'message' => 'Conflito nao encontrado.'], 404);
    }
    if (!empty($conflict['resolved_at'])) {
        json_response(['ok' => false, 'message' => 'Conflito ja resolvido.'], 409);
    }

    $payload = json_decode((string)$conflict['payload_json'], true);
    if (!is_array($payload)) {
        json_response(['ok' => false, 'message' => 'Payload de conflito invalido.'], 500);
    }

    if ($resolution === 'accept_import') {
        $forced = import_account_row($payload, true);
        if (($forced['status'] ?? '') !== 'imported') {
            json_response(['ok' => false, 'message' => 'Nao foi possivel aplicar a importacao.'], 409);
        }
    }

    db()->prepare('UPDATE account_import_conflicts
        SET resolution = ?, resolved_by = ?, resolved_at = NOW()
        WHERE id = ?')
        ->execute([$resolution, $_SESSION['user_id'] ?? null, $conflictId]);

    audit('resolve_conflict', 'account', (int)$conflict['account_id'], [
        'conflict_id' => $conflictId,
        'resolution' => $resolution,
    ]);

    json_response(['ok' => true]);
}

function account_snapshot(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'type' => (string)($row['type'] ?? ''),
        'opening_balance' => (float)($row['opening_balance'] ?? 0),
        'source_key' => $row['source_key'] ?? null,
        'source_updated_at' => $row['source_updated_at'] ?? null,
        'last_manual_edit_at' => $row['last_manual_edit_at'] ?? null,
        'last_imported_at' => $row['last_imported_at'] ?? null,
        'last_change_source' => $row['last_change_source'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
    ];
}

function account_change_diff(array $before, array $after): array
{
    $changes = [];
    foreach (['name', 'type', 'opening_balance', 'source_key', 'source_updated_at', 'last_manual_edit_at', 'last_imported_at', 'last_change_source'] as $field) {
        $beforeValue = $before[$field] ?? null;
        $afterValue = $after[$field] ?? null;
        if ($beforeValue !== $afterValue) {
            $changes[$field] = ['before' => $beforeValue, 'after' => $afterValue];
        }
    }

    return $changes;
}

function save_account_version(int $accountId, string $action, string $sourceMode, ?string $sourceUpdatedAt, ?array $before, ?array $after): void
{
    $stmt = db()->prepare('INSERT INTO account_versions (account_id, user_id, action, source_mode, source_updated_at, before_json, after_json, changes_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $accountId,
        $_SESSION['user_id'] ?? null,
        $action,
        $sourceMode,
        $sourceUpdatedAt,
        $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        json_encode(account_change_diff($before ?? [], $after ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function fetch_account(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM accounts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $account = $stmt->fetch();

    return $account ?: null;
}

function account_import_match(array $input): array
{
    $id = (int)($input['id'] ?? 0);
    $sourceKey = trim((string)($input['source_key'] ?? $input['external_id'] ?? $input['sync_key'] ?? ''));
    $name = trim((string)($input['name'] ?? ''));

    if ($id > 0) {
        $account = fetch_account($id);
        if ($account) {
            return ['account' => $account, 'match' => 'id'];
        }
    }

    if ($sourceKey !== '') {
        $stmt = db()->prepare('SELECT * FROM accounts WHERE source_key = ? LIMIT 1');
        $stmt->execute([$sourceKey]);
        $account = $stmt->fetch();
        if ($account) {
            return ['account' => $account, 'match' => 'source_key'];
        }
    }

    if ($name !== '') {
        $stmt = db()->prepare('SELECT * FROM accounts WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $account = $stmt->fetch();
        if ($account) {
            return ['account' => $account, 'match' => 'name'];
        }
    }

    return ['account' => null, 'match' => 'new'];
}

function account_import_cutoff(array $account): ?int
{
    $timestamps = [];
    foreach (['updated_at', 'last_manual_edit_at', 'last_imported_at', 'source_updated_at'] as $field) {
        if (!empty($account[$field])) {
            $timestamps[] = strtotime((string)$account[$field]);
        }
    }

    $timestamps = array_filter($timestamps, static fn ($value) => $value !== false && $value !== null);

    return $timestamps ? max($timestamps) : null;
}

function record_account_import_conflict(?int $accountId, ?string $sourceKey, ?string $sourceUpdatedAt, array $payload, array $current, string $reason): int
{
    $stmt = db()->prepare('INSERT INTO account_import_conflicts (account_id, import_key, source_updated_at, payload_json, current_json, conflict_reason, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $accountId,
        $sourceKey ?: null,
        $sourceUpdatedAt,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $reason,
    ]);

    return (int)db()->lastInsertId();
}

function import_account_row(array $input, bool $force = false, ?array &$result = null): array
{
    $name = trim((string)($input['name'] ?? ''));
    $type = trim((string)($input['type'] ?? 'corrente'));
    $openingBalance = money_to_float($input['opening_balance'] ?? $input['balance'] ?? 0);
    $sourceKey = trim((string)($input['source_key'] ?? $input['external_id'] ?? $input['sync_key'] ?? $name));
    $sourceUpdatedAt = normalize_datetime($input['source_updated_at'] ?? $input['sheet_updated_at'] ?? $input['imported_at'] ?? null);

    if ($name === '') {
        return ['status' => 'skipped', 'message' => 'Nome obrigatorio.'];
    }

    $match = account_import_match($input);
    $account = $match['account'];

    if ($account) {
        $current = account_snapshot($account);
        $cutoff = account_import_cutoff($account);
        $incomingTime = $sourceUpdatedAt ? strtotime($sourceUpdatedAt) : false;

        if (!$force && ($incomingTime === false || ($cutoff !== null && $incomingTime <= $cutoff))) {
            $reason = $incomingTime === false
                ? 'Importacao sem timestamp nao pode sobrescrever um registro ja editado.'
                : 'Importacao mais antiga que a alteracao local mais recente.';
            $conflictId = record_account_import_conflict((int)$account['id'], $sourceKey ?: null, $sourceUpdatedAt, $input, $current, $reason);
            save_account_version((int)$account['id'], 'import_conflict', 'sheet', $sourceUpdatedAt, $current, $current);
            audit('import_conflict', 'account', (int)$account['id'], [
                'conflict_id' => $conflictId,
                'reason' => $reason,
                'source_key' => $sourceKey,
            ]);

            if (is_array($result)) {
                $result['conflicts']++;
            }

            return ['status' => 'conflict', 'conflict_id' => $conflictId, 'account_id' => (int)$account['id']];
        }

        $stmt = db()->prepare('UPDATE accounts
            SET name = ?, type = ?, opening_balance = ?, source_key = ?, source_updated_at = ?, last_imported_at = NOW(), last_change_source = \'sheet\', updated_at = NOW()
            WHERE id = ?');
        $stmt->execute([$name, $type, $openingBalance, $sourceKey ?: null, $sourceUpdatedAt, (int)$account['id']]);

        $afterRow = fetch_account((int)$account['id']) ?: [];
        $after = account_snapshot($afterRow);
        save_account_version((int)$account['id'], $force ? 'import_resolved' : 'import_update', 'sheet', $sourceUpdatedAt, $current, $after);
        audit($force ? 'import_resolved' : 'import_update', 'account', (int)$account['id'], account_change_diff($current, $after));

        if (is_array($result)) {
            $result['imported']++;
        }

        return ['status' => 'imported', 'account_id' => (int)$account['id']];
    }

    $stmt = db()->prepare('INSERT INTO accounts (name, type, opening_balance, source_key, source_updated_at, last_imported_at, last_change_source, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), \'sheet\', NOW())');
    $stmt->execute([$name, $type, $openingBalance, $sourceKey ?: null, $sourceUpdatedAt]);
    $accountId = (int)db()->lastInsertId();
    $after = account_snapshot(fetch_account($accountId) ?: []);
    save_account_version($accountId, 'import_create', 'sheet', $sourceUpdatedAt, null, $after);
    audit('import_create', 'account', $accountId, $after);

    if (is_array($result)) {
        $result['imported']++;
    }

    return ['status' => 'imported', 'account_id' => $accountId];
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

function overview(): never
{
    json_response(['ok' => true, 'overview' => build_overview()]);
}

function build_overview(): array
{
    $month = $_GET['month'] ?? date('Y-m');
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    $stmt = db()->prepare("SELECT
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) income,
        SUM(CASE WHEN type = 'expense' AND status <> 'ignored' THEN amount ELSE 0 END) expenses,
        SUM(CASE WHEN type = 'expense' AND status = 'paid' THEN amount ELSE 0 END) paid,
        SUM(CASE WHEN type = 'expense' AND status IN ('pending','late') THEN amount ELSE 0 END) pending
        FROM transactions WHERE due_date BETWEEN ? AND ?");
    $stmt->execute([$monthStart, $monthEnd]);
    $totals = $stmt->fetch() ?: [];

    $byCategory = db()->prepare("SELECT COALESCE(c.name, 'Sem categoria') name, COALESCE(c.color, '#64748b') color, SUM(t.amount) total
        FROM transactions t LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.type = 'expense' AND t.status <> 'ignored' AND t.due_date BETWEEN ? AND ?
        GROUP BY name, color ORDER BY total DESC");
    $byCategory->execute([$monthStart, $monthEnd]);

    $monthly = db()->query("SELECT DATE_FORMAT(due_date, '%Y-%m') month,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) income,
        SUM(CASE WHEN type = 'expense' AND status <> 'ignored' THEN amount ELSE 0 END) expenses,
        SUM(CASE WHEN type = 'expense' AND status IN ('pending','late') THEN amount ELSE 0 END) pending
        FROM transactions WHERE due_date IS NOT NULL GROUP BY month ORDER BY month")->fetchAll();

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
