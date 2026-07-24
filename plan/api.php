<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'login') {
        verify_csrf();
        ensure_default_users();
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
        'update_transaction_category' => update_transaction_category(),
        'save_sheet_import' => save_sheet_import(),
        'save_category' => save_category(),
        'delete_category' => delete_category(),
        'save_budget' => save_budget(),
        'delete_budget' => delete_budget(),
        'save_goal' => save_goal(),
        'delete_goal' => delete_goal(),
        'save_account' => save_account(),
        'delete_account' => delete_account(),
        'save_recurring' => save_recurring(),
        'delete_recurring' => delete_recurring(),
        'bank_transactions' => bank_transactions(),
        'save_bank_import' => save_bank_import(),
        'update_bank_transaction_category' => update_bank_transaction_category(),
        'create_share' => create_share(),
        'resolve_share' => resolve_share(),
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
    ensure_share_schema();
    ensure_default_users();
    json_response([
        'ok' => true,
        'user' => $user,
        'csrf' => csrf_token(),
        'categories' => db()->query('SELECT c.*, p.name parent_name FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY c.parent_id IS NOT NULL, p.name, c.name')->fetchAll(),
        'accounts' => db()->query('SELECT * FROM accounts ORDER BY name')->fetchAll(),
        'budgets' => db()->query("SELECT b.*, COALESCE(NULLIF(CONCAT_WS(' / ', p.name, c.name), ''), 'Sem categoria') category_name, c.color, c.parent_id, p.name parent_name
            FROM budgets b JOIN categories c ON c.id = b.category_id LEFT JOIN categories p ON p.id = c.parent_id ORDER BY b.month DESC, category_name")->fetchAll(),
        'goals' => db()->query('SELECT * FROM goals ORDER BY target_date IS NULL, target_date, name')->fetchAll(),
        'recurring' => db()->query("SELECT r.*, COALESCE(NULLIF(CONCAT_WS(' / ', p.name, c.name), ''), 'Sem categoria') category_name, c.parent_id, p.name parent_name
            FROM recurring_rules r LEFT JOIN categories c ON c.id = r.category_id LEFT JOIN categories p ON p.id = c.parent_id ORDER BY next_due_date")->fetchAll(),
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
        $sheetPatterns = sheet_month_patterns($_GET['month']);
        $sourceFallback = $sheetPatterns ? ' OR (t.reference_month IS NULL AND t.source_sheet IS NOT NULL AND (' . implode(' OR ', array_fill(0, count($sheetPatterns), 't.source_sheet LIKE ?')) . '))' : '';
        $where[] = "(t.reference_month = ? OR (t.reference_month IS NULL AND DATE_FORMAT(t.due_date, '%Y-%m') = ?)$sourceFallback)";
        array_push($params, $_GET['month'], $_GET['month'], ...$sheetPatterns);
    }

    if (!empty($_GET['q'])) {
        $where[] = '(t.description LIKE ? OR t.payment_code LIKE ? OR t.notes LIKE ?)';
        $term = '%' . $_GET['q'] . '%';
        array_push($params, $term, $term, $term);
    }

    $sql = "SELECT t.*, COALESCE(NULLIF(CONCAT_WS(' / ', pc.name, c.name), ''), 'Sem categoria') category_name, c.name category_leaf_name, c.parent_id category_parent_id, pc.name category_parent_name, c.color category_color, a.name account_name
            FROM transactions t
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN categories pc ON pc.id = c.parent_id
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

function update_transaction_category(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $categoryId = normalize_category_id($input['category_id'] ?? '');
    $applySimilar = !empty($input['apply_similar']);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Lancamento invalido.'], 422);
    }
    assert_category_exists($categoryId);

    $stmt = db()->prepare('SELECT id, description FROM transactions WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        json_response(['ok' => false, 'message' => 'Lancamento nao encontrado.'], 404);
    }

    $ids = [$id];
    if ($applySimilar) {
        $key = category_match_key((string)$target['description']);
        $rows = db()->query('SELECT id, description FROM transactions')->fetchAll();
        $ids = array_values(array_map(
            fn(array $row) => (int)$row['id'],
            array_filter($rows, fn(array $row) => category_match_key((string)$row['description']) === $key)
        ));
    }

    update_category_for_ids('transactions', $ids, $categoryId);
    audit('update_category', 'transaction', $id, ['category_id' => $categoryId, 'affected' => count($ids)]);
    json_response(['ok' => true, 'affected' => count($ids)]);
}

function update_bank_transaction_category(): never
{
    ensure_bank_schema();
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $categoryId = normalize_category_id($input['category_id'] ?? '');
    $applySimilar = !empty($input['apply_similar']);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Movimentacao invalida.'], 422);
    }
    assert_category_exists($categoryId);

    $stmt = db()->prepare('SELECT id, description FROM bank_transactions WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        json_response(['ok' => false, 'message' => 'Movimentacao nao encontrada.'], 404);
    }

    $ids = [$id];
    if ($applySimilar) {
        $key = category_match_key((string)$target['description']);
        $rows = db()->query('SELECT id, description FROM bank_transactions')->fetchAll();
        $ids = array_values(array_map(
            fn(array $row) => (int)$row['id'],
            array_filter($rows, fn(array $row) => category_match_key((string)$row['description']) === $key)
        ));
    }

    update_category_for_ids('bank_transactions', $ids, $categoryId);
    audit('update_category', 'bank_transaction', $id, ['category_id' => $categoryId, 'affected' => count($ids)]);
    json_response(['ok' => true, 'affected' => count($ids)]);
}

function normalize_category_id(mixed $value): ?int
{
    if ($value === '' || $value === null) {
        return null;
    }
    $id = (int)$value;
    return $id > 0 ? $id : null;
}

function assert_category_exists(?int $categoryId): void
{
    if ($categoryId === null) {
        return;
    }
    $stmt = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$categoryId]);
    if (!$stmt->fetchColumn()) {
        json_response(['ok' => false, 'message' => 'Categoria nao encontrada.'], 422);
    }
}

