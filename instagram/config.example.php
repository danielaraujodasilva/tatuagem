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

    // Espelha imagens/capas do Instagram para a pasta /galeria.
    // O index.php atual já lê essa pasta, então isso troca a galeria sem mexer no layout.
    'mirror_to_gallery' => true,

    // true = move as imagens antigas da /galeria para uma pasta _backup_local_* na primeira sincronização.
    // false = mantém imagens antigas junto com as do Instagram.
    'replace_local_gallery' => true,

    // Timeout para baixar cada imagem/capa do Instagram.
    'download_timeout' => 30,
];
