<?php
session_start();
if(!isset($_SESSION['logado'])) die('Acesso negado');
?>
<h1>Painel</h1>
<p>Pedidos aparecerão aqui</p>