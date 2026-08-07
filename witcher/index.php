<?php

declare(strict_types=1);

$htmlFile = __DIR__ . DIRECTORY_SEPARATOR . 'index.html';
$html = file_get_contents($htmlFile);

if ($html === false) {
    http_response_code(500);
    exit('Nao foi possivel carregar o painel Witcher.');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo $html;
