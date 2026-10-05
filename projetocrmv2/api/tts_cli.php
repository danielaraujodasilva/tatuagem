<?php
declare(strict_types=1);
/**
 * Uso interno: gera a fala com a voz salva (tela Voz) sem passar pelo login.
 * Roda local (CLI): le o texto do stdin e joga o mp3 no stdout.
 * Quem usa: a ponte pessoal do WhatsApp (whatsapp-openclaw-bridge).
 */
$texto = (string)stream_get_contents(STDIN);
$_GET = ['text' => $texto];
require __DIR__ . '/tts.php';