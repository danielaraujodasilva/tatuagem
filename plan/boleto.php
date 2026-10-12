<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// Entrega o PDF do boleto coletado por e-mail. Fica atras do login do Plan,
// porque o PDF tem os dados da conta (nome, endereco e codigo de pagamento).
$user = current_user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Entre no Plan para ver o boleto.';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT description, boleto_arquivo FROM recurring_rules WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$regra = $stmt->fetch() ?: [];
$arquivo = basename((string)($regra['boleto_arquivo'] ?? ''));
$caminho = __DIR__ . '/storage/boletos/' . $arquivo;

if ($id <= 0 || $arquivo === '' || !is_file($caminho)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nenhum boleto guardado para esta conta.';
    exit;
}

$nome = 'boleto-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string)($regra['description'] ?? 'conta')) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nome . '"');
header('Content-Length: ' . (string)filesize($caminho));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($caminho);