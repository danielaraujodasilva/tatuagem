<?php
require 'config.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? $_POST['action'] ?? '';

try {

    function moverClienteWhatsApp($id, $etapa) {
        $arquivo = __DIR__ . '/data/clientes.json';
        if (!file_exists($arquivo)) {
            return false;
        }

        $clientes = json_decode(file_get_contents($arquivo), true);
        if (!is_array($clientes)) {
            $clientes = [];
        }

        $clienteId = preg_replace('/^wa_/', '', (string)$id);

        foreach ($clientes as &$cliente) {
            if ((string)($cliente['id'] ?? '') === $clienteId) {
                $cliente['etapa'] = (string)$etapa;
                file_put_contents($arquivo, json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return true;
            }
        }

        return false;
    }

    // 🔥 FUNÇÃO PRA VALIDAR ETAPA
    function etapaExiste($conn, $etapa) {
        $stmt = $conn->prepare("SELECT id FROM pipelines WHERE id = ?");
        $stmt->execute([$etapa]);
        return $stmt->fetch() ? true : false;
    }

    // 🔥 GET ALL
    if ($action === 'getAll') {
        $stmt = $conn->query("SELECT *, etapa_funil AS etapa FROM leads ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // 🔥 CREATE / UPDATE
    if ($action === 'create' || $action === 'update') {

        $id                  = (int)($_POST['id'] ?? 0);
        $nome                = trim($_POST['nome'] ?? '');
        $telefone            = trim($_POST['telefone'] ?? '');
        $interesse           = trim($_POST['interesse'] ?? '');
        $valor               = $_POST['valor'] ?? 0;
        $origem              = trim($_POST['origem'] ?? '');
        $status              = trim($_POST['status'] ?? '');
        $etapa               = $_POST['etapa'] ?? '1';
        $data_ultimo_contato = $_POST['data_ultimo_contato'] ?? null;

        if (empty($nome) || empty($telefone)) {
            echo json_encode(['error' => 'Nome e telefone são obrigatórios']);
            exit;
        }

        // 🔥 VALIDA ETAPA (aqui é o upgrade)
        if (!etapaExiste($conn, $etapa)) {
            $etapa = 1; // fallback
        }

        if ($action === 'create') {

            $sql = "INSERT INTO leads 
                (nome, telefone, interesse, valor, origem, status, etapa_funil, data_ultimo_contato, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $nome,
                $telefone,
                $interesse,
                $valor,
                $origem,
                $status,
                $etapa,
                $data_ultimo_contato
            ]);

        } else {

            $sql = "UPDATE leads 
                SET nome=?, telefone=?, interesse=?, valor=?, origem=?, status=?, etapa_funil=?, data_ultimo_contato=? 
                WHERE id=?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $nome,
                $telefone,
                $interesse,
                $valor,
                $origem,
                $status,
                $etapa,
                $data_ultimo_contato,
                $id
            ]);
        }

        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 🔥 DELETE
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 🔥 MOVE (drag and drop)
    if ($action === 'move') {
        $id = $_POST['id'] ?? 0;
        $etapa = $_POST['etapa'] ?? '1';

        // valida etapa antes de mover
        if (!etapaExiste($conn, $etapa)) {
            echo json_encode(['error' => 'Etapa inválida']);
            exit;
        }

        if (strpos((string)$id, 'wa_') === 0) {
            if (moverClienteWhatsApp($id, $etapa)) {
                echo json_encode(['status' => 'ok']);
            } else {
                echo json_encode(['error' => 'Cliente WhatsApp nÃ£o encontrado']);
            }
            exit;
        }

        $id = (int)$id;

        $conn->prepare("UPDATE leads SET etapa_funil=? WHERE id=?")
             ->execute([$etapa, $id]);

        echo json_encode(['status' => 'ok']);
        exit;
    }

    // 🔥 INTERAÇÕES
    if ($action === 'addInteraction') {
        $id  = (int)($_POST['id'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'Outros';
        $msg = trim($_POST['msg'] ?? '');

        if ($id && $msg) {
            $stmt = $conn->prepare("
                INSERT INTO interacoes (lead_id, tipo, mensagem, data) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$id, $tipo, $msg]);
            echo json_encode(['status' => 'ok']);
        }
        exit;
    }

    if ($action === 'getHistory') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $conn->prepare("
            SELECT * FROM interacoes 
            WHERE lead_id = ? 
            ORDER BY data DESC
        ");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    echo json_encode(['error' => 'Ação inválida']);

} catch (Exception $e) {
    echo json_encode(['error' => 'Erro no servidor: ' . $e->getMessage()]);
}
