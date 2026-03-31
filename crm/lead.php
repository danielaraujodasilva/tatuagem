<?php 
require 'config.php';
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT *, etapa_funil AS etapa FROM leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    die("<h2 class='text-red-500 p-8'>Lead não encontrado.</h2>");
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
    <div class="max-w-3xl mx-auto p-8">
        <a href="index.php" class="inline-flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-8">
            ← Voltar ao Pipeline
        </a>

        <div class="bg-gray-900 rounded-3xl p-8">
            <div class="flex flex-col md:flex-row justify-between gap-6">
                <div>
                    <h1 class="text-4xl font-bold"><?= htmlspecialchars($lead['nome']) ?></h1>
                    <p class="text-3xl text-emerald-400 mt-2">
                        R$ <?= number_format($lead['valor'] ?? 0, 2, ',', '.') ?>
                    </p>
                </div>
                <?php if (!empty($lead['telefone'])): ?>
                <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['telefone']) ?>" target="_blank"
                   class="bg-green-600 hover:bg-green-700 text-white px-8 py-4 rounded-2xl text-xl flex items-center gap-3 self-start">
                    <i class="fab fa-whatsapp"></i> Abrir WhatsApp
                </a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-10">
                <div>
                    <strong class="text-gray-400 block mb-1">Telefone</strong>
                    <p><?= htmlspecialchars($lead['telefone'] ?? '—') ?></p>
                </div>
                <div>
                    <strong class="text-gray-400 block mb-1">Interesse</strong>
                    <p><?= htmlspecialchars($lead['interesse'] ?? '—') ?></p>
                </div>
                <div>
                    <strong class="text-gray-400 block mb-1">Origem</strong>
                    <p><?= htmlspecialchars($lead['origem'] ?? '—') ?></p>
                </div>
                <div>
                    <strong class="text-gray-400 block mb-1">Status</strong>
                    <p><?= htmlspecialchars($lead['status'] ?? '—') ?></p>
                </div>
                <div>
                    <strong class="text-gray-400 block mb-1">Etapa Atual</strong>
                    <p><?= htmlspecialchars($lead['etapa'] ?? '—') ?></p>
                </div>
            </div>

            <!-- Histórico de Interações -->
            <div class="mt-12">
                <h3 class="text-xl font-semibold mb-4 flex items-center gap-2">
                    <i class="fas fa-history"></i> Histórico de Interações
                </h3>
                <div id="historico" class="space-y-4 min-h-[150px] bg-gray-950 rounded-2xl p-5">
                    <!-- preenchido via JS -->
                </div>
            </div>

            <!-- Adicionar nova interação -->
            <form id="msgForm" class="mt-8 flex gap-3">
                <input type="text" id="msg" placeholder="Escreva uma nova interação (ex: Ligação feita, proposta enviada...)" 
                       class="flex-1 bg-gray-800 border border-gray-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-emerald-500">
                <button type="submit" 
                        class="bg-emerald-600 hover:bg-emerald-700 px-10 rounded-2xl font-medium">Enviar</button>
            </form>
        </div>
    </div>

    <script>
        const leadId = <?= $id ?>;

        function loadHistory() {
            fetch(`handler.php?action=getHistory&id=${leadId}`)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('historico');
                    if (data.error) {
                        container.innerHTML = `<p class="text-red-400">Erro: ${data.error}</p>`;
                        return;
                    }
                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-gray-500 italic">Nenhuma interação registrada ainda.</p>';
                        return;
                    }

                    container.innerHTML = '';
                    data.forEach(item => {
                        container.innerHTML += `
                            <div class="bg-gray-800 rounded-2xl p-5">
                                <small class="text-gray-400">${new Date(item.data).toLocaleString('pt-BR')}</small>
                                <p class="mt-2">${item.mensagem}</p>
                            </div>`;
                    });
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('historico').innerHTML = '<p class="text-red-400">Erro ao carregar histórico.</p>';
                });
        }

        // Enviar nova interação
        document.getElementById('msgForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const msgInput = document.getElementById('msg');
            const mensagem = msgInput.value.trim();

            if (!mensagem) return;

            fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=addInteraction&id=${leadId}&msg=${encodeURIComponent(mensagem)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    msgInput.value = '';
                    loadHistory();           // recarrega o histórico
                } else {
                    alert(data.error || "Erro ao salvar interação");
                }
            })
            .catch(() => alert("Erro de conexão com o servidor"));
        });

        // Carregar histórico ao abrir a página
        window.onload = loadHistory;
    </script>
</body>
</html>