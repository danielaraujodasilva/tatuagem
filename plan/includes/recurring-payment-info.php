<?php
declare(strict_types=1);

// Codigos de pagamento das contas fixas.
//
// Os boletos por e-mail continuam sendo coletados por
// scripts/coletar-boletos-email.php. Aqui ficam os codigos que ja apareceram
// nos lancamentos (PIX fixo, PIX copia e cola, boleto de sistema/WhatsApp) para
// que a tela de Contas fixas nao fique vazia nas contas que nao enviam e-mail.

function recurring_payment_info_column_exists(string $column): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recurring_rules' AND COLUMN_NAME = ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetchColumn();
}

function ensure_recurring_payment_info_schema(): void
{
    if (!recurring_payment_info_column_exists('boleto_codigo')) {
        db()->exec('ALTER TABLE recurring_rules ADD COLUMN boleto_codigo TEXT NULL AFTER next_due_date');
    } else {
        $stmt = db()->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recurring_rules' AND COLUMN_NAME = 'boleto_codigo' LIMIT 1");
        $tipo = strtolower((string)$stmt->fetchColumn());
        // PIX copia e cola passa de 200 caracteres; a coluna antiga era VARCHAR(120).
        if ($tipo !== '' && !in_array($tipo, ['text', 'mediumtext', 'longtext'], true)) {
            db()->exec('ALTER TABLE recurring_rules MODIFY COLUMN boleto_codigo TEXT NULL');
        }
    }

    if (!recurring_payment_info_column_exists('boleto_arquivo')) {
        db()->exec('ALTER TABLE recurring_rules ADD COLUMN boleto_arquivo VARCHAR(180) NULL AFTER boleto_codigo');
    }
    if (!recurring_payment_info_column_exists('boleto_fonte')) {
        db()->exec('ALTER TABLE recurring_rules ADD COLUMN boleto_fonte VARCHAR(40) NULL AFTER boleto_arquivo');
    }
    if (!recurring_payment_info_column_exists('boleto_atualizado_em')) {
        db()->exec('ALTER TABLE recurring_rules ADD COLUMN boleto_atualizado_em DATETIME NULL AFTER boleto_fonte');
    }
}

// Extrai um codigo reutilizavel de pagamento. Devolve null para textos que nao
// servem para pagar (ex.: "pix", "debito automatico", "sem conta ainda").
function recurring_payment_info_extract(string $raw): ?array
{
    $texto = trim((string)(preg_replace('/\s+/u', ' ', $raw) ?? $raw));
    if ($texto === '') {
        return null;
    }

    if (preg_match('/^pix(?![@\w])/iu', $texto)) {
        $texto = trim((string)(preg_replace(
            '/^pix\s*(?:copia\s*(?:e\s*)?cola|copia\s*cola|c&p|cp)?\s*[:\-]?\s*/iu',
            '',
            $texto
        ) ?? $texto));
    }
    $texto = trim((string)(preg_replace(
        '/^(?:c[oó]digo(?:\s+de\s+barras)?|linha\s+digit[aá]vel|boleto)\s*[:\-]?\s*/iu',
        '',
        $texto
    ) ?? $texto));
    if ($texto === '') {
        return null;
    }

    $comparavel = strtolower(trim((string)(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto)));
    $comparavel = trim((string)(preg_replace('/\s+/', ' ', $comparavel) ?? $comparavel));
    foreach ([
        'pix',
        'pix copia e cola',
        'pix copia cola',
        'ao vivo',
        'ao vivo la',
        'debito automatico',
        'credito automatico',
        'boleto gaveta',
        'pago com boleto',
        'cred aprox',
    ] as $ignorar) {
        if ($comparavel === $ignorar) {
            return null;
        }
    }
    foreach (['sem conta', 'pagar no app', 'debito automatico'] as $prefixo) {
        if (str_starts_with($comparavel, $prefixo)) {
            return null;
        }
    }

    // PIX copia e cola (BR Code). Pode passar de 200 caracteres e, as vezes,
    // vem com espacos na cidade do recebedor.
    $inicioPix = strpos($texto, '000201');
    if ($inicioPix !== false) {
        $codigo = preg_replace('/\s+/', '', substr($texto, $inicioPix)) ?? '';
        if (strlen($codigo) >= 60) {
            return ['codigo' => $codigo, 'tipo' => 'pix'];
        }
    }

    // Linha digitavel de boleto (44 a 48 digitos).
    $digitos = preg_replace('/\D+/', '', $texto) ?? '';
    if (strlen($digitos) >= 44 && strlen($digitos) <= 48 && preg_match('/^[\d.\s\-]+$/', $texto)) {
        return ['codigo' => $digitos, 'tipo' => 'boleto'];
    }

    // Chave PIX e-mail.
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $texto, $achado)) {
        return ['codigo' => strtolower($achado[0]), 'tipo' => 'pix'];
    }

    // Chave PIX CNPJ/CPF.
    if (preg_match('/\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/', $texto, $achado)) {
        return ['codigo' => $achado[0], 'tipo' => 'pix'];
    }
    if (preg_match('/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/', $texto, $achado)) {
        return ['codigo' => $achado[0], 'tipo' => 'pix'];
    }
    if (preg_match('/\b\d{14}\b/', $texto, $achado)) {
        return ['codigo' => $achado[0], 'tipo' => 'pix'];
    }

    // Chave PIX aleatoria.
    if (preg_match('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', $texto, $achado)) {
        return ['codigo' => $achado[0], 'tipo' => 'pix'];
    }

    // Chave PIX telefone. Quando o campo so tem numeros/separadores, usa o
    // numero inteiro (o regex abaixo poderia cortar o ultimo digito).
    if (preg_match('/^[\d.\s\-\/()]+$/', $texto)) {
        $digitos = preg_replace('/\D+/', '', $texto) ?? '';
        if (strlen($digitos) >= 10 && strlen($digitos) <= 13) {
            return ['codigo' => $digitos, 'tipo' => 'pix'];
        }
    }
    if (preg_match('/(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?9?\d{4}[\s\-]?\d{4}/', $texto, $achado)) {
        $telefone = preg_replace('/\D+/', '', $achado[0]) ?? '';
        if (strlen($telefone) >= 10 && strlen($telefone) <= 13) {
            return ['codigo' => $telefone, 'tipo' => 'pix'];
        }
    }

    return null;
}

