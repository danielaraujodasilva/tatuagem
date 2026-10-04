<?php
declare(strict_types=1);
/**
 * Simulador de atendimento: recebe a conversa e devolve uma resposta da Irene.
 * NUNCA envia nada a cliente: responde so na tela, para o estudio validar a voz.
 * Motor: Ollama local (se estiver ligado) ou o playbook aprendido das conversas.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

/** Falas de estilo que a Irene aprendeu nas conversas reais (tabela v2_aprendizado). */
function irene_estilo(): array
{
    $pdo = v2_try(static fn() => v2_crm(), null);
    if (!$pdo instanceof PDO || !v2_tabela_existe($pdo, 'v2_aprendizado')) {
        return [];
    }
    return v2_try(static function () use ($pdo) {
        $st = $pdo->query("SELECT valor FROM v2_aprendizado WHERE tipo IN ('estilo','padrao_voz') AND valor <> '' ORDER BY ocorrencias DESC LIMIT 6");
        return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'valor');
    }, []);
}

/**
 * Instrucoes da Irene: jeito de falar do estudio + fatos do negocio.
 * Os fatos abaixo saem do playbook aprendido nas 2.715 conversas do WhatsApp.
 */
function irene_system(): string
{
    $linhas = [
        'Você é a Ellen, secretária do estúdio do Daniel, tatuador em São Paulo.',
        'Você atende no WhatsApp do estúdio.',
        '',
        'COMO FALAR:',
        '- Curto e simpático: no máximo 2 frases. Nunca escreva textão, lista ou parágrafo longo.',
        '- Responda SEMPRE com uma frase completa, cumprimentando ou confirmando a dúvida. Nunca responda só com um número.',
        '- Use emoji no lugar do ponto final (🙌 🔥 😊 👊).',
        '- Escreva "rs" em vez de "kk", e só de vez em quando, quando a conversa estiver descontraída. Nunca no começo da mensagem.',
        '- Se já cumprimentou nesta conversa, não repita "Oi!" em toda mensagem.',
        '- Fale como gente: "Oi!", "Opa, legal!", "Fechou", "Bora!"',
        '- Nunca diga que é uma IA e nunca fale de sistema, robô ou atendimento automático.',
        '- Se ainda não sabe o nome do cliente, pergunte o nome.',
        '- Se a informação não estiver nos fatos abaixo, diga que já confirma com o Daniel.',
        '',
        'FATOS DO ESTÚDIO (use exatamente estes, nunca invente):',
        '- Preço: 699 sem pomada anestésica e 1100 com pomada anestésica.',
        '- Por região: 700 cada região (costas ou braço). Antebraço interno sai 500.',
        '- Reserva: sinal de 50,00, descontado no dia. O pix cai direto pro Daniel.',
        '- Endereço: Rua Catende, 287B, Jd Nordeste, São Paulo — pertinho da estação Artur Alvim do metrô.',
        '- Cuidados: lavar com sabonete neutro, secar com papel e passar a pomada fininha. Nada de sol, piscina ou mar por 15 dias.',
        '- Se o cliente mandar áudio, a resposta também é em áudio.',
        '',
        'EXEMPLOS DE COMO O ESTÚDIO RESPONDE:',
        '- "Opa, legal! Qual seu nome? E já sabe qual tatuagem quer fazer?"',
        '- "Oi? Bora retomar o agendamento da sua tatuagem?"',
        '- "Fechou! Assim que tiver ideia de data me chama 🙌 Obrigada!"',
        '- "Sem pressa! Qualquer dúvida me chama 🙌"',
    ];

    $estilo = irene_estilo();
    if ($estilo) {
        $linhas[] = '';
        $linhas[] = 'JEITO DE FALAR APRENDIDO NAS CONVERSAS:';
        foreach ($estilo as $e) {
            $e = trim(preg_replace('/\s+/u', ' ', (string)$e) ?? '');
            if ($e !== '') {
                $linhas[] = '- ' . mb_substr($e, 0, 140);
            }
        }
    }

    return implode("\n", $linhas);
}

