<?php
header('Content-Type: application/json');
$allowedOrigin = getenv('FLASH_ALLOWED_ORIGIN') ?: '';
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['nome']) || empty($input['whatsapp']) || empty($input['design'])) {
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

$accessToken = getenv('MP_ACCESS_TOKEN') ?: '';
if ($accessToken === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Pagamento indisponivel no momento.']);
    exit;
}

$data = [
    'items' => [[
        'title' => 'Flash Tattoo - ' . $input['design'],
        'quantity' => 1,
        'currency_id' => 'BRL',
        'unit_price' => 249.90
    ]],
    'payer' => ['name' => $input['nome']],
    'back_urls' => [
        'success' => 'https://danieltatuador.com/flash/sucesso.html',
        'failure' => 'https://danieltatuador.com/flash/falha.html',
        'pending' => 'https://danieltatuador.com/flash/pendente.html'
    ],
    'auto_return' => 'approved'
];

$ch = curl_init('https://api.mercadopago.com/checkout/preferences');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
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
