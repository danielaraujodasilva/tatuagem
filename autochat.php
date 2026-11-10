<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/autochat_control.php';
$currentUser = require_staff();

date_default_timezone_set('America/Sao_Paulo');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

const AUTOCHAT_DADOS_PADRAO = 'C:\\Users\\server_spd\\Documents\\whatsapp-origin-bridge\\dados';
const AUTOCHAT_EQUIPE_PADRAO = ['5511947573311', '5511932259200'];

function autochat_digitos(string $valor): string
{
    return preg_replace('/\D+/', '', $valor) ?? '';
}

function autochat_timestamp(?string $iso): ?int
{
    $iso = trim((string)$iso);
    if ($iso === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($iso))->getTimestamp();
    } catch (Throwable) {
        $ts = strtotime($iso);
        return $ts !== false ? $ts : null;
    }
}

function autochat_data(?string $iso, bool $comAno = false): string
{
    $ts = autochat_timestamp($iso);
    if ($ts === null) {
        return '';
    }

    $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    if ($dt->format('Y-m-d') === date('Y-m-d')) {
        return $dt->format('H:i');
    }

    return $dt->format($comAno ? 'd/m/Y H:i' : 'd/m H:i');
}

function autochat_e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function autochat_rotulo_midia(string $tipo): string
{
    $rotulos = [
        'image' => '📷 Imagem',
        'audio' => '🎤 Áudio',
        'video' => '🎬 Vídeo',
        'document' => '📄 Documento',
        'sticker' => '🩹 Figurinha',
        'other' => '[mensagem]',
    ];

    return $rotulos[$tipo] ?? '[' . $tipo . ']';
}

function autochat_dados_dir(): string
{
    return rtrim((string)(getenv('AUTOCHAT_DADOS_DIR') ?: AUTOCHAT_DADOS_PADRAO), '\\/');
}

function autochat_chave_telefone(string $telefone): string
{
    $digitos = autochat_digitos($telefone);
    return strlen($digitos) > 11 ? substr($digitos, -11) : $digitos;
}

function autochat_numeros_equipe(): array
{
    static $numeros = null;
    if ($numeros !== null) {
        return $numeros;
    }

    $lista = AUTOCHAT_EQUIPE_PADRAO;
    $configPath = getenv('AUTOCHAT_CONFIG_PATH') ?: dirname(autochat_dados_dir()) . DIRECTORY_SEPARATOR . 'config.local.json';
    if (is_file($configPath)) {
        $config = json_decode((string)@file_get_contents($configPath), true);
        $followup = is_array($config['followup'] ?? null) ? $config['followup'] : [];
        foreach (['ownerPhone', 'autochatEquipePhones'] as $campo) {
            $valor = (string)($followup[$campo] ?? $config[$campo] ?? '');
            foreach (explode(',', $valor) as $telefone) {
                if (trim($telefone) !== '') {
                    $lista[] = $telefone;
                }
            }
        }
    }
    foreach (explode(',', (string)getenv('AUTOCHAT_EQUIPE_PHONES')) as $telefone) {
        if (trim($telefone) !== '') {
            $lista[] = $telefone;
        }
    }

    $numeros = array_values(array_unique(array_filter(array_map('autochat_chave_telefone', $lista))));
    return $numeros;
}

