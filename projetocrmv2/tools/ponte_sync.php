<?php
declare(strict_types=1);

/**
 * ponte_sync.php — leva as conversas da ponte do WhatsApp (whatsapp-origin-bridge)
 * para as tabelas do CRM: whatsapp_conversations e whatsapp_messages.
 *
 * POR QUE EXISTE
 * A ponte Baileys grava toda mensagem em dados/conversas.jsonl (append-only).
 * Essa e a fonte viva do WhatsApp do estudio, mas a tela ?page=conversa le as
 * tabelas do CRM, que pararam de receber quando a API oficial caiu. Este job
 * copia o feed da ponte para as tabelas, para o historico nao se perder se o
 * Baileys falhar: o jsonl guarda, e este job repoe no banco.
 *
 * O QUE FAZ
 *   - Le o jsonl a partir de um offset guardado (retomada incremental).
 *   - Cria a conversa por telefone se ela ainda nao existir.
 *   - Insere a mensagem (texto/audio/midia) com id sintetico "ponte:<hash>".
 *   - Atualiza o preview/ultima data da conversa.
 *
 * O QUE NAO FAZ (de proposito)
 *   - Nao dispara IA nem envia nada: grava direto, sem passar pelo webhook.
 *   - Nao cria lead e nao apaga nada.
 *   - Conversas novas nascem em attendance_mode "human" (sem resposta automatica).
 *
 * Uso:  php tools/ponte_sync.php [caminho-do-jsonl] [--dry-run] [--reset]
 * Saida: uma linha de resumo + codigo de saida 0 (erro: 1).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('uso restrito a CLI');
}

date_default_timezone_set('America/Sao_Paulo');

const PONTE_JSONL_PADRAO = 'C:\\Users\\server_spd\\Documents\\whatsapp-origin-bridge\\dados\\conversas.jsonl';
const PONTE_TZ = 'America/Sao_Paulo';

$argvList = $argv ?? [];
$dryRun = in_array('--dry-run', $argvList, true);
$reset = in_array('--reset', $argvList, true);
$lids = in_array('--lids', $argvList, true);

$jsonl = getenv('CRM_PONTE_CONVERSAS') ?: PONTE_JSONL_PADRAO;
foreach (array_slice($argvList, 1) as $arg) {
    if ($arg !== '' && $arg[0] !== '-' && is_file($arg)) {
        $jsonl = $arg;
        break;
    }
}

$dataDir = dirname(__DIR__) . '/data';
$stateFile = $dataDir . '/ponte_sync_state.json';
$lockFile = $dataDir . '/ponte_sync.lock';
$logFile = $dataDir . '/ponte_sync.log';

function ponte_log(string $file, string $line): void
{
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND);
}

/** Mesma descoberta de banco do projetocrmv2/includes/bootstrap.php (v2_studio). */
function ponte_pdo(): PDO
{
    $local = [];
    $path = dirname(__DIR__) . '/config.local.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $local = $loaded;
        }
    }

    $plat = [];
    foreach ([
        dirname(__DIR__) . '/../projetocrm/config/database.local.php',
        dirname(__DIR__) . '/../projetocrm/config/database.php',
    ] as $p) {
        if (is_file($p)) {
            $loaded = require $p;
            if (is_array($loaded)) {
                $plat = $loaded;
                break;
            }
        }
    }

    $crmLocal = [];
    $p = dirname(__DIR__) . '/../crm/config.local.php';
    if (is_file($p)) {
        $loaded = require $p;
        if (is_array($loaded)) {
            $crmLocal = $loaded;
        }
    }

    $host = (string)($local['studio_host'] ?? ($plat['host'] ?? ($crmLocal['host'] ?? 'localhost')));
    $user = (string)($local['studio_username'] ?? ($plat['username'] ?? ($crmLocal['username'] ?? 'root')));
    $pass = (string)($local['studio_password'] ?? ($plat['password'] ?? ($crmLocal['password'] ?? '')));
    $platDb = (string)($plat['database'] ?? 'projetocrm_platform');

    $db = trim((string)($local['studio_database'] ?? ''));
    if ($db === '') {
        try {
            $platPdo = new PDO(
                "mysql:host={$host};dbname={$platDb};charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
            );
            $db = (string)($platPdo->query("SELECT database_name FROM studios WHERE status = 'active' ORDER BY id LIMIT 1")->fetchColumn() ?: '');
        } catch (Throwable) {
            $db = '';
        }
    }
    if ($db === '') {
        $db = 'projetocrm_cereja';
    }

    return new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
}

