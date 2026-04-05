<?php require 'config.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Configurações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="bg-gray-950 text-gray-100">

<div class="max-w-5xl mx-auto p-6">

    <!-- HEADER -->
    <div class="flex justify-between items-center mb-8">
        <div class="flex items-center gap-3">
            <i class="fas fa-gear text-2xl text-emerald-500"></i>
            <h1 class="text-2xl font-bold">Configurações do Pipeline</h1>
        </div>

        <a href="index.php" 
           class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-xl flex items-center gap-2">
            <i class="fas fa-arrow-left"></i>
            Voltar
        </a>
    </div>

    <!-- FORM -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 mb-8">
        <h2 class="text-lg font-semibold mb-4">Nova Etapa</h2>

        <form method="POST" action="pipeline_salvar.php" 
              class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

            <input type="text" name="nome" placeholder="Nome da etapa" required 
                   class="bg-gray-800 border border-gray-700 px-4 py-3 rounded-xl col-span-2">

            <input type="number" name="ordem" placeholder="Ordem" 
                   class="bg-gray-800 border border-gray-700 px-4 py-3 rounded-xl">

            <input type="color" name="cor" 
                   class="h-[48px] w-full rounded-xl border border-gray-700 bg-gray-800">

            <button class="bg-emerald-600 hover:bg-emerald-700 px-4 py-3 rounded-xl font-semibold col-span-1 md:col-span-4">
                + Adicionar Etapa
            </button>
        </form>
    </div>

    <!-- LISTA -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
        <h2 class="text-lg font-semibold mb-4">Etapas (arraste para ordenar)</h2>

        <div id="pipelineList" class="space-y-3">
        <?php
        $res = $conn->query("SELECT * FROM pipelines ORDER BY ordem");

        while($p = $res->fetch(PDO::FETCH_ASSOC)):
        ?>

        <div data-id="<?= $p['id'] ?>" 
             class="bg-gray-800 hover:bg-gray-700 p-4 rounded-xl flex justify-between items-center cursor-move transition">

            <div class="flex items-center gap-3">
                <i class="fas fa-grip-vertical text-gray-500"></i>
                <span style="color: <?= $p['cor'] ?>">⬤</span>
                <span class="font-medium"><?= $p['nome'] ?></span>
            </div>

            <div class="flex gap-2">
                <a href="pipeline_editar.php?id=<?= $p['id'] ?>" 
                   class="bg-yellow-500 hover:bg-yellow-600 px-3 py-1 rounded-lg text-sm">
                    Editar
                </a>
                <a href="pipeline_deletar.php?id=<?= $p['id'] ?>" 
                   class="bg-red-500 hover:bg-red-600 px-3 py-1 rounded-lg text-sm">
                    Excluir
                </a>
            </div>
        </div>

        <?php endwhile; ?>
        </div>
    </div>

  
</div>

<script>
Sortable.create(document.getElementById('pipelineList'), {
    animation: 150,
    ghostClass: 'opacity-50',

    onEnd: function () {

        let ordem = [];
        document.querySelectorAll('#pipelineList > div').forEach((el, index) => {
            ordem.push({
                id: el.dataset.id,
                ordem: index + 1
            });
        });

        fetch('pipeline_ordem.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(ordem)
        });
    }
});
</script>

</body>
</html>