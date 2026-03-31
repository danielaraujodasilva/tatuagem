<?php
require 'config.php';

$nome = $_POST['nome'] ?? '';
$ordem = $_POST['ordem'] ?? 0;
$cor = $_POST['cor'] ?? '#ffffff';

$stmt = $conn->prepare("INSERT INTO pipelines (nome, ordem, cor) VALUES (:nome, :ordem, :cor)");

$stmt->execute([
    ':nome' => $nome,
    ':ordem' => $ordem,
    ':cor' => $cor
]);

header("Location: configuracoes.php");
exit;