<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Metodo nao permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload) && $_POST) {
    $payload = $_POST;
}

$id = (int)($payload['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Conversa nao informada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$studio = v2_try(static fn() => v2_studio(), null);
if (!$studio instanceof PDO) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'erro' => 'Conversas indisponiveis.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$marcada = v2_try(static function () use ($studio, $id) {
    $existe = $studio->prepare('SELECT id FROM whatsapp_conversations WHERE id = ? LIMIT 1');
    $existe->execute([$id]);
    if (!$existe->fetchColumn()) {
        return false;
    }
    $stmt = $studio->prepare('UPDATE whatsapp_conversations SET nao_lida = 1, nao_lida_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
    return true;
}, false);

if (!$marcada) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'erro' => 'Conversa nao encontrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'id' => $id, 'nao_lida' => 1], JSON_UNESCAPED_UNICODE);
