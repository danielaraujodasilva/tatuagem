<?php
require 'config.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'getAll') {
        $stmt = $conn->query("SELECT *, etapa_funil AS etapa FROM leads ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $id        = (int)($_POST['id'] ?? 0);
        $nome      = trim($_POST['nome'] ?? '');
        $telefone  = trim($_POST['telefone'] ?? '');
        $interesse = trim($_POST['interesse'] ?? '');
        $valor     = $_POST['valor'] ?? 0;
        $origem    = trim($_POST['origem'] ?? '');
        $status    = trim($_POST['status'] ?? '');
        $etapa     = $_POST['etapa'] ?? '1';

        if (empty($nome) || empty($telefone)) {
            echo json_encode(['error' => 'Nome e telefone são obrigatórios']);
            exit;
        }

        if ($action === 'create') {
            $sql = "INSERT INTO leads (nome, telefone, interesse, valor, origem, status, etapa_funil, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $telefone, $interesse, $valor, $origem, $status, $etapa]);
        } else {
            $sql = "UPDATE leads SET nome=?, telefone=?, interesse=?, valor=?, origem=?, status=?, etapa_funil=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $telefone, $interesse, $valor, $origem, $status, $etapa, $id]);
        }
        echo json_encode(['status' => 'ok', 'id' => $id ?: $conn->lastInsertId()]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'move') {
        $id = (int)($_POST['id'] ?? 0);
        $etapa = $_POST['etapa'] ?? '1';
        $conn->prepare("UPDATE leads SET etapa_funil=? WHERE id=?")->execute([$etapa, $id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'getHistory') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM interacoes WHERE lead_id = ? ORDER BY data DESC");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'addInteraction') {
        $id  = (int)($_POST['id'] ?? 0);
        $msg = trim($_POST['msg'] ?? '');
        if ($id && $msg) {
            $stmt = $conn->prepare("INSERT INTO interacoes (lead_id, mensagem) VALUES (?, ?)");
            $stmt->execute([$id, $msg]);
            echo json_encode(['status' => 'ok']);
        }
        exit;
    }

    echo json_encode(['error' => 'Ação inválida']);

} catch (Exception $e) {
    echo json_encode(['error' => 'Erro no servidor: ' . $e->getMessage()]);
}