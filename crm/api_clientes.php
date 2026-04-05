<?php

header('Content-Type: application/json');

$arquivo = "data/clientes.json";

if (!file_exists($arquivo)) {
    echo json_encode([]);
    exit;
}

$clientes = json_decode(file_get_contents($arquivo), true);

echo json_encode($clientes);