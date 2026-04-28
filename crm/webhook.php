<?php

$data = json_decode(file_get_contents("php://input"), true);

$numero = $data['numero'] ?? '';
$mensagemOriginal = trim($data['mensagem'] ?? '');
$mensagem = strtolower($mensagemOriginal);

if (!$numero || !$mensagem) {
    exit;
}

$config = json_decode(file_get_contents("data/config.json"), true);
$mensagem_trigger = strtolower(trim($config['mensagem_trigger'] ?? 'oi'));

$clientes = [];

if (file_exists("data/clientes.json")) {
    $clientes = json_decode(file_get_contents("data/clientes.json"), true);
}
if (!is_array($clientes)) {
    $clientes = [];
}

function normalizarNumero($num) {
    return preg_replace('/\D/', '', (string)$num);
}

function numerosIguais($a, $b) {
    $a = normalizarNumero($a);
    $b = normalizarNumero($b);

    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;

    $min = min(strlen($a), strlen($b));
    return $min >= 10 && substr($a, -$min) === substr($b, -$min);
}

$clienteIndex = null;

// 🔍 procurar cliente existente
foreach ($clientes as $index => $c) {
    if (numerosIguais($c['numero'] ?? '', $numero)) {
        $clienteIndex = $index;
        break;
    }
}

// 🧠 se NÃO existir, cria novo
if ($clienteIndex === null) {

    // só cria se for mensagem gatilho
    if ($mensagem !== $mensagem_trigger) {
        exit;
    }

    $novo = [
        "id" => uniqid(),
        "numero" => $numero,
        "nome" => "Cliente",
        "status" => "novo",
        "etapa" => "1",
        "atendente" => "bot",
        "mensagens" => []
    ];

    $clientes[] = $novo;
    $clienteIndex = count($clientes) - 1;
}

// 💬 adiciona mensagem no histórico
$clientes[$clienteIndex]['mensagens'][] = [
    "de" => "cliente",
    "texto" => $mensagemOriginal,
    "data" => date("Y-m-d H:i:s"),
    "fromMe" => false
];

// 💾 salva tudo
file_put_contents("data/clientes.json", json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
