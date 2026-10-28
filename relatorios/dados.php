<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

const RELATORIO_TZ = 'America/Sao_Paulo';
const RELATORIO_DIAS_PARADO = 2;

function relatorio_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $local = [];
    $localPath = dirname(__DIR__) . '/projetocrmv2/config.local.php';
    if (is_file($localPath)) {
        $loaded = require $localPath;
        if (is_array($loaded)) {
            $local = $loaded;
        }
    }

    $plataforma = [];
    foreach ([
        dirname(__DIR__) . '/projetocrm/config/database.local.php',
        dirname(__DIR__) . '/projetocrm/config/database.php',
    ] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $loaded = require $path;
        if (is_array($loaded)) {
            $plataforma = $loaded;
            break;
        }
    }

    $host = (string)($local['studio_host'] ?? ($plataforma['host'] ?? (getenv('CRM_DB_HOST') ?: 'localhost')));
    $user = (string)($local['studio_username'] ?? ($plataforma['username'] ?? (getenv('CRM_DB_USER') ?: '')));
    $pass = (string)($local['studio_password'] ?? ($plataforma['password'] ?? (getenv('CRM_DB_PASS') ?: '')));
    $platformDb = (string)($plataforma['database'] ?? 'projetocrm_platform');
    $database = trim((string)($local['studio_database'] ?? ''));

    if ($database === '') {
        try {
            $platform = new PDO(
                "mysql:host={$host};dbname={$platformDb};charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
            );
            $database = (string)($platform->query(
                "SELECT database_name FROM studios WHERE status = 'active' ORDER BY id LIMIT 1"
            )->fetchColumn() ?: '');
        } catch (Throwable $e) {
            error_log('Relatorios: falha ao descobrir banco ativo: ' . $e->getMessage());
        }
    }

    if ($database === '') {
        $database = 'projetocrm_cereja';
    }
    if ($user === '') {
        throw new RuntimeException('Banco do estudio nao configurado.');
    }

    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
}

function relatorio_query(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Relatorios: consulta ignorada: ' . $e->getMessage());
        return [];
    }
}

function relatorio_scalar(PDO $pdo, string $sql, array $params = [], $default = 0)
{
    $rows = relatorio_query($pdo, $sql, $params);
    return $rows ? (array_values($rows[0])[0] ?? $default) : $default;
}

/** @return array<string,mixed> */
function relatorio_periodo(string $mes): array
{
    $mes = preg_replace('/[^0-9-]/', '', $mes) ?? '';
    $tz = new DateTimeZone(RELATORIO_TZ);
    $base = DateTimeImmutable::createFromFormat('!Y-m-d', $mes . '-01', $tz);
    $errors = DateTimeImmutable::getLastErrors();

    if (!$base || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        $base = new DateTimeImmutable('first day of this month', $tz);
    }

    $fim = $base->modify('+1 month');
    $anterior = $base->modify('-1 month');
    $meses = [
           1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    return [
        'month' => $base->format('Y-m'),
        'prev_month' => $anterior->format('Y-m'),
        'start' => $base->format('Y-m-d 00:00:00'),
        'end' => $fim->format('Y-m-d 00:00:00'),
        'start_date' => $base->format('Y-m-d'),
        'end_date' => $fim->format('Y-m-d'),
        'label' => $meses[(int)$base->format('n')] . ' ' . $base->format('Y'),
        'short_label' => mb_substr($meses[(int)$base->format('n')], 0, 3) . '/' . $base->format('y'),
        'days_in_month' => (int)$base->format('t'),
        'is_current' => $base->format('Y-m') === (new DateTimeImmutable('now', $tz))->format('Y-m'),
    ];
}

function relatorio_phone_key(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) > 11) {
        $digits = substr($digits, -11);
    }
    return $digits;
}

function relatorio_pct(int|float $part, int|float $total, int $decimals = 1): float
{
    if ((float)$total <= 0.0) {
        return 0.0;
    }
    return round((((float)$part / (float)$total) * 100), $decimals);
}

function relatorio_median(array $values): ?float
{
    if (!$values) {
        return null;
    }
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = intdiv($count, 2);
    if ($count % 2 === 1) {
        return (float)$values[$middle];
    }
    return ((float)$values[$middle - 1] + (float)$values[$middle]) / 2;
}

function relatorio_percentile(array $values, float $percentile): ?float
{
    if (!$values) {
        return null;
    }
    sort($values, SORT_NUMERIC);
    $index = (int)floor((count($values) - 1) * max(0.0, min(1.0, $percentile)));
    return (float)$values[$index];
}

