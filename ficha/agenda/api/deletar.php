<?php include("../../config/conexao.php"); ?>
<?php

$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("DELETE FROM tatuagens WHERE id=?");
$stmt->bind_param("i", $data['id']);
$stmt->execute();
