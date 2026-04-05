<?php

$data = json_decode(file_get_contents("php://input"), true);

$numero = $data['numero'];
$mensagem = $data['mensagem'];

// envia pro Node
$ch = curl_init("http://localhost:3001/enviar");

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "numero" => $numero,
    "mensagem" => $mensagem
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

echo $response;