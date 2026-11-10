<?php
declare(strict_types=1);
/**
 * Painel de acompanhamento do processamento em lote (padronizacao de fotos/videos).
 * URL: https://danieltatuador.com/instagram/progresso.php?k=SEU_TOKEN
 *
 * Le o arquivo cache/progresso.json, que e atualizado localmente pelo script
 * progresso_site.py enquanto o processamento roda.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=utf-8');

$keyFile = __DIR__ . '/progresso-key.local.php';
$token = is_file($keyFile) ? (string)(require $keyFile) : '';
if ($token !== '') {
    $k = (string)($_GET['k'] ?? '');
    if (!hash_equals($token, $k)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<body style="background:#101014;color:#e8e8ec;font-family:system-ui,sans-serif;padding:2rem">';
        echo '<h2>Acesso restrito</h2><p>Link invalido. Use o endereco completo com o codigo <code>?k=</code>.</p></body>';
        exit;
    }
}

$arquivo = __DIR__ . '/cache/progresso.json';
$d = is_file($arquivo) ? json_decode((string)file_get_contents($arquivo), true) : null;
if (!is_array($d)) {
    $d = [];
}

$arquivos  = (int)($d['arquivos'] ?? 0);
$total     = max(1, (int)($d['total_previsto'] ?? 1));
$pct       = min(100, (int)round($arquivos * 100 / $total));
$rodando   = (bool)($d['rodando'] ?? false);
$concluido = (bool)($d['concluido'] ?? false);
$fase      = (string)($d['fase'] ?? '-');
$eta       = $d['eta_min'] ?? null;
$ritmo     = $d['ritmo_min'] ?? null;
$ultimas   = is_array($d['ultimas'] ?? null) ? $d['ultimas'] : [];
$mb        = (float)($d['mb'] ?? 0);
$fotos     = (int)($d['fotos'] ?? 0);
$videos    = (int)($d['videos'] ?? 0);
$ignorados = (int)($d['ignorados'] ?? 0);
$atualizado= (string)($d['atualizado_em'] ?? 'nunca');
$inicio    = (string)($d['inicio'] ?? '-');
$resumo    = (string)($d['resumo'] ?? '');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dur(?float $min): string {
    if ($min === null || $min <= 0) return '--';
    $m = (int)round($min);
    if ($m < 60) return $m . ' min';
    return intdiv($m, 60) . 'h' . str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT);
}

$cor = $concluido ? '#4ade80' : ($rodando ? '#fbbf24' : '#94a3b8');
$statusTxt = $concluido ? 'Concluido' : ($rodando ? 'Processando' : 'Parado');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Progresso - Padronizacao</title>
<style>
  * { box-sizing: border-box; }
  body { margin:0; padding:18px; background:#0d0d11; color:#e9e9ee;
         font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
  .wrap { max-width:640px; margin:0 auto; }
  h1 { font-size:1.15rem; margin:0 0 4px; font-weight:600; }
  .sub { color:#8b8b99; font-size:.8rem; margin-bottom:18px; }
  .card { background:#16161c; border:1px solid #24242e; border-radius:14px;
          padding:16px; margin-bottom:12px; }
  .top { display:flex; justify-content:space-between; align-items:center; gap:12px; }
  .badge { font-size:.75rem; font-weight:600; padding:5px 11px; border-radius:999px;
           background:<?= $cor ?>22; color:<?= $cor ?>; border:1px solid <?= $cor ?>55; white-space:nowrap; }
  .big { font-size:2.4rem; font-weight:700; letter-spacing:-.02em; line-height:1.1; }
  .big small { font-size:1rem; color:#8b8b99; font-weight:500; }
  .barra { height:10px; background:#24242e; border-radius:999px; overflow:hidden; margin:14px 0 6px; }
  .barra > i { display:block; height:100%; width:<?= $pct ?>%;
               background:linear-gradient(90deg,#3b82f6,<?= $cor ?>); border-radius:999px; }
  .linha { display:flex; justify-content:space-between; font-size:.83rem; color:#9a9aab; }
  .grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; text-align:center; }
  .grid div span { display:block; font-size:1.25rem; font-weight:600; color:#e9e9ee; }
  .grid div small { font-size:.72rem; color:#8b8b99; }
  .lbl { font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; color:#8b8b99;
         margin-bottom:8px; }
  ul { list-style:none; margin:0; padding:0; font-size:.78rem; font-family:ui-monospace,Menlo,monospace; }
  ul li { padding:3px 0; color:#a9a9ba; border-bottom:1px solid #1e1e26; overflow:hidden;
          text-overflow:ellipsis; white-space:nowrap; }
  ul li:last-child { border:0; }
  ul li.ok { color:#7dd3a0; }
  ul li.ig { color:#c9a227; }
  .foot { text-align:center; color:#5f5f6d; font-size:.72rem; margin-top:16px; line-height:1.7; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Padronizacao do acervo</h1>
  <div class="sub">Fotos e videos do Instagram &middot; atualiza sozinho a cada 30s</div>

  <div class="card">
    <div class="top">
      <div class="big"><?= $arquivos ?><small> / <?= $total ?></small></div>
      <div class="badge"><?= h($statusTxt) ?></div>
    </div>
    <div class="barra"><i></i></div>
    <div class="linha"><span><?= $pct ?>% concluido</span><span>faltam <?= max(0, $total - $arquivos) ?></span></div>
  </div>

  <div class="card">
    <div class="grid">
      <div><span><?= dur($eta === null ? null : (float)$eta) ?></span><small>tempo restante</small></div>
      <div><span><?= $ritmo === null ? '--' : h(number_format((float)$ritmo, 1, ',', '.')) ?></span><small>arquivos/min</small></div>
      <div><span><?= h(number_format($mb, 0, ',', '.')) ?> MB</span><small>gerados</small></div>
    </div>
  </div>

  <div class="card">
    <div class="lbl">Detalhes</div>
    <div class="linha" style="margin-bottom:6px"><span>Fase atual</span><span><?= h($fase) ?></span></div>
    <div class="linha" style="margin-bottom:6px"><span>Fotos / videos prontos</span><span><?= $fotos ?> / <?= $videos ?></span></div>
    <div class="linha" style="margin-bottom:6px"><span>Videos longos ignorados</span><span><?= $ignorados ?></span></div>
    <div class="linha" style="margin-bottom:6px"><span>Iniciado em</span><span><?= h($inicio) ?></span></div>
    <div class="linha"><span>Ultima atualizacao</span><span><?= h($atualizado) ?></span></div>
  </div>

  <?php if ($resumo !== ''): ?>
  <div class="card" style="border-color:#4ade8055">
    <div class="lbl">Resultado</div>
    <div style="font-size:.9rem;color:#4ade80"><?= h($resumo) ?></div>
  </div>
  <?php endif; ?>

  <?php if ($ultimas): ?>
  <div class="card">
    <div class="lbl">Ultimos arquivos</div>
    <ul>
      <?php foreach (array_reverse($ultimas) as $l):
          $cl = strpos($l, 'ok ') === 0 ? 'ok' : (strpos($l, 'ignorado') === 0 ? 'ig' : ''); ?>
        <li class="<?= $cl ?>"><?= h($l) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="foot">
    Pasta de saida: PADRONIZADO-COMPLETO<br>
    <a href="galeria.php?k=<?= rawurlencode($k) ?>" style="color:#7f9fd8">abrir a galeria do acervo</a><br>
    Pagina reservada &middot; nao indexada
  </div>
</div>
<script>setTimeout(function(){ location.reload(); }, 30000);</script>
</body>
</html>
