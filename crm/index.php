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
        .kanban-column { min-height: 520px; max-height: 620px; overflow-y: auto; }
        .card { transition: all 0.2s; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.25); }
        .stage-1 { border-left: 4px solid #10b981; }
        .stage-6 { border-left: 4px solid #22c55e; }
        .stage-7 { border-left: 4px solid #ef4444; }
    </style>
</head>
<body class="bg-gray-950 text-gray-100">
    <div class="max-w-screen-2xl mx-auto">
        <!-- HEADER -->
        <div class="bg-gray-900 border-b border-gray-800 px-8 py-6">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="flex items-center gap-3">
                    <i class="fas fa-chart-simple text-3xl text-emerald-500"></i>
                    <h1 class="text-3xl font-bold">CRM Pipeline</h1>
                </div>
                
                <div class="flex flex-wrap gap-3">
                    <input id="search" type="text" placeholder="Buscar nome, telefone ou interesse..." 
                           class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3 w-full lg:w-80 focus:outline-none focus:border-emerald-500">
                    
                    <select id="filterEtapa" class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                        <option value="">Todas as Etapas</option>
                        <?php foreach($stages as $k => $n): ?>
                            <option value="<?= $k ?>"><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input id="filterValorMin" type="number" placeholder="Valor mín. (R$)" 
                           class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3 w-40">
                    
                    <button onclick="newLead()" 
                            class="bg-emerald-600 hover:bg-emerald-700 px-6 py-3 rounded-2xl font-semibold flex items-center gap-2 whitespace-nowrap">
                        <i class="fas fa-plus"></i> Novo Lead
                    </button>
                </div>
            </div>

            <!-- Dashboard Melhorado -->
            <div id="dashboard" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6 mt-10"></div>
        </div>

        <!-- PIPELINE -->
        <div class="p-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-6" id="pipeline">
            <?php foreach($stages as $key => $name): ?>
                <div class="bg-gray-900 rounded-3xl p-5 border border-gray-800 stage-<?= $key ?>" data-stage="<?= $key ?>">
                    <div class="flex justify-between items-center mb-5">
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
        <div class="bg-gray-900 rounded-3xl w-full max-w-2xl mx-4 p-8 max-h-[95vh] overflow-y-auto">
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
                        <label class="block text-sm mb-1">Data Último Contato</label>
                        <input type="date" id="data_ultimo_contato" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
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
                    <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 py-4 rounded-2xl font-semibold">Salvar Lead</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentEditId = null;
        let allLeads = [];

        function loadPipeline(filteredLeads = null) {
            const leadsToShow = filteredLeads || allLeads;
            
            document.querySelectorAll('.kanban-column').forEach(col => col.innerHTML = '');

            let totalPipeline = 0;
            const counts = {};
            const columnTotals = {};

            Object.keys(<?= json_encode(array_keys($stages)) ?>).forEach(k => {
                counts[k] = 0;
                columnTotals[k] = 0;
            });

            leadsToShow.forEach(lead => {
                const etapa = String(lead.etapa || '1');
                const valor = parseFloat(lead.valor) || 0;
                totalPipeline += valor;
                counts[etapa]++;
                columnTotals[etapa] += valor;

                const cardHTML = `
                    <div onclick="viewLead(${lead.id})" class="card bg-gray-800 rounded-3xl p-5 cursor-pointer border border-gray-700">
                        <div class="flex justify-between items-start">
                            <h4 class="font-semibold">${lead.nome}</h4>
                            <div class="flex gap-3">
                                <button onclick="editLead(${lead.id}); event.stopImmediatePropagation();" class="text-blue-400 hover:text-blue-300"><i class="fas fa-edit"></i></button>
                                <button onclick="deleteLead(${lead.id}); event.stopImmediatePropagation();" class="text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <p class="text-gray-400 text-sm">${lead.telefone}</p>
                        ${lead.interesse ? `<p class="text-xs text-gray-500 mt-1">${lead.interesse}</p>` : ''}
                        ${lead.data_ultimo_contato ? `<p class="text-xs text-amber-400 mt-2">Últ. contato: ${lead.data_ultimo_contato}</p>` : ''}
                        ${valor > 0 ? `<p class="text-emerald-400 font-medium mt-3">R$ ${valor.toLocaleString('pt-BR')}</p>` : ''}
                    </div>`;

                const col = document.getElementById(`column-${etapa}`);
                if (col) col.innerHTML += cardHTML;
            });

            Object.keys(counts).forEach(k => {
                const countEl = document.getElementById(`count-${k}`);
                if (countEl) countEl.textContent = counts[k];

                const totalEl = document.getElementById(`total-${k}`);
                if (totalEl) totalEl.textContent = columnTotals[k] > 0 ? `Total: R$ ${columnTotals[k].toLocaleString('pt-BR')}` : '';
            });

            updateDashboard(leadsToShow);
            enableDragAndDrop();
        }

        function updateDashboard(leads) {
            const totalLeads = leads.length;
            const totalValor = leads.reduce((sum, l) => sum + (parseFloat(l.valor) || 0), 0);

            const porEtapa = {};
            leads.forEach(l => {
                const e = String(l.etapa || '1');
                porEtapa[e] = (porEtapa[e] || 0) + 1;
            });

            let html = `
                <div class="bg-gray-800 rounded-3xl p-6">
                    <p class="text-gray-400">Total Leads</p>
                    <p class="text-4xl font-bold">${totalLeads}</p>
                </div>
                <div class="bg-gray-800 rounded-3xl p-6">
                    <p class="text-gray-400">Valor Total</p>
                    <p class="text-4xl font-bold text-emerald-400">R$ ${totalValor.toLocaleString('pt-BR')}</p>
                </div>`;

            document.getElementById('dashboard').innerHTML = html;
        }

        function enableDragAndDrop() {
            document.querySelectorAll('.kanban-column').forEach(column => {
                Sortable.create(column, {
                    group: 'kanban',
                    animation: 180,
                    onEnd: function(evt) {
                        const id = evt.item.dataset.id;
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

        // Filtros
        function applyFilters() {
            const term = document.getElementById('search').value.toLowerCase().trim();
            const etapaFilter = document.getElementById('filterEtapa').value;
            const valorMin = parseFloat(document.getElementById('filterValorMin').value) || 0;

            const filtered = allLeads.filter(lead => {
                const matchSearch = !term || 
                    (lead.nome && lead.nome.toLowerCase().includes(term)) ||
                    (lead.telefone && lead.telefone.includes(term)) ||
                    (lead.interesse && lead.interesse.toLowerCase().includes(term));

                const matchEtapa = !etapaFilter || String(lead.etapa) === etapaFilter;
                const matchValor = parseFloat(lead.valor) >= valorMin;

                return matchSearch && matchEtapa && matchValor;
            });

            loadPipeline(filtered);
        }

        function newLead() {
            currentEditId = null;
            document.getElementById('modalTitle').textContent = 'Novo Lead';
            document.getElementById('leadForm').reset();
            document.getElementById('modal').classList.remove('hidden');
        }

        function editLead(id) {
            const lead = allLeads.find(l => Number(l.id) === Number(id));
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
            document.getElementById('data_ultimo_contato').value = lead.data_ultimo_contato || '';
            document.getElementById('modal').classList.remove('hidden');
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
            formData.append('data_ultimo_contato', document.getElementById('data_ultimo_contato').value || null);

            fetch('handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.error) alert(data.error);
                    else {
                        closeModal();
                        fetchLeadsAndRender();
                        alert(currentEditId ? "Lead atualizado!" : "Lead cadastrado com sucesso!");
                    }
                });
        }

        function deleteLead(id) {
            if (confirm("Excluir este lead?")) {
                fetch('handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete&id=${id}`
                }).then(() => fetchLeadsAndRender());
            }
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        function fetchLeadsAndRender() {
            fetch('handler.php?action=getAll')
                .then(r => r.json())
                .then(leads => {
                    allLeads = leads;
                    loadPipeline(leads);
                });
        }

        // Event listeners para filtros
        document.getElementById('search').addEventListener('input', applyFilters);
        document.getElementById('filterEtapa').addEventListener('change', applyFilters);
        document.getElementById('filterValorMin').addEventListener('input', applyFilters);

        window.onload = fetchLeadsAndRender;
    </script>
</body>
</html>