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

function crmAtualizarClienteWhatsAppPorId($id, array $dados) {
    $clienteId = preg_replace('/^wa_/', '', (string)$id);
    if ($clienteId === '') {
        return null;
    }

    $clientes = crmCarregarClientes();

    foreach ($clientes as &$cliente) {
        if ((string)($cliente['id'] ?? '') !== $clienteId) {
            continue;
        }

        foreach ($dados as $campo => $valor) {
            if (in_array($campo, ['id', 'mensagens'], true)) {
                continue;
            }
            $cliente[$campo] = $valor;
        }

        $cliente['updated_at'] = date('Y-m-d H:i:s');
        $atualizado = $cliente;
        crmSalvarClientes($clientes);
        return $atualizado;
    }

    return null;
}

function crmQuickRepliesPath() {
    return crmDataDir() . '/quick_replies.json';
}

function crmQuickRepliesPadrao() {
    return [
        [
            'id' => 'orcamento-inicial',
            'titulo' => 'Orcamento inicial',
            'categoria' => 'Orcamento',
            'atalho' => '/orcamento',
            'texto' => "Oi! Me manda a referencia da tattoo, tamanho aproximado em cm e local do corpo? Com isso eu consigo te passar uma ideia de valor e agenda.",
            'ativo' => true,
        ],
        [
            'id' => 'pedido-referencia',
            'titulo' => 'Pedido de referencia',
            'categoria' => 'Orcamento',
            'atalho' => '/referencia',
            'texto' => "Consegue me enviar uma ou duas imagens de referencia? Pode ser algo no estilo que voce gosta, mesmo que nao seja exatamente igual.",
            'ativo' => true,
        ],
        [
            'id' => 'regras-sinal',
            'titulo' => 'Regras do sinal',
            'categoria' => 'Fechamento',
            'atalho' => '/sinal',
            'texto' => "Para reservar a data, trabalhamos com sinal. Ele entra como parte do valor total e garante seu horario na agenda.",
            'ativo' => true,
        ],
        [
            'id' => 'cuidados-pos',
            'titulo' => 'Cuidados pos-tattoo',
            'categoria' => 'Pos-atendimento',
            'atalho' => '/cuidados',
            'texto' => "Agora e cuidar bem: higienize com sabonete neutro, use a pomada indicada, evite sol, piscina e coçar a regiao. Qualquer duvida me chama por aqui.",
            'ativo' => true,
        ],
        [
            'id' => 'cliente-sumido',
            'titulo' => 'Cliente sem retorno',
            'categoria' => 'Follow-up',
            'atalho' => '/retorno',
            'texto' => "Oi! Passando para saber se voce ainda quer seguir com essa ideia de tattoo. Se quiser, posso te ajudar a ajustar tamanho, local ou valor.",
            'ativo' => true,
        ],
        [
            'id' => 'cover-up',
            'titulo' => 'Cover-up',
            'categoria' => 'Orcamento',
            'atalho' => '/coverup',
            'texto' => "Para cover-up, preciso ver uma foto bem nítida da tattoo atual e entender o que voce gostaria de cobrir ou transformar. Assim consigo avaliar possibilidades reais.",
            'ativo' => true,
        ],
    ];
}

function crmCarregarRespostasRapidas() {
    $path = crmQuickRepliesPath();

    if (!is_file($path)) {
        crmSalvarRespostasRapidas(crmQuickRepliesPadrao());
    }

    $dados = json_decode((string)file_get_contents($path), true);
    if (!is_array($dados)) {
        $dados = crmQuickRepliesPadrao();
        crmSalvarRespostasRapidas($dados);
    }

    return array_values(array_filter($dados, static function ($item) {
        return is_array($item);
    }));
}

