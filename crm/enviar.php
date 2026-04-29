<?php
$data = json_decode(file_get_contents("php://input"), true);

$numero = $data['numero'] ?? '';
$mensagem = $data['mensagem'] ?? '';

function normalizarNumero($num) {
    return preg_replace('/[^0-9]/', '', $num);
}

function numerosIguais($a, $b) {
    $a = normalizarNumero($a);
    $b = normalizarNumero($b);

    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;

    $min = min(strlen($a), strlen($b));
    return $min >= 10 && substr($a, -$min) === substr($b, -$min);
}

$numeroLimpo = normalizarNumero($numero);

// envia para o Node
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

// se ok → salva no JSON
if (!empty($res['ok'])) {
    $arquivo = "data/clientes.json";
    $messageId = trim((string)($res['messageId'] ?? ''));

    $clientes = file_exists($arquivo)
        ? json_decode(file_get_contents($arquivo), true)
        : [];
    if (!is_array($clientes)) {
        $clientes = [];
    }

    $achou = false;

    foreach ($clientes as &$c) {
        if (numerosIguais($c['numero'] ?? '', $numeroLimpo)) {
            $c['mensagens'][] = [
                "texto" => $mensagem,
                "data" => date('Y-m-d H:i:s'),
                "fromMe" => true,
                "messageId" => $messageId
            ];
            $achou = true;
            break;
        }
    }

    if (!$achou) {
        $clientes[] = [
            "id" => uniqid(),
            "numero" => $numeroLimpo,
            "nome" => "Cliente",
            "status" => "novo",
            "atendente" => "humano",
            "mensagens" => [[
                "texto" => $mensagem,
                "data" => date('Y-m-d H:i:s'),
                "fromMe" => true,
                "messageId" => $messageId
            ]]
        ];
    }

    file_put_contents($arquivo, json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode($res);
