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
        .lead-frio { border-left: 4px solid #eab308; }
        .lead-muito-frio { border-left: 4px solid #ef4444; }
        @media (max-width: 1024px) {
            .pipeline-container { overflow-x: auto; padding-bottom: 20px; }
        }
    </style>
</head>
<body class="bg-gray-950 text-gray-100">
    <div class="max-w-screen-2xl mx-auto">
        <!-- HEADER -->
        <div class="bg-gray-900 border-b border-gray-800 px-6 py-6">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div class="flex items-center gap-3">
                    <i class="fas fa-chart-simple text-3xl text-emerald-500"></i>
                    <h1 class="text-3xl font-bold">CRM Pipeline</h1>
                </div>

                <div class="flex flex-wrap gap-3 w-full lg:w-auto">
                    <input id="search" type="text" placeholder="Buscar nome, telefone ou interesse..." 
                           class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3 flex-1 lg:w-80">

                    <select id="filterEtapa" class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                        <option value="">Todas as etapas</option>
                        <?php foreach($stages as $k => $n): ?>
                            <option value="<?= $k ?>"><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input id="filterValorMin" type="number" placeholder="Valor mín." class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3 w-36">
                    <input id="filterValorMax" type="number" placeholder="Valor máx." class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3 w-36">

                    <button onclick="exportToCSV()" 
                            class="bg-white text-gray-900 px-6 py-3 rounded-2xl font-semibold flex items-center gap-2 hover:bg-gray-100">
                        <i class="fas fa-download"></i> Exportar CSV
                    </button>

                    <button onclick="newLead()" 
                            class="bg-emerald-600 hover:bg-emerald-700 px-6 py-3 rounded-2xl font-semibold flex items-center gap-2">
                        <i class="fas fa-plus"></i> Novo Lead
                    </button>
                </div>
            </div>

            <!-- Dashboard -->
            <div id="dashboard" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6 mt-10"></div>
        </div>

        <!-- PIPELINE -->
        <div class="pipeline-container p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-6" id="pipeline">
            <?php foreach($stages as $key => $name): ?>
                <div class="bg-gray-900 rounded-3xl p-5 border border-gray-800 min-w-[290px]" data-stage="<?= $key ?>">
                    <div class="flex justify-between items-center mb-5">
                        <h2 class="font-bold text-lg"><?= $name ?></h2>
                        <span id="count-<?= $key ?>" class="bg-gray-800 px-4 py-1 rounded-full text-sm">0</span>
                    </div>
                    <div class="kanban-column space-y-4" id="column-<?= $key ?>"></div>
                    <div class="mt-4 flex justify-between">
                        <div id="total-<?= $key ?>" class="text-emerald-400 text-sm font-medium"></div>
                        <button onclick="verTodosNaEtapa('<?= $key ?>')" 
                                class="text-xs bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-2xl">Ver todos</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Ver Todos -->
    <div id="modalVerTodos" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center z-50">
        <div class="bg-gray-900 rounded-3xl w-full max-w-3xl mx-4 p-8 flex flex-col max-h-[90vh]">
            <div class="flex justify-between mb-6">
                <h3 id="modalVerTitulo" class="text-2xl font-bold"></h3>
                <button onclick="closeVerTodos()" class="text-gray-400 hover:text-white text-2xl">×</button>
            </div>
            <input id="searchVerTodos" type="text" placeholder="Buscar dentro desta etapa..." 
                   class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3 mb-6">
            <div id="listaVerTodos" class="flex-1 overflow-y-auto space-y-3"></div>
        </div>
    </div>

    <!-- Modal Lead -->
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
                        <label class="block text-sm mb-1">Valor Estimado</label>
                        <input type="number" id="valor" step="0.01" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Último Contato</label>
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
                    <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 py-4 rounded-2xl font-semibold">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let allLeads = [];

        function diasSemContato(data) {
            if (!data) return 999;
            const diff = Math.abs(new Date() - new Date(data));
            return Math.ceil(diff / (1000 * 60 * 60 * 24));
        }

        function loadPipeline(filtered = null) {
            const leads = filtered || allLeads;
            document.querySelectorAll('.kanban-column').forEach(c => c.innerHTML = '');

            let totalValor = 0;
            const counts = {};
            const totals = {};

            Object.keys(<?= json_encode(array_keys($stages)) ?>).forEach(k => {
                counts[k] = 0;
                totals[k] = 0;
            });

            leads.forEach(lead => {
                const etapa = String(lead.etapa || '1');
                const valor = parseFloat(lead.valor) || 0;
                const dias = diasSemContato(lead.data_ultimo_contato);

                totalValor += valor;
                counts[etapa]++;
                totals[etapa] += valor;

                let classeFrio = '';
                if (dias > 20) classeFrio = 'lead-muito-frio';
                else if (dias > 10) classeFrio = 'lead-frio';

                const html = `
                    <div onclick="viewLead(${lead.id})" class="card bg-gray-800 rounded-3xl p-5 cursor-pointer border border-gray-700 ${classeFrio}">
                        <div class="flex justify-between">
                            <h4 class="font-semibold">${lead.nome}</h4>
                            <div class="flex gap-2">
                                <button onclick="editLead(${lead.id}); event.stopImmediatePropagation()" class="text-blue-400"><i class="fas fa-edit"></i></button>
                                <button onclick="deleteLead(${lead.id}); event.stopImmediatePropagation()" class="text-red-400"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <p class="text-gray-400">${lead.telefone}</p>
                        ${lead.interesse ? `<p class="text-xs text-gray-500">${lead.interesse}</p>` : ''}
                        ${dias < 999 ? `<p class="text-xs mt-2 ${dias > 20 ? 'text-red-400' : 'text-amber-400'}">Sem contato há ${dias} dias</p>` : ''}
                        ${valor > 0 ? `<p class="text-emerald-400 font-medium mt-3">R$ ${valor.toLocaleString('pt-BR')}</p>` : ''}
                    </div>`;

                const col = document.getElementById(`column-${etapa}`);
                if (col && col.children.length < 8) col.innerHTML += html;
            });

            // Atualiza contadores
            Object.keys(counts).forEach(k => {
                document.getElementById(`count-${k}`).textContent = counts[k];
                const totalEl = document.getElementById(`total-${k}`);
                if (totalEl) totalEl.textContent = totals[k] > 0 ? `R$ ${totals[k].toLocaleString('pt-BR')}` : '';
            });

            updateDashboard(leads);
            enableDragAndDrop();
        }

        function updateDashboard(leads) {
            const totalL = leads.length;
            const totalV = leads.reduce((a, b) => a + (parseFloat(b.valor)||0), 0);

            document.getElementById('dashboard').innerHTML = `
                <div class="bg-gray-800 rounded-3xl p-6">
                    <p class="text-gray-400">Total Leads</p>
                    <p class="text-4xl font-bold">${totalL}</p>
                </div>
                <div class="bg-gray-800 rounded-3xl p-6">
                    <p class="text-gray-400">Valor Total</p>
                    <p class="text-4xl font-bold text-emerald-400">R$ ${totalV.toLocaleString('pt-BR')}</p>
                </div>
            `;
        }

        function enableDragAndDrop() {
            document.querySelectorAll('.kanban-column').forEach(col => {
                Sortable.create(col, {
                    group: 'kanban',
                    animation: 180,
                    onEnd: (evt) => {
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

        function verTodosNaEtapa(etapa) {
            const titulo = document.getElementById('modalVerTitulo');
            titulo.textContent = `Leads - ${Object.values(<?= json_encode($stages) ?>)[parseInt(etapa)-1] || 'Etapa ' + etapa}`;

            document.getElementById('modalVerTodos').classList.remove('hidden');

            const filtered = allLeads.filter(l => String(l.etapa) === etapa);
            renderListaVerTodos(filtered);
        }

        function renderListaVerTodos(leads) {
            const container = document.getElementById('listaVerTodos');
            container.innerHTML = leads.map(lead => `
                <div onclick="closeVerTodos(); viewLead(${lead.id});" class="bg-gray-800 hover:bg-gray-700 rounded-2xl p-5 cursor-pointer">
                    <div class="flex justify-between">
                        <strong>${lead.nome}</strong>
                        <span class="text-emerald-400">R$ ${(parseFloat(lead.valor)||0).toLocaleString('pt-BR')}</span>
                    </div>
                    <p class="text-gray-400 text-sm">${lead.telefone}</p>
                </div>
            `).join('');
        }

        function closeVerTodos() {
            document.getElementById('modalVerTodos').classList.add('hidden');
        }

        function exportToCSV() {
            if (!allLeads.length) return alert("Sem dados para exportar");

            let csv = "ID,Nome,Telefone,Interesse,Valor,Origem,Status,Etapa,Último Contato,Cadastro\n";
            allLeads.forEach(l => {
                csv += `"${l.id}","${l.nome}","${l.telefone}","${l.interesse||''}",${l.valor||0},"${l.origem||''}","${l.status||''}","${l.etapa}","${l.data_ultimo_contato||''}","${l.created_at}"\n`;
            });

            const blob = new Blob([csv], {type: 'text/csv'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `leads_${new Date().toISOString().slice(0,10)}.csv`;
            a.click();
        }

        function newLead() {
            document.getElementById('modalTitle').textContent = 'Novo Lead';
            document.getElementById('leadForm').reset();
            document.getElementById('modal').classList.remove('hidden');
        }

        function editLead(id) {
            const lead = allLeads.find(l => Number(l.id) === Number(id));
            if (!lead) return;
            document.getElementById('modalTitle').textContent = 'Editar Lead';
            document.getElementById('leadId').value = lead.id;
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
            const formData = new FormData(e.target);
            formData.append('action', document.getElementById('leadId').value ? 'update' : 'create');

            fetch('handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.error) alert(data.error);
                    else {
                        closeModal();
                        fetch('handler.php?action=getAll').then(r => r.json()).then(leads => {
                            allLeads = leads;
                            loadPipeline();
                        });
                    }
                });
        }

        function deleteLead(id) {
            if (!confirm('Excluir este lead?')) return;
            fetch('handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&id=${id}`
            }).then(() => {
                fetch('handler.php?action=getAll').then(r => r.json()).then(leads => {
                    allLeads = leads;
                    loadPipeline();
                });
            });
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        // Filtros
        function applyFilters() {
            const term = document.getElementById('search').value.toLowerCase();
            const etapa = document.getElementById('filterEtapa').value;
            const vMin = parseFloat(document.getElementById('filterValorMin').value) || 0;
            const vMax = parseFloat(document.getElementById('filterValorMax').value) || Infinity;

            const filtered = allLeads.filter(lead => {
                const matchBusca = !term || 
                    (lead.nome && lead.nome.toLowerCase().includes(term)) ||
                    (lead.telefone && lead.telefone.includes(term)) ||
                    (lead.interesse && lead.interesse.toLowerCase().includes(term));

                const matchEtapa = !etapa || String(lead.etapa) === etapa;
                const valor = parseFloat(lead.valor) || 0;
                const matchValor = valor >= vMin && valor <= vMax;

                return matchBusca && matchEtapa && matchValor;
            });

            loadPipeline(filtered);
        }

        // Inicialização
        window.onload = () => {
            fetch('handler.php?action=getAll')
                .then(r => r.json())
                .then(leads => {
                    allLeads = leads;
                    loadPipeline();
                });

            // Aplicar filtros ao digitar ou mudar
            document.getElementById('search').addEventListener('input', applyFilters);
            document.getElementById('filterEtapa').addEventListener('change', applyFilters);
            document.getElementById('filterValorMin').addEventListener('input', applyFilters);
            document.getElementById('filterValorMax').addEventListener('input', applyFilters);
        };
    </script>
</body>
</html>