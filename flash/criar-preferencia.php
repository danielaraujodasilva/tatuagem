<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['nome']) || empty($input['whatsapp']) || empty($input['design'])) {
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

// TROQUE pela sua chave TEST (não use a de produção aqui!)
$accessToken = 'APP_USR-a4eb5361-7b17-4d43-9e04-8be1ca2225e6';   // ← SUA CHAVE TEST AQUI

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