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

function data_ai_jobs_dir(): string
{
    $dir = __DIR__ . '/data/ai_jobs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function data_ai_job_path(string $jobId): string
{
    if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
        throw new InvalidArgumentException('Job invalido.');
    }

    return data_ai_jobs_dir() . '/' . $jobId . '.json';
}

function data_ai_job_write(string $jobId, array $data): void
{
    $path = data_ai_job_path($jobId);
    $data['updated_at'] = date('Y-m-d H:i:s');
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    copy($tmp, $path);
    unlink($tmp);
}

function data_ai_job_read(string $jobId): ?array
{
    $path = data_ai_job_path($jobId);
    if (!is_file($path)) {
        return null;
    }

    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function data_ai_job_update(string $jobId, array $patch): void
{
    $current = data_ai_job_read($jobId) ?? [];
    data_ai_job_write($jobId, array_merge($current, $patch));
}

function data_ai_php_binary(): string
{
    $suffix = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'php.exe' : 'php';
    $candidates = [
        PHP_BINDIR . DIRECTORY_SEPARATOR . $suffix,
        dirname(PHP_BINDIR) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $suffix,
        'C:\\xampp\\php\\php.exe',
    ];
    if (stripos(basename(PHP_BINARY), 'php') === 0) {
        $candidates[] = PHP_BINARY;
    }
    $candidates[] = 'php';

    foreach ($candidates as $candidate) {
        if ($candidate === 'php' || is_file($candidate)) {
            return $candidate;
        }
    }

    return 'php';
}

function data_ai_start_worker(string $jobId): array
{
    $php = data_ai_php_binary();
    $script = __DIR__ . '/assistente_dados_worker.php';

    if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
        $cmd = 'cmd /C start "" /B ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId);
        $handle = @popen($cmd, 'r');
        if (is_resource($handle)) {
            pclose($handle);
            return ['ok' => true, 'cmd' => $cmd, 'php' => $php];
        }

        return ['ok' => false, 'error' => 'Nao foi possivel iniciar o worker no Windows.', 'cmd' => $cmd, 'php' => $php];
    }

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId) . ' > /dev/null 2>&1 &';
    @exec($cmd, $output, $exitCode);

    return ['ok' => $exitCode === 0, 'cmd' => $cmd, 'php' => $php, 'exit_code' => $exitCode];
}

function data_ai_create_job(string $question, array $user = []): array
{
    $question = trim($question);
    if ($question === '') {
        return data_ai_error('validacao', 'validacao', 'Digite uma pergunta para o assistente.');
    }

    $jobId = bin2hex(random_bytes(16));
    $job = [
        'ok' => true,
        'job_id' => $jobId,
        'status' => 'queued',
        'stage' => 'queued',
        'stage_label' => 'Na fila para processamento.',
        'progress' => 5,
        'question' => $question,
        'user_id' => (int)($user['id'] ?? 0),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    data_ai_job_write($jobId, $job);

    $worker = data_ai_start_worker($jobId);
    if (empty($worker['ok'])) {
        data_ai_job_update($jobId, [
            'ok' => false,
            'status' => 'error',
            'stage' => 'worker_start',
            'stage_label' => 'Falha ao iniciar o processamento em segundo plano.',
            'progress' => 100,
            'error' => $worker['error'] ?? 'Nao foi possivel iniciar worker.',
            'error_type' => 'worker_start_failed',
            'details' => $worker,
        ]);
    } else {
        data_ai_job_update($jobId, [
            'worker' => [
                'php' => $worker['php'] ?? '',
            ],
        ]);
    }

    return data_ai_job_read($jobId) ?? $job;
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
    data_ai_record_query('Ficha/Agenda', 'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function data_ai_safe_section(callable $callback): array
{
    try {
        return ['ok' => true, 'data' => $callback()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function data_ai_question_days(string $question): int
{
    $question = strtolower($question);
    if (preg_match('/ultim[oa]s?\s+(\d{1,3})\s+dias/u', $question, $match)) {
        return max(1, min(180, (int)$match[1]));
    }
    if (preg_match('/últim[oa]s?\s+(\d{1,3})\s+dias/u', $question, $match)) {
        return max(1, min(180, (int)$match[1]));
    }

    return 0;
}

function data_ai_summarize_messages(array $messages, int $limit = 3): array
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
            'texto' => data_ai_preview($text, 280),
        ];
    }, $items), static fn(array $item): bool => $item['texto'] !== ''));
}

