<?php include("../../config/conexao.php"); ?>
<?php


$sql = "
SELECT 
 id,
 descricao as title,
 CONCAT(data_tatuagem,' ',hora_inicio) as start,
 CONCAT(data_tatuagem,' ',hora_fim) as end,
 status
FROM agendamentos
";

$res = $conn->query($sql);

$eventos = [];

while($r = $res->fetch_assoc()){
    $cores = [
        'agendado'=>'#3788d8',
        'confirmado'=>'#28a745',
        'cancelado'=>'#dc3545',
        'concluido'=>'#6c757d'
    ];

    $r['color'] = $cores[$r['status']] ?? '#3788d8';

    $eventos[] = $r;
}

echo json_encode($eventos);
