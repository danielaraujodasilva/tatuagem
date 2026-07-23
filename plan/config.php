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
    'database' => getenv('PLAN_DB_NAME') ?: 'plan_financeiro',
    'username' => getenv('PLAN_DB_USER') ?: 'root',
    'password' => getenv('PLAN_DB_PASS') ?: '',
    'timezone' => 'America/Sao_Paulo',
], $localConfig);

date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');

