<?php
require 'config.php';

$id = $_GET['id'];
$p = $conn->query("SELECT * FROM pipelines WHERE id=$id")->fetch_assoc();
?>

<form method="POST" action="pipeline_update.php">
    <input type="hidden" name="id" value="<?= $p['id'] ?>">

    <input type="text" name="nome" value="<?= $p['nome'] ?>">
    <input type="number" name="ordem" value="<?= $p['ordem'] ?>">
    <input type="color" name="cor" value="<?= $p['cor'] ?>">

    <button>Salvar</button>
</form>