function autochat_midia_relativa(?string $caminho): string
{
    $caminho = trim((string)$caminho);
    if ($caminho === '') {
        return '';
    }

    $base = realpath(autochat_dados_dir());
    $arquivo = realpath($caminho);
    if ($base === false || $arquivo === false || !is_file($arquivo)) {
        return '';
    }

    $basePrefixo = rtrim(strtolower(str_replace('/', DIRECTORY_SEPARATOR, $base)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $arquivoNormalizado = strtolower(str_replace('/', DIRECTORY_SEPARATOR, $arquivo));
    if (!str_starts_with($arquivoNormalizado, $basePrefixo)) {
        return '';
    }

    $relativo = str_replace('\\', '/', substr($arquivo, strlen($base) + 1));
    $partes = explode('/', $relativo, 2);
    if (count($partes) < 2 || !in_array(strtolower($partes[0]), ['imagens', 'videos', 'audios', 'documentos'], true)) {
        return '';
    }

    return $relativo;
}

function autochat_url_midia(string $relativo): string
{
    return '/autochat_media.php?m=' . rawurlencode(str_replace('\\', '/', $relativo));
}

$autochatControle = autochat_control_load();
$autochatLigado = !empty($autochatControle['enabled']);
$podeAlternarAutoChat = (string)($currentUser['role'] ?? '') === 'adm';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'toggle_autochat') {
    autochat_control_csrf_verify();
    if (!$podeAlternarAutoChat) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    $novoEstado = (string)($_POST['enabled'] ?? '0') === '1';
    try {
        autochat_control_set_enabled($novoEstado, (string)($currentUser['nome'] ?? 'AutoChat'));
    } catch (Throwable $erro) {
        http_response_code(500);
        exit('Nao foi possivel alterar o AutoChat.');
    }

    $telefoneRetorno = autochat_digitos((string)($_POST['telefone'] ?? ''));
    header('Location: /autochat' . ($telefoneRetorno !== '' ? '?telefone=' . rawurlencode($telefoneRetorno) : ''));
    exit;
}

/**
 * Junta os eventos do AutoChat com o feed da ponte.
 *
 * @return array<int,array<string,mixed>>
 */
function autochat_carregar_conversas(): array
{
    $dir = autochat_dados_dir();
    $eventosPath = $dir . DIRECTORY_SEPARATOR . 'autochat.jsonl';
    $conversasPath = $dir . DIRECTORY_SEPARATOR . 'conversas.jsonl';
    $equipe = array_fill_keys(autochat_numeros_equipe(), true);

    $chats = [];
    $jidParaTelefone = [];

    $handle = @fopen($eventosPath, 'rb');
    if ($handle !== false) {
        while (($linha = fgets($handle)) !== false) {
            $evento = json_decode($linha, true);
            if (!is_array($evento)) {
                continue;
            }

            $tipo = (string)($evento['evento'] ?? '');
            $jid = trim((string)($evento['jid'] ?? ''));
            $telefone = autochat_digitos((string)($evento['telefone'] ?? ''));
            $at = trim((string)($evento['at'] ?? ''));

            if ($jid !== '' && $telefone !== '') {
                $jidParaTelefone[$jid] = $telefone;
            }
            if ($telefone === '' && $jid !== '') {
                $telefone = $jidParaTelefone[$jid] ?? '';
            }
            if ($telefone === '') {
                continue;
            }
            if (isset($equipe[autochat_chave_telefone($telefone)])) {
                continue;
            }

            if (!isset($chats[$telefone])) {
                $chats[$telefone] = [
                    'telefone' => $telefone,
                    'jid' => $jid,
                    'nome' => '',
                    'desde' => $at,
                    'ultimo_evento' => $at,
                    'ativo' => false,
                    'repassado' => false,
                    'repassado_em' => '',
                    'equipe_assumiu' => false,
                    'dados' => [],
                    'respostas' => 0,
                    'mensagens' => [],
                    'ultima_mensagem' => '',
                    'ultima_mensagem_em' => '',
                ];
            }

            $chat = &$chats[$telefone];
            if ($jid !== '') {
                $chat['jid'] = $jid;
            }
            $chat['ultimo_evento'] = $at;

            if ($tipo === 'ligado') {
                $chat['ativo'] = true;
                $chat['desde'] = $at;
                $chat['repassado'] = false;
                $chat['repassado_em'] = '';
                $chat['equipe_assumiu'] = false;
                $chat['dados'] = [];
                $chat['respostas'] = 0;
                $nome = trim((string)($evento['nome'] ?? ''));
                if ($nome !== '') {
                    $chat['nome'] = $nome;
                }
            } elseif ($tipo === 'desligado') {
                $chat['ativo'] = false;
            } elseif ($tipo === 'repassado') {
                $chat['repassado'] = true;
                $chat['repassado_em'] = $at;
            } elseif ($tipo === 'equipe_assumiu') {
                $chat['equipe_assumiu'] = true;
            } elseif ($tipo === 'respondeu') {
                $chat['respostas']++;
            }

            $dados = $evento['dados'] ?? null;
            if (is_array($dados)) {
                foreach (['nome', 'parte', 'desenho', 'quando'] as $campo) {
                    $valor = trim((string)($dados[$campo] ?? ''));
                    if ($valor !== '') {
                        $chat['dados'][$campo] = $valor;
                    }
                }
            }
            unset($chat);
        }
        fclose($handle);
    }

    if (!$chats) {
        return [];
    }

    $handle = @fopen($conversasPath, 'rb');
    if ($handle !== false) {
        $vistos = [];
        while (($linha = fgets($handle)) !== false) {
            $registro = json_decode($linha, true);
            if (!is_array($registro)) {
                continue;
            }

            $jid = trim((string)($registro['jid_original'] ?? ''));
            $telefone = autochat_digitos((string)($registro['phone'] ?? ''));
            if (empty($registro['phone_resolvido'])) {
                $telefone = $jidParaTelefone[$jid] ?? $telefone;
            } elseif (!isset($chats[$telefone]) && isset($jidParaTelefone[$jid])) {
                $telefone = $jidParaTelefone[$jid];
            }
            if ($jid !== '' && !isset($jidParaTelefone[$jid]) && !empty($registro['phone_resolvido']) && $telefone !== '') {
                $jidParaTelefone[$jid] = $telefone;
            }
            if (!isset($chats[$telefone])) {
                continue;
            }

            $mediaType = strtolower(trim((string)($registro['media_type'] ?? 'text')));
            $texto = trim((string)($registro['text'] ?? ''));
            $transcricao = trim((string)($registro['transcription'] ?? ''));
            $erroTranscricao = trim((string)($registro['transcription_error'] ?? ''));
            $midia = autochat_midia_relativa((string)($registro['media_saved'] ?? ''));
            if ($texto === '' && $transcricao === '' && $midia === '') {
                if ($mediaType === 'text' || $mediaType === '') {
                    continue;
                }
                $texto = autochat_rotulo_midia($mediaType);
            }
            $preview = $texto !== '' ? $texto : ($transcricao !== '' ? $transcricao : autochat_rotulo_midia($mediaType));

            $at = (string)($registro['sent_at'] ?? $registro['at'] ?? '');
            $de = !empty($registro['from_me']) ? 'estudio' : 'cliente';
            $ts = autochat_timestamp($at) ?? 0;
            $assinatura = $jid . '|' . $de . '|' . $at . '|' . $texto . '|' . $transcricao . '|' . $midia;
            if (isset($vistos[$assinatura])) {
                continue;
            }
            $vistos[$assinatura] = true;

            $chats[$telefone]['mensagens'][] = [
                'de' => $de,
                'texto' => $texto,
                'em' => $at,
                'tipo' => $mediaType,
                'midia' => $midia,
                'transcricao' => $transcricao,
                'erro_transcricao' => $erroTranscricao,
            ];

            $nome = trim((string)($registro['name'] ?? ''));
            if ($nome !== '' && $chats[$telefone]['nome'] === '') {
                $chats[$telefone]['nome'] = $nome;
            }

            $ultimoTs = autochat_timestamp($chats[$telefone]['ultima_mensagem_em']) ?? 0;
            if ($ts >= $ultimoTs) {
                $chats[$telefone]['ultima_mensagem'] = $preview;
                $chats[$telefone]['ultima_mensagem_em'] = $at;
            }
        }
        fclose($handle);
    }

    foreach ($chats as &$chat) {
        if (count($chat['mensagens']) > 400) {
            $chat['mensagens'] = array_slice($chat['mensagens'], -400);
        }
        if ($chat['nome'] === '') {
            $chat['nome'] = 'Contato ' . substr($chat['telefone'], -4);
        }
        if ($chat['ultima_mensagem'] === '') {
            $chat['ultima_mensagem'] = 'Sem mensagens registradas.';
        }
    }
    unset($chat);

    $conversas = array_values($chats);
    usort($conversas, static function (array $a, array $b): int {
        $tsA = autochat_timestamp($a['ultima_mensagem_em'] ?: $a['ultimo_evento']) ?? 0;
        $tsB = autochat_timestamp($b['ultima_mensagem_em'] ?: $b['ultimo_evento']) ?? 0;
        return $tsB <=> $tsA;
    });

    return $conversas;
}

$conversas = autochat_carregar_conversas();
$telefoneSelecionado = autochat_digitos((string)($_GET['telefone'] ?? ''));
$selecionada = null;
foreach ($conversas as $conversa) {
    if ($telefoneSelecionado !== '' && $conversa['telefone'] === $telefoneSelecionado) {
        $selecionada = $conversa;
        break;
    }
}
if ($selecionada === null && $conversas) {
    $selecionada = $conversas[0];
}

$camposDados = [
    'nome' => 'Nome',
    'parte' => 'Parte do corpo',
    'desenho' => 'Arte / desenho',
    'quando' => 'Quando',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no,email=no,address=no">
    <title>AutoChat — Atendimentos da Irene</title>
    <style>
        :root {
            --bg: #0b141a;
            --panel: #111b21;
            --panel-2: #202c33;
            --line: #2a3942;
            --text: #e9edef;
            --muted: #8696a0;
            --green: #00a884;
            --green-dark: #008069;
            --bubble-in: #202c33;
            --bubble-out: #005c4b;
            --amber: #f5c451;
            --blue: #63b3ed;
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            overflow: hidden;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        .app {
            display: grid;
            grid-template-columns: 390px minmax(0, 1fr);
            height: 100vh;
            height: 100dvh;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: var(--panel);
            border-right: 1px solid var(--line);
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            background: var(--panel-2);
            border-bottom: 1px solid var(--line);
        }

        .brand-title {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .brand-title strong {
            font-size: 17px;
            letter-spacing: 0.2px;
        }

        .brand-title span {
            overflow: hidden;
            color: var(--muted);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .refresh {
            flex: 0 0 auto;
            padding: 7px 11px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
            font-size: 12px;
            text-decoration: none;
            background: #2a3942;
        }

        .refresh:hover { background: #31434e; }

        .brand-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }

        .control-form { margin: 0; }

        .control-toggle {
            padding: 7px 11px;
            border: 1px solid rgba(134, 150, 160, 0.35);
            border-radius: 999px;
            background: #2a3942;
            color: var(--muted);
            font: inherit;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.2px;
            cursor: pointer;
        }

        .control-toggle.on {
            border-color: rgba(0, 168, 132, 0.45);
            background: rgba(0, 168, 132, 0.18);
            color: #7ff0d0;
        }

        .control-toggle:disabled { cursor: default; opacity: 0.85; }

        .list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
        }

        .conv {
            display: flex;
            gap: 12px;
            padding: 13px 16px;
            color: inherit;
            text-decoration: none;
            border-bottom: 1px solid rgba(42, 57, 66, 0.7);
        }

        .conv:hover { background: #182229; }
        .conv.active { background: #2a3942; }

        .avatar {
            display: grid;
            place-items: center;
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            border-radius: 999px;
            background: #1f544a;
            color: #d9fdd3;
            font-size: 15px;
            font-weight: 700;
        }

        .conv-body {
            flex: 1;
            min-width: 0;
        }

        .conv-top {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
        }

        .conv-name {
            overflow: hidden;
            font-size: 15px;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .conv-time {
            flex: 0 0 auto;
            color: var(--muted);
            font-size: 11px;
        }

        .conv-preview {
            margin-top: 4px;
            overflow: hidden;
            color: var(--muted);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 7px;
        }

        .badge {
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .badge.ativa { background: rgba(0, 168, 132, 0.18); color: #7ff0d0; }
        .badge.handoff { background: rgba(245, 196, 81, 0.17); color: var(--amber); }
        .badge.equipe { background: rgba(99, 179, 237, 0.17); color: var(--blue); }
        .badge.encerrada { background: rgba(134, 150, 160, 0.17); color: var(--muted); }

        .chat {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            background:
                linear-gradient(rgba(11, 20, 26, 0.91), rgba(11, 20, 26, 0.91)),
                repeating-linear-gradient(45deg, #182229 0, #182229 12px, #111b21 12px, #111b21 24px);
        }

        .chat-header {
            flex: 0 0 auto;
            padding: 12px 18px;
            background: var(--panel-2);
            border-bottom: 1px solid var(--line);
        }

        .chat-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .chat-title { flex: 1; min-width: 0; }

        .back {
            display: none;
            flex: 0 0 auto;
            padding: 6px 9px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
            font-size: 12px;
            text-decoration: none;
            background: #2a3942;
        }

        .chat-title strong {
            display: block;
            overflow: hidden;
            font-size: 16px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .chat-title span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
            font-variant-numeric: tabular-nums;
        }

        .readonly {
            flex: 0 0 auto;
            padding: 5px 9px;
            border: 1px solid var(--line);
            border-radius: 999px;
            color: var(--muted);
            font-size: 10px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .dados {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 7px;
            margin-top: 11px;
        }

        .dado {
            padding: 7px 9px;
            border: 1px solid rgba(42, 57, 66, 0.9);
            border-radius: 8px;
            background: rgba(17, 27, 33, 0.72);
        }

        .dado small {
            display: block;
            color: var(--muted);
            font-size: 10px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .dado span {
            display: block;
            margin-top: 3px;
            overflow-wrap: anywhere;
            font-size: 13px;
        }

        .dado.vazio span { color: var(--muted); font-style: italic; }

        .mensagens {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 18px clamp(12px, 4vw, 48px) 26px;
        }

        .bubble {
            width: fit-content;
            max-width: min(76%, 680px);
            margin-bottom: 6px;
            padding: 7px 10px 5px;
            border-radius: 12px 12px 12px 3px;
            background: var(--bubble-in);
            box-shadow: 0 1px 0.5px rgba(0, 0, 0, 0.16);
            font-size: 14px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .bubble.out {
            margin-left: auto;
            border-radius: 12px 12px 3px 12px;
            background: var(--bubble-out);
        }

        .bubble .texto {
            display: block;
            white-space: pre-wrap;
        }

        .midia {
            display: block;
            margin: -2px 0 5px;
        }

        .midia img,
        .midia video {
            display: block;
            width: min(100%, 520px);
            max-height: 420px;
            border-radius: 9px;
            object-fit: contain;
            background: #0b141a;
        }

        .midia audio {
            display: block;
            width: min(100%, 420px);
            min-width: min(280px, 100%);
        }

        .midia.arquivo {
            display: inline-flex;
            gap: 7px;
            align-items: center;
            padding: 9px 11px;
            border: 1px solid rgba(233, 237, 239, 0.17);
            border-radius: 9px;
            color: var(--text);
            text-decoration: none;
            background: rgba(11, 20, 26, 0.36);
        }

        .transcricao {
            margin: 5px 0 2px;
            padding: 8px 10px;
            border-left: 3px solid rgba(0, 168, 132, 0.75);
            border-radius: 6px;
            background: rgba(11, 20, 26, 0.35);
            font-size: 13px;
            line-height: 1.45;
            white-space: pre-wrap;
        }

        .transcricao strong {
            display: block;
            margin-bottom: 2px;
            color: #7ff0d0;
            font-size: 10px;
            letter-spacing: 0.35px;
            text-transform: uppercase;
        }

        .midia-erro {
            margin-top: 5px;
            color: var(--amber);
            font-size: 11px;
        }

        .bubble .meta {
            display: block;
            margin-top: 2px;
            color: rgba(233, 237, 239, 0.58);
            font-size: 10px;
            line-height: 1.2;
            text-align: right;
            white-space: nowrap;
        }

        .empty {
            padding: 40px 24px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
            text-align: center;
        }

        .chat .empty {
            margin: auto;
            max-width: 440px;
        }

        .footer {
            flex: 0 0 auto;
            padding: 10px 20px;
            border-top: 1px solid var(--line);
            background: var(--panel);
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 820px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { border-right: 0; }
            body.chat-open .sidebar { display: none; }
            body:not(.chat-open) .chat { display: none; }
            .chat-header { padding: 10px 12px; }
            .chat-title-row { gap: 8px; }
            .back { display: inline-block; padding: 5px 8px; font-size: 11px; }
            .chat-title strong { font-size: 15px; }
            .chat-title span { font-size: 11px; }
            .readonly { display: none; }
            .dados { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; margin-top: 9px; }
            .dado { padding: 6px 8px; }
            .dado small { font-size: 9px; }
            .dado span { font-size: 12px; }
            .mensagens { padding: 12px 10px 20px; }
            .bubble { max-width: 86%; font-size: 14px; }
            .footer { padding: 7px 12px; font-size: 10px; }
            .brand { padding: 13px 14px; }
            .brand-actions { gap: 6px; }
            .control-toggle { padding: 6px 9px; font-size: 10px; }
            .conv { padding: 11px 12px; gap: 10px; }
            .avatar { flex-basis: 42px; width: 42px; height: 42px; font-size: 14px; }
        }
    </style>
</head>
<body class="<?= $telefoneSelecionado !== '' ? 'chat-open' : '' ?>">
<div class="app">
    <aside class="sidebar">
        <header class="brand">
            <div class="brand-title">
                <strong>AutoChat</strong>
                <span>Atendimentos da Irene · <?= $autochatLigado ? 'ligado' : 'desligado' ?></span>
            </div>
            <div class="brand-actions">
                <?php if ($podeAlternarAutoChat): ?>
                    <form class="control-form" method="post">
                        <?= autochat_control_csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_autochat">
                        <input type="hidden" name="enabled" value="<?= $autochatLigado ? '0' : '1' ?>">
                        <input type="hidden" name="telefone" value="<?= autochat_e($telefoneSelecionado) ?>">
                        <button class="control-toggle<?= $autochatLigado ? ' on' : '' ?>" type="submit" title="<?= autochat_e(($autochatLigado ? 'Desligar' : 'Ligar') . ' AutoChat · atualizado em ' . autochat_data((string)$autochatControle['updated_at'], true)) ?>">
                            Irene <?= $autochatLigado ? 'ON' : 'OFF' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <span class="control-toggle<?= $autochatLigado ? ' on' : '' ?>">Irene <?= $autochatLigado ? 'ON' : 'OFF' ?></span>
                <?php endif; ?>
                <a class="refresh" href="/autochat">Atualizar</a>
            </div>
        </header>

        <div class="list">
            <?php if (!$conversas): ?>
                <div class="empty">
                    Nenhum atendimento da Irene registrado ainda.<br>
                    Com o AutoChat ligado, novos clientes entram aqui automaticamente.
                </div>
            <?php else: ?>
                <?php foreach ($conversas as $conversa): ?>
                    <?php
                    $ativa = $conversa['telefone'] === ($selecionada['telefone'] ?? '');
                    $iniciais = '';
                    foreach (preg_split('/\s+/', trim((string)$conversa['nome'])) ?: [] as $parteNome) {
                        $parteLimpa = preg_replace('/[^\p{L}\p{N}]+/u', '', $parteNome) ?? '';
                        if ($parteLimpa !== '') {
                            $iniciais .= mb_strtoupper(mb_substr($parteLimpa, 0, 1));
                        }
                        if (mb_strlen($iniciais) >= 2) {
                            break;
                        }
                    }
                    $horario = autochat_data($conversa['ultima_mensagem_em'] ?: $conversa['ultimo_evento']);
                    ?>
                    <a class="conv<?= $ativa ? ' active' : '' ?>" href="/autochat?telefone=<?= autochat_e($conversa['telefone']) ?>">
                        <div class="avatar"><?= autochat_e($iniciais !== '' ? $iniciais : '?') ?></div>
                        <div class="conv-body">
                            <div class="conv-top">
                                <span class="conv-name"><?= autochat_e((string)$conversa['nome']) ?></span>
                                <span class="conv-time"><?= autochat_e($horario) ?></span>
                            </div>
                            <div class="conv-preview"><?= autochat_e((string)$conversa['ultima_mensagem']) ?></div>
                            <div class="badges">
                                <?php if ($conversa['repassado']): ?>
                                    <span class="badge handoff">Passado p/ Hellen</span>
                                <?php elseif ($conversa['equipe_assumiu']): ?>
                                    <span class="badge equipe">Equipe assumiu</span>
                                <?php elseif ($conversa['ativo']): ?>
                                    <span class="badge ativa">IA ativa</span>
                                <?php else: ?>
                                    <span class="badge encerrada">Encerrado</span>
                                <?php endif; ?>
                                <?php if ((int)$conversa['respostas'] > 0): ?>
                                    <span class="badge encerrada"><?= (int)$conversa['respostas'] ?> resposta(s)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <main class="chat">
        <?php if ($selecionada === null): ?>
            <div class="empty">
                As conversas iniciadas pelo comando <strong>autochat</strong> aparecerão aqui.
            </div>
        <?php else: ?>
            <header class="chat-header">
                <div class="chat-title-row">
                    <a class="back" href="/autochat">← Conversas</a>
                    <div class="chat-title">
                        <strong><?= autochat_e((string)$selecionada['nome']) ?></strong>
                        <span>
                            +<?= autochat_e((string)$selecionada['telefone']) ?>
                            · iniciado em <?= autochat_e(autochat_data($selecionada['desde'], true)) ?>
                        </span>
                    </div>
                    <span class="readonly">Somente leitura</span>
                </div>
                <div class="dados">
                    <?php foreach ($camposDados as $campo => $rotulo): ?>
                        <?php $valor = trim((string)($selecionada['dados'][$campo] ?? '')); ?>
                        <div class="dado<?= $valor === '' ? ' vazio' : '' ?>">
                            <small><?= autochat_e($rotulo) ?></small>
                            <span><?= autochat_e($valor !== '' ? $valor : 'Não coletado') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </header>

            <section class="mensagens">
                <?php if (!$selecionada['mensagens']): ?>
                    <div class="empty">Nenhuma mensagem sincronizada para este contato.</div>
                <?php else: ?>
                    <?php foreach ($selecionada['mensagens'] as $mensagem): ?>
                        <?php
                        $midia = (string)($mensagem['midia'] ?? '');
                        $midiaUrl = $midia !== '' ? autochat_url_midia($midia) : '';
                        $tipoMidia = (string)($mensagem['tipo'] ?? '');
                        $extensaoMidia = strtolower(pathinfo($midia, PATHINFO_EXTENSION));
                        $ehImagem = in_array($tipoMidia, ['image', 'sticker'], true) || in_array($extensaoMidia, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                        $ehVideo = $tipoMidia === 'video' || in_array($extensaoMidia, ['mp4', 'm4v', 'mov', 'webm'], true);
                        $ehAudio = $tipoMidia === 'audio' || in_array($extensaoMidia, ['ogg', 'oga', 'opus', 'mp3', 'wav', 'm4a', 'aac'], true);
                        $textoMensagem = (string)($mensagem['texto'] ?? '');
                        $transcricao = (string)($mensagem['transcricao'] ?? '');
                        $mostrarTranscricao = $transcricao !== '' && ($ehAudio || $transcricao !== $textoMensagem);
                        $mostrarTexto = $textoMensagem !== '' && !($ehAudio && $transcricao !== '' && $textoMensagem === $transcricao);
                        ?>
                        <div class="bubble <?= $mensagem['de'] === 'estudio' ? 'out' : '' ?>">
                            <?php if ($midiaUrl !== '' && $ehImagem): ?>
                                <a class="midia" href="<?= autochat_e($midiaUrl) ?>" target="_blank" rel="noopener"><img src="<?= autochat_e($midiaUrl) ?>" alt="Imagem recebida no WhatsApp" loading="lazy"></a>
                            <?php elseif ($midiaUrl !== '' && $ehVideo): ?>
                                <span class="midia"><video controls playsinline preload="metadata" src="<?= autochat_e($midiaUrl) ?>"></video></span>
                            <?php elseif ($midiaUrl !== '' && $ehAudio): ?>
                                <span class="midia"><audio controls preload="metadata" src="<?= autochat_e($midiaUrl) ?>"></audio></span>
                            <?php elseif ($midiaUrl !== ''): ?>
                                <a class="midia arquivo" href="<?= autochat_e($midiaUrl) ?>" target="_blank" rel="noopener">📄 Abrir documento</a>
                            <?php endif; ?>
                            <?php if ($mostrarTexto && !($midiaUrl === '' && $mostrarTranscricao)): ?>
                                <span class="texto"><?= autochat_e($textoMensagem) ?></span>
                            <?php endif; ?>
                            <?php if ($mostrarTranscricao): ?>
                                <div class="transcricao"><strong>Transcrição</strong><?= autochat_e($transcricao) ?></div>
                            <?php elseif ($ehAudio && !empty($mensagem['erro_transcricao'])): ?>
                                <div class="midia-erro">Transcrição indisponível: <?= autochat_e((string)$mensagem['erro_transcricao']) ?></div>
                            <?php endif; ?>
                            <span class="meta"><?= autochat_e(autochat_data((string)$mensagem['em'], true)) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <footer class="footer">
                Visualização apenas. Nenhuma mensagem é enviada ou alterada por esta página.
            </footer>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
