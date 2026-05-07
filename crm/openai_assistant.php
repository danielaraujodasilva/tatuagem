<?php
require_once __DIR__ . '/../includes/system_settings.php';
require_once __DIR__ . '/data_store.php';

function crm_ai_log(array $dados): void
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents(
        $dir . '/openai_debug.log',
        '[' . date('Y-m-d H:i:s') . '] ' . json_encode($dados, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

function crm_ai_text_preview(string $texto, int $limite): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $limite);
    }

    return substr($texto, 0, $limite);
}

function crm_ai_cliente_em_modo_bot(array $cliente): bool
{
    $modo = strtolower(trim((string)($cliente['modo_atendimento'] ?? '')));
    if ($modo === 'humano') {
        return false;
    }
    if ($modo === 'bot') {
        return true;
    }

    $atendente = strtolower(trim((string)($cliente['atendente'] ?? '')));
    return $atendente === '' || $atendente === 'bot';
}

function crm_ai_mensagem_texto(array $msg): string
{
    $texto = trim((string)($msg['texto'] ?? ''));
    $transcricao = trim((string)($msg['transcricao'] ?? ''));
    if ($texto !== '' && $transcricao !== '') {
        return $texto . "\nTranscricao do audio: " . $transcricao;
    }
    if ($texto !== '') {
        return $texto;
    }
    if ($transcricao !== '') {
        return $transcricao;
    }

    $mime = (string)($msg['mediaMime'] ?? '');
    if (strpos($mime, 'image/') === 0) {
        return '[Imagem enviada pelo cliente]';
    }
    if (strpos($mime, 'audio/') === 0 || ($msg['tipo'] ?? '') === 'audio') {
        return '[Audio recebido sem transcricao]';
    }
    if (!empty($msg['mediaUrl'])) {
        return '[Arquivo enviado pelo cliente]';
    }

    return '';
}

function crm_ai_historico_conversa(array $cliente, int $limite): string
{
    $mensagens = array_slice($cliente['mensagens'] ?? [], -max(4, $limite));
    $linhas = [];

    foreach ($mensagens as $msg) {
        $texto = crm_ai_mensagem_texto($msg);
        if ($texto === '') {
            continue;
        }

        $autor = !empty($msg['fromMe']) ? 'Atendimento' : 'Cliente';
        if (($msg['de'] ?? '') === 'bot') {
            $autor = 'IA';
        }
        $linhas[] = $autor . ': ' . crm_ai_text_preview($texto, 1200);
    }

    return implode("\n", $linhas);
}

function crm_ai_extrair_texto(array $resposta): string
{
    if (isset($resposta['output_text']) && is_string($resposta['output_text'])) {
        return trim($resposta['output_text']);
    }

    $partes = [];
    foreach (($resposta['output'] ?? []) as $item) {
        foreach (($item['content'] ?? []) as $content) {
            if (isset($content['text']) && is_string($content['text'])) {
                $partes[] = $content['text'];
            }
        }
    }

    return trim(implode("\n", $partes));
}

function crm_ai_gerar_resposta(array $cliente, array $mensagemAtual, array $settings): array
{
    $apiKey = trim((string)($settings['openai_api_key'] ?? ''));
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'OpenAI API key nao configurada.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Extensao cURL do PHP nao esta disponivel.'];
    }

    $model = trim((string)($settings['openai_model'] ?? 'gpt-5-mini')) ?: 'gpt-5-mini';
    $prompt = trim((string)($settings['openai_business_prompt'] ?? ''));
    if ($prompt === '') {
        $prompt = (string)(system_settings_defaults()['openai_business_prompt'] ?? '');
    }

    $historico = crm_ai_historico_conversa($cliente, (int)($settings['openai_max_history'] ?? 20));
    $entradaAtual = crm_ai_mensagem_texto($mensagemAtual);
    if ($entradaAtual === '') {
        return ['ok' => false, 'error' => 'Mensagem sem texto para IA.'];
    }

    $input = "Dados do lead:\n"
        . "Nome: " . (string)($cliente['nome'] ?? 'Cliente') . "\n"
        . "Telefone/ID: " . (string)($cliente['numero'] ?? '') . "\n"
        . "Status: " . (string)($cliente['status'] ?? '') . "\n"
        . "Interesse: " . (string)($cliente['interesse'] ?? '') . "\n\n"
        . "Historico recente:\n" . ($historico !== '' ? $historico : 'Sem historico anterior.') . "\n\n"
        . "Responda agora a ultima mensagem do cliente. Retorne somente a mensagem que deve ser enviada no WhatsApp.";

    $payload = [
        'model' => $model,
        'instructions' => $prompt,
        'input' => $input,
        'max_output_tokens' => 450,
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
    curl_setopt($ch, CURLOPT_TIMEOUT, 80);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $json = json_decode((string)$raw, true);
    crm_ai_log([
        'model' => $model,
        'httpCode' => $httpCode,
        'curlError' => $curlError,
        'response' => crm_ai_text_preview((string)$raw, 2500),
    ]);

    if ($raw === false || $curlError !== '') {
        return ['ok' => false, 'error' => 'Erro de conexao com OpenAI: ' . $curlError];
    }
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($json)) {
        $message = $json['error']['message'] ?? ('OpenAI retornou HTTP ' . $httpCode);
        return ['ok' => false, 'error' => $message];
    }

    $texto = crm_ai_extrair_texto($json);
    if ($texto === '') {
        return ['ok' => false, 'error' => 'OpenAI nao retornou texto.'];
    }

    return ['ok' => true, 'texto' => $texto, 'model' => $model];
}

