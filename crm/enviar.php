<?php
file_put_contents("debug.txt", "====================\n", FILE_APPEND);
file_put_contents("debug.txt", "bateu em: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

$data = json_decode(file_get_contents("php://input"), true);
$numero = $data['numero'] ?? '';
$mensagem = $data['mensagem'] ?? '';

file_put_contents("debug.txt", "Número recebido: $numero\n", FILE_APPEND);
file_put_contents("debug.txt", "Mensagem recebida: $mensagem\n", FILE_APPEND);

function normalizarNumero($num) {
    return preg_replace('/[^0-9]/', '', $num);
}

$numeroLimpo = normalizarNumero($numero);

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
if ($response === false) { echo "ERRO CURL: " . curl_error($ch); exit; }
curl_close($ch);
$res = json_decode($response, true);

// =====================
// SE ENVIO OK → SALVA NO JSON
// =====================
if (!empty($res['ok'])) {
    $arquivo = "data/clientes.json";
    $clientes = file_exists($arquivo) ? json_decode(file_get_contents($arquivo), true) : [];
    $achou = false;

    foreach ($clientes as &$c) {
        $numeroCliente = normalizarNumero($c['numero']);
        if ($numeroCliente == $numeroLimpo) {
            $c['mensagens'][] = [
                "de" => "meu",
                "texto" => $mensagem,
                "data" => date('Y-m-d H:i:s')
            ];
            $achou = true;
            break;
        }
    }

    if (!$achou && !empty($clientes)) {
        $clientes[0]['mensagens'][] = [
            "de" => "meu",
            "texto" => $mensagem,
            "data" => date('Y-m-d H:i:s')
        ];
    }

    file_put_contents($arquivo, json_encode($clientes, JSON_PRETTY_PRINT));
}

echo json_encode($res);