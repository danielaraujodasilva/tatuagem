<?php
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Agendamento invalido.']);
    exit;
}

$stmt = $conn->prepare(
    'SELECT
        t.id,
        t.cliente_id,
        t.descricao,
        t.valor,
        t.data_tatuagem,
        t.hora_inicio,
        t.hora_fim,
        t.status,
        c.nome AS cliente_nome
    FROM tatuagens t
    LEFT JOIN clientes c ON c.id = t.cliente_id
    WHERE t.id = ?
    LIMIT 1'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$evento) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Agendamento nao encontrado.']);
    exit;
}

echo json_encode($evento);