function irene_payload(): array
{
    $raw = file_get_contents('php://input');
    $in = json_decode((string)$raw, true);
    return is_array($in) ? $in : [];
}

/** Historico vindo do navegador: [{papel:'cliente'|'irene', texto:'...'}]. */
function irene_mensagens(array $in): array
{
    $lista = is_array($in['historico'] ?? null) ? $in['historico'] : [];
    $out = [];
    foreach (array_slice($lista, -14) as $m) {
        $texto = trim((string)($m['texto'] ?? ''));
        if ($texto === '') {
            continue;
        }
        $papel = ((string)($m['papel'] ?? '')) === 'irene' ? 'assistant' : 'user';
        $out[] = ['role' => $papel, 'content' => mb_substr($texto, 0, 800)];
    }
    return $out;
}

/** Config do Ollama: usa a mesma do CRM quando existir. */
function irene_ollama(): array
{
    $url = 'http://localhost:11434';
    $model = 'llama3:8b';
    $cfg = __DIR__ . '/../../crm/data/config.json';
    if (is_file($cfg)) {
        $json = json_decode((string)file_get_contents($cfg), true);
        if (is_array($json)) {
            $url = rtrim((string)($json['ollama_url'] ?? $url), '/') ?: $url;
            $model = trim((string)($json['ollama_model'] ?? $model)) ?: $model;
        }
    }
    return [$url, $model];
}

$in = irene_payload();
$mensagens = irene_mensagens($in);
if (!$mensagens || $mensagens[count($mensagens) - 1]['role'] !== 'user') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Manda uma mensagem de cliente primeiro.']);
    exit;
}

$resposta = '';
$motor = '';

$modeloPedido = trim((string)($in['modelo'] ?? ''));

if (function_exists('curl_init')) {
    [$url, $model] = irene_ollama();
    if ($modeloPedido !== '' && isset(v2_irene_modelos()[$modeloPedido])) {
        $model = $modeloPedido;
    }
    $payload = [
        'model' => $model,
        'stream' => false,
        'messages' => array_merge(
            [['role' => 'system', 'content' => irene_system()]],
            $mensagens
        ),
        'options' => [
            'temperature' => 0.6,
            'num_predict' => 200,
            'stop' => ['<think>', '</think>', 'Thinking', 'done thinking'],
        ],
    ];

    $ch = curl_init($url . '/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 150,
    ]);
    $corpo = curl_exec($ch);
    $erro = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($corpo !== false && $status === 200) {
        $json = json_decode((string)$corpo, true);
        $texto = trim((string)($json['message']['content'] ?? ''));
        $texto = preg_replace('/<\/?think>/i', '', $texto) ?? $texto;
        $texto = trim($texto);
        if ($texto !== '') {
            $resposta = $texto;
            $motor = 'IA local · ' . $model;
        }
    }
}

if ($resposta === '') {
    // Plano B: o playbook aprendido das 2.715 conversas reais.
    $ultima = '';
    for ($i = count($mensagens) - 1; $i >= 0; $i--) {
        if ($mensagens[$i]['role'] === 'user') { $ultima = (string)$mensagens[$i]['content']; break; }
    }
    $sug = v2_sugestao_resposta($ultima);
    $resposta = $sug['texto'] ?? 'Oi! Me conta o que você quer fazer que eu já te ajudo 🙌';
    $motor = 'playbook do estúdio (IA local desligada)';
}

echo json_encode([
    'ok' => true,
    'resposta' => $resposta,
    'motor' => $motor,
    'audio' => 'api/tts.php?engine=' . rawurlencode((string)(v2_config()['tts_engine'] ?? 'windows'))
        . '&v=calor_rapido&text=' . rawurlencode($resposta),
], JSON_UNESCAPED_UNICODE);
