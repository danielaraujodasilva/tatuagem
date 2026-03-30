<?php
require 'config.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? $_POST['action'] ?? '';

try {
    // ====================== GET ALL LEADS ======================
    if ($action === 'getAll') {
        $stmt = $conn->query("SELECT *, etapa_funil AS etapa FROM leads ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ====================== CREATE LEAD ======================
    if ($action === 'create') {
        $nome     = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $valor    = $_POST['valor'] ?? 0;
        $etapa    = $_POST['etapa'] ?? '1';

        if (empty($nome) || empty($telefone)) {
            echo json_encode(['error' => 'Nome e telefone são obrigatórios']);
            exit;
        }

        $sql = "INSERT INTO leads (nome, telefone, valor, etapa_funil, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nome, $telefone, $valor, $etapa]);

        echo json_encode(['status' => 'ok', 'id' => $conn->lastInsertId()]);
        exit;
    }

    // ====================== DELETE ======================
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // ====================== MOVE ======================
    if ($action === 'move') {
        $id    = (int)($_POST['id'] ?? 0);
        $etapa = $_POST['etapa'] ?? '1';
        $conn->prepare("UPDATE leads SET etapa_funil=? WHERE id=?")->execute([$etapa, $id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // ====================== INTERAÇÕES (NOVO) ======================
    if ($action === 'addInteraction') {
        $id  = (int)($_POST['id'] ?? 0);
        $msg = trim($_POST['msg'] ?? '');
        if ($id && $msg) {
            $sql = "INSERT INTO interacoes (lead_id, mensagem) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id, $msg]);
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['error' => 'Dados inválidos']);
        }
        exit;
    }

    if ($action === 'getHistory') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM interacoes WHERE lead_id = ? ORDER BY data DESC");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    echo json_encode(['error' => 'Ação inválida: ' . $action]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Erro no servidor: ' . $e->getMessage()]);
}