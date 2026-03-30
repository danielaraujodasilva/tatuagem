<?php include 'config.php'; ?>
<h1>Pipeline</h1>
<?php
$stages = $conn->query("SELECT * FROM pipeline ORDER BY ordem ASC");
while($stage = $stages->fetch_assoc()){
    echo "<h2>".$stage['nome_etapa']."</h2>";
    $leads = $conn->query("SELECT * FROM leads WHERE etapa_funil=".$stage['id']);
    while($lead = $leads->fetch_assoc()){
        echo "<div style='border:1px solid #ccc;padding:5px;margin:5px;'>
        <b>".$lead['nome']."</b><br>
        ".$lead['telefone']."<br>
        <a href='lead.php?id=".$lead['id']."'>Ver</a>
        </div>";
    }
}
?>
