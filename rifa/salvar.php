<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // ajuste se quiser mais segurança

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'Método inválido']);
  exit;
}

$data = file_get_contents('php://input');
$json = json_decode($data, true);

if (!$json || !is_array($json)) {
  echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
  exit;
}

file_put_contents('data.json', json_encode($json, JSON_PRETTY_PRINT));

echo json_encode(['ok' => true]);
?>