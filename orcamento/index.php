<?php
$hotspotsFile = __DIR__ . '/hotspots.json';
$hotspotsJson = file_exists($hotspotsFile) ? file_get_contents($hotspotsFile) : '{"frente":[],"costas":[]}';
json_decode($hotspotsJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $hotspotsJson = '{"frente":[],"costas":[]}';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orçamento de Tattoo | Daniel Araujo</title>
<style>
:root{--bg:#050505;--panel:#101014;--line:rgba(255,255,255,.14);--red:#e7332f;--green:#25d366;--txt:#fff;--muted:#aaa}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 50% 0,rgba(231,51,47,.14),transparent 35%),#050505;color:#fff;font-family:Arial,Helvetica,sans-serif;padding:0 14px 24px}button,input,textarea,select{font:inherit}.shell{max-width:1480px;margin:auto}.nav{height:70px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line)}.brand{display:flex;gap:12px;align-items:center;font-weight:900;letter-spacing:2px;text-transform:uppercase}.logo{width:42px;height:42px;display:grid;place-items:center;border:1px solid var(--red);color:#ff5a52;font-size:24px}.top-zap,.zap,.mobile-cta{background:linear-gradient(#ef3d37,#b81916);border:1px solid rgba(255,255,255,.18);color:#fff;text-decoration:none;border-radius:8px;padding:13px 18px;font-weight:900;text-transform:uppercase}.hero{display:grid;grid-template-columns:minmax(0,1fr)390px;gap:18px;margin-top:18px}.stage,.side-card,.regions,.foot-card,.accordion{background:linear-gradient(180deg,rgba(18,18,20,.96),rgba(5,5,6,.97));border:1px solid var(--line);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.38)}.stage{padding:18px}h1{margin:0;font-size:clamp(28px,4vw,48px);text-transform:uppercase}.lead{color:#ddd;margin:8px 0 0}.version{display:inline-block;margin-left:8px;padding:4px 8px;border:1px solid rgba(231,51,47,.45);border-radius:999px;color:#ff6b63;font-size:12px;vertical-align:middle}.accordion{overflow:hidden}.accordion+ .accordion{margin-top:12px}.accordion summary{list-style:none;cursor:pointer;padding:16px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;font-weight:900;text-transform:uppercase}.accordion summary::-webkit-details-marker{display:none}.accordion summary:after{content:'+';width:28px;height:28px;display:grid;place-items:center;border-radius:50%;border:1px solid rgba(255,255,255,.2);color:#ff6b63;font-size:20px;line-height:1}.accordion[open] summary:after{content:'−'}.accordion-body{padding:0 18px 18px}.main-accordion{background:transparent;border:0;box-shadow:none;overflow:visible}.main-accordion summary{padding:0 0 14px;display:block;cursor:default}.main-accordion summary:after{display:none}.segmented{display:inline-flex;border:1px solid var(--line);border-radius:8px;overflow:hidden;margin:10px 0 18px;background:#080808}.segmented button{border:0;background:transparent;color:#ddd;padding:13px 22px;font-weight:900;cursor:pointer}.segmented button.active{background:linear-gradient(180deg,var(--red),#75110f);color:#fff}.map-box{position:relative;width:min(100%,640px);aspect-ratio:640/760;height:auto;margin:auto;border-radius:12px;background:#050505;overflow:hidden}.map-box.hidden{display:none}.body-render{position:absolute;left:50%;top:50%;height:96%;max-width:78%;transform:translate(-50%,-50%);object-fit:contain;filter:drop-shadow(0 26px 38px rgba(0,0,0,.9));pointer-events:none;user-select:none}.hotspot{position:absolute;z-index:2;border:1px solid transparent;background:transparent;border-radius:999px;color:transparent;transition:.14s;cursor:pointer;outline:0 solid transparent;outline-offset:-2px}.hotspot:hover,.hotspot:focus-visible,.hotspot.hovering{border-color:#fff;outline:2px solid rgba(255,255,255,.96);background:transparent!important;box-shadow:none!important}.hotspot.selected{border-color:#fff;background:rgba(231,51,47,.46)!important;box-shadow:0 0 28px rgba(255,75,69,.95)!important;outline:0 solid transparent}.legend{display:flex;justify-content:center;gap:22px;color:#ddd;margin-top:13px}.dot{display:inline-block;width:13px;height:13px;border-radius:50%;border:1px solid #fff;margin-right:7px}.dot.sel{background:var(--red)}.compact-info{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center;margin-top:16px;padding:14px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.035)}.compact-label{color:#bbb;text-transform:uppercase;font-size:12px;font-weight:900}.count{display:inline-grid;place-items:center;margin-left:8px;background:var(--red);border-radius:50%;width:28px;height:28px;font-weight:900}.price{color:#ff4b45;font-size:clamp(28px,4vw,42px);font-weight:900;margin:3px 0}.desc{color:#ddd;line-height:1.45;margin:0}.side{display:block}.side-card{padding:0;box-shadow:none;background:transparent;border:0}.selection-list{display:grid;gap:8px;min-height:55px}.pill{display:grid;grid-template-columns:1fr auto;gap:10px;padding:11px 12px;border:1px solid var(--line);border-radius:8px;background:rgba(255,255,255,.04)}.pill small{display:block;color:#bbb;margin-top:4px}.pill button{background:transparent;border:0;color:#ddd;font-size:22px;cursor:pointer}.promo-box{margin-top:12px;padding:12px;border:1px solid rgba(37,211,102,.24);border-radius:8px;background:rgba(37,211,102,.06);color:#d8ffe5;text-align:left}.promo-row{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:12px}.promo-row select,.promo-row button,input,textarea{border:1px solid var(--line);border-radius:8px;background:#080808;color:#fff;padding:12px}.promo-row button{background:rgba(231,51,47,.22);font-weight:900;cursor:pointer}.field{display:grid;gap:7px;margin-top:12px}.field label{font-size:12px;text-transform:uppercase;font-weight:900;color:#ddd}.field textarea{min-height:90px;resize:vertical}.zap{display:grid;place-items:center;margin-top:16px;min-height:62px}.note{font-size:12px;color:#aaa;text-align:center}.regions{padding:0;margin-top:18px;overflow:hidden}.regions-head{display:block}.region-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(118px,1fr));gap:10px}.region-card{border:1px solid var(--line);border-radius:10px;background:#090909;color:#fff;min-height:128px;padding:8px;display:grid;grid-template-rows:1fr auto;gap:6px;font-size:10px;font-weight:900;text-transform:uppercase;text-align:center;overflow:hidden;cursor:pointer}.region-card.selected{border-color:#fff;box-shadow:0 0 18px rgba(231,51,47,.55)}.mini{position:relative;height:82px;border-radius:8px;background:#030303;overflow:hidden}.mini img{position:absolute;left:50%;top:50%;height:120%;max-width:92%;transform:translate(-50%,-50%);object-fit:contain;opacity:.9}.mini-hot{position:absolute;border-radius:999px;background:rgba(231,51,47,.58);border:1px solid rgba(255,255,255,.7);box-shadow:0 0 14px rgba(255,75,69,.8)}.foot{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:14px}.foot-card{padding:16px;color:#ddd;box-shadow:none}.foot-card b{display:block;color:#fff;text-transform:uppercase;margin-bottom:8px}.tooltip{position:fixed;z-index:30;pointer-events:none;opacity:0;transform:translate(12px,12px);padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:#050505;color:#fff;font-weight:900;box-shadow:0 10px 30px #000}.tooltip.show{opacity:1}.mobile-cta{display:none;position:fixed;left:12px;right:12px;bottom:12px;z-index:20;text-align:center}.hide-desktop{display:none}@media(max-width:1180px){.hero{grid-template-columns:1fr}.side{display:grid;gap:0}.foot{grid-template-columns:1fr 1fr}}@media(max-width:720px){body{padding:0 10px 84px}.top-zap{display:none}.stage{padding:14px}.compact-info{grid-template-columns:1fr}.region-grid{grid-template-columns:repeat(3,1fr)}.foot{grid-template-columns:1fr}.mobile-cta{display:block}.promo-row{grid-template-columns:1fr}.accordion summary{padding:14px}.accordion-body{padding:0 14px 14px}}
</style>
<style>
body {
  margin: 0;
  padding: 0 16px 28px;
  font-family: 'Montserrat', sans-serif;
  color: #f4f4f4;
  background:
    radial-gradient(circle at top, rgba(231, 51, 47, 0.18), transparent 28%),
    linear-gradient(180deg, rgba(6, 6, 7, 0.96), rgba(6, 6, 7, 0.98)),
    url('../img/bg.jfif') center/cover fixed no-repeat;
}

body::before {
  content: '';
  position: fixed;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.18), rgba(0, 0, 0, 0.62));
  z-index: -1;
}

