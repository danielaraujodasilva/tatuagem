<?php
ob_start();
require_once __DIR__ . '/../../../auth/auth.php';
require_staff();
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $result = $conn->query('
        SELECT *
        FROM tatuagens
        WHERE data_tatuagem IS NOT NULL
          AND hora_inicio IS NOT NULL
        ORDER BY data_tatuagem ASC, hora_inicio ASC
    ');

    $rows = [];
    $clienteIds = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $clienteId = (int)($row['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            $clienteIds[$clienteId] = $clienteId;
        }
    }

    $clientes = [];
    if ($clienteIds) {
        try {
            $ids = implode(',', array_map('intval', array_values($clienteIds)));
            $clienteResult = $conn->query("SELECT id, nome, telefone FROM clientes WHERE id IN ($ids)");
            while ($cliente = $clienteResult->fetch_assoc()) {
                $clientes[(int)$cliente['id']] = $cliente;
            }
        } catch (Throwable $e) {
            $clientes = [];
        }
    }

    $cores = [
        'agendado' => '#38bdf8',
        'confirmado' => '#22c55e',
        'cancelado' => '#fb7185',
        'concluido' => '#94a3b8'
    ];
    $eventos = [];

    foreach ($rows as $row) {
        $status = $row['status'] ?? 'agendado';
        $horaInicio = $row['hora_inicio'] ?: '00:00:00';
        $horaFim = $row['hora_fim'] ?: date('H:i:s', strtotime($horaInicio . ' +1 hour'));
        $cliente = $clientes[(int)($row['cliente_id'] ?? 0)] ?? [];
        $clienteNome = trim((string)($cliente['nome'] ?? ''));

        $eventos[] = [
            'id' => (string) $row['id'],
            'title' => $clienteNome !== '' ? $clienteNome : ($row['descricao'] ?: 'Tatuagem'),
            'start' => $row['data_tatuagem'] . 'T' . $horaInicio,
            'end' => $row['data_tatuagem'] . 'T' . $horaFim,
            'color' => $cores[$status] ?? '#38bdf8',
            'textColor' => '#06111f',
            'display' => 'block',
            'extendedProps' => [
                'status' => $status,
                'descricao' => $row['descricao'] ?? '',
                'valor' => (float)($row['valor'] ?? 0),
                'cliente_id' => (int)($row['cliente_id'] ?? 0),
                'observacoes' => $row['observacoes'] ?? '',
                'pomadas_anestesicas' => (int)($row['pomadas_anestesicas'] ?? 0),
                'referencia_arte' => $row['referencia_arte'] ?? '',
                'cliente_nome' => $cliente['nome'] ?? '',
                'cliente_telefone' => $cliente['telefone'] ?? ''
            ]
        ];
    }

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao listar agendamentos.',
        'debug' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
