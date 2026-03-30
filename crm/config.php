<?php
$conn = new mysqli("localhost", "root", "", "crm_simples");
if ($conn->connect_error) {
    die("Erro: " . $conn->connect_error);
}
?>
