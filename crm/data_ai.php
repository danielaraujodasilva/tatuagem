<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/system_settings.php';

function data_ai_preview($value, int $limit = 700): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $limit);
    }

    return substr($text, 0, $limit);
}

function data_ai_clean_answer(string $text): string
{
    $text = preg_replace('/<think>.*?<\/think>/is', '', $text) ?? $text;
    $text = preg_replace('/Thinking\.\.\..*?done thinking\.\s*/is', '', $text) ?? $text;
    $text = preg_replace('/^\s*(Okay|Alright),?\s+/i', '', $text) ?? $text;
    $text = preg_replace('/^\s*(IA|Assistente|Bot)\s*:\s*/i', '', $text) ?? $text;
    return trim($text);
}

function data_ai_extract_thinking(string $text): string
{
    $parts = [];
    if (preg_match_all('/<think>(.*?)<\/think>/is', $text, $matches)) {
        foreach ($matches[1] as $match) {
            $clean = trim((string)$match);
            if ($clean !== '') {
                $parts[] = $clean;
            }
        }
    }

    if (!$parts && preg_match('/Thinking\.\.\.(.*?)(?:\.\.\.done thinking\.|$)/is', $text, $match)) {
        $clean = trim((string)($match[1] ?? ''));
        if ($clean !== '') {
            $parts[] = $clean;
        }
    }

    return trim(implode("\n\n", $parts));
}

function data_ai_parse_json_response(string $text): ?array
{
    $clean = data_ai_clean_answer($text);
    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean) ?? $clean;
    $first = strpos($clean, '{');
    $last = strrpos($clean, '}');
    if ($first === false || $last === false || $last <= $first) {
        return null;
    }

    $json = substr($clean, $first, $last - $first + 1);
    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : null;
}

function data_ai_error(string $type, string $stage, string $message, array $details = []): array
{
    return [
        'ok' => false,
        'error' => $message,
        'error_type' => $type,
        'stage' => $stage,
        'details' => $details,
        'queries' => data_ai_public_queries(),
    ];
}

function data_ai_context_notes(array $context): array
{
    $notes = ['Modo somente leitura: o assistente recebeu apenas consultas controladas e nao pode alterar dados.'];
    $crm = $context['fontes']['crm'] ?? [];
    $ficha = $context['fontes']['ficha_agenda'] ?? [];
    $financeiro = $context['fontes']['financeiro'] ?? [];

    if (!empty($crm['ok'])) {
        $leads = $crm['data']['leads']['resumo'] ?? [];
        $whatsapp = $crm['data']['whatsapp']['resumo'] ?? [];
        $notes[] = 'CRM lido: ' . (int)($leads['total'] ?? 0) . ' leads e ' . (int)($whatsapp['total'] ?? 0) . ' conversas de WhatsApp.';
    }
    if (!empty($ficha['ok'])) {
        $clientes = $ficha['data']['clientes']['resumo'] ?? [];
        $tatuagens = $ficha['data']['tatuagens']['resumo'] ?? [];
        $notes[] = 'Ficha/agenda lida: ' . (int)($clientes['total'] ?? 0) . ' clientes e ' . (int)($tatuagens['total'] ?? 0) . ' tatuagens/agendamentos.';
    }
    if (!empty($financeiro['ok'])) {
        $resumo = $financeiro['data']['resumo'] ?? [];
        $notes[] = 'Financeiro lido: ' . (int)($resumo['total_despesas_cadastradas'] ?? 0) . ' despesas cadastradas.';
    }

    return array_slice($notes, 0, 5);
}

function data_ai_query_log(?array $set = null): array
{
    static $queries = [];

    if ($set !== null) {
        $queries = $set;
    }

    return $queries;
}

function data_ai_query_value($value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }

    return "'" . str_replace("'", "''", (string)$value) . "'";
}

