<?php

$data = [
    "mensagem_trigger" => $_POST['mensagem_trigger'] ?? 'oi'
];

file_put_contents("data/config.json", json_encode($data, JSON_PRETTY_PRINT));

header("Location: configuracoes.php");