.site-nav {
  position: sticky;
  top: 0;
  z-index: 50;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  max-width: 1480px;
  margin: 0 auto 16px;
  padding: 16px 18px;
  backdrop-filter: blur(14px);
  background: rgba(9, 9, 10, 0.86);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-top: 0;
  border-radius: 0 0 18px 18px;
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.28);
}

.site-brand-link {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  color: #fff;
  text-decoration: none;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.site-mark {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  border: 1px solid rgba(231, 51, 47, 0.7);
  background: linear-gradient(180deg, rgba(231, 51, 47, 0.85), rgba(117, 17, 15, 0.95));
  color: #fff;
  font-size: 18px;
}

.site-brand-text small,
.hero-kicker {
  display: block;
  color: #ff8a84;
  font-size: 11px;
  letter-spacing: 0.2em;
}

.site-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.site-actions a,
.hero-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 0 16px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 800;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.site-actions a {
  color: #f2f2f2;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.site-actions .site-cta,
.hero-action-primary {
  color: #fff;
  background: linear-gradient(180deg, #ef3d37, #b81916);
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 10px 24px rgba(231, 51, 47, 0.18);
}

.site-actions a:hover,
.hero-action:hover {
  transform: translateY(-1px);
}

.shell {
  max-width: 1480px;
}

.shell > .nav {
  display: none;
}

.hero-banner {
  margin: 4px 0 18px;
  padding: 26px 24px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  background:
    radial-gradient(circle at left top, rgba(231, 51, 47, 0.22), transparent 34%),
    linear-gradient(180deg, rgba(16, 16, 18, 0.96), rgba(7, 7, 8, 0.98));
  box-shadow: 0 26px 70px rgba(0, 0, 0, 0.34);
}

.hero-banner h1 {
  margin: 8px 0 10px;
  font-family: 'Montserrat', sans-serif;
  font-size: clamp(30px, 4vw, 56px);
  line-height: 0.98;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.hero-banner p {
  max-width: 760px;
  margin: 0;
  color: #ddd;
  line-height: 1.55;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
}

.hero-action-secondary {
  color: #fff;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

#hero {
  display: block !important;
  min-height: 0 !important;
  height: auto !important;
  margin: 0 auto !important;
  padding: 0 0 16px !important;
  background: transparent !important;
}

#hero-carousel {
  max-width: 1480px !important;
  margin: 0 auto !important;
  height: 500px !important;
  min-height: 500px !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 24px !important;
  background: rgba(12, 12, 14, 0.92) !important;
  box-shadow: 0 26px 70px rgba(0, 0, 0, 0.42) !important;
  padding: 14px !important;
}

#hero-carousel .carousel-slide {
  min-height: 420px;
}

#hero-carousel h2 {
  font-size: clamp(2rem, 4vw, 3.4rem) !important;
  text-transform: uppercase;
  line-height: 0.96;
}

#hero-carousel p {
  max-width: 540px;
}

