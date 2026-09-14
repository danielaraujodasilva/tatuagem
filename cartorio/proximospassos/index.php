<?php
declare(strict_types=1);
/* Roadmap interno do projeto CNJP Cartorio Digital.
   Pagina noindex: nao deve entrar em buscadores nem ser linkada em anuncios. */

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$phases = [
    [
        'id' => 'f0',
        'tag' => 'Fase 0',
        'title' => 'Fundacao (concluido)',
        'goal' => 'Base tecnica limpa, publicando sozinha e sem dependencia deste computador.',
        'state' => 'done',
        'steps' => [
            [
                'id' => 'f0-triagem', 'title' => 'Remover a secao "Triagem guiada"', 'owner' => 'Assistente',
                'prio' => 'feito', 'effort' => 'concluido', 'done' => true,
                'why' => 'A secao saiu da home, junto com o script que a montava (triage-feedback.js).',
                'how' => ['Commit e374801 publicado na branch main.'],
            ],
            [
                'id' => 'f0-cta', 'title' => 'Remover CTA orfao e o endpoint de IA local', 'owner' => 'Assistente',
                'prio' => 'feito', 'effort' => 'concluido', 'done' => true,
                'why' => 'O botao "Resolver online" apontava para a secao removida. A action=triage era o unico uso do Ollama.',
                'how' => ['Commit a867633.', 'Com isso o projeto nao depende mais do PC local (nem da extensao curl).'],
            ],
            [
                'id' => 'f0-deploy', 'title' => 'Deploy automatico GitHub -> HostGator', 'owner' => 'Daniel + Assistente',
                'prio' => 'feito', 'effort' => 'concluido', 'done' => true,
                'why' => 'Todo push na main dispara o workflow "Deploy Production Site", que chama o webhook no servidor e roda git pull.',
                'how' => ['Os dois commits de hoje subiram sozinhos em ~7s cada.', 'Nao existe mais upload manual de arquivos.'],
            ],
            [
                'id' => 'f0-infra', 'title' => 'Servidor validado (PHP + SQLite + acesso)', 'owner' => 'Assistente',
                'prio' => 'feito', 'effort' => 'concluido', 'done' => true,
                'why' => 'O banco SQLite funciona na HostGator: a API responde no ar, o catalogo devolve 25 servicos e o usuario admin ja existe.',
                'how' => ['Rotas de painel respondem 401 sem login (protecao ativa).'],
            ],
        ],
    ],
    [
        'id' => 'f1',
        'tag' => 'Fase 1',
        'title' => 'Bloqueadores: resolver ANTES de ligar anuncio',
        'goal' => 'Sem estes itens voce paga por cliques que nao convertem, e as contas de anuncio podem ser reprovadas.',
        'state' => 'active',
        'steps' => [
            [
                'id' => 'contato', 'title' => 'Dados de contato reais visiveis no site', 'owner' => 'Daniel',
                'prio' => 'P0', 'effort' => '30 min', 'done' => false,
                'why' => 'Hoje nao existe telefone, WhatsApp, e-mail nem horario em lugar nenhum. O proprio modal promete "a equipe entrara em contato por WhatsApp" sem informar numero. Para servico local, o botao de WhatsApp e o principal canal de conversao.',
                'how' => [
                    'Definir o numero comercial (com DDD) usado no WhatsApp Business.',
                    'Adicionar botao flutuante de WhatsApp + bloco de contato no rodape.',
                    'Publicar horario de atendimento e e-mail.',
                ],
            ],
            [
                'id' => 'identidade', 'title' => 'Identidade do anunciante (CNPJ, endereco, responsavel)', 'owner' => 'Daniel',
                'prio' => 'P0', 'effort' => '1 h', 'done' => false,
                'why' => 'Google Ads exige identidade verificavel do anunciante e o Meta analisa confianca/compliance. Site que capta dados sem identificar quem os trata e reprovado ou limitado.',
                'how' => [
                    'Informar CNPJ (ou CPF, se ainda MEI/pessoa fisica) e razao social.',
                    'Endereco comercial e telefone fixo/celular.',
                    'Enviar os dados para eu montar o rodape legal do site.',
                ],
            ],
            [
                'id' => 'lgpd', 'title' => 'Politica de Privacidade e Termos de Uso', 'owner' => 'Assistente redige, Daniel revisa',
                'prio' => 'P0', 'effort' => '2 h', 'done' => false,
                'why' => 'O site coleta nome, telefone e relato do caso, e guarda documentos (PDF/JPG) com retencao de 365 dias. Isso e dado pessoal sob a LGPD. Formulario de lead no Meta exige link de politica de privacidade.',
                'how' => [
                    'Eu redijo os dois textos em linguagem simples e voce revisa.',
                    'Publicar em /cartorio/politica-de-privacidade e /cartorio/termos.',
                    'Linkar no rodape e no formulario de envio.',
                ],
            ],
            [
                'id' => 'simulado', 'title' => 'Tirar "simulado" e "beta" de todo o site', 'owner' => 'Assistente',
                'prio' => 'P0', 'effort' => '1 h', 'done' => false,
                'why' => 'A pagina diz hoje: "Criar pedido simulado", "Estimativa simulada - valores apenas demonstrativos" e "Ambiente beta". Anunciar uma pagina que avisa que e simulacao derruba conversao e a nota de experiencia da pagina de destino no Google.',
                'how' => [
                    'Trocar os textos por promessas reais ("Enviar pedido", "Pedir orcamento").',
                    'Manter o aviso honesto onde ele e juridicamente necessario, sem tom de maquete.',
                ],
            ],
            [
                'id' => 'aviso', 'title' => 'Aviso automatico quando entra um lead', 'owner' => 'Assistente',
                'prio' => 'P0', 'effort' => '2 h', 'done' => false,
                'why' => 'Hoje o pedido cai no banco e ninguem e avisado: e preciso abrir o painel manualmente. Em campanha paga, lead que demora 1 hora para ser visto esfria. E o vazamento mais caro do sistema.',
                'how' => [
                    'Enviar e-mail para cada novo pedido (remetente e destino definidos por voce).',
                    'Opcional: aviso tambem no WhatsApp da equipe.',
                    'Aviso curto, com codigo, servico, telefone e o relato do cliente.',
                ],
            ],
            [
                'id' => 'autoresposta', 'title' => 'Auto-resposta ao cliente com o codigo', 'owner' => 'Assistente',
                'prio' => 'P0', 'effort' => '1 h', 'done' => false,
                'why' => 'Quem vem de anuncio espera resposta imediata. A tela ja mostra o codigo; falta confirmar por e-mail/WhatsApp para o cliente nao achar que caiu no vazio.',
                'how' => ['Mensagem curta padrao com codigo do pedido, prazo de resposta e canais.'],
            ],
            [
                'id' => 'manual', 'title' => 'Corrigir o manual.html', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '20 min', 'done' => false,
                'why' => 'O manual (link no rodape) ainda tem um capitulo "Triagem" dizendo que "a IA local sugere ate tres servicos" - descreve a funcionalidade que foi removida.',
                'how' => ['Reescrever o capitulo para o fluxo real: cliente escolhe o servico, equipe confere.'],
            ],
        ],
    ],
    [
        'id' => 'f2',
        'tag' => 'Fase 2',
        'title' => 'Conversao: fazer o visitante virar pedido',
        'goal' => 'Ajustes que aumentam quantos visitantes pedem orcamento, sem mudar o produto.',
        'state' => 'todo',
        'steps' => [
            [
                'id' => 'portal', 'title' => 'Tirar o portal do cliente de dentro do heroi', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '1 h', 'done' => false,
                'why' => 'A caixa "Acompanhe seu pedido" serve para quem ja e cliente e nao tem nada a fazer ali. Ela ocupa o espaco mais nobre da pagina e, no celular, aparece antes dos servicos.',
                'how' => ['Mover para o rodape ou para um link discreto no topo ("Ja sou cliente").', 'Usar o espaco para a oferta e o botao principal.', 'No celular, isso libera a metade inferior da primeira dobra, hoje ocupada pelo formulario.'],
            ],
            [
                'id' => 'cta-mobile', 'title' => 'Encurtar a primeira dobra no celular', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '1 h', 'done' => false,
                'why' => 'Medicao em 390x844: o botao principal ja aparece inteiro (entre 445px e 491px) e nao ha estouro de largura. O que sobra e a metade de baixo da dobra: ela termina no portal do cliente, entao a oferta de servicos so aparece depois de rolar.',
                'how' => ['Reduzir a altura da headline no celular.', 'Deixar os selos de confianca mais compactos.', 'Resultado: o catalogo aparece mais cedo na rolagem.'],
            ],
            [
                'id' => 'prova', 'title' => 'Prova social e "quem somos"', 'owner' => 'Daniel',
                'prio' => 'P1', 'effort' => '2 h (depende de conteudo)', 'done' => false,
                'why' => 'Servico que lida com documentos e dinheiro precisa de confianca. Nao ha um depoimento, um tempo medio de resposta, um numero de casos atendidos.',
                'how' => [
                    'Me enviar depoimentos reais, tempo medio de resposta e formas de contato.',
                    'Eu monto a secao com o material.',
                ],
            ],
            [
                'id' => 'faq', 'title' => 'Bloco de perguntas frequentes', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '2 h', 'done' => false,
                'why' => 'As duvidas que travam a conversao sao sempre as mesmas e hoje nao estao respondidas: quanto custa, qual o prazo, quais documentos, "voces sao um cartorio?"',
                'how' => ['Montar FAQ com as 8 perguntas mais provaveis.', 'Cada resposta curta e direta, em linguagem de cliente.'],
            ],
            [
                'id' => 'contagem', 'title' => 'Unificar a contagem de servicos', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '30 min', 'done' => false,
                'why' => 'O selo da pagina diz "60+ servicos", o catalogo carrega 25 do banco e o arquivo estatico tem 78. Numeros diferentes minam a credibilidade.',
                'how' => ['Escolher uma fonte unica (o banco) e exibir a contagem real.'],
            ],
            [
                'id' => 'landing', 'title' => 'Landing page por servico', 'owner' => 'Assistente',
                'prio' => 'P2', 'effort' => '1 dia', 'done' => false,
                'why' => 'Anuncio de "certidao de nascimento" caindo na home gera cliques caros e pouco foco. Pagina dedicada por servico (certidoes, firma, matricula, mediacao) converte muito mais.',
                'how' => ['Template unico reaproveitando o banco de servicos.', 'Texto, documentos, prazo, preco e formulario direto.'],
            ],
            [
                'id' => 'obrigado', 'title' => 'Pagina de obrigado (thank-you)', 'owner' => 'Assistente',
                'prio' => 'P2', 'effort' => '1 h', 'done' => false,
                'why' => 'E onde se mede conversao com precisao e onde o cliente confirma que o pedido chegou.',
                'how' => ['Pagina propria apos envio, com codigo do pedido e proximos passos.'],
            ],
        ],
    ],
    [
        'id' => 'f3',
        'tag' => 'Fase 3',
        'title' => 'Medicao: saber de onde vem o cliente',
        'goal' => 'Sem isso a campanha roda no escuro: voce paga e nao sabe qual anuncio traz cliente.',
        'state' => 'todo',
        'steps' => [
            [
                'id' => 'utm', 'title' => 'Gravar a origem (UTM) em cada pedido', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '2 h', 'done' => false,
                'why' => 'O ticket hoje guarda canal, mas nao guarda utm_source/medium/campaign. Sem isso nao ha como saber qual campanha gerou qual pedido.',
                'how' => ['Capturar os parametros da URL no formulario.', 'Salvar no banco e exibir no painel.'],
            ],
            [
                'id' => 'pixel', 'title' => 'Pixel do Meta + GA4 + tag do Google Ads', 'owner' => 'Daniel + Assistente',
                'prio' => 'P1', 'effort' => '2 h', 'done' => false,
                'why' => 'Hoje nao existe nenhum rastreamento instalado. Sem pixel nao ha remarketing, nem otimizacao por conversao, nem medicao de custo por lead.',
                'how' => [
                    'Voce cria (ou me passa) os IDs de Pixel, GA4 e Google Ads.',
                    'Eu instalo as tags no template e valido com o Tag Assistant.',
                ],
            ],
            [
                'id' => 'eventos', 'title' => 'Marcar os eventos de conversao', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '2 h', 'done' => false,
                'why' => 'As plataformas precisam aprender o que e sucesso. Sem evento de conversao, a campanha otimiza por clique - que e justamente o que voce paga sem retorno.',
                'how' => ['Disparar evento no envio do pedido e no login do portal.', 'Enviar o codigo do pedido como valor da conversao.'],
            ],
            [
                'id' => 'consent', 'title' => 'Aviso de cookies / consentimento (LGPD)', 'owner' => 'Assistente',
                'prio' => 'P2', 'effort' => '2 h', 'done' => false,
                'why' => 'Se instalar pixel e GA4, o site passa a rastrear navegacao. Um aviso simples de cookies mantem a operacao alinhada a LGPD.',
                'how' => ['Banner discreto com aceite e link para a politica de privacidade.'],
            ],
        ],
    ],
    [
        'id' => 'f4',
        'tag' => 'Fase 4',
        'title' => 'Campanha no ar',
        'goal' => 'Ligar o trafego depois que as fases acima estiverem fechadas.',
        'state' => 'todo',
        'steps' => [
            [
                'id' => 'contas', 'title' => 'Contas de anuncio e verificacao', 'owner' => 'Daniel',
                'prio' => 'P1', 'effort' => '1-3 dias (analise das plataformas)', 'done' => false,
                'why' => 'Contas novas passam por verificacao. Melhor criar antes e deixar aprovada do que descobrir na hora de subir a campanha.',
                'how' => ['Criar/verificar conta no Meta Ads e no Google Ads.', 'Configurar forma de pagamento e limites.'],
            ],
            [
                'id' => 'posicionamento', 'title' => 'Decidir o posicionamento do nome', 'owner' => 'Daniel',
                'prio' => 'P1', 'effort' => 'decisao', 'done' => false,
                'why' => 'O site se chama "Cartorio Digital", mas o proprio texto afirma que a CNJP nao e cartorio e nao pratica ato de fe publica. Quem clica num anuncio de "cartorio" espera o cartorio: gera lead ruim, reclamacao e risco de reprovacao do anuncio.',
                'how' => [
                    'Escolher o rotulo: "central de servicos e documentos" em vez de "cartorio".',
                    'Decidir se a pagina fica em /cartorio/ ou ganha caminho/subdominio proprio (hoje ela vive dentro de danieltatuador.com, um dominio de estudio de tatuagem).',
                ],
            ],
            [
                'id' => 'estrutura', 'title' => 'Estrutura de campanha por servico', 'owner' => 'Assistente',
                'prio' => 'P2', 'effort' => '2 h', 'done' => false,
                'why' => 'Campanha separada por servico permite cortar o que nao vende e escalar o que vende.',
                'how' => ['Definir palavras-chave negativas.', 'Um conjunto de anuncios por servico, cada um para sua landing page.'],
            ],
        ],
    ],
];

