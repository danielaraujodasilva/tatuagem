<?php
require_once __DIR__ . '/../../../auth/auth.php';
require_staff();
require __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../../includes/system_settings.php';
require_once __DIR__ . '/../../../includes/team_settings.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
team_ensure_tatuagens_team_schema($conn);

$descricao = trim((string) ($data['descricao'] ?? ''));
$status = trim((string) ($data['status'] ?? 'agendado'));
$dataTat = trim((string) ($data['data_tatuagem'] ?? ''));
$horaInicio = trim((string) ($data['hora_inicio'] ?? ''));
$horaFim = trim((string) ($data['hora_fim'] ?? ''));
$valor = (float) ($data['valor'] ?? 0);
$clienteId = isset($data['cliente_id']) && (int)$data['cliente_id'] > 0 ? (int) $data['cliente_id'] : null;
$observacoes = trim((string) ($data['observacoes'] ?? ''));
$pomadas = max(0, (int) ($data['pomadas_anestesicas'] ?? 0));
$valor = system_apply_pomada_total($valor, $pomadas);
$referencia = trim((string) ($data['referencia_arte'] ?? ''));
$artist = team_resolve_tattoo_artist((string)($data['tatuador_id'] ?? ''), (string)($data['tatuador_nome'] ?? ''));
$artistId = (string)($artist['id'] ?? '');
$artistName = (string)($artist['nome'] ?? '');

if ($descricao === '' || $dataTat === '' || $horaInicio === '' || $horaFim === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Preencha descricao, data e horario para salvar.']);
    exit;
}

$conflict = team_validate_tattoo_schedule($conn, null, $artistId, $dataTat, $horaInicio, $horaFim);
if ($conflict !== null) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => $conflict]);
    exit;
}

$stmt = $conn->prepare('INSERT INTO tatuagens (cliente_id, tatuador_id, tatuador_nome, descricao, valor, data_tatuagem, hora_inicio, hora_fim, status, observacoes, pomadas_anestesicas, referencia_arte) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('isssdsssssis', $clienteId, $artistId, $artistName, $descricao, $valor, $dataTat, $horaInicio, $horaFim, $status, $observacoes, $pomadas, $referencia);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Agendamento criado com sucesso para ' . $artistName . '.']);
