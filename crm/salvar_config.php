<?php
require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/../includes/system_settings.php';

$data = [
    'mensagem_trigger' => trim((string)($_POST['mensagem_trigger'] ?? 'oi')),
    'valor_pomada_anestesica' => max(0, (float)str_replace(',', '.', (string)($_POST['valor_pomada_anestesica'] ?? '100'))),
];

system_settings_save($data);

$query = !empty($_POST['embed']) ? '?embed=1&v=20260505-financeiro' : '?v=20260505-financeiro';
header("Location: configuracoes.php" . $query);
