<?php
declare(strict_types=1);
/* Landing page por serviço: /cartorio/s/<slug>/
   Cada anúncio aponta para a landing do serviço, em vez da home.
   Os dados vêm de data/cartorio.sqlite (mesma fonte do catálogo). */

$slug = strtolower(trim((string)($_GET['s'] ?? '')));
$slug = preg_replace('/[^a-z0-9-]/', '', $slug);

$servico = null;
$categorias = [
  'civil' => 'Documentos pessoais',
  'notas' => 'Notas & assinaturas',
  'imoveis' => 'Imóveis & registros',
  'rtdpj' => 'Empresas, RTD & RCPJ',
  'cobranca' => 'Protestos, cobranças & notificações',
  'familia' => 'Família & sucessões',
  'mediacao' => 'Mediação & arbitragem',
  'documentos' => 'Documentos & apostila',
  'digital' => 'Serviços digitais',
];

if ($slug !== '') {
    try {
        $dir = dirname(__DIR__) . '/data';
        if (is_file($dir . '/cartorio.sqlite')) {
            $db = new PDO('sqlite:' . $dir . '/cartorio.sqlite');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $s = $db->prepare('SELECT slug,title,description,price,category,icon,kind,channel,docs_json,steps_json FROM services WHERE slug=? AND active=1');
            $s->execute([$slug]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $servico = $r;
                $servico['docs'] = json_decode((string)($r['docs_json'] ?: '[]'), true) ?: [];
                $servico['steps'] = json_decode((string)($r['steps_json'] ?: '[]'), true) ?: [];
            }
        }
    } catch (Throwable $e) { $servico = null; }
}

if (!$servico) {
    http_response_code(404);
}

$titulo = $servico ? $servico['title'] : 'Serviço não encontrado';
$cat = $servico ? ($categorias[$servico['category']] ?? 'Serviços') : '';
$preco = $servico && (float)$servico['price'] > 0 ? (float)$servico['price'] : null;

