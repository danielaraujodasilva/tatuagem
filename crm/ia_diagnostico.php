<?php
require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/openai_assistant.php';

header('Content-Type: application/json; charset=utf-8');

$settings = system_settings_load();
$testar = !empty($_GET['testar']);

$resultado = [
    'ok' => true,
    'openai_enabled' => !empty($settings['openai_enabled']),
    'openai_key_configurada' => trim((string)($settings['openai_api_key'] ?? '')) !== '',
    'openai_model' => (string)($settings['openai_model'] ?? ''),
    'openai_max_history' => (int)($settings['openai_max_history'] ?? 0),
    'php_curl_disponivel' => function_exists('curl_init'),
    'log' => 'crm/data/openai_debug.log',
];

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
    $teste = crm_ai_gerar_resposta($cliente, $mensagem, $settings);
    $resultado['teste_openai'] = $teste;
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
