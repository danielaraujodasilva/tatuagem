<?php
header('Content-Type: application/json');

// Dados enviados do frontend
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['nome']) || empty($input['whatsapp']) || empty($input['design'])) {
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

// SUA CHAVE SECRETA (TEST para sandbox!)
$accessToken = 'APP_USR-7473082801071546-022611-1d7c398f7def4a230172012f2ee6457c-3228515631'; // TROQUE PELA SUA TEST

$data = [
    'items' => [[
        'title' => 'Flash Tattoo - ' . $input['design'],
        'quantity' => 1,
        'currency_id' => 'BRL',
        'unit_price' => 249.90
    ]],
    'payer' => ['name' => $input['nome']],
    'back_urls' => [
        'success' => 'http://localhost/flash/sucesso.html',
        'failure' => 'http://localhost/flash/falha.html',
        'pending' => 'http://localhost/flash/pendente.html'
    ],
    'auto_return' => 'approved'
];

$ch = curl_init('https://api.mercadopago.com/checkout/preferences');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['id'])) {
    echo json_encode(['id' => $result['id']]);
} else {
    echo json_encode(['error' => $result['message'] ?? 'Erro ao criar pagamento']);
}
?>