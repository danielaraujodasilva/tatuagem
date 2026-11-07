<?php
$phone = '5511999979785';
$msg = 'Oi Daniel! Vim pela página de fechamento e quero um orçamento de fechamento em uma sessão.';
$whatsapp = 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
$clickId = (string)($_GET['gclid'] ?? ($_GET['wbraid'] ?? ($_GET['gbraid'] ?? '')));
if ($clickId !== '' && preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $clickId) === 1) {
    $whatsapp = '../projetocrm/ir.php?src=google&gclid=' . rawurlencode($clickId) . '&msg=' . rawurlencode($msg);
}
$reviews = [];
$reviewsFile = dirname(__DIR__) . '/reviews.json';
if (is_file($reviewsFile)) {
    $decoded = json_decode((string)@file_get_contents($reviewsFile), true);
    if (is_array($decoded)) { $reviews = array_slice($decoded, 0, 3); }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#060504">
<link rel="icon" type="image/svg+xml" href="/img/favicon1.svg?v=20261107">
<title>Fechamento em 1 sessão em Artur Alvim | Daniel Araujo Tatuador</title>
<meta name="description" content="Tatuador em Artur Alvim, Zona Leste. Fechamento em uma sessão, realismo e black & grey. Orçamento direto no WhatsApp, sem compromisso.">
<meta name="robots" content="index, follow, max-image-preview:large">
<meta name="geo.region" content="BR-SP">
<meta name="geo.placename" content="São Paulo, Artur Alvim, Zona Leste">
<meta name="geo.position" content="-23.5361826;-46.4819708">
<link rel="canonical" href="https://danieltatuador.com/fechamento/">
<meta property="og:title" content="Fechamento em 1 sessão em Artur Alvim | Daniel Araujo Tatuador">
<meta property="og:description" content="Fechamento em uma sessão, realismo e black & grey em Artur Alvim, Zona Leste. Orçamento no WhatsApp.">
<meta property="og:image" content="https://danieltatuador.com/img/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:type" content="website">
<meta property="og:url" content="https://danieltatuador.com/fechamento/">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Fechamento em 1 sessão em Artur Alvim | Daniel Araujo">
<meta name="twitter:description" content="Fechamento em uma sessão, realismo e black & grey em Artur Alvim, Zona Leste.">
<meta name="twitter:image" content="https://danieltatuador.com/img/og-image.jpg">
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-17619660621"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'AW-17619660621');
gtag('config', 'G-CQHHDSD0PK');
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Service",
      "name": "Fechamento de tatuagem em uma sessão",
      "serviceType": "Tatuagem",
      "provider": {
        "@type": "TattooParlor",
        "name": "Daniel Araujo Tatuador",
        "telephone": "+5511999979785",
        "address": {
          "@type": "PostalAddress",
          "streetAddress": "Rua Catende, 287B, Jardim Nordeste",
          "addressLocality": "São Paulo",
          "addressRegion": "SP",
          "addressCountry": "BR"
        }
      },
      "areaServed": {"@type": "City", "name": "São Paulo"},
      "offers": {
        "@type": "Offer",
        "priceCurrency": "BRL",
        "price": "799",
        "description": "Fechamento promocional a partir de R$ 799, em preto e branco e conforme as regras da promoção."
      }
    },
    {
      "@type": "FAQPage",
      "mainEntity": [
        {"@type": "Question", "name": "Quanto custa um fechamento?", "acceptedAnswer": {"@type": "Answer", "text": "Os fechamentos promocionais começam em R$ 799, em preto e branco e conforme as regras de cada promoção. Projetos autorais grandes, coberturas e composições com vários elementos são orçados à parte pelo WhatsApp."}},
        {"@type": "Question", "name": "O fechamento é feito em uma sessão?", "acceptedAnswer": {"@type": "Answer", "text": "Sim, o fechamento é planejado para resolver o máximo possível em uma única sessão. Projetos muito grandes podem precisar de retorno, combinado antes."}},
        {"@type": "Question", "name": "Como funciona o sinal?", "acceptedAnswer": {"@type": "Answer", "text": "O horário é reservado com sinal de R$ 50, abatido no valor final. O sinal só é perdido em caso de falta no agendamento."}},
        {"@type": "Question", "name": "Onde fica o estúdio?", "acceptedAnswer": {"@type": "Answer", "text": "Rua Catende, 287B, Jardim Nordeste, São Paulo, na Zona Leste, perto da Estação Artur Alvim."}}
      ]
    }
  ]
}
</script>
<style>
  :root{--gold:#c9964a;--gold2:#e2b871;--text:#f8f3ea;--muted:#b9afa0;--line:rgba(226,184,113,.18);--max:1120px}
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{margin:0;background:radial-gradient(circle at 80% 0,rgba(201,150,74,.16),transparent 28%),linear-gradient(180deg,#040302,#0b0907 55%,#050403);color:var(--text);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;line-height:1.6;overflow-x:hidden}
  a{color:inherit;text-decoration:none}
  .wrap{width:min(var(--max),calc(100% - 36px));margin:auto}
  .top{position:sticky;top:0;z-index:50;background:rgba(6,5,4,.88);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
  .top-in{min-height:74px;display:flex;align-items:center;justify-content:space-between;gap:18px}
  .brand{display:flex;align-items:center;gap:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
  .mark{width:44px;height:44px;display:grid;place-items:center;border:1px solid rgba(226,184,113,.55);color:var(--gold2);font-family:Arial Black,Arial,sans-serif;font-size:18px}
  .brand small{display:block;color:var(--muted);font-size:10px;letter-spacing:.28em;margin-top:3px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:50px;padding:14px 20px;border-radius:8px;border:1px solid rgba(226,184,113,.7);background:rgba(10,8,5,.6);color:var(--gold2);text-transform:uppercase;font-size:12px;font-weight:900;letter-spacing:.07em;cursor:pointer;transition:.2s}
  .btn:hover{background:var(--gold);border-color:var(--gold);color:#111;transform:translateY(-1px)}
  .btn-fill{background:linear-gradient(135deg,var(--gold2),var(--gold));color:#111;border-color:transparent}
  .top .btn{min-height:44px;padding:10px 15px}
  .hero{padding:52px 0 46px;border-bottom:1px solid var(--line)}
  .hero-grid{display:grid;grid-template-columns:1.02fr .98fr;gap:44px;align-items:center}
  .eyebrow{display:inline-flex;align-items:center;gap:10px;color:var(--muted);font-size:11px;letter-spacing:.22em;text-transform:uppercase;font-weight:900}
  .eyebrow:before{content:'';width:34px;height:1px;background:var(--gold)}
  h1{font-family:'Barlow Condensed',Impact,'Arial Black',Arial,sans-serif;font-size:clamp(42px,5.4vw,70px);line-height:.95;text-transform:uppercase;letter-spacing:.02em;margin:16px 0 0}
  h1 strong{color:var(--gold2)}
  .sub{color:#ded4c6;font-size:17px;line-height:1.7;margin:18px 0 0;max-width:560px}
  .price{margin:22px 0 0;display:inline-flex;flex-wrap:wrap;gap:8px;align-items:baseline;border:1px solid rgba(226,184,113,.34);border-radius:10px;padding:12px 15px;background:rgba(201,150,74,.09)}
  .price b{font-family:'Barlow Condensed',Impact,sans-serif;font-size:30px;letter-spacing:.03em;color:var(--gold2);text-transform:uppercase}
  .price span{font-size:12px;color:var(--muted)}
  .actions{display:flex;flex-wrap:wrap;gap:12px;margin:22px 0 0}
  .trust{display:flex;flex-wrap:wrap;gap:8px 18px;margin:20px 0 0;padding:0;list-style:none;color:var(--muted);font-size:13px}
  .trust li:before{content:'✓';color:var(--gold2);margin-right:7px}
  .hero-media{position:relative;border:1px solid rgba(226,184,113,.25);border-radius:14px;overflow:hidden;min-height:460px;background:#0b0907}
  .hero-media img{display:block;width:100%;height:100%;min-height:460px;object-fit:cover;filter:saturate(.8) contrast(1.06)}
  .hero-media:after{content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.55),transparent 45%)}
  .hero-tag{position:absolute;left:16px;bottom:16px;z-index:2;display:inline-flex;align-items:center;gap:8px;background:rgba(6,5,4,.82);border:1px solid rgba(226,184,113,.4);border-radius:99px;padding:9px 13px;color:var(--gold2);font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
  .section{padding:58px 0;border-bottom:1px solid var(--line)}
  .sec-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}
  h2{font-family:'Barlow Condensed',Impact,sans-serif;font-size:clamp(30px,3.4vw,44px);line-height:1;text-transform:uppercase;letter-spacing:.06em;margin:0}
  .kicker{color:var(--muted);font-size:14px;margin:8px 0 0}
  .gal{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .gal img{display:block;width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:10px;border:1px solid rgba(226,184,113,.18);filter:saturate(.85) contrast(1.05);background:#0b0907}
  .gal img:first-child{grid-column:span 2;grid-row:span 2;aspect-ratio:1/1}
  .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
  .step{border:1px solid rgba(226,184,113,.16);border-radius:12px;background:rgba(255,255,255,.03);padding:20px}
  .step b{display:block;font-family:'Barlow Condensed',Impact,sans-serif;font-size:30px;color:var(--gold2);line-height:1}
  .step strong{display:block;text-transform:uppercase;letter-spacing:.06em;font-size:15px;margin-top:8px}
  .step span{display:block;color:var(--muted);font-size:13px;line-height:1.5;margin-top:5px}
  .reviews{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
  .review{border:1px solid rgba(226,184,113,.16);border-radius:12px;background:rgba(255,255,255,.03);padding:20px}
  .stars{color:var(--gold2);letter-spacing:.12em;font-size:14px}
  .review strong{display:block;margin-top:6px}
  .review p{color:var(--muted);font-size:13px;line-height:1.65;margin:8px 0 0}
  .faq{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  details{border:1px solid rgba(226,184,113,.16);border-radius:10px;background:rgba(255,255,255,.03);padding:0 16px}
  summary{cursor:pointer;list-style:none;padding:15px 0;font-weight:800;display:flex;justify-content:space-between;gap:14px}
  summary:after{content:'+';color:var(--gold2);font-size:20px;line-height:1}
  details[open] summary:after{content:'−'}
  details p{color:var(--muted);font-size:13px;line-height:1.65;margin:0;padding:0 0 15px}
  .final{margin:44px 0 0;border:1px solid rgba(226,184,113,.34);border-radius:14px;padding:26px;background:linear-gradient(90deg,rgba(201,150,74,.16),rgba(201,150,74,.04));display:grid;grid-template-columns:1fr auto;gap:18px;align-items:center}
  .final h2{font-size:clamp(26px,3vw,36px)}
  .final p{color:var(--muted);margin:8px 0 0;font-size:14px}
  footer{padding:26px 0 92px;color:var(--muted);font-size:13px}
  .foot{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
  .foot a{color:var(--gold2)}
  .mobile-bar{display:none;position:fixed;left:14px;right:14px;bottom:14px;z-index:60}
  @media(max-width:900px){
    .hero-grid{grid-template-columns:1fr;gap:24px}
    .hero-media,.hero-media img{min-height:340px}
    .gal{grid-template-columns:repeat(2,1fr)}
    .gal img:first-child{grid-column:span 2;grid-row:auto}
    .steps,.reviews{grid-template-columns:1fr}
    .faq{grid-template-columns:1fr}
    .final{grid-template-columns:1fr}
  }
  @media(max-width:600px){
    .hero{padding:34px 0 36px}
    .top .btn{display:none}
    h1{font-size:clamp(38px,10.5vw,52px)}
    .actions .btn{width:100%}
    .mobile-bar{display:block}
  }
  @media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
</style>
</head>
<body>
<header class="top"><div class="wrap top-in">
  <a class="brand" href="/" aria-label="Daniel Araujo Tatuador — site principal"><span class="mark">DT</span><span>Daniel Araujo<small>Tatuador • Artur Alvim</small></span></a>
  <a class="btn btn-fill" href="<?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" onclick="trackLead('lp_header')">Pedir orçamento</a>
</div></header>

<main>
  <section class="hero"><div class="wrap hero-grid">
    <div>
      <span class="eyebrow">Tatuador em Artur Alvim • Zona Leste</span>
      <h1>Tatuador em Artur Alvim.<br><strong>Fechamento em 1 sessão.</strong></h1>
      <p class="sub">Realismo e black &amp; grey com projeto pensado para o corpo. Atendimento a poucos minutos do metrô Artur Alvim, na Zona Leste de São Paulo.</p>
      <div class="price"><b>Fechamento a partir de R$ 799</b><span>preto e branco, conforme as regras da promoção</span></div>
      <div class="actions">
        <a class="btn btn-fill" href="<?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" onclick="trackLead('lp_hero')">Pedir orçamento no WhatsApp</a>
        <a class="btn" href="https://www.google.com/maps/dir/?api=1&amp;destination=Rua+Catende%2C+287B%2C+Jardim+Nordeste%2C+S%C3%A3o+Paulo%2C+SP" target="_blank" rel="noopener" onclick="trackRoute()">Ver rota no Google Maps</a>
      </div>
      <ul class="trust"><li>Orçamento sem compromisso</li><li>Sinal de R$ 50 abatido no valor</li><li>+10 anos de experiência</li><li>Higiene e materiais descartáveis</li></ul>
      <p style="margin:18px 0 0;color:var(--muted);font-size:13px">Prefere ligar? <a href="tel:+5511999979785" onclick="trackPhone('lp_phone')" style="color:var(--gold2);font-weight:800">(11) 99997-9785</a></p>
    </div>
    <div class="hero-media">
      <img src="/img/bg6.jpg" alt="Tatuagem black & grey de samurai feita por Daniel Araujo" width="800" height="800" fetchpriority="high">
      <span class="hero-tag">Fechamento em uma sessão</span>
    </div>
  </div></section>

  <section class="section"><div class="wrap">
    <div class="sec-head"><div><h2>Trabalhos recentes</h2><p class="kicker">Uma amostra do estilo: contraste, leitura e encaixe no corpo.</p></div><a class="btn" href="/#portfolio">Ver galeria completa</a></div>
    <div class="gal">
      <img src="/img/bg1.jpg" alt="Tatuagem autoral de Daniel Araujo" width="800" height="800" loading="lazy" decoding="async">
      <img src="/img/bg2.jpg" alt="Tatuagem black & grey de Daniel Araujo" width="800" height="800" loading="lazy" decoding="async">
      <img src="/img/bg3.jpg" alt="Tatuagem realista de Daniel Araujo" width="800" height="800" loading="lazy" decoding="async">
      <img src="/img/bg8.jpg" alt="Tatuagem autoral no estúdio em Artur Alvim" width="800" height="800" loading="lazy" decoding="async">
      <img src="/img/eu.jpg" alt="Daniel Araujo tatuando no estúdio em Artur Alvim" width="1200" height="1600" loading="lazy" decoding="async">
    </div>
  </div></section>

  <section class="section"><div class="wrap">
    <div class="sec-head"><div><h2>Como funciona</h2><p class="kicker">Simples, direto e sem surpresa no valor.</p></div></div>
    <div class="steps">
      <div class="step"><b>1</b><strong>Manda a ideia</strong><span>Referência, tamanho aproximado e local do corpo no WhatsApp.</span></div>
      <div class="step"><b>2</b><strong>Recebe o orçamento</strong><span>Valor fechado e prazo, sem compromisso.</span></div>
      <div class="step"><b>3</b><strong>Reserva o horário</strong><span>Sinal de R$ 50, abatido no valor final da tatuagem.</span></div>
    </div>
  </div></section>

  <?php if ($reviews): ?>
  <section class="section"><div class="wrap">
    <div class="sec-head"><div><h2>O que dizem os clientes</h2><p class="kicker">Avaliações reais publicadas no Google Maps.</p></div><a class="btn" href="https://www.google.com/maps/search/?api=1&amp;query=Daniel+Araujo+Tatuador+Rua+Catende+287B+Jardim+Nordeste+S%C3%A3o+Paulo" target="_blank" rel="noopener">Ver no Google</a></div>
    <div class="reviews">
      <?php foreach ($reviews as $r): $author = (string)($r['author'] ?? 'Cliente'); $text = (string)($r['text'] ?? ''); $rating = (int)($r['rating'] ?? 5); ?>
      <article class="review"><div class="stars"><?= str_repeat('★', max(1, min(5, $rating))) ?></div><strong><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8') ?></strong><p>“<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>”</p></article>
      <?php endforeach; ?>
    </div>
  </div></section>
  <?php endif; ?>

  <section class="section"><div class="wrap">
    <div class="sec-head"><div><h2>Perguntas frequentes</h2><p class="kicker">O que mais perguntam antes de fechar.</p></div></div>
    <div class="faq">
      <details open><summary>Quanto custa um fechamento?</summary><p>Os fechamentos promocionais começam em R$ 799, em preto e branco e conforme as regras de cada promoção. Projetos autorais grandes, coberturas e composições com vários elementos são orçados à parte pelo WhatsApp.</p></details>
      <details><summary>O fechamento é feito em uma sessão?</summary><p>Sim, o fechamento é planejado para resolver o máximo possível em uma única sessão. Projetos muito grandes podem precisar de retorno, combinado antes.</p></details>
      <details><summary>Como funciona o sinal?</summary><p>O horário é reservado com sinal de R$ 50, abatido no valor final. O sinal só é perdido em caso de falta no agendamento.</p></details>
      <details><summary>Onde fica o estúdio?</summary><p>Rua Catende, 287B, Jardim Nordeste, São Paulo, na Zona Leste, perto da Estação Artur Alvim.</p></details>
    </div>
    <div class="final"><div><h2>Vamos fechar sua ideia?</h2><p>Manda a referência no WhatsApp e recebe o orçamento sem compromisso.</p></div><a class="btn btn-fill" href="<?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" onclick="trackLead('lp_final')">Chamar no WhatsApp</a></div>
  </div></section>
</main>

<footer><div class="wrap foot">
  <span>© <?= date('Y') ?> Daniel Araujo Tatuador • Rua Catende, 287B, Jd Nordeste, São Paulo/SP</span>
  <span><a href="/">Site principal</a> · <a href="/privacy.html">Privacidade</a> · <a href="/terms.html">Termos</a> · <a href="tel:+5511999979785" onclick="trackPhone('lp_footer')">(11) 99997-9785</a></span>
</div></footer>

<a class="btn btn-fill mobile-bar" href="<?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" onclick="trackLead('lp_mobile')">Pedir orçamento no WhatsApp</a>

<script>
window.dataLayer = window.dataLayer || [];
function trackLead(label){try{dataLayer.push({event:'whatsapp_click',label:label});if(typeof gtag==='function')gtag('event','conversion',{send_to:'AW-17619660621/o6mzCPX--qUbEM3e2tFB',event_category:'WhatsApp',event_label:label,transport_type:'beacon'});}catch(e){console.warn(e)}}
function trackRoute(){try{dataLayer.push({event:'route_click',label:'google_maps'});if(typeof gtag==='function')gtag('event','conversion',{send_to:'AW-17619660621/Jaj6CPL--qUbEM3e2tFB',event_category:'Ver rota',event_label:'google_maps',transport_type:'beacon'});}catch(e){console.warn(e)}}
function trackPhone(label){try{dataLayer.push({event:'phone_click',label:label});}catch(e){console.warn(e)}}
</script>
</body>
</html>
