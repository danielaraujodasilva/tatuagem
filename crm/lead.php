<?php include 'config.php'; 
$id = $_GET['id'];
$lead = $conn->query("SELECT * FROM leads WHERE id=$id")->fetch_assoc();
?>
<h1><?php echo $lead['nome']; ?></h1>
<p>Telefone: <?php echo $lead['telefone']; ?></p>

<h2>Interações</h2>
<?php
$res = $conn->query("SELECT * FROM interacoes WHERE lead_id=$id");
while($i = $res->fetch_assoc()){
    echo "<p>".$i['mensagem']."</p>";
}
?>

<form method="post">
<textarea name="msg"></textarea><br>
<button>Salvar</button>
</form>

<?php
if($_POST){
    $msg = $_POST['msg'];
    $conn->query("INSERT INTO interacoes (lead_id,mensagem,tipo,data) VALUES ($id,'$msg','saida',NOW())");
    header("Location: lead.php?id=$id");
}
?>

<a href="https://wa.me/<?php echo $lead['telefone']; ?>" target="_blank">WhatsApp</a>