function update_category_for_ids(string $table, array $ids, ?int $categoryId): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0)));
    if (!$ids) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$categoryId], $ids);
    db()->prepare("UPDATE $table SET category_id = ? WHERE id IN ($placeholders)")->execute($params);
}

function category_match_key(string $description): string
{
    $key = strtolower(trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $description) ?: $description));
    $key = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/', ' ', $key) ?? $key;
    $key = preg_replace('/\b\d{4,}\b/', ' ', $key) ?? $key;
    $key = preg_replace('/[^a-z0-9]+/', ' ', $key) ?? $key;
    return trim(preg_replace('/\s+/', ' ', $key) ?? $key);
}

function save_category(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $wasUpdate = $id > 0;
    $name = trim((string)($input['name'] ?? ''));
    $color = trim((string)($input['color'] ?? '#2563eb'));
    $parentId = normalize_category_id($input['parent_id'] ?? '');
    if ($name === '') {
        json_response(['ok' => false, 'message' => 'Nome obrigatorio.'], 422);
    }
    assert_category_parent($id, $parentId);
    $duplicate = db()->prepare('SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1');
    $duplicate->execute([$name, $id]);
    if ($duplicate->fetchColumn()) {
        json_response(['ok' => false, 'message' => 'Ja existe uma categoria com este nome.'], 409);
    }
    if ($id > 0) {
        db()->prepare('UPDATE categories SET name = ?, color = ?, parent_id = ? WHERE id = ?')->execute([$name, $color, $parentId, $id]);
    } else {
        db()->prepare('INSERT INTO categories (name, color, parent_id) VALUES (?, ?, ?)')->execute([$name, $color, $parentId]);
        $id = (int)db()->lastInsertId();
    }
    audit($wasUpdate ? 'update' : 'create', 'category', $id, ['name' => $name, 'color' => $color, 'parent_id' => $parentId]);
    json_response(['ok' => true, 'id' => $id]);
}

