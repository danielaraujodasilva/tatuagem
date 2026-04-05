<?php

$data = json_decode(file_get_contents("php://input"), true);

$numero = $data['numero'] ?? '';
$mensagem = strtolower(trim($data['mensagem'] ?? ''));

if (!$numero || !$mensagem) {
    exit;
}

$config = json_decode(file_get_contents("data/config.json"), true);
$mensagem_trigger = strtolower(trim($config['mensagem_trigger'] ?? 'oi'));

$clientes = [];

if (file_exists("data/clientes.json")) {
    $clientes = json_decode(file_get_contents("data/clientes.json"), true);
}

$clienteIndex = null;

// 🔍 procurar cliente existente
foreach ($clientes as $index => $c) {
    if ($c['numero'] === $numero) {
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
        "atendente" => "bot",
        "mensagens" => []
    ];

    $clientes[] = $novo;
    $clienteIndex = count($clientes) - 1;
}

// 💬 adiciona mensagem no histórico
$clientes[$clienteIndex]['mensagens'][] = [
    "de" => "cliente",
    "texto" => $mensagem,
    "data" => date("Y-m-d H:i:s")
];

// 💾 salva tudo
file_put_contents("data/clientes.json", json_encode($clientes, JSON_PRETTY_PRINT));