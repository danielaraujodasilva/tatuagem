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

    // ====================== CREATE or UPDATE ======================
    if ($action === 'create' || $action === 'update') {
        // Tenta pegar dos $_POST (mais comum com FormData)
        $nome   = $_POST['nome'] ?? '';
        $tel    = $_POST['telefone'] ?? '';
        $email  = $_POST['email'] ?? '';
        $valor  = $_POST['valor'] ?? 0;
        $etapa  = $_POST['etapa'] ?? '1';
        $obs    = $_POST['observacao'] ?? '';

        // Se ainda estiver vazio, tenta ler raw input (backup)
        if (empty($nome) && empty($tel)) {
            $raw = file_get_contents('php://input');
            parse_str($raw, $rawData);
            $nome   = $rawData['nome'] ?? '';
            $tel    = $rawData['telefone'] ?? '';
            $email  = $rawData['email'] ?? '';
            $valor  = $rawData['valor'] ?? 0;
            $etapa  = $rawData['etapa'] ?? '1';
            $obs    = $rawData['observacao'] ?? '';
        }

        if (empty($nome) || empty($tel)) {
            echo json_encode(['error' => 'Nome e telefone são obrigatórios']);
            exit;
        }

        if ($action === 'create') {
            $sql = "INSERT INTO leads (nome, telefone, email, valor, etapa_funil, observacao, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $tel, $email, $valor, $etapa, $obs]);
            echo json_encode(['status' => 'ok', 'id' => $conn->lastInsertId()]);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $sql = "UPDATE leads SET nome=?, telefone=?, email=?, valor=?, etapa_funil=?, observacao=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $tel, $email, $valor, $etapa, $obs, $id]);
            echo json_encode(['status' => 'ok']);
        }
        exit;
    }

    // ====================== DELETE ======================
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        $conn->prepare("DELETE FROM interacoes WHERE lead_id=?")->execute([$id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // ====================== MOVE (drag and drop) ======================
    if ($action === 'move') {
        $id = (int)($_POST['id'] ?? 0);
        $etapa = $_POST['etapa'] ?? '1';
        $conn->prepare("UPDATE leads SET etapa_funil=? WHERE id=?")->execute([$etapa, $id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    echo json_encode(['error' => 'Ação inválida: ' . $action]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Erro no servidor: ' . $e->getMessage()]);
}