section {
  scroll-margin-top: 110px;
}

#portfolio,
#agendamento,
#faq,
#tattoo-artist {
  max-width: 1480px;
  margin-left: auto;
  margin-right: auto;
}

#portfolio,
#faq,
#agendamento {
  padding-left: 16px;
  padding-right: 16px;
}

.portfolio,
.tattoo-artist,
#agendamento,
#faq {
  position: relative;
}

.portfolio h2,
.tattoo-artist h2,
#agendamento h2,
#faq h2 {
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.portfolio img,
.testimonial,
.faq-card,
.faq-btn,
.faq-body,
.hero-banner,
.tattoo-artist img {
  border-radius: 18px;
}

.tattoo-artist {
  margin-top: 18px;
  padding: 72px 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background:
    radial-gradient(circle at top, rgba(231, 51, 47, 0.16), transparent 30%),
    linear-gradient(180deg, rgba(10, 10, 11, 0.96), rgba(6, 6, 7, 0.98));
}

#agendamento {
  padding-top: 80px;
  padding-bottom: 80px;
}

.form-control,
select.form-control {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #fff;
}

.form-control::placeholder {
  color: #999;
}

.whatsapp-float {
  background: linear-gradient(180deg, #2adf73, #169d4c);
  box-shadow: 0 16px 34px rgba(37, 211, 102, 0.34);
}

footer {
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

@media (max-width: 991px) {
  .site-nav {
    position: static;
    border-radius: 18px;
    margin: 0 auto 14px;
  }

  .site-nav,
  .site-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .site-actions a {
    width: 100%;
  }
}

@media (max-width: 767px) {
  body {
    padding: 0 10px 18px;
  }

  .hero-banner,
  #hero-carousel {
    border-radius: 18px;
  }

  .hero-banner {
    padding: 20px 16px;
  }

  #hero-carousel {
    padding: 10px !important;
  }
}
</style>
</head>
<body>
<nav class="site-nav">
  <div class="site-brand">
    <a href="https://danieltatuador.com/" class="site-brand-link" aria-label="Ir para a página inicial">
      <span class="site-mark">DA</span>
      <span class="site-brand-text">Daniel Araujo<br><small>Orçamento de tattoo</small></span>
    </a>
  </div>
  <div class="site-actions">
    <a href="#hero">Início</a>
    <a href="#portfolio">Portfólio</a>
    <a class="site-cta" id="topZap" href="#agendamento" target="_blank" rel="noopener noreferrer">Pedir orçamento</a>
  </div>
