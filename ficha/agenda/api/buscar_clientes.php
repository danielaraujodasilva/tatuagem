<?php
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$busca = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if ($busca === '') {
    echo json_encode(['status' => 'success', 'clientes' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $busca . '%';

try {
    $stmt = $conn->prepare('
        SELECT id, nome, telefone, email
        FROM clientes
        WHERE nome LIKE ? OR telefone LIKE ? OR email LIKE ?
        ORDER BY nome ASC
        LIMIT 12
    ');
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $clientes = [];

    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }

    $stmt->close();

    echo json_encode(['status' => 'success', 'clientes' => $clientes], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar clientes.',
        'debug' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