$totalSteps = 0;
$doneSteps = 0;
foreach ($phases as $phase) {
    foreach ($phase['steps'] as $step) {
        $totalSteps++;
        if (!empty($step['done'])) {
            $doneSteps++;
        }
    }
}
$pct = $totalSteps > 0 ? (int)round(($doneSteps / $totalSteps) * 100) : 0;
$prioClass = ['P0' => 'p0', 'P1' => 'p1', 'P2' => 'p2', 'feito' => 'ok'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0f766e">
<title>Proximos passos - CNJP Cartorio Digital</title>
<link rel="stylesheet" href="../assets/local-fonts.css">
<style>
:root{--ink:#162235;--muted:#667085;--line:#e4e7ec;--teal:#0f766e;--teal2:#115e59;--tealSoft:#ecfdf5;--white:#fff;--amber:#b45309;--red:#b42318;--slate:#0f172a;--radius:18px}
*{box-sizing:border-box}
body{margin:0;background:#f5f7f8;color:var(--ink);font-family:Inter,system-ui,-apple-system,sans-serif;line-height:1.5;-webkit-text-size-adjust:100%}
.wrap{width:min(1080px,calc(100% - 24px));margin-inline:auto}
.topbar{background:#101828;color:#98a2b3;font-size:.72rem;padding:9px 0}
.topbar .wrap{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:space-between}
.topbar b{color:#5eead4}
header.hero{background:linear-gradient(160deg,#0f2928,#123f3c 60%,#0f766e);color:#fff;padding:44px 0 34px}
.brand{display:inline-flex;align-items:center;gap:9px;text-decoration:none;color:#fff;margin-bottom:22px}
.brand-mark{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.14);font-weight:800}
.brand strong,.brand small{display:block}
.brand strong{font-size:.9rem;line-height:1}
.brand small{font-size:.63rem;color:#9fd8d2;margin-top:3px}
.kicker{font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#6ee7d6}
h1{font-size:clamp(1.7rem,5vw,2.9rem);line-height:1.05;letter-spacing:-.04em;margin:12px 0 12px;max-width:760px}
h1 em{font-style:normal;color:#6ee7d6}
.lead{color:#c3dedb;font-size:.95rem;max-width:680px;margin:0}
.meter{margin-top:26px;display:grid;gap:8px;max-width:560px}
.meter .bar{height:12px;border-radius:999px;background:rgba(255,255,255,.16);overflow:hidden}
.meter .bar i{display:block;height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#5eead4,#14b8a6);border-radius:999px;transition:width .3s}
.meter span{font-size:.7rem;color:#9fd8d2}
.meter b{color:#fff}
main{padding:28px 0 10px}
.card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:18px;margin-bottom:14px}
.need{border:1px solid #fed7aa;background:linear-gradient(135deg,#fff7ed,#fff)}
.need h2{margin:0 0 6px;font-size:1.05rem}
.need>p{margin:0 0 14px;color:var(--muted);font-size:.79rem}
.need ul{margin:0;padding-left:0;list-style:none;display:grid;gap:7px}
.need li{display:flex;gap:9px;align-items:flex-start;font-size:.79rem}
.need li:before{content:'→';color:#c2410c;font-weight:800;flex:0 0 auto}
.deploy{border:1px solid #bfdbfe;background:linear-gradient(135deg,#eff6ff,#fff)}
.deploy h2{margin:0 0 6px;font-size:1.05rem}
.deploy p{margin:0 0 10px;color:var(--muted);font-size:.79rem}
.flow{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:.72rem;margin:0 0 12px}
.flow span{background:#fff;border:1px solid var(--line);border-radius:999px;padding:6px 10px;font-weight:650}
.flow i{color:#94a3b8;font-style:normal}
.phase{border-top:3px solid var(--line)}
.phase.done{border-top-color:#10b981}
.phase.active{border-top-color:var(--teal)}
.phase-head{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:4px}
.phase-tag{background:#f2f4f7;color:#475467;border-radius:999px;padding:4px 10px;font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
.phase.active .phase-tag{background:var(--tealSoft);color:var(--teal)}
.phase.done .phase-tag{background:#ecfdf3;color:#067647}
.phase h2{margin:0;font-size:1.12rem;letter-spacing:-.02em}
.phase-goal{margin:0 0 16px;color:var(--muted);font-size:.79rem}
.step{border:1px solid var(--line);border-radius:14px;padding:13px;margin-bottom:9px;background:#fff;transition:.15s}
.step:hover{border-color:#99d5ce}
.step.is-done{background:#fbfdfc;border-color:#cdece6}
.step-top{display:flex;gap:10px;align-items:flex-start}
.step-top input[type=checkbox]{width:19px;height:19px;margin:2px 0 0;accent-color:var(--teal);flex:0 0 auto;cursor:pointer}
.step-body{min-width:0;flex:1}
.step-title{font-weight:750;font-size:.86rem;display:block;margin-bottom:6px}
.step.is-done .step-title{color:#475467;text-decoration:line-through;text-decoration-color:#a7d8d2}
.chips{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:9px}
.chip{border-radius:999px;padding:3px 8px;font-size:.57rem;font-weight:750;background:#f2f4f7;color:#475467;white-space:nowrap}
.chip.p0{background:#fff1f0;color:var(--red)}
.chip.p1{background:#fff7ed;color:var(--amber)}
.chip.p2{background:#eff6ff;color:#2563eb}
.chip.ok{background:#ecfdf3;color:#067647}
.chip.owner{background:#f5f3ff;color:#6d28d9}
.why{margin:0 0 8px;font-size:.77rem;color:#475467}
.why strong{color:var(--ink)}
.how{margin:0;padding-left:18px;display:grid;gap:4px}
.how li{font-size:.75rem;color:var(--muted)}
footer{border-top:1px solid var(--line);margin-top:8px;padding:22px 0 40px;color:#98a2b3;font-size:.72rem;background:#101828}
footer a{color:#5eead4}
footer .wrap{display:grid;gap:8px}
.reset{border:1px solid #cfd5dc;background:#fff;color:#344054;border-radius:10px;padding:8px 12px;font-size:.7rem;font-weight:700;cursor:pointer}
@media(min-width:700px){
  .step-top{gap:12px}
  .card{padding:22px}
  .steps-grid{display:grid;gap:9px}
}
</style>
</head>
<body>
<div class="topbar">
  <div class="wrap">
    <span>Pagina interna do projeto - <b>nao indexar, nao usar como destino de anuncio</b></span>
    <span>Atualizada em <?= e(date('d/m/Y')) ?></span>
  </div>
</div>

<header class="hero">
  <div class="wrap">
    <a class="brand" href="../"><span class="brand-mark">CN</span><span><strong>CNJP</strong><small>Cartorio Digital</small></span></a>
    <span class="kicker">Roadmap do projeto</span>
    <h1>O que falta para essa pagina <em>vender de verdade.</em></h1>
    <p class="lead">Auditoria honesta da pagina atual, com o caminho em ordem de prioridade. O que esta em vermelho (P0) trava campanha: sem isso, o dinheiro de anuncio vaza e as plataformas podem reprovar a conta.</p>
    <div class="meter">
      <div class="bar"><i></i></div>
      <span><b><?= $doneSteps ?> de <?= $totalSteps ?></b> itens concluidos - <?= $pct ?>% do caminho</span>
    </div>
  </div>
</header>

<main class="wrap">

  <section class="card need">
    <h2>Preciso de voce para destravar a Fase 1</h2>
    <p>Sao informacoes que so voce tem. Com elas eu executo o resto.</p>
    <ul>
      <li>Numero de WhatsApp comercial (com DDD) que vai atender os leads.</li>
      <li>Telefone, e-mail e horario de atendimento que devem aparecer no site.</li>
      <li>CNPJ (ou CPF, se ainda for MEI/pessoa fisica), razao social e endereco comercial.</li>
      <li>E-mail que deve receber o aviso de cada novo pedido.</li>
      <li>IDs de Pixel do Meta, GA4 e Google Ads (ou aviso para eu criar a estrutura e voce so colar os IDs).</li>
      <li>Depoimentos/clientes atendidos e tempo medio de resposta, se existirem.</li>
    </ul>
  </section>

  <section class="card deploy">
    <h2>Como as alteracoes chegam ao site (ja e automatico)</h2>
    <p>Nao existe mais upload manual de arquivos: a publicacao e feita pelo git.</p>
    <div class="flow">
      <span>Edito e testo aqui</span><i>→</i>
      <span>git commit</span><i>→</i>
      <span>git push na main</span><i>→</i>
      <span>GitHub Action</span><i>→</i>
      <span>webhook no servidor</span><i>→</i>
      <span>git pull na HostGator</span>
    </div>
    <p style="margin-bottom:0">Prova: os commits de 14/09/2026 (e374801 e a867633) subiram sozinhos, com sucesso, em cerca de 7 segundos cada - execucoes do workflow <b>Deploy Production Site</b>. O mesmo servidor ja roda o banco SQLite com o catalogo publicado e o acesso administrativo criado.</p>
  </section>

  <?php foreach ($phases as $phase): ?>
  <section class="card phase <?= e($phase['state']) ?>">
    <div class="phase-head">
      <span class="phase-tag"><?= e($phase['tag']) ?></span>
      <h2><?= e($phase['title']) ?></h2>
    </div>
    <p class="phase-goal"><?= e($phase['goal']) ?></p>
    <?php foreach ($phase['steps'] as $step): ?>
      <article class="step <?= !empty($step['done']) ? 'is-done' : '' ?>" data-step="<?= e($step['id']) ?>">
        <div class="step-top">
          <input type="checkbox" <?= !empty($step['done']) ? 'checked' : '' ?> aria-label="<?= e($step['title']) ?>">
          <div class="step-body">
            <label class="step-title"><?= e($step['title']) ?></label>
            <div class="chips">
              <span class="chip <?= e($prioClass[$step['prio']] ?? '') ?>"><?= e($step['prio']) ?></span>
              <span class="chip owner"><?= e($step['owner']) ?></span>
              <span class="chip"><?= e($step['effort']) ?></span>
            </div>
            <p class="why"><strong>Por que importa:</strong> <?= e($step['why']) ?></p>
            <ul class="how">
              <?php foreach ($step['how'] as $line): ?>
                <li><?= e($line) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>

  <section class="card">
    <div class="phase-head" style="margin-bottom:12px">
      <h2 style="font-size:1rem">Sequencia recomendada</h2>
    </div>
    <ol class="how" style="padding-left:20px">
      <li>Voce me envia os dados do bloco laranja (contato, CNPJ, e-mail de aviso).</li>
      <li>Eu publico contato + politica de privacidade + termos e limpo o "simulado/beta".</li>
      <li>Eu ligo o aviso automatico de lead novo e a auto-resposta ao cliente.</li>
      <li>Eu instalo Pixel, GA4 e Google Ads e marco os eventos de conversao.</li>
      <li>Eu faco os ajustes de conversao (heroi, mobile, prova social, FAQ).</li>
      <li>So entao subimos a campanha, com uma landing page por servico.</li>
    </ol>
    <div style="margin-top:14px"><button class="reset" id="reset">Limpar marcacoes desta pagina</button></div>
  </section>

</main>

<footer>
  <div class="wrap">
    <span>CNJP Cartorio Digital - pagina interna de planejamento.</span>
    <span>Esta pagina nao aparece em buscadores e nao deve ser divulgada. <a href="../">Voltar ao site</a></span>
  </div>
</footer>

<script>
(() => {
  const key = 'cnjp-proximospassos';
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(key) || '{}'); } catch (err) { saved = {}; }
  document.querySelectorAll('.step').forEach(step => {
    const box = step.querySelector('input[type=checkbox]');
    const id = step.dataset.step;
    if (box && saved[id]) {
      box.checked = true;
      step.classList.add('is-done');
    }
    box?.addEventListener('change', () => {
      step.classList.toggle('is-done', box.checked);
      saved[id] = box.checked;
      localStorage.setItem(key, JSON.stringify(saved));
    });
  });
  document.querySelector('#reset')?.addEventListener('click', () => {
    localStorage.removeItem(key);
    document.querySelectorAll('.step').forEach(step => {
      step.querySelector('input[type=checkbox]').checked = false;
      step.classList.remove('is-done');
    });
  });
})();
</script>
</body>
</html>
