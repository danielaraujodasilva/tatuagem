<?php include("../../config/conexao.php"); ?>
<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


$sql = "
SELECT 
    id,
    descricao AS title,
    CONCAT(data_tatuagem,'T',hora_inicio) AS start,
    CONCAT(data_tatuagem,'T',hora_fim) AS end,
    status
FROM tatuagens
";

$res = $conn->query($sql);

$cores = [
    'agendado'=>'#3788d8',
    'confirmado'=>'#28a745',
    'cancelado'=>'#dc3545',
    'concluido'=>'#6c757d'
];

$eventos = [];

while($r = $res->fetch_assoc()){
    $r['color'] = $cores[$r['status']] ?? '#3788d8';
    $eventos[] = $r;
}

header('Content-Type: application/json');
echo json_encode($eventos);
