<?php
declare(strict_types=1);
/**
 * Salva a voz escolhida na tela Voz (motor, voz, sentimento, velocidade e tom).
 * O que fica salvo aqui e o que o sistema usa: conversa, simulador e audio
 * automatico. Nada disso fala com cliente.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff();

header('Content-Type: application/json; charset=utf-8');

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = $_POST;
}

$acao = (string)($in['action'] ?? 'salvar');

if ($acao === 'limpar') {
    @unlink(v2_voz_arquivo());
    echo json_encode(['ok' => true, 'salva' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$engine = preg_replace('/[^a-z]/', '', strtolower((string)($in['engine'] ?? ''))) ?: 'windows';
if (!isset(v2_tts_presets()[$engine])) {
    $engine = 'windows';
}

$variacao = preg_replace('/[^a-z_]/', '', strtolower((string)($in['variacao'] ?? ''))) ?: 'natural';
if (!in_array($variacao, array_merge(['natural'], v2_tts_variacoes($engine)), true)) {
    $variacao = 'natural';
}

$voz = trim(preg_replace('/[^A-Za-z0-9 _.\-:\\\\\/]/', '', (string)($in['voz'] ?? '')) ?? '');
$voz = mb_substr($voz, 0, 120);
$opcoes = v2_voz_opcoes()[$engine] ?? [];
if ($voz === '' || ($opcoes !== [] && !isset($opcoes[$voz])) || ($engine === 'piper' && !is_file($voz))) {
    $voz = v2_voz_padrao($engine);
}

$dados = [
    'engine' => $engine,
    'voz' => $voz,
    'variacao' => $variacao,
    'vel' => max(-40, min(80, (int)($in['vel'] ?? 0))),
    'tom' => max(-20, min(20, (int)($in['tom'] ?? 0))),
    'salvo_em' => date('c'),
];

if (!v2_voz_gravar($dados)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'nao consegui salvar a voz'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'salva' => true, 'voz' => $dados], JSON_UNESCAPED_UNICODE);
