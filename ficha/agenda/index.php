<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agenda de Tatuagens</title>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
<main class="ficha-shell">
  <section class="ficha-frame">
    <header class="ficha-hero">
        <span class="ficha-kicker">Agenda de tatuagem</span>
      <h1>Agenda de tatuagens</h1>
      <p>Uma agenda mais apresentavel, com leitura rapida, criacao assistida de horarios e uma camada de detalhes para revisar, editar ou excluir cada agendamento.</p>
      <div class="ficha-nav">
        <a class="btn ficha-btn ficha-btn-secondary" href="../index.php">Nova ficha</a>
        <a class="btn ficha-btn ficha-btn-secondary" href="../public/clientes.php">Clientes</a>
        <a class="btn ficha-btn ficha-btn-primary" href="../public/cadastrar_tatuagem.php">Novo agendamento</a>
      </div>
    </header>

    <div class="ficha-content">
      <div id="agendaAlert" class="ficha-alert ficha-alert-info mb-4" style="display:none;"></div>

      <div class="ficha-calendar-shell">
        <aside class="ficha-calendar-sidebar">
          <div class="ficha-summary">
            <h2 class="ficha-panel-title">Guia rapido</h2>
            <div class="ficha-copy">
              Clique e arraste no calendario para abrir um novo agendamento. Clique em um evento existente para ver tudo e editar sem sair da agenda.
            </div>
          </div>

          <div class="ficha-summary">
            <h2 class="ficha-panel-title">Legenda de status</h2>
            <div class="ficha-legend">
              <div class="ficha-legend-item"><span class="ficha-legend-dot" style="background:#38bdf8;"></span> Agendado</div>
              <div class="ficha-legend-item"><span class="ficha-legend-dot" style="background:#22c55e;"></span> Confirmado</div>
              <div class="ficha-legend-item"><span class="ficha-legend-dot" style="background:#fb7185;"></span> Cancelado</div>
              <div class="ficha-legend-item"><span class="ficha-legend-dot" style="background:#94a3b8;"></span> Concluido</div>
            </div>
          </div>

          <div class="ficha-summary">
            <h2 class="ficha-panel-title">Atalhos de trabalho</h2>
            <div class="d-grid gap-2">
              <a class="btn ficha-btn ficha-btn-secondary" href="../public/cadastrar_tatuagem.php">Cadastrar fora da agenda</a>
              <a class="btn ficha-btn ficha-btn-secondary" href="../public/clientes.php">Ver base de clientes</a>
            </div>
          </div>
        </aside>

        <section class="ficha-calendar-panel">
          <div id="calendar"></div>
        </section>
      </div>
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
            <div class="col-md-4">
              <label class="ficha-form-label" for="eventPomadas">Pomadas anestesicas</label>
              <input type="number" min="0" step="1" id="eventPomadas" name="pomadas_anestesicas" class="form-control" value="0">
            </div>
            <div class="col-md-8">
              <label class="ficha-form-label" for="eventReference">Arte de referencia</label>
              <input type="text" id="eventReference" name="referencia_arte" class="form-control" placeholder="Caminho do arquivo ou link">
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
  const initialDate = urlParams.get('data') || undefined;
  const highlightedEventId = urlParams.get('agendamento_id') || '';

  const fields = {
    id: document.getElementById('eventId'),
    descricao: document.getElementById('eventDescription'),
    status: document.getElementById('eventStatus'),
    data: document.getElementById('eventDate'),
    inicio: document.getElementById('eventStart'),
    fim: document.getElementById('eventEnd'),
    valor: document.getElementById('eventValue'),
    cliente: document.getElementById('eventClientName'),
    observacoes: document.getElementById('eventNotes'),
    pomadas: document.getElementById('eventPomadas'),
    referencia: document.getElementById('eventReference')
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
    initialView: highlightedEventId ? 'timeGridDay' : (window.innerWidth < 900 ? 'listWeek' : 'timeGridWeek'),
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,listWeek'
    },
    buttonText: {
      today: 'Hoje',
      month: 'Mes',
      week: 'Semana',
      list: 'Lista'
    },
    slotMinTime: highlightedEventId ? '00:00:00' : '08:00:00',
    slotMaxTime: '24:00:00',
    initialDate: initialDate,
    nowIndicator: true,
    selectable: true,
    editable: true,
    selectMirror: true,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    height: 'auto',
    events: 'api/listar.php',
    eventDidMount: function (info) {
      if (highlightedEventId && String(info.event.id) === String(highlightedEventId)) {
        info.el.style.boxShadow = '0 0 0 3px rgba(16,185,129,.85)';
      }
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

  saveBtn.addEventListener('click', async function () {
    if (!form.reportValidity()) {
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
      observacoes: fields.observacoes.value.trim(),
      pomadas_anestesicas: fields.pomadas.value || 0,
      referencia_arte: fields.referencia.value.trim()
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
    fields.valor.value = '0';
    fields.cliente.value = 'Sem cliente vinculado';
    fields.observacoes.value = '';
    fields.pomadas.value = '0';
    fields.referencia.value = '';
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
    fields.descricao.value = data.descricao || '';
    fields.status.value = data.status || 'agendado';
    fields.data.value = data.data_tatuagem || '';
    fields.inicio.value = data.hora_inicio ? data.hora_inicio.slice(0, 5) : '';
    fields.fim.value = data.hora_fim ? data.hora_fim.slice(0, 5) : '';
    fields.valor.value = data.valor || 0;
    fields.cliente.value = data.cliente_nome || 'Sem cliente vinculado';
    fields.observacoes.value = data.observacoes || '';
    fields.pomadas.value = data.pomadas_anestesicas || 0;
    fields.referencia.value = data.referencia_arte || '';

    summary.title.textContent = data.descricao || 'Detalhes do agendamento';
    summary.subtitle.textContent = 'Revise as informacoes, ajuste o status e salve quando estiver pronto.';
    deleteBtn.style.display = 'inline-flex';
    updateSummaryCards();
    modal.show();
  }

  async function quickReschedule(event) {
    const payload = {
      id: event.id,
      descricao: event.title,
      status: event.extendedProps.status || 'agendado',
      data_tatuagem: event.startStr.slice(0, 10),
      hora_inicio: event.startStr.slice(11, 16),
      hora_fim: event.endStr ? event.endStr.slice(11, 16) : event.startStr.slice(11, 16),
      valor: event.extendedProps.valor || 0,
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

    const ultimos = Array.isArray(diag.ultimos_agendamentos)
      ? diag.ultimos_agendamentos.map(item => `#${item.id} ${item.data_tatuagem || 'sem data'} ${item.hora_inicio || ''} - ${item.descricao || 'sem descricao'}`).join(' | ')
      : '';

    showAlert(
      `Agendamento #${id} nao existe na agenda deste banco. Banco: ${diag.database || 'desconhecido'}. Total na tabela: ${diag.total_tatuagens}. Maior ID: ${diag.maior_id || 'nenhum'}. Ultimos: ${ultimos || 'nenhum'}.`,
      'danger'
    );
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
      title: data.descricao || 'Tatuagem',
      start: `${data.data_tatuagem}T${startTime}`,
      end: `${data.data_tatuagem}T${endTime}`,
      color: colors[data.status] || '#38bdf8',
      extendedProps: {
        status: data.status || 'agendado',
        valor: Number(data.valor || 0),
        observacoes: data.observacoes || '',
        pomadas_anestesicas: Number(data.pomadas_anestesicas || 0),
        referencia_arte: data.referencia_arte || '',
        cliente_nome: data.cliente_nome || ''
      }
    };
  }
});
</script>
</body>
</html>
