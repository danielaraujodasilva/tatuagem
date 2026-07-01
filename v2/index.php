<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <!-- Google Tag Manager -->
  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer','GTM-5XKC8WNX');</script>
  <!-- End Google Tag Manager -->

  <script async src="https://www.googletagmanager.com/gtag/js?id=AW-17619660621"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'AW-17619660621');
  </script>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#080706">
  <title>Daniel Araujo Tatuador | Tatuagens com propósito</title>
  <meta name="description" content="Tatuagens personalizadas em realismo, black & grey, fineline e projetos autorais. Atendimento com hora marcada e orçamento pelo WhatsApp.">
  <meta name="keywords" content="Daniel Araujo tatuador, tatuagem, realismo, black grey, fineline, tatuador em São Paulo, orçamento tatuagem">
  <meta name="author" content="Daniel Araujo">
  <meta property="og:title" content="Daniel Araujo Tatuador | Tatuagens com propósito">
  <meta property="og:description" content="Sua ideia, minha arte, nossa história. Projetos personalizados em realismo, black & grey e fineline.">
  <meta property="og:image" content="../img/og-image.jpg">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://danieltatuador.com/v2/">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Caveat:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" type="image/svg+xml" href="../img/favicon1.svg">

  <!-- Meta Pixel Code -->
  <script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', '734722824598560');
  fbq('track', 'PageView');
  </script>
  <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=734722824598560&ev=PageView&noscript=1" /></noscript>
  <!-- End Meta Pixel Code -->

  <style>
    :root {
      --bg: #070605;
      --bg-soft: #0f0d0a;
      --bg-card: #15110c;
      --gold: #c9964a;
      --gold-2: #e2b871;
      --gold-dark: #7d5928;
      --text: #f7f2e8;
      --muted: #b9afa0;
      --line: rgba(226,184,113,.24);
      --shadow: 0 28px 90px rgba(0,0,0,.55);
      --radius: 24px;
      --max: 1180px;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at 72% 12%, rgba(201,150,74,.18), transparent 30%),
        radial-gradient(circle at 12% 40%, rgba(201,150,74,.09), transparent 28%),
        linear-gradient(180deg, #040302 0%, #0b0907 45%, #060504 100%);
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image:
        linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);
      background-size: 52px 52px;
      mask-image: linear-gradient(to bottom, rgba(0,0,0,.58), transparent 70%);
      z-index: 0;
    }

    a { color: inherit; text-decoration: none; }
    img { max-width: 100%; display: block; }
    button, a { -webkit-tap-highlight-color: transparent; }

    .page { position: relative; z-index: 1; }
    .container { width: min(var(--max), calc(100% - 40px)); margin: 0 auto; }

    .site-shell {
      width: min(1500px, calc(100% - 28px));
      margin: 20px auto;
      border: 1px solid var(--line);
      border-radius: 22px;
      overflow: hidden;
      background: rgba(7,6,5,.8);
      box-shadow: var(--shadow);
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 30;
      backdrop-filter: blur(22px);
      background: rgba(7,6,5,.72);
      border-bottom: 1px solid rgba(226,184,113,.13);
    }

    .topbar-inner {
      width: min(var(--max), calc(100% - 40px));
      margin: 0 auto;
      min-height: 82px;
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      gap: 28px;
    }

    .brand { display: inline-flex; align-items: center; gap: 13px; min-width: max-content; }
    .brand-mark {
      width: 48px; height: 48px;
      border: 1px solid rgba(226,184,113,.55);
      display: grid; place-items: center;
      color: var(--gold-2);
      font-family: 'Barlow Condensed', sans-serif;
      font-weight: 800;
      font-size: 22px;
      letter-spacing: -1px;
      background: rgba(0,0,0,.28);
    }
    .brand-name {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 26px;
      line-height: .84;
      letter-spacing: .18em;
      font-weight: 800;
      text-transform: uppercase;
    }
    .brand-name span { display: block; color: var(--muted); font-size: 14px; letter-spacing: .32em; margin-top: 8px; }

    .nav { display: flex; justify-content: center; align-items: center; gap: 28px; }
    .nav a {
      position: relative;
      color: rgba(247,242,232,.82);
      font-size: 13px;
      font-weight: 700;
      letter-spacing: .05em;
      transition: color .2s ease;
    }
    .nav a:hover, .nav a.active { color: var(--gold-2); }
    .nav a.active::after, .nav a:hover::after {
      content: '';
      position: absolute;
      left: 0; right: 0; bottom: -12px;
      height: 2px;
      background: var(--gold);
    }

    .header-actions { display: inline-flex; align-items: center; gap: 14px; }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      border-radius: 9px;
      border: 1px solid rgba(226,184,113,.72);
      padding: 16px 22px;
      min-height: 52px;
      color: var(--gold-2);
      background: rgba(10,8,5,.62);
      font-size: 13px;
      font-weight: 900;
      letter-spacing: .08em;
      text-transform: uppercase;
      transition: transform .2s ease, background .2s ease, color .2s ease, border-color .2s ease;
      cursor: pointer;
    }
    .btn:hover { transform: translateY(-2px); background: var(--gold); color: #111; border-color: var(--gold); }
    .btn-filled { background: linear-gradient(135deg, var(--gold-2), var(--gold)); color: #111; border-color: transparent; box-shadow: 0 12px 34px rgba(201,150,74,.22); }
    .btn-filled:hover { filter: brightness(1.04); }
    .menu-toggle { display: none; width: 50px; height: 50px; padding: 0; }

    .hero {
      position: relative;
      min-height: 760px;
      display: grid;
      align-items: center;
      overflow: hidden;
      border-bottom: 1px solid rgba(226,184,113,.14);
      background:
        linear-gradient(90deg, rgba(5,4,3,1) 0%, rgba(5,4,3,.94) 32%, rgba(5,4,3,.42) 67%, rgba(5,4,3,1) 100%),
        url('../img/bg.jfif') center/cover no-repeat;
    }

    .hero::before {
      content: '';
      position: absolute;
      width: min(520px, 72vw);
      aspect-ratio: 1;
      border: 4px solid rgba(226,184,113,.78);
      border-radius: 50%;
      right: 14%;
      top: 14%;
      filter: drop-shadow(0 0 42px rgba(226,184,113,.3));
      opacity: .88;
    }
    .hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background:
        linear-gradient(to bottom, rgba(0,0,0,.15), rgba(0,0,0,.1) 55%, rgba(0,0,0,.92) 100%),
        radial-gradient(circle at 78% 50%, rgba(201,150,74,.12), transparent 32%);
      pointer-events: none;
    }

    .hero-grid {
      position: relative;
      z-index: 2;
      width: min(var(--max), calc(100% - 40px));
      margin: 0 auto;
      padding: 84px 0 120px;
      display: grid;
      grid-template-columns: 1.03fr .97fr;
      gap: 38px;
      align-items: center;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1;
      letter-spacing: .24em;
      text-transform: uppercase;
      font-weight: 800;
      margin-bottom: 20px;
    }
    .eyebrow::before { content: ''; width: 46px; height: 1px; background: var(--gold); }

    .hero-title {
      margin: 0;
      font-family: 'Caveat', cursive;
      font-size: clamp(66px, 9.5vw, 126px);
      line-height: .78;
      letter-spacing: -.04em;
      text-transform: uppercase;
      text-shadow: 0 8px 40px rgba(0,0,0,.55);
    }
    .hero-title strong { color: var(--gold); font-weight: 700; display: inline-block; }
    .hero-sub {
      margin: 28px 0 0;
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
      color: rgba(247,242,232,.9);
      font-family: 'Barlow Condensed', sans-serif;
      font-size: clamp(22px, 2.2vw, 30px);
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .hero-sub span:not(:last-child)::after { content: '•'; color: var(--gold); margin-left: 12px; }
    .hero-copy { max-width: 520px; margin: 24px 0 0; color: var(--muted); line-height: 1.8; font-size: 16px; }
    .hero-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 34px; }

    .social-row { display: flex; gap: 18px; margin-top: 34px; }
    .social-row a {
      width: 43px; height: 43px;
      display: grid; place-items: center;
      border: 1px solid rgba(247,242,232,.18);
      border-radius: 50%;
      color: var(--text);
      background: rgba(255,255,255,.035);
      transition: .2s ease;
    }
    .social-row a:hover { color: #111; background: var(--gold); border-color: var(--gold); transform: translateY(-2px); }

    .hero-visual { align-self: stretch; position: relative; min-height: 590px; }
    .artist-silhouette {
      position: absolute;
      inset: 0 -6% -8% 5%;
      background: url('../img/daniel.jpg') center/cover no-repeat;
      border-radius: 42% 42% 0 0;
      filter: saturate(.78) contrast(1.1) brightness(.72);
      opacity: .58;
      mix-blend-mode: screen;
      mask-image: linear-gradient(to bottom, #000 60%, transparent 100%);
    }
    .hero-visual::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(7,6,5,.9), transparent 48%, rgba(7,6,5,.55));
    }

    .mini-gallery {
      position: absolute;
      z-index: 3;
      left: 0;
      right: -120px;
      bottom: 48px;
      display: grid;
      grid-template-columns: repeat(5, minmax(120px, 1fr));
      gap: 16px;
    }
    .mini-card, .portfolio-card {
      position: relative;
      overflow: hidden;
      border-radius: 13px;
      border: 1px solid rgba(226,184,113,.18);
      background: rgba(255,255,255,.035);
      isolation: isolate;
    }
    .mini-card { aspect-ratio: 1 / 1; box-shadow: 0 20px 55px rgba(0,0,0,.45); }
    .mini-card::after, .portfolio-card::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(0,0,0,.55), transparent 65%);
      pointer-events: none;
    }
    .mini-card div, .portfolio-card div, .about-photo div {
      width: 100%; height: 100%; background-size: cover; background-position: center; filter: saturate(.72) contrast(1.12); transition: transform .5s ease;
    }
    .mini-card:hover div, .portfolio-card:hover div { transform: scale(1.07); }

    .feature-strip {
      position: relative;
      z-index: 3;
      border-top: 1px solid rgba(226,184,113,.16);
      border-bottom: 1px solid rgba(226,184,113,.16);
      background: rgba(13,11,9,.92);
    }
    .feature-grid {
      width: min(var(--max), calc(100% - 40px));
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
    }
    .feature {
      min-height: 126px;
      display: grid;
      grid-template-columns: 52px 1fr;
      align-items: center;
      gap: 18px;
      padding: 24px;
      border-right: 1px solid rgba(226,184,113,.16);
    }
    .feature:first-child { border-left: 1px solid rgba(226,184,113,.16); }
    .feature i { color: var(--gold); font-size: 34px; }
    .feature strong {
      display: block;
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 21px;
      letter-spacing: .07em;
      text-transform: uppercase;
      line-height: 1;
    }
    .feature span { display: block; margin-top: 8px; color: var(--muted); font-size: 13px; line-height: 1.45; }

    section.block { padding: 76px 0; border-bottom: 1px solid rgba(226,184,113,.12); }
    .section-head {
      display: flex;
      justify-content: space-between;
      gap: 24px;
      align-items: end;
      margin-bottom: 28px;
    }
    .section-title {
      margin: 0;
      font-family: 'Barlow Condensed', sans-serif;
      font-size: clamp(34px, 4vw, 56px);
      line-height: .95;
      letter-spacing: .08em;
      text-transform: uppercase;
      font-weight: 700;
    }
    .section-title::after {
      content: '';
      display: block;
      width: 52px; height: 2px;
      background: var(--gold);
      margin-top: 14px;
    }
    .section-kicker { color: var(--muted); max-width: 570px; line-height: 1.7; margin: 12px 0 0; }
    .text-link { color: var(--gold-2); font-weight: 900; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; }

    .portfolio-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 16px;
    }
    .portfolio-card { min-height: 230px; }
    .portfolio-card.large { grid-column: span 2; min-height: 300px; }
    .portfolio-card.wide { grid-column: span 3; min-height: 250px; }
    .portfolio-card .label {
      position: absolute;
      z-index: 2;
      left: 18px;
      right: 18px;
      bottom: 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 24px;
      text-transform: uppercase;
      letter-spacing: .06em;
      font-weight: 700;
    }
    .portfolio-card .label small { color: var(--gold-2); font-family: 'Inter', sans-serif; font-size: 12px; letter-spacing: .08em; }

    .about-grid {
      display: grid;
      grid-template-columns: .9fr 1.1fr 280px;
      gap: 28px;
      align-items: stretch;
    }
    .about-photo, .about-copy, .stats-card {
      border: 1px solid rgba(226,184,113,.16);
      border-radius: 18px;
      background: rgba(255,255,255,.035);
      overflow: hidden;
    }
    .about-photo { min-height: 370px; }
    .about-copy { padding: 36px; }
    .about-copy p { color: var(--muted); line-height: 1.85; margin: 0 0 18px; }
    .signature { font-family: 'Caveat', cursive; color: var(--gold); font-size: 44px; line-height: 1; margin-top: 10px; }
    .stats-card { padding: 24px; display: grid; gap: 14px; }
    .stat {
      border: 1px solid rgba(226,184,113,.17);
      border-radius: 14px;
      padding: 20px;
      display: grid;
      grid-template-columns: 44px 1fr;
      align-items: center;
      gap: 14px;
      background: rgba(0,0,0,.18);
    }
    .stat i { color: var(--gold); font-size: 27px; }
    .stat b { display: block; color: var(--gold-2); font-size: 26px; line-height: 1; }
    .stat span { display: block; color: var(--muted); text-transform: uppercase; font-size: 11px; letter-spacing: .09em; line-height: 1.35; margin-top: 5px; }

    .steps {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 14px;
      counter-reset: step;
    }
    .step {
      position: relative;
      padding: 28px 20px;
      border: 1px solid rgba(226,184,113,.15);
      border-radius: 18px;
      background: linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.02));
      min-height: 210px;
    }
    .step::before {
      counter-increment: step;
      content: counter(step);
      width: 34px; height: 34px;
      border: 1px solid var(--gold);
      color: var(--gold-2);
      border-radius: 50%;
      display: grid; place-items: center;
      font-weight: 900;
      margin-bottom: 22px;
    }
    .step i { position: absolute; right: 22px; top: 27px; color: var(--gold); font-size: 30px; }
    .step h3 {
      margin: 0 0 10px;
      color: var(--gold-2);
      font-family: 'Barlow Condensed', sans-serif;
      text-transform: uppercase;
      letter-spacing: .08em;
      font-size: 25px;
    }
    .step p { margin: 0; color: var(--muted); line-height: 1.65; font-size: 14px; }

    .styles-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
    .style-card {
      position: relative;
      padding: 32px;
      border: 1px solid rgba(226,184,113,.15);
      border-radius: 18px;
      background: rgba(255,255,255,.035);
      overflow: hidden;
      min-height: 250px;
    }
    .style-card::after {
      content: '';
      position: absolute;
      right: -42px; bottom: -42px;
      width: 150px; aspect-ratio: 1;
      border: 1px solid rgba(226,184,113,.2);
      border-radius: 50%;
    }
    .style-card i { color: var(--gold); font-size: 34px; margin-bottom: 22px; }
    .style-card h3 {
      margin: 0 0 12px;
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 32px;
      letter-spacing: .07em;
      text-transform: uppercase;
    }
    .style-card p { color: var(--muted); line-height: 1.75; margin: 0; }

    .reviews { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
    .review {
      padding: 26px;
      border: 1px solid rgba(226,184,113,.15);
      border-radius: 18px;
      background: rgba(255,255,255,.035);
    }
    .stars { color: var(--gold-2); letter-spacing: .08em; margin-bottom: 16px; }
    .review p { color: var(--muted); line-height: 1.75; margin: 0 0 18px; }
    .review b { display: block; }
    .review span { color: var(--muted); font-size: 13px; }

    .faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    details {
      border: 1px solid rgba(226,184,113,.15);
      border-radius: 16px;
      background: rgba(255,255,255,.035);
      padding: 0 20px;
    }
    summary {
      cursor: pointer;
      padding: 20px 0;
      font-weight: 800;
      list-style: none;
      display: flex;
      justify-content: space-between;
      gap: 20px;
      align-items: center;
    }
    summary::-webkit-details-marker { display: none; }
    summary::after { content: '+'; color: var(--gold); font-size: 26px; line-height: 1; }
    details[open] summary::after { content: '−'; }
    details p { margin: 0; padding: 0 0 20px; color: var(--muted); line-height: 1.7; }

    .final-cta {
      margin: 80px 0 0;
      padding: 30px;
      border: 1px solid rgba(226,184,113,.36);
      border-radius: 18px;
      background:
        linear-gradient(90deg, rgba(201,150,74,.19), rgba(201,150,74,.05)),
        url('../img/bg2.jpg') center/cover no-repeat;
      display: grid;
      grid-template-columns: 80px 1fr auto;
      gap: 24px;
      align-items: center;
      overflow: hidden;
      position: relative;
    }
    .final-cta::before { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,.63); }
    .final-cta > * { position: relative; z-index: 1; }
    .final-cta i { color: var(--gold-2); font-size: 58px; }
    .final-cta h2 { margin: 0; font-family: 'Barlow Condensed', sans-serif; font-size: 38px; letter-spacing: .08em; text-transform: uppercase; }
    .final-cta p { margin: 5px 0 0; color: var(--muted); }

    .footer {
      padding: 26px 0;
      color: var(--muted);
      font-size: 13px;
    }
    .footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 20px; }

    .mobile-whatsapp {
      display: none;
      position: fixed;
      left: 14px; right: 14px; bottom: 14px;
      z-index: 50;
      box-shadow: 0 16px 40px rgba(0,0,0,.45);
    }

    .img-bg1 { background-image: url('../img/bg1.jpg'); }
    .img-bg2 { background-image: url('../img/bg2.jpg'); }
    .img-bg3 { background-image: url('../img/bg3.jpg'); }
    .img-bg4 { background-image: url('../img/bg4.jpg'); }
    .img-bg5 { background-image: url('../img/bg5.jpg'); }
    .img-bg6 { background-image: url('../img/bg6.jpg'); }
    .img-bg7 { background-image: url('../img/bg7.jpg'); }
    .img-bg8 { background-image: url('../img/bg8.jpg'); }
    .img-daniel { background-image: url('../img/daniel.jpg'); }

    @media (max-width: 1100px) {
      .nav { display: none; }
      .topbar-inner { grid-template-columns: auto 1fr auto; }
      .menu-toggle { display: inline-flex; }
      .desktop-only { display: none; }
      .hero-grid { grid-template-columns: 1fr; padding-top: 58px; }
      .hero { min-height: auto; }
      .hero::before { right: -120px; top: 70px; width: 420px; opacity: .52; }
      .hero-visual { min-height: 360px; }
      .artist-silhouette { inset: -10% -15% -4% 35%; opacity: .5; }
      .mini-gallery { right: auto; left: 0; bottom: 20px; width: 100%; grid-template-columns: repeat(5, 1fr); }
      .feature-grid { grid-template-columns: repeat(2, 1fr); }
      .about-grid { grid-template-columns: 1fr; }
      .stats-card { grid-template-columns: repeat(3, 1fr); }
      .steps { grid-template-columns: repeat(2, 1fr); }
      .styles-grid, .reviews { grid-template-columns: 1fr; }
      .portfolio-grid { grid-template-columns: repeat(2, 1fr); }
      .portfolio-card, .portfolio-card.large, .portfolio-card.wide { grid-column: span 1; min-height: 260px; }
      .final-cta { grid-template-columns: 1fr; }
    }

    @media (max-width: 720px) {
      .site-shell { margin: 0; width: 100%; border-radius: 0; border-left: 0; border-right: 0; }
      .container, .topbar-inner, .hero-grid, .feature-grid { width: min(100% - 28px, var(--max)); }
      .topbar-inner { min-height: 74px; gap: 14px; }
      .brand-mark { width: 42px; height: 42px; }
      .brand-name { font-size: 20px; letter-spacing: .15em; }
      .brand-name span { font-size: 11px; letter-spacing: .28em; }
      .header-actions .btn:not(.menu-toggle) { padding: 13px 14px; min-height: 46px; font-size: 12px; }
      .menu-toggle { width: 46px; height: 46px; }
      .hero-grid { padding: 42px 0 96px; gap: 12px; }
      .eyebrow { font-size: 11px; letter-spacing: .18em; }
      .hero-title { font-size: clamp(58px, 18vw, 92px); }
      .hero-sub { font-size: 20px; }
      .hero-copy { font-size: 14px; line-height: 1.7; }
      .hero-actions .btn { width: 100%; }
      .social-row { margin-top: 22px; }
      .hero-visual { min-height: 300px; }
      .artist-silhouette { inset: -22% -22% -2% 12%; opacity: .5; }
      .mini-gallery { display: flex; overflow-x: auto; padding-bottom: 4px; scroll-snap-type: x mandatory; }
      .mini-card { min-width: 132px; scroll-snap-align: start; }
      .feature-grid { display: flex; overflow-x: auto; }
      .feature { min-width: 240px; border-left: 1px solid rgba(226,184,113,.16); }
      section.block { padding: 56px 0; }
      .section-head { align-items: flex-start; flex-direction: column; }
      .portfolio-grid { grid-template-columns: 1fr; }
      .portfolio-card, .portfolio-card.large, .portfolio-card.wide { min-height: 230px; }
      .about-copy { padding: 26px; }
      .stats-card { grid-template-columns: 1fr; }
      .steps { grid-template-columns: 1fr; }
      .step { min-height: auto; }
      .faq-grid { grid-template-columns: 1fr; }
      .final-cta { margin-top: 54px; padding: 24px; }
      .final-cta h2 { font-size: 30px; }
      .footer { padding-bottom: 86px; }
      .footer-inner { flex-direction: column; align-items: flex-start; }
      .mobile-whatsapp { display: inline-flex; }
    }
  </style>
