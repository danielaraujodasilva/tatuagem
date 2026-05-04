<?php
require_once __DIR__ . '/../../../auth/auth.php';
require_admin();
require __DIR__ . '/../../config/conexao.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    $database = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';
    $tableCheck = $conn->query("SHOW TABLES LIKE 'tatuagens'");
    $tabelaExiste = (bool) $tableCheck->fetch_assoc();

    $diagnostico = [
        'ok' => true,
        'database' => $database,
        'tabela_tatuagens_existe' => $tabelaExiste,
        'id_procurado' => $id,
        'total_tatuagens' => 0,
        'maior_id' => null,
        'registro_procurado' => null,
        'ultimos_agendamentos' => [],
    ];

    if (!$tabelaExiste) {
        echo json_encode($diagnostico, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $resumo = $conn->query('SELECT COUNT(*) AS total, MAX(id) AS maior_id FROM tatuagens')->fetch_assoc();
    $diagnostico['total_tatuagens'] = (int)($resumo['total'] ?? 0);
    $diagnostico['maior_id'] = $resumo['maior_id'] !== null ? (int)$resumo['maior_id'] : null;

    if ($id > 0) {
        $stmt = $conn->prepare('
            SELECT
                t.id,
                t.cliente_id,
                t.descricao,
                t.valor,
                t.data_tatuagem,
                t.hora_inicio,
                t.hora_fim,
                t.status,
                t.observacoes,
                t.pomadas_anestesicas,
                t.referencia_arte,
                t.created_at,
                c.nome AS cliente_nome,
                c.telefone AS cliente_telefone
            FROM tatuagens t
            LEFT JOIN clientes c ON c.id = t.cliente_id
            WHERE t.id = ?
            LIMIT 1
        ');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $diagnostico['registro_procurado'] = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }

    $result = $conn->query('
        SELECT
            t.id,
            t.cliente_id,
            t.descricao,
            t.valor,
            t.data_tatuagem,
            t.hora_inicio,
            t.hora_fim,
            t.status,
            t.created_at,
            c.nome AS cliente_nome,
            c.telefone AS cliente_telefone
        FROM tatuagens t
        LEFT JOIN clientes c ON c.id = t.cliente_id
        ORDER BY t.id DESC
        LIMIT 8
    ');

    while ($row = $result->fetch_assoc()) {
        $diagnostico['ultimos_agendamentos'][] = $row;
    }

    echo json_encode($diagnostico, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
