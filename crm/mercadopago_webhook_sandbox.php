<?php
declare(strict_types=1);

require_once __DIR__ . '/mercadopago_lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Use POST.']);
    exit;
}

$raw = (string)file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = [];
}

$headers = mercadopago_request_headers();
$type = (string)($payload['type'] ?? $_GET['type'] ?? '');
$dataId = (string)($_GET['data_id'] ?? $_GET['data.id'] ?? $payload['data']['id'] ?? '');
$config = mercadopago_config();
$signatureOk = mercadopago_verify_webhook_signature($headers, $dataId);
$requireSignature = !empty($config['require_webhook_signature']);

$baseEvent = [
    'received_at' => date('c'),
    'event_id' => (string)($payload['id'] ?? ''),
    'type' => $type,
    'action' => (string)($payload['action'] ?? ''),
    'data_id' => $dataId,
    'signature_ok' => $signatureOk,
    'live_mode' => (bool)($payload['live_mode'] ?? false),
];

if ($requireSignature && !$signatureOk) {
    mercadopago_log_event($baseEvent + ['error' => 'assinatura invalida']);
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Assinatura invalida.']);
    exit;
}

if ($type !== 'payment' || $dataId === '') {
    mercadopago_log_event($baseEvent + ['error' => 'evento ignorado']);
    http_response_code(200);
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

$paymentResult = mercadopago_get_payment($dataId);
$payment = is_array($paymentResult['data'] ?? null) ? $paymentResult['data'] : [];

$event = $baseEvent + [
    'payment_ok' => $paymentResult['ok'],
    'payment_status' => (string)($payment['status'] ?? ''),
    'status_detail' => (string)($payment['status_detail'] ?? ''),
    'external_reference' => (string)($payment['external_reference'] ?? ''),
    'transaction_amount' => (float)($payment['transaction_amount'] ?? 0),
    'payment_method' => (string)($payment['payment_method_id'] ?? ''),
    'date_approved' => (string)($payment['date_approved'] ?? ''),
];

if (!$paymentResult['ok']) {
    $event['error'] = $paymentResult['error'];
}

mercadopago_log_event($event);
http_response_code($paymentResult['ok'] ? 200 : 502);
echo json_encode(['ok' => $paymentResult['ok'], 'event' => $event], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