/** @return array<string,mixed> */
function relatorio_agenda_resumo(PDO $pdo, string $column, string $start, string $end): array
{
    $dateStart = $column === 'appointment_date' ? substr($start, 0, 10) : $start;
    $dateEnd = $column === 'appointment_date' ? substr($end, 0, 10) : $end;
    $rows = relatorio_query(
        $pdo,
        "SELECT status, COUNT(*) AS total, COALESCE(SUM(value), 0) AS valor
         FROM appointments
         WHERE {$column} >= ? AND {$column} < ?
         GROUP BY status",
        [$dateStart, $dateEnd]
    );

    $statuses = [];
    $total = 0;
    $retained = 0;
    $lost = 0;
    $value = 0.0;
    $lostValue = 0.0;
    $retainedValue = 0.0;
    foreach ($rows as $row) {
        $status = (string)$row['status'];
        $count = (int)$row['total'];
        $amount = (float)$row['valor'];
        $isLost = in_array($status, ['cancelado', 'falta'], true);
        $isRetained = in_array($status, ['finalizado', 'confirmado', 'concluido'], true);
        $statuses[] = ['status' => $status, 'total' => $count, 'value' => $amount];
        $total += $count;
        $value += $amount;
        if ($isLost) {
            $lost += $count;
            $lostValue += $amount;
        } elseif ($isRetained) {
            $retained += $count;
            $retainedValue += $amount;
        }
    }

    return [
        'statuses' => $statuses,
        'total' => $total,
        'retained' => $retained,
        'lost' => $lost,
        'value' => round($value, 2),
        'retained_value' => round($retainedValue, 2),
        'lost_value' => round($lostValue, 2),
        'retained_pct' => relatorio_pct($retained, $total),
    ];
}