/** ISO (UTC) do feed -> data local do CRM. */
function ponte_data(?string $iso): string
{
    $iso = trim((string)$iso);
    if ($iso === '') {
        return date('Y-m-d H:i:s');
    }
    try {
        $dt = new DateTimeImmutable($iso);
        return $dt->setTimezone(new DateTimeZone(PONTE_TZ))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        $ts = strtotime($iso);
        return date('Y-m-d H:i:s', $ts !== false ? $ts : time());
    }
}

/** media_type da ponte -> message_type que as telas do CRM/v2 conhecem. */
function ponte_tipo(string $mediaType): string
{
    $map = [
        'text' => 'texto',
        'other' => 'texto',
        'audio' => 'audio',
        'image' => 'image',
        'video' => 'video',
        'document' => 'document',
        'sticker' => 'sticker',
    ];
    return $map[$mediaType] ?? 'texto';
}

function ponte_nome_generico(?string $nome): bool
{
    $nome = trim((string)$nome);
    return $nome === '' || $nome === 'Cliente' || $nome === 'Cliente WhatsApp';
}

/**
 * Eventos "other" sem texto: devolve um rotulo curto para os que tem conteudo
 * (localizacao, contato...) ou null para os que sao so ruido de sistema
 * (reacao/recibo), que nao devem entrar como conversa.
 *
 * @param array<int,string> $keys
 */
function ponte_rotulo_other(array $keys): ?string
{
    $keys = array_values(array_filter(array_map('strval', $keys)));
    $map = [
        'locationMessage' => 'localizacao 📍',
        'contactMessage' => 'contato 👤',
        'interactiveMessage' => 'resposta rapida 🔘',
        'albumMessage' => 'album 🖼',
    ];
    foreach ($keys as $key) {
        if (isset($map[$key])) {
            return $map[$key];
        }
    }
    $ruido = ['reactionMessage', 'protocolMessage', 'secretEncryptedMessage', 'senderKeyDistributionMessage', 'messageContextInfo', ''];
    foreach ($keys as $key) {
        if (!in_array($key, $ruido, true)) {
            return '[' . $key . ']';
        }
    }
    return null;
}

