<?php
require __DIR__ . '/../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$telefone = isset($_GET['telefone']) ? trim((string) $_GET['telefone']) : '';

if ($telefone === '') {
    echo json_encode(['encontrado' => false]);
    exit;
}

$stmt = $conn->prepare('SELECT id, nome FROM clientes WHERE telefone = ? LIMIT 1');
$stmt->bind_param('s', $telefone);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result) {
    echo json_encode(['encontrado' => true, 'id' => (int) $result['id'], 'nome' => $result['nome']]);
    exit;
}

echo json_encode(['encontrado' => false]);
