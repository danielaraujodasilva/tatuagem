<?php
require 'config.php';
require_once __DIR__ . '/data_store.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? $_POST['action'] ?? '';

try {
    function etapaExiste($conn, $etapa) {
        $stmt = $conn->prepare("SELECT id FROM pipelines WHERE id = ?");
        $stmt->execute([$etapa]);
        return (bool)$stmt->fetch();
    }

    function primeiraEtapa($conn) {
        $stmt = $conn->query("SELECT id, nome FROM pipelines ORDER BY ordem, id");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (trim((string)($row['nome'] ?? '')) !== '') {
                return (string)$row['id'];
            }
        }
        return '1';
    }

    function carregarClientesWhatsApp() {
        return [crmClientesPath(), crmCarregarClientes()];
    }

    function salvarClientesWhatsApp($arquivo, $clientes) {
        crmSalvarClientes($clientes);
    }

    function atualizarClienteWhatsApp($id, $dados) {
        [$arquivo, $clientes] = carregarClientesWhatsApp();
        $clienteId = preg_replace('/^wa_/', '', (string)$id);

        foreach ($clientes as &$cliente) {
            if ((string)($cliente['id'] ?? '') === $clienteId) {
                foreach ($dados as $campo => $valor) {
                    $cliente[$campo] = $valor;
                }
                salvarClientesWhatsApp($arquivo, $clientes);
                return true;
            }
        }

        return false;
    }

    function excluirClienteWhatsApp($id) {
        [$arquivo, $clientes] = carregarClientesWhatsApp();
        $clienteId = preg_replace('/^wa_/', '', (string)$id);
        $antes = count($clientes);

        $clientes = array_values(array_filter($clientes, function ($cliente) use ($clienteId) {
            return (string)($cliente['id'] ?? '') !== $clienteId;
        }));

        if (count($clientes) === $antes) {
            return false;
        }

        salvarClientesWhatsApp($arquivo, $clientes);
        return true;
    }

    if ($action === 'getAll') {
        $stmt = $conn->query("SELECT *, etapa_funil AS etapa FROM leads ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'create' || $action === 'update') {
        $idRaw               = $_POST['id'] ?? 0;
        $id                  = (int)$idRaw;
        $nome                = trim($_POST['nome'] ?? '');
        $telefone            = trim($_POST['telefone'] ?? '');
        $interesse           = trim($_POST['interesse'] ?? '');
        $valor               = $_POST['valor'] ?? 0;
        $origem              = trim($_POST['origem'] ?? '');
        $status              = trim($_POST['status'] ?? '');
        $etapa               = $_POST['etapa'] ?? primeiraEtapa($conn);
        $data_ultimo_contato = $_POST['data_ultimo_contato'] ?? null;

        if ($nome === '' || $telefone === '') {
            echo json_encode(['error' => 'Nome e telefone sao obrigatorios']);
            exit;
        }

        if (!etapaExiste($conn, $etapa)) {
            $etapa = primeiraEtapa($conn);
        }

        if ($action === 'update' && strpos((string)$idRaw, 'wa_') === 0) {
            $ok = atualizarClienteWhatsApp($idRaw, [
                'nome' => $nome,
                'numero' => $telefone,
                'interesse' => $interesse,
                'valor' => $valor,
                'origem' => $origem ?: 'WhatsApp',
                'status' => $status,
                'etapa' => (string)$etapa,
                'data_ultimo_contato' => $data_ultimo_contato,
            ]);

            echo json_encode($ok ? ['status' => 'ok'] : ['error' => 'Cliente WhatsApp nao encontrado']);
            exit;
        }

        if ($action === 'create') {
            $sql = "INSERT INTO leads
                (nome, telefone, interesse, valor, origem, status, etapa_funil, data_ultimo_contato, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $telefone, $interesse, $valor, $origem, $status, $etapa, $data_ultimo_contato]);
        } else {
            $sql = "UPDATE leads
                SET nome=?, telefone=?, interesse=?, valor=?, origem=?, status=?, etapa_funil=?, data_ultimo_contato=?
                WHERE id=?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $telefone, $interesse, $valor, $origem, $status, $etapa, $data_ultimo_contato, $id]);
        }

        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'delete') {
        $idRaw = $_POST['id'] ?? 0;

        if (strpos((string)$idRaw, 'wa_') === 0) {
            $ok = excluirClienteWhatsApp($idRaw);
            echo json_encode($ok ? ['status' => 'ok'] : ['error' => 'Cliente WhatsApp nao encontrado']);
            exit;
        }

        $id = (int)$idRaw;
        $conn->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($action === 'move') {
        $id = $_POST['id'] ?? 0;
        $etapa = $_POST['etapa'] ?? primeiraEtapa($conn);

        if (!etapaExiste($conn, $etapa)) {
            echo json_encode(['error' => 'Etapa invalida']);
            exit;
        }

        if (strpos((string)$id, 'wa_') === 0) {
            $ok = atualizarClienteWhatsApp($id, ['etapa' => (string)$etapa]);
            echo json_encode($ok ? ['status' => 'ok'] : ['error' => 'Cliente WhatsApp nao encontrado']);
            exit;
        }

        $conn->prepare("UPDATE leads SET etapa_funil=? WHERE id=?")->execute([$etapa, (int)$id]);
        echo json_encode(['status' => 'ok']);
        exit;
    }

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
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['error' => 'Acao invalida']);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro no servidor: ' . $e->getMessage()]);
}
