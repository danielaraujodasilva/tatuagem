<?php
declare(strict_types=1);
/* Proposta Comercial - Camara de Arbitragem
   Empreendimento Maria Vitoria | Cocaia - Guarulhos/SP
   Estrutura visual baseada em /proposta/ (CNJP), conteudo proprio. */

function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b3b7e">
<title>Proposta Comercial &mdash; Camara de Arbitragem | Maria Vitoria</title>
<meta name="description" content="Proposta comercial da Camara de Arbitragem para o Empreendimento Maria Vitoria (Cocaia, Guarulhos/SP): contratos ativos, contratos judicializados e conciliacao trabalhista preventiva.">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="/favicon.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  :root{
    --cnjp-primary:#0b3b7e;
    --cnjp-secondary:#1256b0;
    --cnjp-accent:#e6f0ff;
  }
  html{scroll-behavior:smooth}
  body{color:#1b2430}
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
    <a class="navbar-brand fw-bold" href="#top"><i class="bi bi-shield-check me-2"></i>Camara de Arbitragem</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Alternar navegacao">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="#apresentacao">Apresentacao</a></li>
        <li class="nav-item"><a class="nav-link" href="#ativos">Contratos ativos</a></li>
        <li class="nav-item"><a class="nav-link" href="#judicializados">Judicializados</a></li>
        <li class="nav-item"><a class="nav-link" href="#trabalhista">Trabalhista</a></li>
        <li class="nav-item"><a class="nav-link" href="#resumo">Resumo</a></li>
      </ul>
      <div class="ms-lg-3 d-flex gap-2">
        <a class="btn btn-light btn-sm" href="#resumo"><i class="bi bi-cash-coin me-1"></i> Ver valores</a>
        <button class="btn btn-outline-light btn-sm no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
      </div>
    </div>
  </div>
</nav>

<!-- Hero -->
<header class="hero-wave bg-cnjp text-white py-5">
  <div class="container py-4">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">Proposta Comercial &middot; Camara de Arbitragem</span>
        <h1 class="display-5 fw-bold mb-3">Solucao Consensual de Conflitos<br class="d-none d-md-block"> Empreendimento Maria Vitoria</h1>
        <p class="lead mb-3">Cocaia &mdash; Guarulhos/SP</p>
        <p class="mb-0 text-white-50">Atuacao da Camara de Arbitragem em parceria com o Dr. Eric e seu escritorio, conduzindo de forma organizada e consensual as demandas dos adquirentes do empreendimento, alem da prevencao de novas demandas trabalhistas.</p>
      </div>
      <div class="col-lg-4">
        <div class="card glass border-0 shadow-soft text-dark">
          <div class="card-body">
            <h6 class="text-cnjp mb-3"><i class="bi bi-card-checklist me-2"></i>Escopo da proposta</h6>
            <ul class="list-unstyled small mb-0">
              <li class="mb-2"><i class="bi bi-check2-circle me-2 text-cnjp"></i>Ate 160 contratos ativos</li>
              <li class="mb-2"><i class="bi bi-check2-circle me-2 text-cnjp"></i>Contratos ja judicializados</li>
              <li class="mb-2"><i class="bi bi-check2-circle me-2 text-cnjp"></i>20 conciliacoes trabalhistas preventivas</li>
              <li><i class="bi bi-check2-circle me-2 text-cnjp"></i>Parceria com Dr. Eric e escritorio</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Apresentacao -->
<section id="apresentacao" class="py-5">
  <div class="container">
    <div class="row g-4 align-items-start">
      <div class="col-lg-7">
        <h2 class="h3 text-cnjp"><i class="bi bi-building-check me-2"></i>Apresentacao</h2>
        <p class="text-muted">Esta proposta tem por objetivo conduzir, de forma organizada e consensual, as demandas envolvendo os adquirentes do empreendimento Maria Vitoria, bem como prevenir novas demandas trabalhistas.</p>
        <p class="text-muted mb-0">A atuacao sera realizada em <strong>parceria com o Dr. Eric e seu escritorio</strong>, com divisao das atribuicoes conforme a natureza de cada procedimento, preservando a seguranca juridica e o relacionamento com os clientes.</p>
      </div>
      <div class="col-lg-5">
        <div class="row g-3">
          <div class="col-6">
            <div class="card h-100 text-center shadow-soft border-0">
              <div class="card-body">
                <div class="feature-icon text-cnjp"><i class="bi bi-people-fill"></i></div>
                <h6 class="mt-3 mb-1">Consensual</h6>
                <p class="small text-muted mb-0">Acordos com concordancia expressa do consumidor.</p>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 text-center shadow-soft border-0">
              <div class="card-body">
                <div class="feature-icon text-cnjp"><i class="bi bi-shield-check"></i></div>
                <h6 class="mt-3 mb-1">Seguranca</h6>
                <p class="small text-muted mb-0">Formalizacao, aditivos e Conciarb quando cabivel.</p>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 text-center shadow-soft border-0">
              <div class="card-body">
                <div class="feature-icon text-cnjp"><i class="bi bi-graph-up-arrow"></i></div>
                <h6 class="mt-3 mb-1">Escala</h6>
                <p class="small text-muted mb-0">Condicoes especiais por volume de procedimentos.</p>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 text-center shadow-soft border-0">
              <div class="card-body">
                <div class="feature-icon text-cnjp"><i class="bi bi-eye-slash"></i></div>
                <h6 class="mt-3 mb-1">Prevencao</h6>
                <p class="small text-muted mb-0">Composicao antes da instauracao de novos litigios.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Contratos ativos -->
<section id="ativos" class="py-5 bg-light">
  <div class="container">
    <div class="row mb-4">
      <div class="col-lg-8">
        <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">1 &middot; Contratos ativos</span>
        <h2 class="h3 text-cnjp mb-2"><i class="bi bi-file-earmark-text me-2"></i>Ate 160 adquirentes</h2>
        <p class="text-muted mb-0">Para os contratos ativos, sera realizado um procedimento individual de conciliacao.</p>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-list-check me-2"></i>O procedimento contempla</h6>
            <div class="flow d-grid gap-2">
              <div class="step"><b>1</b><span>Notificacao / convocacao do adquirente</span></div>
              <div class="step"><b>2</b><span>Abertura do procedimento</span></div>
              <div class="step"><b>3</b><span>Audiencia de conciliacao</span></div>
              <div class="step"><b>4</b><span>Negociacao entre as partes</span></div>
              <div class="step"><b>5</b><span>Concordancia expressa e especifica do consumidor</span></div>
              <div class="step"><b>6</b><span>Formalizacao do acordo</span></div>
              <div class="step"><b>7</b><span>Elaboracao do aditivo contratual</span></div>
              <div class="step"><b>8</b><span>Conciarb / compromisso arbitral, quando cabivel e expressamente aceito</span></div>
              <div class="step"><b>9</b><span>Encerramento do procedimento</span></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4 d-flex flex-column">
            <h6 class="text-cnjp mb-3"><i class="bi bi-cash-coin me-2"></i>Investimento</h6>
            <p class="small text-muted mb-3">Valor de referencia de <strong>01 salario por procedimento</strong>.</p>
            <div class="price-tag p-3 mb-2 text-center">
              <div class="small text-uppercase" style="letter-spacing:.06em">Condicao especial em escala</div>
              <div class="fs-3">R$ 900,00</div>
              <div class="small">por procedimento</div>
            </div>
            <p class="small text-muted mb-0">Condicao aplicavel a contratacao em escala para o volume de ate 160 adquirentes.</p>
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
        <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">2 &middot; Contratos ja judicializados</span>
        <h2 class="h3 text-cnjp mb-2"><i class="bi bi-bank me-2"></i>Composicao consensual</h2>
        <p class="text-muted mb-0">Nos casos em que o adquirente ja possui processo judicial contra a construtora, sera realizada uma tentativa estruturada de composicao consensual.</p>
      </div>
    </div>
    <div class="card shadow-soft border-0 mb-4">
      <div class="card-body p-4">
        <h6 class="text-cnjp mb-3"><i class="bi bi-diagram-3 me-2"></i>Fluxo</h6>
        <div class="flow d-flex flex-wrap gap-2">
          <div class="step"><b>1</b><span>Analise do caso</span></div>
          <div class="step"><b>2</b><span>Convocacao</span></div>
          <div class="step"><b>3</b><span>Audiencia de conciliacao</span></div>
          <div class="step"><b>4</b><span>Negociacao</span></div>
          <div class="step"><b>5</b><span>Acordo</span></div>
          <div class="step"><b>6</b><span>Formalizacao</span></div>
          <div class="step"><b>7</b><span>Providencias judiciais cabiveis</span></div>
        </div>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-percent me-2"></i>Tabela padrao da Camara</h6>
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
            <h6 class="text-cnjp mb-3"><i class="bi bi-tags me-2"></i>Condicao especial em escala</h6>
            <div class="price-tag p-3 mb-3 text-center">
              <div class="fs-3">3%</div>
              <div class="small">sobre o valor discutido</div>
            </div>
            <p class="small text-muted mb-0">Considerando o volume de demandas e a contratacao em escala, o percentual de <strong>3% permanece aberto a negociacao</strong>, conforme volume, complexidade e quantidade de processos.</p>
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
        <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">3 &middot; Conciliacao trabalhista preventiva</span>
        <h2 class="h3 text-cnjp mb-2"><i class="bi bi-person-workspace me-2"></i>20 funcionarios</h2>
        <p class="text-muted mb-0">Procedimentos destinados aos funcionarios que foram ou estao sendo dispensados pela construtora, buscando composicao consensual e preventiva antes da eventual instauracao de uma reclamacao trabalhista.</p>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-diagram-3 me-2"></i>Fluxo</h6>
            <div class="flow d-grid gap-2">
              <div class="step"><b>1</b><span>Notificacao</span></div>
              <div class="step"><b>2</b><span>Abertura do procedimento</span></div>
              <div class="step"><b>3</b><span>Audiencia de conciliacao</span></div>
              <div class="step"><b>4</b><span>Negociacao</span></div>
              <div class="step"><b>5</b><span>Acordo</span></div>
              <div class="step"><b>6</b><span>Formalizacao</span></div>
              <div class="step"><b>7</b><span>Encerramento</span></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <h6 class="text-cnjp mb-3"><i class="bi bi-cash-coin me-2"></i>Investimento</h6>
            <p class="small text-muted mb-3">O valor padrao da Camara corresponde a <strong>01 salario por procedimento</strong>. Para contratacao em escala, acima de 5 procedimentos, aplica-se condicao especial de <strong>meio salario por procedimento</strong>.</p>
            <div class="price-tag p-3 text-center">
              <div class="small text-uppercase" style="letter-spacing:.06em">Condicao especial</div>
              <div class="fs-3">R$ 800,00</div>
              <div class="small">por conciliacao</div>
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
        <h2 class="h3 text-cnjp"><i class="bi bi-clipboard-data me-2"></i>Resumo da proposta</h2>
        <p class="text-muted mb-0">Condicoes comerciais consolidadas.</p>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4">
            <ul class="resumo-list">
              <li><i class="bi bi-check-circle-fill"></i><span><strong>Contratos ativos:</strong> R$ 900,00 por procedimento</span></li>
              <li><i class="bi bi-check-circle-fill"></i><span><strong>Contratos judicializados:</strong> 3% sobre o valor discutido &mdash; condicao especial aberta a negociacao</span></li>
              <li><i class="bi bi-check-circle-fill"></i><span><strong>20 conciliacoes trabalhistas preventivas:</strong> R$ 800,00 por procedimento</span></li>
              <li><i class="bi bi-check-circle-fill"></i><span><strong>Total das conciliacoes trabalhistas:</strong> R$ 16.000,00</span></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-soft border-0 h-100">
          <div class="card-body p-4 d-flex flex-column">
            <h6 class="text-cnjp mb-3"><i class="bi bi-people me-2"></i>Parceria</h6>
            <p class="small text-muted mb-3">A total das conciliacoes trabalhistas considera 20 procedimentos &times; R$ 800,00.</p>
            <div class="alert alert-primary border-0 shadow-soft d-flex align-items-start mb-0" role="alert">
              <i class="bi bi-info-circle-fill me-3 fs-4"></i>
              <div class="small">A atuacao sera realizada em parceria com o <strong>Dr. Eric</strong> e seu escritorio, com divisao das atribuicoes conforme a natureza de cada procedimento.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Rodape -->
<footer class="bg-cnjp text-white py-4">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small">Camara de Arbitragem &mdash; Conciliacao &middot; Prevencao &middot; Arbitragem &middot; Solucao de Conflitos</div>
    <div class="small no-print"><a href="#top" class="text-white text-decoration-none">Voltar ao topo</a></div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){var y=document.getElementById('year');if(y)y.textContent=String(new Date().getFullYear());})();
</script>
</body>
</html>
