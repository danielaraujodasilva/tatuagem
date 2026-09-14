<?php
declare(strict_types=1);
/* Página de obrigado: confirma o pedido e mostra os próximos passos.
   Recebe o código por ?code=CD-0000. Serve também como URL de conversão
   para medir o envio (é o marco real de "lead gerado"). */
$code = strtoupper(trim((string)($_GET['code'] ?? '')));
$code = preg_replace('/[^A-Z0-9-]/', '', $code);

$pedido = null;
if ($code !== '') {
    try {
        $dir = dirname(__DIR__) . '/data';
        if (is_file($dir . '/cartorio.sqlite')) {
            $db = new PDO('sqlite:' . $dir . '/cartorio.sqlite');
            $s = $db->prepare('SELECT code,service,channel,status,created_at FROM requests WHERE code=? AND archived=0');
            $s->execute([$code]);
            $pedido = $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) { $pedido = null; }
}
$title = 'Pedido recebido';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0f766e">
<meta name="robots" content="noindex,follow">
<title><?= htmlspecialchars($title) ?> — CNJP</title>
<link rel="stylesheet" href="../assets/local-fonts.css">
<style>
:root{--ink:#162235;--muted:#5b6675;--line:#e4e7ec;--teal:#0f766e;--tealSoft:#ecfdf5}
*{box-sizing:border-box}
body{margin:0;background:#f5f7f8;color:var(--ink);font-family:Inter,system-ui,-apple-system,sans-serif;line-height:1.55;-webkit-text-size-adjust:100%}
.wrap{width:min(680px,calc(100% - 32px));margin-inline:auto}
header.head{background:linear-gradient(160deg,#0f2928,#123f3c 60%,#0f766e);color:#fff;padding:30px 0}
.brand{display:inline-flex;align-items:center;gap:9px;text-decoration:none;color:#fff}
.brand-mark{width:36px;height:36px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.16);font-weight:800}
.brand strong,.brand small{display:block}
.brand strong{font-size:.88rem;line-height:1}
.brand small{font-size:.62rem;color:#9fd8d2;margin-top:3px}
main{padding:38px 0 60px}
.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:32px 28px;text-align:center}
.icon{width:62px;height:62px;border-radius:50%;background:var(--tealSoft);color:var(--teal);display:grid;place-items:center;margin:0 auto 16px;font-size:32px;font-weight:800}
h1{font-size:1.5rem;letter-spacing:-.03em;margin:0 0 10px;line-height:1.2}
p.lead{color:var(--muted);font-size:.9rem;margin:0 auto 20px;max-width:480px}
.code-box{display:inline-flex;flex-direction:column;gap:4px;background:var(--tealSoft);border:1px solid #99d5ce;border-radius:14px;padding:14px 26px;margin-bottom:8px}
.code-box span{font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#115e59}
.code-box strong{font-size:1.45rem;letter-spacing:.02em;color:#115e59;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.sem-code{background:#fff7ed;border:1px solid #fdba74;color:#9a6700;border-radius:12px;padding:12px 16px;font-size:.82rem}
.steps{text-align:left;margin:26px 0 0;padding:0;list-style:none;display:grid;gap:10px}
.steps li{display:flex;gap:12px;align-items:flex-start;border:1px solid var(--line);border-radius:13px;padding:13px 15px}
.steps b{flex:0 0 24px;height:24px;border-radius:50%;background:var(--tealSoft);color:var(--teal);display:grid;place-items:center;font-size:.7rem}
.steps strong,.steps span{display:block}
.steps strong{font-size:.8rem}
.steps span{font-size:.7rem;color:var(--muted);margin-top:2px}
.detalhe{margin-top:20px;font-size:.75rem;color:var(--muted);border-top:1px solid var(--line);padding-top:16px}
.detalhe b{color:var(--ink)}
.actions{display:flex;flex-direction:column;gap:9px;margin-top:24px}
.actions a,.actions button{display:block;text-decoration:none;text-align:center;border-radius:12px;padding:14px 18px;font-size:.85rem;font-weight:700;border:0;cursor:pointer}
.btn-primary{background:var(--teal);color:#fff}
.btn-secondary{background:#fff;color:var(--ink);border:1px solid #d0d5dd!important}
.mail{margin-top:18px;font-size:.73rem;color:var(--muted)}
@media(min-width:600px){.actions{flex-direction:row;justify-content:center}.actions a,.actions button{min-width:210px}}
</style>
</head>
<body>
<header class="head"><div class="wrap"><a class="brand" href="../"><span class="brand-mark">CN</span><span><strong>CNJP</strong><small>Cartório Digital</small></span></a></div></header>
<main><div class="wrap">
  <div class="card">
    <div class="icon">✓</div>
    <h1>Recebemos o seu pedido.</h1>
    <p class="lead">Ele já está na fila de atendimento. Um integrante da equipe confere os dados e o checklist antes de qualquer protocolo.</p>

    <?php if ($pedido): ?>
      <div class="code-box"><span>Seu código</span><strong><?= htmlspecialchars($pedido['code']) ?></strong></div>
      <p class="lead">Guarde este código: é com ele e com o telefone informado que você acompanha o andamento.</p>
      <div class="detalhe">
        <b>Serviço:</b> <?= htmlspecialchars((string)$pedido['service']) ?><br>
        <b>Recebido em:</b> <?= htmlspecialchars((string)$pedido['created_at']) ?><br>
        <b>Etapa atual:</b> recebido
      </div>
    <?php elseif ($code !== ''): ?>
      <div class="sem-code">Não localizamos o pedido <b><?= htmlspecialchars($code) ?></b>. Se você acabou de enviar, aguarde alguns instantes e recarregue. Em caso de dúvida, fale com a equipe.</div>
    <?php else: ?>
      <div class="sem-code">Você chegou pela página de confirmação, mas sem o código do pedido. Se enviou uma solicitação, verifique o e-mail de confirmação ou fale com a equipe.</div>
    <?php endif; ?>

    <ol class="steps">
      <li><b>1</b><div><strong>A equipe confere</strong><span>Serviço, documentos e valores do seu caso.</span></div></li>
      <li><b>2</b><div><strong>Você recebe o orçamento</strong><span>Com taxas oficiais, terceiros e serviço CNJP separados. Nada é cobrado sem a sua aprovação.</span></div></li>
      <li><b>3</b><div><strong>Acompanha pelo portal</strong><span>Cada mudança de etapa aparece no portal e você é avisado.</span></div></li>
    </ol>

    <div class="actions">
      <a class="btn-primary" href="../#clientPortal">Acompanhar meu pedido</a>
      <a class="btn-secondary" href="../">Voltar ao site</a>
    </div>
    <p class="mail">Se você informou um e-mail, a confirmação com o código já está a caminho. Não chegou em alguns minutos? Confira a caixa de spam.</p>
  </div>
</div></main>
</body>
</html>
