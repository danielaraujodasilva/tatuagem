<?php
include("../config/conexao.php");

/* =====================================================
   CADASTRAR TATUAGEM (POST)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cliente_id'])) {

    $cliente_id = intval($_POST['cliente_id']);
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $valor = floatval($_POST['valor']);

    $data_tatuagem = $_POST['data_tatuagem'] . ' ' . $_POST['hora_tatuagem'];

    $sql = "
        INSERT INTO tatuagens 
        (cliente_id, descricao, valor, data_tatuagem)
        VALUES
        ($cliente_id, '$descricao', $valor, '$data_tatuagem')
    ";

    if ($conn->query($sql)) {
        echo json_encode(['status'=>'success','message'=>'Tatuagem salva. Bora rabiscar pele.']);
    } else {
        echo json_encode(['status'=>'error','message'=>$conn->error]);
    }

    exit();
}


/* =====================================================
   API LISTAR PARA O CALENDÁRIO (GET ?api=listar)
===================================================== */
if (isset($_GET['api']) && $_GET['api'] === 'listar') {

    $sql = "
        SELECT 
            t.id,
            t.descricao,
            t.valor,
            t.data_tatuagem,
            c.nome AS cliente
        FROM tatuagens t
        LEFT JOIN clientes c ON c.id = t.cliente_id
        ORDER BY t.data_tatuagem
    ";

    $res = $conn->query($sql);

    $eventos = [];

    while($row = $res->fetch_assoc()){
        $eventos[] = [
            'id' => $row['id'],
            'title' => $row['cliente'].' - '.$row['descricao'],
            'start' => $row['data_tatuagem']
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($eventos);
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Agenda Tatuagem</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
body{background:#121212;color:#eee}
.autocomplete-suggestions{
    position:absolute;
    background:#222;
    border:1px solid #444;
    width:100%;
    z-index:999;
}
.autocomplete-suggestion{
    padding:8px;
    cursor:pointer;
}
.autocomplete-suggestion:hover{
    background:#333;
}
</style>
</head>
<body>

<div class="container mt-5">

<h2 class="mb-4">Cadastrar Tatuagem</h2>

<div id="alerta"></div>

<form id="formTatuagem">

<div class="mb-3 position-relative">
<label>Cliente</label>
<input type="text" id="clienteInput" class="form-control" autocomplete="off">
<input type="hidden" name="cliente_id" id="clienteId">
<div id="clienteSuggestions" class="autocomplete-suggestions"></div>
</div>

<div class="mb-3">
<label>Descrição</label>
<input type="text" name="descricao" class="form-control" required>
</div>

<div class="mb-3">
<label>Valor</label>
<input type="number" step="0.01" name="valor" class="form-control" required>
</div>

<div class="row">
<div class="col">
<input type="date" name="data_tatuagem" class="form-control" required>
</div>
<div class="col">
<input type="time" name="hora_tatuagem" class="form-control" required>
</div>
</div>

<button class="btn btn-success mt-3">Salvar</button>

</form>

</div>

<script>
/* =========================
   AUTOCOMPLETE CLIENTE
========================= */
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


/* =========================
   SALVAR AJAX
========================= */
$('#formTatuagem').submit(function(e){

    e.preventDefault();

    if(!$('#clienteId').val()){
        alert('Escolhe o cliente primeiro né campeão.');
        return;
    }

    $.post('agenda_tatuagem.php',$(this).serialize(),function(resp){

        let r = JSON.parse(resp);

        $('#alerta').html(`
            <div class="alert alert-${r.status=='success'?'success':'danger'}">
                ${r.message}
            </div>
        `);

        if(r.status=='success'){
            $('#formTatuagem')[0].reset();
        }

    });

});
</script>

</body>
</html>
