<?php include 'config.php'; ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>CRM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#0f172a; color:#fff; }
.column { min-width:250px; margin-right:15px; }
.card { background:#1e293b; border:none; }
.card a { color:#fff; text-decoration:none; }
</style>
</head>
<body>

<div class="container-fluid p-4">
<h2 class="mb-4">Pipeline</h2>

<div class="d-flex overflow-auto">

<?php
$stages = $conn->query("SELECT * FROM pipeline ORDER BY ordem ASC");
while($stage = $stages->fetch_assoc()){
?>
<div class="column">
    <h5 class="mb-3"><?php echo $stage['nome_etapa']; ?></h5>

    <?php
    $leads = $conn->query("SELECT * FROM leads WHERE etapa_funil=".$stage['id']);
    while($lead = $leads->fetch_assoc()){
    ?>
    <div class="card p-2 mb-2">
        <a href="lead.php?id=<?php echo $lead['id']; ?>">
            <strong><?php echo $lead['nome']; ?></strong><br>
            <small><?php echo $lead['telefone']; ?></small>
        </a>
    </div>
    <?php } ?>

</div>
<?php } ?>

</div>
</div>

</body>
</html>
