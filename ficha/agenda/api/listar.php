<?php
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$sql = '
    SELECT
        t.id,
        t.descricao AS title,
        CONCAT(t.data_tatuagem, "T", t.hora_inicio) AS start,
        CONCAT(t.data_tatuagem, "T", t.hora_fim) AS end,
        t.status,
        t.valor,
        c.nome AS cliente_nome
    FROM tatuagens t
    LEFT JOIN clientes c ON c.id = t.cliente_id
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
    $row['color'] = $cores[$row['status']] ?? '#38bdf8';
    $row['extendedProps'] = [
        'status' => $row['status'],
        'valor' => (float) $row['valor'],
        'cliente_nome' => $row['cliente_nome']
    ];
    unset($row['status'], $row['valor'], $row['cliente_nome']);
    $eventos[] = $row;
}

echo json_encode($eventos);
