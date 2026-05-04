<?php
require_once __DIR__ . '/../../../auth/auth.php';
require_staff();
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Agendamento invalido.']);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT * FROM tatuagens WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$evento) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Agendamento nao encontrado.']);
        exit;
    }

    $cliente = [];
    $clienteId = (int)($evento['cliente_id'] ?? 0);
    if ($clienteId > 0) {
        try {
            $stmt = $conn->prepare('SELECT id, nome, telefone FROM clientes WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $clienteId);
            $stmt->execute();
            $cliente = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        } catch (Throwable $e) {
            $cliente = [];
        }
    }

    $evento['valor'] = $evento['valor'] ?? 0;
    $evento['observacoes'] = $evento['observacoes'] ?? '';
    $evento['pomadas_anestesicas'] = $evento['pomadas_anestesicas'] ?? 0;
    $evento['referencia_arte'] = $evento['referencia_arte'] ?? '';
    $evento['cliente_nome'] = $cliente['nome'] ?? '';
    $evento['cliente_telefone'] = $cliente['telefone'] ?? '';

    echo json_encode($evento, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao carregar detalhes do agendamento.',
        'debug' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
