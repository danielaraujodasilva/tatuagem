<?php
require 'config.php';

$id = $_GET['id'];

// move leads pra etapa 1 (ou muda se quiser)
$conn->query("UPDATE leads SET etapa=1 WHERE etapa=$id");

$conn->query("DELETE FROM pipelines WHERE id=$id");

header("Location: configuracoes.php");