</head>
<body>
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5XKC8WNX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

  <?php
    $whatsapp = 'https://api.whatsapp.com/send?phone=5511947573311&text=' . rawurlencode('Oi Daniel! Vim pelo site e quero fazer um orçamento de tatuagem.');
  ?>

  <div class="page">
    <div class="site-shell">
      <header class="topbar">
        <div class="topbar-inner">
          <a class="brand" href="#inicio" aria-label="Daniel Tatuador">
            <span class="brand-mark">DT</span>
            <span class="brand-name">Daniel <span>Tatuador</span></span>
          </a>

          <nav class="nav" aria-label="Navegação principal">
            <a class="active" href="#inicio">Início</a>
            <a href="#portfolio">Portfólio</a>
            <a href="#sobre">Sobre</a>
            <a href="#estilos">Estilos</a>
            <a href="#processo">Processo</a>
            <a href="#faq">FAQ</a>
          </nav>

          <div class="header-actions">
            <a class="btn desktop-only" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('header_orcamento')">Orçamento <i class="fa-solid fa-arrow-right"></i></a>
            <button class="btn menu-toggle" type="button" aria-label="Abrir menu" onclick="document.body.classList.toggle('menu-open')"><i class="fa-solid fa-bars"></i></button>
          </div>
        </div>
      </header>

      <main>
        <section class="hero" id="inicio">
          <div class="hero-grid">
            <div class="hero-content">
              <span class="eyebrow">Projeto autoral na pele</span>
              <h1 class="hero-title">Sua ideia.<br>Minha arte.<br><strong>Nossa história.</strong></h1>
              <div class="hero-sub"><span>Realismo</span><span>Black & Grey</span><span>Fineline</span></div>
              <p class="hero-copy">Tatuagens personalizadas com leitura visual, acabamento limpo e um processo pensado para transformar referência solta em projeto com presença.</p>
              <div class="hero-actions">
                <a class="btn btn-filled" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('hero_agendar')"><i class="fa-regular fa-calendar-check"></i> Agendar horário</a>
                <a class="btn" href="#portfolio">Ver portfólio</a>
              </div>
              <div class="social-row" aria-label="Redes sociais">
                <a href="https://www.instagram.com/danielaraujo.tatuador/" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('social_whatsapp')" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                <a href="https://www.tiktok.com/@danielaraujotatuador" target="_blank" rel="noopener" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
              </div>
            </div>

            <div class="hero-visual" aria-hidden="true">
              <div class="artist-silhouette"></div>
              <div class="mini-gallery">
                <div class="mini-card"><div class="img-bg1"></div></div>
                <div class="mini-card"><div class="img-bg2"></div></div>
                <div class="mini-card"><div class="img-bg3"></div></div>
                <div class="mini-card"><div class="img-bg4"></div></div>
                <div class="mini-card"><div class="img-bg5"></div></div>
              </div>
            </div>
          </div>
        </section>

        <div class="feature-strip">
          <div class="feature-grid">
            <div class="feature"><i class="fa-regular fa-comments"></i><div><strong>Atendimento personalizado</strong><span>Cada projeto é pensado para o seu corpo e sua história.</span></div></div>
            <div class="feature"><i class="fa-solid fa-shield-heart"></i><div><strong>Higiene e segurança</strong><span>Procedimentos cuidadosos e materiais descartáveis.</span></div></div>
            <div class="feature"><i class="fa-regular fa-gem"></i><div><strong>Materiais premium</strong><span>Tintas e equipamentos de alta qualidade.</span></div></div>
            <div class="feature"><i class="fa-regular fa-star"></i><div><strong>Resultado exclusivo</strong><span>Arte criada para marcar, não para copiar catálogo.</span></div></div>
          </div>
        </div>

        <section class="block" id="portfolio">
          <div class="container">
            <div class="section-head">
              <div>
                <h2 class="section-title">Portfólio</h2>
                <p class="section-kicker">Uma seleção visual para mostrar acabamento, contraste, composição e leitura de pele. Ou seja: o que realmente importa depois que o hype passa.</p>
              </div>
              <a class="text-link" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('portfolio_whatsapp')">Pedir projeto <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <div class="portfolio-grid">
              <a class="portfolio-card large" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('portfolio_realismo')"><div class="img-bg1"></div><span class="label">Realismo <small>ver projeto</small></span></a>
              <a class="portfolio-card large" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('portfolio_blackgrey')"><div class="img-bg2"></div><span class="label">Black & Grey <small>ver projeto</small></span></a>
              <a class="portfolio-card large" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('portfolio_fineline')"><div class="img-bg3"></div><span class="label">Fineline <small>ver projeto</small></span></a>
              <a class="portfolio-card wide" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('portfolio_fechamento')"><div class="img-bg6"></div><span class="label">Fechamentos <small>ver projeto</small></span></a>
              <a class="portfolio-card wide" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('portfolio_lettering')"><div class="img-bg8"></div><span class="label">Lettering <small>ver projeto</small></span></a>
            </div>
          </div>
        </section>

        <section class="block" id="sobre">
          <div class="container">
            <div class="about-grid">
              <div class="about-photo"><div class="img-daniel"></div></div>
              <div class="about-copy">
                <h2 class="section-title">Sobre o artista</h2>
                <p>Sou Daniel Araujo, tatuador especializado em projetos personalizados, realismo, black & grey e composições pensadas para funcionar na pele.</p>
                <p>Meu foco é transformar sua ideia em uma tattoo com técnica, contraste, encaixe no corpo e personalidade. Nada de projeto jogado às pressas só porque o mundo aparentemente decidiu que tudo precisa ser feito ontem.</p>
                <div class="signature">Daniel</div>
              </div>
              <aside class="stats-card" aria-label="Números do estúdio">
                <div class="stat"><i class="fa-regular fa-gem"></i><div><b>+10</b><span>anos de experiência</span></div></div>
                <div class="stat"><i class="fa-regular fa-user"></i><div><b>+1k</b><span>projetos realizados</span></div></div>
                <div class="stat"><i class="fa-regular fa-star"></i><div><b>100%</b><span>foco no acabamento</span></div></div>
              </aside>
            </div>
          </div>
        </section>

        <section class="block" id="processo">
          <div class="container">
            <div class="section-head">
              <div>
                <h2 class="section-title">Como funciona</h2>
                <p class="section-kicker">Um processo simples para sair do “tenho uma ideia meio nada a ver” até uma tattoo bem resolvida.</p>
              </div>
            </div>
            <div class="steps">
              <div class="step"><i class="fa-regular fa-message"></i><h3>Conversa</h3><p>Você manda sua ideia, referências, local do corpo e tamanho aproximado.</p></div>
              <div class="step"><i class="fa-solid fa-pencil"></i><h3>Desenho</h3><p>Ajustamos estilo, composição e leitura visual para a tattoo funcionar.</p></div>
              <div class="step"><i class="fa-regular fa-calendar"></i><h3>Agenda</h3><p>Definimos data, horário e sinal para reservar sua sessão.</p></div>
              <div class="step"><i class="fa-solid fa-wand-magic-sparkles"></i><h3>Tattoo</h3><p>Execução com técnica, calma, higiene e atenção em cada detalhe.</p></div>
              <div class="step"><i class="fa-regular fa-circle-check"></i><h3>Cuidados</h3><p>Você recebe orientação para cicatrizar bem e preservar o resultado.</p></div>
            </div>
          </div>
        </section>

        <section class="block" id="estilos">
          <div class="container">
            <div class="section-head">
              <div>
                <h2 class="section-title">Estilos</h2>
                <p class="section-kicker">O site precisa vender sem parecer que está implorando. Aqui a ideia é deixar claro onde o trabalho brilha.</p>
              </div>
            </div>
            <div class="styles-grid">
              <article class="style-card"><i class="fa-solid fa-eye"></i><h3>Realismo</h3><p>Rostos, animais, esculturas, olhos e composições com profundidade, contraste e presença.</p></article>
              <article class="style-card"><i class="fa-solid fa-moon"></i><h3>Black & Grey</h3><p>Sombreamento, textura, volume e leitura forte sem depender de cor para funcionar.</p></article>
              <article class="style-card"><i class="fa-solid fa-pen-nib"></i><h3>Fineline</h3><p>Traço limpo, delicado e bem planejado para tatuagens elegantes e discretas.</p></article>
            </div>
          </div>
        </section>

        <section class="block" id="depoimentos">
          <div class="container">
            <div class="section-head">
              <div>
                <h2 class="section-title">Depoimentos</h2>
                <p class="section-kicker">A parte em que humanos confiam mais em outros humanos do que no layout. Vai entender, mas funciona.</p>
              </div>
              <a class="text-link" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('reviews_whatsapp')">Quero tatuar <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="reviews">
              <article class="review"><div class="stars">★★★★★</div><p>“Atendimento cuidadoso, projeto bem explicado e resultado muito acima do que eu imaginava.”</p><b>Cliente do estúdio</b><span>Projeto personalizado</span></article>
              <article class="review"><div class="stars">★★★★★</div><p>“A tattoo ficou limpa, bem encaixada no corpo e com detalhes muito bonitos.”</p><b>Cliente do estúdio</b><span>Black & Grey</span></article>
              <article class="review"><div class="stars">★★★★★</div><p>“Desde o orçamento até os cuidados, tudo foi direto e profissional.”</p><b>Cliente do estúdio</b><span>Realismo</span></article>
            </div>
          </div>
        </section>

        <section class="block" id="faq">
          <div class="container">
            <div class="section-head">
              <div>
                <h2 class="section-title">Perguntas frequentes</h2>
                <p class="section-kicker">As dúvidas que sempre aparecem antes da tattoo. Melhor responder aqui do que virar atendimento repetitivo eterno, essa punição moderna.</p>
              </div>
            </div>
            <div class="faq-grid">
              <details><summary>Como peço um orçamento?</summary><p>Chame no WhatsApp com a ideia, referências, tamanho aproximado e local do corpo. Com isso já dá para orientar valor e agenda.</p></details>
              <details><summary>Precisa pagar sinal?</summary><p>Sim. O sinal reserva a data e evita aquele esporte olímpico chamado “sumir depois de confirmar”.</p></details>
              <details><summary>Dá para adaptar uma referência?</summary><p>Dá. A ideia é usar referências como ponto de partida e criar algo que funcione no seu corpo.</p></details>
              <details><summary>Você faz cobertura?</summary><p>Cada caso precisa ser avaliado por foto ou presencialmente, porque cobertura boa depende de tamanho, cor, idade e contraste da tattoo antiga.</p></details>
              <details><summary>Quanto tempo demora?</summary><p>Depende do tamanho, detalhes e região do corpo. No orçamento a estimativa fica mais clara.</p></details>
              <details><summary>Como cuidar depois?</summary><p>Você recebe orientação de cuidados após a sessão para preservar a cicatrização e o resultado.</p></details>
            </div>

            <div class="final-cta">
              <i class="fa-brands fa-whatsapp"></i>
              <div>
                <h2>Vamos tirar sua ideia do papel?</h2>
                <p>Fale comigo pelo WhatsApp e dê o primeiro passo para transformar referência em tattoo.</p>
              </div>
              <a class="btn btn-filled" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('final_cta')">Chamar no WhatsApp <i class="fa-brands fa-whatsapp"></i></a>
            </div>
          </div>
        </section>
      </main>

      <footer class="footer">
        <div class="container footer-inner">
          <a class="brand" href="#inicio" aria-label="Daniel Tatuador">
            <span class="brand-mark">DT</span>
            <span class="brand-name">Daniel <span>Tatuador</span></span>
          </a>
          <span>© <?= date('Y') ?> Daniel Araujo Tatuador. Todos os direitos reservados.</span>
          <div class="social-row" style="margin:0">
            <a href="https://www.instagram.com/danielaraujo.tatuador/" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
            <a href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('footer_whatsapp')" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <a href="https://www.tiktok.com/@danielaraujotatuador" target="_blank" rel="noopener" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
          </div>
        </div>
      </footer>
    </div>

    <a class="btn btn-filled mobile-whatsapp" href="<?= $whatsapp ?>" target="_blank" rel="noopener" onclick="trackLead('mobile_fixed_whatsapp')"><i class="fa-brands fa-whatsapp"></i> Chamar no WhatsApp</a>
  </div>

  <script>
    function trackLead(label) {
      try {
        if (typeof gtag === 'function') {
          gtag('event', 'conversion', {
            send_to: 'AW-17619660621',
            event_category: 'WhatsApp',
            event_label: label
          });
        }
        if (typeof fbq === 'function') {
          fbq('track', 'Lead', { content_name: label });
        }
      } catch (error) {
        console.warn('Tracking indisponível:', error);
      }
    }

    const navLinks = document.querySelectorAll('.nav a');
    const sections = [...navLinks].map(link => document.querySelector(link.getAttribute('href'))).filter(Boolean);

    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        navLinks.forEach(link => link.classList.toggle('active', link.getAttribute('href') === '#' + entry.target.id));
      });
    }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

    sections.forEach(section => observer.observe(section));
  </script>
</body>
</html>
