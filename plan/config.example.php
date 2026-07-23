<?php
return [
    'host' => getenv('PLAN_DB_HOST') ?: 'localhost',
    'database' => getenv('PLAN_DB_NAME') ?: 'plan_financeiro',
    'username' => getenv('PLAN_DB_USER') ?: 'seu_usuario',
    'password' => getenv('PLAN_DB_PASS') ?: 'sua_senha',
    'timezone' => 'America/Sao_Paulo',
];
