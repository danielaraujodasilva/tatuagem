<?php
declare(strict_types=1);

/**
 * conversa_feed.php - "pulso" de tempo real da tela ?page=conversa.
 *
 * A tela ?page=conversa e montada no servidor (so mudava com F5). Este endpoint
 * da ao JavaScript uma forma barata de saber QUANDO o feed mudou, sem baixar a
 * pagina inteira: responde uma "versao" do feed (contagem + ultimo id).
 *
 * Ele tambem acorda o sincronizador da ponte (tools/ponte_sync.php) quando ele
 * esta atrasado, para o feed ficar fresco. Nao envia nada, nao cria lead e nao
 * apaga nada.
 *
 * Uso: GET api/conversa_feed.php  ->  {"ok":true,"versao":"...","sincronizou":bool}
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$sincronizou = false;
$permitirSync = (string)($_GET['sync'] ?? '1') !== '0';

if ($permitirSync) {
    $logFile = dirname(__DIR__) . '/data/ponte_sync.log';
    $ultimo = is_file($logFile) ? (int)@filemtime($logFile) : 0;

    // So acorda o sync se ele esta parado ha mais de 2s (o proprio script tem trava).
    if ((time() - $ultimo) >= 2) {
        $phpBin = 'C:\\xampp\\php\\php.exe';
        $script = dirname(__DIR__) . '/tools/ponte_sync.php';
        if (is_file($phpBin) && is_file($script) && function_exists('exec')) {
            $saida = [];
            $codigo = 0;
            @exec('"' . $phpBin . '" "' . $script . '"', $saida, $codigo);
            $sincronizou = true;
        }
    }
}

$versao = '0';
$studio = v2_try(static fn() => v2_studio(), null);
if ($studio instanceof PDO) {
    $row = v2_try(static function () use ($studio) {
        return $studio->query(
            'SELECT (SELECT COUNT(*) FROM whatsapp_messages) AS m,
                    (SELECT COALESCE(MAX(id), 0) FROM whatsapp_messages) AS mid,
                    (SELECT COUNT(*) FROM whatsapp_conversations) AS c'
        )->fetch(PDO::FETCH_ASSOC);
    }, null);
    if (is_array($row)) {
        $versao = (int)$row['m'] . '-' . (int)$row['mid'] . '-' . (int)$row['c'];
    }
}

echo json_encode([
    'ok' => true,
    'versao' => $versao,
    'sincronizou' => $sincronizou,
    'agora' => date('c'),
], JSON_UNESCAPED_UNICODE);