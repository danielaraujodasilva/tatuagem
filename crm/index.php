<?php require 'config.php'; ?>
<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
?>

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
                    <?php if (auth_has_role(['adm'])): ?>
                    <a href="../auth/usuarios.php"
                       class="bg-gray-800 hover:bg-gray-700 px-4 py-3 rounded-2xl flex items-center" title="Usuarios">
                        <i class="fas fa-users"></i>
                    </a>
                    <a href="configuracoes.php"
                       class="bg-gray-800 hover:bg-gray-700 px-4 py-3 rounded-2xl flex items-center" title="Configuracoes">
                        <i class="fas fa-gear"></i>
                    </a>
                    <?php endif; ?>
                    <a href="../auth/logout.php"
                       class="bg-gray-800 hover:bg-gray-700 px-4 py-3 rounded-2xl flex items-center" title="Sair">
                        <i class="fas fa-right-from-bracket"></i>
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
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="openScheduleOverlay()" class="bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-2xl font-semibold flex items-center gap-2">
                            <i class="fas fa-calendar-plus"></i> Agendar
                        </button>
                        <button onclick="closeChatOverlay()" class="text-gray-400 hover:text-white text-3xl leading-none">&times;</button>
                    </div>
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
                        <button id="recordAudioBtn" type="button" onclick="toggleAudioRecording()" class="bg-gray-800 hover:bg-gray-700 w-11 h-11 rounded-2xl flex items-center justify-center">
                            <i class="fas fa-microphone"></i>
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

    <!-- Overlay Agendamento -->
    <div id="scheduleOverlay" class="hidden fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4">
        <div class="bg-gray-900 border border-gray-800 rounded-3xl w-full max-w-4xl max-h-[92vh] overflow-y-auto p-6">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-2xl font-bold flex items-center gap-3"><i class="fas fa-calendar-plus text-emerald-400"></i> Agendar tatuagem</h3>
                    <p class="text-gray-400 mt-1">Busque um cliente existente ou crie o cadastro basico com nome e telefone.</p>
                </div>
                <button type="button" onclick="closeScheduleOverlay()" class="text-gray-400 hover:text-white text-3xl leading-none">&times;</button>
            </div>

            <form id="scheduleForm" onsubmit="saveSchedule(event)" class="space-y-5">
                <input type="hidden" id="scheduleClienteId" name="cliente_id">
                <div class="bg-gray-950/70 border border-gray-800 rounded-2xl p-4">
                    <label class="block text-sm mb-2">Pesquisar cliente</label>
                    <input type="text" id="scheduleClientSearch" placeholder="Digite nome, telefone ou e-mail" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    <div id="scheduleClientResults" class="hidden mt-3 space-y-2"></div>
                    <p id="scheduleClientNotice" class="text-sm text-amber-300 mt-3"></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm mb-1">Nome do cliente *</label>
                        <input type="text" id="scheduleNome" name="nome" required class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Telefone *</label>
                        <input type="tel" id="scheduleTelefone" name="telefone" required class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Data *</label>
                        <input type="date" id="scheduleData" name="data_tatuagem" required class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm mb-1">Hora inicio *</label>
                            <input type="time" id="scheduleHoraInicio" name="hora_inicio" required class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Hora fim</label>
                            <input type="time" id="scheduleHoraFim" name="hora_fim" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Valor da tatuagem (R$)</label>
                        <input type="number" step="0.01" id="scheduleValor" name="valor" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Pomadas anestesicas</label>
                        <input type="number" min="0" step="1" id="schedulePomadas" name="pomadas_anestesicas" value="0" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Descricao / arte pretendida</label>
                        <input type="text" id="scheduleDescricao" name="descricao" placeholder="Ex.: Fechamento de braço, fine line, cobertura..." class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Observacoes</label>
                        <textarea id="scheduleObservacoes" name="observacoes" rows="3" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3 resize-none"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Arte de referencia (opcional)</label>
                        <input type="file" id="scheduleReferencia" name="referencia" accept="image/*,.pdf" class="w-full bg-gray-800 border border-gray-700 rounded-2xl px-5 py-3">
                    </div>
                </div>

                <div id="scheduleResult" class="hidden bg-emerald-950/40 border border-emerald-700 rounded-2xl p-4 text-sm"></div>

                <div class="flex flex-col md:flex-row gap-3 pt-2">
                    <button type="button" onclick="closeScheduleOverlay()" class="flex-1 py-4 text-gray-300 hover:text-white font-medium rounded-2xl">Cancelar</button>
                    <button type="submit" id="scheduleSubmit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 py-4 rounded-2xl font-semibold">
                        Salvar agendamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let allLeads = [];
        let currentLeads = null;
        let currentVerTodosLeads = [];
        let activeChat = null;
        let chatPollTimer = null;
        let chatLastSignature = '';
        const pendingTranscriptions = {};
        let mediaRecorder = null;
        let recordedAudioChunks = [];
        let recordedAudioFile = null;
        let recordingTimer = null;
        let recordingStartedAt = 0;
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

        function textoSeguro(value) {
            return String(value ?? '');
        }

        function loadPipeline(filteredLeads = null) {
            const leads = Array.isArray(filteredLeads) ? filteredLeads : allLeads;
            currentLeads = leads;
            document.querySelectorAll('.kanban-column').forEach(col => col.innerHTML = '');

            let totalValor = 0;
            const counts = {};
            const columnTotals = {};

            STAGE_IDS.forEach(k => {
                counts[k] = 0;
                columnTotals[k] = 0;
            });

            leads.forEach(leadRaw => {
                const lead = leadRaw || {};
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

                const leadId = textoSeguro(lead.id);
                const html = `
                    <div data-id="${escapeHtml(leadId)}" onclick="viewLead('${escapeHtml(leadId)}')" class="card bg-gray-800 rounded-2xl p-4 cursor-pointer border border-gray-700 ${classeFrio}">
                        <div class="flex justify-between items-start">
                            <h4 class="font-semibold text-sm break-words">${escapeHtml(lead.nome || 'Cliente')}</h4>
                        </div>
                        <p class="text-gray-400 text-xs mt-1 break-words">${escapeHtml(lead.telefone)}</p>
                        ${lead.interesse ? `<p class="text-xs text-gray-500 mt-2 break-words">${escapeHtml(lead.interesse)}</p>` : ''}
                        ${dias < 999 ? `<p class="text-xs mt-3 ${dias > 20 ? 'text-red-400' : 'text-amber-400'}">Sem contato há ${dias} dias</p>` : ''}
                        ${valor > 0 ? `<p class="text-emerald-400 font-medium mt-3">R$ ${valor.toLocaleString('pt-BR')}</p>` : ''}

                        <div class="flex justify-start gap-3 mt-4 pt-3 border-t border-gray-700 text-xs">
                            <button onclick="event.stopPropagation(); editLead('${escapeHtml(leadId)}')" class="text-blue-400 hover:text-blue-300 flex items-center gap-1">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button onclick="event.stopPropagation(); deleteLead('${escapeHtml(leadId)}')" class="text-red-400 hover:text-red-300 flex items-center gap-1">
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
    refreshLeads();
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

            mensagens.forEach(msg => {
                const key = transcriptionKey(msg.messageId || '', msg.mediaUrl || '');
                if (pendingTranscriptions[key] && (msg.transcricao || msg.transcricao_erro)) {
                    clearInterval(pendingTranscriptions[key].timer);
                    delete pendingTranscriptions[key];
                }
            });

            container.innerHTML = mensagens.map(msg => `
                <div class="flex ${msg.fromMe ? 'justify-end' : 'justify-start'}">
                    <div class="${msg.fromMe ? 'bg-emerald-600 text-white rounded-br-md' : 'bg-gray-800 text-gray-100 rounded-bl-md'} px-4 py-3 rounded-2xl max-w-[78%] shadow-lg">
                        ${renderChatMedia(msg)}
                        ${msg.texto ? `<p class="whitespace-pre-wrap break-words leading-relaxed ${msg.mediaUrl ? 'mt-2' : ''}">${escapeHtml(msg.texto)}</p>` : ''}
                        ${msg.transcricao ? `<div class="mt-3 bg-gray-950/35 rounded-xl px-3 py-2 text-sm"><strong>Transcrição:</strong> ${escapeHtml(msg.transcricao)}</div>` : ''}
                        ${msg.transcricao_erro ? `<div class="mt-3 bg-red-950/40 border border-red-800/60 rounded-xl px-3 py-2 text-sm text-red-100"><strong>Erro na transcrição:</strong> ${escapeHtml(msg.transcricao_erro)}</div>` : ''}
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
            if (!msg.fromMe && !msg.status) return '';

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
            const pending = pendingTranscriptions[transcriptionKey(msg.messageId || '', msg.mediaUrl || '')];

            if (mime.startsWith('image/')) {
                return `<a href="${url}" target="_blank"><img src="${url}" class="max-h-80 rounded-xl object-contain bg-black/20"></a>`;
            }
            if (mime.startsWith('video/')) {
                return `<video src="${url}" controls class="max-h-80 rounded-xl bg-black/20"></video>`;
            }
            if (mime.startsWith('audio/') || msg.tipo === 'audio') {
                return `
                    <div class="space-y-2">
                        <audio src="${url}" controls class="w-72 max-w-full"></audio>
                        ${pending ? renderTranscriptionProgress(pending) : `<button type="button" onclick="transcribeAudio(this, '${escapeHtml(msg.messageId || '')}', '${url}')" class="text-xs bg-gray-950/40 hover:bg-gray-950/60 px-3 py-2 rounded-xl">Transcrever audio</button>`}
                    </div>
                `;
            }

            return `<a href="${url}" target="_blank" class="flex items-center gap-3 bg-gray-950/35 hover:bg-gray-950/50 rounded-xl px-3 py-3"><i class="fas fa-file"></i><span class="break-all">${fileName}</span></a>`;
        }

        function transcriptionKey(messageId, url) {
            return messageId || url;
        }

        function renderTranscriptionProgress(state) {
            const progress = Number(state?.progress) || 5;
            const elapsed = Math.max(0, Math.floor((Date.now() - (state?.startedAt || Date.now())) / 1000));
            const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const seconds = String(elapsed % 60).padStart(2, '0');
            const safeProgress = Math.max(5, Math.min(95, progress));
            let phase = 'Preparando audio...';
            if (elapsed >= 10) phase = 'Carregando modelo small...';
            if (elapsed >= 45) phase = 'Transcrevendo com mais qualidade...';
            if (elapsed >= 120) phase = 'Ainda trabalhando. A primeira vez pode demorar.';
            if (elapsed >= 240) phase = 'Ainda rodando. Se passar de 6 min, tente novamente.';

            return `
                <div class="bg-gray-950/40 rounded-xl px-3 py-2 w-72 max-w-full">
                    <div class="flex justify-between text-[11px] text-gray-200 mb-1">
                        <span>${phase}</span>
                        <span>${minutes}:${seconds}</span>
                    </div>
                    <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-400 rounded-full transition-all" style="width: ${safeProgress}%"></div>
                    </div>
                    <div class="text-[10px] text-gray-400 mt-1">${safeProgress}% estimado</div>
                </div>
            `;
        }

        function transcribeAudio(button, messageId, url) {
            const key = transcriptionKey(messageId, url);
            const oldText = button.textContent;
            button.disabled = true;
            button.textContent = 'Transcrevendo...';
            pendingTranscriptions[key] = {
                progress: 8,
                startedAt: Date.now(),
                timer: setInterval(() => {
                    const current = pendingTranscriptions[key];
                    if (!current) return;
                    const elapsed = Math.floor((Date.now() - current.startedAt) / 1000);
                    const nextStep = elapsed < 30 ? 6 : (elapsed < 120 ? 2 : 1);
                    current.progress = Math.min(95, current.progress + nextStep);
                    loadChatMessages(true);
                }, 2000)
            };
            loadChatMessages(true);

            fetch('transcrever_audio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messageId, mediaUrl: url, model: 'small' })
            })
            .then(async r => {
                const raw = await r.text();
                try {
                    return JSON.parse(raw);
                } catch (e) {
                    const preview = raw.trim().slice(0, 500) || `HTTP ${r.status}`;
                    throw new Error(preview);
                }
            })
            .then(data => {
                if (data.ok) {
                    clearInterval(pendingTranscriptions[key]?.timer);
                    delete pendingTranscriptions[key];
                    loadChatMessages(true);
                } else {
                    alert(data.error || 'Nao foi possivel transcrever o audio');
                    clearInterval(pendingTranscriptions[key]?.timer);
                    delete pendingTranscriptions[key];
                    loadChatMessages(true);
                }
            })
            .catch(error => {
                console.warn('Transcricao ainda pode estar rodando no servidor:', error);
                if (pendingTranscriptions[key]) {
                    pendingTranscriptions[key].progress = Math.max(pendingTranscriptions[key].progress, 70);
                }
                loadChatMessages(true);
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = oldText;
            });
        }

        function loadChatMessages(forceScroll = false) {
            if (!activeChat) return;

            fetch(`api_chat.php?id=${encodeURIComponent(activeChat.id)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) return;

                    const signature = JSON.stringify(data.mensagens.map(msg => [msg.messageId, msg.texto, msg.data, msg.fromMe, msg.rawFromMe, msg.de, msg.status, msg.status_updated_at, msg.transcricao, msg.transcricao_erro, msg.mediaUrl]));
                    if (signature === chatLastSignature && Object.keys(pendingTranscriptions).length === 0) return;

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
            chatPollTimer = setInterval(loadChatMessages, 1000);
            setTimeout(() => document.getElementById('chatInput').focus(), 50);
        }

        function closeChatOverlay() {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                stopAudioRecording(true);
            }
            document.getElementById('chatOverlay').classList.add('hidden');
            clearInterval(chatPollTimer);
            chatPollTimer = null;
            activeChat = null;
        }

        function onlyDigits(value) {
            return String(value ?? '').replace(/\D+/g, '');
        }

        function setScheduleNotice(message, tone = 'amber') {
            const notice = document.getElementById('scheduleClientNotice');
            notice.textContent = message || '';
            notice.className = `text-sm mt-3 ${tone === 'green' ? 'text-emerald-300' : 'text-amber-300'}`;
        }

        function openScheduleOverlay() {
            if (!activeChat) return;

            const form = document.getElementById('scheduleForm');
            form.reset();
            document.getElementById('scheduleClienteId').value = '';
            document.getElementById('scheduleNome').value = activeChat.nome && activeChat.nome !== 'Cliente WhatsApp' ? activeChat.nome : '';
            document.getElementById('scheduleTelefone').value = activeChat.telefone || '';
            document.getElementById('scheduleDescricao').value = activeChat.interesse || '';
            document.getElementById('schedulePomadas').value = '0';
            document.getElementById('scheduleClientSearch').value = activeChat.telefone || activeChat.nome || '';
            document.getElementById('scheduleClientResults').classList.add('hidden');
            document.getElementById('scheduleClientResults').innerHTML = '';
            document.getElementById('scheduleResult').classList.add('hidden');
            document.getElementById('scheduleResult').innerHTML = '';
            setScheduleNotice('Vou procurar esse telefone na ficha para evitar cadastro duplicado.');

            document.getElementById('scheduleOverlay').classList.remove('hidden');
            searchScheduleClients(activeChat.telefone || activeChat.nome || '', true);
            setTimeout(() => document.getElementById('scheduleData').focus(), 80);
        }

        function closeScheduleOverlay() {
            document.getElementById('scheduleOverlay').classList.add('hidden');
        }

        let scheduleSearchTimer = null;
        function scheduleSearchChanged() {
            clearTimeout(scheduleSearchTimer);
            const term = document.getElementById('scheduleClientSearch').value.trim();
            scheduleSearchTimer = setTimeout(() => searchScheduleClients(term, false), 250);
        }

        async function searchScheduleClients(term, autoSelectByPhone = false) {
            const results = document.getElementById('scheduleClientResults');
            results.innerHTML = '';
            results.classList.add('hidden');

            if (!term || term.length < 2) {
                setScheduleNotice('Digite pelo menos 2 caracteres para buscar na ficha.');
                return;
            }

            try {
                const response = await fetch(`agendamento_clientes.php?q=${encodeURIComponent(term)}`);
                const data = await response.json();
                const clientes = Array.isArray(data.clientes) ? data.clientes : [];

                if (!clientes.length) {
                    document.getElementById('scheduleClienteId').value = '';
                    setScheduleNotice('Cliente nao encontrado. Ao salvar, o sistema cria um cadastro basico com nome e telefone.');
                    return;
                }

                const activeDigits = onlyDigits(document.getElementById('scheduleTelefone').value || activeChat?.telefone || '');
                const exact = clientes.find(c => activeDigits && onlyDigits(c.telefone) === activeDigits);
                if (autoSelectByPhone && exact) {
                    selectScheduleClient(exact);
                    setScheduleNotice('Cliente ja encontrado na ficha. Vou usar esse cadastro para o agendamento.', 'green');
                    return;
                }

                results.innerHTML = clientes.map(cliente => `
                    <button type="button" onclick='selectScheduleClient(${JSON.stringify(cliente).replace(/'/g, '&#039;')})' class="w-full text-left bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-2xl px-4 py-3">
                        <strong>${escapeHtml(cliente.nome || 'Cliente')}</strong>
                        <span class="block text-sm text-gray-400">${escapeHtml(cliente.telefone || '')}${cliente.email ? ' · ' + escapeHtml(cliente.email) : ''}</span>
                    </button>
                `).join('');
                results.classList.remove('hidden');
                setScheduleNotice('Selecione o cliente correto para evitar duplicidade.');
            } catch (error) {
                setScheduleNotice('Nao consegui buscar clientes agora. Ainda da para criar cadastro basico ao salvar.');
            }
        }

        function selectScheduleClient(cliente) {
            document.getElementById('scheduleClienteId').value = cliente.id || '';
            document.getElementById('scheduleNome').value = cliente.nome || '';
            document.getElementById('scheduleTelefone').value = cliente.telefone || '';
            document.getElementById('scheduleClientSearch').value = `${cliente.nome || ''} ${cliente.telefone || ''}`.trim();
            document.getElementById('scheduleClientResults').classList.add('hidden');
            document.getElementById('scheduleClientResults').innerHTML = '';
            setScheduleNotice('Cliente existente selecionado. O agendamento ficara vinculado a essa ficha.', 'green');
        }

        async function saveSchedule(event) {
            event.preventDefault();

            const form = document.getElementById('scheduleForm');
            if (!form.reportValidity()) return;

            const submit = document.getElementById('scheduleSubmit');
            const resultBox = document.getElementById('scheduleResult');
            const formData = new FormData(form);

            submit.disabled = true;
            submit.textContent = 'Salvando...';

            try {
                const response = await fetch('agendamento_salvar.php', { method: 'POST', body: formData });
                const result = await response.json().catch(() => ({}));

                if (!response.ok || !result.ok) {
                    alert(result.error || 'Nao foi possivel salvar o agendamento.');
                    return;
                }

                const fichaUrl = new URL(result.ficha_url, window.location.href).href;
                const agendaUrl = result.agenda_url ? new URL(result.agenda_url, window.location.href).href : '';
                resultBox.classList.remove('hidden');
                resultBox.innerHTML = `
                    <strong>${escapeHtml(result.message || 'Agendamento salvo.')}</strong>
                    ${agendaUrl ? `<div class="mt-3"><a href="${escapeHtml(agendaUrl)}" target="_blank" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-xl font-semibold"><i class="fas fa-calendar-days"></i> Abrir na agenda</a></div>` : ''}
                    <div class="mt-2 text-gray-200">Link para o cliente completar a ficha:</div>
                    <div class="mt-2 flex flex-col md:flex-row gap-2">
                        <input id="scheduleFichaLink" readonly value="${escapeHtml(fichaUrl)}" class="flex-1 bg-gray-950 border border-gray-700 rounded-xl px-3 py-2 text-sm">
                        <button type="button" onclick="copyScheduleFichaLink()" class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-xl">Copiar link</button>
                    </div>
                `;

                refreshLeads();
            } catch (error) {
                alert('Erro de conexao ao salvar agendamento.');
            } finally {
                submit.disabled = false;
                submit.textContent = 'Salvar agendamento';
            }
        }

        function copyScheduleFichaLink() {
            const input = document.getElementById('scheduleFichaLink');
            input.select();
            document.execCommand('copy');
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
            const file = recordedAudioFile || input.files?.[0];

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
            recordedAudioFile = null;
            const preview = document.getElementById('attachmentPreview');
            if (preview) {
                preview.classList.add('hidden');
                preview.innerHTML = '';
            }
        }

        function updateRecordingPreview() {
            const preview = document.getElementById('attachmentPreview');
            const elapsed = Math.max(0, Math.floor((Date.now() - recordingStartedAt) / 1000));
            const minutes = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const seconds = String(elapsed % 60).padStart(2, '0');
            preview.classList.remove('hidden');
            preview.innerHTML = `
                <span class="flex items-center gap-2 text-red-200"><i class="fas fa-circle text-red-400 text-[10px]"></i> Gravando ${minutes}:${seconds}</span>
                <button type="button" onclick="stopAudioRecording(true)" class="text-red-200 hover:text-white text-xs">Cancelar</button>
            `;
        }

        async function toggleAudioRecording() {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                stopAudioRecording(false);
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                alert('Seu navegador nao liberou gravacao de audio aqui.');
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const options = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                    ? { mimeType: 'audio/webm;codecs=opus' }
                    : {};
                mediaRecorder = new MediaRecorder(stream, options);
                recordedAudioChunks = [];
                recordedAudioFile = null;

                mediaRecorder.ondataavailable = event => {
                    if (event.data && event.data.size > 0) {
                        recordedAudioChunks.push(event.data);
                    }
                };

                mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(track => track.stop());
                    clearInterval(recordingTimer);
                    recordingTimer = null;
                    document.getElementById('recordAudioBtn').classList.remove('bg-red-600', 'hover:bg-red-700');
                    document.getElementById('recordAudioBtn').classList.add('bg-gray-800', 'hover:bg-gray-700');
                };

                recordingStartedAt = Date.now();
                mediaRecorder.start();
                document.getElementById('recordAudioBtn').classList.remove('bg-gray-800', 'hover:bg-gray-700');
                document.getElementById('recordAudioBtn').classList.add('bg-red-600', 'hover:bg-red-700');
                updateRecordingPreview();
                recordingTimer = setInterval(updateRecordingPreview, 500);
            } catch (error) {
                alert('Nao consegui acessar o microfone: ' + (error.message || error));
            }
        }

        function stopAudioRecording(cancel = false) {
            if (!mediaRecorder || mediaRecorder.state !== 'recording') return;

            const recorder = mediaRecorder;
            recorder.addEventListener('stop', () => {
                if (!cancel && recordedAudioChunks.length) {
                    const mime = recorder.mimeType || 'audio/webm';
                    const blob = new Blob(recordedAudioChunks, { type: mime });
                    recordedAudioFile = new File([blob], `audio_${Date.now()}.webm`, { type: mime });
                    updateAttachmentPreview();
                } else {
                    recordedAudioFile = null;
                    updateAttachmentPreview();
                }
                recordedAudioChunks = [];
            }, { once: true });

            recorder.stop();
        }

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const fileInput = document.getElementById('chatFile');
            const texto = input.value.trim();
            const file = recordedAudioFile || fileInput.files?.[0];
            if (!activeChat || (!texto && !file)) return;

            const formData = new FormData();
            formData.append('numero', activeChat.telefone);
            formData.append('mensagem', texto);
            if (file) {
                formData.append('arquivo', file);
                if (recordedAudioFile) {
                    formData.append('ptt', '1');
                }
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
                        refreshLeads();
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
                refreshLeads();
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
                    (lead.nome && textoSeguro(lead.nome).toLowerCase().includes(term)) ||
                    (lead.telefone && textoSeguro(lead.telefone).includes(term)) ||
                    (lead.interesse && textoSeguro(lead.interesse).toLowerCase().includes(term));

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
        if (!res.ok) {
            console.error('api_clientes.php retornou erro:', res.status);
            return [];
        }
        const clientes = await res.json();
        if (!Array.isArray(clientes)) {
            console.error('api_clientes.php não retornou uma lista:', clientes);
            return [];
        }

        // converter clientes em leads compatíveis
        const leadsConvertidos = clientes
            .map((c, index) => {
                const cliente = c || {};
                const mensagens = Array.isArray(cliente.mensagens) ? cliente.mensagens : [];
                const ultimaMensagem = mensagens[mensagens.length - 1] || {};
                const primeiraMensagem = mensagens[0] || {};
                const idBase = textoSeguro(cliente.id || cliente.numero || index);

                return {
                    id: 'wa_' + idBase,
                    nome: textoSeguro(cliente.nome || 'Cliente WhatsApp'),
                    telefone: textoSeguro(cliente.numero || ''),
                    interesse: textoSeguro(cliente.interesse || ultimaMensagem.texto || ''),
                    valor: cliente.valor || 0,
                    origem: textoSeguro(cliente.origem || 'WhatsApp'),
                    status: textoSeguro(cliente.status || 'novo'),
                    etapa: textoSeguro(cliente.etapa || FIRST_STAGE),
                    data_ultimo_contato: textoSeguro(cliente.data_ultimo_contato || ultimaMensagem.data || ''),
                    created_at: textoSeguro(primeiraMensagem.data || '')
                };
            })
            .filter(lead => lead.id !== 'wa_' && (lead.telefone || lead.interesse || lead.nome));

        return leadsConvertidos;

    } catch (e) {
        console.log('Erro ao carregar WhatsApp:', e);
        return [];
    }
}

async function carregarLeadsSistema() {
    try {
        const res = await fetch('handler.php?action=getAll');
        if (!res.ok) {
            console.error('handler.php?action=getAll retornou erro:', res.status);
            return [];
        }

        const leads = await res.json();
        if (!Array.isArray(leads)) {
            console.error('handler.php?action=getAll não retornou uma lista:', leads);
            return [];
        }

        return leads;
    } catch (e) {
        console.error('Erro ao carregar leads do sistema:', e);
        return [];
    }
}

async function refreshLeads() {
    const [leadsSistemaResult, leadsWhatsResult] = await Promise.allSettled([
        carregarLeadsSistema(),
        carregarClientesWhatsApp()
    ]);

    const leadsSistema = leadsSistemaResult.status === 'fulfilled' && Array.isArray(leadsSistemaResult.value)
        ? leadsSistemaResult.value
        : [];
    const leadsWhats = leadsWhatsResult.status === 'fulfilled' && Array.isArray(leadsWhatsResult.value)
        ? leadsWhatsResult.value
        : [];

    allLeads = [...leadsSistema, ...leadsWhats];
    loadPipeline(allLeads);
}


        // Inicialização
        window.onload = async () => {

    await refreshLeads();

    document.getElementById('search').addEventListener('input', applyFilters);
            document.getElementById('filterEtapa').addEventListener('change', applyFilters);
            document.getElementById('filterValorMin').addEventListener('input', applyFilters);
            document.getElementById('filterValorMax').addEventListener('input', applyFilters);
            document.getElementById('searchVerTodos').addEventListener('input', (event) => {
                const term = event.target.value.toLowerCase().trim();
                renderListaVerTodos(currentVerTodosLeads.filter(lead =>
                    !term ||
                    (lead.nome && textoSeguro(lead.nome).toLowerCase().includes(term)) ||
                    (lead.telefone && textoSeguro(lead.telefone).includes(term)) ||
                    (lead.interesse && textoSeguro(lead.interesse).toLowerCase().includes(term))
                ));
            });
            document.getElementById('chatInput').addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendChatMessage();
                }
            });
            document.getElementById('chatFile').addEventListener('change', updateAttachmentPreview);
            document.getElementById('scheduleClientSearch').addEventListener('input', scheduleSearchChanged);
            document.getElementById('scheduleTelefone').addEventListener('blur', () => {
                const telefone = document.getElementById('scheduleTelefone').value.trim();
                if (telefone) searchScheduleClients(telefone, true);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !document.getElementById('scheduleOverlay').classList.contains('hidden')) {
                    closeScheduleOverlay();
                    return;
                }
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
