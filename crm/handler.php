<?php
require 'config.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'getAll') {
        // Adaptado para sua tabela real
        $stmt = $conn->query("SELECT *, etapa_funil AS etapa FROM leads ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $nome   = $_POST['nome'] ?? '';
        $tel    = $_POST['telefone'] ?? '';
        $email  = $_POST['email'] ?? '';
        $valor  = $_POST['valor'] ?? 0;
        $etapa  = $_POST['etapa'] ?? 'novo';
        $obs    = $_POST['observacao'] ?? '';

        if (empty($nome) || empty($tel)) {
            echo json_encode(['error' => 'Nome e telefone são obrigatórios']);
            exit;
        }

        if ($action === 'create') {
            $sql = "INSERT INTO leads (nome, telefone, email, valor, etapa_funil, observacao) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $tel, $email, $valor, $etapa, $obs]);
            echo json_encode(['status' => 'ok', 'id' => $conn->lastInsertId()]);
        } else {
            $id = (int)$_POST['id'];
            $sql = "UPDATE leads SET nome=?, telefone=?, email=?, valor=?, etapa_funil=?, observacao=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $tel, $email, $valor, $etapa, $obs, $id]);
            echo json_encode(['status' => 'ok']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        $conn->prepare("DELETE FROM interacoes WHERE lead_id=?")->execute([$id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'move') {
        $id = (int)$_POST['id'];
        $etapa = $_POST['etapa'];
        $conn->prepare("UPDATE leads SET etapa_funil=? WHERE id=?")->execute([$etapa, $id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    echo json_encode(['error' => 'Ação inválida']);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}