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

function data_ai_normalize_text(string $text): string
{
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $text = strtr($text, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'ê' => 'e',
        'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u',
        'ç' => 'c',
    ]);

    return $text;
}

function data_ai_question_has_any(string $question, array $terms): bool
{
    $question = data_ai_normalize_text($question);
    foreach ($terms as $term) {
        if (strpos($question, data_ai_normalize_text((string)$term)) !== false) {
            return true;
        }
    }

    return false;
}

function data_ai_money_br($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function data_ai_int_value($value): int
{
    return (int)round((float)($value ?? 0));
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

function data_ai_shell_arg(string $value): string
{
    if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    return escapeshellarg($value);
}

function data_ai_worker_log_path(string $jobId): string
{
    if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
        throw new InvalidArgumentException('Job invalido.');
    }

    return data_ai_jobs_dir() . '/' . $jobId . '.worker.log';
}

function data_ai_start_worker(string $jobId): array
{
    $php = data_ai_php_binary();
    $script = __DIR__ . '/assistente_dados_worker.php';
    $log = data_ai_worker_log_path($jobId);
    @file_put_contents($log, '[' . date('Y-m-d H:i:s') . "] iniciando worker\n");

    if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
        $cmd = 'start "" /B '
            . data_ai_shell_arg($php) . ' '
            . data_ai_shell_arg($script) . ' '
            . data_ai_shell_arg($jobId) . ' >> '
            . data_ai_shell_arg($log) . ' 2>&1';
        $handle = @popen($cmd, 'r');
        if (is_resource($handle)) {
            $exitCode = pclose($handle);
            return ['ok' => $exitCode === 0 || $exitCode === -1, 'cmd' => $cmd, 'php' => $php, 'exit_code' => $exitCode, 'log' => $log];
        }

        return ['ok' => false, 'error' => 'Nao foi possivel iniciar o worker no Windows.', 'cmd' => $cmd, 'php' => $php, 'log' => $log];
    }

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId) . ' >> ' . escapeshellarg($log) . ' 2>&1 &';
    @exec($cmd, $output, $exitCode);

    return ['ok' => $exitCode === 0, 'cmd' => $cmd, 'php' => $php, 'exit_code' => $exitCode, 'log' => $log];
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
    $localConfigPath = __DIR__ . '/config.local.php';
    $localConfig = [];

    if (is_file($localConfigPath)) {
        $loaded = require $localConfigPath;
        if (is_array($loaded)) {
            $localConfig = $loaded;
        }
    }

    $config = array_merge([
        'host' => getenv('CRM_DB_HOST') ?: 'localhost',
        'database' => getenv('CRM_DB_NAME') ?: 'crm_simples',
        'username' => getenv('CRM_DB_USER') ?: '',
        'password' => getenv('CRM_DB_PASS') ?: '',
    ], $localConfig);

    foreach (['host', 'database', 'username'] as $field) {
        if (trim((string)($config[$field] ?? '')) === '') {
            throw new RuntimeException('Configuracao do CRM incompleta para o assistente de dados.');
        }
    }

    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
            (string)$config['username'],
            (string)$config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new RuntimeException('Conexao do CRM indisponivel para o assistente de dados.');
    }
}