/** So os digitos de um telefone ou jid. */
function ponte_digitos(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

/** Coluna existe? Em cache, para o sync rodar ate em banco sem a migracao. */
function ponte_tem_coluna(PDO $pdo, string $tabela, string $coluna): bool
{
    static $cache = [];
    $chave = $tabela . '.' . $coluna;
    if (!array_key_exists($chave, $cache)) {
        $cache[$chave] = (bool)$pdo->query(
            'SHOW COLUMNS FROM ' . $tabela . ' LIKE ' . $pdo->quote($coluna)
        )->fetchColumn();
    }
    return $cache[$chave];
}

/** Ultimo contato da conversa, recalculado a partir das mensagens dela. */
function ponte_recalcular_ultima(PDO $pdo, int $conversaId): void
{
    $ordem = 'ORDER BY COALESCE(m.sent_at, m.created_at) DESC, m.id DESC LIMIT 1';
    $sql = 'UPDATE whatsapp_conversations SET
                last_message_preview = (SELECT LEFT(COALESCE(NULLIF(m.body, ""), NULLIF(m.transcricao, ""), ""), 250)
                                          FROM whatsapp_messages m WHERE m.conversation_id = ? ' . $ordem . '),
                last_message_direction = (SELECT IF(m.from_me = 1, "out", "in")
                                          FROM whatsapp_messages m WHERE m.conversation_id = ? ' . $ordem . '),
                last_message_at = (SELECT COALESCE(m.sent_at, m.created_at)
                                          FROM whatsapp_messages m WHERE m.conversation_id = ? ' . $ordem . ')';
    $params = [$conversaId, $conversaId, $conversaId];
    $sql .= ',
                updated_at = NOW()
            WHERE id = ?';
    $params[] = $conversaId;
    $pdo->prepare($sql)->execute($params);
}

/**
 * Le o feed inteiro e devolve jid (LID) -> telefone real. Serve para a primeira
 * rodada (o mapa fica guardado no state) e para o modo --lids.
 * @return array<string,string>
 */
function ponte_mapa_lids(string $jsonl): array
{
    $mapa = [];
    $handle = fopen($jsonl, 'rb');
    if ($handle === false) {
        return $mapa;
    }
    while (($linha = fgets($handle)) !== false) {
        $rec = json_decode($linha, true);
        if (!is_array($rec) || empty($rec['phone_resolvido'])) {
            continue;
        }
        $jid = trim((string)($rec['jid_original'] ?? ''));
        $phone = ponte_digitos((string)($rec['phone'] ?? ''));
        if ($jid !== '' && $phone !== '') {
            $mapa[$jid] = $phone;
        }
    }
    fclose($handle);
    return $mapa;
}

/**
 * Mescla as conversas fantasma (chave = LID) na conversa do telefone real.
 * Move so as mensagens que ainda nao existem no destino - a mesma mensagem pode
 * ter sido gravada nas duas chaves - e marca a fantasma em merged_into_id, que
 * tira ela das listas. Nada e apagado.
 * @return array{0:int,1:int} [conversas_mescladas, mensagens_movidas]
 */
function ponte_mesclar_lids(PDO $pdo, array $lidMap, bool $dryRun): array
{
    if (!$lidMap) {
        return [0, 0];
    }
    $temColuna = (bool)$pdo->query("SHOW COLUMNS FROM whatsapp_conversations LIKE 'merged_into_id'")->fetchColumn();
    if (!$temColuna) {
        return [0, 0];
    }

    $candidatas = $pdo->query(
        'SELECT id, phone, remote_jid FROM whatsapp_conversations
          WHERE merged_into_id IS NULL AND remote_jid IS NOT NULL AND remote_jid <> ""'
    )->fetchAll(PDO::FETCH_ASSOC);

    $selReal = $pdo->prepare('SELECT id FROM whatsapp_conversations WHERE phone = ? ORDER BY id LIMIT 1');
    $selMsgs = $pdo->prepare('SELECT id, from_me, message_type, body, sent_at FROM whatsapp_messages WHERE conversation_id = ?');
    $selDup = $pdo->prepare(
        'SELECT id FROM whatsapp_messages
          WHERE conversation_id = ? AND from_me = ? AND message_type = ? AND COALESCE(body, "") = ?
            AND sent_at BETWEEN ? AND ? LIMIT 1'
    );
    $upMsg = $pdo->prepare('UPDATE whatsapp_messages SET conversation_id = ? WHERE id = ?');
    $upConv = $pdo->prepare('UPDATE whatsapp_conversations SET merged_into_id = ?, updated_at = NOW() WHERE id = ?');

    $mescladas = 0;
    $movidas = 0;
    foreach ($candidatas as $conv) {
        $jid = (string)$conv['remote_jid'];
        $doJid = ponte_digitos($jid);
        if ($doJid === '' || ponte_digitos((string)$conv['phone']) !== $doJid) {
            continue; // telefone "de verdade": nao e fantasma de LID
        }
        $real = (string)($lidMap[$jid] ?? '');
        if ($real === '' || $real === $doJid) {
            continue;
        }
        $selReal->execute([$real]);
        $realId = (int)$selReal->fetchColumn();
        if ($realId <= 0) {
            continue; // a conversa real ainda nao existe: fica para a proxima
        }

        $selMsgs->execute([(int)$conv['id']]);
        foreach ($selMsgs->fetchAll(PDO::FETCH_ASSOC) as $msg) {
            $sentAt = (string)($msg['sent_at'] ?? '');
            $ts = $sentAt !== '' ? strtotime($sentAt) : false;
            if ($ts !== false) {
                $selDup->execute([
                    $realId,
                    (int)$msg['from_me'],
                    (string)$msg['message_type'],
                    (string)$msg['body'],
                    date('Y-m-d H:i:s', $ts - 3),
                    date('Y-m-d H:i:s', $ts + 3),
                ]);
                if ($selDup->fetchColumn()) {
                    continue; // mesma mensagem ja esta na conversa real
                }
            }
            if (!$dryRun) {
                $upMsg->execute([$realId, (int)$msg['id']]);
            }
            $movidas++;
        }

        if (!$dryRun) {
            $upConv->execute([$realId, (int)$conv['id']]);
            ponte_recalcular_ultima($pdo, $realId);
        }
        $mescladas++;
    }

    return [$mescladas, $movidas];
}

/**
 * Importa um registro do feed. Devolve [conversa_nova(bool), mensagem_nova(bool)].
 * @return array{0:bool,1:bool}
 */
function ponte_importar(PDO $pdo, array $rec, bool $dryRun, array &$lidMap = []): array
{
    $phone = preg_replace('/\D+/', '', (string)($rec['phone'] ?? '')) ?? '';
    if ($phone === '') {
        return [false, false];
    }

    // A ponte as vezes manda o chat so com o LID (phone_resolvido=false). Se esse
    // LID ja apareceu resolvido antes, usamos o telefone real: sem isso nasciam
    // duas conversas do mesmo cliente e a fantasma poluia a lista "sem resposta".
    $remoteJid = trim((string)($rec['jid_original'] ?? ''));
    if ($remoteJid !== '') {
        if (!empty($rec['phone_resolvido'])) {
            $lidMap[$remoteJid] = $phone;
        } elseif (isset($lidMap[$remoteJid])) {
            $phone = (string)$lidMap[$remoteJid];
        }
    }

    $fromMe = !empty($rec['from_me']);
    $mediaType = strtolower(trim((string)($rec['media_type'] ?? 'text')));
    $texto = trim((string)($rec['text'] ?? ''));
    $transcricao = trim((string)($rec['transcription'] ?? ''));
    $tipo = ponte_tipo($mediaType);
    $corpo = $texto !== '' ? $texto : ($tipo === 'audio' ? $transcricao : '');
    if ($mediaType === 'other' && $corpo === '') {
        $rotuloOther = ponte_rotulo_other($rec['message_keys'] ?? []);
        if ($rotuloOther === null) {
            return [false, false];
        }
        $corpo = $rotuloOther;
    }
    $sentAt = ponte_data($rec['sent_at'] ?? $rec['at'] ?? null);
    $nome = trim((string)($rec['name'] ?? ''));
    $mediaSalva = trim((string)($rec['media_saved'] ?? ''));
    $transcricaoErro = trim((string)($rec['transcription_error'] ?? ''));
    $direcao = $fromMe ? 'out' : 'in';

    $cnt = $pdo->prepare('SELECT id, name, last_message_at FROM whatsapp_conversations WHERE phone = ? ORDER BY id LIMIT 1');
    $cnt->execute([$phone]);
    $conv = $cnt->fetch(PDO::FETCH_ASSOC) ?: null;

    $conversaNova = false;
    if (!$conv) {
        $conversaNova = true;
        if ($dryRun) {
            $conv = ['id' => 0, 'name' => $nome !== '' ? $nome : 'Cliente WhatsApp', 'last_message_at' => null];
        } else {
            $nomeConv = $nome !== '' ? $nome : 'Cliente WhatsApp';
            $temLeitura = ponte_tem_coluna($pdo, 'whatsapp_conversations', 'nao_lida');
            $colsLeitura = $temLeitura ? ', nao_lida, nao_lida_at' : '';
            $valsLeitura = $temLeitura ? ', ?, ?' : '';
            $ins = $pdo->prepare(
                'INSERT INTO whatsapp_conversations
                    (phone, name, remote_jid, attendance_mode, needs_human, ai_last_status, last_message_preview, last_message_direction, last_message_at' . $colsLeitura . ', created_at, updated_at)
                 VALUES (?, ?, ?, "human", ?, "IA inativa", ?, ?, ?' . $valsLeitura . ', ?, NOW())'
            );
            $paramsConv = [
                $phone,
                $nomeConv,
                $remoteJid,
                $fromMe ? 0 : 1,
                $corpo !== '' ? mb_substr($corpo, 0, 250) : '[' . $tipo . ']',
                $direcao,
                $sentAt,
            ];
            if ($temLeitura) {
                $paramsConv[] = 0;
                $paramsConv[] = null;
            }
            $paramsConv[] = $sentAt;
            $ins->execute($paramsConv);
            $conv = ['id' => (int)$pdo->lastInsertId(), 'name' => $nomeConv, 'last_message_at' => $sentAt];
        }
    } elseif (!$dryRun && $nome !== '' && ponte_nome_generico($conv['name'] ?? null)) {
        $pdo->prepare('UPDATE whatsapp_conversations SET name = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$nome, (int)$conv['id']]);
    }

    $conversaId = (int)$conv['id'];
    $msgId = 'ponte:' . substr(sha1($phone . '|' . $sentAt . '|' . ($fromMe ? '1' : '0') . '|' . $tipo . '|' . $corpo . '|' . (string)($rec['at'] ?? '')), 0, 40);

    $mensagemNova = false;
    if (!$dryRun) {
        $dup = $pdo->prepare('SELECT id FROM whatsapp_messages WHERE message_id = ? LIMIT 1');
        $dup->execute([$msgId]);
        $jaExiste = (bool)$dup->fetchColumn();

        if (!$jaExiste) {
            // Evita duplicar o que a API oficial ja gravou no periodo de sobreposicao.
            $janela = $pdo->prepare(
                'SELECT id FROM whatsapp_messages
                  WHERE conversation_id = ? AND from_me = ? AND message_type = ?
                    AND sent_at BETWEEN ? AND ? AND COALESCE(body, "") = ?
                  LIMIT 1'
            );
            $janela->execute([
                $conversaId,
                $fromMe ? 1 : 0,
                $tipo,
                date('Y-m-d H:i:s', strtotime($sentAt) - 3),
                date('Y-m-d H:i:s', strtotime($sentAt) + 3),
                $corpo,
            ]);
            $jaExiste = (bool)$janela->fetchColumn();
        }

        if (!$jaExiste) {
            $ins = $pdo->prepare(
                'INSERT INTO whatsapp_messages
                    (conversation_id, direction, sender_type, body, media_url, media_mime, media_file_name, media_file_path,
                     message_type, message_id, remote_jid, from_me, status, sent_at, created_at, transcricao, transcript, transcricao_erro)
                 VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)'
            );
            $ins->execute([
                $conversaId,
                $direcao,
                $fromMe ? 'human' : 'customer',
                $corpo !== '' ? $corpo : null,
                $mediaSalva !== '' ? basename($mediaSalva) : null,
                $mediaSalva !== '' ? $mediaSalva : null,
                $tipo,
                $msgId,
                $remoteJid !== '' ? $remoteJid : null,
                $fromMe ? 1 : 0,
                $fromMe ? 'sent' : null,
                $sentAt,
                $transcricao !== '' ? $transcricao : null,
                $transcricao !== '' ? $transcricao : null,
                $transcricaoErro !== '' ? $transcricaoErro : null,
            ]);
            $mensagemNova = true;

            $ultima = (string)($conv['last_message_at'] ?? '');
            if ($ultima === '' || $sentAt >= $ultima) {
                $sqlUlt = 'UPDATE whatsapp_conversations
                        SET remote_jid = COALESCE(NULLIF(?, ""), remote_jid),
                            needs_human = ?,
                            last_message_preview = ?,
                            last_message_direction = ?,
                            last_message_at = ?,
                            updated_at = NOW()
                      WHERE id = ?';
                $paramsUlt = [
                    $remoteJid,
                    $fromMe ? 0 : 1,
                    $corpo !== '' ? mb_substr($corpo, 0, 250) : '[' . $tipo . ']',
                    $direcao,
                    $sentAt,
                ];
                $paramsUlt[] = $conversaId;
                $pdo->prepare($sqlUlt)->execute($paramsUlt);
            }
        }
    }

    return [$conversaNova, $mensagemNova];
}

if (!is_file($jsonl)) {
    echo 'PONTE|ERRO|jsonl_ausente|' . $jsonl . PHP_EOL;
    exit(1);
}

@mkdir($dataDir, 0775, true);
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo 'PONTE|SKIP|ja_rodando' . PHP_EOL;
    exit(0);
}

