<?php
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $clienteTemTelefone = (bool) $conn->query("SHOW COLUMNS FROM clientes LIKE 'telefone'")->fetch_assoc();
    $telefoneSelect = $clienteTemTelefone ? ', c.telefone AS cliente_telefone' : ', "" AS cliente_telefone';

    $sql = '
        SELECT
            t.*,
            c.nome AS cliente_nome
            ' . $telefoneSelect . '
        FROM tatuagens t
        LEFT JOIN clientes c ON c.id = t.cliente_id
        WHERE t.data_tatuagem IS NOT NULL
          AND t.hora_inicio IS NOT NULL
        ORDER BY t.data_tatuagem ASC, t.hora_inicio ASC
    ';

    $result = $conn->query($sql);
    $cores = [
        'agendado' => '#38bdf8',
        'confirmado' => '#22c55e',
        'cancelado' => '#fb7185',
        'concluido' => '#94a3b8'
    ];
    $eventos = [];

    while ($row = $result->fetch_assoc()) {
        $status = $row['status'] ?? 'agendado';
        $horaInicio = $row['hora_inicio'] ?: '00:00:00';
        $horaFim = $row['hora_fim'] ?: date('H:i:s', strtotime($horaInicio . ' +1 hour'));

        $eventos[] = [
            'id' => (string) $row['id'],
            'title' => $row['descricao'] ?: 'Tatuagem',
            'start' => $row['data_tatuagem'] . 'T' . $horaInicio,
            'end' => $row['data_tatuagem'] . 'T' . $horaFim,
            'color' => $cores[$status] ?? '#38bdf8',
            'extendedProps' => [
                'status' => $status,
                'valor' => (float)($row['valor'] ?? 0),
                'observacoes' => $row['observacoes'] ?? '',
                'pomadas_anestesicas' => (int)($row['pomadas_anestesicas'] ?? 0),
                'referencia_arte' => $row['referencia_arte'] ?? '',
                'cliente_nome' => $row['cliente_nome'] ?? '',
                'cliente_telefone' => $row['cliente_telefone'] ?? ''
            ]
        ];
    }

    echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao listar agendamentos.',
        'debug' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
