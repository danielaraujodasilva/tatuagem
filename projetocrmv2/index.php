<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** Consulta segura: nunca derruba a tela por causa de tabela ausente. */
function v2_q($pdo, string $sql, array $fallback = []): array
{
    $rows = v2_try(static function () use ($pdo, $sql) {
        if (!$pdo instanceof PDO) {
            return [];
        }
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }, $fallback);

    return is_array($rows) ? $rows : $fallback;
}

function v2_q1($pdo, string $sql, string $campo = 'total', $default = 0)
{
    $rows = v2_q($pdo, $sql);
    return $rows[0][$campo] ?? $default;
}

$user = require_staff();

$page = (string)($_GET['page'] ?? 'hoje');
$page = preg_replace('/[^a-z_]/', '', $page) ?: 'hoje';
if (!array_key_exists($page, v2_paginas())) {
    $page = 'hoje';
}

$arquivo = __DIR__ . '/views/' . $page . '.php';
if (is_file($arquivo)) {
    require $arquivo;
} else {
    http_response_code(404);
    echo 'Tela nao encontrada.';
}