</nav>
<div class="shell">
<nav class="nav"><div class="brand"><span class="logo">DA</span><span>Daniel Araujo<br><small>Tattoo</small></span></div><a class="top-zap" href="#" target="_blank">Chamar no WhatsApp</a></nav>
<section class="hero-banner">
  <span class="hero-kicker">Orçamento rápido e visual premium</span>
  <h1>Monte seu orçamento de tattoo com a cara do estúdio</h1>
  <p>Escolha a região do corpo, aplique promoções e envie tudo pronto no WhatsApp. O visual foi alinhado para ficar mais próximo da identidade da página inicial.</p>
  <div class="hero-actions">
    <a class="hero-action hero-action-primary" href="#agendamento">Quero meu orçamento</a>
    <a class="hero-action hero-action-secondary" href="#portfolio">Ver trabalhos</a>
  </div>
</section>
<section class="hero">
  <main class="stage">
    <details class="accordion main-accordion" open>
      <summary><h1>Orçamento de Tattoo 3D <span class="version">clique no corpo</span></h1><p class="lead">Escolha a área da tattoo e veja uma estimativa inicial.</p></summary>
      <div class="segmented"><button class="active" type="button" data-view="frente">Frente</button><button type="button" data-view="costas">Costas</button></div>
      <div id="frontBox" class="map-box"></div><div id="backBox" class="map-box hidden"></div>
      <div class="legend"><span><i class="dot sel"></i>Selecionado</span><span><i class="dot"></i>Disponível</span></div>
      <div class="compact-info">
        <div><div class="compact-label">Áreas selecionadas <span id="count" class="count">0</span></div><div id="preco" class="price">---</div><p id="descricao" class="desc">Clique em uma ou mais regiões para montar a estimativa inicial.</p><div id="promoCurrent" class="promo-box" style="display:none"></div></div>
        <div class="promo-row"><select id="promoSelect"></select><button id="applyPromo" type="button">Aplicar</button></div>
      </div>
    </details>
  </main>
  <aside class="side">
    <details class="accordion"><summary>Sua seleção detalhada</summary><div class="accordion-body"><div id="selectionList" class="selection-list"></div></div></details>
    <details class="accordion"><summary>Enviar para WhatsApp</summary><div class="accordion-body"><div class="field"><label>Nome completo</label><input id="cliente" placeholder="Digite seu nome"></div><div class="field"><label>Referência / ideia da tattoo</label><textarea id="referenciaTexto" maxlength="500" placeholder="Descreva sua ideia, estilo, referência..."></textarea></div><a id="zap" class="zap" href="#" target="_blank">Receber orçamento no WhatsApp</a><p class="note">Você será redirecionado com uma mensagem pronta.</p></div></details>
  </aside>
