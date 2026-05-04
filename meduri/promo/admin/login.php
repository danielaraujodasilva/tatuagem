<?php
session_start();
if($_POST){
    $adminUser = getenv('PROMO_ADMIN_USER') ?: '';
    $adminPassHash = getenv('PROMO_ADMIN_PASS_HASH') ?: '';
    $userOk = $adminUser !== '' && hash_equals($adminUser, (string)($_POST['user'] ?? ''));
    $passOk = $adminPassHash !== '' && password_verify((string)($_POST['pass'] ?? ''), $adminPassHash);

    if($userOk && $passOk){
        $_SESSION['logado'] = true;
        header('Location: dashboard.php');
        exit;
    }
}
?>
<form method="post">
<input name="user" placeholder="user">
<input name="pass" type="password">
<button>Entrar</button>
</form>
