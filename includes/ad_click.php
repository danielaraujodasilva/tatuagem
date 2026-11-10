<?php
declare(strict_types=1);

/**
 * Persistencia do click id do Google Ads (gclid / wbraid / gbraid).
 *
 * Problema que isto resolve: o id do clique so existia na URL da pagina em que
 * o visitante caiu. Se ele navegasse para outra pagina do site (home -> fechamento,
 * home -> orcamento, galeria etc.), a URL seguinte nao tinha mais o id e o botao
 * do WhatsApp mandava o cliente direto para o wa.me, sem passar pelo /projetocrm/ir.php.
 * Resultado: o CRM nao gravava a origem e o Google Ads nao recebia a conversao.
 *
 * Agora o primeiro acesso com ?gclid= (ou wbraid/gbraid) grava o id num cookie
 * first-party de 30 dias. Todas as paginas que usam este helper reaproveitam o
 * cookie e mandam o clique pelo ir.php, mesmo que a URL atual nao tenha o parametro.
 */

const AD_CLICK_COOKIE_TIPO  = 'ad_click_tipo';
const AD_CLICK_COOKIE_VALOR = 'ad_click_valor';
const AD_CLICK_DIAS         = 30;

/** Os click ids do Google tem este formato (gclid, wbraid e gbraid). */
function ad_click_valido(string $valor): bool
{
    return preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $valor) === 1;
}

function ad_click_cookie_opcoes(): array
{
    return [
        'expires'  => time() + 60 * 60 * 24 * AD_CLICK_DIAS,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Lax',
    ];
}

/**
 * Le o click id da URL e, se existir, persiste no cookie.
 * Deve ser chamada no topo da pagina, antes de qualquer HTML.
 */
function ad_click_capturar(): void
{
    foreach (['gclid', 'wbraid', 'gbraid'] as $tipo) {
        $valor = trim((string)($_GET[$tipo] ?? ''));
        if ($valor === '' || !ad_click_valido($valor)) {
            continue;
        }
        $_COOKIE[AD_CLICK_COOKIE_TIPO]  = $tipo;
        $_COOKIE[AD_CLICK_COOKIE_VALOR] = $valor;
        if (!headers_sent()) {
            $opcoes = ad_click_cookie_opcoes();
            setcookie(AD_CLICK_COOKIE_TIPO, $tipo, $opcoes);
            setcookie(AD_CLICK_COOKIE_VALOR, $valor, $opcoes);
        }
        return;
    }
}

/**
 * Click id atual (URL ou cookie), no formato ['tipo' => 'gclid', 'valor' => '...'].
 * Retorna null quando o visitante nao veio de anuncio do Google.
 */
function ad_click_atual(): ?array
{
    foreach (['gclid', 'wbraid', 'gbraid'] as $tipo) {
        $valor = trim((string)($_GET[$tipo] ?? ''));
        if ($valor !== '' && ad_click_valido($valor)) {
            return ['tipo' => $tipo, 'valor' => $valor];
        }
    }

    $tipo  = strtolower(trim((string)($_COOKIE[AD_CLICK_COOKIE_TIPO] ?? '')));
    $valor = trim((string)($_COOKIE[AD_CLICK_COOKIE_VALOR] ?? ''));
    if (in_array($tipo, ['gclid', 'wbraid', 'gbraid'], true) && ad_click_valido($valor)) {
        return ['tipo' => $tipo, 'valor' => $valor];
    }

    return null;
}

/**
 * Query string para o ir.php (sem "?"), preservando o tipo do click id.
 * Retorna '' quando nao ha clique do Google.
 */
function ad_click_query(string $msg = '', string $src = 'google'): string
{
    $click = ad_click_atual();
    if ($click === null) {
        return '';
    }
    $params = ['src' => $src, $click['tipo'] => $click['valor']];
    if ($msg !== '') {
        $params['msg'] = $msg;
    }
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/**
 * URL do botao de WhatsApp: passa pelo ir.php quando ha clique do Google,
 * senao devolve o link direto do wa.me (fallback).
 */
function ad_click_whatsapp_url(string $fallback, string $msg = '', string $src = 'google'): string
{
    $query = ad_click_query($msg, $src);
    return $query === '' ? $fallback : '/projetocrm/ir.php?' . $query;
}