function recurring_payment_info_aliases(): array
{
    return [
        'sabesp' => ['agua estudio', 'agua'],
        'fausp' => ['faculdade', 'faculdade cod barra', 'faculdade fran'],
        'aluguel estudio' => ['aluguel cantende erika cpf joaquim'],
        'terapia luna' => ['mariana terapia'],
        'cartao credito' => ['cartao pag seguro'],
        'convenio amil' => ['convenio dentista', 'convenio dente cod barra'],
        'enel estudio' => ['enel tatuagem'],
    ];
}

function recurring_payment_info_normalize(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ]);
    return trim((string)(preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? $texto));
}

function recurring_payment_info_tokens(string $texto): array
{
    $tokens = preg_split('/\s+/', recurring_payment_info_normalize($texto)) ?: [];
    return array_values(array_unique(array_filter($tokens, fn(string $token): bool => strlen($token) >= 4)));
}

function recurring_payment_info_score(array $rule, array $transacao, array $matchers): int
{
    $ruleText = recurring_payment_info_normalize((string)$rule['description']);
    $txText = recurring_payment_info_normalize((string)$transacao['description']);
    $score = 0;

    if ($ruleText === $txText) {
        $score += 100;
    } elseif ($ruleText !== '' && $txText !== '' && (str_contains($ruleText, $txText) || str_contains($txText, $ruleText))) {
        $score += 40;
    }

    foreach (recurring_payment_info_aliases()[$ruleText] ?? [] as $alias) {
        if ($txText === $alias) {
            $score += 80;
            break;
        }
    }

    foreach (recurring_payment_info_tokens((string)$rule['description']) as $token) {
        if (str_contains($txText, $token)) {
            $score += 10;
        }
    }
    foreach ($matchers as $matcher) {
        foreach (recurring_payment_info_tokens((string)$matcher['match_key']) as $token) {
            if (str_contains($txText, $token)) {
                $score += 5;
            }
        }
    }

    $ruleAmount = (float)$rule['amount'];
    $txAmount = (float)$transacao['amount'];
    $diferenca = abs($ruleAmount - $txAmount);
    if ($diferenca <= 0.011) {
        $score += 50;
    } elseif ($diferenca <= max(1.0, $ruleAmount * 0.01)) {
        $score += 20;
    }

    return $score;
}

