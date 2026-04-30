<?php
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$id = (int) ($data['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Agendamento invalido.']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM tatuagens WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Agendamento excluido com sucesso.']);
