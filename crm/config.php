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
    'host' => getenv('CRM_DB_HOST') ?: 'localhost',
    'database' => getenv('CRM_DB_NAME') ?: 'crm_simples',
    'username' => getenv('CRM_DB_USER') ?: '',
    'password' => getenv('CRM_DB_PASS') ?: '',
], $localConfig);

foreach (['host', 'database', 'username'] as $field) {
    if ($config[$field] === '') {
        http_response_code(500);
        die('Configuracao do CRM incompleta. Defina CRM_DB_* ou crm/config.local.php.');
    }
}

try {
    $conn = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die('Erro de conexao com o CRM.');
}

$stages = [
    '1' => 'Novo',
    '2' => 'Contato Inicial',
    '3' => 'Reuniao',
    '4' => 'Proposta Enviada',
    '5' => 'Negociacao',
    '6' => 'Fechado - Ganho',
    '7' => 'Fechado - Perdido',
];