function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function brl(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0f766e">
<meta name="robots" content="<?= $servico ? 'index,follow' : 'noindex,follow' ?>">
<title><?= e($titulo) ?> — CNJP Cartório Digital</title>
<meta name="description" content="<?= e($servico ? mb_substr((string)$servico['description'], 0, 155) : 'Serviço não encontrado') ?>">
<link rel="stylesheet" href="../assets/local-fonts.css">
<style>
:root{--ink:#162235;--muted:#5b6675;--line:#e4e7ec;--teal:#0f766e;--teal2:#115e59;--tealSoft:#ecfdf5}
*{box-sizing:border-box}
body{margin:0;background:#f5f7f8;color:var(--ink);font-family:Inter,system-ui,-apple-system,sans-serif;line-height:1.55;-webkit-text-size-adjust:100%}
.wrap{width:min(900px,calc(100% - 32px));margin-inline:auto}
header.head{background:linear-gradient(160deg,#0f2928,#123f3c 60%,#0f766e);color:#fff;padding:26px 0}
.head .wrap{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.brand{display:inline-flex;align-items:center;gap:9px;text-decoration:none;color:#fff}
.brand-mark{width:36px;height:36px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.16);font-weight:800}
.brand strong,.brand small{display:block}
.brand strong{font-size:.88rem;line-height:1}
.brand small{font-size:.62rem;color:#9fd8d2;margin-top:3px}
.head a.voltar{color:#b6f0e8;font-size:.76rem;text-decoration:none;border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:7px 15px}
.hero{background:linear-gradient(160deg,#0f2928,#123f3c 60%,#0f766e);color:#fff;padding:0 0 44px}
.hero .wrap{display:grid;gap:26px}
.kicker{font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:#7ff0e0}
.hero .icon{width:54px;height:54px;border-radius:15px;display:grid;place-items:center;background:rgba(255,255,255,.16);font-size:28px;margin-bottom:14px}
h1{font-size:clamp(1.6rem,5vw,2.5rem);line-height:1.08;letter-spacing:-.04em;margin:10px 0 12px}
.hero p.lead{color:#dbeeeb;font-size:.95rem;max-width:560px;margin:0}
.hero .preco{margin-top:18px;display:inline-flex;flex-direction:column;gap:2px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:14px;padding:12px 20px}
.hero .preco span{font-size:.6rem;text-transform:uppercase;letter-spacing:.08em;color:#9fd8d2;font-weight:800}
.hero .preco strong{font-size:1.3rem}
main{padding:30px 0 56px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:22px;margin-bottom:16px}
.card h2{font-size:1.05rem;margin:0 0 12px;letter-spacing:-.02em}
.docs{display:grid;gap:8px}
.doc{display:flex;gap:9px;align-items:flex-start;border:1px solid var(--line);border-radius:12px;padding:11px 13px;font-size:.8rem}
.doc::before{content:'✓';color:var(--teal);font-weight:800}
.stepper{display:flex;flex-wrap:wrap;gap:7px}
.stepper div{display:flex;gap:7px;align-items:center;border:1px solid var(--line);border-radius:11px;padding:9px 12px;font-size:.72rem;font-weight:650;background:#fff}
.stepper b{width:20px;height:20px;border-radius:50%;background:var(--tealSoft);color:var(--teal);display:grid;place-items:center;font-size:.6rem}
.nota{margin-top:12px;padding:11px 14px;border-radius:12px;background:#f8fafc;font-size:.73rem;color:var(--muted)}
.cta-card{background:#fff;border:1px solid #99d5ce;border-radius:18px;padding:22px;text-align:center}
.cta-card h2{margin:0 0 8px;font-size:1.1rem}
.cta-card p{color:var(--muted);font-size:.82rem;margin:0 0 16px}
.botoes{display:flex;flex-direction:column;gap:9px}
.botoes a,.botoes button{display:block;text-decoration:none;text-align:center;border-radius:12px;padding:14px 18px;font-size:.86rem;font-weight:700;border:0;cursor:pointer}
.b-primario{background:var(--teal);color:#fff}
.b-secundario{background:#fff;color:var(--ink);border:1px solid #d0d5dd!important}
.form-venda{margin-top:18px;display:grid;gap:10px;text-align:left}
.form-venda label{display:grid;gap:5px;font-size:.72rem;font-weight:750}
.form-venda input,.form-venda textarea{padding:11px;border:1px solid #d0d5dd;border-radius:10px;font-size:16px;font-family:inherit}
.form-venda textarea{min-height:80px}
.aviso{font-size:.68rem;color:var(--muted);margin-top:10px}
.nao-achou{text-align:center;padding:50px 0}
@media(min-width:600px){.botoes{flex-direction:row;justify-content:center}.botoes a,.botoes button{min-width:220px}.hero .wrap{grid-template-columns:1.4fr .6fr;align-items:center}}
</style>
</head>
<body>
<header class="head"><div class="wrap">
  <a class="brand" href="../"><span class="brand-mark">CN</span><span><strong>CNJP</strong><small>Cartório Digital</small></span></a>
  <a class="voltar" href="../">Voltar ao site</a>
</div></header>

<?php if ($servico): ?>
<section class="hero"><div class="wrap">
  <div>
    <span class="kicker"><?= e($cat) ?></span>
    <div class="icon"><span class="material-symbols-rounded"><?= e((string)$servico['icon']) ?></span></div>
    <h1><?= e($servico['title']) ?></h1>
    <p class="lead"><?= e((string)$servico['description']) ?></p>
  </div>
  <?php if ($preco !== null): ?>
  <div><div class="preco"><span>A partir de</span><strong><?= brl($preco) ?></strong></div></div>
  <?php endif; ?>
</div></section>

<main class="wrap">
  <?php if ($servico['docs']): ?>
  <section class="card">
    <h2>O que normalmente precisamos</h2>
    <div class="docs">
      <?php foreach ($servico['docs'] as $d): ?><div class="doc"><?= e((string)$d) ?></div><?php endforeach; ?>
    </div>
    <div class="nota">Não tem tudo em mãos? Sem problema — a equipe confere com você o que já existe e o que falta antes de qualquer pedido.</div>
  </section>
  <?php endif; ?>

  <?php if ($servico['steps']): ?>
  <section class="card">
    <h2>Como o processo anda</h2>
    <div class="stepper">
      <?php foreach ($servico['steps'] as $i => $st): ?><div><b><?= $i + 1 ?></b><span><?= e((string)$st) ?></span></div><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="cta-card" id="pedido">
    <h2>Pedir <?= e(mb_strtolower((string)$servico['title'])) ?></h2>
    <p>Preencha o básico. O pedido só vira processo depois da conferência e da aprovação do orçamento — nada é cobrado agora.</p>
    <div class="botoes">
      <a class="b-primario" href="../">Ver todos os serviços</a>
      <a class="b-secundario" href="../#clientPortal">Já sou cliente</a>
    </div>
    <form class="form-venda" id="landingForm">
      <label>Seu nome<input name="name" required placeholder="Como podemos te chamar?"></label>
      <label>WhatsApp ou telefone<input name="phone" required placeholder="(11) 99999-9999"></label>
      <label>E-mail (opcional)<input name="email" type="email" placeholder="voce@email.com - recebe o código do pedido"></label>
      <label>Conte o seu caso<textarea name="description" placeholder="Explique o que aconteceu e o que você já tem em mãos."></textarea></label>
      <button class="b-primario" type="submit" data-slug="<?= e((string)$servico['slug']) ?>">Enviar pedido</button>
    </form>
    <p class="aviso">Taxas oficiais, terceiros e serviço CNJP aparecem separados antes da sua aprovação. A CNJP não é cartório e não pratica ato de fé pública.</p>
  </section>
</main>

<script>
// origem (UTM) reaproveitada do site
(function(){const K='cnjp_origem',C=['utm_source','utm_medium','utm_campaign','utm_content','utm_term','gclid','fbclid'];
try{const q=new URLSearchParams(location.search);const achou=C.some(c=>(q.get(c)||'').trim()!=='');const ref=document.referrer&&!document.referrer.includes(location.host)?document.referrer:'';const at=JSON.parse(sessionStorage.getItem(K)||'null');
if(achou&&(!at||!at.tem_utm)){const d={tem_utm:true,landing:location.pathname+location.search,referrer:ref};C.forEach(c=>{const v=(q.get(c)||'').trim();if(v)d[c]=v.slice(0,120)});sessionStorage.setItem(K,JSON.stringify(d))}
else if(!at){sessionStorage.setItem(K,JSON.stringify({tem_utm:false,landing:location.pathname+location.search,referrer:ref}))}}catch(e){}})();
window.cnjpOrigem=()=>{try{return JSON.parse(sessionStorage.getItem('cnjp_origem')||'{}')}catch(e){return{}}};

document.getElementById('landingForm').addEventListener('submit',async ev=>{
  ev.preventDefault();
  const f=new FormData(ev.target), b=ev.target.querySelector('button[type=submit]');
  b.disabled=true; b.textContent='Enviando…';
  const payload={name:f.get('name'),phone:f.get('phone'),email:f.get('email')||'',service:'<?= e((string)$servico['slug']) ?>',description:f.get('description')||'',channel:'Site',origem:window.cnjpOrigem()};
  try{
    const r=await fetch('../api.php?action=request',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const d=await r.json();
    if(!r.ok) throw new Error(d.message||'Não foi possível enviar.');
    window.location.href='../obrigado/?code='+encodeURIComponent(d.code);
  }catch(err){
    b.disabled=false; b.textContent='Enviar pedido';
    alert(err.message||'Não foi possível enviar agora. Tente novamente.');
  }
});
</script>

<?php else: ?>
<main class="wrap nao-achou">
  <h1>Serviço não encontrado</h1>
  <p class="lead" style="color:#5b6675">O endereço pode ter mudado. Veja a lista completa de serviços disponíveis.</p>
  <div class="botoes" style="margin-top:22px"><a class="b-primario" href="../">Ver todos os serviços</a></div>
</main>
<?php endif; ?>
</body>
</html>