$state = [];
if (!$reset && is_file($stateFile)) {
    $decoded = json_decode((string)file_get_contents($stateFile), true);
    if (is_array($decoded)) {
        $state = $decoded;
    }
}
$offset = (int)($state['offset'] ?? 0);

// Mapa LID (jid) -> telefone real, aprendido do feed e guardado no state.
// Sem ele a ponte cria duas conversas para o mesmo cliente (ver ponte_mesclar_lids).
$lidMap = is_array($state['lid_map'] ?? null) ? $state['lid_map'] : [];
if ($lids || !isset($state['lid_map'])) {
    $lidMap = array_merge($lidMap, ponte_mapa_lids($jsonl));
    ponte_log($logFile, 'mapa de LIDs carregado: ' . count($lidMap) . ' jids');
}
$tamanho = (int)filesize($jsonl);
if ($offset > $tamanho || ($state['path'] ?? '') !== $jsonl) {
    $offset = 0;
}

$pdo = ponte_pdo();
$handle = fopen($jsonl, 'rb');
if ($handle === false) {
    echo 'PONTE|ERRO|jsonl_ilegivel' . PHP_EOL;
    exit(1);
}
if ($offset > 0) {
    fseek($handle, $offset);
}

$lidas = 0;
$conversasNovas = 0;
$mensagensNovas = 0;
$erros = 0;
$pos = $offset;
$pdo->beginTransaction();

