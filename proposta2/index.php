<?php
declare(strict_types=1);
/* Proposta Comercial - Câmara de Arbitragem
   Empreendimento Maria Vitória | Cocaia - Guarulhos/SP
   Estrutura visual baseada em /proposta/ (CNJP), conteúdo próprio. */

function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b3b7e">
<title>Proposta Comercial &mdash; Câmara de Arbitragem | Maria Vitória</title>
<meta name="description" content="Proposta comercial da Câmara de Arbitragem para o Empreendimento Maria Vitória (Cocaia, Guarulhos/SP): contratos ativos, contratos judicializados e conciliação trabalhista preventiva.">
<!-- Proposta comercial confidencial: não indexar. Remova a linha abaixo se quiser pública. -->
<meta name="robots" content="noindex,nofollow">
<meta property="og:type" content="website">
<meta property="og:title" content="Proposta Comercial - Câmara de Arbitragem | Maria Vitória">
<meta property="og:description" content="Solução consensual de conflitos para o Empreendimento Maria Vitória (Cocaia - Guarulhos/SP).">
<meta property="og:locale" content="pt_BR">
<meta name="twitter:card" content="summary">
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="./assets/local-fonts.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root{
    --cnjp-primary:#0b3b7e;
    --cnjp-secondary:#1256b0;
    --cnjp-accent:#e6f0ff;
  }
  html{scroll-behavior:smooth}
  body{color:#1b2430;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
  /* Compensa a navbar fixa ao pular para as seções por âncora */
  section[id],header[id]{scroll-margin-top:84px}
  a:focus-visible,button:focus-visible{outline:3px solid var(--cnjp-secondary);outline-offset:2px}
  .bg-cnjp{ background: linear-gradient(135deg, var(--cnjp-primary), var(--cnjp-secondary)); }
  .text-cnjp{ color: var(--cnjp-primary) !important; }
  .btn-cnjp{ background: var(--cnjp-primary); border-color: var(--cnjp-primary); color:#fff; }
  .btn-cnjp:hover{ background: var(--cnjp-secondary); border-color: var(--cnjp-secondary); color:#fff; }
  .badge-soft{ background: var(--cnjp-accent); color: var(--cnjp-primary); }
  .feature-icon{ font-size:2rem; }
  .shadow-soft{ box-shadow: 0 10px 25px rgba(11,59,126,.15); }
  .hero-wave{ position:relative; overflow:hidden; }
  .price-tag{ background: var(--cnjp-accent); color: var(--cnjp-primary); border-radius: .75rem; font-weight:800; }
  .flow .step{
    display:flex; gap:.6rem; align-items:flex-start;
    border:1px solid #e4e7ec; border-radius:.75rem; padding:.7rem .85rem; font-size:.85rem; background:#fff;
  }
  .flow .step b{
    flex:0 0 auto; width:22px; height:22px; border-radius:50%;
    background: var(--cnjp-accent); color: var(--cnjp-primary);
    display:grid; place-items:center; font-size:.7rem;
  }
  .resumo-list{ list-style:none; padding:0; margin:0; }
  .resumo-list li{ display:flex; gap:.6rem; align-items:flex-start; padding:.55rem 0; border-bottom:1px dashed #dbe3ef; }
  .resumo-list li:last-child{ border-bottom:0; }
  .resumo-list i{ color: var(--cnjp-primary); margin-top:.15rem; }
  @media print{
    .no-print{ display:none !important; }
    .card, .accordion-button{ box-shadow:none !important; }
    body{ font-size:12px; }
  }
</style>
</head>
<body id="top">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-cnjp shadow-sm sticky-top no-print">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#top"><i class="bi bi-shield-check me-2" aria-hidden="true"></i>Câmara de Arbitragem</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Alternar navegação">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="#apresentacao">Apresentação</a></li>
        <li class="nav-item"><a class="nav-link" href="#ativos">Contratos ativos</a></li>
        <li class="nav-item"><a class="nav-link" href="#judicializados">Judicializados</a></li>
        <li class="nav-item"><a class="nav-link" href="#trabalhista">Trabalhista</a></li>
        <li class="nav-item"><a class="nav-link" href="#resumo">Resumo</a></li>
      </ul>
      <div class="ms-lg-3 d-flex gap-2">
        <a class="btn btn-light btn-sm" href="#resumo"><i class="bi bi-cash-coin me-1" aria-hidden="true"></i> Ver valores</a>
        <button class="btn btn-outline-light btn-sm no-print" type="button" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir</button>
      </div>
    </div>
  </div>
</nav>

<!-- Hero -->
<header class="hero-wave bg-cnjp text-white py-5">
  <div class="container py-4">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">Proposta Comercial &middot; Câmara de Arbitragem</span>
        <h1 class="display-5 fw-bold mb-3">Solução Consensual de Conflitos<br class="d-none d-md-block"> Empreendimento Maria Vitória</h1>
        <p class="lead mb-3">Cocaia &mdash; Guarulhos/SP</p>
        <p class="mb-0 text-white-50">Atuação da Câmara de Arbitragem em parceria com o Dr. Eric e seu escritório, conduzindo de forma organizada e consensual as demandas dos adquirentes do empreendimento, além da prevenção de novas demandas trabalhistas.</p>
      </div>
      <div class="col-lg-4">
        <div class="card glass border-0 shadow-soft text-dark">
          <div class="card-body">
            <h6 class="text-cnjp mb-3"><i class="bi bi-card-checklist me-2" aria-hidden="true"></i>Escopo da proposta</h6>
            <ul class="list-unstyled small mb-0">
              <li class="mb-2"><i class="bi bi-check2-circle me-2 text-cnjp" aria-hidden="true"></i>Até 160 contratos ativos</li>
              <li class="mb-2"><i class="bi bi-check2-circle me-2 text-cnjp" aria-hidden="true"></i>Contratos já judicializados</li>
              <li class="mb-2"><i class="bi bi-check2-circle me-2 text-cnjp" aria-hidden="true"></i>20 conciliações trabalhistas preventivas</li>
              <li><i class="bi bi-check2-circle me-2 text-cnjp" aria-hidden="true"></i>Parceria com Dr. Eric e escritório</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Apresentação -->
<section id="apresentacao" class="py-5">
  <div class="container">
    <div class="row g-4 align-items-start">
      <div class="col-lg-7">
        <h2 class="h3 text-cnjp"><i class="bi bi-building-check me-2" aria-hidden="true"></i>Apresentação</h2>
        <p class="text-muted">Esta proposta tem por objetivo conduzir, de forma organizada e consensual, as demandas envolvendo os adquirentes do empreendimento Maria Vitória, bem como prevenir novas demandas trabalhistas.</p>
        <p class="text-muted mb-0">A atuação será realizada em <strong>parceria com o Dr. Eric e seu escritório</strong>, com divisão das atribuições conforme a natureza de cada procedimento, preservando a segurança jurídica e o relacionamento com os clientes.</p>
      </div>
      <div class="col-lg-5">
        <div class="row g-3">
          <div class="col-6">
            <div class="card h-100 text-center shadow-soft border-0">
              <div class="card-body">
                <div class="feature-icon text-cnjp"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
                <h6 class="mt-3 mb-1">Consensual</h6>
                <p class="small text-muted mb-0">Acordos com concordância expressa do consumidor.</p>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 text-center shadow-soft border-0">
              <div class="card-body">
                <div class="feature-icon text-cnjp"><i class="bi bi-shield-check" aria-hidden="true"></i></div>
                <h6 class="mt-3 mb-1">Segurança</h6>
                <p class="small text-muted mb-0">Formalização, aditivos e Conciarb quando cabível.</p>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 text-center shadow-soft border-0">
              <div class="card-body">
                <div class="feature-icon text-cnjp"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></div>
                <h6 class="mt-3 mb-1">Escala</h6>
                <p class="small text-muted mb-0">Condições especiais por volume de procedimentos.</p>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 text-center shadow-soft border-0">
              <div class="card-body">
                <div class="feature-icon text-cnjp"><i class="bi bi-eye-slash" aria-hidden="true"></i></div>
                <h6 class="mt-3 mb-1">Prevenção</h6>
                <p class="small text-muted mb-0">Composição antes da instauração de novos litígios.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 1. Contratos ativos -->
<section id="ativos" class="py-5 bg-light">
  <div class="container">
    <div class="row mb-4">
      <div class="col-lg-8">
        <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">1 &middot; Contratos ativos</span>
        <h2 class="h3 text-cnjp mb-2"><i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Até 160 adquirentes</h2>
        <p class="text-muted mb-0">Para os contratos ativos, será realizado um procedimento individual de conciliação.</p>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-list-check me-2" aria-hidden="true"></i>O procedimento contempla</h6>
            <div class="flow d-grid gap-2">
              <div class="step"><b>1</b><span>Notificação / convocação do adquirente</span></div>
              <div class="step"><b>2</b><span>Abertura do procedimento</span></div>
              <div class="step"><b>3</b><span>Audiência de conciliação</span></div>
              <div class="step"><b>4</b><span>Negociação entre as partes</span></div>
              <div class="step"><b>5</b><span>Concordância expressa e específica do consumidor</span></div>
              <div class="step"><b>6</b><span>Formalização do acordo</span></div>
              <div class="step"><b>7</b><span>Elaboração do aditivo contratual</span></div>
              <div class="step"><b>8</b><span>Conciarb / compromisso arbitral, quando cabível e expressamente aceito</span></div>
              <div class="step"><b>9</b><span>Encerramento do procedimento</span></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4 d-flex flex-column">
            <h6 class="text-cnjp mb-3"><i class="bi bi-cash-coin me-2" aria-hidden="true"></i>Investimento</h6>
            <p class="small text-muted mb-3">Valor de referência de <strong>01 salário por procedimento</strong>.</p>
            <div class="price-tag p-3 mb-2 text-center">
              <div class="small text-uppercase" style="letter-spacing:.06em">Condição especial em escala</div>
              <div class="fs-3">R$ 900,00</div>
              <div class="small">por procedimento</div>
            </div>
            <p class="small text-muted mb-0">Condição aplicável à contratação em escala para o volume de até 160 adquirentes.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Judicializados -->
<section id="judicializados" class="py-5">
  <div class="container">
    <div class="row mb-4">
      <div class="col-lg-8">
        <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">2 &middot; Contratos já judicializados</span>
        <h2 class="h3 text-cnjp mb-2"><i class="bi bi-bank me-2" aria-hidden="true"></i>Composição consensual</h2>
        <p class="text-muted mb-0">Nos casos em que o adquirente já possui processo judicial contra a construtora, será realizada uma tentativa estruturada de composição consensual.</p>
      </div>
    </div>
    <div class="card shadow-soft border-0 mb-4">
      <div class="card-body p-4">
        <h6 class="text-cnjp mb-3"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>Fluxo</h6>
        <div class="flow d-flex flex-wrap gap-2">
          <div class="step"><b>1</b><span>Análise do caso</span></div>
          <div class="step"><b>2</b><span>Convocação</span></div>
          <div class="step"><b>3</b><span>Audiência de conciliação</span></div>
          <div class="step"><b>4</b><span>Negociação</span></div>
          <div class="step"><b>5</b><span>Acordo</span></div>
          <div class="step"><b>6</b><span>Formalização</span></div>
          <div class="step"><b>7</b><span>Providências judiciais cabíveis</span></div>
        </div>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-percent me-2" aria-hidden="true"></i>Tabela padrão da Câmara</h6>
            <div class="price-tag p-3 text-center">
              <div class="fs-3">10%</div>
              <div class="small">sobre o valor do conflito</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-tags me-2" aria-hidden="true"></i>Condição especial em escala</h6>
            <div class="price-tag p-3 mb-3 text-center">
              <div class="fs-3">3%</div>
              <div class="small">sobre o valor discutido</div>
            </div>
            <p class="small text-muted mb-0">Considerando o volume de demandas e a contratação em escala, o percentual de <strong>3% permanece aberto a negociação</strong>, conforme volume, complexidade e quantidade de processos.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Trabalhista -->
<section id="trabalhista" class="py-5 bg-light">
  <div class="container">
    <div class="row mb-4">
      <div class="col-lg-8">
        <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">3 &middot; Conciliação trabalhista preventiva</span>
        <h2 class="h3 text-cnjp mb-2"><i class="bi bi-person-workspace me-2" aria-hidden="true"></i>20 funcionários</h2>
        <p class="text-muted mb-0">Procedimentos destinados aos funcionários que foram ou estão sendo dispensados pela construtora, buscando composição consensual e preventiva antes da eventual instauração de uma reclamação trabalhista.</p>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>Fluxo</h6>
            <div class="flow d-grid gap-2">
              <div class="step"><b>1</b><span>Notificação</span></div>
              <div class="step"><b>2</b><span>Abertura do procedimento</span></div>
              <div class="step"><b>3</b><span>Audiência de conciliação</span></div>
              <div class="step"><b>4</b><span>Negociação</span></div>
              <div class="step"><b>5</b><span>Acordo</span></div>
              <div class="step"><b>6</b><span>Formalização</span></div>
              <div class="step"><b>7</b><span>Encerramento</span></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-cash-coin me-2" aria-hidden="true"></i>Investimento</h6>
            <p class="small text-muted mb-3">O valor padrão da Câmara corresponde a <strong>01 salário por procedimento</strong>. Para contratação em escala, acima de 5 procedimentos, aplica-se condição especial de <strong>meio salário por procedimento</strong>.</p>
            <div class="price-tag p-3 text-center">
              <div class="small text-uppercase" style="letter-spacing:.06em">Condição especial</div>
              <div class="fs-3">R$ 800,00</div>
              <div class="small">por conciliação</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Resumo -->
<section id="resumo" class="py-5">
  <div class="container">
    <div class="row mb-4">
      <div class="col-lg-8">
        <h2 class="h3 text-cnjp"><i class="bi bi-clipboard-data me-2" aria-hidden="true"></i>Resumo da proposta</h2>
        <p class="text-muted mb-0">Condições comerciais consolidadas.</p>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <ul class="resumo-list">
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><strong>Contratos ativos:</strong> R$ 900,00 por procedimento</span></li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><strong>Contratos judicializados:</strong> 3% sobre o valor discutido &mdash; condição especial aberta a negociação</span></li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><strong>20 conciliações trabalhistas preventivas:</strong> R$ 800,00 por procedimento</span></li>
              <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><strong>Total das conciliações trabalhistas:</strong> R$ 16.000,00</span></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4 d-flex flex-column">
            <h6 class="text-cnjp mb-3"><i class="bi bi-people me-2" aria-hidden="true"></i>Parceria</h6>
            <p class="small text-muted mb-3">O total das conciliações trabalhistas considera 20 procedimentos &times; R$ 800,00.</p>
            <div class="alert alert-primary border-0 shadow-soft d-flex align-items-start mb-0" role="alert">
              <i class="bi bi-info-circle-fill me-3 fs-4" aria-hidden="true"></i>
              <div class="small">A atuação será realizada em parceria com o <strong>Dr. Eric</strong> e seu escritório, com divisão das atribuições conforme a natureza de cada procedimento.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Rodapé -->
<footer class="bg-cnjp text-white py-4">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small">Câmara de Arbitragem &mdash; Conciliação &middot; Prevenção &middot; Arbitragem &middot; Solução de Conflitos</div>
    <div class="small no-print"><a href="#top" class="text-white text-decoration-none">Voltar ao topo</a></div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