function recurring_payment_info_locked(array $rule): bool
{
    $codigo = trim((string)($rule['boleto_codigo'] ?? ''));
    if ($codigo === '') {
        return false;
    }
    $fonte = strtolower(trim((string)($rule['boleto_fonte'] ?? '')));
    // So o historico pode ser atualizado automaticamente. Codigo de e-mail,
    // manual ou de origem desconhecida fica preservado.
    return $fonte !== 'historico';
}

function sync_recurring_payment_info(?int $onlyRuleId = null): array
{
    ensure_recurring_payment_info_schema();

    $sql = 'SELECT id, description, amount, category_id, next_due_date, boleto_codigo, boleto_arquivo, boleto_fonte, boleto_atualizado_em
        FROM recurring_rules WHERE is_active = 1';
    $params = [];
    if ($onlyRuleId !== null) {
        $sql .= ' AND id = ?';
        $params[] = $onlyRuleId;
    }
    $stmt = db()->prepare($sql . ' ORDER BY id');
    $stmt->execute($params);
    $rules = $stmt->fetchAll();
    if (!$rules) {
        return ['updated' => 0, 'assigned' => 0];
    }

    $matchers = [];
    foreach (db()->query('SELECT rule_id, match_key FROM recurring_rule_matchers ORDER BY id') as $matcher) {
        $matchers[(int)$matcher['rule_id']][] = $matcher;
    }

    $transactions = [];
    foreach (db()->query("SELECT id, description, amount, due_date, payment_code
        FROM transactions
        WHERE payment_code IS NOT NULL AND payment_code <> ''
          AND COALESCE(due_date, '1000-01-01') >= DATE_SUB(CURDATE(), INTERVAL 13 MONTH)
        ORDER BY COALESCE(due_date, '1000-01-01') DESC, id DESC") as $row) {
        $codigo = recurring_payment_info_extract((string)$row['payment_code']);
        if ($codigo === null) {
            continue;
        }
        $row['codigo'] = $codigo['codigo'];
        $row['tipo'] = $codigo['tipo'];
        $transactions[] = $row;
    }
    if (!$transactions) {
        return ['updated' => 0, 'assigned' => 0];
    }

    $usados = [];
    $atribuidos = [];
    foreach ($rules as $rule) {
        $melhor = null;
        $melhorScore = 0;
        foreach ($transactions as $transacao) {
            if (isset($usados[(int)$transacao['id']])) {
                continue;
            }
            $score = recurring_payment_info_score($rule, $transacao, $matchers[(int)$rule['id']] ?? []);
            if ($score > $melhorScore) {
                $melhor = $transacao;
                $melhorScore = $score;
            }
        }
        if ($melhor !== null && $melhorScore >= 60) {
            $usados[(int)$melhor['id']] = true;
            $atribuidos[(int)$rule['id']] = $melhor;
        }
    }

    $update = db()->prepare('UPDATE recurring_rules
        SET boleto_codigo = ?, boleto_fonte = ?, boleto_atualizado_em = ?
        WHERE id = ?');
    $limpar = db()->prepare('UPDATE recurring_rules
        SET boleto_codigo = NULL, boleto_fonte = NULL, boleto_atualizado_em = NULL
        WHERE id = ?');
    $updated = 0;
    foreach ($rules as $rule) {
        $transacao = $atribuidos[(int)$rule['id']] ?? null;
        if ($transacao === null) {
            // Nao deixa um codigo antigo de historico preso na conta quando o
            // lancamento mais recente nao tem mais um codigo valido.
            if ((string)($rule['boleto_fonte'] ?? '') === 'historico' && trim((string)($rule['boleto_codigo'] ?? '')) !== '') {
                $limpar->execute([(int)$rule['id']]);
                $updated += $limpar->rowCount();
            }
            continue;
        }
        if (recurring_payment_info_locked($rule)) {
            continue;
        }
        $codigo = (string)$transacao['codigo'];
        $fonte = 'historico';
        if ((string)($rule['boleto_codigo'] ?? '') === $codigo && (string)($rule['boleto_fonte'] ?? '') === $fonte) {
            continue;
        }
        $data = trim((string)($transacao['due_date'] ?? ''));
        $update->execute([$codigo, $fonte, $data !== '' ? $data . ' 12:00:00' : date('Y-m-d H:i:s'), (int)$rule['id']]);
        $updated += $update->rowCount();
    }

    return ['updated' => $updated, 'assigned' => count($atribuidos)];
}
