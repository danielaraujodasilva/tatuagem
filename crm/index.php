<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM - Pipeline</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .kanban-column { min-height: 500px; }
        .card { transition: all 0.2s; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.2); }
    </style>
</head>
<body class="bg-gray-950 text-gray-100">
    <div class="max-w-screen-2xl mx-auto">
        <!-- HEADER -->
        <div class="bg-gray-900 border-b border-gray-800 px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <i class="fas fa-chart-simple text-3xl text-emerald-500"></i>
                <h1 class="text-3xl font-bold">CRM Pipeline</h1>
            </div>
            <div class="flex items-center gap-4">
                <input id="search" type="text" placeholder="🔎 Buscar lead..." 
                       class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:border-emerald-500 w-80">
                <button onclick="newLead()" 
                        class="bg-emerald-600 hover:bg-emerald-700 px-6 py-3 rounded-lg font-semibold flex items-center gap-2 transition">
                    <i class="fas fa-plus"></i> Novo Lead
                </button>
            </div>
        </div>

        <!-- PIPELINE -->
        <div class="p-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-6" id="pipeline">
            <?php foreach($stages as $key => $name): ?>
                <div class="bg-gray-900 rounded-2xl p-4 border border-gray-800" data-stage="<?= $key ?>">
                    <div class="flex justify-between items-center mb-4 px-2">
                        <h2 class="font-bold text-lg"><?= $name ?></h2>
                        <span id="count-<?= $key ?>" class="bg-gray-800 text-xs px-3 py-1 rounded-full">0</span>
                    </div>
                    <div class="kanban-column space-y-3 min-h-[500px]" id="column-<?= $key ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MODAL NOVO/EDITAR LEAD -->
    <div id="modal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50">
        <div class="bg-gray-900 rounded-3xl w-full max-w-lg mx-4 p-8">
            <h3 id="modalTitle" class="text-2xl font-bold mb-6">Novo Lead</h3>
            <form id="leadForm" onsubmit="saveLead(event)">
                <input type="hidden" id="leadId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm mb-1">Nome *</label>
                        <input type="text" id="nome" required class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Telefone (com DDD) *</label>
                            <input type="tel" id="telefone" required class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Email</label>
                            <input type="email" id="email" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Valor estimado (R$)</label>
                        <input type="number" id="valor" step="0.01" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Etapa</label>
                        <select id="etapa" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3">
                            <?php foreach($stages as $k => $n): ?>
                                <option value="<?= $k ?>"><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Observação</label>
                        <textarea id="observacao" rows="3" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3"></textarea>
                    </div>
                </div>
                <div class="flex gap-3 mt-8">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 py-4 text-gray-400 hover:text-white font-medium">Cancelar</button>
                    <button type="submit" 
                            class="flex-1 bg-emerald-600 hover:bg-emerald-700 py-4 rounded-2xl font-semibold">Salvar Lead</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentEditId = null;

        function loadPipeline() {
            fetch('handler.php?action=getAll')
                .then(r => r.json())
                .then(leads => {
                    // limpa todas as colunas
                    document.querySelectorAll('.kanban-column').forEach(col => col.innerHTML = '');

                    const counts = {};
                    Object.keys(<?= json_encode(array_keys($stages)) ?>).forEach(k => counts[k] = 0);

                    leads.forEach(lead => {
                        const cardHTML = `
                            <div onclick="openLead(${lead.id})" class="card bg-gray-800 hover:bg-gray-700 rounded-2xl p-5 cursor-pointer border border-gray-700" data-id="${lead.id}">
                                <div class="flex justify-between">
                                    <h4 class="font-semibold text-lg">${lead.nome}</h4>
                                    <a href="https://wa.me/55${lead.telefone.replace(/\D/g,'')}" target="_blank" 
                                       class="text-emerald-400 hover:text-emerald-300">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                </div>
                                <p class="text-gray-400">${lead.telefone}</p>
                                ${lead.valor ? `<p class="text-emerald-400 font-medium mt-2">R$ ${parseFloat(lead.valor).toLocaleString('pt-BR')}</p>` : ''}
                                <button onclick="event.stopImmediatePropagation(); deleteLead(${lead.id});" 
                                        class="text-red-400 hover:text-red-300 text-xs mt-4">
                                    <i class="fas fa-trash"></i> Excluir
                                </button>
                            </div>`;

                        const col = document.getElementById(`column-${lead.etapa}`);
                        if (col) col.innerHTML += cardHTML;
                        counts[lead.etapa]++;
                    });

                    // atualiza contadores
                    Object.keys(counts).forEach(k => {
                        const el = document.getElementById(`count-${k}`);
                        if (el) el.textContent = counts[k];
                    });

                    // ativa drag and drop
                    enableDragAndDrop();
                });
        }

        function enableDragAndDrop() {
            document.querySelectorAll('.kanban-column').forEach(column => {
                Sortable.create(column, {
                    group: 'kanban',
                    animation: 150,
                    onEnd: function(evt) {
                        const leadId = evt.item.dataset.id;
                        const newStage = evt.to.parentElement.dataset.stage;
                        if (leadId && newStage) {
                            fetch('handler.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=move&id=${leadId}&etapa=${newStage}`
                            });
                        }
                    }
                });
            });
        }

        function newLead() {
            currentEditId = null;
            document.getElementById('modalTitle').textContent = 'Novo Lead';
            document.getElementById('leadForm').reset();
            document.getElementById('modal').classList.remove('hidden');
        }

        function saveLead(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', currentEditId ? 'update' : 'create');
            if (currentEditId) formData.append('id', currentEditId);
            formData.append('nome', document.getElementById('nome').value);
            formData.append('telefone', document.getElementById('telefone').value);
            formData.append('email', document.getElementById('email').value);
            formData.append('valor', document.getElementById('valor').value);
            formData.append('etapa', document.getElementById('etapa').value);
            formData.append('observacao', document.getElementById('observacao').value);

            fetch('handler.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                closeModal();
                loadPipeline();
            });
        }

        function openLead(id) {
            window.location.href = `lead.php?id=${id}`;
        }

        function deleteLead(id) {
            if (confirm('Tem certeza que quer excluir este lead?')) {
                fetch('handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&id=${id}`
                }).then(() => loadPipeline());
            }
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        // Busca em tempo real
        document.getElementById('search').addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.card').forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(term) ? '' : 'none';
            });
        });

        // Carrega tudo ao abrir
        window.onload = loadPipeline;
    </script>
</body>
</html>