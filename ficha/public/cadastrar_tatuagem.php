<?php
include("../config/conexao.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cliente_id'])) {
  $cliente_id = $_POST['cliente_id'];
  $descricao = $_POST['descricao'];
  $valor = $_POST['valor'];
  $data_tatuagem = $_POST['data_tatuagem'] . ' ' . $_POST['hora_tatuagem'];

  $sql_tatuagem = "INSERT INTO tatuagens (cliente_id, descricao, valor, data_tatuagem)
                   VALUES ('$cliente_id', '$descricao', '$valor', '$data_tatuagem')";

  if ($conn->query($sql_tatuagem) === TRUE) {
    echo json_encode(['status' => 'success', 'message' => 'Tatuagem cadastrada com sucesso!']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $conn->error]);
  }

  $conn->close();
  exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Cadastrar Tatuagem</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body { background-color: #121212; color: #f5f5f5; }
    .container { margin-top: 50px; }
    .autocomplete-suggestions {
      position: absolute;
      background-color: #333;
      border: 1px solid #555;
      z-index: 9999;
      max-height: 200px;
      overflow-y: auto;
      width: 100%;
    }
    .autocomplete-suggestion {
      padding: 10px;
      cursor: pointer;
    }
    .autocomplete-suggestion:hover {
      background-color: #444;
    }
  </style>
</head>
<body>
<div class="container">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Sistema de Cadastro</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link active" href="https://danieltatuador.com/ficha/">Cadastrar Cliente</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="https://danieltatuador.com/ficha/public/cadastrar_tatuagem.php">Cadastrar Tatuagem</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<h1 class="mb-4 mt-4">Cadastrar Tatuagem</h1>

<div id="alerta"></div>

<form id="formTatuagem">
  <div class="mb-3 position-relative">
    <label class="form-label">Identifique o Cliente (Nome, E-mail ou Telefone)</label>
    <input type="text" class="form-control" id="clienteInput" autocomplete="off" required>
    <input type="hidden" name="cliente_id" id="clienteId">
    <div id="clienteSuggestions" class="autocomplete-suggestions"></div>
  </div>

  <div class="mb-3">
    <label class="form-label">Descrição</label>
    <input type="text" class="form-control" name="descricao" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Valor (R$)</label>
    <input type="number" step="0.01" class="form-control" name="valor" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Data da Tatuagem</label>
    <input type="date" class="form-control" name="data_tatuagem" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Hora da Tatuagem</label>
    <input type="time" class="form-control" name="hora_tatuagem" required>
  </div>
  <button type="submit" class="btn btn-success">Salvar Tatuagem</button>
</form>

</div>

<script>
$(document).ready(function(){
  // autocomplete cliente
  $('#clienteInput').on('input', function(){
    let valor = $(this).val();
    if (valor.length < 2) {
      $('#clienteSuggestions').hide();
      return;
    }
    $.ajax({
      url: 'buscar_clientes.php',
      method: 'GET',
      data: { busca: valor },
      success: function(data){
        $('#clienteSuggestions').html(data).show();
      }
    });
  });

  $(document).on('click', '.autocomplete-suggestion', function(){
    let clienteId = $(this).data('id');
    let clienteNome = $(this).text();
    $('#clienteInput').val(clienteNome);
    $('#clienteId').val(clienteId);
    $('#clienteSuggestions').hide();
  });

  $(document).click(function(e) {
    if (!$(e.target).closest('#clienteInput, #clienteSuggestions').length) {
      $('#clienteSuggestions').hide();
    }
  });

  // envio do formulário via AJAX
  $('#formTatuagem').submit(function(e){
    e.preventDefault();
    if (!$('#clienteId').val()) {
      alert("Por favor, selecione um cliente válido.");
      return;
    }

    $.ajax({
      url: 'cadastrar_tatuagem.php',
      method: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(response){
        let alertaHtml = `
          <div class="alert alert-${response.status === 'success' ? 'success' : 'danger'} alert-dismissible fade show" role="alert">
            ${response.message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        `;
        $('#alerta').html(alertaHtml);

        if (response.status === 'success') {
          $('#formTatuagem')[0].reset();
          $('#clienteId').val('');
        }
      },
      error: function(){
        let alertaErro = `
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Erro ao cadastrar a tatuagem.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        `;
        $('#alerta').html(alertaErro);
      }
    });
  });

});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