function data_ai_interpolate_query(string $sql, array $params): string
{
    foreach ($params as $param) {
        $pos = strpos($sql, '?');
        if ($pos === false) {
            break;
        }
        $sql = substr($sql, 0, $pos) . data_ai_query_value($param) . substr($sql, $pos + 1);
    }

    return $sql;
}

function data_ai_record_query(string $source, string $sql, array $params = []): void
{
    $sql = $params ? data_ai_interpolate_query($sql, $params) : $sql;
    $normalized = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
    if ($normalized === '') {
        return;
    }

    $queries = data_ai_query_log();
    $key = $source . '|' . $normalized;
    foreach ($queries as $query) {
        if (($query['key'] ?? '') === $key) {
            return;
        }
    }

    $queries[] = [
        'key' => $key,
        'fonte' => $source,
        'sql' => $normalized,
        'params' => $params,
    ];
    data_ai_query_log($queries);
}

function data_ai_public_queries(): array
{
    $queries = data_ai_query_log();
    return array_map(static function (array $query): array {
        return [
            'fonte' => (string)($query['fonte'] ?? ''),
            'sql' => (string)($query['sql'] ?? ''),
        ];
    }, array_slice($queries, 0, 40));
}

function data_ai_crm_pdo(): PDO
{
    global $conn;

    $previous = $conn ?? null;
    require __DIR__ . '/config.php';
    $pdo = $conn ?? null;

    if ($previous !== null) {
        $conn = $previous;
    } else {
        unset($GLOBALS['conn']);
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Conexao do CRM indisponivel.');
    }

    return $pdo;
}

function data_ai_ficha_mysqli(): mysqli
{
    global $conn;

    $previous = $conn ?? null;
    require __DIR__ . '/../ficha/config/conexao.php';
    $mysqli = $conn ?? null;

    if ($previous !== null) {
        $conn = $previous;
    } else {
        unset($GLOBALS['conn']);
    }

    if (!$mysqli instanceof mysqli) {
        throw new RuntimeException('Conexao da ficha indisponivel.');
    }

    return $mysqli;
}

