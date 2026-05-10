<?php
require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/../includes/system_settings.php';
require_once __DIR__ . '/../includes/team_settings.php';

$tattooArtistsPayload = array_key_exists('tattoo_artists_payload', $_POST) ? $_POST['tattoo_artists_payload'] : null;
$attendantsPayload = array_key_exists('attendants_payload', $_POST) ? $_POST['attendants_payload'] : null;

$data = [
    'mensagem_trigger' => trim((string)($_POST['mensagem_trigger'] ?? 'oi')),
    'valor_pomada_anestesica' => max(0, (float)str_replace(',', '.', (string)($_POST['valor_pomada_anestesica'] ?? '100'))),
    'openai_enabled' => !empty($_POST['openai_enabled']),
    'ai_provider' => 'ollama',
    'openai_api_key' => '',
    'openai_model' => '',
    'ollama_url' => rtrim(trim((string)($_POST['ollama_url'] ?? 'http://localhost:11434')), '/') ?: 'http://localhost:11434',
    'ollama_model' => trim((string)($_POST['ollama_model'] ?? 'qwen3:14b')) ?: 'qwen3:14b',
    'ai_timeout_seconds' => max(20, min(180, (int)($_POST['ai_timeout_seconds'] ?? 120))),
    'ai_num_predict' => max(40, min(450, (int)($_POST['ai_num_predict'] ?? 220))),
    'data_ai_model' => trim((string)($_POST['data_ai_model'] ?? 'qwen3:14b')) ?: 'qwen3:14b',
    'data_ai_timeout_seconds' => max(30, min(420, (int)($_POST['data_ai_timeout_seconds'] ?? 240))),
    'data_ai_num_predict' => max(120, min(6000, (int)($_POST['data_ai_num_predict'] ?? 2400))),
    'openai_max_history' => max(4, min(60, (int)($_POST['openai_max_history'] ?? 20))),
    'openai_business_prompt' => trim((string)($_POST['openai_business_prompt'] ?? '')),
    'tattoo_artists' => $tattooArtistsPayload !== null
        ? (team_payload_people($tattooArtistsPayload, 'tattoo_artist') ?: team_default_tattoo_artists())
        : team_tattoo_artists(),
    'attendants' => $attendantsPayload !== null
        ? (team_payload_people($attendantsPayload, 'attendant') ?: team_default_attendants())
        : team_attendants(),
];

system_settings_save($data);

$query = !empty($_POST['embed']) ? '?embed=1&v=20260505-financeiro' : '?v=20260505-financeiro';
header("Location: configuracoes.php" . $query);
