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
    'php_curl_disponivel' => function_exists('curl_init'),
    'log' => 'crm/data/ia_debug.log',
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
    $resultado['teste_ia_local'] = $teste;
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
