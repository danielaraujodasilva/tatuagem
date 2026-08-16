<?php
declare(strict_types=1);

final class PluggyClient
{
    private const BASE_URL = 'https://api.pluggy.ai';

    private ?string $apiKey = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Credenciais da Pluggy nao configuradas.');
        }
    }

    public function accounts(string $itemId): array
    {
        $payload = $this->request('GET', '/accounts?itemId=' . rawurlencode($itemId));
        return $this->results($payload);
    }

    public function transactions(string $accountId, string $dateFrom): array
    {
        $path = '/v2/transactions?accountId=' . rawurlencode($accountId) . '&dateFrom=' . rawurlencode($dateFrom);
        $transactions = [];
        $pages = 0;

        while ($path !== '' && $pages < 100) {
            $payload = $this->request('GET', $path);
            array_push($transactions, ...$this->results($payload));
            $path = $this->nextPath((string)($payload['next'] ?? ''));
            $pages++;
        }

        if ($pages >= 100 && $path !== '') {
            throw new RuntimeException('A Pluggy retornou mais paginas que o limite de seguranca.');
        }

        return $transactions;
    }

    private function apiKey(): string
    {
        if ($this->apiKey !== null) {
            return $this->apiKey;
        }

        $payload = $this->request('POST', '/auth', [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
        ], false);
        $apiKey = trim((string)($payload['apiKey'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('A Pluggy nao retornou uma chave de acesso valida.');
        }

        return $this->apiKey = $apiKey;
    }

    private function request(string $method, string $path, ?array $body = null, bool $authenticated = true): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensao cURL do PHP nao esta habilitada.');
        }

        $url = str_starts_with($path, 'https://') ? $path : self::BASE_URL . '/' . ltrim($path, '/');
        if (!str_starts_with($url, self::BASE_URL . '/')) {
            throw new RuntimeException('Endereco de paginacao da Pluggy invalido.');
        }

        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($authenticated) {
            $headers[] = 'X-API-KEY: ' . $this->apiKey();
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }

        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('Falha de rede ao consultar a Pluggy: ' . $error);
        }

        $payload = json_decode($response, true);
        if ($status < 200 || $status >= 300) {
            $message = 'Resposta recusada pela Pluggy.';
            if (is_array($payload)) {
                $error = $payload['error'] ?? null;
                $message = (string)($payload['message'] ?? (is_array($error) ? ($error['message'] ?? '') : $error) ?: $message);
            }
            throw new RuntimeException($message . ' (HTTP ' . $status . ')');
        }
        if (!is_array($payload)) {
            throw new RuntimeException('A Pluggy retornou uma resposta invalida.');
        }

        return $payload;
    }

    private function results(array $payload): array
    {
        if (isset($payload['results']) && is_array($payload['results'])) {
            return $payload['results'];
        }
        return array_is_list($payload) ? $payload : [];
    }

    private function nextPath(string $next): string
    {
        if ($next === '') {
            return '';
        }
        if (str_starts_with($next, '?')) {
            return '/v2/transactions' . $next;
        }
        if (str_starts_with($next, '/')) {
            return $next;
        }
        if (str_starts_with($next, self::BASE_URL . '/')) {
            return $next;
        }
        throw new RuntimeException('Cursor de paginacao da Pluggy invalido.');
    }
}