function crm_ai_encontrar_jid(array $cliente, array $mensagemAtual): string
{
    $jid = trim((string)($mensagemAtual['remoteJid'] ?? ''));
    if ($jid !== '') {
        return preg_replace('/:\d+(?=@)/', '', $jid);
    }

    foreach (array_reverse($cliente['mensagens'] ?? []) as $msg) {
        $jid = trim((string)($msg['remoteJid'] ?? ''));
        if ($jid !== '') {
            return preg_replace('/:\d+(?=@)/', '', $jid);
        }
    }

    return '';
}

function crm_ai_enviar_whatsapp(string $numero, string $jid, string $mensagem): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'erro' => 'Extensao cURL do PHP nao esta disponivel.'];
    }

    $ch = curl_init('http://localhost:3001/enviar');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'numero' => preg_replace('/\D/', '', $numero),
        'jid' => $jid,
        'mensagem' => $mensagem,
        'media' => null,
    ], JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $json = json_decode((string)$raw, true);
    crm_ai_log([
        'sendHttpCode' => $httpCode,
        'sendCurlError' => $curlError,
        'sendResponse' => crm_ai_text_preview((string)$raw, 1200),
    ]);

    if ($raw === false || $curlError !== '') {
        return ['ok' => false, 'erro' => $curlError ?: 'Erro ao chamar envio local'];
    }

    return is_array($json) ? $json : ['ok' => false, 'erro' => 'Resposta invalida do envio local'];
}

function crm_ai_responder_se_aplicavel(array &$clientes, int $clienteIndex, array $mensagemAtual): array
{
    $settings = system_settings_load();
    if (empty($settings['openai_enabled'])) {
        return ['ok' => true, 'skipped' => 'disabled'];
    }
    if (!empty($mensagemAtual['fromMe'])) {
        return ['ok' => true, 'skipped' => 'from_me'];
    }
    if (empty($clientes[$clienteIndex]) || !crm_ai_cliente_em_modo_bot($clientes[$clienteIndex])) {
        return ['ok' => true, 'skipped' => 'human_mode'];
    }

    $gerada = crm_ai_gerar_resposta($clientes[$clienteIndex], $mensagemAtual, $settings);
    if (empty($gerada['ok'])) {
        crm_ai_log(['skipped' => 'generation_failed', 'error' => $gerada['error'] ?? '']);
        return $gerada;
    }

    $texto = trim((string)($gerada['texto'] ?? ''));
    if ($texto === '') {
        return ['ok' => false, 'error' => 'Resposta vazia da IA.'];
    }

    $jid = crm_ai_encontrar_jid($clientes[$clienteIndex], $mensagemAtual);
    $envio = crm_ai_enviar_whatsapp((string)($clientes[$clienteIndex]['numero'] ?? ''), $jid, $texto);
    if (empty($envio['ok'])) {
        return ['ok' => false, 'error' => $envio['erro'] ?? 'Nao foi possivel enviar resposta da IA.'];
    }

    $clientes[$clienteIndex]['mensagens'][] = [
        'de' => 'bot',
        'texto' => $texto,
        'data' => date('Y-m-d H:i:s'),
        'fromMe' => true,
        'messageId' => trim((string)($envio['messageId'] ?? '')),
        'remoteJid' => trim((string)($envio['remoteJid'] ?? $jid)),
        'status' => 'sent',
        'tipo' => 'texto',
    ];
    $clientes[$clienteIndex]['atendente'] = 'bot';
    $clientes[$clienteIndex]['modo_atendimento'] = 'bot';
    $clientes[$clienteIndex]['data_ultimo_contato'] = date('Y-m-d H:i:s');
    crmSalvarClientes($clientes);

    return ['ok' => true, 'sent' => true, 'model' => $gerada['model'] ?? ''];
}
