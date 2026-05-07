<?php
require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/../includes/system_settings.php';

$current = system_settings_load();
$openaiKey = trim((string)($_POST['openai_api_key'] ?? ''));
if ($openaiKey === '' && empty($_POST['openai_clear_key'])) {
    $openaiKey = (string)($current['openai_api_key'] ?? '');
}

$data = [
    'mensagem_trigger' => trim((string)($_POST['mensagem_trigger'] ?? 'oi')),
    'valor_pomada_anestesica' => max(0, (float)str_replace(',', '.', (string)($_POST['valor_pomada_anestesica'] ?? '100'))),
    'openai_enabled' => !empty($_POST['openai_enabled']),
    'openai_api_key' => !empty($_POST['openai_clear_key']) ? '' : $openaiKey,
    'openai_model' => trim((string)($_POST['openai_model'] ?? 'gpt-5-mini')) ?: 'gpt-5-mini',
    'openai_max_history' => max(4, min(60, (int)($_POST['openai_max_history'] ?? 20))),
    'openai_business_prompt' => trim((string)($_POST['openai_business_prompt'] ?? '')),
];

system_settings_save($data);

$query = !empty($_POST['embed']) ? '?embed=1&v=20260505-financeiro' : '?v=20260505-financeiro';
header("Location: configuracoes.php" . $query);
