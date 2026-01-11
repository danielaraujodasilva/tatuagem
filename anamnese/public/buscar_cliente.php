<?php
include("../config/conexao.php");

$telefone = $_GET['telefone'];
$sql = "SELECT nome FROM clientes WHERE telefone = '$telefone'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  $cliente = $result->fetch_assoc();
  echo json_encode(["encontrado" => true, "nome" => $cliente['nome']]);
} else {
  echo json_encode(["encontrado" => false]);
}

$conn->close();
?>