function data_ai_ficha_mysqli(): mysqli
{
    $localConfigPath = __DIR__ . '/../ficha/config/conexao.local.php';
    $localConfig = [];

    if (is_file($localConfigPath)) {
        $loaded = require $localConfigPath;
        if (is_array($loaded)) {
            $localConfig = $loaded;
        }
    }

    $config = array_merge([
        'host' => getenv('FICHA_DB_HOST') ?: 'localhost',
        'port' => getenv('FICHA_DB_PORT') ?: 3306,
        'database' => getenv('FICHA_DB_NAME') ?: 'tatuagem_novo',
        'username' => getenv('FICHA_DB_USER') ?: '',
        'password' => getenv('FICHA_DB_PASS') ?: '',
    ], $localConfig);

    foreach (['host', 'database', 'username'] as $field) {
        if (trim((string)($config[$field] ?? '')) === '') {
            throw new RuntimeException('Configuracao da ficha incompleta para o assistente de dados.');
        }
    }

    try {
        $mysqli = new mysqli(
            (string)$config['host'],
            (string)$config['username'],
            (string)$config['password'],
            (string)$config['database'],
            (int)$config['port']
        );
        $mysqli->set_charset('utf8mb4');
        return $mysqli;
    } catch (mysqli_sql_exception $e) {
        throw new RuntimeException('Conexao da ficha/agenda indisponivel para o assistente de dados.');
    }
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
    data_ai_record_query('CRM', 'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function data_ai_mysqli_table_exists(mysqli $conn, string $table): bool
{
    data_ai_record_query('Ficha/Agenda', 'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
    $stmt = $conn->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = ((int)($stmt->get_result()->fetch_row()[0] ?? 0)) > 0;
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

function data_ai_question_days(string $question): int
{
    $question = data_ai_normalize_text($question);
    if (preg_match('/ultim[oa]s?\s+(\d{1,3})\s+dias/u', $question, $match)) {
        return max(1, min(180, (int)$match[1]));
    }
    if (preg_match('/últim[oa]s?\s+(\d{1,3})\s+dias/u', $question, $match)) {
        return max(1, min(180, (int)$match[1]));
    }

    return 0;
}

function data_ai_summarize_messages(array $messages, int $limit = 5): array
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

function data_ai_whatsapp_attention_sql(PDO $pdo): array
{
    $sql = "
        SELECT
            c.id, c.numero, c.nome, c.status, c.etapa, c.atendente, c.modo_atendimento,
            c.interesse, c.valor, c.origem, c.data_ultimo_contato, c.updated_at,
            lm.ultima_msg_cliente,
            lr.ultima_resposta_atendimento,
            TIMESTAMPDIFF(HOUR, lm.ultima_msg_cliente, NOW()) AS horas_sem_resposta
        FROM crm_whatsapp_clientes c
        INNER JOIN (
            SELECT cliente_id, MAX(data) AS ultima_msg_cliente
            FROM crm_whatsapp_mensagens
            WHERE from_me = 0
            GROUP BY cliente_id
        ) lm ON lm.cliente_id = c.id
        LEFT JOIN (
            SELECT cliente_id, MAX(data) AS ultima_resposta_atendimento
            FROM crm_whatsapp_mensagens
            WHERE from_me = 1
            GROUP BY cliente_id
        ) lr ON lr.cliente_id = c.id
        WHERE (
            lr.ultima_resposta_atendimento IS NULL
            OR lm.ultima_msg_cliente > lr.ultima_resposta_atendimento
            OR c.modo_atendimento = 'bot'
            OR c.atendente = 'bot'
        )
        AND LOWER(COALESCE(c.status, '')) NOT IN ('fechado', 'fechado - perdido', 'cancelado', 'concluido')
        ORDER BY
            CASE WHEN lr.ultima_resposta_atendimento IS NULL OR lm.ultima_msg_cliente > lr.ultima_resposta_atendimento THEN 0 ELSE 1 END,
            lm.ultima_msg_cliente DESC
        LIMIT 20
    ";
    $rows = data_ai_pdo_rows($pdo, $sql);
    $ids = array_map(static fn(array $row): string => (string)$row['id'], $rows);
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
            if (count($messagesByClient[$clientId]) < 3) {
                $messagesByClient[$clientId][] = $message;
            }
        }
    }

    foreach ($rows as &$row) {
        $row['interesse'] = data_ai_preview($row['interesse'] ?? '', 420);
        $row['motivo'] = empty($row['ultima_resposta_atendimento'])
            ? 'cliente sem resposta registrada do atendimento'
            : 'ultima mensagem do cliente posterior a ultima resposta do atendimento ou conversa em bot';
        $row['mensagens_recentes'] = data_ai_summarize_messages(array_reverse($messagesByClient[(string)$row['id']] ?? []), 3);
    }
    unset($row);

    return $rows;
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
            LIMIT 18
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
                if (count($messagesByClient[$clientId]) < 5) {
                    $messagesByClient[$clientId][] = $message;
                }
            }
        }

        foreach ($clients as &$client) {
            $messages = array_reverse($messagesByClient[(string)$client['id']] ?? []);
            $client['mensagens_recentes'] = data_ai_summarize_messages($messages, 5);
            $client['interesse'] = data_ai_preview($client['interesse'] ?? '', 500);
        }
        unset($client);

        return [
            'fonte' => 'crm_whatsapp_sql',
            'resumo' => $summary[0] ?? [],
            'por_status' => $byStatus,
            'precisam_atencao' => data_ai_whatsapp_attention_sql($pdo),
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
    $attention = [];

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
        $messages = data_ai_summarize_messages($client['mensagens'] ?? []);
        $client['mensagens_recentes'] = $messages;
        $client['interesse'] = data_ai_preview($client['interesse'] ?? '', 500);
        $lastMessage = $messages ? $messages[count($messages) - 1] : null;
        if ($lastMessage && (($lastMessage['autor'] ?? '') === 'Cliente' || $mode === 'bot')) {
            $attention[] = [
                'numero' => (string)($client['numero'] ?? ''),
                'nome' => (string)($client['nome'] ?? 'Cliente'),
                'status' => (string)($client['status'] ?? ''),
                'etapa' => (string)($client['etapa'] ?? ''),
                'modo_atendimento' => (string)($client['modo_atendimento'] ?? $client['atendente'] ?? ''),
                'interesse' => $client['interesse'],
                'valor' => (float)($client['valor'] ?? 0),
                'data_ultimo_contato' => (string)($client['data_ultimo_contato'] ?? ''),
                'motivo' => ($lastMessage['autor'] ?? '') === 'Cliente' ? 'ultima mensagem visivel veio do cliente' : 'conversa em modo bot',
                'mensagens_recentes' => array_slice($messages, -3),
            ];
        }
        unset($client['mensagens']);
    }
    unset($client);

    usort($clients, static fn(array $a, array $b): int => strcmp((string)($b['data_ultimo_contato'] ?? ''), (string)($a['data_ultimo_contato'] ?? '')));

    return [
        'fonte' => 'crm_whatsapp_json',
        'resumo' => $summary,
        'por_status' => array_values($byStatus),
        'precisam_atencao' => array_slice($attention, 0, 20),
        'conversas_recentes' => array_slice($clients, 0, 18),
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
                'recentes' => data_ai_pdo_rows($pdo, 'SELECT id, nome, telefone, interesse, valor, origem, status, etapa_funil AS etapa, data_ultimo_contato, created_at FROM leads ORDER BY created_at DESC, id DESC LIMIT 20'),
            ];
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
                        COALESCE(SUM(CASE WHEN data_tatuagem >= CURDATE() AND status <> 'cancelado' THEN valor ELSE 0 END), 0) AS valor_futuro_previsto,
                        COALESCE(SUM(CASE WHEN DATE_FORMAT(data_tatuagem, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') AND status <> 'cancelado' THEN valor ELSE 0 END), 0) AS valor_mes_atual
                    FROM tatuagens
                "),
                'por_status' => data_ai_mysqli_rows($conn, "SELECT status, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor FROM tatuagens GROUP BY status ORDER BY qtd DESC"),
                'futuras_por_status' => data_ai_mysqli_rows($conn, "
                    SELECT status, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor
                    FROM tatuagens
                    WHERE data_tatuagem >= CURDATE()
                    AND status <> 'cancelado'
                    GROUP BY status
                    ORDER BY qtd DESC
                "),
                'mes_atual_ate_hoje' => data_ai_mysqli_row($conn, "
                    SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN status <> 'cancelado' THEN 1 ELSE 0 END) AS total_sem_cancelados,
                        COALESCE(SUM(valor), 0) AS valor_total,
                        COALESCE(SUM(CASE WHEN status <> 'cancelado' THEN valor ELSE 0 END), 0) AS valor_sem_cancelados
                    FROM tatuagens
                    WHERE data_tatuagem >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                    AND data_tatuagem <= CURDATE()
                "),
                'mes_atual_ate_hoje_por_status' => data_ai_mysqli_rows($conn, "
                    SELECT status, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor
                    FROM tatuagens
                    WHERE data_tatuagem >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                    AND data_tatuagem <= CURDATE()
                    GROUP BY status
                    ORDER BY qtd DESC
                "),
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
                    AND t.status <> 'cancelado'
                    ORDER BY t.data_tatuagem ASC, t.hora_inicio ASC, t.id ASC
                    LIMIT 60
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

function data_ai_computed_context(string $question, array $context): array
{
    $computed = [
        'orientacao_para_resposta' => [
            'Use estes blocos calculados para numeros e listas prioritarias.',
            'As listas detalhadas podem ter limite de linhas; para totais, prefira campos resumo, por_status, por_mes e futuras_por_status.',
            'Se uma fonte estiver com ok=false, informe que aquela fonte nao foi considerada em vez de preencher a lacuna com suposicoes.',
        ],
        'intencoes_detectadas' => [],
        'avisos_fontes' => [],
    ];

    foreach (($context['fontes'] ?? []) as $name => $source) {
        if (empty($source['ok'])) {
            $computed['avisos_fontes'][] = $name . ': ' . (string)($source['error'] ?? 'indisponivel');
        }
    }

    $needsAgenda = data_ai_question_has_any($question, ['agenda', 'agendamento', 'agendamentos', 'futuro', 'proximo', 'proximos', 'receber', 'previsto', 'previsao', 'tatuagem', 'faturamento']);
    $needsWhatsapp = data_ai_question_has_any($question, ['whatsapp', 'conversa', 'conversas', 'mensagem', 'mensagens', 'atencao', 'responder', 'contato']);
    $needsLeads = data_ai_question_has_any($question, ['lead', 'leads', 'origem', 'pipeline', 'etapa', 'funil', 'potencial']);
    $needsFinanceiro = data_ai_question_has_any($question, ['financeiro', 'despesa', 'despesas', 'gasto', 'gastos', 'custo', 'custos', 'compare', 'comparar']);

    if ($needsAgenda) {
        $computed['intencoes_detectadas'][] = 'agenda_faturamento';
        $tatuagens = $context['fontes']['ficha_agenda']['data']['tatuagens'] ?? [];
        $computed['agenda_faturamento'] = [
            'resumo_geral' => $tatuagens['resumo'] ?? null,
            'mes_atual_ate_hoje' => $tatuagens['mes_atual_ate_hoje'] ?? null,
            'mes_atual_ate_hoje_por_status' => $tatuagens['mes_atual_ate_hoje_por_status'] ?? [],
            'futuras_por_status' => $tatuagens['futuras_por_status'] ?? [],
            'proximas_nao_canceladas' => $tatuagens['proximas'] ?? [],
            'por_status_geral' => $tatuagens['por_status'] ?? [],
            'por_mes' => $tatuagens['por_mes'] ?? [],
            'regra' => 'Agendamentos futuros e valor previsto excluem status cancelado. Para "mes ate agora", use mes_atual_ate_hoje.',
        ];
    }

    if ($needsWhatsapp) {
        $computed['intencoes_detectadas'][] = 'whatsapp_atencao';
        $whatsapp = $context['fontes']['crm']['data']['whatsapp'] ?? [];
        $computed['whatsapp_atencao'] = [
            'resumo' => $whatsapp['resumo'] ?? null,
            'por_status' => $whatsapp['por_status'] ?? [],
            'conversas_prioritarias' => $whatsapp['precisam_atencao'] ?? [],
            'conversas_recentes' => $whatsapp['conversas_recentes'] ?? [],
            'regra' => 'Priorize conversas em que a ultima mensagem visivel veio do cliente, sem resposta posterior do atendimento, ou em modo bot.',
        ];
    }

    if ($needsLeads) {
        $computed['intencoes_detectadas'][] = 'leads_pipeline';
        $leads = $context['fontes']['crm']['data']['leads'] ?? [];
        $computed['leads_pipeline'] = [
            'resumo' => $leads['resumo'] ?? null,
            'por_status' => $leads['por_status'] ?? [],
            'por_origem' => $leads['por_origem'] ?? [],
            'por_etapa' => $leads['por_etapa'] ?? [],
            'recentes' => $leads['recentes'] ?? [],
        ];
    }

    if ($needsFinanceiro) {
        $computed['intencoes_detectadas'][] = 'financeiro';
        $computed['financeiro'] = [
            'despesas' => $context['fontes']['financeiro']['data'] ?? [],
            'tatuagens_mes_atual' => $context['fontes']['ficha_agenda']['data']['tatuagens']['resumo']['valor_mes_atual'] ?? null,
        ];
    }

    if (!$computed['intencoes_detectadas']) {
        $computed['intencoes_detectadas'][] = 'geral';
    }

    return $computed;
}

function data_ai_build_context(string $question): array
{
    $sources = [
        'crm' => data_ai_crm_context(),
        'ficha_agenda' => data_ai_ficha_context($question),
        'financeiro' => data_ai_finance_context(),
    ];
    $baseContext = [
        'gerado_em' => date('Y-m-d H:i:s'),
        'pergunta' => data_ai_preview($question, 1200),
        'seguranca' => [
            'modo' => 'somente_leitura',
            'observacao' => 'A IA nao recebe permissao para escrever no banco. O sistema envia apenas resultados de consultas SELECT fixas e leitura de JSON.',
        ],
        'fontes' => $sources,
    ];

    return [
        'gerado_em' => $baseContext['gerado_em'],
        'pergunta' => $baseContext['pergunta'],
        'seguranca' => $baseContext['seguranca'],
        'dados_calculados_para_resposta' => data_ai_computed_context($question, $baseContext),
        'fontes' => $sources,
    ];
}

function data_ai_is_month_to_date_schedule_question(string $question): bool
{
    $hasSchedule = data_ai_question_has_any($question, ['agenda', 'agendamento', 'agendamentos', 'tatuagem', 'tatuagens']);
    $hasMonth = data_ai_question_has_any($question, ['nesse mes', 'neste mes', 'mes atual', 'esse mes', 'ate agora', 'até agora']);
    $hasRelativeMonthNow = data_ai_question_has_any($question, ['nesse', 'neste', 'esse']) && data_ai_question_has_any($question, ['agora']);
    $asksCount = data_ai_question_has_any($question, ['quantos', 'quantas', 'quantidade', 'total', 'tiveram', 'teve', 'foram']);

    return $hasSchedule && ($hasMonth || $hasRelativeMonthNow) && $asksCount;
}

function data_ai_try_local_answer(string $question, array $context, float $startedAt, float $contextSeconds): ?array
{
    if (!data_ai_is_month_to_date_schedule_question($question)) {
        return null;
    }

    $ficha = $context['fontes']['ficha_agenda'] ?? [];
    $agenda = $context['dados_calculados_para_resposta']['agenda_faturamento'] ?? [];
    $month = $agenda['mes_atual_ate_hoje'] ?? null;
    if (empty($ficha['ok']) || !is_array($month)) {
        $reason = rtrim(!empty($ficha['error']) ? (string)$ficha['error'] : 'dados da ficha/agenda indisponiveis', '.');
        return [
            'ok' => true,
            'answer' => 'Nao consegui responder quantos agendamentos tiveram neste mes ate agora porque a fonte da agenda nao esta disponivel: ' . $reason . '.',
            'transparency' => [
                'A pergunta foi identificada como contagem de agendamentos do mes atual ate hoje.',
                'A resposta local nao usou WhatsApp nem leads porque eles nao respondem essa pergunta.',
            ],
            'queries' => data_ai_public_queries(),
            'thinking' => '',
            'raw_model_output' => '',
            'diagnostic' => [
                'stage' => 'concluido_local_sem_fonte',
                'context_seconds' => $contextSeconds,
                'total_seconds' => round(microtime(true) - $startedAt, 3),
                'local_answer' => true,
            ],
            'model' => 'Analise local',
            'read_only' => true,
            'generated_at' => $context['gerado_em'],
        ];
    }

    $total = data_ai_int_value($month['total'] ?? 0);
    $totalSemCancelados = data_ai_int_value($month['total_sem_cancelados'] ?? $total);
    $valorSemCancelados = (float)($month['valor_sem_cancelados'] ?? 0);
    $porStatus = $agenda['mes_atual_ate_hoje_por_status'] ?? [];
    $statusLines = [];
    foreach (array_slice(is_array($porStatus) ? $porStatus : [], 0, 8) as $row) {
        $status = trim((string)($row['status'] ?? 'sem status')) ?: 'sem status';
        $statusLines[] = '- ' . $status . ': ' . data_ai_int_value($row['qtd'] ?? 0) . ' agendamento(s)';
    }

    $answer = 'Neste mes, ate hoje (' . date('d/m/Y') . '), tiveram ' . $totalSemCancelados . ' agendamento(s) nao cancelado(s).';
    if ($total !== $totalSemCancelados) {
        $answer .= "\nNo total bruto do mes ate hoje foram " . $total . ' registro(s), incluindo cancelados.';
    }
    $answer .= "\nValor previsto desses agendamentos nao cancelados: " . data_ai_money_br($valorSemCancelados) . '.';
    if ($statusLines) {
        $answer .= "\n\nPor status:\n" . implode("\n", $statusLines);
    }

    return [
        'ok' => true,
        'answer' => $answer,
        'transparency' => [
            'Pergunta respondida por calculo local, usando somente a tabela de tatuagens/agendamentos.',
            'Periodo considerado: do primeiro dia do mes atual ate hoje.',
            'A contagem principal exclui status cancelado; o total bruto aparece separadamente quando houver cancelados.',
        ],
        'queries' => data_ai_public_queries(),
        'thinking' => '',
        'raw_model_output' => '',
        'diagnostic' => [
            'stage' => 'concluido_local',
            'context_seconds' => $contextSeconds,
            'total_seconds' => round(microtime(true) - $startedAt, 3),
            'local_answer' => true,
            'intent' => 'agenda_mes_ate_hoje',
        ],
        'model' => 'Analise local',
        'read_only' => true,
        'generated_at' => $context['gerado_em'],
    ];
}

function data_ai_ollama_chat_request(string $ollamaUrl, string $model, array $messages, int $timeout, int $numPredict, int $numCtx = 12000, bool $jsonMode = false): array
{
    $payload = [
        'model' => $model,
        'stream' => false,
        'think' => false,
        'messages' => $messages,
        'options' => [
            'temperature' => 0.1,
            'num_predict' => $numPredict,
            'num_ctx' => $numCtx,
        ],
    ];
    if ($jsonMode) {
        $payload['format'] = 'json';
    }
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Nao foi possivel montar payload JSON para o modelo.', 'json_error' => json_last_error_msg()];
    }

    $ch = curl_init($ollamaUrl . '/api/chat');
    curl_setopt($ch, CURLOPT_POST, true);
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
        return [
            'ok' => false,
            'error' => $curlErrno === 28 || stripos($curlError, 'timed out') !== false ? 'Timeout aguardando resposta do Ollama.' : 'Erro de conexao com Ollama.',
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'http_code' => $httpCode,
            'seconds' => $totalTime,
            'raw_preview' => data_ai_preview((string)$raw, 1800),
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($json)) {
        return [
            'ok' => false,
            'error' => is_array($json) ? (string)($json['error'] ?? 'Ollama retornou erro HTTP.') : 'Ollama retornou JSON invalido.',
            'http_code' => $httpCode,
            'seconds' => $totalTime,
            'raw_preview' => data_ai_preview((string)$raw, 2200),
        ];
    }

    $content = (string)($json['message']['content'] ?? '');
    return [
        'ok' => true,
        'content' => $content,
        'thinking' => (string)($json['message']['thinking'] ?? data_ai_extract_thinking($content)),
        'raw' => $json,
        'http_code' => $httpCode,
        'seconds' => $totalTime,
        'done_reason' => $json['done_reason'] ?? null,
        'eval_count' => $json['eval_count'] ?? null,
        'prompt_eval_count' => $json['prompt_eval_count'] ?? null,
    ];
}

function data_ai_schema_for_pdo(PDO $pdo, array $tables): array
{
    $schema = [];
    foreach ($tables as $table) {
        if (!data_ai_pdo_table_exists($pdo, $table)) {
            continue;
        }
        data_ai_record_query('Schema CRM', 'DESCRIBE `' . $table . '`');
        $columns = $pdo->query('DESCRIBE `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $schema[$table] = array_map(static function (array $column): array {
            return [
                'nome' => (string)($column['Field'] ?? ''),
                'tipo' => (string)($column['Type'] ?? ''),
            ];
        }, $columns);
    }

    return $schema;
}

function data_ai_schema_for_mysqli(mysqli $conn, array $tables): array
{
    $schema = [];
    foreach ($tables as $table) {
        if (!data_ai_mysqli_table_exists($conn, $table)) {
            continue;
        }
        data_ai_record_query('Schema Ficha/Agenda', 'DESCRIBE `' . $table . '`');
        $result = $conn->query('DESCRIBE `' . $table . '`');
        $columns = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $schema[$table] = array_map(static function (array $column): array {
            return [
                'nome' => (string)($column['Field'] ?? ''),
                'tipo' => (string)($column['Type'] ?? ''),
            ];
        }, $columns);
    }

    return $schema;
}

function data_ai_query_schema_context(): array
{
    $context = [
        'crm' => ['ok' => false, 'tables' => [], 'error' => null],
        'ficha' => ['ok' => false, 'tables' => [], 'error' => null],
        'financeiro_json' => [
            'ok' => true,
            'observacao' => 'Despesas ficam em crm/data/finance_expenses.json e ja entram no contexto complementar; nao gere SQL para financeiro_json.',
            'campos_comuns' => ['data', 'descricao', 'categoria', 'valor'],
        ],
    ];

    try {
        $pdo = data_ai_crm_pdo();
        $context['crm']['tables'] = data_ai_schema_for_pdo($pdo, ['leads', 'pipelines', 'interacoes', 'crm_whatsapp_clientes', 'crm_whatsapp_mensagens']);
        $context['crm']['ok'] = !empty($context['crm']['tables']);
    } catch (Throwable $e) {
        $context['crm']['error'] = $e->getMessage();
    }

    try {
        $conn = data_ai_ficha_mysqli();
        $context['ficha']['tables'] = data_ai_schema_for_mysqli($conn, ['clientes', 'tatuagens']);
        $context['ficha']['ok'] = !empty($context['ficha']['tables']);
    } catch (Throwable $e) {
        $context['ficha']['error'] = $e->getMessage();
    }

    return $context;
}

function data_ai_validate_dynamic_sql(string $sql, string $source, array $schema): array
{
    $sql = trim($sql);
    $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;
    if ($sql === '') {
        return ['ok' => false, 'error' => 'SQL vazio.'];
    }
    if (!preg_match('/^select\s/i', $sql)) {
        return ['ok' => false, 'error' => 'A consulta precisa comecar com SELECT.'];
    }
    if (strpos($sql, ';') !== false || preg_match('/--|#|\/\*/', $sql)) {
        return ['ok' => false, 'error' => 'A consulta nao pode conter comentarios ou ponto e virgula.'];
    }
    if (preg_match('/\b(insert|update|delete|drop|alter|truncate|create|replace|grant|revoke|call|load|outfile|dumpfile|lock|unlock|handler)\b/i', $sql)) {
        return ['ok' => false, 'error' => 'A consulta contem palavra-chave nao permitida.'];
    }
    if (preg_match('/\b(senha|senha_hash|password|token|token_hash|secret)\b/i', $sql)) {
        return ['ok' => false, 'error' => 'A consulta tenta acessar campo sensivel nao permitido.'];
    }
    if (preg_match('/\binformation_schema\b/i', $sql)) {
        return ['ok' => false, 'error' => 'information_schema nao e permitido.'];
    }

    $tables = array_keys($schema[$source]['tables'] ?? []);
    if (!$tables) {
        return ['ok' => false, 'error' => 'Fonte sem tabelas disponiveis.'];
    }
    preg_match_all('/\b(?:from|join)\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $matches);
    $usedTables = array_unique($matches[1] ?? []);
    if (!$usedTables) {
        return ['ok' => false, 'error' => 'Nao identifiquei tabela na consulta.'];
    }
    foreach ($usedTables as $table) {
        if (!in_array($table, $tables, true)) {
            return ['ok' => false, 'error' => 'Tabela nao permitida: ' . $table];
        }
    }

    return ['ok' => true, 'sql' => $sql, 'used_tables' => $usedTables];
}

function data_ai_execute_dynamic_query(string $source, string $sql, array $schema, int $limit = 200): array
{
    $validation = data_ai_validate_dynamic_sql($sql, $source, $schema);
    if (empty($validation['ok'])) {
        return ['ok' => false, 'error' => $validation['error'] ?? 'Consulta invalida.', 'sql' => $sql];
    }

    $safeSql = 'SELECT * FROM (' . $validation['sql'] . ') AS data_ai_result LIMIT ' . max(1, min(500, $limit));
    if ($source === 'crm') {
        $pdo = data_ai_crm_pdo();
        data_ai_record_query('Consulta dinamica CRM', $safeSql);
        $rows = $pdo->query($safeSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $conn = data_ai_ficha_mysqli();
        data_ai_record_query('Consulta dinamica Ficha/Agenda', $safeSql);
        $result = $conn->query($safeSql);
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    return [
        'ok' => true,
        'source' => $source,
        'sql' => $validation['sql'],
        'safe_sql' => $safeSql,
        'rows' => $rows,
        'row_count' => count($rows),
        'used_tables' => $validation['used_tables'] ?? [],
    ];
}

function data_ai_plan_dynamic_queries(string $question, array $schema, string $ollamaUrl, string $model, int $timeout): array
{
    $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $messages = [
        ['role' => 'system', 'content' =>
            "Voce e um planejador SQL MySQL somente leitura. Gere consultas SELECT para responder a pergunta do gestor usando APENAS as tabelas e colunas listadas. Retorne exclusivamente JSON valido no formato {\"queries\":[{\"fonte\":\"crm|ficha\",\"finalidade\":\"por que esta consulta e necessaria\",\"sql\":\"SELECT ...\"}],\"observacoes\":[\"limites ou fonte ausente\"]}. Regras: maximo 6 consultas; cada SQL deve comecar com SELECT; nao use escrita, DDL, comentarios, ponto e virgula, information_schema, funcoes perigosas ou tabelas fora do schema; prefira COUNT/SUM/GROUP BY para perguntas numericas; use CURDATE(), DATE_FORMAT(CURDATE(), '%Y-%m-01') e datas do banco quando a pergunta falar de hoje, mes atual ou futuro; inclua LIMIT em listagens."
        ],
        ['role' => 'user', 'content' => "Data atual: " . date('Y-m-d') . "\nPergunta: " . $question . "\nSchema disponivel:\n" . $schemaJson],
    ];

    $response = data_ai_ollama_chat_request($ollamaUrl, $model, $messages, $timeout, 900, 10000, true);
    if (empty($response['ok'])) {
        return ['ok' => false, 'error' => $response['error'] ?? 'Falha planejando consultas.', 'details' => $response];
    }

    $planned = data_ai_parse_json_response((string)$response['content']);
    if (!is_array($planned)) {
        return ['ok' => false, 'error' => 'O modelo nao retornou um plano JSON valido.', 'details' => $response];
    }

    $plannedQueries = is_array($planned['queries'] ?? null) ? $planned['queries'] : [];
    $plannedNotes = is_array($planned['observacoes'] ?? null) ? $planned['observacoes'] : [];
    $queries = array_values(array_filter($plannedQueries, 'is_array'));
    return [
        'ok' => true,
        'queries' => array_slice($queries, 0, 6),
        'observacoes' => array_values(array_filter(array_map('strval', $plannedNotes))),
        'planner_raw' => $response['content'],
        'planner_diagnostic' => $response,
    ];
}

function data_ai_result_payload(string $source, string $purpose, string $sql, array $rows): array
{
    return [
        'ok' => true,
        'source' => $source,
        'finalidade' => $purpose,
        'sql' => trim(preg_replace('/\s+/', ' ', $sql) ?? $sql),
        'rows' => $rows,
        'row_count' => count($rows),
    ];
}

function data_ai_status_filter_from_question(string $question): array
{
    $statuses = [];
    foreach (['agendado', 'confirmado', 'cancelado', 'concluido'] as $status) {
        if (data_ai_question_has_any($question, [$status])) {
            $statuses[] = $status;
        }
    }

    return $statuses;
}

function data_ai_period_filter_from_question(string $question, string $column): string
{
    if (data_ai_question_has_any($question, ['mes passado', 'mês passado'])) {
        return "$column >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND $column < DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    }
    if (data_ai_question_has_any($question, ['mes', 'mês', 'este mes', 'esse mes', 'neste mes', 'nesse mes', 'mes atual'])) {
        return "$column >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND $column <= LAST_DAY(CURDATE())";
    }
    if (data_ai_question_has_any($question, ['futuro', 'futuros', 'proximo', 'proximos', 'próximo', 'próximos'])) {
        return "$column >= CURDATE()";
    }
    if (data_ai_question_has_any($question, ['hoje'])) {
        return "$column = CURDATE()";
    }

    return "$column >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
}

function data_ai_collect_relevant_results(string $question): array
{
    $results = [];
    $errors = [];
    $wantsAgenda = data_ai_question_has_any($question, ['agenda', 'agendamento', 'agendamentos', 'tatuagem', 'tatuagens', 'cliente', 'clientes', 'confirmado', 'agendado', 'valor']);
    $wantsCrm = data_ai_question_has_any($question, ['lead', 'leads', 'pipeline', 'origem', 'funil']);
    $wantsWhatsapp = data_ai_question_has_any($question, ['whatsapp', 'conversa', 'conversas', 'mensagem', 'mensagens', 'atencao', 'atenção', 'responder']);
    $wantsFinance = data_ai_question_has_any($question, ['financeiro', 'despesa', 'despesas', 'gasto', 'gastos', 'custo', 'custos']);

    if (!$wantsAgenda && !$wantsCrm && !$wantsWhatsapp && !$wantsFinance) {
        $wantsAgenda = $wantsCrm = $wantsWhatsapp = $wantsFinance = true;
    }

    if ($wantsAgenda) {
        try {
            $conn = data_ai_ficha_mysqli();
            if (data_ai_mysqli_table_exists($conn, 'tatuagens')) {
                $period = data_ai_period_filter_from_question($question, 't.data_tatuagem');
                $statuses = data_ai_status_filter_from_question($question);
                $statusClause = '';
                if ($statuses) {
                    $quoted = array_map(static fn(string $status): string => "'" . str_replace("'", "''", $status) . "'", $statuses);
                    $statusClause = ' AND t.status IN (' . implode(',', $quoted) . ')';
                } elseif (!data_ai_question_has_any($question, ['cancelado', 'cancelados'])) {
                    $statusClause = " AND t.status <> 'cancelado'";
                }

                $sqlList = "
                    SELECT t.id, c.nome AS cliente_nome, c.telefone AS cliente_telefone, t.descricao, t.valor, t.data_tatuagem, t.hora_inicio, t.hora_fim, t.status, t.observacoes
                    FROM tatuagens t
                    LEFT JOIN clientes c ON c.id = t.cliente_id
                    WHERE $period $statusClause
                    ORDER BY t.data_tatuagem ASC, t.hora_inicio ASC, t.id ASC
                    LIMIT 120
                ";
                $rowsList = data_ai_mysqli_rows($conn, $sqlList);
                $results[] = data_ai_result_payload('ficha', 'agendamentos/clientes relevantes para a pergunta', $sqlList, $rowsList);

                $sqlSummary = "
                    SELECT t.status, COUNT(*) AS qtd, COALESCE(SUM(t.valor), 0) AS valor
                    FROM tatuagens t
                    WHERE $period $statusClause
                    GROUP BY t.status
                    ORDER BY qtd DESC
                ";
                $results[] = data_ai_result_payload('ficha', 'resumo por status dos agendamentos filtrados', $sqlSummary, data_ai_mysqli_rows($conn, $sqlSummary));
            }
        } catch (Throwable $e) {
            $errors[] = ['source' => 'ficha', 'error' => $e->getMessage()];
        }
    }

    if ($wantsCrm || $wantsWhatsapp) {
        try {
            $pdo = data_ai_crm_pdo();
            if ($wantsCrm && data_ai_pdo_table_exists($pdo, 'leads')) {
                $sql = 'SELECT id, nome, telefone, interesse, valor, origem, status, etapa_funil AS etapa, data_ultimo_contato, created_at FROM leads ORDER BY created_at DESC, id DESC LIMIT 80';
                $results[] = data_ai_result_payload('crm', 'leads recentes e valores potenciais', $sql, data_ai_pdo_rows($pdo, $sql));
                $sql = 'SELECT status, origem, etapa_funil AS etapa, COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor FROM leads GROUP BY status, origem, etapa_funil ORDER BY qtd DESC LIMIT 80';
                $results[] = data_ai_result_payload('crm', 'agrupamento de leads por status, origem e etapa', $sql, data_ai_pdo_rows($pdo, $sql));
            }
            if ($wantsWhatsapp && data_ai_pdo_table_exists($pdo, 'crm_whatsapp_clientes')) {
                $sql = "SELECT id, numero, nome, status, etapa, atendente, modo_atendimento, interesse, valor, origem, data_ultimo_contato, updated_at FROM crm_whatsapp_clientes ORDER BY COALESCE(data_ultimo_contato, updated_at) DESC LIMIT 80";
                $results[] = data_ai_result_payload('crm', 'conversas recentes do WhatsApp', $sql, data_ai_pdo_rows($pdo, $sql));
                if (data_ai_pdo_table_exists($pdo, 'crm_whatsapp_mensagens')) {
                    $attention = data_ai_whatsapp_attention_sql($pdo);
                    $results[] = data_ai_result_payload('crm', 'conversas do WhatsApp que parecem precisar de atencao', 'consulta segura de ultimas mensagens e respostas por cliente', $attention);
                }
            }
        } catch (Throwable $e) {
            $errors[] = ['source' => 'crm', 'error' => $e->getMessage()];
        }
    }

    if ($wantsFinance) {
        $finance = data_ai_finance_context();
        if (!empty($finance['ok'])) {
            $rows = is_array($finance['data']['recentes'] ?? null) ? $finance['data']['recentes'] : [];
            $results[] = data_ai_result_payload('financeiro', 'despesas recentes cadastradas', 'READ JSON crm/data/finance_expenses.json', $rows);
        } else {
            $errors[] = ['source' => 'financeiro', 'error' => (string)($finance['error'] ?? 'Financeiro indisponivel')];
        }
    }

    return ['results' => $results, 'errors' => $errors];
}

function data_ai_local_answer_from_results(string $question, array $results, array $errors, float $startedAt, float $contextSeconds, string $generatedAt): ?array
{
    foreach ($results as $result) {
        if (($result['source'] ?? '') !== 'ficha' || stripos((string)($result['finalidade'] ?? ''), 'agendamentos/clientes') === false) {
            continue;
        }
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        if (!$rows) {
            return [
                'ok' => true,
                'answer' => 'Nao encontrei agendamentos que batam com essa pergunta no periodo/filtro solicitado.',
                'transparency' => ['Resposta montada a partir de consulta segura na agenda/ficha.'],
                'queries' => data_ai_public_queries(),
                'thinking' => '',
                'raw_model_output' => '',
                'diagnostic' => ['stage' => 'concluido_coleta_local', 'total_seconds' => round(microtime(true) - $startedAt, 3), 'context_seconds' => $contextSeconds],
                'model' => 'Analise local',
                'read_only' => true,
                'generated_at' => $generatedAt,
            ];
        }

        $total = count($rows);
        $value = array_reduce($rows, static fn(float $sum, array $row): float => $sum + (float)($row['valor'] ?? 0), 0.0);
        $lines = ['Encontrei ' . $total . ' agendamento(s) para o filtro da pergunta, somando ' . data_ai_money_br($value) . '.'];
        $lines[] = '';
        foreach (array_slice($rows, 0, 30) as $row) {
            $date = trim((string)($row['data_tatuagem'] ?? ''));
            $hour = trim((string)($row['hora_inicio'] ?? ''));
            $client = trim((string)($row['cliente_nome'] ?? 'Cliente sem nome')) ?: 'Cliente sem nome';
            $status = trim((string)($row['status'] ?? 'sem status')) ?: 'sem status';
            $lines[] = '- ' . $client . ': ' . data_ai_money_br($row['valor'] ?? 0) . ', ' . $status . ($date !== '' ? ', ' . $date : '') . ($hour !== '' ? ' ' . substr($hour, 0, 5) : '');
        }
        if ($total > 30) {
            $lines[] = '- ...mais ' . ($total - 30) . ' registro(s) no filtro.';
        }

        return [
            'ok' => true,
            'answer' => implode("\n", $lines),
            'transparency' => ['Resposta montada a partir de consulta segura na agenda/ficha.', 'Linhas consideradas: ' . $total . '.'],
            'queries' => data_ai_public_queries(),
            'thinking' => '',
            'raw_model_output' => '',
            'diagnostic' => ['stage' => 'concluido_coleta_local', 'total_seconds' => round(microtime(true) - $startedAt, 3), 'context_seconds' => $contextSeconds],
            'model' => 'Analise local',
            'read_only' => true,
            'generated_at' => $generatedAt,
        ];
    }

    return null;
}

function data_ai_try_collected_answer(string $question, array $context, string $ollamaUrl, string $model, int $timeout, int $numPredict, float $startedAt, float $contextSeconds, ?callable $progress = null): ?array
{
    if ($progress) {
        $progress('coleta_dados', 'Coletando dados relevantes com consultas seguras.', 56, []);
    }
    $collected = data_ai_collect_relevant_results($question);
    $results = $collected['results'];
    $errors = $collected['errors'];
    if (!$results) {
        return null;
    }

    $local = data_ai_local_answer_from_results($question, $results, $errors, $startedAt, $contextSeconds, (string)$context['gerado_em']);
    if ($local !== null && count($results) <= 2 && data_ai_question_has_any($question, ['quantos', 'quais', 'qual', 'valor', 'clientes', 'agendamentos'])) {
        return $local;
    }

    if ($progress) {
        $progress('resposta_com_dados', 'Gerando resposta com os dados coletados.', 78, ['results' => count($results)]);
    }
    $answerInput = [
        'pergunta' => $question,
        'data_atual' => date('Y-m-d'),
        'resultados_consultas' => $results,
        'erros_fontes' => $errors,
    ];
    $answerJson = json_encode($answerInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($answerJson === false || !function_exists('curl_init')) {
        return $local;
    }
    $messages = [
        ['role' => 'system', 'content' => 'Voce e um analista interno do estudio de tatuagem. Responda em portugues do Brasil, direto e humano. Use somente os resultados_consultas. Responda exatamente a pergunta, sem resumo geral. Retorne JSON valido: {"resposta":"texto final","transparencia":["evidencia usada"]}.'],
        ['role' => 'user', 'content' => $answerJson],
    ];
    $response = data_ai_ollama_chat_request($ollamaUrl, $model, $messages, max(30, min(90, $timeout - (int)ceil(microtime(true) - $startedAt))), min($numPredict, 1600), 9000, true);
    if (empty($response['ok'])) {
        return $local;
    }
    $structured = data_ai_parse_json_response((string)$response['content']);
    $answer = data_ai_clean_answer((string)($structured['resposta'] ?? $response['content']));
    if ($answer === '') {
        return $local;
    }
    $transparency = is_array($structured['transparencia'] ?? null) ? $structured['transparencia'] : [];
    $transparency = array_values(array_filter(array_map(static fn($item): string => data_ai_preview($item, 260), $transparency)));
    $transparency[] = 'Consultas seguras coletadas: ' . count($results) . '.';

    return [
        'ok' => true,
        'answer' => $answer,
        'transparency' => $transparency,
        'queries' => data_ai_public_queries(),
        'thinking' => (string)($response['thinking'] ?? ''),
        'raw_model_output' => (string)$response['content'],
        'diagnostic' => [
            'stage' => 'concluido_coleta_ia',
            'context_seconds' => $contextSeconds,
            'total_seconds' => round(microtime(true) - $startedAt, 3),
            'collected_results' => count($results),
            'model' => $model,
        ],
        'model' => $model . ' + coleta segura',
        'read_only' => true,
        'generated_at' => $context['gerado_em'],
    ];
}

function data_ai_try_dynamic_answer(string $question, array $context, string $ollamaUrl, string $model, int $timeout, int $numPredict, float $startedAt, float $contextSeconds, ?callable $progress = null): ?array
{
    $emitProgress = static function (string $stage, string $label, int $percent, array $details = []) use ($progress): void {
        if ($progress) {
            $progress($stage, $label, $percent, $details);
        }
    };

    $emitProgress('schema_dinamico', 'Mapeando tabelas disponiveis para consulta dinamica.', 46);
    $schema = data_ai_query_schema_context();
    if (empty($schema['crm']['ok']) && empty($schema['ficha']['ok'])) {
        return null;
    }

    $emitProgress('planejamento_sql', 'IA escolhendo consultas SQL somente leitura.', 52);
    $plan = data_ai_plan_dynamic_queries($question, $schema, $ollamaUrl, $model, min($timeout, 75));
    if (empty($plan['ok']) || empty($plan['queries'])) {
        return data_ai_error('planejamento_sql', 'planejamento_sql', 'Nao consegui montar uma consulta segura para responder essa pergunta. Tente reformular com um pouco mais de contexto.', [
            'plan_error' => $plan['error'] ?? 'Plano sem consultas.',
            'planner_raw' => data_ai_preview((string)($plan['details']['content'] ?? $plan['planner_raw'] ?? ''), 1800),
            'schema_sources' => [
                'crm_ok' => !empty($schema['crm']['ok']),
                'ficha_ok' => !empty($schema['ficha']['ok']),
            ],
        ]);
    }

    $results = [];
    $errors = [];
    $emitProgress('execucao_sql', 'Executando consultas validadas em modo somente leitura.', 62, [
        'queries' => count($plan['queries']),
    ]);
    foreach ($plan['queries'] as $index => $query) {
        $source = strtolower(trim((string)($query['fonte'] ?? '')));
        $sql = trim((string)($query['sql'] ?? ''));
        if (!in_array($source, ['crm', 'ficha'], true)) {
            $errors[] = ['index' => $index, 'error' => 'Fonte invalida no plano.', 'query' => $query];
            continue;
        }
        try {
            $executed = data_ai_execute_dynamic_query($source, $sql, $schema);
            $executed['finalidade'] = (string)($query['finalidade'] ?? '');
            if (!empty($executed['ok'])) {
                $results[] = $executed;
            } else {
                $errors[] = $executed;
            }
        } catch (Throwable $e) {
            $errors[] = ['source' => $source, 'sql' => $sql, 'error' => $e->getMessage()];
        }
    }

    if (!$results) {
        return data_ai_error('consultas_dinamicas_invalidas', 'execucao_sql', 'A IA montou consultas, mas nenhuma passou pela validacao de seguranca ou execucao somente leitura.', [
            'errors' => $errors,
            'planned_queries' => $plan['queries'],
        ]);
    }

    $answerInput = [
        'pergunta' => $question,
        'data_atual' => date('Y-m-d'),
        'resultados_consultas' => $results,
        'erros_consultas_descartadas' => $errors,
        'observacoes_do_plano' => $plan['observacoes'] ?? [],
        'contexto_complementar' => [
            'dados_calculados_para_resposta' => $context['dados_calculados_para_resposta'] ?? [],
            'financeiro' => $context['fontes']['financeiro'] ?? [],
            'fontes_indisponiveis' => $context['dados_calculados_para_resposta']['avisos_fontes'] ?? [],
        ],
    ];
    $answerJson = json_encode($answerInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($answerJson === false) {
        return null;
    }

    $emitProgress('resposta_dinamica', 'Gerando resposta humanizada com os resultados reais.', 78);
    $messages = [
        ['role' => 'system', 'content' =>
            "Voce e um analista interno do estudio de tatuagem. Responda em portugues do Brasil, de forma direta, humana e util. Use somente os resultados_consultas e o contexto_complementar. Responda exatamente a pergunta; nao faca resumo geral se a pergunta for especifica. Se uma consulta veio vazia, diga isso. Se houve fonte indisponivel, mencione apenas quando afetar a resposta. Retorne exclusivamente JSON valido: {\"resposta\":\"texto final para o gestor\",\"transparencia\":[\"consulta/evidencia usada\",\"limite relevante\"]}."
        ],
        ['role' => 'user', 'content' => $answerJson],
    ];
    $answerResponse = data_ai_ollama_chat_request($ollamaUrl, $model, $messages, max(30, $timeout - (int)ceil(microtime(true) - $startedAt)), min($numPredict, 2600), 12000, true);
    if (empty($answerResponse['ok'])) {
        return data_ai_error('resposta_dinamica', 'resposta_dinamica', 'As consultas foram executadas, mas nao consegui transformar os resultados em resposta agora.', [
            'answer_error' => $answerResponse['error'] ?? 'Falha na resposta final.',
            'executed_queries' => count($results),
            'query_results_preview' => data_ai_preview(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '', 2200),
        ]);
    }

    $structured = data_ai_parse_json_response((string)$answerResponse['content']);
    $answer = data_ai_clean_answer((string)($structured['resposta'] ?? $answerResponse['content']));
    if ($answer === '') {
        return data_ai_error('resposta_dinamica_vazia', 'resposta_dinamica', 'As consultas foram executadas, mas o modelo retornou uma resposta vazia.', [
            'executed_queries' => count($results),
            'raw_preview' => data_ai_preview((string)$answerResponse['content'], 2200),
        ]);
    }
    $transparency = $structured['transparencia'] ?? [];
    if (!is_array($transparency)) {
        $transparency = [];
    }
    $transparency = array_values(array_filter(array_map(static fn($item): string => data_ai_preview($item, 260), $transparency)));
    $transparency[] = 'Consultas dinamicas somente leitura executadas: ' . count($results) . '.';

    return [
        'ok' => true,
        'answer' => $answer,
        'transparency' => $transparency,
        'queries' => data_ai_public_queries(),
        'thinking' => (string)($answerResponse['thinking'] ?? ''),
        'raw_model_output' => (string)$answerResponse['content'],
        'diagnostic' => [
            'stage' => 'concluido_dinamico',
            'context_seconds' => $contextSeconds,
            'total_seconds' => round(microtime(true) - $startedAt, 3),
            'dynamic_answer' => true,
            'planned_queries' => count($plan['queries']),
            'executed_queries' => count($results),
            'discarded_queries' => count($errors),
            'planner_seconds' => $plan['planner_diagnostic']['seconds'] ?? null,
            'answer_seconds' => $answerResponse['seconds'] ?? null,
            'model' => $model,
        ],
        'model' => $model . ' + consultas dinamicas',
        'read_only' => true,
        'generated_at' => $context['gerado_em'],
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

    $startedAt = microtime(true);
    $settings = system_settings_load();
    $ollamaUrl = rtrim(trim((string)($settings['ollama_url'] ?? 'http://localhost:11434')), '/') ?: 'http://localhost:11434';
    $model = trim((string)($settings['data_ai_model'] ?? 'qwen3:14b')) ?: 'qwen3:14b';
    $timeout = max(30, min(420, (int)($settings['data_ai_timeout_seconds'] ?? 240)));
    $numPredict = max(120, min(6000, (int)($settings['data_ai_num_predict'] ?? 2400)));
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
    $emitProgress('contexto_pronto', 'Contexto pronto. Preparando chamada para o Ollama.', 42, [
        'context_seconds' => $contextSeconds,
        'queries' => count(data_ai_public_queries()),
    ]);

    if (!function_exists('curl_init')) {
        $localAnswer = data_ai_try_local_answer($question, $context, $startedAt, $contextSeconds);
        if ($localAnswer !== null) {
            $emitProgress('concluido', 'Resposta local pronta.', 100);
            return $localAnswer;
        }
        return data_ai_error('php_sem_curl', 'ambiente_php', 'A extensao cURL do PHP nao esta disponivel.');
    }

    $collectedAnswer = data_ai_try_collected_answer($question, $context, $ollamaUrl, $model, $timeout, $numPredict, $startedAt, $contextSeconds, $progress);
    if ($collectedAnswer !== null && !empty($collectedAnswer['ok'])) {
        $emitProgress('concluido', 'Resposta com dados coletados pronta.', 100);
        return $collectedAnswer;
    }

    $dynamicAnswer = data_ai_try_dynamic_answer($question, $context, $ollamaUrl, $model, $timeout, $numPredict, $startedAt, $contextSeconds, $progress);
    if ($dynamicAnswer !== null && !empty($dynamicAnswer['ok'])) {
        $emitProgress('concluido', 'Resposta dinamica pronta.', 100);
        return $dynamicAnswer;
    }

    $localAnswer = data_ai_try_local_answer($question, $context, $startedAt, $contextSeconds);
    if ($localAnswer !== null) {
        $emitProgress('concluido', 'Resposta local pronta.', 100);
        return $localAnswer;
    }
    if ($dynamicAnswer !== null) {
        return $dynamicAnswer;
    }

    $system = "Voce e um analista interno do estudio de tatuagem. Responda em portugues do Brasil, com objetividade e clareza. Use somente os dados fornecidos no JSON de contexto. Priorize o bloco dados_calculados_para_resposta para numeros, totais, listas prioritarias e regras de filtro; ele foi calculado pelo sistema para a pergunta atual. Use o bloco fontes apenas como evidencia complementar. Nao invente numeros, datas, nomes ou conclusoes que nao estejam apoiadas nos dados. Se uma fonte estiver indisponivel ou a pergunta exigir algo fora do contexto, diga exatamente o que faltou. Voce nao executa SQL e nao altera dados; o sistema ja enviou apenas consultas de leitura.";
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
        'think' => false,
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
