<?php
/**
 * Instagram / Meta callback endpoint
 * URL pública esperada:
 * https://danieltatuador.com/instagram/callback.php
 *
 * Este arquivo faz 2 coisas:
 * 1) Valida webhook da Meta via hub.challenge.
 * 2) Recebe o code do OAuth do Instagram, caso você use esta mesma URL como redirect_uri.
 */

// Troque este token se quiser. O mesmo valor deve ser colocado no campo "Verificar token" da Meta.
const META_VERIFY_TOKEN = 'daniel_ig_feed_2026';

// Para OAuth. Só preencha quando for fazer login/autorização do Instagram.
// Recomendo depois mover isso para um config.php fora do público ou variáveis de ambiente.
const META_APP_ID = '';
const META_APP_SECRET = '';
const INSTAGRAM_REDIRECT_URI = 'https://danieltatuador.com/instagram/callback.php';

header('X-Content-Type-Options: nosniff');

/**
 * Log simples para debug. Cria instagram_callback.log nesta mesma pasta.
 * Se preferir, apague depois que tudo estiver validado.
 */
function ig_log(string $message, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= PHP_EOL;
    @file_put_contents(__DIR__ . '/instagram_callback.log', $line, FILE_APPEND);
}

/**
 * 1) Verificação de webhook da Meta.
 * A Meta chama esta URL com:
 * ?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_mode'], $_GET['hub_verify_token'], $_GET['hub_challenge'])) {
    $mode = (string) $_GET['hub_mode'];
    $token = (string) $_GET['hub_verify_token'];
    $challenge = (string) $_GET['hub_challenge'];

    if ($mode === 'subscribe' && hash_equals(META_VERIFY_TOKEN, $token)) {
        ig_log('Webhook verificado com sucesso.');
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(200);
        echo $challenge;
        exit;
    }

    ig_log('Falha na verificação do webhook.', [
        'mode' => $mode,
        'token_recebido' => $token,
    ]);

    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(403);
    echo 'Verify token inválido.';
    exit;
}

/**
 * 2) Recebimento de eventos de webhook.
 * Para feed/imagens do site, normalmente você nem precisa disso agora.
 * Mas deixei pronto para a Meta não reclamar e para debug futuro.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody = file_get_contents('php://input') ?: '';
    ig_log('Webhook POST recebido.', [
        'body' => $rawBody,
        'headers' => function_exists('getallheaders') ? getallheaders() : [],
    ]);

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

/**
 * 3) OAuth callback do Instagram.
 * Quando você autorizar a conta, o Instagram redireciona pra cá com ?code=...
 * Aqui eu só mostro o code e, se APP_ID/SECRET estiverem preenchidos, tento trocar por token.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
    $code = (string) $_GET['code'];
    ig_log('OAuth code recebido.', ['code_inicio' => substr($code, 0, 12) . '...']);

    header('Content-Type: text/html; charset=utf-8');

    echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><title>Instagram conectado</title>';
    echo '<style>body{font-family:Arial,sans-serif;max-width:760px;margin:40px auto;padding:0 20px;line-height:1.5}code,pre{background:#f4f4f4;padding:10px;border-radius:8px;display:block;overflow:auto}.ok{color:#087a2d}.warn{color:#9a5b00}</style>';
    echo '</head><body>';
    echo '<h1 class="ok">Instagram voltou com um code.</h1>';
    echo '<p>Bom sinal. O robozinho da Meta tropeçou, mas chegou.</p>';
    echo '<p><strong>Code recebido:</strong></p><pre>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</pre>';

    if (META_APP_ID === '' || META_APP_SECRET === '') {
        echo '<p class="warn"><strong>Ainda não troquei por access token</strong>, porque META_APP_ID e META_APP_SECRET estão vazios neste arquivo.</p>';
        echo '<p>Preencha essas constantes ou salve esses dados em um config seguro, depois rode o OAuth de novo.</p>';
        echo '</body></html>';
        exit;
    }

    $postFields = http_build_query([
        'client_id' => META_APP_ID,
        'client_secret' => META_APP_SECRET,
        'grant_type' => 'authorization_code',
        'redirect_uri' => INSTAGRAM_REDIRECT_URI,
        'code' => $code,
    ]);

    $ch = curl_init('https://api.instagram.com/oauth/access_token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        ig_log('Erro curl ao trocar code por token.', ['erro' => $curlError]);
        echo '<h2>Erro ao trocar code por token</h2><pre>' . htmlspecialchars($curlError, ENT_QUOTES, 'UTF-8') . '</pre>';
        echo '</body></html>';
        exit;
    }

    ig_log('Resposta OAuth token.', ['http' => $httpCode, 'response' => $response]);

    echo '<h2>Resposta da troca por token</h2>';
    echo '<p>Guarde isso com cuidado. Token exposto é convite pra dor de cabeça com buffet livre.</p>';
    echo '<pre>' . htmlspecialchars($response, ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</body></html>';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
http_response_code(200);
echo "Instagram callback ativo.\n";
echo "Verify token: " . META_VERIFY_TOKEN . "\n";