</section>
<details class="accordion regions"><summary>Todas as regiões disponíveis</summary><div class="accordion-body"><div id="regionGrid" class="region-grid"></div></div></details>
<details class="accordion" style="margin-top:14px"><summary>Como funciona</summary><div class="accordion-body"><section class="foot"><div class="foot-card"><b>Como funciona</b>Selecione áreas, veja a estimativa e envie pelo WhatsApp.</div><div class="foot-card"><b>Orçamento personalizado</b>O valor final depende do tamanho, estilo e detalhamento.</div><div class="foot-card"><b>Atendimento rápido</b>As informações chegam organizadas.</div><div class="foot-card"><b>100% seguro</b>Seus dados não são compartilhados.</div></section></div></details>
</div><div id="tooltip" class="tooltip"></div><a id="mobileCta" class="mobile-cta" href="#" target="_blank">Receber orçamento no WhatsApp</a>
<script>
const FRONT_IMG='assets/body-front-muscular.png?v=1';
const BACK_IMG='assets/body-back-muscular.png?v=1';
const raw=<?php echo $hotspotsJson; ?>;
const VIEW_ADJUST={frente:{x:0,y:0},costas:{x:2,y:0}};
function fixHotspot(view,h){const a=VIEW_ADJUST[view]||{x:0,y:0};const c=[...h];c[3]=Number(c[3])+a.x;c[4]=Number(c[4])+a.y;return c}
function area(titulo,min,max,desc){return{titulo,min,max,desc}}
function promo(titulo,desc,ids,desconto,view){return{titulo,desc,ids,desconto,view}}
const phone='5511947573311';
const areas={cabeca:area('Cabeça',2500,6500,'Área extrema e muito visível.'),pescoco:area('Pescoço',1500,3500,'Região de alto impacto.'),ombros:area('Ombro',1800,4200,'Excelente para encaixe anatômico.'),peito:area('Peitoral',2500,7000,'Área grande e visual.'),abdomen:area('Abdômen',2000,5600,'Área ampla e sensível.'),costela:area('Costela',1800,5600,'Ótima para composições laterais.'),quadril:area('Quadril',1500,4200,'Complemento de perna ou abdômen.'),costas_superior:area('Costas superior',1800,5200,'Área forte para impacto.'),costas_media:area('Costas média',1800,5600,'Boa para peças centrais.'),costas_inferior:area('Costas inferior',1500,4200,'Região boa para complemento.'),lombar:area('Lombar',1500,4200,'Boa para peças centrais.'),braco_interno:area('Braço superior interno',1200,3500,'Metade superior interna.'),braco_externo:area('Braço superior externo',1400,3900,'Metade superior externa.'),antebraco_interno:area('Antebraço interno',1200,3400,'Área muito procurada.'),antebraco_externo:area('Antebraço externo',1200,3200,'Boa leitura no dia a dia.'),pulso:area('Punho',600,1600,'Área menor e delicada.'),mao_palma:area('Mão palma',900,2800,'Área delicada e de alto desgaste.'),mao_dorso:area('Mão dorso',900,2800,'Área muito visível.'),coxa_frontal:area('Coxa frontal',2500,7200,'Área ampla para peças grandes.'),coxa_posterior:area('Coxa posterior',2300,6600,'Boa para fechamento.'),gluteo:area('Glúteos',1800,4600,'Complemento de perna ou quadril.'),joelho:area('Joelho anterior',1200,3800,'Área complexa.'),joelho_posterior:area('Joelho posterior',900,2700,'Área sensível.'),canela:area('Canela',1800,4800,'Área de impacto visual.'),panturrilha:area('Panturrilha',1800,4800,'Boa curvatura.'),tornozelo:area('Tornozelo',600,1900,'Área menor para detalhes.'),pe_dorso:area('Pé dorso',900,2800,'Área visível.'),pe_planta:area('Pé planta',700,2200,'Alto desgaste.')};
const promos=[promo('Braço externo esquerdo','Ombro + braço superior externo + antebraço externo',['ombro_esq_costas','braco_esq_costas','antebraco_esq_externo'],.88,'costas'),promo('Braço interno esquerdo','Braço superior interno + antebraço interno',['braco_esq_frente','antebraco_esq_interno'],.9,'frente'),promo('Peitoral completo','Peito esquerdo + direito',['peito_esq','peito_dir'],.9,'frente'),promo('Costas completa','Costas superior + média + inferior + lombar',['costas_esq_alta','costas_dir_alta','costas_esq_media','costas_dir_media','costas_esq_baixa','costas_dir_baixa','lombar'],.82,'costas')];
const $=id=>document.getElementById(id), selected=new Map();
function money(v){return Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0})}
function esc(v=''){return String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
function img(view){return view==='costas'?BACK_IMG:FRONT_IMG}
function label(el){return el.dataset.label||areas[el.dataset.region]?.titulo||el.dataset.region}
function draw(view,boxId){const box=$(boxId);box.innerHTML=`<img class="body-render" src="${img(view)}" alt="Boneco ${view}">`;(raw[view]||[]).forEach(original=>{const h=fixHotspot(view,original);const e=document.createElement('button');e.type='button';e.className='hotspot';e.dataset.id=h[0];e.dataset.region=h[1];e.dataset.label=h[2];e.style.left=h[3]+'%';e.style.top=h[4]+'%';e.style.width=h[5]+'%';e.style.height=h[6]+'%';e.onclick=()=>selectPart(e);e.onmouseenter=()=>e.classList.add('hovering');e.onmouseleave=()=>{e.classList.remove('hovering');$('tooltip').classList.remove('show')};e.onmousemove=ev=>tip(ev,h[2]);box.appendChild(e)})}
function tip(e,t){$('tooltip').textContent=t;$('tooltip').style.left=e.clientX+'px';$('tooltip').style.top=e.clientY+'px';$('tooltip').classList.add('show')}
function selectPart(e){const item={id:e.dataset.id,region:e.dataset.region,label:label(e)};selected.has(item.id)?selected.delete(item.id):selected.set(item.id,item);update()}
function selectRegion(region){const els=[...document.querySelectorAll(`.hotspot[data-region="${region}"]`)];const all=els.length&&els.every(e=>selected.has(e.dataset.id));els.forEach(e=>{const item={id:e.dataset.id,region:e.dataset.region,label:label(e)};all?selected.delete(item.id):selected.set(item.id,item)});update()}
function calc(){if(!selected.size)return null;const t=[...selected.values()].reduce((a,i)=>{const d=areas[i.region]||{};return{min:a.min+(d.min||0),max:a.max+(d.max||0)}},{min:0,max:0});const p=promos.filter(p=>p.ids.every(id=>selected.has(id))).sort((a,b)=>a.desconto-b.desconto)[0];const disc=p?.desconto||1;return{min:Math.round(t.min*disc/50)*50,max:Math.round(t.max*disc/50)*50,promo:p,off:p?Math.round((1-disc)*100):0}}
function update(){document.querySelectorAll('.hotspot').forEach(e=>e.classList.toggle('selected',selected.has(e.dataset.id)));document.querySelectorAll('.region-card').forEach(c=>c.classList.toggle('selected',[...selected.values()].some(i=>i.region===c.dataset.region)));const items=[...selected.values()],est=calc();$('count').innerText=items.length;$('selectionList').innerHTML=items.length?items.map(i=>{const d=areas[i.region]||{};return`<div class="pill"><span>${esc(i.label)}<small>${money(d.min)} - ${money(d.max)}</small></span><button data-remove="${i.id}">×</button></div>`}).join(''):'<div style="color:#aaa">Nenhuma região selecionada ainda.</div>';$('preco').innerText=est?`${money(est.min)} - ${money(est.max)}`:'---';$('descricao').innerText=est?(est.promo?`${est.promo.titulo}: ${est.promo.desc}`:(areas[items.at(-1).region]?.desc||'Região selecionada.')):'Clique em uma ou mais regiões para montar a estimativa inicial.';$('promoCurrent').style.display=est?.promo?'block':'none';if(est?.promo)$('promoCurrent').innerHTML=`<b>Promoção aplicada!</b> ${est.off}% OFF`;renderPromos();links()}
function renderPromos(){$('promoSelect').innerHTML='<option value="">Selecionar promoção</option>'+promos.map((p,i)=>`<option value="${i}">${p.titulo}</option>`).join('')}
function links(){const est=calc();const client=$('cliente').value.trim()||'Cliente ainda não informou';const idea=$('referenciaTexto').value.trim()||'Não descreveu ainda';const areaText=[...selected.values()].map(i=>i.label).join(', ')||'a definir';const price=est?`${money(est.min)} a ${money(est.max)}`:'Aguardando seleção';const msg=['Olá! Quero um orçamento de tatuagem.',`Nome: ${client}`,`Área escolhida: ${areaText}`,`Referência/ideia: ${idea}`,`Pré-orçamento: ${price}`,'Sei que o valor final depende de avaliação.'].join('\n');const href=`https://wa.me/${phone}?text=${encodeURIComponent(msg)}&utm_source=orcamento_3d`;['zap','mobileCta','topZap'].forEach(id=>$(id).href=href)}
function renderGrid(){const first={};['frente','costas'].forEach(v=>(raw[v]||[]).forEach(h=>{if(!first[h[1]])first[h[1]]={h,v}}));$('regionGrid').innerHTML=Object.keys(areas).map(k=>{const f=first[k];const h=f?fixHotspot(f.v,f.h):[null,k,k,45,45,12,12];const image=img(f?.v||'frente');return`<button class="region-card" data-region="${k}"><span class="mini"><img src="${image}"><i class="mini-hot" style="left:${h[3]}%;top:${h[4]}%;width:${h[5]}%;height:${h[6]}%"></i></span><span>${esc(areas[k].titulo)}</span></button>`}).join('');document.querySelectorAll('.region-card').forEach(b=>b.onclick=()=>selectRegion(b.dataset.region))}
function setView(v){document.querySelectorAll('[data-view]').forEach(b=>b.classList.toggle('active',b.dataset.view===v));$('frontBox').classList.toggle('hidden',v!=='frente');$('backBox').classList.toggle('hidden',v!=='costas')}
$('selectionList').onclick=e=>{const b=e.target.closest('[data-remove]');if(b){selected.delete(b.dataset.remove);update()}};
$('applyPromo').onclick=()=>{const p=promos[Number($('promoSelect').value)];if(!p)return;selected.clear();setView(p.view);p.ids.forEach(id=>{const e=document.querySelector(`[data-id="${id}"]`);if(e)selected.set(id,{id,region:e.dataset.region,label:label(e)})});update()};
document.querySelectorAll('[data-view]').forEach(b=>b.onclick=()=>setView(b.dataset.view));
['cliente','referenciaTexto'].forEach(id=>$(id).oninput=links);
draw('frente','frontBox');draw('costas','backBox');renderGrid();renderPromos();update();
</script>
</body>
</html>
