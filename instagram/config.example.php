<?php
/**
 * Copie este arquivo para:
 * instagram/config.local.php
 *
 * Depois coloque o token real lá no config.local.php.
 * Nunca commite config.local.php, porque token em repo público é pedir pra tomar rasteira da Meta.
 */

return [
    // Token gerado no painel da Meta/Instagram.
    'access_token' => 'COLE_SEU_TOKEN_AQUI',

    // Quantidade máxima de posts retornados pelo feed público do site.
    'limit' => 12,

    // Tempo de cache em segundos. 21600 = 6 horas.
    'cache_ttl' => 21600,
];