function crmSalvarRespostasRapidas(array $respostas) {
    $path = crmQuickRepliesPath();
    $tmp = $path . '.tmp';
    $normalizadas = [];

    foreach ($respostas as $resposta) {
        if (!is_array($resposta)) {
            continue;
        }

        $titulo = trim((string)($resposta['titulo'] ?? ''));
        $texto = trim((string)($resposta['texto'] ?? ''));
        if ($titulo === '' || $texto === '') {
            continue;
        }

        $id = trim((string)($resposta['id'] ?? ''));
        if ($id === '') {
            $id = uniqid('qr_', true);
        }

        $normalizadas[] = [
            'id' => $id,
            'titulo' => $titulo,
            'categoria' => trim((string)($resposta['categoria'] ?? 'Geral')) ?: 'Geral',
            'atalho' => trim((string)($resposta['atalho'] ?? '')),
            'texto' => $texto,
            'ativo' => !isset($resposta['ativo']) || (bool)$resposta['ativo'],
        ];
    }

    $json = json_encode($normalizadas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($tmp, $json === false ? "[]" : $json);
    copy($tmp, $path);
    unlink($tmp);
}

function crmAutomationRulesPath() {
    return crmDataDir() . '/automation_rules.json';
}

function crmAutomacoesPadrao() {
    return [
        [
            'id' => 'lead-orcamento',
            'titulo' => 'Criar lead por palavra-chave',
            'evento' => 'mensagem_recebida',
            'acao' => 'criar_lead',
            'palavras_chave' => 'orcamento, orçamento, valor, preço, preco, tattoo, tatuagem',
            'atraso_horas' => 0,
            'status_destino' => 'novo',
            'mensagem' => 'Quando a mensagem recebida tiver uma dessas palavras, o webhook cria o lead e coloca o atendimento com o bot.',
            'ativo' => true,
        ],
        [
            'id' => 'alerta-sem-resposta',
            'titulo' => 'Alertar conversa sem resposta',
            'evento' => 'sem_resposta',
            'acao' => 'alerta',
            'palavras_chave' => '',
            'atraso_horas' => 24,
            'status_destino' => 'sem_retorno',
            'mensagem' => 'Cliente aguardando resposta ha mais de 24h. Priorizar atendimento humano.',
            'ativo' => true,
        ],
        [
            'id' => 'follow-up-orcamento',
            'titulo' => 'Follow-up de orcamento parado',
            'evento' => 'orcamento_sem_retorno',
            'acao' => 'enviar_mensagem',
            'palavras_chave' => '',
            'atraso_horas' => 48,
            'status_destino' => 'sem_retorno',
            'mensagem' => 'Oi! Passando para saber se voce ainda quer seguir com essa ideia de tattoo. Posso ajustar tamanho, local ou valor.',
            'ativo' => true,
        ],
        [
            'id' => 'cuidados-pos-tattoo',
            'titulo' => 'Enviar cuidados pos-tattoo',
            'evento' => 'sessao_concluida',
            'acao' => 'enviar_mensagem',
            'palavras_chave' => '',
            'atraso_horas' => 0,
            'status_destino' => 'fechado',
            'mensagem' => 'Sessao concluida: enviar cuidados pos-tattoo e registrar retorno se necessario.',
            'ativo' => true,
        ],
        [
            'id' => 'avaliacao-30-dias',
            'titulo' => 'Pedir avaliacao e foto cicatrizada',
            'evento' => 'dias_apos_sessao',
            'acao' => 'enviar_mensagem',
            'palavras_chave' => '',
            'atraso_horas' => 720,
            'status_destino' => '',
            'mensagem' => 'Ja fazem 30 dias da tattoo. Pedir avaliacao, foto cicatrizada e nova ideia de tattoo.',
            'ativo' => true,
        ],
        [
            'id' => 'lembrete-24h-agenda',
            'titulo' => 'Lembrete 24h antes da sessao',
            'evento' => 'antes_da_sessao',
            'acao' => 'enviar_mensagem',
            'palavras_chave' => '',
            'atraso_horas' => 24,
            'status_destino' => 'agendado',
            'mensagem' => 'Lembrar cliente da sessao de amanha, horario, sinal e orientacoes pre-tattoo.',
            'ativo' => true,
        ],
    ];
}

function crmCarregarAutomacoes() {
    $path = crmAutomationRulesPath();

    if (!is_file($path)) {
        crmSalvarAutomacoes(crmAutomacoesPadrao());
    }

    $dados = json_decode((string)file_get_contents($path), true);
    if (!is_array($dados)) {
        $dados = crmAutomacoesPadrao();
        crmSalvarAutomacoes($dados);
    }

    return array_values(array_filter($dados, static function ($item) {
        return is_array($item);
    }));
}

function crmSalvarAutomacoes(array $automacoes) {
    $path = crmAutomationRulesPath();
    $tmp = $path . '.tmp';
    $normalizadas = [];

    foreach ($automacoes as $automacao) {
        if (!is_array($automacao)) {
            continue;
        }

        $titulo = trim((string)($automacao['titulo'] ?? ''));
        if ($titulo === '') {
            continue;
        }

        $id = trim((string)($automacao['id'] ?? ''));
        if ($id === '') {
            $id = uniqid('auto_', true);
        }

        $normalizadas[] = [
            'id' => $id,
            'titulo' => $titulo,
            'evento' => trim((string)($automacao['evento'] ?? 'mensagem_recebida')) ?: 'mensagem_recebida',
            'acao' => trim((string)($automacao['acao'] ?? 'alerta')) ?: 'alerta',
            'palavras_chave' => trim((string)($automacao['palavras_chave'] ?? '')),
            'atraso_horas' => max(0, (int)($automacao['atraso_horas'] ?? 0)),
            'status_destino' => trim((string)($automacao['status_destino'] ?? '')),
            'mensagem' => trim((string)($automacao['mensagem'] ?? '')),
            'ativo' => !empty($automacao['ativo']),
        ];
    }

    $json = json_encode($normalizadas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($tmp, $json === false ? "[]" : $json);
    copy($tmp, $path);
    unlink($tmp);
}

function crmNormalizarTextoAutomacao($texto) {
    $texto = trim((string)$texto);
    if (function_exists('mb_strtolower')) {
        $texto = mb_strtolower($texto, 'UTF-8');
    } else {
        $texto = strtolower($texto);
    }

    return preg_replace('/\s+/u', ' ', $texto);
}

function crmPalavrasAutomacao(array $automacao) {
    $texto = (string)($automacao['palavras_chave'] ?? '');
    $partes = preg_split('/[,;\n]+/', $texto) ?: [];
    $palavras = [];

    foreach ($partes as $parte) {
        $palavra = crmNormalizarTextoAutomacao($parte);
        if ($palavra !== '') {
            $palavras[] = $palavra;
        }
    }

    return array_values(array_unique($palavras));
}

function crmAutomacaoDisparaLead($mensagem) {
    $mensagem = crmNormalizarTextoAutomacao($mensagem);
    if ($mensagem === '') {
        return null;
    }

    foreach (crmCarregarAutomacoes() as $automacao) {
        if (empty($automacao['ativo']) || ($automacao['evento'] ?? '') !== 'mensagem_recebida' || ($automacao['acao'] ?? '') !== 'criar_lead') {
            continue;
        }

        $palavras = crmPalavrasAutomacao($automacao);
        if (!$palavras) {
            return $automacao;
        }

        foreach ($palavras as $palavra) {
            if (function_exists('mb_strpos')) {
                if (mb_strpos($mensagem, $palavra) !== false) {
                    return $automacao;
                }
            } elseif (strpos($mensagem, $palavra) !== false) {
                return $automacao;
            }
        }
    }

    return null;
}
