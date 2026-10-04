<?php
declare(strict_types=1);
/**
 * Simulador de atendimento: recebe a conversa e devolve uma resposta da Irene.
 * NUNCA envia nada a cliente: responde so na tela, para o estudio validar a voz.
 *
 * Motores: LM Studio (local) ou "meu cerebro" - o mesmo modelo que roda o Codex.
 * Ultimo recurso: o playbook aprendido das conversas.
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
 * Os fatos saem do playbook aprendido nas 2.715 conversas do WhatsApp.
 */
function irene_system(): string
{
    $linhas = [
        'Você é a Irene, secretária do estúdio do Daniel, tatuador em São Paulo.',
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

/** Tira tags de raciocinio (qwen3 e afins). */
function irene_limpar(string $texto): string
{
    $texto = preg_replace('/<think>[\s\S]*?<\/think>/i', ' ', $texto) ?? $texto;
    $texto = preg_replace('/<\/?think>/i', ' ', $texto) ?? $texto;
    return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
}

/**
 * Primeira mensagem de qualquer cliente: quem fala e quais sao as opcoes.
 * Nao passa pelo modelo - e sempre igual, curtinha e sem erro.
 */
function irene_abertura(): string
{
    return "Oi! Aqui é a Irene, a inteligência artificial do estúdio 😊 "
        . "O Daniel e a Hellen estão ocupados agora e ainda não podem te atender.\n"
        . "Manda 1 pra aguardar falar direto com eles, ou 2 pra eu já tirar suas dúvidas enquanto isso 🙌";
}

/** Cliente respondeu o menu: 1 = esperar o Daniel/Hellen, 2 = falar comigo. */
function irene_opcao(string $texto): ?array
{
    $limpo = trim(preg_replace('/[^0-9a-z]/i', '', mb_strtolower($texto)) ?? '');
    if ($limpo === '1' || $limpo === 'um') {
        return [
            'texto' => 'Beleza! Já avisei a Hellen e o Daniel que você quer falar com eles 🙌 Só um minutinho que eles te chamam.',
            'estado' => 'humano',
        ];
    }
    if ($limpo === '2' || $limpo === 'dois') {
        return [
            'texto' => 'Fechou! Manda sua dúvida que eu já vou respondendo até a Hellen ou o Daniel assumirem 🙌',
            'estado' => 'ajudando',
        ];
    }
    return null;
}

/** LM Studio: API local compativel com OpenAI. */
function irene_lmstudio(string $modelo, array $mensagens): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'erro' => 'cURL indisponivel no PHP'];
    }
    // Qwen3 responde direto quando o ultimo "user" leva /no_think; sem isso ele
    // gasta o limite de tokens "pensando" e a resposta sai vazia ou lenta.
    if ($modelo !== '' && stripos($modelo, 'qwen3') !== false) {
        for ($i = count($mensagens) - 1; $i >= 0; $i--) {
            if (($mensagens[$i]['role'] ?? '') === 'user') {
                $mensagens[$i]['content'] = rtrim((string)$mensagens[$i]['content']) . ' /no_think';
                break;
            }
        }
    }
    $payload = [
        'model' => $modelo,
        'stream' => false,
        'temperature' => 0.6,
        'max_tokens' => 220,
        'messages' => array_merge([['role' => 'system', 'content' => irene_system()]], $mensagens),
    ];
    $ch = curl_init(v2_lmstudio_url() . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 150,
    ]);
    $corpo = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $falha = curl_error($ch);
    curl_close($ch);

    if ($corpo === false) {
        return ['ok' => false, 'erro' => $falha !== '' ? $falha : 'LM Studio nao respondeu'];
    }
    $json = json_decode((string)$corpo, true);
    if ($status !== 200) {
        return ['ok' => false, 'erro' => (string)($json['error']['message'] ?? ('LM Studio HTTP ' . $status))];
    }
    $texto = irene_limpar((string)($json['choices'][0]['message']['content'] ?? ''));
    return $texto !== '' ? ['ok' => true, 'texto' => $texto] : ['ok' => false, 'erro' => 'LM Studio devolveu resposta vazia'];
}

/**
 * "Meu cerebro": o mesmo modelo que o Codex usa nesta maquina (DeepSeek).
 * A chave vive so no ambiente do servidor - nunca entra em arquivo nem em log.
 */
