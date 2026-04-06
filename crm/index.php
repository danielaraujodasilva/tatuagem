<?php require 'config.php'; ?>
<?php
$stages = [];

$result = $conn->query("SELECT * FROM pipelines ORDER BY ordem");

while($row = $result->fetch(PDO::FETCH_ASSOC)){
    $stages[$row['id']] = $row['nome'];
}
?>
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
        .pipeline-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(265px, 1fr));
            gap: 1.25rem;
            padding: 1.5rem;
            overflow-x: auto;
            scroll-behavior: smooth;
        }

        .kanban-column { 
            min-height: 540px; 
            max-height: 680px; 
            overflow-y: auto; 
            padding-right: 8px;
        }

        .card { 
            transition: all 0.2s; 
            min-height: 148px;
        }

        .card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.25); 
        }

        .lead-frio { border-left: 4px solid #eab308; }
        .lead-muito-frio { border-left: 4px solid #ef4444; }

        /* Mobile - scroll horizontal */
        @media (max-width: 1280px) {
            .pipeline-container {
                grid-template-columns: repeat(7, minmax(255px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .pipeline-container {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
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
                    <a href="configuracoes.php" 
   class="bg-gray-800 hover:bg-gray-700 px-4 py-3 rounded-2xl flex items-center">
    <i class="fas fa-gear"></i>
</a>
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

                    <button onclick="openNewClientsModal()" class="bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-2xl font-semibold flex items-center gap-2">
  <i class="fas fa-user-plus"></i> Novos Clientes
</button>
                </div>
            </div>

            <!-- Dashboard -->
            <div id="dashboard" onclick="showDashboardModal()" 
                 class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6 mt-10 cursor-pointer hover:opacity-90 transition">
            </div>
        </div>

        <!-- PIPELINE - 7 colunas fixas -->
        <div class="pipeline-container" id="pipeline">
            <?php foreach($stages as $key => $name): ?>
                <div class="bg-gray-900 rounded-3xl p-5 border border-gray-800" data-stage="<?= $key ?>">
                    <div class="flex justify-between items-center mb-5">
                        <h2 class="font-bold text-lg"><?= $name ?></h2>
                        <span id="count-<?= $key ?>" class="bg-gray-800 px-4 py-1 rounded-full text-sm">0</span>
                    </div>
                    <div class="kanban-column space-y-4" id="column-<?= $key ?>"></div>
                    <div class="mt-6 pt-4 border-t border-gray-700 flex justify-between items-center">
                        <div id="total-<?= $key ?>" class="text-emerald-400 text-sm font-medium"></div>
                        <button onclick="verTodosNaEtapa('<?= $key ?>')" 
                                class="text-xs bg-gray-800 hover:bg-gray-700 px-5 py-2 rounded-2xl">Ver todos</button>
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
                <button onclick="closeVerTodos()" class="text-gray-400 hover:text-white text-3xl">×</button>
            </div>
            <input id="searchVerTodos" type="text" placeholder="Buscar dentro desta etapa..." 
                   class="bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3 mb-6">
            <div id="listaVerTodos" class="flex-1 overflow-y-auto space-y-3"></div>
        </div>
    </div>

    <!-- Modal Novos Clientes -->
<div id="modalNovosClientes" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center z-50">
  <div class="bg-gray-900 rounded-3xl w-full max-w-md mx-4 p-8">
    <div class="flex justify-between items-center mb-6">
      <h3 class="text-2xl font-bold">Novos Clientes</h3>
      <button onclick="closeNewClientsModal()" class="text-gray-400 hover:text-white text-3xl">×</button>
    </div>

    <div class="mb-4">
      <label class="block text-sm mb-1">Data Início</label>
      <input type="date" id="newClientsStart" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
    </div>
    <div class="mb-4">
      <label class="block text-sm mb-1">Data Fim</label>
      <input type="date" id="newClientsEnd" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
    </div>
    <div class="mb-6">
      <label class="block text-sm mb-1">Hora Início</label>
      <input type="time" id="newClientsStartTime" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
    </div>
    <div class="mb-6">
      <label class="block text-sm mb-1">Hora Fim</label>
      <input type="time" id="newClientsEndTime" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
    </div>
    <button onclick="filterNewClients()" class="bg-emerald-600 hover:bg-emerald-700 px-6 py-3 rounded-2xl font-semibold w-full">Filtrar</button>

    <div id="newClientsResult" class="mt-6 text-gray-100 text-lg"></div>
  </div>
</div>

    <!-- Modal Novo/Editar -->
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
                    <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 py-4 rounded-2xl font-semibold">Salvar Lead</button>
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

        function loadPipeline(filteredLeads = null) {
            const leads = filteredLeads || allLeads;
            document.querySelectorAll('.kanban-column').forEach(col => col.innerHTML = '');

            let totalValor = 0;
            const counts = {};
            const columnTotals = {};

            Object.keys(<?= json_encode(array_keys($stages)) ?>).forEach(k => {
                counts[k] = 0;
                columnTotals[k] = 0;
            });

            leads.forEach(lead => {
                const etapa = String(
    lead.etapa ||
    (lead.status === 'novo' ? '1' : null) ||
    Object.keys(<?= json_encode($stages) ?>)[0]
);
                const valor = parseFloat(lead.valor) || 0;
                const dias = diasSemContato(lead.data_ultimo_contato);

                totalValor += valor;
                counts[etapa]++;
                columnTotals[etapa] += valor;

                let classeFrio = '';
                if (dias > 20) classeFrio = 'lead-muito-frio';
                else if (dias > 10) classeFrio = 'lead-frio';

                const html = `
                    <div data-id="${lead.id}" onclick="viewLead('${lead.id}')" class="card bg-gray-800 rounded-3xl p-5 cursor-pointer border border-gray-700 ${classeFrio}">
                        <div class="flex justify-between items-start">
                            <h4 class="font-semibold text-base">${lead.nome}</h4>
                        </div>
                        <p class="text-gray-400 text-sm mt-1">${lead.telefone}</p>
                        ${lead.interesse ? `<p class="text-xs text-gray-500 mt-3">${lead.interesse}</p>` : ''}
                        ${dias < 999 ? `<p class="text-xs mt-3 ${dias > 20 ? 'text-red-400' : 'text-amber-400'}">Sem contato há ${dias} dias</p>` : ''}
                        ${valor > 0 ? `<p class="text-emerald-400 font-medium mt-4">R$ ${valor.toLocaleString('pt-BR')}</p>` : ''}

                        <div class="flex justify-start gap-5 mt-5 pt-4 border-t border-gray-700 text-sm">
                            <button onclick="editLead('${lead.id}'); event.stopImmediatePropagation()" class="text-blue-400 hover:text-blue-300 flex items-center gap-1">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button onclick="deleteLead('${lead.id}'); event.stopImmediatePropagation()" class="text-red-400 hover:text-red-300 flex items-center gap-1">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </div>`;

                const col = document.getElementById(`column-${etapa}`);
                if (col && col.children.length < 8) {
                    col.innerHTML += html;
                }
            });

            Object.keys(counts).forEach(k => {
                const countEl = document.getElementById(`count-${k}`);
                if (countEl) countEl.textContent = counts[k];

                const totalEl = document.getElementById(`total-${k}`);
                if (totalEl) totalEl.textContent = columnTotals[k] > 0 ? `R$ ${columnTotals[k].toLocaleString('pt-BR')}` : '';
            });

            updateDashboard(leads);
            enableDragAndDrop();
        }

        function updateDashboard(leads) {
            const totalL = leads.length;
            const totalV = leads.reduce((sum, l) => sum + (parseFloat(l.valor) || 0), 0);

            document.getElementById('dashboard').innerHTML = `
                <div class="bg-gray-800 rounded-3xl p-6 text-center">
                    <p class="text-gray-400 text-sm">Total Leads</p>
                    <p class="text-4xl font-bold">${totalL}</p>
                </div>
                <div class="bg-gray-800 rounded-3xl p-6 text-center">
                    <p class="text-gray-400 text-sm">Valor Total</p>
                    <p class="text-4xl font-bold text-emerald-400">R$ ${totalV.toLocaleString('pt-BR')}</p>
                </div>
            `;
        }

        function showDashboardModal() {
            const totalL = allLeads.length;
            const totalV = allLeads.reduce((sum, l) => sum + (parseFloat(l.valor) || 0), 0);
            alert(`📊 Dashboard Detalhado\n\nTotal de Leads: ${totalL}\nValor Total no Pipeline: R$ ${totalV.toLocaleString('pt-BR')}\n\nGráficos completos em breve.`);
        }

        function enableDragAndDrop() {
    document.querySelectorAll('.kanban-column').forEach(column => {

        // evita duplicar sortable (isso buga tudo)
        if (column.sortableInstance) {
            column.sortableInstance.destroy();
        }

        column.sortableInstance = Sortable.create(column, {
            group: 'kanban',
            animation: 180,
            ghostClass: 'opacity-50',

            onEnd: function(evt) {
                const id = evt.item.getAttribute('data-id');
                const newEtapa = evt.to.closest('[data-stage]').getAttribute('data-stage');

                if (id && newEtapa) {
                    fetch('handler.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=move&id=${id}&etapa=${newEtapa}`
                    })
                    .then(() => {
    Promise.all([
        fetch('handler.php?action=getAll').then(r => r.json()),
        carregarClientesWhatsApp()
    ]).then(([leadsSistema, leadsWhats]) => {
        allLeads = [...leadsSistema, ...leadsWhats];
        loadPipeline();
    });
});
                }
            }
        });
    });
}

        function verTodosNaEtapa(etapa) {
            const stages = <?= json_encode($stages) ?>;
const stageName = stages[etapa] || 'Etapa ' + etapa;
            document.getElementById('modalVerTitulo').textContent = `Leads - ${stageName}`;
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
                    <p class="text-gray-400">${lead.telefone}</p>
                </div>
            `).join('');
        }

        function closeVerTodos() {
            document.getElementById('modalVerTodos').classList.add('hidden');
        }

        function exportToCSV() {
            if (!allLeads.length) return alert("Não há leads para exportar");

            let csv = "ID,Nome,Telefone,Interesse,Valor,Origem,Status,Etapa,Último Contato,Data Cadastro\n";
            allLeads.forEach(l => {
                csv += `"${l.id}","${l.nome.replace(/"/g, '""')}","${l.telefone}","${(l.interesse||'').replace(/"/g, '""')}",${l.valor||0},"${(l.origem||'').replace(/"/g, '""')}","${(l.status||'').replace(/"/g, '""')}","${l.etapa}","${l.data_ultimo_contato||''}","${l.created_at}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `crm_leads_${new Date().toISOString().slice(0,10)}.csv`;
            link.click();
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

    // se for WhatsApp
    if (String(id).startsWith('wa_')) {
        window.location.href = `chat.php?id=${id}`;
        return;
    }

    // se for lead normal
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
                        Promise.all([
    fetch('handler.php?action=getAll').then(r => r.json()),
    carregarClientesWhatsApp()
]).then(([leadsSistema, leadsWhats]) => {
    allLeads = [...leadsSistema, ...leadsWhats];
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
                Promise.all([
    fetch('handler.php?action=getAll').then(r => r.json()),
    carregarClientesWhatsApp()
]).then(([leadsSistema, leadsWhats]) => {
    allLeads = [...leadsSistema, ...leadsWhats];
    loadPipeline();
});
            });
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        function applyFilters() {
            const term = document.getElementById('search').value.toLowerCase().trim();
            const etapa = document.getElementById('filterEtapa').value;
            const vMin = parseFloat(document.getElementById('filterValorMin').value) || 0;
            const vMax = parseFloat(document.getElementById('filterValorMax').value) || Infinity;

            const filtered = allLeads.filter(lead => {
                const matchSearch = !term || 
                    (lead.nome && lead.nome.toLowerCase().includes(term)) ||
                    (lead.telefone && lead.telefone.includes(term)) ||
                    (lead.interesse && lead.interesse.toLowerCase().includes(term));

                const matchEtapa = !etapa || String(lead.etapa) === etapa;
                const valor = parseFloat(lead.valor) || 0;
                const matchValor = valor >= vMin && valor <= vMax;

                return matchSearch && matchEtapa && matchValor;
            });

            loadPipeline(filtered);
        }


        async function carregarClientesWhatsApp() {
    try {
        const res = await fetch('api_clientes.php');
        const clientes = await res.json();

        // converter clientes em leads compatíveis
        const leadsConvertidos = clientes.map(c => ({
            id: 'wa_' + c.id,
            nome: c.nome || 'Cliente WhatsApp',
            telefone: c.numero,
            interesse: c.mensagens?.slice(-1)[0]?.texto || '',
            valor: 0,
            origem: 'WhatsApp',
            status: c.status || 'novo',
            etapa: Object.keys(<?= json_encode($stages) ?>)[0], // joga na primeira coluna
            data_ultimo_contato: c.mensagens?.slice(-1)[0]?.data || '',
            created_at: c.mensagens?.[0]?.data || ''
        }));

        return leadsConvertidos;

    } catch (e) {
        console.log('Erro ao carregar WhatsApp:', e);
        return [];
    }
}


        // Inicialização
        window.onload = async () => {

    const leadsSistema = await fetch('handler.php?action=getAll')
        .then(r => r.json());

    const leadsWhats = await carregarClientesWhatsApp();

    // junta tudo
    allLeads = [...leadsSistema, ...leadsWhats];

    loadPipeline(allLeads);

    document.getElementById('search').addEventListener('input', applyFilters);
    document.getElementById('filterEtapa').addEventListener('change', applyFilters);
    document.getElementById('filterValorMin').addEventListener('input', applyFilters);
    document.getElementById('filterValorMax').addEventListener('input', applyFilters);
};
    </script>

    <script>
        function openNewClientsModal() {
  document.getElementById('modalNovosClientes').classList.remove('hidden');
}

function closeNewClientsModal() {
  document.getElementById('modalNovosClientes').classList.add('hidden');
}

function openNewClientsModal() {
  document.getElementById('modalNovosClientes').classList.remove('hidden');
}

function closeNewClientsModal() {
  document.getElementById('modalNovosClientes').classList.add('hidden');
}
    </script>
</body>
</html>