while (($linha = fgets($handle)) !== false) {
    if (substr($linha, -1) !== "\n") {
        break; // linha parcial (escrita em andamento): deixa para a proxima rodada
    }
    $pos += strlen($linha);
    $lidas++;

    $rec = json_decode($linha, true);
    if (!is_array($rec)) {
        continue;
    }

    try {
        [$convNova, $msgNova] = ponte_importar($pdo, $rec, $dryRun, $lidMap);
        $conversasNovas += $convNova ? 1 : 0;
        $mensagensNovas += $msgNova ? 1 : 0;
    } catch (Throwable $e) {
        $erros++;
        ponte_log($logFile, 'erro: ' . $e->getMessage());
        continue;
    }

    if ($lidas % 200 === 0 && !$dryRun) {
        $pdo->commit();
        $pdo->beginTransaction();
        file_put_contents($stateFile, json_encode([
            'path' => $jsonl,
            'offset' => $pos,
            'lid_map' => $lidMap,
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT));
    }
}

// Fantasmas de LID (inclusive as criadas antes desta versao): mescla na conversa
// real, para "sem resposta" refletir o mesmo chat que aparece no celular.
[$mescladas, $msgsMescladas] = ponte_mesclar_lids($pdo, $lidMap, $dryRun);

if (!$dryRun) {
    $pdo->commit();
    file_put_contents($stateFile, json_encode([
        'path' => $jsonl,
        'offset' => $pos,
        'lid_map' => $lidMap,
        'updated_at' => date('c'),
    ], JSON_PRETTY_PRINT));
} else {
    $pdo->rollBack();
}
fclose($handle);

$resumo = sprintf(
    'PONTE|OK|lidas=%d|conversas_novas=%d|mensagens_novas=%d|erros=%d|offset=%d|lids_mesclados=%d|msgs_mescladas=%d%s',
    $lidas,
    $conversasNovas,
    $mensagensNovas,
    $erros,
    $pos,
    $mescladas,
    $msgsMescladas,
    $dryRun ? '|dry_run' : ''
);
echo $resumo . PHP_EOL;
ponte_log($logFile, $resumo);

flock($lock, LOCK_UN);
fclose($lock);
exit(0);
