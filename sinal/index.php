<?php
/**
 * Link do sinal: https://danieltatuador.com/sinal/
 *
 * POR QUE ESTE ARQUIVO EXISTE
 * O Daniel atende pelo celular e quer um link curto e facil de decorar/salvar.
 * Esta pasta so repassa o apelido que esta em projetocrmv2/cobrar.local.php e
 * chama a pagina de cobranca (que e quem gera o Pix, via ponte local).
 *
 * NAO tem senha de proposito: e um link de uso pessoal, nao divulgado, com
 * noindex. Se um dia quiser fechar, use o link com chave:
 *   /projetocrmv2/cobrar.php?k=CHAVE  (a chave fica em cobrar.local.php)
 */
$_GET['atalho'] = 'u2vmku753';

require __DIR__ . '/../projetocrmv2/cobrar.php';
