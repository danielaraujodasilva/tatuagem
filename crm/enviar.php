<?php

$data = json_decode(file_get_contents("php://input"), true);

$numero = $data['numero'];
$mensagem = $data['mensagem'];

// normaliza número (remove sufixo do WhatsApp)
$numeroLimpo = str_replace('@s.whatsapp.net', '', $numero);

// =====================
// ENVIA PRO NODE
// =====================
$ch = curl_init("http://localhost:3001/enviar");

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "numero" => $numeroLimpo,
    "mensagem" => $mensagem
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$res = json_decode($response, true);

// =====================
// SE ENVIO OK → SALVA
// =====================
if (!empty($res['ok'])) {

    $arquivo = "data/clientes.json";

    $clientes = file_exists($arquivo)
        ? json_decode(file_get_contents($arquivo), true)
        : [];

    foreach ($clientes as &$c) {

        $numeroCliente = str_replace('@s.whatsapp.net', '', $c['numero']);

        if ($numeroCliente == $numeroLimpo) {

            $c['mensagens'][] = [
                "texto" => $mensagem,
                "data" => date('Y-m-d H:i:s'),
                "fromMe" => true
            ];

            break;
        }
    }

    file_put_contents($arquivo, json_encode($clientes, JSON_PRETTY_PRINT));
}

echo json_encode($res);