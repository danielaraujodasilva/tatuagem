<?php
declare(strict_types=1);
/**
 * Salva os fatos do estúdio editados na tela Estúdio. E o que a Irene usa
 * para responder os clientes.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = $_POST;
}

$texto = (string)($in['texto'] ?? '');
$fatos = preg_split('/\r\n|\r|\n/', $texto) ?: [];

if (!v2_estudio_gravar($fatos)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'escreva pelo menos um fato'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'fatos' => v2_estudio_fatos()], JSON_UNESCAPED_UNICODE);