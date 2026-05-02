<?php
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$sql = '
    SELECT
        t.id,
        t.descricao AS title,
        CONCAT(t.data_tatuagem, "T", t.hora_inicio) AS start,
        CONCAT(t.data_tatuagem, "T", COALESCE(t.hora_fim, ADDTIME(t.hora_inicio, "01:00:00"))) AS end,
        t.status,
        t.valor,
        t.observacoes,
        t.pomadas_anestesicas,
        t.referencia_arte,
        c.nome AS cliente_nome,
        c.telefone AS cliente_telefone
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
    $row['color'] = $cores[$row['status']] ?? '#38bdf8';
    $row['extendedProps'] = [
        'status' => $row['status'],
        'valor' => (float) $row['valor'],
        'observacoes' => $row['observacoes'],
        'pomadas_anestesicas' => (int) $row['pomadas_anestesicas'],
        'referencia_arte' => $row['referencia_arte'],
        'cliente_nome' => $row['cliente_nome'],
        'cliente_telefone' => $row['cliente_telefone']
    ];
    unset($row['status'], $row['valor'], $row['observacoes'], $row['pomadas_anestesicas'], $row['referencia_arte'], $row['cliente_nome'], $row['cliente_telefone']);
    $eventos[] = $row;
}

echo json_encode($eventos);