function data_ai_question_focus(string $question): array
{
    $text = strtolower($question);
    $has = static function (array $terms) use ($text): bool {
        foreach ($terms as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }
        return false;
    };

    $crm = $has(['lead', 'leads', 'whatsapp', 'conversa', 'cliente', 'status', 'funil', 'origem', 'agend']);
    $agenda = $has(['agenda', 'agend', 'horario', 'horário', 'tatuagem', 'tatuagens', 'ficha', 'cliente']);
    $finance = $has(['finance', 'despesa', 'gasto', 'custo', 'valor', 'receita', 'lucro']);

    if (!$crm && !$agenda && !$finance) {
        return ['crm' => true, 'agenda' => true, 'finance' => true];
    }

    return [
        'crm' => $crm,
        'agenda' => $agenda,
        'finance' => $finance,
    ];
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
            LIMIT 12
        ");
        $ids = array_map(static fn(array $row): string => (string)$row['id'], $clients);
        $messagesByClient = [];

        if ($ids) {
            data_ai_record_query('CRM', "
                SELECT cliente_id, texto, data, from_me, tipo, media_url, transcricao
                FROM crm_whatsapp_mensagens
                WHERE cliente_id = ?
                ORDER BY data DESC, id DESC
                LIMIT 4
            ");
            $stmt = $pdo->prepare("
                SELECT cliente_id, texto, data, from_me, tipo, media_url, transcricao
                FROM crm_whatsapp_mensagens
                WHERE cliente_id = ?
                ORDER BY data DESC, id DESC
                LIMIT 4
            ");
            foreach ($ids as $id) {
                $stmt->execute([$id]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $message) {
                    $clientId = (string)$message['cliente_id'];
                    $messagesByClient[$clientId] ??= [];
                    $messagesByClient[$clientId][] = $message;
                }
            }
        }

        foreach ($clients as &$client) {
            $messages = array_reverse($messagesByClient[(string)$client['id']] ?? []);
            $client['mensagens_recentes'] = data_ai_summarize_messages($messages, 3);
            $client['interesse'] = data_ai_preview($client['interesse'] ?? '', 280);
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
        $client['mensagens_recentes'] = data_ai_summarize_messages($client['mensagens'] ?? [], 3);
        $client['interesse'] = data_ai_preview($client['interesse'] ?? '', 280);
        unset($client['mensagens']);
    }
    unset($client);

    usort($clients, static fn(array $a, array $b): int => strcmp((string)($b['data_ultimo_contato'] ?? ''), (string)($a['data_ultimo_contato'] ?? '')));

    return [
        'fonte' => 'crm_whatsapp_json',
        'resumo' => $summary,
        'por_status' => array_values($byStatus),
        'conversas_recentes' => array_slice($clients, 0, 12),
    ];
}

function data_ai_crm_context(string $question = ''): array
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
                'recentes' => data_ai_pdo_rows($pdo, 'SELECT id, nome, telefone, interesse, valor, origem, status, etapa_funil AS etapa, data_ultimo_contato, created_at FROM leads ORDER BY created_at DESC, id DESC LIMIT 12'),
            ];
            foreach ($data['leads']['recentes'] as &$lead) {
                $lead['interesse'] = data_ai_preview($lead['interesse'] ?? '', 240);
            }
            unset($lead);
        }

        return $data;
    });
}

