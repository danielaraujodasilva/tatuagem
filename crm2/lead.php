<?php include 'config.php';
$id = $_GET['id'];
$lead = $conn->query("SELECT * FROM leads WHERE id=$id")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Lead</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#0f172a; color:#fff; }
.chat { background:#1e293b; padding:10px; border-radius:10px; }
</style>
</head>
<body>

<div class="container p-4">

<h2><?php echo $lead['nome']; ?></h2>
<p><?php echo $lead['telefone']; ?></p>

<a class="btn btn-success mb-3" target="_blank"
href="https://wa.me/<?php echo $lead['telefone']; ?>">
WhatsApp
</a>

<div class="chat mb-3">
<h5>Histórico</h5>

<?php
$res = $conn->query("SELECT * FROM interacoes WHERE lead_id=$id ORDER BY data DESC");
while($i = $res->fetch_assoc()){
    echo "<div class='mb-2'><small>".$i['data']."</small><br>".$i['mensagem']."</div>";
}
?>

</div>

<form method="post">
<textarea name="msg" class="form-control mb-2" placeholder="Digite..."></textarea>
<button class="btn btn-primary">Enviar</button>
</form>

<?php
if($_POST){
    $msg = $_POST['msg'];
    $conn->query("INSERT INTO interacoes (lead_id,mensagem,tipo,data) VALUES ($id,'$msg','saida',NOW())");
    header("Location: lead.php?id=$id");
}
?>

</div>

</body>
</html>
