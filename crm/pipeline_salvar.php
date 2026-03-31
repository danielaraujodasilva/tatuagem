<?php
require 'config.php';

$nome = $_POST['nome'];
$ordem = $_POST['ordem'] ?? 0;
$cor = $_POST['cor'] ?? '#6c757d';

$stmt = $conn->prepare("INSERT INTO pipelines (nome, ordem, cor) VALUES (?, ?, ?)");
$stmt->bind_param("sis", $nome, $ordem, $cor);
$stmt->execute();

header("Location: configuracoes.php");