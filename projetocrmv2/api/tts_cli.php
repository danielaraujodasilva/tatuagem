<?php
declare(strict_types=1);
/**
 * Uso interno: gera a fala com a voz salva (tela Voz) sem passar pelo login.
 * Roda local (CLI): le o texto do stdin e joga o mp3 no stdout.
 * Quem usa: a ponte pessoal do WhatsApp (whatsapp-openclaw-bridge).
 */
$texto = (string)stream_get_contents(STDIN);
$_GET = ['text' => $texto];

/* Motor opcional por variavel de ambiente (BRIDGE_TTS_ENGINE=piper|windows|...).
   Sem isso, vale a voz SALVA na tela Voz. */
$motor = trim((string)getenv('BRIDGE_TTS_ENGINE'));
if ($motor !== '') {
    $_GET['engine'] = $motor;
}
$vozPonte = trim((string)getenv('BRIDGE_TTS_VOICE'));
if ($vozPonte !== '') {
    $_GET['voice'] = $vozPonte;
}
$ritmoPonte = trim((string)getenv('BRIDGE_TTS_VARIANT'));
if ($ritmoPonte !== '') {
    $_GET['v'] = $ritmoPonte;
}
$velPonte = trim((string)getenv('BRIDGE_TTS_VEL'));
if ($velPonte !== '') {
    $_GET['vel'] = $velPonte;
}
$tomPonte = trim((string)getenv('BRIDGE_TTS_TOM'));
if ($tomPonte !== '') {
    $_GET['tom'] = $tomPonte;
}

require __DIR__ . '/tts.php';