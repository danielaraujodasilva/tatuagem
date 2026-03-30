<?php
require 'config.php';
echo "<h1>Teste de Conexão</h1>";

try {
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Conexão OK!<br>";
    echo "Tabelas encontradas: " . implode(", ", $tables);
    
    if (in_array('leads', $tables)) {
        $count = $conn->query("SELECT COUNT(*) FROM leads")->fetchColumn();
        echo "<br>Leads no banco: <b>$count</b>";
    } else {
        echo "<br>❌ Tabela 'leads' NÃO existe!";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>