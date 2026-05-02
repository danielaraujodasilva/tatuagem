<?php

function crmDataDir() {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function crmClientesLegacyPath() {
    return crmDataDir() . '/clientes.json';
}

function crmClientesPath() {
    $runtime = crmDataDir() . '/clientes_runtime.json';
    $legacy = crmClientesLegacyPath();

    if (!file_exists($runtime)) {
        if (is_file($legacy) && filesize($legacy) > 0) {
            copy($legacy, $runtime);
        } else {
            file_put_contents($runtime, "[]");
        }
    }

    return $runtime;
}

function crmDb() {
    static $pdo = null;
    global $conn;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!isset($conn) || !($conn instanceof PDO)) {
        require __DIR__ . '/config.php';
    }

    $pdo = $conn;
    return $pdo;
}

function crmTabelasChatDisponiveis() {
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    try {
        $stmt = crmDb()->query("SHOW TABLES LIKE 'crm_whatsapp_clientes'");
        $ok = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $ok = false;
    }

    return $ok;
}

function crmNormalizarBoolean($valor) {
    return !empty($valor) ? 1 : 0;
}

function crmCarregarClientesJsonFallback() {
    $runtime = crmClientesPath();
    $clientes = json_decode((string)file_get_contents($runtime), true);

    if (!is_array($clientes)) {
        $clientes = [];
    }

    $legacy = crmClientesLegacyPath();
    if (count($clientes) === 0 && is_file($legacy) && filesize($legacy) > 2) {
        $clientesLegacy = json_decode((string)file_get_contents($legacy), true);
        if (is_array($clientesLegacy) && count($clientesLegacy) > 0) {
            $clientes = $clientesLegacy;
            crmSalvarClientesJsonFallback($clientes);
        }
    }

    return $clientes;
}

