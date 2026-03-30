<?php 
require 'config.php';
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    die("Lead não encontrado.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lead['nome']) ?> - Detalhes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-950 text-gray-100">
    <div class="max-w-2xl mx-auto p-8">
        <a href="index.php" class="text-emerald-400 hover:text-emerald-300 mb-6 inline-flex items-center gap-2">
            ← Voltar ao Pipeline
        </a>
        
        <div class="bg-gray-900 rounded-3xl p-8">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-4xl font-bold"><?= htmlspecialchars($lead['nome']) ?></h1>
                    <p class="text-2xl text-emerald-400 mt-1">
                        R$ <?= number_format($lead['valor'] ?? 0, 2, ',', '.') ?>
                    </p>
                </div>
                <?php if (!empty($lead['telefone'])): ?>
                <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['telefone']) ?>" target="_blank"
                   class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-2xl text-xl flex items-center gap-3">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-6 mt-10 text-lg">
                <div><strong>Telefone:</strong> <?= htmlspecialchars($lead['telefone'] ?? '—') ?></div>
                <!-- Email removido porque a coluna não existe na tabela -->
            </div>

            <div class="mt-12">
                <h3 class="text-xl font-semibold mb-4 flex items-center gap-2">
                    <i class="fas fa-history"></i> Histórico de Interações
                </h3>
                <div id="historico" class="space-y-4 min-h-[200px]"></div>
            </div>

            <!-- Adicionar interação -->
            <form id="msgForm" class="mt-10 flex gap-3">
                <input type="text" id="msg" placeholder="Escreva uma interação..." 
                       class="flex-1 bg-gray-800 border border-gray-700 rounded-2xl px-5 py-4 focus:outline-none">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 px-8 rounded-2xl font-medium">Enviar</button>
            </form>
        </div>
    </div>

    <script>
        function loadHistory() {
            fetch(`handler.php?action=getHistory&id=<?= $id ?>`)
                .then(r => r.json())
                .then(msgs => {
                    const div = document.getElementById('historico');
                    if (msgs.length === 0) {
                        div.innerHTML = '<p class="text-gray-500">Nenhuma interação ainda.</p>';
                        return;
                    }
                    div.innerHTML = '';
                    msgs.forEach(m => {
                        div.innerHTML += `
                            <div class="bg-gray-800 rounded-2xl p-4">
                                <small class="text-gray-400">${new Date(m.data).toLocaleString('pt-BR')}</small>
                                <p class="mt-1">${m.mensagem}</p>
                            </div>`;
                    });
                })
                .catch(() => {
                    document.getElementById('historico').innerHTML = '<p class="text-red-400">Erro ao carregar histórico.</p>';
                });
        }

        document.getElementById('msgForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const msgInput = document.getElementById('msg');
            const msg = msgInput.value.trim();
            if (!msg) return;

            fetch('handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=addInteraction&id=<?= $id ?>&msg=${encodeURIComponent(msg)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    msgInput.value = '';
                    loadHistory();
                } else {
                    alert(data.error || "Erro ao salvar interação");
                }
            })
            .catch(() => alert("Erro de conexão"));
        });

        window.onload = loadHistory;
    </script>
</body>
</html>