function data_ai_ficha_context(string $question = ''): array
{
    return data_ai_safe_section(static function () use ($question): array {
        $conn = data_ai_ficha_mysqli();
        $requestedDays = data_ai_question_days($question);
        $data = [
            'clientes' => ['disponivel' => false],
            'tatuagens' => ['disponivel' => false],
            'periodo_perguntado' => [
                'ultimos_dias' => $requestedDays,
            ],
        ];

        if (data_ai_mysqli_table_exists($conn, 'clientes')) {
            $recentClients = data_ai_mysqli_rows($conn, "
                SELECT id, nome, email, telefone, data_nascimento, genero, profissao, endereco, estilo_tatuagem, instagram_cliente, vai_tatuar, created_at
                FROM clientes
                ORDER BY id DESC
                LIMIT 20
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
            $periodRows = [];
            if ($requestedDays > 0) {
                $periodRows = data_ai_mysqli_rows($conn, "
                    SELECT t.id, t.cliente_id, c.nome AS cliente_nome, c.telefone AS cliente_telefone, t.descricao, t.valor, t.data_tatuagem, t.hora_inicio, t.hora_fim, t.status, t.pomadas_anestesicas, t.observacoes
                    FROM tatuagens t
                    LEFT JOIN clientes c ON c.id = t.cliente_id
                    WHERE t.data_tatuagem BETWEEN DATE_SUB(CURDATE(), INTERVAL " . (int)$requestedDays . " DAY) AND CURDATE()
                    ORDER BY t.data_tatuagem DESC, t.hora_inicio DESC, t.id DESC
                    LIMIT 80
                ");
            }

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
                    LIMIT 30
                "),
                'recentes' => data_ai_mysqli_rows($conn, "
                    SELECT t.id, t.cliente_id, c.nome AS cliente_nome, c.telefone AS cliente_telefone, t.descricao, t.valor, t.data_tatuagem, t.hora_inicio, t.hora_fim, t.status, t.pomadas_anestesicas, t.observacoes
                    FROM tatuagens t
                    LEFT JOIN clientes c ON c.id = t.cliente_id
                    ORDER BY t.id DESC
                    LIMIT 25
                "),
                'periodo_perguntado' => $periodRows,
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

function data_ai_money_br($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function data_ai_text_contains_any(string $text, array $terms): bool
{
    $text = strtolower($text);
    foreach ($terms as $term) {
        if (str_contains($text, strtolower($term))) {
            return true;
        }
    }

    return false;
}

function data_ai_conversation_reason_labels(string $text, string $status, string $interest): array
{
    $combined = trim($text . ' ' . $status . ' ' . $interest);
    $labels = [];

    if (data_ai_text_contains_any($combined, ['valor', 'preco', 'preço', 'orcamento', 'orçamento', 'quanto', 'caro', 'barato'])) {
        $labels[] = 'preco_orcamento';
    }
    if (data_ai_text_contains_any($combined, ['agenda', 'agendar', 'horario', 'horário', 'dia', 'data', 'disponivel', 'disponível'])) {
        $labels[] = 'horario_agenda';
    }
    if (data_ai_text_contains_any($combined, ['pensar', 'decidir', 'ver aqui', 'vejo', 'depois', 'retorno', 'confirmo'])) {
        $labels[] = 'decisao_pendente';
    }
    if (data_ai_text_contains_any($combined, ['referencia', 'referência', 'foto', 'imagem', 'desenho', 'tamanho', 'local do corpo', 'ideia'])) {
        $labels[] = 'faltam_detalhes';
    }
    if (data_ai_text_contains_any($combined, ['sinal', 'pix', 'entrada', 'pagamento', 'pagar'])) {
        $labels[] = 'pagamento_sinal';
    }

    return $labels ?: ['sem_motivo_claro'];
}

function data_ai_conversation_insights(array $context): array
{
    $conversations = $context['fontes']['crm']['data']['whatsapp']['conversas_recentes'] ?? [];
    $items = [];
    $reasonCounts = [];
    $scheduledTerms = ['agendado', 'confirmado', 'fechado', 'concluido', 'concluído'];
    $finalTerms = ['perdido', 'cancelado', 'fechado', 'concluido', 'concluído'];

    foreach ($conversations as $conversation) {
        if (!is_array($conversation)) {
            continue;
        }

        $status = strtolower((string)($conversation['status'] ?? ''));
        $stage = strtolower((string)($conversation['etapa'] ?? ''));
        $isScheduled = data_ai_text_contains_any($status . ' ' . $stage, $scheduledTerms);
        $isFinal = data_ai_text_contains_any($status . ' ' . $stage, $finalTerms);
        $messages = $conversation['mensagens_recentes'] ?? [];
        $messageText = '';
        $lastClient = '';
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $line = trim((string)($message['texto'] ?? ''));
            $messageText .= ' ' . $line;
            if (($message['autor'] ?? '') === 'Cliente' && $line !== '') {
                $lastClient = $line;
            }
        }

        $interest = (string)($conversation['interesse'] ?? '');
        $labels = data_ai_conversation_reason_labels($messageText, $status, $interest);
        foreach ($labels as $label) {
            $reasonCounts[$label] = ($reasonCounts[$label] ?? 0) + 1;
        }

        $score = count($labels) + ($isScheduled ? -3 : 2);
        if ($lastClient !== '') {
            $score++;
        }

        $items[] = [
            'nome' => (string)($conversation['nome'] ?? ''),
            'numero' => (string)($conversation['numero'] ?? ''),
            'status' => (string)($conversation['status'] ?? ''),
            'etapa' => (string)($conversation['etapa'] ?? ''),
            'interesse' => data_ai_preview($interest, 160),
            'valor' => (float)($conversation['valor'] ?? 0),
            'ultimo_cliente' => data_ai_preview($lastClient, 180),
            'motivos' => $labels,
            'score' => $score,
            'agendado' => $isScheduled,
            'acionavel' => !$isScheduled && !$isFinal,
        ];
    }

    usort($items, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']));
    arsort($reasonCounts);

    return [
        'motivos' => $reasonCounts,
        'conversas' => array_values(array_filter($items, static fn(array $item): bool => !empty($item['acionavel']))),
    ];
}

function data_ai_reason_label(string $key): string
{
    return [
        'preco_orcamento' => 'Preço/orçamento: cliente pergunta valor, compara preço ou ainda não fechou orçamento.',
        'horario_agenda' => 'Horário/agenda: conversa gira em torno de disponibilidade, data ou encaixe.',
        'decisao_pendente' => 'Decisão pendente: cliente diz que vai pensar, ver melhor ou retornar depois.',
        'faltam_detalhes' => 'Faltam detalhes: referência, imagem, tamanho, local do corpo ou ideia ainda incompleta.',
        'pagamento_sinal' => 'Pagamento/sinal: dúvida ou fricção sobre Pix, entrada ou reserva.',
        'sem_motivo_claro' => 'Sem motivo claro: pelas mensagens recentes não ficou explícito o bloqueio.',
    ][$key] ?? $key;
}

function data_ai_reason_short_label(string $key): string
{
    return [
        'preco_orcamento' => 'preco/orcamento',
        'horario_agenda' => 'horario/agenda',
        'decisao_pendente' => 'decisao pendente',
        'faltam_detalhes' => 'faltam detalhes',
        'pagamento_sinal' => 'pagamento/sinal',
        'sem_motivo_claro' => 'sem motivo claro',
    ][$key] ?? $key;
}

function data_ai_local_answer(string $question, array $context, array $problem = []): array
{
    $crm = $context['fontes']['crm']['data'] ?? [];
    $whatsapp = $crm['whatsapp'] ?? [];
    $leads = $crm['leads'] ?? [];
    $ficha = $context['fontes']['ficha_agenda']['data'] ?? [];
    $finance = $context['fontes']['financeiro']['data'] ?? [];
    $insights = data_ai_conversation_insights($context);
    $lines = [];

    if ($problem) {
        $lines[] = 'O modelo local nao terminou a tempo, entao montei uma leitura direta dos dados recentes para nao deixar a consulta sem resposta.';
        $lines[] = '';
    }

    $questionLower = strtolower($question);
    $asksConversation = str_contains($questionLower, 'conversa')
        || str_contains($questionLower, 'whatsapp')
        || str_contains($questionLower, 'argument')
        || (str_contains($questionLower, 'lead') && str_contains($questionLower, 'agend'));
    $asksAgendaRevenue = data_ai_text_contains_any($questionLower, ['faturamento', 'previsto', 'agendamentos por status']);
    $asksFutureAgenda = str_contains($questionLower, 'agenda futura') || str_contains($questionLower, 'futuras');
    $asksLeadSummary = data_ai_text_contains_any($questionLower, ['origem', 'etapa', 'valor potencial']);
    $asksFinance = data_ai_text_contains_any($questionLower, ['despesa', 'gasto', 'financeiro']);

    if ($asksConversation) {
        $lines[] = 'Pelo recorte das conversas recentes, os principais motivos que aparecem antes do agendamento sao:';
        if ($insights['motivos']) {
            $position = 1;
            foreach (array_slice($insights['motivos'], 0, 5, true) as $reason => $count) {
                $lines[] = $position . '. ' . data_ai_reason_label((string)$reason) . ' Ocorrencias no recorte: ' . (int)$count . '.';
                $position++;
            }
        } else {
            $lines[] = '- Nao encontrei motivos claros nas mensagens recentes enviadas ao assistente.';
        }

        $lines[] = '';
        $lines[] = 'Conversas que eu priorizaria para follow-up:';
        $candidates = array_slice($insights['conversas'], 0, 6);
        if (!$candidates) {
            $lines[] = '- Nenhuma conversa recente sem agendamento apareceu com dados suficientes no recorte.';
        }
        foreach ($candidates as $candidate) {
            $rawName = trim((string)$candidate['nome']);
            $name = $rawName !== '' && strtolower($rawName) !== 'cliente' ? $rawName : ($candidate['numero'] ?: 'Lead sem nome');
            $reasonText = implode(', ', array_map('data_ai_reason_short_label', $candidate['motivos']));
            $lastClient = $candidate['ultimo_cliente'] !== '' ? ' Ultima fala do cliente: "' . $candidate['ultimo_cliente'] . '".' : '';
            $lines[] = '- ' . $name . ': status "' . ($candidate['status'] ?: '-') . '", etapa "' . ($candidate['etapa'] ?: '-') . '". Motivo provavel: ' . $reasonText . '.' . $lastClient;
        }

        $lines[] = '';
        $lines[] = 'Sugestao pratica: separar esses leads em tres filas: falta de detalhes, negociacao de valor e tentativa de data. Assim o atendimento manda uma pergunta objetiva em vez de uma mensagem generica.';
    } elseif ($asksAgendaRevenue) {
        $tattoos = $ficha['tatuagens'] ?? [];
        $summary = $tattoos['resumo'] ?? [];
        $lines[] = 'Faturamento previsto dos agendamentos por status:';
        foreach (($tattoos['por_status'] ?? []) as $row) {
            $lines[] = '- ' . (($row['status'] ?? '') ?: 'sem_status') . ': ' . (int)($row['qtd'] ?? 0) . ' agendamentos, ' . data_ai_money_br($row['valor'] ?? 0) . '.';
        }
        if (!$tattoos || empty($tattoos['por_status'])) {
            $lines[] = '- Nao encontrei agendamentos agrupados por status na ficha/agenda.';
        }
        if ($summary) {
            $lines[] = '';
            $lines[] = 'Resumo: ' . (int)($summary['total'] ?? 0) . ' tatuagens cadastradas, ' . (int)($summary['futuras'] ?? 0) . ' futuras, ' . data_ai_money_br($summary['valor_mes_atual'] ?? 0) . ' previstos no mes atual.';
        }
    } elseif ($asksFutureAgenda) {
        $appointments = array_slice($ficha['tatuagens']['proximas'] ?? [], 0, 10);
        $lines[] = 'Clientes com agenda futura:';
        if (!$appointments) {
            $lines[] = '- Nao encontrei horarios futuros na ficha/agenda.';
        }
        foreach ($appointments as $appointment) {
            $client = (string)($appointment['cliente_nome'] ?? 'Cliente sem nome');
            $date = (string)($appointment['data_tatuagem'] ?? '');
            $time = substr((string)($appointment['hora_inicio'] ?? ''), 0, 5);
            $value = data_ai_money_br($appointment['valor'] ?? 0);
            $desc = data_ai_preview($appointment['descricao'] ?? '', 120);
            $lines[] = '- ' . $client . ': ' . $date . ($time ? ' as ' . $time : '') . ', ' . $value . ', status "' . (($appointment['status'] ?? '') ?: '-') . '"' . ($desc ? '. ' . $desc : '') . '.';
        }
    } elseif ($asksLeadSummary) {
        $lines[] = 'Resumo dos leads:';
        if (!empty($leads['resumo'])) {
            $lines[] = '- Total: ' . (int)($leads['resumo']['total'] ?? 0) . ' leads, valor potencial de ' . data_ai_money_br($leads['resumo']['valor_total'] ?? 0) . '.';
        }
        if (!empty($leads['por_origem'])) {
            $lines[] = '';
            $lines[] = 'Por origem:';
            foreach (array_slice($leads['por_origem'], 0, 8) as $row) {
                $lines[] = '- ' . (($row['origem'] ?? '') ?: 'sem_origem') . ': ' . (int)($row['qtd'] ?? 0) . ' leads, ' . data_ai_money_br($row['valor'] ?? 0) . '.';
            }
        }
        if (!empty($leads['por_etapa'])) {
            $lines[] = '';
            $lines[] = 'Por etapa:';
            foreach (array_slice($leads['por_etapa'], 0, 8) as $row) {
                $lines[] = '- ' . (($row['etapa'] ?? '') ?: 'sem_etapa') . ': ' . (int)($row['qtd'] ?? 0) . ' leads, ' . data_ai_money_br($row['valor'] ?? 0) . '.';
            }
        }
    } elseif ($asksFinance) {
        $financeSummary = $finance['resumo'] ?? [];
        $tattooSummary = $ficha['tatuagens']['resumo'] ?? [];
        $expensesMonth = (float)($financeSummary['valor_despesas_mes_atual'] ?? 0);
        $appointmentsMonth = (float)($tattooSummary['valor_mes_atual'] ?? 0);
        $lines[] = 'Comparativo financeiro do mes atual:';
        $lines[] = '- Despesas cadastradas no mes: ' . data_ai_money_br($expensesMonth) . '.';
        $lines[] = '- Valor de tatuagens no mes: ' . data_ai_money_br($appointmentsMonth) . '.';
        $lines[] = '- Diferenca simples: ' . data_ai_money_br($appointmentsMonth - $expensesMonth) . '.';
        if (!empty($finance['por_categoria'])) {
            $lines[] = '';
            $lines[] = 'Despesas por categoria:';
            foreach (array_slice($finance['por_categoria'], 0, 8) as $row) {
                $lines[] = '- ' . (($row['categoria'] ?? '') ?: 'Geral') . ': ' . (int)($row['qtd'] ?? 0) . ' registros, ' . data_ai_money_br($row['valor'] ?? 0) . '.';
            }
        }
    } else {
        $whatsappSummary = $whatsapp['resumo'] ?? [];
        $leadSummary = $leads['resumo'] ?? [];
        $tattooSummary = $ficha['tatuagens']['resumo'] ?? [];
        $financeSummary = $finance['resumo'] ?? [];

        $lines[] = 'Resumo rapido dos dados disponiveis:';
        if ($whatsappSummary) {
            $lines[] = '- WhatsApp: ' . (int)($whatsappSummary['total'] ?? 0) . ' conversas, valor total de ' . data_ai_money_br($whatsappSummary['valor_total'] ?? 0) . '.';
        }
        if ($leadSummary) {
            $lines[] = '- Leads: ' . (int)($leadSummary['total'] ?? 0) . ' registros, valor total de ' . data_ai_money_br($leadSummary['valor_total'] ?? 0) . '.';
        }
        if ($tattooSummary) {
            $lines[] = '- Agenda/ficha: ' . (int)($tattooSummary['total'] ?? 0) . ' tatuagens, ' . (int)($tattooSummary['futuras'] ?? 0) . ' futuras.';
        }
        if ($financeSummary) {
            $lines[] = '- Financeiro: ' . (int)($financeSummary['total_despesas_cadastradas'] ?? 0) . ' despesas cadastradas, total de ' . data_ai_money_br($financeSummary['valor_total_despesas'] ?? 0) . '.';
        }
    }

    return [
        'answer' => trim(implode("\n", $lines)),
        'transparency' => [
            'Resposta local montada com consultas somente leitura.',
            'Foram usados resumos de CRM, WhatsApp, agenda/ficha e financeiro conforme relevancia da pergunta.',
        ],
    ];
}

function data_ai_fallback_response(string $question, array $context, array $details, string $model): array
{
    $local = data_ai_local_answer($question, $context, $details);

    return [
        'ok' => true,
        'answer' => $local['answer'],
        'transparency' => $local['transparency'],
        'queries' => data_ai_public_queries(),
        'thinking' => '',
        'raw_model_output' => '',
        'diagnostic' => [
            'stage' => 'fallback_local',
            'model' => $model,
            'fallback_reason' => $details['error_type'] ?? 'ollama_indisponivel',
            'timeout_seconds' => $details['timeout_configurado_segundos'] ?? null,
        ],
        'model' => 'Resumo local',
        'read_only' => true,
        'generated_at' => $context['gerado_em'] ?? date('Y-m-d H:i:s'),
        'fallback' => true,
    ];
}

function data_ai_should_answer_locally(string $question): bool
{
    return data_ai_text_contains_any($question, [
        'argument',
        'conversa',
        'whatsapp',
        'lead',
        'leads',
        'agend',
        'status',
        'origem',
        'resumo',
        'despesa',
        'gasto',
        'financeiro',
        'cliente',
        'clientes',
    ]);
}

function data_ai_local_response(string $question, array $context): array
{
    $local = data_ai_local_answer($question, $context);

    return [
        'ok' => true,
        'answer' => $local['answer'],
        'transparency' => $local['transparency'],
        'queries' => data_ai_public_queries(),
        'thinking' => '',
        'raw_model_output' => '',
        'diagnostic' => [
            'stage' => 'analise_local',
            'context_sources' => array_keys($context['fontes'] ?? []),
        ],
        'model' => 'Analise local',
        'read_only' => true,
        'generated_at' => $context['gerado_em'] ?? date('Y-m-d H:i:s'),
        'local_analysis' => true,
    ];
}

function data_ai_llm_context(string $question, array $context): array
{
    $crm = $context['fontes']['crm']['data'] ?? [];
    $whatsapp = $crm['whatsapp'] ?? [];
    $leads = $crm['leads'] ?? [];
    $ficha = $context['fontes']['ficha_agenda']['data'] ?? [];
    $finance = $context['fontes']['financeiro']['data'] ?? [];
    $insights = data_ai_conversation_insights($context);

    $conversations = array_map(static function (array $item): array {
        return [
            'nome' => $item['nome'] ?: 'Lead sem nome',
            'numero' => $item['numero'],
            'status' => $item['status'],
            'etapa' => $item['etapa'],
            'interesse' => $item['interesse'],
            'valor' => $item['valor'],
            'ultimo_cliente' => $item['ultimo_cliente'],
            'motivos_detectados' => array_map('data_ai_reason_label', $item['motivos']),
        ];
    }, array_slice($insights['conversas'], 0, 8));

    $reasons = [];
    foreach (array_slice($insights['motivos'], 0, 6, true) as $reason => $count) {
        $reasons[] = [
            'motivo' => data_ai_reason_label((string)$reason),
            'ocorrencias_no_recorte' => (int)$count,
        ];
    }

    return [
        'gerado_em' => $context['gerado_em'] ?? date('Y-m-d H:i:s'),
        'pergunta' => data_ai_preview($question, 500),
        'instrucoes_de_uso' => 'Use este resumo executivo. Nao diga que faltou o JSON bruto se os campos abaixo forem suficientes.',
        'crm_whatsapp' => [
            'resumo' => $whatsapp['resumo'] ?? [],
            'por_status' => array_slice($whatsapp['por_status'] ?? [], 0, 8),
            'motivos_detectados' => $reasons,
            'conversas_para_follow_up' => $conversations,
        ],
        'leads' => [
            'resumo' => $leads['resumo'] ?? [],
            'por_status' => array_slice($leads['por_status'] ?? [], 0, 8),
            'por_origem' => array_slice($leads['por_origem'] ?? [], 0, 8),
            'por_etapa' => array_slice($leads['por_etapa'] ?? [], 0, 8),
            'recentes' => array_slice($leads['recentes'] ?? [], 0, 8),
        ],
        'agenda_ficha' => [
            'clientes_resumo' => $ficha['clientes']['resumo'] ?? [],
            'tatuagens_resumo' => $ficha['tatuagens']['resumo'] ?? [],
            'proximas' => array_slice($ficha['tatuagens']['proximas'] ?? [], 0, 8),
        ],
        'financeiro' => [
            'resumo' => $finance['resumo'] ?? [],
            'por_categoria' => array_slice($finance['por_categoria'] ?? [], 0, 8),
        ],
    ];
}

function data_ai_build_context(string $question): array
{
    $focus = data_ai_question_focus($question);

    return [
        'gerado_em' => date('Y-m-d H:i:s'),
        'pergunta' => data_ai_preview($question, 1200),
        'seguranca' => [
            'modo' => 'somente_leitura',
            'observacao' => 'A IA nao recebe permissao para escrever no banco. O sistema envia apenas resultados de consultas SELECT fixas e leitura de JSON.',
        ],
        'foco' => $focus,
        'fontes' => [
            'crm' => $focus['crm'] ? data_ai_crm_context($question) : ['ok' => true, 'omitido' => 'Fonte omitida para acelerar esta pergunta.'],
            'ficha_agenda' => $focus['agenda'] ? data_ai_ficha_context($question) : ['ok' => true, 'omitido' => 'Fonte omitida para acelerar esta pergunta.'],
            'financeiro' => $focus['finance'] ? data_ai_finance_context() : ['ok' => true, 'omitido' => 'Fonte omitida para acelerar esta pergunta.'],
        ],
    ];
}

function data_ai_ask(string $question, ?callable $progress = null): array
{
    data_ai_query_log([]);
    $emitProgress = static function (string $stage, string $label, int $percent, array $details = []) use ($progress): void {
        if ($progress) {
            $progress($stage, $label, $percent, $details);
        }
    };
    $emitProgress('validacao', 'Validando pergunta e configuracao.', 8);

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
    $numPredict = max(420, min(1800, (int)($settings['data_ai_num_predict'] ?? 700)));
    if (function_exists('set_time_limit')) {
        @set_time_limit($timeout + 45);
    }
    $emitProgress('montagem_contexto', 'Lendo CRM, ficha, agenda e financeiro.', 18, [
        'model' => $model,
        'timeout' => $timeout,
        'num_predict' => $numPredict,
    ]);
    $contextStartedAt = microtime(true);
    $context = data_ai_build_context($question);
    $contextSeconds = round(microtime(true) - $contextStartedAt, 3);
    $emitProgress('contexto_pronto', 'Contexto pronto. Escolhendo melhor forma de resposta.', 42, [
        'context_seconds' => $contextSeconds,
        'queries' => count(data_ai_public_queries()),
    ]);

    if (data_ai_should_answer_locally($question)) {
        $emitProgress('analise_local', 'Gerando resposta direta com os dados do CRM.', 92);
        $response = data_ai_local_response($question, $context);
        $response['diagnostic']['context_seconds'] = $contextSeconds;
        $response['diagnostic']['total_seconds'] = round(microtime(true) - $startedAt, 3);
        $emitProgress('concluido', 'Resposta pronta.', 100);
        return $response;
    }

    $system = "Voce e um analista interno do estudio de tatuagem. Responda em portugues do Brasil, com objetividade e clareza. Use somente os dados do JSON de contexto. Nao invente numeros, datas, nomes ou conclusoes sem apoio nos dados. Quando houver mensagens de WhatsApp, priorize evidencias da conversa e explique de forma pratica o que o gestor deve fazer. Voce nao executa SQL e nao altera dados.";
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    $llmContext = data_ai_llm_context($question, $context);
    $contextJson = json_encode($llmContext, $jsonFlags);
    if ($contextJson === false) {
        return data_ai_error('contexto_json_invalido', 'montagem_contexto', 'Nao foi possivel converter o contexto dos dados para JSON.', [
            'json_error' => json_last_error_msg(),
        ]);
    }

    $user = "Pergunta do gestor:\n" . $question . "\n\nDados disponiveis em JSON:\n" . $contextJson;

    $payload = [
        'model' => $model,
        'stream' => false,
        'think' => false,
        'format' => 'json',
        'messages' => [
            ['role' => 'system', 'content' => $system . "\n\nRetorne exclusivamente um JSON valido neste formato: {\"resposta\":\"texto final para o gestor\",\"transparencia\":[\"resumo curto da fonte/evidencia usada\",\"outro resumo curto\"]}. O campo transparencia deve ter no maximo 5 itens, explicando quais fontes e evidencias voce usou, sem raciocinio interno passo a passo, sem tags <think>, sem bastidores tecnicos e sem JSON bruto dentro dos textos."],
            ['role' => 'user', 'content' => $user],
        ],
        'options' => [
            'temperature' => 0.2,
            'num_predict' => $numPredict,
            'num_ctx' => 4096,
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

    $emitProgress('consulta_ollama', 'Consultando ' . $model . ' em segundo plano.', 55, [
        'url' => $ollamaUrl . '/api/chat',
    ]);
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
    $emitProgress('resposta_ollama', 'Ollama respondeu. Interpretando retorno.', 82, [
        'http_code' => $httpCode,
        'curl_errno' => $curlErrno,
        'seconds' => $totalTime,
    ]);

    $json = json_decode((string)$raw, true);
    if ($raw === false || $curlError !== '') {
        $isTimeout = $curlErrno === 28 || stripos($curlError, 'timed out') !== false;
        $details = [
            'error_type' => $isTimeout ? 'ollama_timeout' : 'ollama_conexao',
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'timeout_configurado_segundos' => $timeout,
            'tempo_total_curl_segundos' => $totalTime,
            'url' => $ollamaUrl . '/api/chat',
            'model' => $model,
            'num_predict' => $numPredict,
            'contexto_segundos' => $contextSeconds,
        ];

        return data_ai_fallback_response($question, $context, $details, $model);
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
        return data_ai_fallback_response($question, $context, [
            'error_type' => 'ollama_resposta_vazia',
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
        ], $model);
    }
    $emitProgress('concluido', 'Resposta pronta.', 100);

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
            'think_enabled' => false,
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

function data_ai_ollama_diagnostic(): array
{
    $settings = system_settings_load();
    $ollamaUrl = rtrim(trim((string)($settings['ollama_url'] ?? 'http://localhost:11434')), '/') ?: 'http://localhost:11434';
    $model = trim((string)($settings['data_ai_model'] ?? 'qwen3:14b')) ?: 'qwen3:14b';
    $timeout = max(30, min(420, (int)($settings['data_ai_timeout_seconds'] ?? 240)));
    $numPredict = max(120, min(6000, (int)($settings['data_ai_num_predict'] ?? 2400)));
    $result = [
        'ok' => true,
        'stage' => 'diagnostico_ollama',
        'ollama_url' => $ollamaUrl,
        'model' => $model,
        'timeout_seconds' => $timeout,
        'num_predict' => $numPredict,
        'php_curl' => function_exists('curl_init'),
    ];

    if (!function_exists('curl_init')) {
        $result['ok'] = false;
        $result['error'] = 'Extensao cURL do PHP nao esta disponivel.';
        return $result;
    }

    $tagsStarted = microtime(true);
    $ch = curl_init($ollamaUrl . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $tagsRaw = curl_exec($ch);
    $tagsError = curl_error($ch);
    $tagsErrno = curl_errno($ch);
    $tagsHttp = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $tagsJson = json_decode((string)$tagsRaw, true);
    $models = [];
    foreach (($tagsJson['models'] ?? []) as $item) {
        if (!empty($item['name'])) {
            $models[] = (string)$item['name'];
        }
    }

    $result['tags'] = [
        'ok' => $tagsRaw !== false && $tagsError === '' && $tagsHttp >= 200 && $tagsHttp < 300,
        'http_code' => $tagsHttp,
        'curl_errno' => $tagsErrno,
        'curl_error' => $tagsError,
        'seconds' => round(microtime(true) - $tagsStarted, 3),
        'models' => $models,
        'model_found' => in_array($model, $models, true),
        'raw_preview' => data_ai_preview((string)$tagsRaw, 1200),
    ];

    $chatStarted = microtime(true);
    $payload = [
        'model' => $model,
        'stream' => false,
        'think' => false,
        'messages' => [
            ['role' => 'user', 'content' => 'Responda apenas OK.'],
        ],
        'options' => [
            'num_predict' => 60,
            'num_ctx' => 4096,
        ],
    ];
    $ch = curl_init($ollamaUrl . '/api/chat');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, min($timeout, 180));
    $chatRaw = curl_exec($ch);
    $chatError = curl_error($ch);
    $chatErrno = curl_errno($ch);
    $chatHttp = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $chatCurlSeconds = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $chatJson = json_decode((string)$chatRaw, true);
    $content = (string)($chatJson['message']['content'] ?? '');
    $thinking = (string)($chatJson['message']['thinking'] ?? '');
    $result['chat'] = [
        'ok' => $chatRaw !== false && $chatError === '' && $chatHttp >= 200 && $chatHttp < 300 && is_array($chatJson),
        'http_code' => $chatHttp,
        'curl_errno' => $chatErrno,
        'curl_error' => $chatError,
        'seconds' => round(microtime(true) - $chatStarted, 3),
        'curl_seconds' => $chatCurlSeconds,
        'done' => $chatJson['done'] ?? null,
        'done_reason' => $chatJson['done_reason'] ?? null,
        'prompt_eval_count' => $chatJson['prompt_eval_count'] ?? null,
        'eval_count' => $chatJson['eval_count'] ?? null,
        'content' => $content,
        'content_chars' => strlen($content),
        'thinking_preview' => data_ai_preview($thinking, 1800),
        'thinking_chars' => strlen($thinking),
        'raw_preview' => data_ai_preview((string)$chatRaw, 2200),
    ];

    if (!$result['tags']['ok'] || !$result['chat']['ok'] || empty($result['tags']['model_found'])) {
        $result['ok'] = false;
    }

    return $result;
}
