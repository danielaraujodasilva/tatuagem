<?php
require 'config.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'getAll') {
    $stmt = $conn->query("SELECT * FROM leads ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'create' || $action === 'update') {
    $nome   = $_POST['nome'];
    $tel    = $_POST['telefone'];
    $email  = $_POST['email'] ?? '';
    $valor  = $_POST['valor'] ?? 0;
    $etapa  = $_POST['etapa'];
    $obs    = $_POST['observacao'] ?? '';

    if ($action === 'create') {
        $sql = "INSERT INTO leads (nome, telefone, email, valor, etapa, observacao, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nome, $tel, $email, $valor, $etapa, $obs]);
    } else {
        $id = $_POST['id'];
        $sql = "UPDATE leads SET nome=?, telefone=?, email=?, valor=?, etapa=?, observacao=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nome, $tel, $email, $valor, $etapa, $obs, $id]);
    }
    exit;
}

if ($action === 'delete') {
    $id = $_POST['id'];
    $conn->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
    $conn->prepare("DELETE FROM interacoes WHERE lead_id=?")->execute([$id]);
    exit;
}

if ($action === 'move') {
    $id = $_POST['id'];
    $etapa = $_POST['etapa'];
    $conn->prepare("UPDATE leads SET etapa=? WHERE id=?")->execute([$etapa, $id]);
    exit;
}