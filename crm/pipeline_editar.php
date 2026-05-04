<?php require 'config.php'; ?>
<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
?>


<?php
$id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM pipelines WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();

$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("Etapa não encontrada");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Editar Etapa</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-950 text-gray-100">

<div class="max-w-xl mx-auto p-6">

    <!-- HEADER -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">✏️ Editar Etapa</h1>

        <a href="configuracoes.php" 
           class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-xl">
            Voltar
        </a>
    </div>

    <!-- FORM -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
        <form method="POST" action="pipeline_update.php" class="space-y-5">

            <input type="hidden" name="id" value="<?= $p['id'] ?>">

            <div>
                <label class="block text-sm mb-1">Nome</label>
                <input type="text" name="nome" value="<?= $p['nome'] ?>" required
                       class="w-full bg-gray-800 border border-gray-700 px-4 py-3 rounded-xl">
            </div>

            <div>
                <label class="block text-sm mb-1">Ordem</label>
                <input type="number" name="ordem" value="<?= $p['ordem'] ?>"
                       class="w-full bg-gray-800 border border-gray-700 px-4 py-3 rounded-xl">
            </div>

            <div>
                <label class="block text-sm mb-1">Cor</label>
                <input type="color" name="cor" value="<?= $p['cor'] ?>"
                       class="w-full h-12 bg-gray-800 border border-gray-700 rounded-xl">
            </div>

            <button class="w-full bg-emerald-600 hover:bg-emerald-700 py-3 rounded-xl font-semibold">
                Salvar Alterações
            </button>
        </form>
    </div>

</div>

</body>
</html>