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
</style>
HTML;

$html = str_replace('</head>', $heroOverride . "\n</head>", $html);
echo $html;
