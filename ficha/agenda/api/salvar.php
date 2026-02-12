$data = json_decode(file_get_contents("php://input"), true);

$inicio = new DateTime($data['inicio']);
$fim = new DateTime($data['fim']);

$conn = new mysqli("localhost","root","","SEU_BANCO");

$stmt = $conn->prepare("
INSERT INTO agendamentos 
(descricao,data_tatuagem,hora_inicio,hora_fim,status)
VALUES (?,?,?,?, 'agendado')
");

$stmt->bind_param(
    "ssss",
    $data['descricao'],
    $inicio->format('Y-m-d'),
    $inicio->format('H:i:s'),
    $fim->format('H:i:s')
);

$stmt->execute();
