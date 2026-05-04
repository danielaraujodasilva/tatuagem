<?php
require_once __DIR__ . '/../../auth/auth.php';
require_staff();
require __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../../includes/app_menu.php';

$clienteSelecionadoId = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0;
$clienteSelecionadoNome = '';

if ($clienteSelecionadoId > 0) {
    $stmtCliente = $conn->prepare('SELECT nome FROM clientes WHERE id = ? LIMIT 1');
    $stmtCliente->bind_param('i', $clienteSelecionadoId);
    $stmtCliente->execute();
    $clienteData = $stmtCliente->get_result()->fetch_assoc();
    $stmtCliente->close();
    if ($clienteData) {
        $clienteSelecionadoNome = $clienteData['nome'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cliente_id'])) {
    header('Content-Type: application/json; charset=utf-8');

    $clienteId = (int) $_POST['cliente_id'];
    $descricao = trim((string) ($_POST['descricao'] ?? ''));
    $valor = (float) ($_POST['valor'] ?? 0);
    $data = trim((string) ($_POST['data_tatuagem'] ?? ''));
    $horaInicio = trim((string) ($_POST['hora_inicio'] ?? ''));
    $horaFim = trim((string) ($_POST['hora_fim'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'agendado'));

    if ($clienteId <= 0 || $descricao === '' || $data === '') {
        echo json_encode(['status' => 'error', 'message' => 'Preencha cliente, descricao e data.']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO tatuagens (cliente_id, descricao, valor, data_tatuagem, hora_inicio, hora_fim, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isdssss', $clienteId, $descricao, $valor, $data, $horaInicio, $horaFim, $status);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'Agendamento salvo com sucesso.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastrar Tatuagem</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/style.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="ficha-body">
<main class="ficha-shell">
  <section class="ficha-frame">
    <header class="ficha-hero">
        <span class="ficha-kicker">Ficha de cliente</span>
      <h1>Novo agendamento de tatuagem</h1>
      <p>Associe o atendimento a um cliente ja cadastrado, organize horario, valor e status inicial em uma tela mais objetiva.</p>
      <?php app_menu_render('agenda'); ?>
    </header>

    <div class="ficha-content">
      <div id="alerta"></div>

      <div class="row g-4">
        <div class="col-lg-7">
          <form id="formTatuagem" class="ficha-panel">
            <div class="mb-4">
              <h2 class="ficha-panel-title">Informacoes do agendamento</h2>
              <p class="ficha-copy mb-0">Use a busca por cliente para preencher rapido e manter o historico conectado ao atendimento.</p>
            </div>

            <div class="mb-3 position-relative">
              <label class="ficha-form-label">Cliente</label>
              <input type="text" id="clienteInput" class="form-control" autocomplete="off" required value="<?php echo htmlspecialchars($clienteSelecionadoNome, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="cliente_id" id="clienteId" value="<?php echo $clienteSelecionadoId > 0 ? $clienteSelecionadoId : ''; ?>">
              <div id="clienteSuggestions" class="ficha-autocomplete" style="display:none;"></div>
            </div>

            <div class="row g-3">
              <div class="col-md-8">
                <label class="ficha-form-label">Descricao</label>
                <input type="text" name="descricao" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="ficha-form-label">Valor (R$)</label>
                <input type="number" step="0.01" name="valor" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="ficha-form-label">Data</label>
                <input type="date" name="data_tatuagem" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="ficha-form-label">Hora inicio</label>
                <input type="time" name="hora_inicio" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="ficha-form-label">Hora fim</label>
                <input type="time" name="hora_fim" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="ficha-form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="agendado">Agendado</option>
                  <option value="confirmado">Confirmado</option>
                  <option value="cancelado">Cancelado</option>
                  <option value="concluido">Concluido</option>
                </select>
              </div>
            </div>

            <div class="d-flex flex-column flex-md-row gap-3 mt-4">
              <button class="btn ficha-btn ficha-btn-primary flex-fill">Salvar agendamento</button>
              <a class="btn ficha-btn ficha-btn-secondary flex-fill" href="../agenda/">Abrir agenda</a>
            </div>
          </form>
        </div>

        <div class="col-lg-5">
          <div class="ficha-summary">
            <h2 class="ficha-panel-title">Resumo rapido</h2>
            <div class="ficha-stats">
              <div class="ficha-stat">
                <span>Cliente preselecionado</span>
                <strong id="clienteResumo"><?php echo $clienteSelecionadoNome !== '' ? htmlspecialchars($clienteSelecionadoNome, ENT_QUOTES, 'UTF-8') : 'Nenhum'; ?></strong>
              </div>
              <div class="ficha-stat">
                <span>Status inicial</span>
                <strong id="statusResumo">Agendado</strong>
              </div>
              <div class="ficha-stat">
                <span>Proximo passo</span>
                <strong>Salvar e revisar</strong>
              </div>
            </div>
            <div class="ficha-panel mt-4">
              <h3 class="ficha-panel-title">Fluxo recomendado</h3>
              <p class="ficha-copy mb-0">1. Escolha o cliente. 2. Defina descricao, valor e horario. 3. Salve. 4. Ajuste ou acompanhe depois na agenda visual.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
$(function () {
  const $statusResumo = $('#statusResumo');
  const $clienteResumo = $('#clienteResumo');

  $('select[name="status"]').on('change', function () {
    $statusResumo.text($(this).find('option:selected').text());
  });

  $('#clienteInput').on('input', function () {
    const valor = $(this).val();
    $('#clienteId').val('');
    $clienteResumo.text(valor || 'Nenhum');

    if (valor.length < 2) {
      $('#clienteSuggestions').hide();
      return;
    }

    $.get('buscar_clientes.php', { busca: valor }, function (data) {
      $('#clienteSuggestions').html(data).show();
    });
  });

  $(document).on('click', '.autocomplete-suggestion', function () {
    const id = $(this).data('id');
    if (!id) {
      return;
    }
    $('#clienteInput').val($(this).text());
    $('#clienteId').val(id);
    $clienteResumo.text($(this).text());
    $('#clienteSuggestions').hide();
  });

  $(document).click(function (event) {
    if (!$(event.target).closest('#clienteInput, #clienteSuggestions').length) {
      $('#clienteSuggestions').hide();
    }
  });

  $('#formTatuagem').submit(function (event) {
    event.preventDefault();

    if (!$('#clienteId').val()) {
      $('#alerta').html('<div class="ficha-alert ficha-alert-danger mb-4">Escolha um cliente valido antes de salvar.</div>');
      return;
    }

    $.ajax({
      url: 'cadastrar_tatuagem.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function (response) {
        const classe = response.status === 'success' ? 'success' : 'danger';
        $('#alerta').html('<div class="ficha-alert ficha-alert-' + classe + ' mb-4">' + response.message + '</div>');
        if (response.status === 'success') {
          $('#formTatuagem')[0].reset();
          $('#clienteId').val('');
          $clienteResumo.text('Nenhum');
          $statusResumo.text('Agendado');
        }
      },
      error: function () {
        $('#alerta').html('<div class="ficha-alert ficha-alert-danger mb-4">Nao foi possivel salvar o agendamento.</div>');
      }
    });
  });
});
</script>
</body>
</html>
