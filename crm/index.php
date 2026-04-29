<?php require 'config.php'; ?>
<?php
$stages = [];

$result = $conn->query("SELECT * FROM pipelines ORDER BY ordem");

while($row = $result->fetch(PDO::FETCH_ASSOC)){
    $stageName = trim((string)($row['nome'] ?? ''));
    if ($stageName === '') {
        continue;
    }
    $stages[(string)$row['id']] = $stageName;
}

$stageIds = array_map('strval', array_keys($stages));
$firstStage = $stageIds[0] ?? '1';
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
            grid-template-columns: repeat(<?= max(count($stages), 1) ?>, minmax(180px, 1fr));
            gap: 0.75rem;
            padding: 1rem;
            overflow-x: auto;
            scroll-behavior: smooth;
        }

        .kanban-column { 
            min-height: 540px; 
            max-height: 680px; 
            overflow-y: auto; 
            padding-right: 4px;
        }

        .card { 
            transition: all 0.2s; 
            min-height: 126px;
        }

        .card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.25); 
        }

        .lead-frio { border-left: 4px solid #eab308; }
        .lead-muito-frio { border-left: 4px solid #ef4444; }

        @media (max-width: 1280px) {
            .pipeline-container {
                grid-template-columns: repeat(<?= max(count($stages), 1) ?>, minmax(210px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .pipeline-container {
                grid-template-columns: repeat(<?= max(count($stages), 1) ?>, minmax(220px, 1fr));
            }
        }
    </style>
</head>
<body class="bg-gray-950 text-gray-100">
    <div class="w-full mx-auto">
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

    <!-- Overlay Chat WhatsApp -->
    <div id="chatOverlay" class="hidden fixed inset-0 bg-black/75 z-50">
        <div class="h-full w-full flex justify-end">
            <aside class="w-full max-w-2xl h-full bg-gray-950 border-l border-gray-800 shadow-2xl flex flex-col">
                <div class="bg-gray-900 border-b border-gray-800 px-5 py-4 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-600 flex items-center justify-center">
                                <i class="fab fa-whatsapp text-xl"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 id="chatName" class="font-bold text-lg truncate">Cliente WhatsApp</h3>
                                <p id="chatPhone" class="text-sm text-gray-400 truncate"></p>
                            </div>
                        </div>
                    </div>
                    <button onclick="closeChatOverlay()" class="text-gray-400 hover:text-white text-3xl leading-none">&times;</button>
                </div>

                <div id="chatMessages" class="flex-1 overflow-y-auto px-5 py-5 space-y-3 bg-gray-950"></div>

                <div id="emojiPanel" class="hidden border-t border-gray-800 bg-gray-900 px-5 py-3">
                    <div class="flex flex-wrap gap-2 text-2xl">
                        <button type="button" onclick="insertEmoji('😀')" class="hover:bg-gray-800 rounded-lg p-1">😀</button>
                        <button type="button" onclick="insertEmoji('😂')" class="hover:bg-gray-800 rounded-lg p-1">😂</button>
                        <button type="button" onclick="insertEmoji('😍')" class="hover:bg-gray-800 rounded-lg p-1">😍</button>
                        <button type="button" onclick="insertEmoji('🙏')" class="hover:bg-gray-800 rounded-lg p-1">🙏</button>
                        <button type="button" onclick="insertEmoji('👍')" class="hover:bg-gray-800 rounded-lg p-1">👍</button>
                        <button type="button" onclick="insertEmoji('🔥')" class="hover:bg-gray-800 rounded-lg p-1">🔥</button>
                        <button type="button" onclick="insertEmoji('✨')" class="hover:bg-gray-800 rounded-lg p-1">✨</button>
                        <button type="button" onclick="insertEmoji('❤️')" class="hover:bg-gray-800 rounded-lg p-1">❤️</button>
                    </div>
                </div>

                <div class="bg-gray-900 border-t border-gray-800 p-4">
                    <div id="attachmentPreview" class="hidden mb-3 bg-gray-800 border border-gray-700 rounded-2xl px-4 py-3 text-sm text-gray-200 flex items-center justify-between gap-3"></div>
                    <div class="flex items-end gap-3">
                        <button type="button" onclick="toggleEmojiPanel()" class="bg-gray-800 hover:bg-gray-700 w-11 h-11 rounded-2xl flex items-center justify-center text-xl">☺</button>
                        <input id="chatFile" type="file" class="hidden" accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.txt">
                        <button type="button" onclick="document.getElementById('chatFile').click()" class="bg-gray-800 hover:bg-gray-700 w-11 h-11 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <textarea id="chatInput" rows="1" placeholder="Digite uma mensagem..." class="flex-1 max-h-40 bg-gray-800 border border-gray-700 rounded-2xl px-4 py-3 resize-none focus:outline-none focus:border-emerald-500"></textarea>
                        <button type="button" onclick="sendChatMessage()" class="bg-emerald-600 hover:bg-emerald-700 w-11 h-11 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        let allLeads = [];
        let currentLeads = null;
        let currentVerTodosLeads = [];
        let activeChat = null;
        let chatPollTimer = null;
        let chatLastSignature = '';
        const STAGES = <?= json_encode($stages, JSON_UNESCAPED_UNICODE) ?>;
        const STAGE_IDS = <?= json_encode($stageIds, JSON_UNESCAPED_UNICODE) ?>;
        const FIRST_STAGE = <?= json_encode($firstStage, JSON_UNESCAPED_UNICODE) ?>;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function diasSemContato(data) {
            if (!data) return 999;
            const diff = Math.abs(new Date() - new Date(data));
            return Math.ceil(diff / (1000 * 60 * 60 * 24));
        }

        function loadPipeline(filteredLeads = null) {
            const leads = filteredLeads || allLeads;
            currentLeads = leads;
            document.querySelectorAll('.kanban-column').forEach(col => col.innerHTML = '');

            let totalValor = 0;
            const counts = {};
            const columnTotals = {};

            STAGE_IDS.forEach(k => {
                counts[k] = 0;
                columnTotals[k] = 0;
            });

            leads.forEach(lead => {
                let etapa = String(lead.etapa || (lead.status === 'novo' ? FIRST_STAGE : null) || FIRST_STAGE);
                if (!STAGE_IDS.includes(etapa)) {
                    etapa = FIRST_STAGE;
                }
                const valor = parseFloat(lead.valor) || 0;
                const dias = diasSemContato(lead.data_ultimo_contato);

                totalValor += valor;
                if (counts[etapa] === undefined) {
                    counts[etapa] = 0;
                    columnTotals[etapa] = 0;
                }
                counts[etapa] += 1;
                columnTotals[etapa] += valor;

                let classeFrio = '';
                if (dias > 20) classeFrio = 'lead-muito-frio';
                else if (dias > 10) classeFrio = 'lead-frio';

                const html = `
                    <div data-id="${escapeHtml(lead.id)}" onclick="viewLead('${escapeHtml(lead.id)}')" class="card bg-gray-800 rounded-2xl p-4 cursor-pointer border border-gray-700 ${classeFrio}">
                        <div class="flex justify-between items-start">
                            <h4 class="font-semibold text-sm break-words">${escapeHtml(lead.nome)}</h4>
                        </div>
                        <p class="text-gray-400 text-xs mt-1 break-words">${escapeHtml(lead.telefone)}</p>
                        ${lead.interesse ? `<p class="text-xs text-gray-500 mt-2 break-words">${escapeHtml(lead.interesse)}</p>` : ''}
                        ${dias < 999 ? `<p class="text-xs mt-3 ${dias > 20 ? 'text-red-400' : 'text-amber-400'}">Sem contato há ${dias} dias</p>` : ''}
                        ${valor > 0 ? `<p class="text-emerald-400 font-medium mt-3">R$ ${valor.toLocaleString('pt-BR')}</p>` : ''}

                        <div class="flex justify-start gap-3 mt-4 pt-3 border-t border-gray-700 text-xs">
                            <button onclick="event.stopPropagation(); editLead('${escapeHtml(lead.id)}')" class="text-blue-400 hover:text-blue-300 flex items-center gap-1">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button onclick="event.stopPropagation(); deleteLead('${escapeHtml(lead.id)}')" class="text-red-400 hover:text-red-300 flex items-center gap-1">
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
                        body: `action=move&id=${encodeURIComponent(id)}&etapa=${encodeURIComponent(newEtapa)}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            loadPipeline(currentLeads);
                            return;
                        }
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
            const stageName = STAGES[etapa] || 'Etapa ' + etapa;
            document.getElementById('modalVerTitulo').textContent = `Leads - ${stageName}`;
            document.getElementById('modalVerTodos').classList.remove('hidden');

            const filtered = allLeads.filter(l => String(l.etapa) === etapa);
            currentVerTodosLeads = filtered;
            document.getElementById('searchVerTodos').value = '';
            renderListaVerTodos(filtered);
        }

        function renderListaVerTodos(leads) {
            const container = document.getElementById('listaVerTodos');
            container.innerHTML = leads.map(lead => `
                <div onclick="closeVerTodos(); viewLead('${escapeHtml(lead.id)}');" class="bg-gray-800 hover:bg-gray-700 rounded-2xl p-5 cursor-pointer">
                    <div class="flex justify-between">
                        <strong>${escapeHtml(lead.nome)}</strong>
                        <span class="text-emerald-400">R$ ${(parseFloat(lead.valor)||0).toLocaleString('pt-BR')}</span>
                    </div>
                    <p class="text-gray-400">${escapeHtml(lead.telefone)}</p>
                </div>
            `).join('');
        }

        function closeVerTodos() {
            document.getElementById('modalVerTodos').classList.add('hidden');
        }

        function exportToCSV() {
            const leadsToExport = currentLeads ?? allLeads;
            if (!leadsToExport.length) return alert("Não há leads para exportar");

            const csvValue = value => `"${String(value ?? '').replace(/"/g, '""')}"`;

            let csv = "ID,Nome,Telefone,Interesse,Valor,Origem,Status,Etapa,Último Contato,Data Cadastro\n";
            leadsToExport.forEach(l => {
                csv += [
                    l.id,
                    l.nome,
                    l.telefone,
                    l.interesse,
                    l.valor || 0,
                    l.origem,
                    l.status,
                    STAGES[l.etapa] || l.etapa,
                    l.data_ultimo_contato || '',
                    l.created_at || ''
                ].map(csvValue).join(',') + "\n";
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
            const lead = allLeads.find(l => String(l.id) === String(id));
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
        openChatOverlay(id);
        return;
    }

    // se for lead normal
    window.location.href = `lead.php?id=${id}`;
}

        function renderChatMessages(mensagens) {
            const container = document.getElementById('chatMessages');
            const shouldStickToBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 80;

            container.innerHTML = mensagens.map(msg => `
                <div class="flex ${msg.fromMe ? 'justify-end' : 'justify-start'}">
                    <div class="${msg.fromMe ? 'bg-emerald-600 text-white rounded-br-md' : 'bg-gray-800 text-gray-100 rounded-bl-md'} px-4 py-3 rounded-2xl max-w-[78%] shadow-lg">
                        ${renderChatMedia(msg)}
                        ${msg.texto ? `<p class="whitespace-pre-wrap break-words leading-relaxed ${msg.mediaUrl ? 'mt-2' : ''}">${escapeHtml(msg.texto)}</p>` : ''}
                        <span class="text-[11px] text-gray-300 flex items-center justify-end gap-1 mt-2">
                            <span>${escapeHtml(msg.hora)}</span>
                            ${renderMessageStatus(msg)}
                        </span>
                    </div>
                </div>
            `).join('');

            if (shouldStickToBottom) {
                container.scrollTop = container.scrollHeight;
            }
        }

        function renderMessageStatus(msg) {
            if (!msg.fromMe) return '';

            const status = msg.status || 'sent';
            const icons = {
                pending: '<i class="fas fa-clock text-gray-300" title="Enviando"></i>',
                sent: '<i class="fas fa-check text-gray-300" title="Enviada"></i>',
                delivered: '<span class="text-gray-300" title="Entregue">✓✓</span>',
                read: '<span class="text-sky-300 font-semibold" title="Visualizada">✓✓</span>',
                played: '<span class="text-sky-300 font-semibold" title="Reproduzida">✓✓</span>',
                error: '<i class="fas fa-triangle-exclamation text-red-300" title="Erro"></i>',
            };

            return icons[status] || icons.sent;
        }

        function renderChatMedia(msg) {
            if (!msg.mediaUrl) return '';

            const url = escapeHtml(msg.mediaUrl);
            const mime = msg.mediaMime || '';
            const fileName = escapeHtml(msg.mediaFileName || 'arquivo');

            if (mime.startsWith('image/')) {
                return `<a href="${url}" target="_blank"><img src="${url}" class="max-h-80 rounded-xl object-contain bg-black/20"></a>`;
            }
            if (mime.startsWith('video/')) {
                return `<video src="${url}" controls class="max-h-80 rounded-xl bg-black/20"></video>`;
            }
            if (mime.startsWith('audio/')) {
                return `
                    <div class="space-y-2">
                        <audio src="${url}" controls class="w-72 max-w-full"></audio>
                        <button type="button" onclick="transcribeAudio('${url}')" class="text-xs bg-gray-950/40 hover:bg-gray-950/60 px-3 py-2 rounded-xl">Transcrever áudio</button>
                    </div>
                `;
            }

            return `<a href="${url}" target="_blank" class="flex items-center gap-3 bg-gray-950/35 hover:bg-gray-950/50 rounded-xl px-3 py-3"><i class="fas fa-file"></i><span class="break-all">${fileName}</span></a>`;
        }

        function transcribeAudio(url) {
            alert('O botão já está pronto, mas a transcrição precisa conectar uma API de fala para texto.');
        }

        function loadChatMessages(forceScroll = false) {
            if (!activeChat) return;

            fetch(`api_chat.php?id=${encodeURIComponent(activeChat.id)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) return;

                    const signature = JSON.stringify(data.mensagens.map(msg => [msg.texto, msg.data, msg.fromMe]));
                    if (signature === chatLastSignature) return;

                    chatLastSignature = signature;
                    renderChatMessages(data.mensagens);

                    if (forceScroll) {
                        const container = document.getElementById('chatMessages');
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }

        function openChatOverlay(id) {
            const lead = allLeads.find(l => String(l.id) === String(id));
            if (!lead) return;

            activeChat = lead;
            chatLastSignature = '';
            document.getElementById('chatName').textContent = lead.nome || 'Cliente WhatsApp';
            document.getElementById('chatPhone').textContent = lead.telefone || '';
            document.getElementById('chatInput').value = '';
            removeAttachment();
            document.getElementById('emojiPanel').classList.add('hidden');
            document.getElementById('chatOverlay').classList.remove('hidden');

            loadChatMessages(true);
            clearInterval(chatPollTimer);
            chatPollTimer = setInterval(loadChatMessages, 2500);
            setTimeout(() => document.getElementById('chatInput').focus(), 50);
        }

        function closeChatOverlay() {
            document.getElementById('chatOverlay').classList.add('hidden');
            clearInterval(chatPollTimer);
            chatPollTimer = null;
            activeChat = null;
        }

        function toggleEmojiPanel() {
            document.getElementById('emojiPanel').classList.toggle('hidden');
        }

        function insertEmoji(emoji) {
            const input = document.getElementById('chatInput');
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
            input.focus();
            input.selectionStart = input.selectionEnd = start + emoji.length;
        }

        function updateAttachmentPreview() {
            const input = document.getElementById('chatFile');
            const preview = document.getElementById('attachmentPreview');
            const file = input.files?.[0];

            if (!file) {
                preview.classList.add('hidden');
                preview.innerHTML = '';
                return;
            }

            preview.classList.remove('hidden');
            preview.innerHTML = `
                <span class="truncate"><i class="fas fa-paperclip mr-2"></i>${escapeHtml(file.name)}</span>
                <button type="button" onclick="removeAttachment()" class="text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
            `;
        }

        function removeAttachment() {
            const input = document.getElementById('chatFile');
            if (input) input.value = '';
            const preview = document.getElementById('attachmentPreview');
            if (preview) {
                preview.classList.add('hidden');
                preview.innerHTML = '';
            }
        }

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const fileInput = document.getElementById('chatFile');
            const texto = input.value.trim();
            const file = fileInput.files?.[0];
            if (!activeChat || (!texto && !file)) return;

            const formData = new FormData();
            formData.append('numero', activeChat.telefone);
            formData.append('mensagem', texto);
            if (file) {
                formData.append('arquivo', file);
            }

            input.disabled = true;
            fetch('enviar.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    input.value = '';
                    removeAttachment();
                    loadChatMessages(true);
                } else {
                    alert(res.erro || 'Erro ao enviar mensagem');
                }
            })
            .catch(() => alert('Erro de conexão'))
            .finally(() => {
                input.disabled = false;
                input.focus();
            });
        }

        function saveLead(e) {
            e.preventDefault();
            const id = document.getElementById('leadId').value;
            const formData = new FormData();
            formData.append('action', id ? 'update' : 'create');
            formData.append('id', id);
            formData.append('nome', document.getElementById('nome').value);
            formData.append('telefone', document.getElementById('telefone').value);
            formData.append('valor', document.getElementById('valor').value);
            formData.append('data_ultimo_contato', document.getElementById('data_ultimo_contato').value);
            formData.append('interesse', document.getElementById('interesse').value);
            formData.append('origem', document.getElementById('origem').value);
            formData.append('status', document.getElementById('status').value);
            formData.append('etapa', document.getElementById('etapa').value);

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
                body: `action=delete&id=${encodeURIComponent(id)}`
            }).then(r => r.json()).then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
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
            interesse: c.interesse || c.mensagens?.slice(-1)[0]?.texto || '',
            valor: c.valor || 0,
            origem: c.origem || 'WhatsApp',
            status: c.status || 'novo',
            etapa: c.etapa || FIRST_STAGE,
            data_ultimo_contato: c.data_ultimo_contato || c.mensagens?.slice(-1)[0]?.data || '',
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
            document.getElementById('searchVerTodos').addEventListener('input', (event) => {
                const term = event.target.value.toLowerCase().trim();
                renderListaVerTodos(currentVerTodosLeads.filter(lead =>
                    !term ||
                    (lead.nome && lead.nome.toLowerCase().includes(term)) ||
                    (lead.telefone && String(lead.telefone).includes(term)) ||
                    (lead.interesse && lead.interesse.toLowerCase().includes(term))
                ));
            });
            document.getElementById('chatInput').addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendChatMessage();
                }
            });
            document.getElementById('chatFile').addEventListener('change', updateAttachmentPreview);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && activeChat) {
                    closeChatOverlay();
                }
            });
};
    </script>

    <script>
        function openNewClientsModal() {
  document.getElementById('modalNovosClientes').classList.remove('hidden');
}

function closeNewClientsModal() {
  document.getElementById('modalNovosClientes').classList.add('hidden');
}

function filterNewClients() {
    const startDate = document.getElementById('newClientsStart').value;
    const endDate = document.getElementById('newClientsEnd').value;
    const startTime = document.getElementById('newClientsStartTime').value;
    const endTime = document.getElementById('newClientsEndTime').value;

    if (!startDate || !endDate) return alert('Preencha data início e fim');

    const start = new Date(`${startDate}T${startTime || '00:00'}`);
    const end = new Date(`${endDate}T${endTime || '23:59'}`);

    const filtered = allLeads.filter(lead => {
        const created = new Date(lead.created_at); // assumindo que created_at existe
        return created >= start && created <= end;
    });

    const resultContainer = document.getElementById('newClientsResult');
    resultContainer.innerHTML = filtered.length
        ? `<p>${filtered.length} clientes encontrados</p>` + filtered.map(l => `<div>${escapeHtml(l.nome)} - ${escapeHtml(l.telefone)}</div>`).join('')
        : '<p>Nenhum cliente encontrado nesse período</p>';
}
    </script>
</body>
</html>
