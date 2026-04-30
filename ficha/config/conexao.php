<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$localConfigPath = __DIR__ . '/conexao.local.php';
$localConfig = [];

if (file_exists($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

$config = array_merge([
    'host' => getenv('FICHA_DB_HOST') ?: 'localhost',
    'port' => getenv('FICHA_DB_PORT') ?: 3306,
    'database' => getenv('FICHA_DB_NAME') ?: 'tatuagem_novo',
    'username' => getenv('FICHA_DB_USER') ?: 'tatu_user',
    'password' => getenv('FICHA_DB_PASS') ?: 'Daniel*123',
], $localConfig);

$missing = [];
foreach (['host', 'database', 'username', 'password'] as $field) {
    if (empty($config[$field])) {
        $missing[] = $field;
    }
}

if ($missing) {
    http_response_code(500);
    echo 'Configuracao do banco incompleta em ficha/config/conexao.php. Campos faltando: ' . implode(', ', $missing) . '.';
    exit;
}

$conn = new mysqli(
    $config['host'],
    $config['username'],
    $config['password'],
    $config['database'],
    (int) $config['port']
);

$conn->set_charset('utf8mb4');
