<?php
include("../config/conexao.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* =====================================================
   SALVAR TATUAGEM (POST AJAX)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cliente_id'])) {

    header('Content-Type: application/json');

    $cliente_id   = $_POST['cliente_id'];
    $descricao    = $_POST['descricao'];
    $valor        = $_POST['valor'];
    $data         = $_POST['data_tatuagem'];
    $hora         = $_POST['hora_tatuagem'];

    $data_tatuagem = $data . " " . $hora . ":00";

    $stmt = $conn->prepare("
        INSERT INTO tatuagens 
        (cliente_id, descricao, valor, data_tatuagem) 
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("isds", $cliente_id, $descricao, $valor, $data_tatuagem);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Tatuagem salva com sucesso 🔥"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => $stmt->error
        ]);
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
    color:#eee;
}

.container{
    max-width:700px;
    margin-top:60px;
}

.autocomplete-suggestions{
    position:absolute;
    background:#222;
    border:1px solid #444;
    width:100%;
    max-height:200px;
    overflow-y:auto;
    z-index:999;
}

.autocomplete-suggestion{
    padding:10px;
    cursor:pointer;
}

.autocomplete-suggestion:hover{
    background:#333;
}
</style>
</head>

<body>

<div class="container">

<h3 class="mb-4">Cadastrar Tatuagem</h3>

<div id="alerta"></div>

<form id="formTatuagem">

    <!-- CLIENTE -->
    <div class="mb-3 position-relative">
        <label>Cliente</label>
        <input type="text" id="clienteInput" class="form-control" autocomplete="off" required>
        <input type="hidden" name="cliente_id" id="clienteId">
        <div id="clienteSuggestions" class="autocomplete-suggestions" style="display:none;"></div>
    </div>

    <!-- DESCRIÇÃO -->
    <div class="mb-3">
        <label>Descrição</label>
        <input type="text" name="descricao" class="form-control" required>
    </div>

    <!-- VALOR -->
    <div class="mb-3">
        <label>Valor (R$)</label>
        <input type="number" step="0.01" name="valor" class="form-control" required>
    </div>

    <!-- DATA -->
    <div class="mb-3">
        <label>Data</label>
        <input type="date" name="data_tatuagem" class="form-control" required>
    </div>

    <!-- HORA -->
    <div class="mb-3">
        <label>Hora</label>
        <input type="time" name="hora_tatuagem" class="form-control" required>
    </div>

    <button class="btn btn-success w-100">Salvar Tatuagem</button>

</form>

</div>


<script>
$(function(){

/* =====================================================
   AUTOCOMPLETE CLIENTE
===================================================== */

$('#clienteInput').on('input', function(){

    let valor = $(this).val();

    if(valor.length < 2){
        $('#clienteSuggestions').hide();
        return;
    }

    $.get('buscar_clientes.php',{busca:valor},function(data){
        $('#clienteSuggestions').html(data).show();
    });

});

$(document).on('click','.autocomplete-suggestion',function(){
    $('#clienteInput').val($(this).text());
    $('#clienteId').val($(this).data('id'));
    $('#clienteSuggestions').hide();
});

$(document).click(function(e){
    if(!$(e.target).closest('#clienteInput').length){
        $('#clienteSuggestions').hide();
    }
});


/* =====================================================
   SUBMIT AJAX (AGORA FUNCIONA DE VERDADE)
===================================================== */

$('#formTatuagem').submit(function(e){

    e.preventDefault();

    if(!$('#clienteId').val()){
        alert('Seleciona um cliente primeiro, criatura.');
        return;
    }

    $.ajax({
        url:'cadastrar_tatuagem.php',
        method:'POST',
        data:$(this).serialize(),
        dataType:'json',

        success:function(r){

            $('#alerta').html(`
                <div class="alert alert-success">${r.message}</div>
            `);

            $('#formTatuagem')[0].reset();
            $('#clienteId').val('');
        },

        error:function(xhr){
            console.log(xhr.responseText);

            $('#alerta').html(`
                <div class="alert alert-danger">
                    Erro no servidor. Abre o F12 e para de confiar no destino.
                </div>
            `);
        }
    });

});

});
</script>

</body>
</html>
