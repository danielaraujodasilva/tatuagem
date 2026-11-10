<?php
declare(strict_types=1);

if (!function_exists('mercadopago_config')) {
    function mercadopago_config(): array
    {
        static $config = null;
        if (is_array($config)) {
            return $config;
        }

        $localPath = __DIR__ . '/config.mercadopago.local.php';
        $local = is_file($localPath) ? require $localPath : [];
        if (!is_array($local)) {
            $local = [];
        }

        $config = array_merge([
            'environment' => 'test',
            'access_token' => getenv('MP_ACCESS_TOKEN') ?: '',
            'public_key' => getenv('MP_PUBLIC_KEY') ?: '',
            'webhook_secret' => getenv('MP_WEBHOOK_SECRET') ?: '',
            'require_webhook_signature' => true,
            'notification_url' => '',
            'back_url' => '',
        ], $local);

        return $config;
    }
}

if (!function_exists('mercadopago_api_request')) {
    function mercadopago_api_request(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
    {
        $config = mercadopago_config();
        $token = trim((string)($config['access_token'] ?? ''));
        if ($token === '') {
            return [
                'ok' => false,
                'status' => 0,
                'data' => [],
                'raw' => '',
                'error' => 'Access Token do Mercado Pago nao configurado.',
            ];
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: DanielTatuador-CRM/1.0',
        ];

        foreach ($extraHeaders as $name => $value) {
            $headers[] = trim((string)$name) . ': ' . trim((string)$value);
        }

        $ch = curl_init('https://api.mercadopago.com' . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $ok = $curlError === '' && $status >= 200 && $status < 300;

        return [
            'ok' => $ok,
            'status' => $status,
            'data' => is_array($decoded) ? $decoded : [],
            'raw' => is_string($raw) ? $raw : '',
            'error' => $curlError !== '' ? $curlError : ($ok ? '' : 'Mercado Pago retornou HTTP ' . $status . '.'),
        ];
    }
}

if (!function_exists('mercadopago_create_sinal_preference')) {
    function mercadopago_create_sinal_preference(array $booking): array
    {
        $config = mercadopago_config();
        $reference = trim((string)($booking['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'sinal_teste_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
        }

        $backUrl = trim((string)($config['back_url'] ?? ''));
        $notificationUrl = trim((string)($config['notification_url'] ?? ''));
        $title = 'Sinal de agendamento';
        $artist = trim((string)($booking['artist'] ?? ''));
        if ($artist !== '') {
            $title .= ' - ' . $artist;
        }

        $payload = [
            'items' => [[
                'id' => 'sinal-agendamento',
                'title' => $title,
                'description' => 'Sinal de R$ 50,00 para reserva de horario.',
                'category_id' => 'services',
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => 50.00,
            ]],
            'external_reference' => $reference,
            'metadata' => [
                'tipo' => 'sinal_agendamento',
                'agendamento_id' => (string)($booking['agendamento_id'] ?? ''),
                'cliente_nome' => (string)($booking['name'] ?? ''),
                'tatuador' => $artist,
            ],
            'statement_descriptor' => 'DANIELTATTOO',
        ];

        if ($backUrl !== '') {
            $payload['back_urls'] = [
                'success' => $backUrl . '?status=success',
                'pending' => $backUrl . '?status=pending',
                'failure' => $backUrl . '?status=failure',
            ];
            $payload['auto_return'] = 'approved';
        }

        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        }

        $result = mercadopago_api_request(
            'POST',
            '/checkout/preferences',
            $payload,
            ['X-Idempotency-Key' => 'sinal-' . hash('sha256', $reference)]
        );
        $result['reference'] = $reference;

        return $result;
    }
}

if (!function_exists('mercadopago_get_payment')) {
    function mercadopago_get_payment(string $paymentId): array
    {
        return mercadopago_api_request('GET', '/v1/payments/' . rawurlencode($paymentId));
    }
}

if (!function_exists('mercadopago_request_headers')) {
    function mercadopago_request_headers(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!is_array($headers) || $headers === []) {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with((string)$key, 'HTTP_')) {
                    $name = strtolower(str_replace('_', '-', substr((string)$key, 5)));
                    $headers[$name] = (string)$value;
                }
            }
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string)$name)] = (string)$value;
        }

        return $normalized;
    }
}

if (!function_exists('mercadopago_verify_webhook_signature')) {
    function mercadopago_verify_webhook_signature(array $headers, string $dataId): bool
    {
        $secret = trim((string)(mercadopago_config()['webhook_secret'] ?? ''));
        if ($secret === '') {
            return false;
        }

        $signature = trim((string)($headers['x-signature'] ?? ''));
        $requestId = trim((string)($headers['x-request-id'] ?? ''));
        if ($signature === '') {
            return false;
        }

        $timestamp = '';
        $hash = '';
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 'ts') {
                $timestamp = $pair[1];
            } elseif ($pair[0] === 'v1') {
                $hash = $pair[1];
            }
        }

        if ($timestamp === '' || $hash === '') {
            return false;
        }

        $manifestParts = [];
        if ($dataId !== '') {
            $manifestParts[] = 'id:' . $dataId;
        }
        if ($requestId !== '') {
            $manifestParts[] = 'request-id:' . $requestId;
        }
        $manifestParts[] = 'ts:' . $timestamp;
        $manifest = implode(';', $manifestParts) . ';';
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals(strtolower($expected), strtolower($hash));
    }
}

if (!function_exists('mercadopago_log_event')) {
    function mercadopago_log_event(array $event): void
    {
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            @file_put_contents($dir . '/mercadopago_sandbox.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

if (!function_exists('mercadopago_recent_events')) {
    function mercadopago_recent_events(int $limit = 12): array
    {
        $path = __DIR__ . '/data/mercadopago_sandbox.log';
        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $events = [];
        foreach (array_reverse(array_slice($lines, -100)) as $line) {
            $event = json_decode($line, true);
            if (is_array($event)) {
                $events[] = $event;
            }
            if (count($events) >= $limit) {
                break;
            }
        }

        return $events;
    }
}
