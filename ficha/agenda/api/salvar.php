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
$clienteId = isset($data['cliente_id']) ? (int) $data['cliente_id'] : null;
$observacoes = trim((string) ($data['observacoes'] ?? ''));
$pomadas = max(0, (int) ($data['pomadas_anestesicas'] ?? 0));
$referencia = trim((string) ($data['referencia_arte'] ?? ''));

if ($descricao === '' || $dataTat === '' || $horaInicio === '' || $horaFim === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Preencha descricao, data e horario para salvar.']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO tatuagens (cliente_id, descricao, valor, data_tatuagem, hora_inicio, hora_fim, status, observacoes, pomadas_anestesicas, referencia_arte) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('isdsssssis', $clienteId, $descricao, $valor, $dataTat, $horaInicio, $horaFim, $status, $observacoes, $pomadas, $referencia);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Agendamento criado com sucesso.']);