function irene_cerebro(string $modelo, array $mensagens): array
{
    $cfg = v2_cerebro();
    if (!$cfg['ligado']) {
        return ['ok' => false, 'erro' => 'chave do Codex nao esta no ambiente do servidor'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'erro' => 'cURL indisponivel no PHP'];
    }
    $payload = [
        'model' => $modelo,
        'stream' => false,
        'temperature' => 0.7,
        'max_tokens' => 320,
        'messages' => array_merge([['role' => 'system', 'content' => irene_system()]], $mensagens),
    ];
    $ch = curl_init($cfg['url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 90,
    ]);
    $corpo = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $falha = curl_error($ch);
    curl_close($ch);

    if ($corpo === false) {
        return ['ok' => false, 'erro' => $falha !== '' ? $falha : 'o cerebro nao respondeu'];
    }
    $json = json_decode((string)$corpo, true);
    if ($status !== 200) {
        $recado = mb_substr((string)($json['error']['message'] ?? 'erro do provedor'), 0, 160);
        return ['ok' => false, 'erro' => 'HTTP ' . $status . ' ' . $recado];
    }
    $texto = irene_limpar((string)($json['choices'][0]['message']['content'] ?? ''));
    return $texto !== '' ? ['ok' => true, 'texto' => $texto] : ['ok' => false, 'erro' => 'o cerebro devolveu resposta vazia'];
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

$in = irene_payload();
$mensagens = irene_mensagens($in);
if (!$mensagens || $mensagens[count($mensagens) - 1]['role'] !== 'user') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Manda uma mensagem de cliente primeiro.']);
    exit;
}

/* Quem responde: "lmstudio|modelo" ou "cerebro|modelo" (vazio = preferido local). */
$escolha = trim((string)($in['modelo'] ?? ''));
$backend = '';
$modelo = '';
if ($escolha !== '' && strpos($escolha, '|') !== false) {
    $partes = explode('|', $escolha, 2);
    $backend = strtolower(trim($partes[0]));
    $modelo = trim($partes[1]);
}

$resposta = '';
$motor = '';
$aviso = '';
$estado = 'ia';

/* Regra do estudio: a primeira resposta nunca e do modelo, e o menu 1/2 tambem nao. */
$jaFalei = false;
foreach ($mensagens as $m) {
    if ($m['role'] === 'assistant') {
        $jaFalei = true;
        break;
    }
}
$ultima = (string)$mensagens[count($mensagens) - 1]['content'];

if (!$jaFalei) {
    $resposta = irene_abertura();
    $motor = 'mensagem de abertura do estúdio';
    $estado = 'menu';
} elseif (($opcao = irene_opcao($ultima)) !== null) {
    $resposta = $opcao['texto'];
    $motor = 'mensagem do estúdio · opção ' . ($opcao['estado'] === 'humano' ? '1' : '2');
    $estado = $opcao['estado'];
} elseif ($backend === 'cerebro') {
    $qual = $modelo !== '' ? $modelo : (string)v2_cerebro()['modelo'];
    $r = irene_cerebro($qual, $mensagens);
    if (!empty($r['ok'])) {
        $resposta = (string)$r['texto'];
        $motor = 'Meu cérebro (Codex) · ' . $qual;
    } else {
        $aviso = 'Meu cérebro: ' . (string)($r['erro'] ?? 'falhou');
        /* Se a internet/API falhar, o simulador continua de pé no modelo local. */
        $lm = v2_lmstudio_modelos();
        $local = v2_lmstudio_modelo_preferido() ?: ($lm[0] ?? '');
        if ($local !== '') {
            $r2 = irene_lmstudio($local, $mensagens);
            if (!empty($r2['ok'])) {
                $resposta = (string)$r2['texto'];
                $motor = 'LM Studio (reserva) · ' . $local;
            }
        }
    }
} else {
    $lm = v2_lmstudio_modelos();
    $escolhido = $modelo !== '' ? $modelo : (v2_lmstudio_modelo_preferido() ?: ($lm[0] ?? 'qwen/qwen3-8b'));
    $r = irene_lmstudio($escolhido, $mensagens);
    if (!empty($r['ok'])) {
        $resposta = (string)$r['texto'];
        $motor = 'LM Studio · ' . $escolhido;
    } else {
        $aviso = 'LM Studio: ' . (string)($r['erro'] ?? 'falhou');
    }
}

if ($resposta === '') {
    // Ultimo recurso: o playbook aprendido das 2.715 conversas reais.
    $ultima = '';
    for ($i = count($mensagens) - 1; $i >= 0; $i--) {
        if ($mensagens[$i]['role'] === 'user') { $ultima = (string)$mensagens[$i]['content']; break; }
    }
    $sug = v2_sugestao_resposta($ultima);
    $resposta = $sug['texto'] ?? 'Oi! Me conta o que você quer fazer que eu já te ajudo 🙌';
    $motor = 'playbook do estúdio (IA local indisponível)';
}

$vozSistema = v2_voz_atual();

echo json_encode([
    'ok' => true,
    'resposta' => $resposta,
    'motor' => $motor,
    'estado' => $estado,
    'aviso' => $aviso,
    'audio' => 'api/tts.php?' . http_build_query([
        'engine' => $vozSistema['engine'],
        'voice' => $vozSistema['voz'],
        'v' => $vozSistema['variacao'],
        'vel' => (string)$vozSistema['vel'],
        'tom' => (string)$vozSistema['tom'],
        'text' => $resposta,
    ], '', '&', PHP_QUERY_RFC3986),
], JSON_UNESCAPED_UNICODE);
