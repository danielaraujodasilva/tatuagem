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
], $localConfig);

date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');

