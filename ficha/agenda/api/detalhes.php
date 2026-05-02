<?php
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Agendamento invalido.']);
    exit;
}

try {
    $clienteTemTelefone = (bool) $conn->query("SHOW COLUMNS FROM clientes LIKE 'telefone'")->fetch_assoc();
    $telefoneSelect = $clienteTemTelefone ? ', c.telefone AS cliente_telefone' : ', "" AS cliente_telefone';

    $stmt = $conn->prepare(
        'SELECT
            t.*,
            c.nome AS cliente_nome
            ' . $telefoneSelect . '
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

    $evento['valor'] = $evento['valor'] ?? 0;
    $evento['observacoes'] = $evento['observacoes'] ?? '';
    $evento['pomadas_anestesicas'] = $evento['pomadas_anestesicas'] ?? 0;
    $evento['referencia_arte'] = $evento['referencia_arte'] ?? '';
    $evento['cliente_nome'] = $evento['cliente_nome'] ?? '';
    $evento['cliente_telefone'] = $evento['cliente_telefone'] ?? '';

    echo json_encode($evento, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao carregar detalhes do agendamento.',
        'debug' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
