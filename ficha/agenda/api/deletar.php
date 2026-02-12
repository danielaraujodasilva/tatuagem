<?php include("../../config/conexao.php"); ?>
<?php


$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'];

$stmt = $conn->prepare("DELETE FROM agendamentos WHERE id = ?");
$stmt->bind_param("i",$id);

$stmt->execute();
