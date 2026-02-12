<?php include("../../config/conexao.php"); ?>
<?php


$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'];

$inicio = new DateTime($data['inicio']);
$fim = new DateTime($data['fim']);

$stmt = $conn->prepare("
UPDATE agendamentos 
SET 
    data_tatuagem = ?,
    hora_inicio = ?,
    hora_fim = ?
WHERE id = ?
");

$stmt->bind_param(
    "sssi",
    $inicio->format('Y-m-d'),
    $inicio->format('H:i:s'),
    $fim->format('H:i:s'),
    $id
);

$stmt->execute();