function assert_category_parent(int $categoryId, ?int $parentId): void
{
    if ($parentId === null) {
        return;
    }
    if ($parentId === $categoryId) {
        json_response(['ok' => false, 'message' => 'Uma categoria nao pode ser pai dela mesma.'], 422);
    }

    $stmt = db()->prepare('SELECT parent_id FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$parentId]);
    $parent = $stmt->fetch();
    if (!$parent) {
        json_response(['ok' => false, 'message' => 'Categoria principal nao encontrada.'], 422);
    }
    if ($parent['parent_id'] !== null) {
        json_response(['ok' => false, 'message' => 'Escolha uma categoria principal, nao outra subcategoria.'], 422);
    }
}

function delete_category(): never
{
    $id = (int)(json_input()['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Categoria invalida.'], 422);
    }

    $stmt = db()->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $name = (string)($stmt->fetchColumn() ?: '');
    if ($name === '') {
        json_response(['ok' => false, 'message' => 'Categoria nao encontrada.'], 404);
    }
    if (strtolower($name) === 'sem categoria') {
        json_response(['ok' => false, 'message' => 'A categoria padrao nao pode ser excluida.'], 422);
    }

    $children = db()->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
    $children->execute([$id]);
    if ((int)$children->fetchColumn() > 0) {
        json_response(['ok' => false, 'message' => 'Mova ou exclua as subcategorias antes de excluir esta categoria principal.'], 422);
    }

    db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    audit('delete', 'category', $id, ['name' => $name]);
    json_response(['ok' => true]);
}

function save_budget(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $categoryId = (int)($input['category_id'] ?? 0);
    $month = trim((string)($input['month'] ?? ''));
    $limit = money_to_float($input['limit_amount'] ?? 0);

    if ($categoryId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        json_response(['ok' => false, 'message' => 'Informe mes e categoria validos.'], 422);
    }

    if ($id > 0) {
        $duplicate = db()->prepare('SELECT id FROM budgets WHERE category_id = ? AND month = ? AND id <> ? LIMIT 1');
        $duplicate->execute([$categoryId, $month, $id]);
        if ($duplicate->fetchColumn()) {
            json_response(['ok' => false, 'message' => 'Ja existe orcamento para esta categoria neste mes.'], 409);
        }
        db()->prepare('UPDATE budgets SET category_id = ?, month = ?, limit_amount = ? WHERE id = ?')
            ->execute([$categoryId, $month, $limit, $id]);
    } else {
        db()->prepare('INSERT INTO budgets (category_id, month, limit_amount)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE limit_amount = VALUES(limit_amount)')
            ->execute([$categoryId, $month, $limit]);
        $stmt = db()->prepare('SELECT id FROM budgets WHERE category_id = ? AND month = ? LIMIT 1');
        $stmt->execute([$categoryId, $month]);
        $id = (int)$stmt->fetchColumn();
    }

    audit('save', 'budget', $id, ['category_id' => $categoryId, 'month' => $month, 'limit_amount' => $limit]);
    json_response(['ok' => true, 'id' => $id]);
}

function delete_budget(): never
{
    $id = (int)(json_input()['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Orcamento invalido.'], 422);
    }

    db()->prepare('DELETE FROM budgets WHERE id = ?')->execute([$id]);
    audit('delete', 'budget', $id);
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
        audit('update', 'goal', $id, [
            'name' => $data[0],
            'target_amount' => $data[1],
            'current_amount' => $data[2],
            'target_date' => $data[3],
        ]);
    } else {
        db()->prepare('INSERT INTO goals (name, target_amount, current_amount, target_date) VALUES (?, ?, ?, ?)')->execute($data);
        $id = (int)db()->lastInsertId();
        audit('create', 'goal', $id, [
            'name' => $data[0],
            'target_amount' => $data[1],
            'current_amount' => $data[2],
            'target_date' => $data[3],
        ]);
    }
    json_response(['ok' => true, 'id' => $id]);
}

function delete_goal(): never
{
    $id = (int)(json_input()['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Meta invalida.'], 422);
    }

    $stmt = db()->prepare('SELECT * FROM goals WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $goal = $stmt->fetch();
    if (!$goal) {
        json_response(['ok' => false, 'message' => 'Meta nao encontrada.'], 404);
    }

    db()->prepare('DELETE FROM goals WHERE id = ?')->execute([$id]);
    audit('delete', 'goal', $id, $goal);
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
        $id = (int)db()->lastInsertId();
    }
    json_response(['ok' => true, 'id' => $id]);
}

