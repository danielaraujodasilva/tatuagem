<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require_once __DIR__ . '/data_store.php';

header('Content-Type: application/json');

$clientes = crmCarregarClientes();

echo json_encode(array_values($clientes), JSON_UNESCAPED_UNICODE);