function crmSalvarClientesJsonFallback($clientes) {
    $path = crmClientesPath();
    $tmp = $path . '.tmp';
    $json = json_encode(array_values($clientes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (is_file($path) && filesize($path) > 2) {
        copy($path, $path . '.bak');
    }

    file_put_contents($tmp, $json === false ? "[]" : $json);
    copy($tmp, $path);
    unlink($tmp);
}

function crmCarregarClientes() {
    if (!crmTabelasChatDisponiveis()) {
        return crmCarregarClientesJsonFallback();
    }

    $sql = "
        SELECT
            c.*,
            m.id AS mensagem_db_id,
            m.de,
            m.texto,
            m.data,
            m.from_me,
            m.message_id,
            m.remote_jid,
            m.status AS mensagem_status,
            m.status_updated_at,
            m.tipo,
            m.media_url,
            m.media_mime,
            m.media_file_name,
            m.transcricao,
            m.transcricao_erro
        FROM crm_whatsapp_clientes c
        LEFT JOIN crm_whatsapp_mensagens m ON m.cliente_id = c.id
        ORDER BY c.updated_at DESC, c.id ASC, m.data ASC, m.id ASC
    ";

    $rows = crmDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $legacyClientes = crmCarregarClientesJsonFallback();
        if ($legacyClientes) {
            crmSalvarClientes($legacyClientes);
            $rows = crmDb()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $clientes = [];

    foreach ($rows as $row) {
        $id = (string)$row['id'];
        if (!isset($clientes[$id])) {
            $clientes[$id] = [
                'id' => $id,
                'numero' => $row['numero'] ?? '',
                'nome' => $row['nome'] ?? 'Cliente',
                'status' => $row['status'] ?? 'novo',
                'etapa' => $row['etapa'] ?? '',
                'atendente' => $row['atendente'] ?? '',
                'interesse' => $row['interesse'] ?? '',
                'valor' => $row['valor'] ?? 0,
                'origem' => $row['origem'] ?? 'WhatsApp',
                'data_ultimo_contato' => $row['data_ultimo_contato'] ?? '',
                'created_at' => $row['created_at'] ?? '',
                'mensagens' => [],
            ];
        }

        if ($row['mensagem_db_id'] === null) {
            continue;
        }

        $clientes[$id]['mensagens'][] = [
            'de' => $row['de'] ?? '',
            'texto' => $row['texto'] ?? '',
            'data' => $row['data'] ?? '',
            'fromMe' => (bool)$row['from_me'],
            'messageId' => $row['message_id'] ?? '',
            'remoteJid' => $row['remote_jid'] ?? '',
            'status' => $row['mensagem_status'] ?? '',
            'status_updated_at' => $row['status_updated_at'] ?? '',
            'tipo' => $row['tipo'] ?? 'texto',
            'mediaUrl' => $row['media_url'] ?? '',
            'mediaMime' => $row['media_mime'] ?? '',
            'mediaFileName' => $row['media_file_name'] ?? '',
            'transcricao' => $row['transcricao'] ?? '',
            'transcricao_erro' => $row['transcricao_erro'] ?? '',
        ];
    }

    return array_values($clientes);
}

function crmSalvarClientes($clientes) {
    if (!crmTabelasChatDisponiveis()) {
        crmSalvarClientesJsonFallback($clientes);
        return;
    }

    $pdo = crmDb();
    $pdo->beginTransaction();

    try {
        $clienteStmt = $pdo->prepare("
            INSERT INTO crm_whatsapp_clientes
                (id, numero, nome, status, etapa, atendente, interesse, valor, origem, data_ultimo_contato, created_at, updated_at)
            VALUES
                (:id, :numero, :nome, :status, :etapa, :atendente, :interesse, :valor, :origem, :data_ultimo_contato, :created_at, NOW())
            ON DUPLICATE KEY UPDATE
                numero = VALUES(numero),
                nome = VALUES(nome),
                status = VALUES(status),
                etapa = VALUES(etapa),
                atendente = VALUES(atendente),
                interesse = VALUES(interesse),
                valor = VALUES(valor),
                origem = VALUES(origem),
                data_ultimo_contato = VALUES(data_ultimo_contato),
                updated_at = NOW()
        ");

        $deleteStmt = $pdo->prepare("DELETE FROM crm_whatsapp_mensagens WHERE cliente_id = ?");
        $mensagemStmt = $pdo->prepare("
            INSERT INTO crm_whatsapp_mensagens
                (cliente_id, de, texto, data, from_me, message_id, remote_jid, status, status_updated_at, tipo, media_url, media_mime, media_file_name, transcricao, transcricao_erro)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $idsMantidos = [];

        foreach (array_values($clientes) as $cliente) {
            $id = trim((string)($cliente['id'] ?? ''));
            if ($id === '') {
                $id = uniqid();
            }
            $idsMantidos[] = $id;

            $clienteStmt->execute([
                ':id' => $id,
                ':numero' => (string)($cliente['numero'] ?? ''),
                ':nome' => (string)($cliente['nome'] ?? 'Cliente'),
                ':status' => (string)($cliente['status'] ?? 'novo'),
                ':etapa' => (string)($cliente['etapa'] ?? ''),
                ':atendente' => (string)($cliente['atendente'] ?? ''),
                ':interesse' => (string)($cliente['interesse'] ?? ''),
                ':valor' => (float)($cliente['valor'] ?? 0),
                ':origem' => (string)($cliente['origem'] ?? 'WhatsApp'),
                ':data_ultimo_contato' => ($cliente['data_ultimo_contato'] ?? '') ?: null,
                ':created_at' => ($cliente['created_at'] ?? '') ?: date('Y-m-d H:i:s'),
            ]);

            $deleteStmt->execute([$id]);

            foreach (($cliente['mensagens'] ?? []) as $msg) {
                $mensagemStmt->execute([
                    $id,
                    (string)($msg['de'] ?? ''),
                    (string)($msg['texto'] ?? ''),
                    ($msg['data'] ?? '') ?: date('Y-m-d H:i:s'),
                    crmNormalizarBoolean($msg['fromMe'] ?? false),
                    (string)($msg['messageId'] ?? ''),
                    (string)($msg['remoteJid'] ?? ''),
                    (string)($msg['status'] ?? ''),
                    ($msg['status_updated_at'] ?? '') ?: null,
                    (string)($msg['tipo'] ?? 'texto'),
                    (string)($msg['mediaUrl'] ?? ''),
                    (string)($msg['mediaMime'] ?? ''),
                    (string)($msg['mediaFileName'] ?? ''),
                    (string)($msg['transcricao'] ?? ''),
                    (string)($msg['transcricao_erro'] ?? ''),
                ]);
            }
        }

        if ($idsMantidos) {
            $placeholders = implode(',', array_fill(0, count($idsMantidos), '?'));
            $pdo->prepare("DELETE FROM crm_whatsapp_clientes WHERE id NOT IN ($placeholders)")->execute($idsMantidos);
        } else {
            $pdo->exec("DELETE FROM crm_whatsapp_clientes");
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
