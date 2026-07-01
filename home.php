<?php
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

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
  var next=document.querySelector('.promo-carousel-wrapper .carousel-btn.next');
  var dots=document.querySelectorAll('.promo-carousel-wrapper .promo-dots button');
  if(!wrapper||!next){return;}
  var timer=null;
  function start(){
    stop();
    timer=setInterval(function(){
      var active=document.querySelector('.promo-carousel-wrapper .promo-dots button.active');
      var isLast=active&&dots.length&&active===dots[dots.length-1];
      if(isLast){
        var firstDot=document.querySelector('.promo-carousel-wrapper .promo-dots button:first-child');
        if(firstDot){firstDot.click();return;}
      }
      next.click();
    },4000);
  }
  function stop(){
    if(timer){clearInterval(timer);timer=null;}
  }
  wrapper.addEventListener('mouseenter',stop);
  wrapper.addEventListener('mouseleave',start);
  wrapper.addEventListener('touchstart',stop,{passive:true});
  wrapper.addEventListener('touchend',start,{passive:true});
  start();
});
</script>
HTML;

$html = str_replace('</head>', $heroOverride . "\n</head>", $html);
echo $html;
