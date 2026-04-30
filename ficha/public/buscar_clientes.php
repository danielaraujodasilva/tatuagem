<?php
require __DIR__ . '/../config/conexao.php';

$busca = isset($_GET['busca']) ? trim((string) $_GET['busca']) : '';

if ($busca === '') {
    exit;
}

$like = '%' . $busca . '%';
$stmt = $conn->prepare('SELECT id, nome, telefone, email FROM clientes WHERE nome LIKE ? OR telefone LIKE ? OR email LIKE ? ORDER BY nome ASC LIMIT 10');
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='autocomplete-suggestion'>Nenhum cliente encontrado</div>";
    exit;
}

while ($cliente = $result->fetch_assoc()) {
    echo "<div class='autocomplete-suggestion' data-id='" . (int) $cliente['id'] . "'>" .
        htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') . ' - ' .
        htmlspecialchars($cliente['telefone'], ENT_QUOTES, 'UTF-8') . ' - ' .
        htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8') .
        "</div>";
}

$stmt->close();