function data_ai_pdo_rows(PDO $pdo, string $sql): array
{
    data_ai_record_query('CRM', $sql);
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function data_ai_pdo_value(PDO $pdo, string $sql, string $key = 'total')
{
    data_ai_record_query('CRM', $sql);
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? ($row[$key] ?? null) : null;
}

function data_ai_mysqli_rows(mysqli $conn, string $sql): array
{
    data_ai_record_query('Ficha/Agenda', $sql);
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function data_ai_mysqli_row(mysqli $conn, string $sql): array
{
    data_ai_record_query('Ficha/Agenda', $sql);
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return is_array($row) ? $row : [];
}

function data_ai_pdo_table_exists(PDO $pdo, string $table): bool
{
    data_ai_record_query('CRM', 'SHOW TABLES LIKE ?', [$table]);
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function data_ai_mysqli_table_exists(mysqli $conn, string $table): bool
{
    data_ai_record_query('Ficha/Agenda', 'SHOW TABLES LIKE ?', [$table]);
    $stmt = $conn->prepare('SHOW TABLES LIKE ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    return $exists;
}

function data_ai_safe_section(callable $callback): array
{
    try {
        return ['ok' => true, 'data' => $callback()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function data_ai_summarize_messages(array $messages, int $limit = 8): array
{
    $items = array_slice($messages, -$limit);

    return array_values(array_filter(array_map(static function (array $message): array {
        $text = trim((string)($message['texto'] ?? ''));
        $transcription = trim((string)($message['transcricao'] ?? ''));
        if ($text === '' && $transcription !== '') {
            $text = 'Transcricao de audio: ' . $transcription;
        }
        if ($text === '' && !empty($message['media_url'])) {
            $text = '[' . ((string)($message['tipo'] ?? 'arquivo')) . ' anexado]';
        }

        $fromMe = !empty($message['from_me']) || !empty($message['fromMe']);

        return [
            'autor' => $fromMe ? 'Atendimento' : 'Cliente',
            'data' => (string)($message['data'] ?? ''),
            'tipo' => (string)($message['tipo'] ?? 'texto'),
            'texto' => data_ai_preview($text, 500),
        ];
    }, $items), static fn(array $item): bool => $item['texto'] !== ''));
}

function data_ai_read_whatsapp_json(): array
{
    $paths = [
        __DIR__ . '/data/clientes_runtime.json',
        __DIR__ . '/data/clientes.json',
    ];

    foreach ($paths as $path) {
        if (!is_file($path) || filesize($path) <= 2) {
            continue;
        }

        $data = json_decode((string)file_get_contents($path), true);
        if (is_array($data)) {
            return array_values(array_filter($data, 'is_array'));
        }
    }

    return [];
}

function data_ai_whatsapp_context(PDO $pdo): array
{
    if (data_ai_pdo_table_exists($pdo, 'crm_whatsapp_clientes') && data_ai_pdo_table_exists($pdo, 'crm_whatsapp_mensagens')) {
        $summary = data_ai_pdo_rows($pdo, "
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(valor), 0) AS valor_total,
                SUM(CASE WHEN modo_atendimento = 'bot' OR atendente = 'bot' THEN 1 ELSE 0 END) AS em_ia,
                SUM(CASE WHEN modo_atendimento = 'humano' THEN 1 ELSE 0 END) AS em_atendente
            FROM crm_whatsapp_clientes
        ");
        $byStatus = data_ai_pdo_rows($pdo, "
            SELECT status, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor
            FROM crm_whatsapp_clientes
            GROUP BY status
            ORDER BY qtd DESC
            LIMIT 20
        ");
        $clients = data_ai_pdo_rows($pdo, "
            SELECT id, numero, nome, status, etapa, atendente, modo_atendimento, interesse, valor, origem, data_ultimo_contato, updated_at
            FROM crm_whatsapp_clientes
            ORDER BY COALESCE(data_ultimo_contato, updated_at) DESC
            LIMIT 35
        ");
        $ids = array_map(static fn(array $row): string => (string)$row['id'], $clients);
        $messagesByClient = [];

        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            data_ai_record_query('CRM', "
                SELECT cliente_id, texto, data, from_me, tipo, media_url, transcricao
                FROM crm_whatsapp_mensagens
                WHERE cliente_id IN ($placeholders)
                ORDER BY data DESC, id DESC
            ", $ids);
            $stmt = $pdo->prepare("
                SELECT cliente_id, texto, data, from_me, tipo, media_url, transcricao
                FROM crm_whatsapp_mensagens
                WHERE cliente_id IN ($placeholders)
                ORDER BY data DESC, id DESC
            ");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $message) {
                $clientId = (string)$message['cliente_id'];
                $messagesByClient[$clientId] ??= [];
                if (count($messagesByClient[$clientId]) < 8) {
                    $messagesByClient[$clientId][] = $message;
                }
            }
        }

        foreach ($clients as &$client) {
            $messages = array_reverse($messagesByClient[(string)$client['id']] ?? []);
            $client['mensagens_recentes'] = data_ai_summarize_messages($messages);
            $client['interesse'] = data_ai_preview($client['interesse'] ?? '', 500);
        }
        unset($client);

        return [
            'fonte' => 'crm_whatsapp_sql',
            'resumo' => $summary[0] ?? [],
            'por_status' => $byStatus,
            'conversas_recentes' => $clients,
        ];
    }

    $clients = data_ai_read_whatsapp_json();
    $summary = [
        'total' => count($clients),
        'valor_total' => 0,
        'em_ia' => 0,
        'em_atendente' => 0,
    ];
    $byStatus = [];

    foreach ($clients as &$client) {
        $status = (string)($client['status'] ?? 'sem_status');
        $byStatus[$status] ??= ['status' => $status, 'qtd' => 0, 'valor' => 0];
        $value = (float)($client['valor'] ?? 0);
        $byStatus[$status]['qtd']++;
        $byStatus[$status]['valor'] += $value;
        $summary['valor_total'] += $value;
        $mode = strtolower((string)($client['modo_atendimento'] ?? $client['atendente'] ?? ''));
        if ($mode === 'humano') {
            $summary['em_atendente']++;
        } else {
            $summary['em_ia']++;
        }
        $client['mensagens_recentes'] = data_ai_summarize_messages($client['mensagens'] ?? []);
        $client['interesse'] = data_ai_preview($client['interesse'] ?? '', 500);
        unset($client['mensagens']);
    }
    unset($client);

    usort($clients, static fn(array $a, array $b): int => strcmp((string)($b['data_ultimo_contato'] ?? ''), (string)($a['data_ultimo_contato'] ?? '')));

    return [
        'fonte' => 'crm_whatsapp_json',
        'resumo' => $summary,
        'por_status' => array_values($byStatus),
        'conversas_recentes' => array_slice($clients, 0, 35),
    ];
}

function data_ai_crm_context(): array
{
    return data_ai_safe_section(static function (): array {
        $pdo = data_ai_crm_pdo();
        $data = [
            'pipelines' => data_ai_pdo_table_exists($pdo, 'pipelines') ? data_ai_pdo_rows($pdo, 'SELECT id, nome, ordem, cor FROM pipelines ORDER BY ordem, id LIMIT 30') : [],
            'leads' => ['disponivel' => false],
            'whatsapp' => data_ai_whatsapp_context($pdo),
        ];

        if (data_ai_pdo_table_exists($pdo, 'leads')) {
            $data['leads'] = [
                'disponivel' => true,
                'resumo' => data_ai_pdo_rows($pdo, 'SELECT COUNT(*) AS total, COALESCE(SUM(valor), 0) AS valor_total FROM leads')[0] ?? [],
                'por_status' => data_ai_pdo_rows($pdo, 'SELECT status, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor FROM leads GROUP BY status ORDER BY qtd DESC LIMIT 20'),
                'por_origem' => data_ai_pdo_rows($pdo, 'SELECT origem, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor FROM leads GROUP BY origem ORDER BY qtd DESC LIMIT 20'),
                'por_etapa' => data_ai_pdo_rows($pdo, 'SELECT etapa_funil AS etapa, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor FROM leads GROUP BY etapa_funil ORDER BY qtd DESC LIMIT 20'),
                'recentes' => data_ai_pdo_rows($pdo, 'SELECT id, nome, telefone, interesse, valor, origem, status, etapa_funil AS etapa, data_ultimo_contato, created_at FROM leads ORDER BY created_at DESC, id DESC LIMIT 40'),
            ];
        }

        return $data;
    });
}

function data_ai_ficha_context(): array
{
    return data_ai_safe_section(static function (): array {
        $conn = data_ai_ficha_mysqli();
        $data = [
            'clientes' => ['disponivel' => false],
            'tatuagens' => ['disponivel' => false],
        ];

        if (data_ai_mysqli_table_exists($conn, 'clientes')) {
            $recentClients = data_ai_mysqli_rows($conn, "
                SELECT id, nome, email, telefone, data_nascimento, genero, profissao, endereco, estilo_tatuagem, instagram_cliente, vai_tatuar, created_at
                FROM clientes
                ORDER BY id DESC
                LIMIT 40
            ");
            foreach ($recentClients as &$client) {
                $client['endereco'] = data_ai_preview($client['endereco'] ?? '', 220);
            }
            unset($client);

            $data['clientes'] = [
                'disponivel' => true,
                'resumo' => data_ai_mysqli_row($conn, 'SELECT COUNT(*) AS total FROM clientes'),
                'recentes' => $recentClients,
            ];
        }

        if (data_ai_mysqli_table_exists($conn, 'tatuagens')) {
            $data['tatuagens'] = [
                'disponivel' => true,
                'resumo' => data_ai_mysqli_row($conn, "
                    SELECT
                        COUNT(*) AS total,
                        COALESCE(SUM(valor), 0) AS valor_total,
                        SUM(CASE WHEN data_tatuagem >= CURDATE() AND status <> 'cancelado' THEN 1 ELSE 0 END) AS futuras,
                        COALESCE(SUM(CASE WHEN DATE_FORMAT(data_tatuagem, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') AND status <> 'cancelado' THEN valor ELSE 0 END), 0) AS valor_mes_atual
                    FROM tatuagens
                "),
                'por_status' => data_ai_mysqli_rows($conn, "SELECT status, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor FROM tatuagens GROUP BY status ORDER BY qtd DESC"),
                'por_mes' => data_ai_mysqli_rows($conn, "
                    SELECT DATE_FORMAT(data_tatuagem, '%Y-%m') AS mes, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor
                    FROM tatuagens
                    WHERE data_tatuagem IS NOT NULL
                    GROUP BY DATE_FORMAT(data_tatuagem, '%Y-%m')
                    ORDER BY mes DESC
                    LIMIT 18
                "),
                'proximas' => data_ai_mysqli_rows($conn, "
                    SELECT t.id, t.cliente_id, c.nome AS cliente_nome, c.telefone AS cliente_telefone, t.descricao, t.valor, t.data_tatuagem, t.hora_inicio, t.hora_fim, t.status, t.pomadas_anestesicas, t.observacoes
                    FROM tatuagens t
                    LEFT JOIN clientes c ON c.id = t.cliente_id
                    WHERE t.data_tatuagem >= CURDATE()
                    ORDER BY t.data_tatuagem ASC, t.hora_inicio ASC, t.id ASC
                    LIMIT 50
                "),
                'recentes' => data_ai_mysqli_rows($conn, "
                    SELECT t.id, t.cliente_id, c.nome AS cliente_nome, c.telefone AS cliente_telefone, t.descricao, t.valor, t.data_tatuagem, t.hora_inicio, t.hora_fim, t.status, t.pomadas_anestesicas, t.observacoes
                    FROM tatuagens t
                    LEFT JOIN clientes c ON c.id = t.cliente_id
                    ORDER BY t.id DESC
                    LIMIT 40
                "),
            ];
        }

        return $data;
    });
}

function data_ai_finance_context(): array
{
    return data_ai_safe_section(static function (): array {
        $path = __DIR__ . '/data/finance_expenses.json';
        data_ai_record_query('Financeiro', 'READ JSON crm/data/finance_expenses.json');
        $expenses = [];
        if (is_file($path) && filesize($path) > 2) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded)) {
                $expenses = array_values(array_filter($decoded, 'is_array'));
            }
        }

        $total = 0.0;
        $currentMonth = date('Y-m');
        $monthTotal = 0.0;
        $byCategory = [];

        foreach ($expenses as &$expense) {
            $value = (float)($expense['valor'] ?? 0);
            $date = (string)($expense['data'] ?? '');
            $category = trim((string)($expense['categoria'] ?? 'Geral')) ?: 'Geral';

            $total += $value;
            if (substr($date, 0, 7) === $currentMonth) {
                $monthTotal += $value;
            }
            $byCategory[$category] ??= ['categoria' => $category, 'qtd' => 0, 'valor' => 0.0];
            $byCategory[$category]['qtd']++;
            $byCategory[$category]['valor'] += $value;

            $expense['descricao'] = data_ai_preview($expense['descricao'] ?? '', 260);
        }
        unset($expense);

        usort($expenses, static fn(array $a, array $b): int => strcmp((string)($b['data'] ?? ''), (string)($a['data'] ?? '')));

        return [
            'resumo' => [
                'total_despesas_cadastradas' => count($expenses),
                'valor_total_despesas' => $total,
                'valor_despesas_mes_atual' => $monthTotal,
            ],
            'por_categoria' => array_values($byCategory),
            'recentes' => array_slice($expenses, 0, 35),
        ];
    });
}

function data_ai_build_context(string $question): array
{
    return [
        'gerado_em' => date('Y-m-d H:i:s'),
        'pergunta' => data_ai_preview($question, 1200),
        'seguranca' => [
            'modo' => 'somente_leitura',
            'observacao' => 'A IA nao recebe permissao para escrever no banco. O sistema envia apenas resultados de consultas SELECT fixas e leitura de JSON.',
        ],
        'fontes' => [
            'crm' => data_ai_crm_context(),
            'ficha_agenda' => data_ai_ficha_context(),
            'financeiro' => data_ai_finance_context(),
        ],
    ];
}

function data_ai_ask(string $question): array
{
    data_ai_query_log([]);
    $question = trim($question);
    if ($question === '') {
        return data_ai_error('validacao', 'validacao', 'Digite uma pergunta para o assistente.');
    }

    if (!function_exists('curl_init')) {
        return data_ai_error('php_sem_curl', 'ambiente_php', 'A extensao cURL do PHP nao esta disponivel.');
    }

    $startedAt = microtime(true);
    $settings = system_settings_load();
    $ollamaUrl = rtrim(trim((string)($settings['ollama_url'] ?? 'http://localhost:11434')), '/') ?: 'http://localhost:11434';
    $model = trim((string)($settings['data_ai_model'] ?? 'qwen3:14b')) ?: 'qwen3:14b';
    $timeout = max(30, min(420, (int)($settings['data_ai_timeout_seconds'] ?? 240)));
    $numPredict = max(120, min(6000, (int)($settings['data_ai_num_predict'] ?? 2400)));
    if (function_exists('set_time_limit')) {
        @set_time_limit($timeout + 45);
    }
    $contextStartedAt = microtime(true);
    $context = data_ai_build_context($question);
    $contextSeconds = round(microtime(true) - $contextStartedAt, 3);

    $system = "Voce e um analista interno do estudio de tatuagem. Responda em portugues do Brasil, com objetividade e clareza. Use somente os dados fornecidos no JSON de contexto. Nao invente numeros, datas, nomes ou conclusoes que nao estejam apoiadas nos dados. Se a pergunta exigir algo fora do contexto, diga exatamente o que faltou. Voce nao executa SQL e nao altera dados; o sistema ja enviou apenas consultas de leitura.";
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    $contextJson = json_encode($context, $jsonFlags);
    if ($contextJson === false) {
        return data_ai_error('contexto_json_invalido', 'montagem_contexto', 'Nao foi possivel converter o contexto dos dados para JSON.', [
            'json_error' => json_last_error_msg(),
        ]);
    }

    $user = "Pergunta do gestor:\n" . $question . "\n\nDados disponiveis em JSON:\n" . $contextJson;

    $payload = [
        'model' => $model,
        'stream' => false,
        'think' => true,
        'messages' => [
            ['role' => 'system', 'content' => $system . "\n\nRetorne exclusivamente um JSON valido neste formato: {\"resposta\":\"texto final para o gestor\",\"transparencia\":[\"resumo curto da fonte/evidencia usada\",\"outro resumo curto\"]}. O campo transparencia deve ter no maximo 5 itens, explicando quais fontes e evidencias voce usou, sem raciocinio interno passo a passo, sem tags <think>, sem bastidores tecnicos e sem JSON bruto dentro dos textos."],
            ['role' => 'user', 'content' => $user],
        ],
        'options' => [
            'temperature' => 0.2,
            'num_predict' => $numPredict,
            'num_ctx' => 12000,
        ],
    ];

    $ch = curl_init($ollamaUrl . '/api/chat');
    curl_setopt($ch, CURLOPT_POST, true);
    $payloadJson = json_encode($payload, $jsonFlags);
    if ($payloadJson === false) {
        return data_ai_error('payload_json_invalido', 'preparacao_ollama', 'Nao foi possivel montar o payload para o Ollama.', [
            'json_error' => json_last_error_msg(),
        ]);
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $json = json_decode((string)$raw, true);
    if ($raw === false || $curlError !== '') {
        $isTimeout = $curlErrno === 28 || stripos($curlError, 'timed out') !== false;
        return data_ai_error($isTimeout ? 'ollama_timeout' : 'ollama_conexao', 'consulta_ollama', $isTimeout ? 'Timeout aguardando resposta do Ollama.' : 'Erro de conexao com Ollama.', [
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'timeout_configurado_segundos' => $timeout,
            'tempo_total_curl_segundos' => $totalTime,
            'url' => $ollamaUrl . '/api/chat',
            'model' => $model,
            'num_predict' => $numPredict,
            'contexto_segundos' => $contextSeconds,
        ]);
    }
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($json)) {
        $message = $json['error'] ?? ('Ollama retornou HTTP ' . $httpCode);
        return data_ai_error(!is_array($json) ? 'ollama_json_invalido' : 'ollama_http', 'resposta_ollama', is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE), [
            'http_code' => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'tempo_total_curl_segundos' => $totalTime,
            'raw_preview' => data_ai_preview((string)$raw, 2500),
            'url' => $ollamaUrl . '/api/chat',
            'model' => $model,
            'done_reason' => $json['done_reason'] ?? null,
            'num_predict' => $numPredict,
        ]);
    }

    $rawAnswer = (string)($json['message']['content'] ?? '');
    $thinking = (string)($json['message']['thinking'] ?? '');
    if ($thinking === '') {
        $thinking = data_ai_extract_thinking($rawAnswer);
    }
    $structured = data_ai_parse_json_response($rawAnswer);
    $answer = data_ai_clean_answer((string)($structured['resposta'] ?? $rawAnswer));
    $transparency = $structured['transparencia'] ?? [];
    if (!is_array($transparency)) {
        $transparency = [];
    }
    $transparency = array_values(array_filter(array_map(static fn($item): string => data_ai_preview($item, 260), $transparency)));
    if (!$transparency) {
        $transparency = data_ai_context_notes($context);
    }
    $queries = data_ai_public_queries();
    if ($queries) {
        $transparency[] = 'Consultas de leitura registradas para estudo: ' . count($queries) . '.';
    }

    if ($answer === '') {
        return data_ai_error('ollama_resposta_vazia', 'interpretacao_resposta', 'Ollama respondeu, mas nao retornou texto aproveitavel.', [
            'http_code' => $httpCode,
            'tempo_total_curl_segundos' => $totalTime,
            'done_reason' => $json['done_reason'] ?? null,
            'eval_count' => $json['eval_count'] ?? null,
            'prompt_eval_count' => $json['prompt_eval_count'] ?? null,
            'num_predict' => $numPredict,
            'thinking_chars' => strlen($thinking),
            'thinking_preview' => data_ai_preview($thinking, 2500),
            'raw_preview' => data_ai_preview($rawAnswer, 2500),
            'model' => $model,
        ]);
    }

    return [
        'ok' => true,
        'answer' => $answer,
        'transparency' => $transparency,
        'queries' => $queries,
        'thinking' => $thinking,
        'raw_model_output' => $rawAnswer,
        'diagnostic' => [
            'stage' => 'concluido',
            'http_code' => $httpCode,
            'ollama_total_seconds' => $totalTime,
            'context_seconds' => $contextSeconds,
            'total_seconds' => round(microtime(true) - $startedAt, 3),
            'timeout_seconds' => $timeout,
            'num_predict' => $numPredict,
            'model' => $model,
            'url' => $ollamaUrl . '/api/chat',
            'done_reason' => $json['done_reason'] ?? null,
            'eval_count' => $json['eval_count'] ?? null,
            'prompt_eval_count' => $json['prompt_eval_count'] ?? null,
            'structured_json' => $structured !== null,
            'raw_chars' => strlen($rawAnswer),
            'thinking_chars' => strlen($thinking),
        ],
        'model' => $model,
        'read_only' => true,
        'generated_at' => $context['gerado_em'],
    ];
}
