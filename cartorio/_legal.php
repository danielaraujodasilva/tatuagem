<?php
declare(strict_types=1);
/* Template compartilhado das páginas legais (Política de Privacidade / Termos de Uso).
   Estrutura pronta para publicação; os campos [entre colchetes] são os dados empresariais
   que só o dono pode fornecer (CNPJ, razão social, endereço, e-mail, WhatsApp). */
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$docType   = $docType   ?? 'politics';
$title     = $title     ?? 'Política de Privacidade';
$updatedAt = $updatedAt ?? date('d/m/Y');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#0f766e">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?> — CNJP</title>
  <link rel="stylesheet" href="./assets/local-fonts.css">
  <style>
    :root{--ink:#162235;--muted:#5b6675;--line:#e4e7ec;--teal:#0f766e;--teal2:#115e59;--tealSoft:#ecfdf5;--amber:#b45309}
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;background:#f5f7f8;color:var(--ink);font-family:Inter,system-ui,-apple-system,sans-serif;line-height:1.6;-webkit-text-size-adjust:100%}
    a{color:var(--teal)}
    .wrap{width:min(860px,calc(100% - 32px));margin-inline:auto}
    .legal-header{background:linear-gradient(160deg,#0f2928,#123f3c 60%,#0f766e);color:#fff;padding:44px 0 34px;position:sticky;top:0;z-index:10;box-shadow:0 4px 14px rgba(15,41,40,.18)}
    .legal-header .wrap{display:flex;align-items:center;gap:14px;justify-content:space-between;flex-wrap:wrap}
    .brand{display:inline-flex;align-items:center;gap:9px;text-decoration:none;color:#fff}
    .brand-mark{width:36px;height:36px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.16);font-weight:800}
    .brand strong,.brand small{display:block}
    .brand strong{font-size:.88rem;line-height:1}
    .brand small{font-size:.62rem;color:#9fd8d2;margin-top:3px}
    .back{color:#b6f0e8;font-size:.78rem;text-decoration:none;border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:7px 15px}
    .back:hover{background:rgba(255,255,255,.1)}
    .page-label{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:#0f766e}
    main{padding:34px 0 60px}
    .doc{background:#fff;border:1px solid var(--line);border-radius:18px;padding:34px 30px 40px}
    .doc h1{margin:6px 0 4px;font-size:1.6rem;letter-spacing:-.02em;line-height:1.15}
    .doc-meta{color:var(--muted);font-size:.76rem;border-bottom:1px solid var(--line);padding-bottom:18px;margin-bottom:22px}
    .doc h2{font-size:1.08rem;margin:26px 0 8px;padding-top:14px;border-top:1px solid #eef1f4}
    .doc h3{font-size:.92rem;margin:16px 0 6px;color:var(--teal2)}
    .doc p,.doc li{font-size:.85rem;color:#344054}
    .doc ul,.doc ol{margin:8px 0 12px;padding-left:22px;display:grid;gap:6px}
    .toc{background:var(--tealSoft);border:1px solid #cdece6;border-radius:14px;padding:16px 18px;margin:20px 0 6px}
    .toc strong{display:block;font-size:.78rem;margin-bottom:8px;color:var(--teal2)}
    .toc a{font-size:.78rem;display:inline-block;margin:2px 14px 2px 0}
    .placeholder{background:#fff7ed;border:1px dashed #fdba74;border-radius:12px;padding:12px 16px;font-size:.82rem;color:#9a6700;margin:12px 0}
    .placeholder b{color:#7c4a12}
    .data-table{width:100%;border-collapse:collapse;margin:12px 0;font-size:.82rem}
    .data-table th,.data-table td{border:1px solid var(--line);padding:10px 12px;text-align:left;vertical-align:top}
    .data-table th{background:#f8faf9;font-weight:700;color:var(--ink);white-space:nowrap}
    .note{background:#f8fafc;border:1px solid var(--line);border-radius:12px;padding:14px 16px;font-size:.82rem;color:#3f4a58;margin:14px 0}
    .legal-footer{border-top:1px solid var(--line);margin-top:10px;padding:26px 0 48px;color:#9aa4b1;font-size:.74rem;background:#101828}
    .legal-footer a{color:#5eead4;text-decoration:none}
    .legal-footer .wrap{display:grid;gap:8px}
    @media(max-width:560px){.doc{padding:24px 18px 30px}}
  </style>
</head>
<body>
<div class="legal-header">
  <div class="wrap">
    <a class="brand" href="./"><span class="brand-mark">CN</span><span><strong>CNJP</strong><small>Cartório Digital</small></span></a>
    <a class="back" href="./">← Voltar ao site</a>
  </div>
</div>

<main class="wrap">
  <div class="doc">
    <span class="page-label">Documento legal</span>
    <h1><?= e($title) ?></h1>
    <div class="doc-meta">Versão para revisão · Atualizado em <?= e($updatedAt) ?></div>

    <div class="placeholder"><b>Atenção:</b> esta é uma <b>minuta</b> pronta para revisão. Os campos entre colchetes — <b>[razão social], [CNPJ], [endereço], [e-mail] e [WhatsApp]</b> — precisam ser preenchidos com os dados reais do negócio antes da publicação. Enquanto não forem definidos, esta página não deve ser vinculada a formulários nem a campanhas.</div>

    <?php if ($docType === 'politics'): ?>

    <div class="toc">
      <strong>O que você vai encontrar nesta página</strong>
      <a href="#quem">1. Quem trata seus dados</a>
      <a href="#coleta">2. Quais dados coletamos</a>
      <a href="#uso">3. Para que usamos</a>
      <a href="#base">4. Base legal</a>
      <a href="#guarda">5. Guarda e retenção</a>
      <a href="#direitos">6. Seus direitos</a>
      <a href="#cookies">7. Cookies e rastreamento</a>
      <a href="#contato">8. Dúvidas e contato</a>
    </div>

    <h2 id="quem">1. Quem trata seus dados</h2>
    <p>Esta política descreve como <strong>[razão social / nome fantasia do prestador]</strong>, <strong>[CNPJ ou CPF]</strong>, com sede em <strong>[endereço]</strong>, trata os dados pessoais de quem usa este site. Somos o <strong>controlador</strong> dos dados, nos termos da Lei Geral de Proteção de Dados (Lei nº 13.709/2018).</p>
    <div class="note">A <strong>CNJP não é um cartório</strong> e não pratica atos reservados a cartórios ou a outros órgãos públicos. O serviço é de organização, facilitação e acompanhamento de procedimentos dentro da competência da empresa. Tratamos seus dados para prestar esse serviço e nada além disso.</div>

    <h2 id="coleta">2. Quais dados coletamos</h2>
    <table class="data-table">
      <tr><th>Dado</th><th>Quando</th><th>Por quê</th></tr>
      <tr><td>Nome</td><td>Formulário de pedido / atendimento humano</td><td>Identificar você no atendimento e no pedido</td></tr>
      <tr><td>Telefone ou WhatsApp</td><td>Formulário de pedido / atendimento humano</td><td>Contatar você e validar o acesso ao portal</td></tr>
      <tr><td>Descrição do que você precisa</td><td>Formulário de pedido</td><td>Entender seu caso e preparar o atendimento</td></tr>
      <tr><td>Canal preferido</td><td>Formulário de pedido</td><td>Atendê-lo pelo meio que você escolher</td></tr>
      <tr><td>Documentos (PDF, JPG, PNG)</td><td>Envio de arquivos pelo atendimento</td><td>Executar o procedimento solicitado</td></tr>
      <tr><td>Registros de atividade</td><td>Durante o uso</td><td>Auditar o andamento do pedido</td></tr>
    </table>

    <h2 id="uso">3. Para que usamos</h2>
    <ul>
      <li>Prestar e acompanhar o serviço que você solicitou;</li>
      <li>Validar seu acesso ao portal de acompanhamento (código + telefone);</li>
      <li>Entrar em contato pelos canais que você informar;</li>
      <li>Manter histórico interno e auditoria do atendimento;</li>
      <li>Cumprir obrigações legais e regulatórias.</li>
    </ul>

    <h2 id="base">4. Base legal</h2>
    <p>Tratamos seus dados com base na <strong>execução do contrato ou de providências pré-contratuais</strong> (ao solicitar um serviço), no <strong>consentimento</strong> (para comunicações além do atendimento) e em <strong>obrigação legal</strong> ou <strong>legítimo interesse</strong>, conforme o caso. Você pode pedir esclarecimento sobre a base aplicada a qualquer momento.</p>

    <h2 id="guarda">5. Guarda e retenção</h2>
    <ul>
      <li>Seus dados ficam armazenados de forma controlada e com acesso restrito à equipe;</li>
      <li><strong>Documentos enviados são retidos por até 365 dias</strong> e, após esse prazo, são removidos automaticamente do sistema;</li>
      <li>Dados do pedido são mantidos enquanto durar a relação ou enquanto houver obrigação legal de guarda.</li>
    </ul>

    <h2 id="direitos">6. Seus direitos</h2>
    <p>Você pode, a qualquer momento e gratuitamente:</p>
    <ul>
      <li>Confirmar se tratamos seus dados e <strong>acessá-los</strong>;</li>
      <li><strong>Corrigir</strong> dados incompletos, inexatos ou desatualizados;</li>
      <li>Solicitar <strong>anonimização, bloqueio ou eliminação</strong> de dados desnecessários ou excessivos;</li>
      <li>Revogar o <strong>consentimento</strong>;</li>
      <li>Requerer <strong>portabilidade</strong> e informações sobre quem compartilhamos dados;</li>
      <li>Apresentar reclamação à <strong>ANPD</strong> (Autoridade Nacional de Proteção de Dados).</li>
    </ul>

    <h2 id="cookies">7. Cookies e rastreamento</h2>
    <p>Hoje este site usa apenas a sessão necessária para o funcionamento do portal de acompanhamento. <strong>Não há rastreadores de publicidade instalados no momento.</strong> Se, no futuro, instalarmos ferramentas de medição (como pixel ou Google Analytics), esta seção será atualizada e você será avisado.</p>

    <h2 id="contato">8. Dúvidas e contato</h2>
    <p>Para exercer seus direitos ou tirar dúvidas sobre esta política, fale conosco:</p>
    <ul>
      <li>E-mail: <strong>[e-mail de contato]</strong></li>
      <li>WhatsApp / telefone: <strong>[número]</strong></li>
      <li>Endereço: <strong>[endereço]</strong></li>
    </ul>

    <?php else: ?>

    <div class="toc">
      <strong>O que você vai encontrar nesta página</strong>
      <a href="#servico">1. Serviço e natureza da CNJP</a>
      <a href="#uso-site">2. Uso do site</a>
      <a href="#pedidos">3. Pedidos e orçamento</a>
      <a href="#prazos">4. Prazos e responsabilidade</a>
      <a href="#documentos">5. Documentos</a>
      <a href="#pagamento">6. Pagamento e valores</a>
      <a href="#lgt">7. Lei aplicável e foro</a>
      <a href="#ctto">8. Contato</a>
    </div>

    <h2 id="servico">1. Serviço e natureza da CNJP</h2>
    <p><strong>[razão social]</strong>, <strong>[CNPJ]</strong>, com sede em <strong>[endereço]</strong>, presta serviços de organização, facilitação e acompanhamento de procedimentos documentais, cartorários e extrajudiciais, além de mediação, conciliação e arbitragem dentro de sua competência.</p>
    <div class="note">A <strong>CNJP não é um cartório</strong> e não pratica ato reservado a cartórios, advogados ou outros órgãos. Ao usar o serviço, você concorda com essa natureza. Atos de fé pública ou atos privativos de terceiros continuam sendo executados exclusivamente pelas instituições competentes; nós facilitamos o caminho até elas.</div>

    <h2 id="uso-site">2. Uso do site</h2>
    <ul>
      <li>Você é responsável pela veracidade das informações fornecidas;</li>
      <li>O uso para fins ilícitos, abusivos ou que induzam a erro é proibido;</li>
      <li>O acesso ao painel administrativo é restrito à equipe autorizada;</li>
      <li>Podemos ajustar o site e estes termos a qualquer momento, publicando a nova versão nesta página.</li>
    </ul>

    <h2 id="pedidos">3. Pedidos e orçamento</h2>
    <p>Ao enviar um pedido, você recebe um <strong>código de acompanhamento</strong> e um canal de contato. A solicitação passa por análise da equipe, que valida documentos e confirma os valores antes de qualquer procedimento. <strong>O envio de um pedido não gera, por si só, a contratação do serviço</strong> nem a cobrança de valores: o orçamento é confirmado em contato antes de prosseguir.</p>

    <h2 id="prazos">4. Prazos e responsabilidade</h2>
    <ul>
      <li>Prazos de órgãos e cartórios não são controlados pela CNJP e variam conforme a localidade e a complexidade;</li>
      <li>Informamos prazos estimados, mas não garantimos data específica de órgãos terceiros;</li>
      <li>Nossa responsabilidade está limitada à prestação do serviço de facilitação e acompanhamento, exceto dolo ou culpa comprovada;</li>
      <li>Em caso de desistência, informe pelo canal de contato para orientação sobre valores eventualmente já pagos.</li>
    </ul>

    <h2 id="documentos">5. Documentos</h2>
    <p>Aceitamos PDF, JPG e PNG, de até 10&nbsp;MB por arquivo. Você declara que tem direito de compartilhar os documentos enviados. Eles são usados apenas para o procedimento solicitado e <strong>retidos por até 365 dias</strong>, após o que são removidos automaticamente. Veja mais na <a href="./politica-de-privacidade.php">Política de Privacidade</a>.</p>

    <h2 id="pagamento">6. Pagamento e valores</h2>
    <p>Valores são sempre <strong>discriminados</strong>: taxas oficiais e de terceiros separadas da remuneração da CNJP pelo serviço. Nenhuma cobrança é feita antes da confirmação do orçamento com você. <strong>[Política de prazos, formas de pagamento e cancelamento a definir com o responsável — preencher aqui.]</strong></p>

    <h2 id="lgt">7. Lei aplicável e foro</h2>
    <p>Estes termos são regidos pelas leis brasileiras. Fica eleito o foro da comarca de <strong>[comarca]</strong> para dirimir dúvidas, sem prejuízo dos canais de solução consensual que oferecemos.</p>

    <h2 id="ctto">8. Contato</h2>
    <ul>
      <li>E-mail: <strong>[e-mail de contato]</strong></li>
      <li>WhatsApp / telefone: <strong>[número]</strong></li>
      <li>Endereço: <strong>[endereço]</strong></li>
    </ul>

    <?php endif; ?>

  </div>
</main>

<footer class="legal-footer">
  <div class="wrap">
    <span><a href="./">CNJP Cartório Digital</a> · <a href="./politica-de-privacidade.php">Política de Privacidade</a> · <a href="./termos-de-uso.php">Termos de Uso</a></span>
    <span>© <?= date('Y') ?> [razão social] — Todos os direitos reservados.</span>
  </div>
</footer>
</body>
</html>