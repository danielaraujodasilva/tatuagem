<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Pipeline</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .kanban-column { min-height: 520px; }
        .card { transition: all 0.2s; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.25); }
    </style>
</head>
<body class="bg-gray-950 text-gray-100">
    <div class="max-w-screen-2xl mx-auto">
        <!-- HEADER + DASHBOARD -->
        <div class="bg-gray-900 border-b border-gray-800 px-8 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <i class="fas fa-chart-simple text-3xl text-emerald-500"></i>
                    <h1 class="text-3xl font-bold">CRM Pipeline</h1>
                </div>
                <button onclick="newLead()" class="bg-emerald-600 hover:bg-emerald-700 px-6 py-3 rounded-2xl font-semibold flex items-center gap-2">
                    <i class="fas fa-plus"></i> Novo Lead
                </button>
            </div>

            <!-- Dashboard -->
            <div id="dashboard" class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-8"></div>
        </div>

        <!-- PIPELINE -->
        <div class="p-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-6">
            <?php foreach($stages as $key => $name): ?>
                <div class="bg-gray-900 rounded-3xl p-5 border border-gray-800" data-stage="<?= $key ?>">
                    <div class="flex justify-between mb-5">
                        <h2 class="font-bold text-lg"><?= $name ?></h2>
                        <span id="count-<?= $key ?>" class="bg-gray-800 px-4 py-1 rounded-full text-sm">0</span>
                    </div>
                    <div class="kanban-column space-y-4" id="column-<?= $key ?>"></div>
                    <div id="total-<?= $key ?>" class="mt-4 text-right text-emerald-400 text-sm font-medium"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MODAL -->
    <div id="modal" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center z-50">
        <div class="bg-gray-900 rounded-3xl w-full max-w-2xl mx-4 p-8">
            <h3 id="modalTitle" class="text-2xl font-bold mb-6">Novo Lead</h3>
            <form id="leadForm" onsubmit="saveLead(event)">
                <input type="hidden" id="leadId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="col-span-2">
                        <label class="block text-sm mb-1">Nome *</label>
                        <input type="text" id="nome" required class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Telefone *</label>
                        <input type="tel" id="telefone" required class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Valor Estimado (R$)</label>
                        <input type="number" id="valor" step="0.01" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Interesse</label>
                        <input type="text" id="interesse" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Origem</label>
                        <input type="text" id="origem" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Status</label>
                        <input type="text" id="status" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-sm mb-1">Etapa</label>
                    <select id="etapa" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                        <?php foreach($stages as $k => $n): ?>
                            <option value="<?= $k ?>"><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex gap-4 mt-10">
                    <button type="button" onclick="closeModal()" class="flex-1 py-4 text-gray-400 hover:text-white font-medium rounded-2xl">Cancelar</button>
                    <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 py-4 rounded-2xl font-semibold">Salvar</button>
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
                    document.querySelectorAll('.kanban-column').forEach(col => col.innerHTML = '');
                    let totalPipeline = 0;
                    const counts = {};
                    const columnTotals = {};

                    Object.keys(<?= json_encode(array_keys($stages)) ?>).forEach(k => {
                        counts[k] = 0;
                        columnTotals[k] = 0;
                    });

                    leads.forEach(lead => {
                        const etapa = String(lead.etapa || '1');
                        const valor = parseFloat(lead.valor) || 0;
                        totalPipeline += valor;
                        counts[etapa]++;
                        columnTotals[etapa] += valor;

                        const cardHTML = `
                            <div onclick="viewLead(${lead.id})" class="card bg-gray-800 rounded-3xl p-5 cursor-pointer border border-gray-700">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-semibold text-lg">${lead.nome}</h4>
                                    <div class="flex gap-3">
                                        <button onclick="editLead(${lead.id}); event.stopImmediatePropagation();" class="text-blue-400 hover:text-blue-300"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteLead(${lead.id}); event.stopImmediatePropagation();" class="text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                                <p class="text-gray-400">${lead.telefone}</p>
                                ${lead.interesse ? `<p class="text-xs text-gray-500 mt-2">${lead.interesse}</p>` : ''}
                                ${valor > 0 ? `<p class="text-emerald-400 font-medium mt-3">R$ ${valor.toLocaleString('pt-BR')}</p>` : ''}
                            </div>`;

                        const col = document.getElementById(`column-${etapa}`);
                        if (col) col.innerHTML += cardHTML;
                    });

                    // Atualiza contadores e totais por coluna
                    Object.keys(counts).forEach(k => {
                        document.getElementById(`count-${k}`).textContent = counts[k];
                        const totalEl = document.getElementById(`total-${k}`);
                        if (totalEl) totalEl.textContent = columnTotals[k] > 0 ? `Total: R$ ${columnTotals[k].toLocaleString('pt-BR')}` : '';
                    });

                    updateDashboard(leads.length, totalPipeline);
                    enableDragAndDrop();
                });
        }

        function updateDashboard(totalLeads, totalValor) {
            document.getElementById('dashboard').innerHTML = `
                <div class="bg-gray-800 rounded-3xl p-6">
                    <p class="text-gray-400">Total de Leads</p>
                    <p class="text-4xl font-bold">${totalLeads}</p>
                </div>
                <div class="bg-gray-800 rounded-3xl p-6">
                    <p class="text-gray-400">Valor Total no Pipeline</p>
                    <p class="text-4xl font-bold text-emerald-400">R$ ${totalValor.toLocaleString('pt-BR')}</p>
                </div>
            `;
        }

        function enableDragAndDrop() {
            document.querySelectorAll('.kanban-column').forEach(column => {
                Sortable.create(column, {
                    group: 'kanban',
                    animation: 180,
                    onEnd: function(evt) {
                        const id = evt.item.dataset.id || evt.item.querySelector('[data-id]').dataset.id;
                        const newEtapa = evt.to.parentElement.dataset.stage;
                        if (id && newEtapa) {
                            fetch('handler.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: `action=move&id=${id}&etapa=${newEtapa}`
                            }).then(() => loadPipeline());
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

        function editLead(id) {
            fetch('handler.php?action=getAll')
                .then(r => r.json())
                .then(leads => {
                    const lead = leads.find(l => parseInt(l.id) === parseInt(id));
                    if (!lead) return;
                    currentEditId = id;
                    document.getElementById('modalTitle').textContent = 'Editar Lead';
                    document.getElementById('nome').value = lead.nome || '';
                    document.getElementById('telefone').value = lead.telefone || '';
                    document.getElementById('interesse').value = lead.interesse || '';
                    document.getElementById('valor').value = lead.valor || '';
                    document.getElementById('origem').value = lead.origem || '';
                    document.getElementById('status').value = lead.status || '';
                    document.getElementById('etapa').value = lead.etapa || '1';
                    document.getElementById('modal').classList.remove('hidden');
                });
        }

        function viewLead(id) {
            window.location.href = `lead.php?id=${id}`;
        }

        function saveLead(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', currentEditId ? 'update' : 'create');
            if (currentEditId) formData.append('id', currentEditId);
            formData.append('nome', document.getElementById('nome').value.trim());
            formData.append('telefone', document.getElementById('telefone').value.trim());
            formData.append('interesse', document.getElementById('interesse').value.trim());
            formData.append('valor', document.getElementById('valor').value || 0);
            formData.append('origem', document.getElementById('origem').value.trim());
            formData.append('status', document.getElementById('status').value.trim());
            formData.append('etapa', document.getElementById('etapa').value);

            fetch('handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                    } else {
                        closeModal();
                        loadPipeline();
                        alert(currentEditId ? "Lead atualizado com sucesso!" : "Lead cadastrado com sucesso!");
                    }
                })
                .catch(() => alert("Erro ao salvar. Tente novamente."));
        }

        function deleteLead(id) {
            if (confirm("Excluir este lead permanentemente?")) {
                fetch('handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete&id=${id}`
                }).then(() => loadPipeline());
            }
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        window.onload = loadPipeline;
    </script>
</body>
</html>