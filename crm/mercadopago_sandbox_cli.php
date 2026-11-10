<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/mercadopago_lib.php';

$config = mercadopago_config();
$me = mercadopago_api_request('GET', '/users/me');
$booking = [
    'name' => 'Cliente Teste Irene',
    'phone' => '(11) 99999-9999',
    'date' => date('Y-m-d', strtotime('+1 day')),
    'time' => '14:00',
    'end_time' => '15:00',
    'description' => 'Tatuagem teste - rosa no braco',
    'artist' => 'Daniel',
    'agendamento_id' => 'TESTE',
];
$result = mercadopago_create_sinal_preference($booking);
$preference = is_array($result['data'] ?? null) ? $result['data'] : [];

echo json_encode([
    'config_ok' => trim((string)($config['access_token'] ?? '')) !== '',
    'me_ok' => $me['ok'],
    'me_id' => $me['data']['id'] ?? null,
    'me_nickname' => $me['data']['nickname'] ?? null,
    'me_tags' => $me['data']['tags'] ?? [],
    'preference_ok' => $result['ok'],
    'preference_http' => $result['status'] ?? null,
    'preference_id' => $preference['id'] ?? null,
    'reference' => $result['reference'] ?? null,
    'init_point' => $preference['init_point'] ?? null,
    'sandbox_init_point' => $preference['sandbox_init_point'] ?? null,
    'error' => $result['error'] ?? null,
    'api_message' => $preference['message'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
