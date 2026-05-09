<?php
require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/openai_assistant.php';

header('Content-Type: application/json; charset=utf-8');

$settings = system_settings_load();
$testar = !empty($_GET['testar']);

$resultado = [
    'ok' => true,
    'ia_ativada' => !empty($settings['openai_enabled']),
    'provedor' => (string)($settings['ai_provider'] ?? 'ollama'),
    'ollama_url' => (string)($settings['ollama_url'] ?? 'http://localhost:11434'),
    'ollama_model' => (string)($settings['ollama_model'] ?? 'qwen3:14b'),
    'mensagens_contexto' => (int)($settings['openai_max_history'] ?? 0),
    'timeout_ia_segundos' => (int)($settings['ai_timeout_seconds'] ?? 120),
    'tamanho_resposta' => (int)($settings['ai_num_predict'] ?? 220),
    'php_curl_disponivel' => function_exists('curl_init'),
    'log' => 'crm/data/ia_debug.log',
];

$resultado['ollama_conexao'] = crm_ai_ollama_status($settings);

if ($testar) {
    $cliente = [
        'id' => 'diagnostico',
        'numero' => 'teste',
        'nome' => 'Cliente Teste',
        'status' => 'novo',
        'interesse' => 'Tatuagem de teste',
        'atendente' => 'bot',
        'modo_atendimento' => 'bot',
        'mensagens' => [
            [
                'de' => 'cliente',
                'texto' => 'Oi, queria fazer uma tatuagem no braço. Como funciona?',
                'fromMe' => false,
                'data' => date('Y-m-d H:i:s'),
            ],
        ],
    ];
    $mensagem = $cliente['mensagens'][0];
    $teste = crm_ai_gerar_resposta($cliente, $mensagem, $settings, 35, 48);
    $resultado['teste_ia_local'] = $teste;
    $resultado['observacao'] = 'Este teste usa timeout de 35s e resposta curta. Se der timeout, rode "ollama run qwen3:14b" uma vez no servidor para aquecer o modelo e tente novamente.';
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
