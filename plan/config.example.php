<?php
return [
    'host' => getenv('PLAN_DB_HOST') ?: 'localhost',
    'port' => getenv('PLAN_DB_PORT') ?: '',
    'unix_socket' => getenv('PLAN_DB_SOCKET') ?: '',
    'database' => getenv('PLAN_DB_NAME') ?: 'plan_financeiro',
    'username' => getenv('PLAN_DB_USER') ?: 'root',
    'password' => getenv('PLAN_DB_PASS') ?: '',
    'timezone' => 'America/Sao_Paulo',
    'debug' => false,
    'bank_sync' => [
        'enabled' => false,
        'provider' => 'pluggy',
        'lookback_days' => 365,
        'pluggy' => [
            'client_id' => '',
            'client_secret' => '',
            'item_ids' => [],
        ],
    ],
];
