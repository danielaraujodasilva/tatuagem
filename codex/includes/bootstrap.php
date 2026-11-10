<?php
declare(strict_types=1);

/*
 * Mesmo login/senha e mesma sessao do /plan (Plan Financeiro).
 * Ao incluir o bootstrap do Plan ganhamos db(), current_user(),
 * csrf_token(), verify_csrf(), json_response() e json_input().
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

require_once __DIR__ . '/../../plan/includes/bootstrap.php';