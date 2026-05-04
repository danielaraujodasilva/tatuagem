<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require __DIR__ . '/../ficha/config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

function onlyDigitsCrmAgenda(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

$q = trim((string)($_GET['q'] ?? ''));
$digits = onlyDigitsCrmAgenda($q);

if ($q === '') {
    echo json_encode(['ok' => true, 'clientes' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';
$phoneLike = $digits !== '' ? '%' . $digits . '%' : '__sem_telefone__';

$sql = '
    SELECT id, nome, email, telefone, created_at
    FROM clientes
    WHERE nome LIKE ?
       OR email LIKE ?
       OR telefone LIKE ?
       OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, " ", ""), "-", ""), "(", ""), ")", "") LIKE ?
    ORDER BY created_at DESC, nome ASC
    LIMIT 12
';

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $like, $like, $like, $phoneLike);
$stmt->execute();
$result = $stmt->get_result();
$clientes = [];

while ($row = $result->fetch_assoc()) {
    $row['telefone_digits'] = onlyDigitsCrmAgenda((string)$row['telefone']);
    $clientes[] = $row;
}

$stmt->close();

echo json_encode(['ok' => true, 'clientes' => $clientes], JSON_UNESCAPED_UNICODE);
