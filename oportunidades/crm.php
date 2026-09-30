<?php
declare(strict_types=1);

/**
 * Acesso ao banco do CRM do estudio (projetocrm) e montagem da lista de oportunidades.
 *
 * O nome e o telefone do cliente sao lidos aqui, em tempo de execucao, e nunca ficam
 * gravados no repositorio.
 */

function crm_config(): array
{
    $override = __DIR__ . '/config.local.php';
    if (is_file($override)) {
        $local = require $override;
        if (is_array($local) && !empty($local['database'])) {
            return $local;
        }
    }

    $base = dirname(__DIR__) . '/projetocrm/config/database.php';
    $local = dirname(__DIR__) . '/projetocrm/config/database.local.php';

    $config = [];
    if (is_file($base)) {
        $loaded = require $base;
        if (is_array($loaded)) {
            $config = $loaded;
        }
    }
    if (is_file($local)) {
        $loaded = require $local;
        if (is_array($loaded)) {
            $config = array_merge($config, $loaded);
        }
    }

    return $config;
}

function crm_database_candidates(): array
{
    $config = crm_config();
    $candidates = [];

    if (!empty($config['database'])) {
        $candidates[] = (string)$config['database'];
    }
    foreach (['projetocrm_cereja', 'projetocrm_platform'] as $nome) {
        if (!in_array($nome, $candidates, true)) {
            $candidates[] = $nome;
        }
    }

    return $candidates;
}

function crm_connect(string $database): PDO
{
    $config = crm_config();
    $host = (string)($config['host'] ?? 'localhost');
    $port = (int)($config['port'] ?? 3306);
    $user = (string)($config['username'] ?? 'root');
    $pass = (string)($config['password'] ?? '');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->query('SELECT 1 FROM whatsapp_conversations LIMIT 1');

    return $pdo;
}

function crm_nome_exibicao(?string $nome, string $fone): string
{
    $nome = trim((string)$nome);
    $genericos = ['', 'Cliente', 'Cliente WhatsApp', 'Estudio Cereja 2', 'Estúdio Cereja 2', 'Daniel Tatuador'];

    if (in_array($nome, $genericos, true) || stripos($nome, 'Cliente ') === 0) {
        $digitos = preg_replace('/\D/', '', $fone) ?? '';
        $sufixo = $digitos !== '' ? substr($digitos, -4) : '----';

        return 'Cliente ' . $sufixo;
    }

    return $nome;
}

/**
 * @return array<int, array<string, mixed>>
 */
function crm_oportunidades(): array
{
    $analise = require __DIR__ . '/analise.php';
    if (!is_array($analise) || $analise === []) {
        return [];
    }

    $ids = array_map('intval', array_keys($analise));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT c.id,
                   c.phone,
                   c.name,
                   COALESCE(c.last_message_at, c.updated_at) AS ultimo_contato
              FROM whatsapp_conversations c
             WHERE c.id IN ({$placeholders})";

    $erro = null;
    foreach (crm_database_candidates() as $database) {
        try {
            $stmt = crm_connect($database)->prepare($sql);
            $stmt->execute($ids);
            $conversas = $stmt->fetchAll();
        } catch (Throwable $e) {
            $erro = $e;
            continue;
        }

        $linhas = [];
        foreach ($conversas as $conversa) {
            $id = (int)$conversa['id'];
            if (!isset($analise[$id])) {
                continue;
            }

            [$nota, $situacao, $origem, $resumo] = array_pad($analise[$id], 4, '');
            $fone = preg_replace('/\D/', '', (string)$conversa['phone']) ?? '';
            if ($fone === '') {
                continue;
            }

            $ultimo = $conversa['ultimo_contato'];
            $linhas[] = [
                'id' => $id,
                'n' => crm_nome_exibicao($conversa['name'] ?? null, $fone),
                'f' => $fone,
                's' => (int)$nota,
                'st' => (string)$situacao,
                'o' => (string)$origem,
                'd' => $ultimo ? date('d/m', strtotime((string)$ultimo)) : '',
                'r' => (string)$resumo,
            ];
        }

        if ($linhas !== []) {
            usort($linhas, static fn(array $a, array $b): int => $b['s'] <=> $a['s']);

            return $linhas;
        }
    }

    if ($erro !== null) {
        throw new RuntimeException('Nao foi possivel ler as conversas no banco do CRM: ' . $erro->getMessage());
    }

    return [];
}
