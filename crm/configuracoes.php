<?php require 'config.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Configurações</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 p-10">

<h1 class="text-2xl font-bold mb-6">⚙️ Pipeline</h1>

<form method="POST" action="pipeline_salvar.php" class="flex gap-3 mb-6">
    <input type="text" name="nome" placeholder="Nome da etapa" required class="bg-gray-800 px-4 py-2 rounded">
    <input type="number" name="ordem" placeholder="Ordem" class="bg-gray-800 px-4 py-2 rounded w-24">
    <input type="color" name="cor" class="h-10 w-16">
    <button class="bg-emerald-600 px-4 py-2 rounded">Adicionar</button>
</form>

<?php
$res = $conn->query("SELECT * FROM pipelines ORDER BY ordem");
while($p = $res->fetch_assoc()):
?>

<div class="bg-gray-800 p-4 mb-2 rounded flex justify-between items-center">
    <div>
        <span style="color: <?= $p['cor'] ?>">⬤</span>
        <?= $p['nome'] ?>
    </div>

    <div class="flex gap-2">
        <a href="pipeline_editar.php?id=<?= $p['id'] ?>" class="bg-yellow-500 px-3 py-1 rounded">Editar</a>
        <a href="pipeline_deletar.php?id=<?= $p['id'] ?>" class="bg-red-500 px-3 py-1 rounded">Excluir</a>
    </div>
</div>

<?php endwhile; ?>

</body>
</html>