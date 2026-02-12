<?php
include("../config/conexao.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cliente_id'])) {

    $cliente_id  = $_POST['cliente_id'];
    $descricao   = $_POST['descricao'];
    $valor       = $_POST['valor'];
    $data        = $_POST['data_tatuagem'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim    = $_POST['hora_fim'];
    $status      = $_POST['status'];
    $observacoes = $_POST['observacoes'];

    $stmt = $conn->prepare("
        INSERT INTO tatuagens
        (cliente_id, descricao, valor, data_tatuagem, hora_inicio, hora_fim, status, observacoes)
        VALUES (?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "isdsssss",
        $cliente_id,
        $descricao,
        $valor,
        $data,
        $hora_inicio,
        $hora_fim,
        $status,
        $observacoes
    );

    if ($stmt->execute()) {
        echo json_encode(['status'=>'success','message'=>'Tatuagem cadastrada com sucesso!']);
    } else {
        echo json_encode(['status'=>'error','message'=>$stmt->error]);
    }

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
body{
    background:#121212;
    color:#f5f5f5;
}
.container{
    margin-top:50px;
    max-width:800px;
}
.autocomplete-suggestions{
    position:absolute;
    background:#333;
    border:1px solid #555;
    z-index:9999;
    max-height:200px;
    overflow-y:auto;
    width:100%;
}
.autocomplete-suggestion{
    padding:10px;
    cursor:pointer;
}
.autocomplete-suggestion:hover{
    background:#444;
}
</style>
</head>

<body>

<div class="container">

<h2 class="mb-4">Cadastrar Tatuagem</h2>

<div id="alerta"></div>

<form id="formTatuagem">

    <!-- Cliente -->
    <div class="mb-3 position-relative">
        <label>Cliente</label>
        <input type="text" class="form-control" id="clienteInput" autocomplete="off" required>
        <input type="hidden" name="cliente_id" id="clienteId">
        <div id="clienteSuggestions" class="autocomplete-suggestions"></div>
    </div>

    <!-- Descrição -->
    <div class="mb-3">
        <label>Descrição</label>
        <input type="text" name="descricao" class="form-control" required>
    </div>

    <!-- Valor -->
    <div class="mb-3">
        <label>Valor (R$)</label>
        <input type="number" step="0.01" name="valor" class="form-control" required>
    </div>

    <!-- Data + Horários -->
    <div class="row">
        <div class="col-md-4">
            <label>Data</label>
            <input type="date" name="data_tatuagem" class="form-control" required>
        </div>

        <div class="col-md-4">
            <label>Hora início</label>
            <input type="time" name="hora_inicio" class="form-control" required>
        </div>

        <div class="col-md-4">
            <label>Hora fim</label>
            <input type="time" name="hora_fim" class="form-control" required>
        </div>
    </div>

    <!-- Status -->
    <div class="mt-3">
        <label>Status</label>
        <select name="status" class="form-select">
            <option value="agendado">Agendado</option>
            <option value="confirmado">Confirmado</option>
            <option value="concluido">Concluído</option>
            <option value="cancelado">Cancelado</option>
        </select>
    </div>

    <!-- Observações -->
    <div class="mt-3">
        <label>Observações</label>
        <textarea name="observacoes" rows="3" class="form-control"></textarea>
    </div>

    <button class="btn btn-success mt-4 w-100">Salvar Tatuagem</button>
</form>

</div>

<script>
$(function(){

    // 🔎 autocomplete cliente
    $('#clienteInput').on('input', function(){
        let valor = $(this).val();

        if(valor.length < 2){
            $('#clienteSuggestions').hide();
            return;
        }

        $.get('buscar_clientes.php',{busca:valor}, function(data){
            $('#clienteSuggestions').html(data).show();
        });
    });

    $(document).on('click','.autocomplete-suggestion', function(){
        $('#clienteInput').val($(this).text());
        $('#clienteId').val($(this).data('id'));
        $('#clienteSuggestions').hide();
    });

    $(document).click(function(e){
        if(!$(e.target).closest('#clienteInput,#clienteSuggestions').length){
            $('#clienteSuggestions').hide();
        }
    });

    // 🚀 submit AJAX
    $('#formTatuagem').submit(function(e){
        e.preventDefault();

        if(!$('#clienteId').val()){
            alert("Seleciona um cliente válido, chefia.");
            return;
        }

        $.post('cadastrar_tatuagem.php', $(this).serialize(), function(res){

            let alerta = `
            <div class="alert alert-${res.status==='success'?'success':'danger'} alert-dismissible fade show">
                ${res.message}
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;

            $('#alerta').html(alerta);

            if(res.status==='success'){
                $('#formTatuagem')[0].reset();
                $('#clienteId').val('');
            }

        }, 'json');
    });

});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
