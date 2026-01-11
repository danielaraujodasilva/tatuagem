<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$servername = "br926.hostgator.com.br";
$username = "fran5062_clientes";
$password = "Clientes*123";
$dbname = "fran5062_tatuagem";
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
} catch (mysqli_sql_exception $e) {
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
}
?>
