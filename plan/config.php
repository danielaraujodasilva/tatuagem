<?php
$localConfigPath = __DIR__ . '/config.local.php';
$localConfig = [];

if (file_exists($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

$config = array_merge([
    'host' => getenv('PLAN_DB_HOST') ?: 'localhost',
    'port' => getenv('PLAN_DB_PORT') ?: '',
    'unix_socket' => getenv('PLAN_DB_SOCKET') ?: '',
    'database' => getenv('PLAN_DB_NAME') ?: 'plan_financeiro',
    'username' => getenv('PLAN_DB_USER') ?: 'root',
    'password' => getenv('PLAN_DB_PASS') ?: '',
    'timezone' => 'America/Sao_Paulo',
    'debug' => filter_var(getenv('PLAN_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'bank_sync' => [
        'enabled' => filter_var(getenv('PLAN_BANK_SYNC_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
        'provider' => 'pluggy',
        'lookback_days' => max(30, min(365, (int)(getenv('PLAN_BANK_SYNC_LOOKBACK_DAYS') ?: 365))),
        'pluggy' => [
            'client_id' => getenv('PLAN_PLUGGY_CLIENT_ID') ?: '',
            'client_secret' => getenv('PLAN_PLUGGY_CLIENT_SECRET') ?: '',
            'item_ids' => array_values(array_filter(array_map('trim', explode(',', getenv('PLAN_PLUGGY_ITEM_IDS') ?: getenv('PLAN_PLUGGY_ITEM_ID') ?: '')))),
        ],
    ],
], $localConfig);

date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');

