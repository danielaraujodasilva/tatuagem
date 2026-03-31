<?php 
require 'config.php';
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT *, etapa_funil AS etapa FROM leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    die("<h2 class='text-red-500 p-10 text-center'>Lead não encontrado.</h2>");
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
    <div class="max-w-4xl mx-auto p-8">
        <a href="index.php" class="inline-flex items-center gap-2 text-emerald-400 hover:text-emerald-300 mb-8 font-medium">
            ← Voltar ao Pipeline
        </a>

        <div class="bg-gray-900 rounded-3xl p-8">
            <!-- Cabeçalho -->
            <div class="flex flex-col md:flex-row justify-between gap-6 border-b border-gray-700 pb-8">
                <div>
                    <h1 class="text-4xl font-bold"><?= htmlspecialchars($lead['nome']) ?></h1>
                    <p class="text-3xl text-emerald-400 mt-3">
                        R$ <?= number_format($lead['valor'] ?? 0, 2, ',', '.') ?>
                    </p>
                </div>
                <?php if (!empty($lead['telefone'])): ?>
                <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['telefone']) ?>" target="_blank"
                   class="bg-green-600 hover:bg-green-700 px-8 py-4 rounded-2xl text-xl flex items-center gap-3 self-start md:self-center">
                    <i class="fab fa-whatsapp text-2xl"></i> 
                    <span>WhatsApp</span>
                </a>
                <?php endif; ?>
            </div>

            <!-- Informações do Lead -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-12 gap-y-8 mt-10">
                <div>
                    <p class="text-gray-400 text-sm">Telefone</p>
                    <p class="text-lg"><?= htmlspecialchars($lead['telefone'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Interesse</p>
                    <p class="text-lg"><?= htmlspecialchars($lead['interesse'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Origem</p>
                    <p class="text-lg"><?= htmlspecialchars($lead['origem'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Status</p>
                    <p class="text-lg"><?= htmlspecialchars($lead['status'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Etapa Atual</p>
                    <p class="text-lg"><?= htmlspecialchars($lead['etapa'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Último Contato</p>
                    <p class="text-lg"><?= $lead['data_ultimo_contato'] ? date('d/m/Y', strtotime($lead['data_ultimo_contato'])) : '—' ?></p>
                </div>
                <div>
                    <p class="text-gray-400 text-sm">Data de Cadastro</p>
                    <p class="text-lg"><?= date('d/m/Y H:i', strtotime($lead['created_at'])) ?></p>
                </div>
            </div>

            <!-- Histórico de Interações -->
            <div class="mt-14">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-semibold flex items-center gap-3">
                        <i class="fas fa-history"></i> Histórico de Interações
                    </h3>
                </div>

                <div id="historico" class="space-y-5 bg-gray-950 rounded-2xl p-6 min-h-[200px]">
                    <!-- JS preenche aqui -->
                </div>

                <!-- Formulário para nova interação -->
                <div class="mt-8 bg-gray-900 rounded-3xl p-6">
                    <h4 class="font-medium mb-4">Adicionar Nova Interação</h4>
                    <form id="msgForm" class="space-y-4">
                        <div>
                            <label class="block text-sm mb-2">Tipo de Interação</label>
                            <select id="tipo" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                                <option value="Ligação">📞 Ligação</option>
                                <option value="WhatsApp">💬 WhatsApp</option>
                                <option value="Reunião">🤝 Reunião</option>
                                <option value="Proposta">📄 Proposta Enviada</option>
                                <option value="Negociação">💰 Negociação</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-2">Descrição da Interação</label>
                            <textarea id="msg" rows="3" placeholder="Descreva o que foi conversado..." 
                                      class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-4 focus:outline-none"></textarea>
                        </div>
                        <button type="submit" 
                                class="w-full bg-emerald-600 hover:bg-emerald-700 py-4 rounded-2xl font-semibold">
                            Registrar Interação
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const leadId = <?= $id ?>;

        function loadHistory() {
            fetch(`handler.php?action=getHistory&id=${leadId}`)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('historico');
                    
                    if (data.length === 0) {
                        container.innerHTML = `<p class="text-gray-500 italic text-center py-8">Nenhuma interação registrada ainda.</p>`;
                        return;
                    }

                    container.innerHTML = '';
                    data.forEach(item => {
                        const tipoIcon = {
                            'Ligação': '📞',
                            'WhatsApp': '💬',
                            'Reunião': '🤝',
                            'Proposta': '📄',
                            'Negociação': '💰'
                        }[item.tipo] || '•';

                        container.innerHTML += `
                            <div class="bg-gray-800 rounded-2xl p-6">
                                <div class="flex justify-between">
                                    <span class="text-lg">${tipoIcon} <strong>${item.tipo}</strong></span>
                                    <small class="text-gray-400">${new Date(item.data).toLocaleString('pt-BR')}</small>
                                </div>
                                <p class="mt-3 text-gray-200">${item.mensagem}</p>
                            </div>`;
                    });
                })
                .catch(() => {
                    document.getElementById('historico').innerHTML = `<p class="text-red-400">Erro ao carregar histórico.</p>`;
                });
        }

        // Enviar nova interação
        document.getElementById('msgForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const tipo = document.getElementById('tipo').value;
            const msg = document.getElementById('msg').value.trim();

            if (!msg) {
                alert("Escreva uma descrição da interação");
                return;
            }

            fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=addInteraction&id=${leadId}&tipo=${encodeURIComponent(tipo)}&msg=${encodeURIComponent(msg)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    document.getElementById('msg').value = '';
                    loadHistory();
                } else {
                    alert(data.error || "Erro ao registrar interação");
                }
            })
            .catch(() => alert("Erro de conexão"));
        });

        // Carregar ao abrir
        window.onload = loadHistory;
    </script>
</body>
</html>