<?php
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

$expiredPromo = '<div class="promo-col bg5" data-title="Sorteio Filantrópico" data-content="&lt;p&gt;Estamos ajudando uma mamãe de Guarulhos que teve uma bebê e infelizmente está precisando de ajuda. Então, para cada pessoa que fizer a doação de algo que seja útil para ajudar essa mãe e/ou essa bebê, estará concorrendo a um fechamento no valor de R$999 com nossa equipe! O Sorteio será no dia 10 de Outubro de 2025!&lt;/p&gt;"><span>Sorteio Filantrópico</span></div>';
$html = str_replace($expiredPromo, '', $html);

$heroOverride = <<<'HTML'
<style id="hero-final-adjustments">
.hero{
  background:linear-gradient(90deg,rgba(5,4,3,1),rgba(5,4,3,.94) 32%,rgba(5,4,3,.42) 67%,rgba(5,4,3,1)) !important;
}
.hero:before{
  display:none !important;
  content:none !important;
}
.hero-visual{
  position:relative;
  min-height:1200px !important;
}
.artist-silhouette{
  position:absolute;
  inset:0 -6% -8% 5%;
  background:url(img/daniel.jpg) center / cover no-repeat;
  border-radius:0 !important;
  filter:none !important;
  opacity:1 !important;
  mix-blend-mode:normal !important;
  mask-image:linear-gradient(to bottom,#000 60%,transparent);
}
@media(max-width:720px){
  .hero-grid{
    grid-template-columns:1fr !important;
    padding-bottom:64px !important;
  }
  .hero-visual{
    display:none !important;
  }
}
</style>
<script id="promo-carousel-autoplay">
document.addEventListener('DOMContentLoaded',function(){
  var wrapper=document.querySelector('.promo-carousel-wrapper');
  var track=document.querySelector('.promo-carousel-wrapper .promo-track');
  if(!wrapper||!track){return;}
  var timer=null;
  var index=0;

  function getCards(){return Array.prototype.slice.call(document.querySelectorAll('.promo-carousel-wrapper .promo-col'));}
  function getDots(){return Array.prototype.slice.call(document.querySelectorAll('.promo-carousel-wrapper .promo-dots button'));}
  function visibleCount(){return window.innerWidth<720?1:(window.innerWidth<1100?3:6);}
  function stepSize(){
    var card=getCards()[0];
    if(!card){return 0;}
    return card.getBoundingClientRect().width+(window.innerWidth<720?18:-38);
  }
  function maxIndex(){return Math.max(0,getCards().length-visibleCount());}
  function syncFromActiveDot(){
    var dots=getDots();
    var active=document.querySelector('.promo-carousel-wrapper .promo-dots button.active');
    var found=dots.indexOf(active);
    if(found>=0){index=found;}
  }
  function goTo(nextIndex){
    var max=maxIndex();
    index=nextIndex>max?0:nextIndex;
    var dots=getDots();
    if(dots[index]){dots[index].click();return;}
    track.style.transform='translateX('+(-index*stepSize())+'px)';
  }
  function nextSlide(){
    syncFromActiveDot();
    goTo(index+1);
  }
  function start(){
    stop();
    timer=setInterval(nextSlide,4000);
  }
  function stop(){
    if(timer){clearInterval(timer);timer=null;}
  }

  wrapper.addEventListener('mouseenter',stop);
  wrapper.addEventListener('mouseleave',start);
  wrapper.addEventListener('touchstart',stop,{passive:true});
  wrapper.addEventListener('touchend',start,{passive:true});
  window.addEventListener('resize',function(){syncFromActiveDot();goTo(index);});
  start();
});
</script>
HTML;

$html = str_replace('</head>', $heroOverride . "\n</head>", $html);
echo $html;
