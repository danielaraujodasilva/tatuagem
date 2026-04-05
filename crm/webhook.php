<?php

$data = json_decode(file_get_contents("php://input"), true);

$numero = $data['numero'] ?? '';
$mensagem = strtolower(trim($data['mensagem'] ?? ''));

$config = json_decode(file_get_contents("data/config.json"), true);

$mensagem_trigger = strtolower(trim($config['mensagem_trigger'] ?? 'oi'));

if ($mensagem === $mensagem_trigger) {

    $clientes = [];

    if (file_exists("data/clientes.json")) {
        $clientes = json_decode(file_get_contents("data/clientes.json"), true);
    }

    // evitar duplicado
    foreach ($clientes as $c) {
        if ($c['numero'] === $numero) {
            exit;
        }
    }

    $novo = [
        "id" => uniqid(),
        "numero" => $numero,
        "nome" => "Cliente",
        "status" => "novo",
        "mensagens" => [
            [
                "de" => "cliente",
                "texto" => $mensagem,
                "data" => date("Y-m-d H:i:s")
            ]
        ]
    ];

    $clientes[] = $novo;

    file_put_contents("data/clientes.json", json_encode($clientes, JSON_PRETTY_PRINT));
}