/** @return array<string,mixed> */
function relatorio_carregar(string $mes): array
{
    $pdo = relatorio_db();
    $periodo = relatorio_periodo($mes);
    $start = $periodo['start'];
    $end = $periodo['end'];
    $now = new DateTimeImmutable('now', new DateTimeZone(RELATORIO_TZ));

    $conversas = relatorio_query(
        $pdo,
        "SELECT c.id, c.phone, c.lead_id, c.customer_id, c.last_message_direction,
                c.last_message_at, c.created_at, c.attendance_mode,
                l.status AS lead_status, l.pipeline_stage, l.source AS lead_source,
                l.created_at AS lead_created_at
         FROM whatsapp_conversations c
         LEFT JOIN leads l ON l.id = c.lead_id
         WHERE c.merged_into_id IS NULL
           AND c.last_message_at >= ? AND c.last_message_at < ?
         ORDER BY c.last_message_at DESC",
        [$start, $end]
    );

    $convIds = array_map(static fn(array $row): int => (int)$row['id'], $conversas);
    $convMap = [];
    foreach ($conversas as $row) {
        $convMap[(int)$row['id']] = $row;
    }

    $empty = [
        'meta' => [
            'period' => $periodo,
            'generated_at' => $now->format('Y-m-d H:i:s'),
            'generated_label' => $now->format('d/m/Y \a\s H:i'),
            'read_only' => true,
        ],
        'overview' => [
            'contacts' => 0, 'new_contacts' => 0, 'returning_contacts' => 0,
            'messages' => 0, 'inbound' => 0, 'outbound' => 0,
            'with_lead' => 0, 'without_lead' => 0, 'lead_coverage_pct' => 0.0,
            'new_leads' => 0, 'new_customers' => 0,
            'waiting_studio' => 0, 'waiting_leads' => 0,
            'stale' => 0, 'stale_last_studio' => 0, 'stale_applicable' => $periodo['is_current'],
            'first_response_median' => null, 'first_response_p90' => null,
            'response_over_1h_pct' => 0.0, 'response_over_24h_pct' => 0.0,
            'response_turns' => 0, 'no_response' => 0,
            'quote_conversations' => 0, 'price_conversations' => 0,
            'positive_conv' => 0, 'positive_conv_pct' => 0.0,
            'new_positive_conv' => 0, 'new_positive_conv_pct' => 0.0,
            'acquisition_contacts' => 0, 'acquisition_positive_conv' => 0,
            'acquisition_positive_conv_pct' => 0.0, 'agenda_imported_contacts' => 0,
            'crm_closed' => 0, 'crm_closed_pct' => 0.0,
            'ai_analyzed' => 0, 'ai_closed' => 0, 'ai_closed_pct' => 0.0,
        ],
        'daily' => [], 'channels' => [], 'pipeline' => [], 'objections' => [],
        'response' => [], 'agenda' => [], 'insights' => [], 'priorities' => [],
        'data_quality' => [],
    ];

    if (!$convIds) {
        return $empty;
    }

    $withLead = 0;
    $waitingStudio = 0;
    $waitingLeads = 0;
    $stale = 0;
    $staleLastStudio = 0;
    $crmClosed = 0;
    $statusCounts = [];
    $sourceCounts = [];
    $acquisitionLeadContacts = 0;
    $agendaImportedContacts = 0;
    $staleLimit = $now->modify('-' . RELATORIO_DIAS_PARADO . ' days')->getTimestamp();

    foreach ($conversas as $row) {
        $hasLead = $row['lead_id'] !== null && (int)$row['lead_id'] > 0;
        if ($hasLead) {
            $withLead++;
        }
        if (($row['last_message_direction'] ?? '') === 'in') {
            $waitingStudio++;
            if ($hasLead) {
                $waitingLeads++;
            }
        }
        if ($hasLead && $periodo['is_current'] && strtotime((string)$row['last_message_at']) < $staleLimit) {
            $stale++;
            if (($row['last_message_direction'] ?? '') === 'out') {
                $staleLastStudio++;
            }
        }

        $status = $hasLead ? (string)($row['lead_status'] ?: 'sem_etapa') : 'sem_lead';
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        if ($status === 'fechado') {
            $crmClosed++;
        }

        $source = $hasLead ? strtolower(trim((string)($row['lead_source'] ?? ''))) : 'sem_lead';
        $sourceKey = match ($source) {
            'meta', 'facebook', 'instagram_ads' => 'meta',
            'google' => 'google',
            'instagram' => 'instagram',
            'whatsapp' => 'whatsapp',
            'google agenda' => 'google_agenda',
            'sem_lead' => 'sem_lead',
            '' => $hasLead ? 'outros' : 'sem_lead',
            default => 'outros',
        };
        $sourceCounts[$sourceKey] = ($sourceCounts[$sourceKey] ?? 0) + 1;
        if ($sourceKey === 'google_agenda') {
            $agendaImportedContacts++;
        } elseif ($hasLead) {
            $acquisitionLeadContacts++;
        }
    }

    $firstRows = relatorio_query(
        $pdo,
        "SELECT c.id, MIN(COALESCE(m.sent_at, m.created_at)) AS first_at
         FROM whatsapp_conversations c
         JOIN whatsapp_messages m ON m.conversation_id = c.id
         WHERE c.id IN (" . implode(',', array_fill(0, count($convIds), '?')) . ")
         GROUP BY c.id",
        $convIds
    );
    $firstMap = [];
    foreach ($firstRows as $row) {
        $firstMap[(int)$row['id']] = (string)$row['first_at'];
    }
    $newContacts = 0;
    foreach ($convIds as $id) {
        if (!empty($firstMap[$id]) && $firstMap[$id] >= $start && $firstMap[$id] < $end) {
            $newContacts++;
        }
    }

    $messageRows = relatorio_query(
        $pdo,
        "SELECT m.conversation_id, m.from_me, m.body, m.transcricao,
                COALESCE(m.sent_at, m.created_at) AS sent_at,
                DATE(COALESCE(m.sent_at, m.created_at)) AS day
         FROM whatsapp_messages m
         WHERE m.conversation_id IN (" . implode(',', array_fill(0, count($convIds), '?')) . ")
           AND COALESCE(m.sent_at, m.created_at) >= ?
           AND COALESCE(m.sent_at, m.created_at) < ?
         ORDER BY m.conversation_id, COALESCE(m.sent_at, m.created_at), m.id",
        array_merge($convIds, [$start, $end])
    );

    $daily = [];
    $baseDate = new DateTimeImmutable($periodo['start_date'], new DateTimeZone(RELATORIO_TZ));
    $lastDay = $periodo['is_current'] ? min($periodo['days_in_month'], (int)$now->format('j')) : $periodo['days_in_month'];
    for ($day = 1; $day <= $lastDay; $day++) {
        $date = $baseDate->setDate((int)$baseDate->format('Y'), (int)$baseDate->format('n'), $day)->format('Y-m-d');
        $daily[$date] = [
            'date' => $date,
            'label' => $baseDate->setDate((int)$baseDate->format('Y'), (int)$baseDate->format('n'), $day)->format('d/m'),
            'messages' => 0, 'inbound' => 0, 'outbound' => 0, 'conversations' => 0,
        ];
    }

    $messageTotal = count($messageRows);
    $inbound = 0;
    $outbound = 0;
    foreach ($messageRows as $row) {
        $isOut = (int)$row['from_me'] === 1;
        if ($isOut) {
            $outbound++;
        } else {
            $inbound++;
        }
        $day = (string)$row['day'];
        if (isset($daily[$day])) {
            $daily[$day]['messages']++;
            $daily[$day][$isOut ? 'outbound' : 'inbound']++;
        }
    }
    foreach ($conversas as $row) {
        $createdDate = substr((string)$row['created_at'], 0, 10);
        if (isset($daily[$createdDate])) {
            $daily[$createdDate]['conversations']++;
        }
    }

    $responseBuckets = [
        'ate_5' => 0, 'ate_15' => 0, 'ate_1h' => 0,
        'ate_4h' => 0, 'ate_24h' => 0, 'acima_24h' => 0,
    ];
    $turnDurations = [];
    $firstAt = [];
    $pendingAt = [];
    $responseTurns = 0;
    $noResponse = 0;
    $quoteConversations = [];
    $objections = [
        'price' => ['label' => 'Preço/orçamento', 'ids' => [], 'messages' => 0],
        'technical' => ['label' => 'Dúvida técnica', 'ids' => [], 'messages' => 0],
        'schedule' => ['label' => 'Horário/agenda', 'ids' => [], 'messages' => 0],
        'location' => ['label' => 'Localização', 'ids' => [], 'messages' => 0],
        'time' => ['label' => 'Trabalho/agenda', 'ids' => [], 'messages' => 0],
        'health' => ['label' => 'Dor/saúde', 'ids' => [], 'messages' => 0],
        'indecision' => ['label' => 'Indecisão/retorno', 'ids' => [], 'messages' => 0],
        'price_objection' => ['label' => 'Recusa por preço', 'ids' => [], 'messages' => 0],
    ];
    $patterns = [
        'price' => '/(pre[cç]o|valor|quanto|or[cç]amento|custa|custaria|investimento|tabela)/iu',
        'technical' => '/(desenho|refer[eê]ncia|arte|tamanho|colorid|realismo|fineline|tra[cç]o|sombra|como funciona)/iu',
        'schedule' => '/(hor[aá]rio|agenda|vaga|dispon[ií]vel|que dia|qual dia|marcar|agendar)/iu',
        'location' => '/(onde fica|endere[cç]o|localiza|como chego|como chegar|esta[cç][aã]o|metr[oô]|trem|[oô]nibus|longe|bairro)/iu',
        'time' => '/(n[aã]o consigo|trabalho|trabalhar|folga|depois do trabalho|muito corrido)/iu',
        'health' => '/(d[oó]i|dor|medo|alerg|cicatri|gr[aá]vida|gestante|amament|rem[eé]dio|anticoagul|diabet|infec[cç]|dermatite|queloide)/iu',
        'indecision' => '/(vou pensar|pensar|falar com|ver com|semana que vem|m[eê]s que vem|talvez|ainda n[aã]o|agora n[aã]o|mais pra frente|te aviso)/iu',
        'price_objection' => '/(muito caro|fora do or[cç]amento|sem condi[cç][oõ]es|n[aã]o tenho dinheiro|n[aã]o cabe|muito alto|ta caro|est[aá] caro|n[aã]o consigo pagar|desconto|abaixa)/iu',
    ];

    foreach ($messageRows as $row) {
        $convId = (int)$row['conversation_id'];
        $hasLead = ($convMap[$convId]['lead_id'] ?? null) !== null;
        if (!$hasLead) {
            continue;
        }
        $isOut = (int)$row['from_me'] === 1;
        $sentAt = strtotime((string)$row['sent_at']);
        $text = trim((string)(($row['body'] ?? '') !== '' ? $row['body'] : ($row['transcricao'] ?? '')));
        if ($isOut) {
            if (isset($pendingAt[$convId])) {
                $duration = max(0, $sentAt - $pendingAt[$convId]);
                $turnDurations[] = $duration;
                $responseTurns++;
                if (!isset($firstAt[$convId])) {
                    $firstAt[$convId] = $duration;
                }
                if ($duration <= 300) {
                    $responseBuckets['ate_5']++;
                } elseif ($duration <= 900) {
                    $responseBuckets['ate_15']++;
                } elseif ($duration <= 3600) {
                    $responseBuckets['ate_1h']++;
                } elseif ($duration <= 14400) {
                    $responseBuckets['ate_4h']++;
                } elseif ($duration <= 86400) {
                    $responseBuckets['ate_24h']++;
                } else {
                    $responseBuckets['acima_24h']++;
                }
                unset($pendingAt[$convId]);
            }
            if ($text !== '' && preg_match('/(R\\$|reais|fica R)/iu', $text)) {
                $quoteConversations[$convId] = true;
            }
            continue;
        }

        if (!isset($pendingAt[$convId])) {
            $pendingAt[$convId] = $sentAt;
        }
        if ($text === '') {
            continue;
        }
        $leadId = $convMap[$convId]['lead_id'] ?? null;
        if ($leadId === null || (int)$leadId <= 0) {
            continue;
        }
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                $objections[$key]['ids'][$convId] = true;
                $objections[$key]['messages']++;
            }
        }
    }
    foreach ($pendingAt as $convId => $unused) {
        if (($convMap[$convId]['last_message_direction'] ?? '') === 'in') {
            $noResponse++;
        }
    }

    $leads = relatorio_query($pdo, 'SELECT id, phone, customer_id FROM leads');
    $customers = relatorio_query($pdo, 'SELECT id, phone FROM customers');
    $positiveAppointments = relatorio_query(
        $pdo,
        "SELECT id, lead_id, customer_id, status, value, created_at
         FROM appointments
         WHERE status IN ('finalizado', 'confirmado', 'concluido')
           AND (lead_id IS NOT NULL OR customer_id IS NOT NULL)"
    );

    $leadsByPhone = [];
    $leadCustomer = [];
    foreach ($leads as $lead) {
        $key = relatorio_phone_key((string)$lead['phone']);
        if ($key !== '') {
            $leadsByPhone[$key][] = $lead;
        }
        if ($lead['customer_id'] !== null) {
            $leadCustomer[(int)$lead['id']] = (int)$lead['customer_id'];
        }
    }
    $customersByPhone = [];
    foreach ($customers as $customer) {
        $key = relatorio_phone_key((string)$customer['phone']);
        if ($key !== '') {
            $customersByPhone[$key][] = (int)$customer['id'];
        }
    }
    $appointmentsByLead = [];
    $appointmentsByCustomer = [];
    foreach ($positiveAppointments as $appointment) {
        if ($appointment['lead_id'] !== null) {
            $appointmentsByLead[(int)$appointment['lead_id']][] = $appointment;
        }
        if ($appointment['customer_id'] !== null) {
            $appointmentsByCustomer[(int)$appointment['customer_id']][] = $appointment;
        }
    }

    $positiveConv = 0;
    $newPositiveConv = 0;
    $acquisitionPositiveConv = 0;
    $positiveBySource = [];
    $customerDirectLinks = 0;
    foreach ($conversas as $row) {
        $convId = (int)$row['id'];
        if (($row['customer_id'] ?? null) !== null) {
            $customerDirectLinks++;
        }

        $leadIds = [];
        $customerIds = [];
        if (($row['lead_id'] ?? null) !== null) {
            $leadIds[(int)$row['lead_id']] = true;
        }
        if (($row['customer_id'] ?? null) !== null) {
            $customerIds[(int)$row['customer_id']] = true;
        }
        $phoneKey = relatorio_phone_key((string)$row['phone']);
        foreach ($phoneKey !== '' ? ($leadsByPhone[$phoneKey] ?? []) : [] as $lead) {
            $leadIds[(int)$lead['id']] = true;
        }
        foreach (array_keys($leadIds) as $leadId) {
            if (isset($leadCustomer[$leadId])) {
                $customerIds[$leadCustomer[$leadId]] = true;
            }
        }
        foreach ($phoneKey !== '' ? ($customersByPhone[$phoneKey] ?? []) : [] as $customerId) {
            $customerIds[$customerId] = true;
        }

        $matches = [];
        foreach (array_keys($leadIds) as $leadId) {
            foreach ($appointmentsByLead[$leadId] ?? [] as $appointment) {
                $matches[(int)$appointment['id']] = $appointment;
            }
        }
        foreach (array_keys($customerIds) as $customerId) {
            foreach ($appointmentsByCustomer[$customerId] ?? [] as $appointment) {
                $matches[(int)$appointment['id']] = $appointment;
            }
        }
        if (!$matches) {
            continue;
        }

        $positiveConv++;
        $hasNew = false;
        foreach ($matches as $appointment) {
            $createdAt = (string)($appointment['created_at'] ?? '');
            if ($createdAt >= $start && $createdAt < $end) {
                $hasNew = true;
                break;
            }
        }
        if ($hasNew) {
            $newPositiveConv++;
        }

        $source = $row['lead_id'] === null ? 'sem_lead' : strtolower(trim((string)($row['lead_source'] ?? '')));
        $sourceKey = match ($source) {
            'meta', 'facebook', 'instagram_ads' => 'meta',
            'google' => 'google',
            'instagram' => 'instagram',
            'whatsapp' => 'whatsapp',
            'google agenda' => 'google_agenda',
            '' => 'outros',
            default => 'outros',
        };
        $positiveBySource[$sourceKey] = ($positiveBySource[$sourceKey] ?? 0) + 1;
        if ($sourceKey !== 'google_agenda' && $sourceKey !== 'sem_lead') {
            $acquisitionPositiveConv++;
        }
    }

    $agendaDate = relatorio_agenda_resumo($pdo, 'appointment_date', $start, $end);
    $agendaCreated = relatorio_agenda_resumo($pdo, 'created_at', $start, $end);
    $newLeads = (int)relatorio_scalar($pdo, 'SELECT COUNT(*) FROM leads WHERE created_at >= ? AND created_at < ?', [$start, $end]);
    $newCustomers = (int)relatorio_scalar($pdo, 'SELECT COUNT(*) FROM customers WHERE created_at >= ? AND created_at < ?', [$start, $end]);
    $ai = relatorio_query(
        $pdo,
        "SELECT COUNT(*) AS analisadas, COALESCE(SUM(v.fechou = 1), 0) AS fechadas,
                COALESCE(SUM(CASE WHEN v.fechou = 1 THEN v.valor ELSE 0 END), 0) AS valor
         FROM conversa_venda v
         JOIN whatsapp_conversations c ON c.id = v.conversation_id
         WHERE c.merged_into_id IS NULL AND c.last_message_at >= ? AND c.last_message_at < ?",
        [$start, $end]
    )[0] ?? ['analisadas' => 0, 'fechadas' => 0, 'valor' => 0];

    $sourceLabels = [
        'meta' => 'Meta Ads', 'google' => 'Google', 'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp', 'google_agenda' => 'Google Agenda',
        'outros' => 'Outros', 'sem_lead' => 'Sem lead',
    ];
    $channels = [];
    foreach ($sourceCounts as $key => $count) {
        if ($key === 'google_agenda' || $key === 'sem_lead') {
            continue;
        }
        $channels[] = [
            'key' => $key,
            'label' => $sourceLabels[$key] ?? ucfirst($key),
            'conversations' => $count,
            'positive' => (int)($positiveBySource[$key] ?? 0),
            'conversion_pct' => relatorio_pct((int)($positiveBySource[$key] ?? 0), $count),
        ];
    }
    usort($channels, static fn(array $a, array $b): int => $b['conversations'] <=> $a['conversations']);

    $pipelineLabels = [
        'novo' => 'Novo', 'agendado' => 'Agendado', 'fechado' => 'Fechado',
        'pre_agendado' => 'Pré-agendado', 'perdido' => 'Perdido',
        'sem_etapa' => 'Sem etapa', 'sem_lead' => 'Sem lead',
    ];
    $pipeline = [];
    foreach ($pipelineLabels as $key => $label) {
        if (isset($statusCounts[$key])) {
            $pipeline[] = ['key' => $key, 'label' => $label, 'total' => $statusCounts[$key]];
        }
    }

    $objectionRows = [];
    foreach ($objections as $key => $item) {
        $objectionRows[] = [
            'key' => $key,
            'label' => $item['label'],
            'conversations' => count($item['ids']),
            'messages' => $item['messages'],
            'pct' => relatorio_pct(count($item['ids']), $withLead),
        ];
    }
    usort($objectionRows, static fn(array $a, array $b): int => $b['conversations'] <=> $a['conversations']);

    $responseTotal = array_sum($responseBuckets);
    $response = [
        ['key' => 'ate_5', 'label' => 'Até 5 min', 'total' => $responseBuckets['ate_5']],
        ['key' => 'ate_15', 'label' => '5-15 min', 'total' => $responseBuckets['ate_15']],
        ['key' => 'ate_1h', 'label' => '15-60 min', 'total' => $responseBuckets['ate_1h']],
        ['key' => 'ate_4h', 'label' => '1-4 h', 'total' => $responseBuckets['ate_4h']],
        ['key' => 'ate_24h', 'label' => '4-24 h', 'total' => $responseBuckets['ate_24h']],
        ['key' => 'acima_24h', 'label' => '24h+', 'total' => $responseBuckets['acima_24h']],
    ];
    foreach ($response as &$bucket) {
        $bucket['pct'] = relatorio_pct($bucket['total'], $responseTotal);
    }
    unset($bucket);

    $firstMedian = relatorio_median($firstAt);
    $firstP90 = relatorio_percentile($firstAt, 0.9);
    $overOneHour = 0;
    $overOneDay = 0;
    foreach ($turnDurations as $duration) {
        if ($duration > 3600) {
            $overOneHour++;
        }
        if ($duration > 86400) {
            $overOneDay++;
        }
    }

    $overview = [
        'contacts' => count($conversas),
        'new_contacts' => $newContacts,
        'returning_contacts' => max(0, count($conversas) - $newContacts),
        'messages' => $messageTotal,
        'inbound' => $inbound,
        'outbound' => $outbound,
        'with_lead' => $withLead,
        'without_lead' => max(0, count($conversas) - $withLead),
        'lead_coverage_pct' => relatorio_pct($withLead, count($conversas)),
        'new_leads' => $newLeads,
        'new_customers' => $newCustomers,
        'waiting_studio' => $waitingStudio,
        'waiting_leads' => $waitingLeads,
        'stale' => $stale,
        'stale_last_studio' => $staleLastStudio,
        'stale_applicable' => $periodo['is_current'],
        'first_response_median' => $firstMedian !== null ? (int)round($firstMedian) : null,
        'first_response_p90' => $firstP90 !== null ? (int)round($firstP90) : null,
        'response_over_1h_pct' => relatorio_pct($overOneHour, $responseTotal),
        'response_over_24h_pct' => relatorio_pct($overOneDay, $responseTotal),
        'response_turns' => $responseTotal,
        'no_response' => $noResponse,
        'quote_conversations' => count($quoteConversations),
        'price_conversations' => count($objections['price']['ids']),
        'positive_conv' => $positiveConv,
        'positive_conv_pct' => relatorio_pct($positiveConv, $withLead),
        'acquisition_contacts' => $acquisitionLeadContacts,
        'acquisition_positive_conv' => $acquisitionPositiveConv,
        'acquisition_positive_conv_pct' => relatorio_pct($acquisitionPositiveConv, $acquisitionLeadContacts),
        'agenda_imported_contacts' => $agendaImportedContacts,
        'new_positive_conv' => $newPositiveConv,
        'new_positive_conv_pct' => relatorio_pct($newPositiveConv, $withLead),
        'crm_closed' => $crmClosed,
        'crm_closed_pct' => relatorio_pct($crmClosed, $withLead),
        'ai_analyzed' => (int)$ai['analisadas'],
        'ai_closed' => (int)$ai['fechadas'],
        'ai_closed_pct' => relatorio_pct((int)$ai['fechadas'], (int)$ai['analisadas']),
    ];

    $insights = [];
    if ($stale > 0) {
        $insights[] = [
            'tone' => 'critical', 'icon' => 'fa-hourglass-half',
            'title' => $stale . ' leads parados',
            'text' => 'Sem movimento há mais de ' . RELATORIO_DIAS_PARADO . ' dias. ' . $staleLastStudio . ' terminaram com a última mensagem do estúdio.',
        ];
    }
    if ($waitingLeads > 0) {
        $insights[] = [
            'tone' => 'warning', 'icon' => 'fa-headset',
            'title' => $waitingLeads . ' esperando resposta',
            'text' => 'Conversas qualificadas em que a ultima mensagem veio do cliente.',
        ];
    }
    if ($overview['first_response_median'] !== null) {
        $responseMinutes = max(1, (int)round(((int)$overview['first_response_median']) / 60));
        $responseHours = intdiv($responseMinutes, 60);
        $responseRest = $responseMinutes % 60;
        $responseLabel = $responseHours > 0
            ? $responseHours . 'h' . ($responseRest > 0 ? ' ' . $responseRest . 'min' : '')
            : $responseMinutes . ' min';
        $insights[] = [
            'tone' => $overview['first_response_median'] > 600 ? 'warning' : 'good',
            'icon' => 'fa-stopwatch',
            'title' => 'Primeira resposta: ' . $responseLabel,
            'text' => 'Tempo mediano por conversa. ' . number_format($overview['response_over_1h_pct'], 1, ',', '.') . '% das respostas levam mais de 1 hora.',
        ];
    }
    if ($overview['price_conversations'] >= 20 && count($objections['price_objection']['ids']) < max(3, (int)round($overview['price_conversations'] * 0.05))) {
        $insights[] = [
            'tone' => 'info', 'icon' => 'fa-tags',
            'title' => 'Preço é dúvida, não recusa',
            'text' => $overview['price_conversations'] . ' conversas perguntaram valor, mas apenas ' . count($objections['price_objection']['ids']) . ' recusaram explicitamente por preço.',
        ];
    }
    if ($agendaDate['lost'] > 0) {
        $insights[] = [
            'tone' => 'warning', 'icon' => 'fa-calendar-xmark',
            'title' => $agendaDate['lost'] . ' perdas na agenda',
            'text' => 'R$ ' . number_format($agendaDate['lost_value'], 0, ',', '.') . ' em valor de sessões canceladas ou faltas.',
        ];
    }
    if ($overview['without_lead'] > 0) {
        $insights[] = [
            'tone' => 'critical', 'icon' => 'fa-link-slash',
            'title' => $overview['without_lead'] . ' contatos sem lead',
            'text' => 'A cobertura do CRM esta em ' . number_format($overview['lead_coverage_pct'], 1, ',', '.') . '%. Sem vinculo, o funil e o follow-up ficam incompletos.',
        ];
    }

    $priorities = [
        [
            'number' => '01', 'title' => 'Zerar a fila de espera',
            'text' => $waitingLeads . ' conversas qualificadas aguardam o estúdio. Meta: responder em até 10 minutos.',
            'metric' => $waitingLeads,
        ],
        [
            'number' => '02', 'title' => 'Retomar os parados',
            'text' => $stale . ' leads sem movimento há 48h. Priorize os que já receberam orçamento.',
            'metric' => $stale,
        ],
        [
            'number' => '03', 'title' => 'Fechar depois do orçamento',
            'text' => $overview['quote_conversations'] . ' conversas tiveram valor enviado. Crie retorno em D0, D+1 e D+3.',
            'metric' => $overview['quote_conversations'],
        ],
        [
            'number' => '04', 'title' => 'Reduzir faltas',
            'text' => $agendaDate['lost'] . ' sessões foram perdidas neste mês. Confirme 24h antes e peça sinal.',
            'metric' => $agendaDate['lost'],
        ],
    ];

    return [
        'meta' => [
            'period' => $periodo,
            'generated_at' => $now->format('Y-m-d H:i:s'),
            'generated_label' => $now->format('d/m/Y \a\s H:i'),
            'read_only' => true,
        ],
        'overview' => $overview,
        'daily' => array_values($daily),
        'channels' => $channels,
        'pipeline' => $pipeline,
        'objections' => $objectionRows,
        'response' => $response,
        'agenda' => ['date' => $agendaDate, 'created' => $agendaCreated],
        'insights' => $insights,
        'priorities' => $priorities,
        'data_quality' => [
            'lead_coverage_pct' => $overview['lead_coverage_pct'],
            'direct_customer_links' => $customerDirectLinks,
            'crm_closed' => $crmClosed,
            'ai_closed' => (int)$ai['fechadas'],
            'ai_analyzed' => (int)$ai['analisadas'],
            'positive_conv' => $positiveConv,
            'agenda_imported_contacts' => $agendaImportedContacts,
        ],
    ];
}

/** @return array<string,mixed> */
function relatorio_delta(int|float|null $current, int|float|null $previous, bool $points = false): array
{
    if ($current === null || $previous === null) {
        return ['value' => null, 'direction' => 'flat'];
    }
    $diff = (float)$current - (float)$previous;
    if ($points) {
        return [
            'value' => round($diff, 1),
            'direction' => $diff > 0.05 ? 'up' : ($diff < -0.05 ? 'down' : 'flat'),
        ];
    }
    return [
        'value' => (float)$previous > 0 ? round(($diff / (float)$previous) * 100, 1) : null,
        'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
    ];
}







