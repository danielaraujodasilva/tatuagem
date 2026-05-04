<?php
require_once __DIR__ . '/../../../auth/auth.php';
require_staff();
header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_FILES['referencia']) || !is_uploaded_file($_FILES['referencia']['tmp_name'])) {
        throw new RuntimeException('Nenhum arquivo recebido.');
    }

    $file = $_FILES['referencia'];
    $maxBytes = 8 * 1024 * 1024;

    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('A referencia precisa ter ate 8 MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Envie JPG, PNG, WEBP, GIF ou PDF.');
    }

    $dir = __DIR__ . '/../../uploads/referencias';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $name = uniqid('ref_', true) . '.' . $allowed[$mime];
    $target = $dir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Nao consegui salvar a referencia.');
    }

    echo json_encode([
        'status' => 'success',
        'path' => 'uploads/referencias/' . $name,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
