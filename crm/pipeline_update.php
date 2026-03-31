<?php
require 'config.php';

$id = $_POST['id'];
$nome = $_POST['nome'];
$ordem = $_POST['ordem'];
$cor = $_POST['cor'];

$stmt = $conn->prepare("UPDATE pipelines SET nome=?, ordem=?, cor=? WHERE id=?");
$stmt->bind_param("sisi", $nome, $ordem, $cor, $id);
$stmt->execute();

header("Location: configuracoes.php");