function delete_account(): never
{
    $id = (int)(json_input()['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Conta invalida.'], 422);
    }

    $stmt = db()->prepare('SELECT * FROM accounts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $account = $stmt->fetch();
    if (!$account) {
        json_response(['ok' => false, 'message' => 'Conta nao encontrada.'], 404);
    }

    db()->prepare('DELETE FROM accounts WHERE id = ?')->execute([$id]);
    audit('delete', 'account', $id, $account);
    json_response(['ok' => true]);
}

function save_recurring(): never
{
    $input = json_input();
    $id = (int)($input['id'] ?? 0);
    $frequency = trim((string)($input['frequency'] ?? 'monthly'));
    $data = [
        trim((string)($input['description'] ?? '')),
        money_to_float($input['amount'] ?? 0),
        ($input['category_id'] ?? '') === '' ? null : (int)$input['category_id'],
        in_array($frequency, ['monthly', 'weekly', 'yearly'], true) ? $frequency : 'monthly',
        normalize_date($input['next_due_date'] ?? null),
        !empty($input['is_active']) ? 1 : 0,
    ];
    if ($data[0] === '') {
        json_response(['ok' => false, 'message' => 'Descricao obrigatoria.'], 422);
    }
    if ($id > 0) {
        $data[] = $id;
        db()->prepare('UPDATE recurring_rules SET description=?, amount=?, category_id=?, frequency=?, next_due_date=?, is_active=? WHERE id=?')->execute($data);
        audit('update', 'recurring_rule', $id, [
            'description' => $data[0],
            'amount' => $data[1],
            'category_id' => $data[2],
            'frequency' => $data[3],
            'next_due_date' => $data[4],
            'is_active' => $data[5],
        ]);
    } else {
        db()->prepare('INSERT INTO recurring_rules (description, amount, category_id, frequency, next_due_date, is_active) VALUES (?, ?, ?, ?, ?, ?)')->execute($data);
        $id = (int)db()->lastInsertId();
        audit('create', 'recurring_rule', $id, [
            'description' => $data[0],
            'amount' => $data[1],
            'category_id' => $data[2],
            'frequency' => $data[3],
            'next_due_date' => $data[4],
            'is_active' => $data[5],
        ]);
    }
    json_response(['ok' => true, 'id' => $id]);
}

function delete_recurring(): never
{
    $id = (int)(json_input()['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Regra recorrente invalida.'], 422);
    }

    $stmt = db()->prepare('SELECT * FROM recurring_rules WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $rule = $stmt->fetch();
    if (!$rule) {
        json_response(['ok' => false, 'message' => 'Regra recorrente nao encontrada.'], 404);
    }

    db()->prepare('DELETE FROM recurring_rules WHERE id = ?')->execute([$id]);
    audit('delete', 'recurring_rule', $id, $rule);
    json_response(['ok' => true]);
}

function sheet_month_patterns(string $month): array
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $match)) {
        return [];
    }
    $names = [
        '01' => 'Janeiro',
        '02' => 'Fevereiro',
        '03' => 'Marco',
        '04' => 'Abril',
        '05' => 'Maio',
        '06' => 'Junho',
        '07' => 'Julho',
        '08' => 'Agosto',
        '09' => 'Setembro',
        '10' => 'Outubro',
        '11' => 'Novembro',
        '12' => 'Dezembro',
    ];
    $year = $match[1];
    $name = $names[$match[2]] ?? '';
    return $name === '' ? [] : ["%$name%$year%", "%$name-$year%", "%$name $year%"];
}

function ensure_default_users(): void
{
    $stmt = db()->prepare("INSERT INTO users (name, email, password_hash, is_active)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), is_active = VALUES(is_active)");
    $stmt->execute([
        'Francielen',
        'francielen.admw@gmail.com',
        '$2y$10$JSGPLsvrC/9v0nM5aJa14e0KT0pchXipO5iBkhJxj51lyLu6F9LvG',
    ]);
}

