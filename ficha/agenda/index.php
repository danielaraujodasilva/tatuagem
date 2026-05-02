<?php
$agendaInitialDate = '';

if (empty($_GET['data'])) {
    try {
        require __DIR__ . '/../config/conexao.php';
        $result = $conn->query("
            SELECT data_tatuagem
            FROM tatuagens
            WHERE data_tatuagem >= CURDATE()
            ORDER BY data_tatuagem ASC, hora_inicio ASC, id ASC
            LIMIT 1
        ");
        $row = $result ? $result->fetch_assoc() : null;

        if (!$row) {
            $result = $conn->query("
                SELECT data_tatuagem
                FROM tatuagens
                ORDER BY data_tatuagem DESC, hora_inicio DESC, id DESC
                LIMIT 1
            ");
            $row = $result ? $result->fetch_assoc() : null;
        }

        $agendaInitialDate = (string)($row['data_tatuagem'] ?? '');
    } catch (Throwable $e) {
        $agendaInitialDate = '';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agenda de Tatuagens</title>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/style.css?v=20260502-agenda-polish-2" rel="stylesheet">
<style>
  .ficha-agenda-page .ficha-frame { width: min(100%, 1680px); }
  .ficha-agenda-page .ficha-calendar-shell-full { display: block; width: 100%; }
  .ficha-agenda-page .ficha-calendar-panel,
  .ficha-agenda-page #calendar,
  .ficha-agenda-page .fc { width: 100%; max-width: none; min-width: 0; }
  .ficha-agenda-page .fc-view-harness,
  .ficha-agenda-page .fc-scrollgrid,
  .ficha-agenda-page .fc-daygrid-body,
  .ficha-agenda-page .fc-daygrid-body table { width: 100% !important; }
  .ficha-agenda-page .fc a.fc-daygrid-day-number {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    margin: 8px 8px 2px auto;
    padding: 0 !important;
    border-radius: 999px;
    color: #dbeafe !important;
    text-decoration: none !important;
    font-weight: 800;
    background: rgba(15, 23, 42, 0.62);
    border: 1px solid rgba(148, 163, 184, 0.14);
  }
  .ficha-agenda-page .fc .fc-day-today a.fc-daygrid-day-number {
    color: #06111f !important;
    background: #38bdf8;
    border-color: rgba(56, 189, 248, 0.88);
    box-shadow: 0 10px 22px rgba(56, 189, 248, 0.22);
  }
  .ficha-agenda-page .fc .fc-daygrid-day-frame { padding: 10px !important; }
  .ficha-agenda-page .fc .fc-daygrid-day-events { gap: 7px; padding: 5px 8px 12px; }
</style>
</head>
<body class="ficha-body ficha-agenda-page">
<main class="ficha-shell">
  <section class="ficha-frame">
    <header class="ficha-hero">
        <span class="ficha-kicker">Agenda de tatuagem</span>
      <h1>Agenda de tatuagens</h1>
      <div class="ficha-nav">
        <a class="btn ficha-btn ficha-btn-secondary" href="../index.php">Nova ficha</a>
        <a class="btn ficha-btn ficha-btn-secondary" href="../public/clientes.php">Clientes</a>
        <a class="btn ficha-btn ficha-btn-primary" href="../public/cadastrar_tatuagem.php">Novo agendamento</a>
      </div>
    </header>

    <div class="ficha-content">
      <div id="agendaAlert" class="ficha-alert ficha-alert-info mb-4" style="display:none;"></div>

      <div class="ficha-calendar-shell ficha-calendar-shell-full">
        <section class="ficha-calendar-panel">
          <div id="calendar"></div>
        </section>
      </div>

      <footer class="ficha-summary mt-4">
        <h2 class="ficha-panel-title">Legenda de status</h2>
        <div class="ficha-legend">
          <div class="ficha-legend-item"><span class="ficha-legend-dot" style="background:#38bdf8;"></span> Agendado</div>
          <div class="ficha-legend-item"><span class="ficha-legend-dot" style="background:#22c55e;"></span> Confirmado</div>
          <div class="ficha-legend-item"><span class="ficha-legend-dot" style="background:#fb7185;"></span> Cancelado</div>
          <div class="ficha-legend-item"><span class="ficha-legend-dot" style="background:#94a3b8;"></span> Concluido</div>
        </div>
      </footer>
    </div>
  </section>
</main>

<div class="modal fade ficha-modal" id="eventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 class="modal-title h4 mb-1" id="eventModalTitle">Detalhes do agendamento</h2>
          <div class="ficha-muted" id="eventModalSubtitle">Revise as informacoes e ajuste o que for necessario.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="ficha-detail-grid mb-4">
          <div class="ficha-detail-card"><span>Status atual</span><div id="eventCurrentStatus">Agendado</div></div>
          <div class="ficha-detail-card"><span>Cliente vinculado</span><div id="eventCurrentClient">Sem cliente</div></div>
          <div class="ficha-detail-card"><span>Valor</span><div id="eventCurrentValue">R$ 0,00</div></div>
          <div class="ficha-detail-card"><span>Janela do atendimento</span><div id="eventCurrentWindow">-</div></div>
        </div>

        <form id="eventForm" class="ficha-modal-panel">
          <input type="hidden" id="eventId" name="id">
          <input type="hidden" id="eventClientId" name="cliente_id">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="ficha-form-label" for="eventDescription">Descricao</label>
              <input type="text" id="eventDescription" name="descricao" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label" for="eventStatus">Status</label>
              <select id="eventStatus" name="status" class="form-select">
                <option value="agendado">Agendado</option>
                <option value="confirmado">Confirmado</option>
                <option value="cancelado">Cancelado</option>
                <option value="concluido">Concluido</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label" for="eventDate">Data</label>
              <input type="date" id="eventDate" name="data_tatuagem" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label" for="eventStart">Hora inicio</label>
              <input type="time" id="eventStart" name="hora_inicio" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label" for="eventEnd">Hora fim</label>
              <input type="time" id="eventEnd" name="hora_fim" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label" for="eventValue">Valor (R$)</label>
              <input type="number" step="0.01" id="eventValue" name="valor" class="form-control" value="0">
            </div>
            <div class="col-md-8">
              <label class="ficha-form-label" for="eventClientName">Cliente vinculado</label>
              <input type="text" id="eventClientName" class="form-control" disabled>
            </div>
            <div class="col-12">
              <label class="ficha-form-label" for="eventClientSearch">Buscar cliente para vincular</label>
              <div class="position-relative">
                <input type="text" id="eventClientSearch" class="form-control" placeholder="Digite nome, telefone ou e-mail">
                <div id="eventClientResults" class="ficha-autocomplete" style="display:none;"></div>
              </div>
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label" for="eventPomadas">Pomadas anestesicas</label>
              <input type="number" min="0" step="1" id="eventPomadas" name="pomadas_anestesicas" class="form-control" value="0">
            </div>
            <div class="col-md-8">
              <label class="ficha-form-label" for="eventReference">Arte de referencia</label>
              <input type="hidden" id="eventReference" name="referencia_arte">
              <input type="file" id="eventReferenceFile" class="form-control" accept="image/*,.pdf">
              <div id="eventReferencePreview" class="mt-3"></div>
            </div>
            <div class="col-12">
              <label class="ficha-form-label" for="eventNotes">Observacoes</label>
              <textarea id="eventNotes" name="observacoes" class="form-control" rows="3"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer d-flex flex-column flex-md-row gap-3">
        <button type="button" id="deleteEventBtn" class="btn ficha-btn ficha-btn-danger me-md-auto">Excluir agendamento</button>
        <button type="button" class="btn ficha-btn ficha-btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" id="saveEventBtn" class="btn ficha-btn ficha-btn-primary">Salvar alteracoes</button>
      </div>
    </div>
  </div>
</div>

<div id="referenceOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-black bg-opacity-75" style="z-index: 2000;">
  <div class="h-100 d-flex align-items-center justify-content-center p-3">
    <button type="button" id="referenceOverlayClose" class="btn btn-light position-absolute top-0 end-0 m-3">Fechar</button>
    <img id="referenceOverlayImage" src="" alt="Arte de referencia" class="img-fluid rounded-4 shadow-lg" style="max-height: 92vh; object-fit: contain;">
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const alertBox = document.getElementById('agendaAlert');
  const modalElement = document.getElementById('eventModal');
  const modal = new bootstrap.Modal(modalElement);
  const form = document.getElementById('eventForm');
  const calendarEl = document.getElementById('calendar');
  const urlParams = new URLSearchParams(window.location.search);
  const serverInitialDate = <?= json_encode($agendaInitialDate, JSON_UNESCAPED_UNICODE) ?>;
  const initialDate = urlParams.get('data') || serverInitialDate || undefined;
  const highlightedEventId = urlParams.get('agendamento_id') || '';
  const referenceOverlay = document.getElementById('referenceOverlay');
  const referenceOverlayImage = document.getElementById('referenceOverlayImage');

  const fields = {
    id: document.getElementById('eventId'),
    clienteId: document.getElementById('eventClientId'),
    clienteBusca: document.getElementById('eventClientSearch'),
    clienteResultados: document.getElementById('eventClientResults'),
    descricao: document.getElementById('eventDescription'),
    status: document.getElementById('eventStatus'),
    data: document.getElementById('eventDate'),
    inicio: document.getElementById('eventStart'),
    fim: document.getElementById('eventEnd'),
    valor: document.getElementById('eventValue'),
    cliente: document.getElementById('eventClientName'),
    observacoes: document.getElementById('eventNotes'),
    pomadas: document.getElementById('eventPomadas'),
    referencia: document.getElementById('eventReference'),
    referenciaArquivo: document.getElementById('eventReferenceFile')
  };

  const summary = {
    title: document.getElementById('eventModalTitle'),
    subtitle: document.getElementById('eventModalSubtitle'),
    status: document.getElementById('eventCurrentStatus'),
    client: document.getElementById('eventCurrentClient'),
    value: document.getElementById('eventCurrentValue'),
    window: document.getElementById('eventCurrentWindow')
  };

  const deleteBtn = document.getElementById('deleteEventBtn');
  const saveBtn = document.getElementById('saveEventBtn');

  const calendar = new FullCalendar.Calendar(calendarEl, {
    locale: 'pt-br',
    initialView: highlightedEventId ? 'timeGridDay' : 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    buttonText: {
      today: 'Hoje',
      month: 'Mes',
      week: 'Semana',
      day: 'Dia'
    },
    slotMinTime: highlightedEventId ? '00:00:00' : '08:00:00',
    slotMaxTime: '24:00:00',
    initialDate: initialDate,
    nowIndicator: true,
    selectable: true,
    editable: true,
    selectMirror: true,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    eventDisplay: 'block',
    dayMaxEventRows: 4,
    height: 'auto',
    events: 'api/listar.php',
    eventSourceFailure: function () {
      showAlert('Nao foi possivel carregar os agendamentos. Abra o diagnostico ou recarregue a pagina.', 'danger');
    },
    eventDidMount: function (info) {
      if (highlightedEventId && String(info.event.id) === String(highlightedEventId)) {
        info.el.style.boxShadow = '0 0 0 3px rgba(16,185,129,.85)';
      }
    },
    eventContent: function (info) {
      const time = info.timeText ? `<span class="agenda-event-time">${escapeHtml(info.timeText)}</span>` : '';
      const title = `<span class="agenda-event-title">${escapeHtml(info.event.title)}</span>`;
      const separator = info.timeText ? '<span class="agenda-event-separator">&nbsp;-&nbsp;</span>' : '';
      return { html: `<div class="agenda-event-card">${time}${separator}${title}</div>` };
    },
    select: function (info) {
      resetForm();
      summary.title.textContent = 'Novo agendamento';
      summary.subtitle.textContent = 'Preencha os dados principais para salvar um novo horario.';
      deleteBtn.style.display = 'none';
      fillDateTime(info.start, info.end);
      updateSummaryCards();
      modal.show();
      calendar.unselect();
    },
    eventClick: function (info) {
      loadEvent(info.event.id);
    },
    eventDrop: function (info) {
      quickReschedule(info.event);
    },
    eventResize: function (info) {
      quickReschedule(info.event);
    }
  });

  form.addEventListener('input', updateSummaryCards);
  fields.clienteBusca.addEventListener('input', searchEventClients);
  fields.referencia.addEventListener('input', updateReferencePreview);
  fields.referenciaArquivo.addEventListener('change', updateReferencePreview);
  document.getElementById('referenceOverlayClose').addEventListener('click', closeReferenceOverlay);
  referenceOverlay.addEventListener('click', event => {
    if (event.target === referenceOverlay) {
      closeReferenceOverlay();
    }
  });

  saveBtn.addEventListener('click', async function () {
    if (!form.reportValidity()) {
      return;
    }

    const uploadedReference = await uploadReferenceIfNeeded();
    if (uploadedReference === false) {
      return;
    }

    const payload = {
      id: fields.id.value,
      descricao: fields.descricao.value.trim(),
      status: fields.status.value,
      data_tatuagem: fields.data.value,
      hora_inicio: fields.inicio.value,
      hora_fim: fields.fim.value,
      valor: fields.valor.value || 0,
      cliente_id: fields.clienteId.value || '',
      observacoes: fields.observacoes.value.trim(),
      pomadas_anestesicas: fields.pomadas.value || 0,
      referencia_arte: uploadedReference || fields.referencia.value.trim()
    };

    const endpoint = payload.id ? 'api/atualizar.php' : 'api/salvar.php';
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok || result.status === 'error') {
      showAlert(result.message || 'Nao foi possivel salvar o agendamento.', 'danger');
      return;
    }

    modal.hide();
    calendar.refetchEvents();
    showAlert(result.message || 'Agendamento salvo com sucesso.', 'success');
  });

  deleteBtn.addEventListener('click', async function () {
    if (!fields.id.value) {
      return;
    }

    if (!confirm('Excluir este agendamento?')) {
      return;
    }

    const response = await fetch('api/deletar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: fields.id.value })
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok || result.status === 'error') {
      showAlert(result.message || 'Nao foi possivel excluir o agendamento.', 'danger');
      return;
    }

    modal.hide();
    calendar.refetchEvents();
    showAlert(result.message || 'Agendamento excluido.', 'success');
  });

  function resetForm() {
    form.reset();
    fields.id.value = '';
    fields.clienteId.value = '';
    fields.clienteBusca.value = '';
    fields.clienteResultados.style.display = 'none';
    fields.clienteResultados.innerHTML = '';
    fields.valor.value = '0';
    fields.cliente.value = 'Sem cliente vinculado';
    fields.observacoes.value = '';
    fields.pomadas.value = '0';
    fields.referencia.value = '';
    fields.referenciaArquivo.value = '';
    updateReferencePreview();
    summary.status.textContent = 'Agendado';
    summary.client.textContent = 'Sem cliente';
    summary.value.textContent = 'R$ 0,00';
    summary.window.textContent = '-';
  }

  function fillDateTime(start, end) {
    fields.data.value = start.toISOString().slice(0, 10);
    fields.inicio.value = start.toTimeString().slice(0, 5);
    fields.fim.value = end ? end.toTimeString().slice(0, 5) : start.toTimeString().slice(0, 5);
  }

  function updateSummaryCards() {
    summary.status.textContent = fields.status.options[fields.status.selectedIndex]?.text || 'Agendado';
    summary.client.textContent = fields.cliente.value || 'Sem cliente';
    summary.value.textContent = formatCurrency(Number(fields.valor.value || 0));
    summary.window.textContent = buildWindowText(fields.data.value, fields.inicio.value, fields.fim.value);
  }

  function buildWindowText(dateValue, startValue, endValue) {
    if (!dateValue) {
      return '-';
    }
    const date = new Date(dateValue + 'T00:00:00');
    const label = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full' }).format(date);
    if (startValue && endValue) {
      return label + ' - ' + startValue + ' ate ' + endValue;
    }
    return label;
  }

  async function loadEvent(id) {
    const response = await fetch('api/detalhes.php?id=' + encodeURIComponent(id));
    const data = await response.json().catch(() => null);

    if (!response.ok || !data) {
      showAlert('Nao foi possivel carregar os detalhes do agendamento.', 'danger');
      return;
    }

    resetForm();
    fields.id.value = data.id;
    fields.clienteId.value = data.cliente_id || '';
    fields.clienteBusca.value = '';
    fields.descricao.value = data.descricao || '';
    fields.status.value = data.status || 'agendado';
    fields.data.value = data.data_tatuagem || '';
    fields.inicio.value = data.hora_inicio ? data.hora_inicio.slice(0, 5) : '';
    fields.fim.value = data.hora_fim ? data.hora_fim.slice(0, 5) : '';
    fields.valor.value = data.valor || 0;
    fields.cliente.value = clientLabel(data);
    fields.observacoes.value = data.observacoes || '';
    fields.pomadas.value = data.pomadas_anestesicas || 0;
    fields.referencia.value = data.referencia_arte || '';
    updateReferencePreview();

    summary.title.textContent = data.descricao || 'Detalhes do agendamento';
    summary.subtitle.textContent = 'Revise as informacoes, ajuste o status e salve quando estiver pronto.';
    deleteBtn.style.display = 'inline-flex';
    updateSummaryCards();
    modal.show();
  }

  async function quickReschedule(event) {
    const payload = {
      id: event.id,
      descricao: event.extendedProps.descricao || event.title,
      status: event.extendedProps.status || 'agendado',
      data_tatuagem: event.startStr.slice(0, 10),
      hora_inicio: event.startStr.slice(11, 16),
      hora_fim: event.endStr ? event.endStr.slice(11, 16) : event.startStr.slice(11, 16),
      valor: event.extendedProps.valor || 0,
      cliente_id: event.extendedProps.cliente_id || '',
      observacoes: event.extendedProps.observacoes || '',
      pomadas_anestesicas: event.extendedProps.pomadas_anestesicas || 0,
      referencia_arte: event.extendedProps.referencia_arte || ''
    };

    const response = await fetch('api/atualizar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok || result.status === 'error') {
      showAlert(result.message || 'Nao foi possivel atualizar o horario.', 'danger');
      calendar.refetchEvents();
      return;
    }

    showAlert('Horario atualizado com sucesso.', 'success');
  }

  function showAlert(message, type) {
    alertBox.className = 'ficha-alert ficha-alert-' + type + ' mb-4';
    alertBox.textContent = message;
    alertBox.style.display = 'block';
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value || 0);
  }

  calendar.render();
  setTimeout(() => calendar.updateSize(), 80);
  window.addEventListener('resize', () => calendar.updateSize());

  if (highlightedEventId) {
    openLinkedEvent(highlightedEventId);
  }

  async function openLinkedEvent(id) {
    const response = await fetch('api/detalhes.php?id=' + encodeURIComponent(id));
    const data = await response.json().catch(() => null);

    if (!response.ok || !data || data.status === 'error') {
      await showLinkedEventDiagnostics(id);
      return;
    }

    if (!calendar.getEventById(String(data.id))) {
      calendar.addEvent(buildCalendarEventFromDetails(data));
    }

    showAlert('Agendamento encontrado no banco e destacado na agenda.', 'success');
    loadEvent(data.id);
  }

  async function showLinkedEventDiagnostics(id) {
    const response = await fetch('api/diagnostico.php?id=' + encodeURIComponent(id));
    const diag = await response.json().catch(() => null);

    if (!response.ok || !diag || !diag.ok) {
      showAlert('O link abriu a data certa, mas a agenda nao encontrou esse agendamento pelo ID ' + id + ' e o diagnostico tambem falhou.', 'danger');
      return;
    }

    if (diag.registro_procurado) {
      const fallback = normalizeDiagnosticEvent(diag.registro_procurado);
      if (!calendar.getEventById(String(fallback.id))) {
        calendar.addEvent(buildCalendarEventFromDetails(fallback));
      }

      showAlert('Agendamento encontrado pelo diagnostico e aberto na agenda. O endpoint de detalhes falhou, mas o registro existe.', 'success');
      fillEventFormFromData(fallback);
      modal.show();
      return;
    }

    const ultimos = Array.isArray(diag.ultimos_agendamentos)
      ? diag.ultimos_agendamentos.map(item => `#${item.id} ${item.data_tatuagem || 'sem data'} ${item.hora_inicio || ''} - ${item.descricao || 'sem descricao'}`).join(' | ')
      : '';

    showAlert(
      `Agendamento #${id} nao existe na agenda deste banco. Banco: ${diag.database || 'desconhecido'}. Total na tabela: ${diag.total_tatuagens}. Maior ID: ${diag.maior_id || 'nenhum'}. Ultimos: ${ultimos || 'nenhum'}.`,
      'danger'
    );
  }

  function normalizeDiagnosticEvent(data) {
    return {
      id: data.id,
      cliente_id: data.cliente_id || '',
      descricao: data.descricao || 'Tatuagem',
      valor: data.valor || 0,
      data_tatuagem: data.data_tatuagem || initialDate || '',
      hora_inicio: data.hora_inicio || '00:00:00',
      hora_fim: data.hora_fim || data.hora_inicio || '01:00:00',
      status: data.status || 'agendado',
      observacoes: data.observacoes || '',
      pomadas_anestesicas: data.pomadas_anestesicas || 0,
      referencia_arte: data.referencia_arte || '',
      cliente_nome: data.cliente_nome || '',
      cliente_telefone: data.cliente_telefone || ''
    };
  }

  function fillEventFormFromData(data) {
    resetForm();
    fields.id.value = data.id || '';
    fields.clienteId.value = data.cliente_id || '';
    fields.clienteBusca.value = '';
    fields.descricao.value = data.descricao || '';
    fields.status.value = data.status || 'agendado';
    fields.data.value = data.data_tatuagem || '';
    fields.inicio.value = data.hora_inicio ? data.hora_inicio.slice(0, 5) : '';
    fields.fim.value = data.hora_fim ? data.hora_fim.slice(0, 5) : '';
    fields.valor.value = data.valor || 0;
    fields.cliente.value = clientLabel(data);
    fields.observacoes.value = data.observacoes || '';
    fields.pomadas.value = data.pomadas_anestesicas || 0;
    fields.referencia.value = data.referencia_arte || '';
    updateReferencePreview();

    summary.title.textContent = data.descricao || 'Detalhes do agendamento';
    summary.subtitle.textContent = 'Revise as informacoes, ajuste o status e salve quando estiver pronto.';
    deleteBtn.style.display = 'inline-flex';
    updateSummaryCards();
  }

  function buildCalendarEventFromDetails(data) {
    const startTime = data.hora_inicio || '00:00:00';
    const endTime = data.hora_fim || startTime;
    const colors = {
      agendado: '#38bdf8',
      confirmado: '#22c55e',
      cancelado: '#fb7185',
      concluido: '#94a3b8'
    };

    return {
      id: String(data.id),
      title: eventTitle(data),
      start: `${data.data_tatuagem}T${startTime}`,
      end: `${data.data_tatuagem}T${endTime}`,
      color: colors[data.status] || '#38bdf8',
      textColor: '#06111f',
      display: 'block',
      extendedProps: {
        status: data.status || 'agendado',
        descricao: data.descricao || '',
        valor: Number(data.valor || 0),
        cliente_id: data.cliente_id || '',
        observacoes: data.observacoes || '',
        pomadas_anestesicas: Number(data.pomadas_anestesicas || 0),
        referencia_arte: data.referencia_arte || '',
        cliente_nome: data.cliente_nome || '',
        cliente_telefone: data.cliente_telefone || ''
      }
    };
  }

  function eventTitle(data) {
    const nome = (data.cliente_nome || '').trim();
    return nome || data.descricao || 'Tatuagem';
  }

  function clientLabel(data) {
    const nome = (data.cliente_nome || '').trim();
    const telefone = (data.cliente_telefone || '').trim();
    if (nome && telefone) return `${nome} - ${telefone}`;
    if (nome) return nome;
    if (telefone) return telefone;
    return 'Sem cliente vinculado';
  }

  let clientSearchTimer = null;
  function searchEventClients() {
    clearTimeout(clientSearchTimer);
    const term = fields.clienteBusca.value.trim();

    clientSearchTimer = setTimeout(async () => {
      if (term.length < 2) {
        fields.clienteResultados.style.display = 'none';
        fields.clienteResultados.innerHTML = '';
        return;
      }

      const response = await fetch('api/buscar_clientes.php?q=' + encodeURIComponent(term));
      const data = await response.json().catch(() => ({ clientes: [] }));
      const clientes = Array.isArray(data.clientes) ? data.clientes : [];

      if (!clientes.length) {
        fields.clienteResultados.innerHTML = '<div class="autocomplete-suggestion">Nenhum cliente encontrado</div>';
        fields.clienteResultados.style.display = 'block';
        return;
      }

      fields.clienteResultados.innerHTML = clientes.map(cliente => `
        <div class="autocomplete-suggestion" data-id="${escapeHtml(cliente.id)}" data-nome="${escapeHtml(cliente.nome || '')}" data-telefone="${escapeHtml(cliente.telefone || '')}">
          ${escapeHtml(cliente.nome || 'Cliente')} - ${escapeHtml(cliente.telefone || '')}${cliente.email ? ' - ' + escapeHtml(cliente.email) : ''}
        </div>
      `).join('');
      fields.clienteResultados.style.display = 'block';
    }, 220);
  }

  fields.clienteResultados.addEventListener('click', event => {
    const item = event.target.closest('.autocomplete-suggestion[data-id]');
    if (!item) return;

    const cliente = {
      id: item.dataset.id,
      cliente_id: item.dataset.id,
      cliente_nome: item.dataset.nome || '',
      cliente_telefone: item.dataset.telefone || ''
    };

    fields.clienteId.value = cliente.id;
    fields.cliente.value = clientLabel(cliente);
    fields.clienteBusca.value = '';
    fields.clienteResultados.innerHTML = '';
    fields.clienteResultados.style.display = 'none';
    updateSummaryCards();
  });

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  function referenceUrl(value) {
    const clean = String(value || '').trim();
    if (!clean) return '';
    if (/^(https?:)?\/\//i.test(clean) || clean.startsWith('/')) return clean;
    if (clean.startsWith('../')) return clean;
    if (clean.startsWith('uploads/')) return '../' + clean;
    return clean;
  }

  function isImageReference(value) {
    return /\.(jpe?g|png|webp|gif|jfif)$/i.test(String(value || '').split('?')[0]);
  }

  function updateReferencePreview() {
    const container = document.getElementById('eventReferencePreview');
    const raw = fields.referencia.value.trim();
    const file = fields.referenciaArquivo.files?.[0] || null;
    const url = referenceUrl(raw);

    if (file) {
      const isImage = file.type.startsWith('image/');
      const objectUrl = isImage ? URL.createObjectURL(file) : '';
      container.innerHTML = isImage
        ? `
          <button type="button" class="border-0 bg-transparent p-0 text-start" onclick="openReferenceOverlay('${escapeHtml(objectUrl)}')">
            <img src="${escapeHtml(objectUrl)}" alt="Arte de referencia" class="rounded-3 border" style="width: 140px; height: 140px; object-fit: cover;">
            <div class="ficha-muted mt-2">Nova referencia selecionada</div>
          </button>
        `
        : '<div class="ficha-muted">Novo arquivo selecionado: ' + escapeHtml(file.name) + '</div>';
      return;
    }

    if (!raw) {
      container.innerHTML = '<div class="ficha-muted">Nenhuma arte de referencia vinculada.</div>';
      return;
    }

    if (isImageReference(raw)) {
      container.innerHTML = `
        <button type="button" class="border-0 bg-transparent p-0 text-start" onclick="openReferenceOverlay('${escapeHtml(url)}')">
          <img src="${escapeHtml(url)}" alt="Arte de referencia" class="rounded-3 border" style="width: 140px; height: 140px; object-fit: cover;">
          <div class="ficha-muted mt-2">Clique para ampliar</div>
        </button>
      `;
      return;
    }

    container.innerHTML = `<a class="btn ficha-btn ficha-btn-secondary" href="${escapeHtml(url)}" target="_blank" rel="noopener">Abrir referencia</a>`;
  }

  async function uploadReferenceIfNeeded() {
    const file = fields.referenciaArquivo.files?.[0] || null;
    if (!file) return '';

    const formData = new FormData();
    formData.append('referencia', file);

    const response = await fetch('api/upload_referencia.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json().catch(() => ({}));

    if (!response.ok || result.status === 'error' || !result.path) {
      showAlert(result.message || 'Nao foi possivel enviar a referencia.', 'danger');
      return false;
    }

    fields.referencia.value = result.path;
    fields.referenciaArquivo.value = '';
    updateReferencePreview();
    return result.path;
  }

  window.openReferenceOverlay = function (url) {
    referenceOverlayImage.src = url;
    referenceOverlay.classList.remove('d-none');
  };

  function closeReferenceOverlay() {
    referenceOverlay.classList.add('d-none');
    referenceOverlayImage.src = '';
  }
});
</script>
</body>
</html>
