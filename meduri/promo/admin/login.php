<?php
session_start();
if($_POST){
    if($_POST['user']=='admin' && $_POST['pass']=='123'){
        $_SESSION['logado'] = true;
        header('Location: dashboard.php');
    }
}
?>
<form method="post">
<input name="user" placeholder="user">
<input name="pass" type="password">
<button>Entrar</button>
</form>