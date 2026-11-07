<?php
$dir = __DIR__;
$files = [];
foreach (scandir($dir) as $f) {
    if (preg_match('/\.(jpe?g|png|webp|gif)$/i', $f)) { $files[] = $f; }
}
natsort($files);
$files = array_values($files);
$total = count($files);
$perPage = 48;
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min($pages, (int)($_GET['p'] ?? 1)));
$slice = array_slice($files, ($page - 1) * $perPage, $perPage);

$phone = '5511999979785';
$msg = 'Oi Daniel! Vim pela galeria do site e quero fazer um orçamento de tatuagem.';
$whatsapp = 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
$clickId = (string)($_GET['gclid'] ?? ($_GET['wbraid'] ?? ($_GET['gbraid'] ?? '')));
if ($clickId !== '' && preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $clickId) === 1) {
    $whatsapp = '../projetocrm/ir.php?src=google&gclid=' . rawurlencode($clickId) . '&msg=' . rawurlencode($msg);
}
$canonical = 'https://danieltatuador.com/galeria/' . ($page > 1 ? '?p=' . $page : '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#060504">
<link rel="icon" type="image/svg+xml" href="/img/favicon1.svg?v=20261107">
<title>Galeria de Tatuagens | Daniel Araujo Tatuador — Artur Alvim</title>
<meta name="description" content="Galeria completa de tatuagens de Daniel Araujo: fechamentos, realismo e black & grey em Artur Alvim, Zona Leste de São Paulo.">
<meta name="robots" content="<?= $page > 1 ? 'noindex, follow' : 'index, follow, max-image-preview:large' ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:title" content="Galeria de Tatuagens | Daniel Araujo Tatuador">
<meta property="og:description" content="Fechamentos, realismo e black & grey em Artur Alvim, Zona Leste de São Paulo.">
<meta property="og:image" content="https://danieltatuador.com/img/og-image.jpg">
<meta property="og:type" content="website">
<meta property="og:url" content="https://danieltatuador.com/galeria/">
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-17619660621"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','AW-17619660621');gtag('config','G-CQHHDSD0PK');</script>
<style>
  :root{--gold:#c9964a;--gold2:#e2b871;--text:#f8f3ea;--muted:#b9afa0;--line:rgba(226,184,113,.18);--max:1180px}
  *{box-sizing:border-box}
  body{margin:0;background:radial-gradient(circle at 80% 0,rgba(201,150,74,.15),transparent 28%),linear-gradient(180deg,#040302,#0b0907 55%,#050403);color:var(--text);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;line-height:1.6;overflow-x:hidden}
  a{color:inherit;text-decoration:none}
  .wrap{width:min(var(--max),calc(100% - 36px));margin:auto}
  .top{position:sticky;top:0;z-index:50;background:rgba(6,5,4,.88);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
  .top-in{min-height:72px;display:flex;align-items:center;justify-content:space-between;gap:16px}
  .brand{display:flex;align-items:center;gap:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
  .mark{width:42px;height:42px;display:grid;place-items:center;border:1px solid rgba(226,184,113,.55);color:var(--gold2);font-family:Arial Black,Arial,sans-serif;font-size:17px}
  .brand small{display:block;color:var(--muted);font-size:10px;letter-spacing:.26em;margin-top:3px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:12px 18px;border-radius:8px;border:1px solid rgba(226,184,113,.7);background:rgba(10,8,5,.6);color:var(--gold2);text-transform:uppercase;font-size:12px;font-weight:900;letter-spacing:.07em;cursor:pointer}
  .btn-fill{background:linear-gradient(135deg,var(--gold2),var(--gold));color:#111;border-color:transparent}
  .head{padding:46px 0 24px;border-bottom:1px solid var(--line)}
  .eyebrow{display:inline-flex;align-items:center;gap:10px;color:var(--muted);font-size:11px;letter-spacing:.2em;text-transform:uppercase;font-weight:900}
  .eyebrow:before{content:'';width:34px;height:1px;background:var(--gold)}
  h1{font-family:'Barlow Condensed',Impact,'Arial Black',sans-serif;font-size:clamp(38px,5vw,64px);line-height:1;text-transform:uppercase;letter-spacing:.03em;margin:14px 0 0}
  h1 strong{color:var(--gold2)}
  .head p{color:var(--muted);max-width:720px;margin:14px 0 0;font-size:15px}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;padding:30px 0 10px}
  .card{position:relative;display:block;aspect-ratio:3/4;border-radius:10px;overflow:hidden;border:1px solid rgba(226,184,113,.18);background:rgba(255,255,255,.03)}
  .card img{display:block;width:100%;height:100%;object-fit:cover;filter:grayscale(.25) saturate(.85) contrast(1.04);transition:transform .4s ease,filter .4s ease}
  .card:hover img{transform:scale(1.04);filter:grayscale(0) saturate(1) contrast(1.04)}
  .pager{display:flex;justify-content:center;align-items:center;gap:12px;padding:26px 0 50px;flex-wrap:wrap}
  .pager a,.pager span{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:11px 15px;border:1px solid rgba(226,184,113,.24);border-radius:8px}
  .pager a{color:var(--gold2)}
  .pager .off{color:var(--muted);opacity:.5}
  .cta{margin:0 0 40px;border:1px solid rgba(226,184,113,.32);border-radius:14px;padding:24px;background:linear-gradient(90deg,rgba(201,150,74,.14),rgba(201,150,74,.03));display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap}
  .cta h2{font-family:'Barlow Condensed',Impact,sans-serif;font-size:clamp(24px,3vw,34px);text-transform:uppercase;letter-spacing:.06em;margin:0}
  .cta p{color:var(--muted);margin:6px 0 0;font-size:14px}
  footer{padding:24px 0 92px;border-top:1px solid var(--line);color:var(--muted);font-size:13px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
  footer a{color:var(--gold2)}
  .mobile-bar{display:none;position:fixed;left:14px;right:14px;bottom:14px;z-index:60}
  @media(max-width:980px){.grid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:700px){.grid{grid-template-columns:repeat(2,1fr);gap:10px}.top .btn{display:none}.head{padding:32px 0 18px}.mobile-bar{display:block}}
  @media(prefers-reduced-motion:reduce){*{transition:none!important;scroll-behavior:auto!important}}
</style>
</head>
<body>
<header class="top"><div class="wrap top-in">
  <a class="brand" href="/" aria-label="Daniel Araujo Tatuador — site principal"><span class="mark">DT</span><span>Daniel Araujo<small>Tatuador • Artur Alvim</small></span></a>
  <a class="btn btn-fill" href="<?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" onclick="trackLead('gal_header')">Pedir orçamento</a>
</div></header>

<main class="wrap">
  <div class="head">
    <span class="eyebrow">Galeria • Daniel Araujo Tatuador</span>
    <h1>Tatuagens em <strong>Artur Alvim</strong></h1>
    <p>Fechamentos, realismo e black &amp; grey. Uma seleção do trabalho do estúdio em São Paulo. Para ver o projeto completo ou tirar uma dúvida, chame no WhatsApp.</p>
  </div>

  <div class="grid">
    <?php foreach ($slice as $i => $file): $hash = substr(md5($file), 0, 16); $thumbFile = __DIR__ . '/thumbs/' . $hash . '.webp'; $largeFile = __DIR__ . '/large/' . $hash . '.webp'; $src = '/galeria/' . rawurlencode($file); $thumbSrc = is_file($thumbFile) ? '/galeria/thumbs/' . $hash . '.webp' : $src; $largeSrc = is_file($largeFile) ? '/galeria/large/' . $hash . '.webp' : $src; $alt = 'Tatuagem autoral de Daniel Araujo em Artur Alvim — projeto ' . (($page - 1) * $perPage + $i + 1); $srcset = is_file($thumbFile) ? ' srcset="' . htmlspecialchars($thumbSrc, ENT_QUOTES, 'UTF-8') . ' 480w" sizes="(max-width:700px) 50vw, (max-width:980px) 33vw, 280px"' : ''; ?>
      <a class="card" href="<?= htmlspecialchars($largeSrc, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Ampliar tatuagem">
        <img src="<?= htmlspecialchars($thumbSrc, ENT_QUOTES, 'UTF-8') ?>"<?= $srcset ?> width="480" height="640" alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>" loading="<?= $i < 4 ? 'eager' : 'lazy' ?>" decoding="async">
      </a>
    <?php endforeach; ?>
  </div>

  <div class="pager">
    <?php if ($page > 1): ?><a href="?p=<?= $page - 1 ?>">← Anterior</a><?php else: ?><span class="off">← Anterior</span><?php endif; ?>
    <span>Página <?= $page ?> de <?= $pages ?> • <?= $total ?> trabalhos</span>
    <?php if ($page < $pages): ?><a href="?p=<?= $page + 1 ?>">Próxima →</a><?php else: ?><span class="off">Próxima →</span><?php endif; ?>
  </div>

  <div class="cta"><div><h2>Quer sua ideia na pele?</h2><p>Manda a referência no WhatsApp e recebe o orçamento sem compromisso.</p></div><a class="btn btn-fill" href="<?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" onclick="trackLead('gal_cta')">Chamar no WhatsApp</a></div>
</main>

<footer class="wrap">
  <span>© <?= date('Y') ?> Daniel Araujo Tatuador • Rua Catende, 287B, Jd Nordeste, São Paulo/SP</span>
  <span><a href="/">Site principal</a> · <a href="/fechamento/">Fechamento em 1 sessão</a> · <a href="/privacy.html">Privacidade</a> · <a href="/terms.html">Termos</a> · <a href="tel:+5511999979785" onclick="trackPhone('gal_footer')">(11) 99997-9785</a></span>
</footer>

<a class="btn btn-fill mobile-bar" href="<?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" onclick="trackLead('gal_mobile')">Pedir orçamento no WhatsApp</a>

<script>
window.dataLayer=window.dataLayer||[];
function trackLead(label){try{dataLayer.push({event:'whatsapp_click',label:label});if(typeof gtag==='function')gtag('event','conversion',{send_to:'AW-17619660621/o6mzCPX--qUbEM3e2tFB',event_category:'WhatsApp',event_label:label,transport_type:'beacon'});}catch(e){console.warn(e)}}
function trackPhone(label){try{dataLayer.push({event:'phone_click',label:label});}catch(e){console.warn(e)}}
</script>
</body>
</html>
