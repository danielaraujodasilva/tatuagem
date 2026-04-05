
<?php
$host = 'localhost';
$db   = 'crm_simples';   // ← altere
$user = 'root';
$pass = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$stages = [
    '1' => '🆕 Novo',
    '2' => '📞 Contato Inicial',
    '3' => '🤝 Reunião',
    '4' => '📄 Proposta Enviada',
    '5' => '💰 Negociação',
    '6' => '✅ Fechado - Ganho',
    '7' => '❌ Fechado - Perdido'
];

file_put_contents("data/config.json", json_encode([
    "mensagem_trigger" => $_POST['mensagem_trigger']
]));