<?php
return [
    'host' => getenv('PLAN_DB_HOST') ?: 'localhost',
    'database' => getenv('PLAN_DB_NAME') ?: 'plan_financeiro',
    'username' => getenv('PLAN_DB_USER') ?: 'root',
    'password' => getenv('PLAN_DB_PASS') ?: '',
    'timezone' => 'America/Sao_Paulo',
];