function ensure_share_schema(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS share_links (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token CHAR(32) NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(220) NOT NULL,
        note TEXT NULL,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_viewed_at DATETIME NULL,
        UNIQUE KEY uniq_share_links_token (token),
        INDEX idx_share_links_target (entity_type, entity_id),
        INDEX idx_share_links_created_by (created_by),
        CONSTRAINT fk_share_links_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function create_share(): never
{
    ensure_share_schema();
    $input = json_input();
    $entityType = trim((string)($input['entity_type'] ?? ''));
    $entityId = (int)($input['entity_id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));

    if (!in_array($entityType, ['transaction', 'bank_transaction'], true) || $entityId <= 0) {
        json_response(['ok' => false, 'message' => 'Item invalido para compartilhar.'], 422);
    }

    $target = share_target_row($entityType, $entityId);
    if (!$target) {
        json_response(['ok' => false, 'message' => 'Nao encontrei este item para compartilhar.'], 404);
    }

    $token = bin2hex(random_bytes(16));
    $title = share_target_title($entityType, $target);
    $stmt = db()->prepare('INSERT INTO share_links (token, entity_type, entity_id, title, note, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $token,
        $entityType,
        $entityId,
        $title,
        $note === '' ? null : substr($note, 0, 1200),
        $_SESSION['user_id'] ?? null,
    ]);

    audit('create_share', 'share_link', (int)db()->lastInsertId(), ['target' => $entityType, 'target_id' => $entityId]);
    json_response(['ok' => true, 'token' => $token, 'url' => plan_share_url($token), 'title' => $title]);
}

function resolve_share(): never
{
    ensure_share_schema();
    $token = trim((string)($_GET['token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        json_response(['ok' => false, 'message' => 'Link de compartilhamento invalido.'], 422);
    }

    $stmt = db()->prepare("SELECT s.*, u.name created_by_name
        FROM share_links s
        LEFT JOIN users u ON u.id = s.created_by
        WHERE s.token = ?
        LIMIT 1");
    $stmt->execute([$token]);
    $share = $stmt->fetch();
    if (!$share) {
        json_response(['ok' => false, 'message' => 'Link de compartilhamento nao encontrado.'], 404);
    }

    $target = share_target_row((string)$share['entity_type'], (int)$share['entity_id']);
    if (!$target) {
        json_response(['ok' => false, 'message' => 'O item compartilhado nao existe mais.'], 404);
    }

    db()->prepare('UPDATE share_links SET last_viewed_at = NOW() WHERE id = ?')->execute([(int)$share['id']]);
    json_response(['ok' => true, 'share' => $share, 'target' => $target]);
}

function share_target_row(string $entityType, int $entityId): ?array
{
    if ($entityType === 'transaction') {
        $stmt = db()->prepare("SELECT t.*, COALESCE(NULLIF(CONCAT_WS(' / ', pc.name, c.name), ''), 'Sem categoria') category_name, c.name category_leaf_name, c.parent_id category_parent_id, pc.name category_parent_name, c.color category_color, a.name account_name
            FROM transactions t
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN categories pc ON pc.id = c.parent_id
            LEFT JOIN accounts a ON a.id = t.account_id
            WHERE t.id = ?
            LIMIT 1");
        $stmt->execute([$entityId]);
        return $stmt->fetch() ?: null;
    }

    if ($entityType === 'bank_transaction') {
        ensure_bank_schema();
        $stmt = db()->prepare("SELECT bt.*, a.name account_name, COALESCE(NULLIF(CONCAT_WS(' / ', pc.name, c.name), ''), 'Sem categoria') category_name, c.name category_leaf_name, c.parent_id category_parent_id, pc.name category_parent_name, t.description matched_description
            FROM bank_transactions bt
            LEFT JOIN accounts a ON a.id = bt.account_id
            LEFT JOIN categories c ON c.id = bt.category_id
            LEFT JOIN categories pc ON pc.id = c.parent_id
            LEFT JOIN transactions t ON t.id = bt.matched_transaction_id
            WHERE bt.id = ?
            LIMIT 1");
        $stmt->execute([$entityId]);
        return $stmt->fetch() ?: null;
    }

    return null;
}

function share_target_title(string $entityType, array $target): string
{
    if ($entityType === 'bank_transaction') {
        return trim(sprintf(
            '%s em %s - %s',
            $target['description'] ?? 'Movimentacao bancaria',
            format_share_date($target['transaction_date'] ?? null),
            money_label((float)($target['amount'] ?? 0))
        ));
    }

    return trim(sprintf(
        '%s - %s (%s)',
        $target['description'] ?? 'Lancamento',
        money_label((float)($target['amount'] ?? 0)),
        status_text((string)($target['status'] ?? 'pending'))
    ));
}

function plan_share_url(string $token): string
{
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/plan/api.php')), '/');
    return $scheme . '://' . $host . $dir . '/index.php?share=' . rawurlencode($token);
}

function money_label(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function status_text(string $status): string
{
    return [
        'paid' => 'pago',
        'pending' => 'pendente',
        'late' => 'atrasado',
        'ignored' => 'ignorado',
    ][$status] ?? $status;
}

function format_share_date(?string $date): string
{
    if (!$date) {
        return 'sem data';
    }
    $parsed = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    return $parsed ? $parsed->format('d/m/Y') : $date;
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

    $categoryColumn = db()->prepare("SELECT COUNT(*) total FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'parent_id'");
    $categoryColumn->execute();
    if ((int)($categoryColumn->fetch()['total'] ?? 0) === 0) {
        db()->exec("ALTER TABLE categories ADD COLUMN parent_id INT UNSIGNED NULL AFTER color");
        db()->exec("ALTER TABLE categories ADD INDEX idx_categories_parent (parent_id)");
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
    if (!empty($_GET['category_id'])) {
        $where[] = 'bt.category_id = ?';
        $params[] = (int)$_GET['category_id'];
    }
    if (!empty($_GET['direction']) && in_array($_GET['direction'], ['credit', 'debit'], true)) {
        $where[] = 'bt.direction = ?';
        $params[] = $_GET['direction'];
    }
    if (isset($_GET['matched']) && $_GET['matched'] !== '') {
        $where[] = $_GET['matched'] === 'yes' ? 'bt.matched_transaction_id IS NOT NULL' : 'bt.matched_transaction_id IS NULL';
    }
    if (!empty($_GET['date_from'])) {
        $where[] = 'bt.transaction_date >= ?';
        $params[] = normalize_date($_GET['date_from']);
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 'bt.transaction_date <= ?';
        $params[] = normalize_date($_GET['date_to']);
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

    $sql = "SELECT bt.*, a.name account_name, COALESCE(NULLIF(CONCAT_WS(' / ', pc.name, c.name), ''), 'Sem categoria') category_name, c.name category_leaf_name, c.parent_id category_parent_id, pc.name category_parent_name, t.description matched_description
        FROM bank_transactions bt
        LEFT JOIN accounts a ON a.id = bt.account_id
        LEFT JOIN categories c ON c.id = bt.category_id
        LEFT JOIN categories pc ON pc.id = c.parent_id
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

    $byCategory = db()->prepare("SELECT COALESCE(NULLIF(CONCAT_WS(' / ', pc.name, c.name), ''), 'Sem categoria') name, COALESCE(c.color, '#64748b') color, SUM(t.amount) total
        FROM transactions t LEFT JOIN categories c ON c.id = t.category_id LEFT JOIN categories pc ON pc.id = c.parent_id
        WHERE t.type = 'expense' AND t.status <> 'ignored' AND (t.reference_month = ? OR (t.reference_month IS NULL AND t.due_date BETWEEN ? AND ?))
        GROUP BY name, color ORDER BY total DESC");
    $byCategory->execute([$month, $monthStart, $monthEnd]);

    $monthly = db()->query("SELECT COALESCE(reference_month, DATE_FORMAT(due_date, '%Y-%m')) month,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) income,
        SUM(CASE WHEN type = 'expense' AND status <> 'ignored' THEN amount ELSE 0 END) expenses,
        SUM(CASE WHEN type = 'expense' AND status IN ('pending','late') THEN amount ELSE 0 END) pending
        FROM transactions WHERE reference_month IS NOT NULL OR due_date IS NOT NULL GROUP BY month ORDER BY month")->fetchAll();

    $upcoming = db()->prepare("SELECT t.*, COALESCE(NULLIF(CONCAT_WS(' / ', pc.name, c.name), ''), 'Sem categoria') category_name FROM transactions t LEFT JOIN categories c ON c.id = t.category_id LEFT JOIN categories pc ON pc.id = c.parent_id
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
