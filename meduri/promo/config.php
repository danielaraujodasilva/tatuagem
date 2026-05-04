<?php
// CONFIGURAÇÕES GERAIS

// Banco de dados
define('DB_HOST', getenv('PROMO_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('PROMO_DB_NAME') ?: 'tattoo');
define('DB_USER', getenv('PROMO_DB_USER') ?: '');
define('DB_PASS', getenv('PROMO_DB_PASS') ?: '');

// Mercado Pago (placeholder)
define('MP_ACCESS_TOKEN', getenv('PROMO_MP_ACCESS_TOKEN') ?: '');

// Email SMTP (placeholder)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', getenv('PROMO_SMTP_USER') ?: '');
define('SMTP_PASS', getenv('PROMO_SMTP_PASS') ?: '');
define('SMTP_PORT', (int)(getenv('PROMO_SMTP_PORT') ?: 587));

// URL base
define('BASE_URL', getenv('PROMO_BASE_URL') ?: '');
?>
