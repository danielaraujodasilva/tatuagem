<?php
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$descricao = trim((string) ($data['descricao'] ?? ''));
$status = trim((string) ($data['status'] ?? 'agendado'));
$dataTat = trim((string) ($data['data_tatuagem'] ?? ''));
$horaInicio = trim((string) ($data['hora_inicio'] ?? ''));
$horaFim = trim((string) ($data['hora_fim'] ?? ''));
$valor = (float) ($data['valor'] ?? 0);

if ($descricao === '' || $dataTat === '' || $horaInicio === '' || $horaFim === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Preencha descricao, data e horario para salvar.']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO tatuagens (cliente_id, descricao, valor, data_tatuagem, hora_inicio, hora_fim, status) VALUES (NULL, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sdssss', $descricao, $valor, $dataTat, $horaInicio, $horaFim, $status);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Agendamento criado com sucesso.']);
