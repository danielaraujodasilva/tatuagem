<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0a0b0d">
<title>CNJP | Roadmap de Reestruturação</title>
<style>
:root{--bg:#0a0b0d;--panel:#121419;--panel2:#171a20;--line:#292e38;--txt:#f4f6f8;--muted:#9aa3af;--accent:#d8b56b;--good:#76c893;--warn:#e9b949;--bad:#e07a7a;--max:1080px}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:linear-gradient(180deg,#08090b,#0d0f13 45%,#090a0c);color:var(--txt);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;line-height:1.6}a{color:inherit}.wrap{width:min(var(--max),calc(100% - 28px));margin:auto}.top{position:sticky;top:0;z-index:10;background:rgba(10,11,13,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}.topin{min-height:64px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{font-weight:900;letter-spacing:.12em}.brand span{color:var(--accent)}.mini{font-size:12px;color:var(--muted)}.hero{padding:64px 0 34px}.eyebrow{font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--accent)}h1{font-size:clamp(38px,7vw,72px);line-height:.95;margin:10px 0 18px;letter-spacing:-.04em}.lead{font-size:clamp(17px,2.2vw,22px);max-width:800px;color:#c6ccd5}.rule{margin-top:24px;padding:16px 18px;border-left:3px solid var(--accent);background:#111318;color:#d9dee5}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:28px 0}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px}.card b{display:block;font-size:14px;color:var(--accent);margin-bottom:5px}.card strong{font-size:28px}.card p{margin:6px 0 0;color:var(--muted);font-size:13px}.section{padding:22px 0}.section h2{font-size:27px;margin:0 0 8px}.section>p{color:var(--muted);margin-top:0}.now{border:1px solid rgba(216,181,107,.45);background:linear-gradient(135deg,rgba(216,181,107,.12),rgba(18,20,25,.6));border-radius:18px;padding:22px;margin:12px 0 28px}.now h2{margin:0 0 6px}.now p{margin:0;color:#ccd2da}.steps{display:grid;gap:10px}.step{display:flex;gap:12px;align-items:flex-start;background:var(--panel);border:1px solid var(--line);padding:14px 16px;border-radius:12px}.step input{margin-top:5px;accent-color:var(--accent);transform:scale(1.2)}.step label{cursor:pointer}.step small{display:block;color:var(--muted);margin-top:3px}.step.done{opacity:.55}.step.done label{text-decoration:line-through}.progress{height:10px;border-radius:999px;background:#191d23;overflow:hidden;border:1px solid var(--line);margin:15px 0 8px}.progress>div{height:100%;width:0;background:linear-gradient(90deg,#b88b3f,var(--accent));transition:.25s}.progressText{font-size:12px;color:var(--muted)}details{background:var(--panel);border:1px solid var(--line);border-radius:14px;margin:10px 0;overflow:hidden}summary{list-style:none;cursor:pointer;padding:17px 52px 17px 18px;font-weight:850;position:relative}summary::-webkit-details-marker{display:none}summary:after{content:'+';position:absolute;right:18px;top:13px;font-size:26px;color:var(--accent)}details[open] summary:after{content:'–'}details[open] summary{border-bottom:1px solid var(--line);background:#15181e}.inside{padding:18px}.inside h3{margin:18px 0 6px;font-size:18px}.inside h3:first-child{margin-top:0}.inside p{color:#c2c8d0}.inside ul{margin:8px 0 14px;padding-left:22px}.inside li{margin:6px 0}.tag{display:inline-flex;border:1px solid var(--line);border-radius:999px;padding:3px 9px;font-size:11px;color:var(--muted);margin:2px}.tag.good{color:var(--good);border-color:rgba(118,200,147,.35)}.tag.warn{color:var(--warn);border-color:rgba(233,185,73,.35)}.tag.bad{color:var(--bad);border-color:rgba(224,122,122,.35)}.tree{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap;background:#090a0d;border:1px solid var(--line);padding:16px;border-radius:10px;color:#d9dee5;overflow:auto}.cols{display:grid;grid-template-columns:1fr 1fr;gap:12px}.box{background:var(--panel2);border:1px solid var(--line);border-radius:11px;padding:14px}.box h4{margin:0 0 6px;color:var(--accent)}.box p{margin:0;color:var(--muted);font-size:13px}.matrix{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.idea{background:#101216;border:1px solid var(--line);border-radius:10px;padding:12px}.idea b{display:block}.idea span{font-size:12px;color:var(--muted)}.callout{padding:14px;border-radius:10px;background:#19150d;border:1px solid rgba(216,181,107,.28);color:#e3dac8}.danger{background:#1b1012;border-color:rgba(224,122,122,.25);color:#e7c9c9}.notes textarea{width:100%;min-height:180px;resize:vertical;background:#0b0d10;color:var(--txt);border:1px solid var(--line);border-radius:12px;padding:14px;font:inherit}.notes small{color:var(--muted)}.footer{margin-top:38px;border-top:1px solid var(--line);padding:30px 0 50px;color:var(--muted);font-size:13px}.pill{display:inline-block;background:rgba(216,181,107,.12);color:var(--accent);padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800;margin-bottom:8px}.phaseTitle{display:flex;align-items:center;gap:10px}.n{width:30px;height:30px;display:grid;place-items:center;border-radius:50%;background:var(--accent);color:#111;font-weight:900;flex:0 0 auto}@media(max-width:760px){.grid,.cols,.matrix{grid-template-columns:1fr}.hero{padding-top:42px}.topin{min-height:56px}h1{font-size:44px}.card strong{font-size:23px}}
</style>
</head>
<body>
<header class="top"><div class="wrap topin"><div class="brand">CNJP <span>ROADMAP</span></div><div class="mini">Documento vivo • versão inicial</div></div></header>
<main class="wrap">
<section class="hero">
<div class="eyebrow">Reestruturação da empresa</div>
<h1>Primeiro entender.<br>Depois organizar.<br>Só então vender.</h1>
<p class="lead">Esta página é o mapa da CNJP. A ideia é tirar tudo da cabeça, organizar por níveis e construir a empresa sem tentar resolver cinquenta coisas ao mesmo tempo.</p>
<div class="rule"><strong>Regra principal:</strong> não começar por anúncio, site novo ou campanha. Primeiro definimos exatamente o que a empresa é, o que pode vender, como entrega e quem faz cada parte.</div>
<div class="grid">
<div class="card"><b>OBJETIVO AGORA</b><strong>Mapear</strong><p>Descobrir capacidades, serviços, limites e oportunidades.</p></div>
<div class="card"><b>DEPOIS</b><strong>Empacotar</strong><p>Transformar dezenas de possibilidades em poucos produtos fáceis de entender.</p></div>
<div class="card"><b>SÓ ENTÃO</b><strong>Vender</strong><p>Site, campanhas, prospecção, parceiros e automações.</p></div>
</div>
</section>

<section class="now">
<h2>📍 Onde estamos agora</h2>
<p><strong>Fase 1: inventário da CNJP.</strong> Ainda não estamos decidindo nomes bonitos nem preços. Estamos descobrindo tudo que existe dentro dessa empresa e o que vale a pena transformar em negócio.</p>
<div class="progress"><div id="bar"></div></div><div class="progressText" id="progressText">0% concluído</div>
</section>

<section class="section">
<h2>Checklist imediato</h2><p>Faça isso primeiro. O resto pode esperar educadamente.</p>
<div class="steps" id="checklist">
<div class="step"><input type="checkbox" id="c1"><label for="c1"><strong>Levantar o cartão CNPJ e todos os CNAEs atuais</strong><small>Separar o que pertence à Fran, o que pertence à tecnologia e o que existe apenas por conveniência cadastral.</small></label></div>
<div class="step"><input type="checkbox" id="c2"><label for="c2"><strong>Listar formação, certificados e habilitações da Fran</strong><small>Mediação, conciliação, arbitragem, cursos, registros, experiência e qualquer habilitação formal.</small></label></div>
<div class="step"><input type="checkbox" id="c3"><label for="c3"><strong>Listar tudo que a CNJP já fez de verdade</strong><small>Casos atendidos, documentos produzidos, acordos, clientes, parceiros e serviços vendidos anteriormente.</small></label></div>
<div class="step"><input type="checkbox" id="c4"><label for="c4"><strong>Listar os serviços de tecnologia que Daniel consegue entregar hoje</strong><small>Automação, CRM, WhatsApp, IA, sites, sistemas, integrações, infraestrutura e suporte.</small></label></div>
<div class="step"><input type="checkbox" id="c5"><label for="c5"><strong>Mapear parceiros já disponíveis</strong><small>Advogados, contadores, corretores, engenheiros, peritos, despachantes e outros profissionais.</small></label></div>
<div class="step"><input type="checkbox" id="c6"><label for="c6"><strong>Revisar juridicamente o que pode e o que não pode ser ofertado</strong><small>Antes de publicar qualquer novo serviço regulado, validar enquadramento, publicidade, habilitações e necessidade de profissional parceiro.</small></label></div>
</div>
</section>

<section class="section">
<h2>Visão da empresa</h2><p>Abra só quando quiser enxergar o mapa maior.</p>
<details open><summary>Estrutura geral imaginada</summary><div class="inside"><div class="tree">CNJP
│
├── JUSTIÇA PRIVADA / SOLUÇÕES EXTRAJUDICIAIS
│   ├── CNJP Resolve .......... pessoa física
│   ├── CNJP Empresas ......... conflitos e soluções B2B
│   ├── CNJP Arbitragem ....... controvérsias patrimoniais/contratuais
│   └── CNJP Extrajudicial .... documentação, regularização e parceiros
│
└── CNJP TECH
    ├── Automação empresarial
    ├── Sistemas sob medida
    ├── CRM e atendimento
    ├── WhatsApp + IA
    ├── Sites e integrações
    └── Infraestrutura digital</div><p><strong>Tatuagem fica fora da marca comercial.</strong> O CNAE pode até existir no CNPJ, mas misturar estúdio, arbitragem e tatuagem na comunicação seria uma bela salada radioativa.</p></div></details>
<details><summary>Princípio de posicionamento</summary><div class="inside"><h3>CNJP</h3><p>Não deve parecer uma lista aleatória de serviços. Deve parecer uma empresa que <strong>resolve problemas e organiza processos</strong>.</p><h3>CNJP Tech</h3><p>Não vender “programação”. Vender resultado: reduzir trabalho manual, organizar atendimento, recuperar oportunidades, automatizar processos e criar sistemas úteis para empresas.</p></div></details>
</section>

<section class="section">
<h2>Roadmap completo</h2><p>Cada fase só precisa ser aprofundada quando chegar a hora.</p>

<details open><summary><span class="phaseTitle"><span class="n">1</span> Inventário: descobrir o que temos</span></summary><div class="inside">
<h3>Objetivo</h3><p>Construir uma fotografia real da CNJP hoje.</p>
<div class="cols"><div class="box"><h4>Fran</h4><p>Formação, habilitações, experiência, serviços já executados, rede profissional e atividades que deseja exercer.</p></div><div class="box"><h4>Daniel / Tech</h4><p>Competências técnicas, sistemas já criados, infraestrutura, automações, produtos reutilizáveis e capacidade mensal.</p></div></div>
<h3>Saída desta fase</h3><ul><li>Lista de CNAEs.</li><li>Lista de competências reais.</li><li>Lista de serviços já executados.</li><li>Lista de parceiros.</li><li>Lista de limitações legais e operacionais.</li></ul>
<span class="tag good">FAZER AGORA</span>
</div></details>

<details><summary><span class="phaseTitle"><span class="n">2</span> Catálogo bruto: listar todas as oportunidades</span></summary><div class="inside">
<p>Nesta fase não filtramos demais. Criamos um catálogo enorme e depois cortamos.</p>
<h3>Possíveis famílias de soluções</h3>
<div class="matrix">
<div class="idea"><b>Mediação e conciliação</b><span>Acordos, conflitos familiares, patrimoniais, empresariais e negociações.</span></div>
<div class="idea"><b>Arbitragem</b><span>Controvérsias enquadráveis, especialmente empresariais e patrimoniais.</span></div>
<div class="idea"><b>Empresas</b><span>Inadimplência, fornecedores, clientes, contratos e conflitos recorrentes.</span></div>
<div class="idea"><b>Imobiliário</b><span>Locação, compra e venda, documentação, conflitos e regularizações com parceiros.</span></div>
<div class="idea"><b>Família</b><span>Mediação familiar e preparação de consensos, respeitando os limites legais de cada caso.</span></div>
<div class="idea"><b>Extrajudicial</b><span>Notificações, organização documental, encaminhamentos e acompanhamento de procedimentos.</span></div>
<div class="idea"><b>Tech</b><span>Automação, CRM, WhatsApp, IA, sistemas, sites, infraestrutura e integrações.</span></div>
<div class="idea"><b>Parcerias B2B</b><span>Serviços para escritórios, clínicas, imobiliárias, escolas, condomínios e empresas.</span></div>
</div>
<h3>Saída desta fase</h3><p>Um inventário de talvez 30, 50 ou mais possibilidades. Ainda não é o menu do site.</p><span class="tag warn">DEPOIS DO INVENTÁRIO</span>
</div></details>

<details><summary><span class="phaseTitle"><span class="n">3</span> Produtos: transformar serviços em coisas fáceis de comprar</span></summary><div class="inside">
<p>O cliente não compra “procedimento autocompositivo”. Compra uma solução para um problema que está enchendo o saco dele.</p>
<h3>Formato de cada produto</h3><ul><li><strong>Problema:</strong> o que aconteceu com o cliente?</li><li><strong>Promessa:</strong> o que tentaremos resolver?</li><li><strong>Entrada:</strong> quais dados/documentos precisamos?</li><li><strong>Processo:</strong> quais etapas acontecem?</li><li><strong>Saída:</strong> o que o cliente recebe?</li><li><strong>Responsável:</strong> Fran, Daniel, parceiro ou equipe?</li><li><strong>Preço:</strong> avulso, pacote, mensalidade ou êxito quando juridicamente cabível?</li></ul>
<h3>Meta</h3><p>Reduzir dezenas de possibilidades para aproximadamente <strong>8 a 15 produtos comerciais claros</strong>.</p><span class="tag">NÃO PRECIFICAR AINDA</span>
</div></details>

<details><summary><span class="phaseTitle"><span class="n">4</span> Operação: desenhar como a empresa entrega</span></summary><div class="inside">
<div class="tree">Lead
  ↓
Triagem
  ↓
Classificação do caso / necessidade
  ↓
Proposta + pagamento
  ↓
Execução
  ↓
Documento / sistema / acordo / entrega
  ↓
Parceiro ou órgão externo, quando necessário
  ↓
Pós-atendimento
  ↓
Indicação / recorrência / próxima oportunidade</div>
<h3>Precisaremos definir</h3><ul><li>Quem atende.</li><li>Quem aprova.</li><li>Quem executa.</li><li>Prazos.</li><li>Modelos de documentos.</li><li>Checklist por serviço.</li><li>CRM e status.</li><li>Financeiro.</li><li>Indicadores.</li></ul>
</div></details>

<details><summary><span class="phaseTitle"><span class="n">5</span> Estrutura comercial: preços, planos e recorrência</span></summary><div class="inside">
<h3>Possíveis modelos</h3><ul><li><strong>Serviço avulso:</strong> cliente paga por uma demanda específica.</li><li><strong>Pacote:</strong> conjunto de etapas por preço fechado.</li><li><strong>Mensalidade B2B:</strong> empresa paga para ter atendimento contínuo.</li><li><strong>Implantação + mensalidade:</strong> especialmente CNJP Tech.</li><li><strong>Projeto sob medida:</strong> escopo fechado para empresas.</li></ul>
<div class="callout">Prioridade estratégica: procurar serviços que gerem <strong>receita recorrente</strong>, não apenas trabalhos isolados.</div>
</div></details>

<details><summary><span class="phaseTitle"><span class="n">6</span> Marca, site e marketing</span></summary><div class="inside">
<p>Só agora mexemos seriamente em comunicação.</p>
<h3>Entregas</h3><ul><li>Arquitetura de marca CNJP / CNJP Tech.</li><li>Site institucional principal.</li><li>Páginas específicas por problema.</li><li>WhatsApp e triagem automatizada.</li><li>Google Ads para intenção direta.</li><li>Meta Ads para descoberta e remarketing.</li><li>Prospecção B2B.</li><li>Programa de parceiros e indicações.</li><li>Cases e prova social.</li></ul>
<div class="callout">Marketing amplifica o que já existe. Se a oferta estiver confusa, ele só amplifica a confusão com orçamento diário.</div>
</div></details>

<details><summary><span class="phaseTitle"><span class="n">7</span> Escala: transformar operação em ativo</span></summary><div class="inside">
<h3>Justiça privada</h3><ul><li>Contratos recorrentes com empresas.</li><li>Rede de parceiros.</li><li>Procedimentos padronizados.</li><li>Automação administrativa.</li><li>Possíveis credenciamentos e expansão institucional, após validação específica.</li></ul>
<h3>Tech</h3><ul><li>Reaproveitar módulos entre clientes.</li><li>Transformar soluções repetidas em produto.</li><li>Criar SaaS quando houver demanda comprovada.</li><li>Receita de implantação + mensalidade + evolução.</li></ul>
</div></details>
</section>

<section class="section">
<h2>CNJP Tech</h2><p>Um braço real da empresa, mas separado da comunicação jurídica.</p>
<details open><summary>O que vender</summary><div class="inside"><div class="matrix">
<div class="idea"><b>Atendimento inteligente</b><span>WhatsApp, IA, triagem, CRM, agenda e transferência para humano.</span></div>
<div class="idea"><b>CRM sob medida</b><span>Leads, clientes, propostas, contratos, agenda, financeiro e relatórios.</span></div>
<div class="idea"><b>Automação empresarial</b><span>Eliminar tarefas repetitivas entre formulários, planilhas, e-mail, WhatsApp e sistemas.</span></div>
<div class="idea"><b>IA aplicada</b><span>Atendimento, qualificação, suporte, análise documental e follow-up.</span></div>
<div class="idea"><b>Sites e landing pages</b><span>Como parte de uma solução maior, não como commodity isolada.</span></div>
<div class="idea"><b>Infraestrutura</b><span>Hospedagem, domínio, e-mail, servidor, backups, integrações e manutenção.</span></div>
</div></div></details>
<details><summary>Como pensar a venda</summary><div class="inside"><p><strong>Não:</strong> “fazemos PHP, JavaScript, IA e APIs”.</p><p><strong>Sim:</strong> “sua equipe perde horas copiando informação e respondendo as mesmas coisas? Nós automatizamos esse processo.”</p><p>O valor está no problema resolvido, não na ferramenta usada.</p></div></details>
<details><summary>Cases que já podem nascer dentro de casa</summary><div class="inside"><ul><li>Estúdio Daniel Araujo: CRM, atendimento, campanhas, WhatsApp, agenda e automações.</li><li>CNJP: triagem, gestão de casos, documentos, agenda, cobrança e relacionamento.</li><li>Projetos internos que podem virar módulos reutilizáveis para outros clientes.</li></ul><p>A própria operação vira laboratório e prova de conceito.</p></div></details>
</section>

<section class="section">
<h2>Guardrails importantes</h2><p>Parte chata, portanto parte que evita merda cara.</p>
<details><summary>Atividades jurídicas e reguladas</summary><div class="inside"><div class="callout danger"><strong>Antes de vender:</strong> cada serviço jurídico, extrajudicial, arbitral ou documental precisa ser validado conforme formação da Fran, habilitações, regras profissionais, necessidade de advogado/parceiro, tribunal, cartório ou outro órgão competente.</div><p>Não presumir que um CNAE autoriza automaticamente a execução irrestrita de uma atividade profissional regulada. CNAE é enquadramento cadastral; habilitação profissional e regras específicas são outra história.</p></div></details>
<details><summary>Separação entre mediação e representação</summary><div class="inside"><p>Em atividades de mediação e conciliação, imparcialidade e conflito de interesses precisam ser tratados desde o desenho do serviço. A empresa deve definir quando atua como terceiro neutro e quando encaminha a demanda a advogado ou outro parceiro.</p></div></details>
<details><summary>Promessas comerciais</summary><div class="inside"><p>Evitar prometer resultado garantido em conflitos, processos, benefícios, regularizações ou procedimentos dependentes de terceiros. A promessa deve descrever o trabalho e o objetivo, não fabricar certeza onde ela não existe.</p></div></details>
<details><summary>Proteção de dados</summary><div class="inside"><p>A CNJP lida potencialmente com documentos, conflitos, informações financeiras e dados pessoais sensíveis. O desenho operacional deverá incluir acesso mínimo, armazenamento seguro, backups, registro de consentimentos quando aplicável e política de retenção.</p></div></details>
</section>

<section class="section">
<h2>Ideias em estacionamento</h2><p>Boas ideias que não precisam sequestrar nossa atenção agora.</p>
<details><summary>CNJP Empresas</summary><div class="inside"><ul><li>Mesa permanente de conciliação.</li><li>Recuperação amigável de recebíveis.</li><li>Mediação com clientes e fornecedores.</li><li>Planos mensais por volume de casos.</li><li>Serviço terceirizado para imobiliárias, clínicas, escolas, academias e condomínios.</li></ul></div></details>
<details><summary>CNJP para escritórios e profissionais</summary><div class="inside"><ul><li>Mediação terceirizada.</li><li>Triagem e organização documental.</li><li>Ferramentas tecnológicas para escritórios.</li><li>Automação de atendimento.</li><li>Parcerias de encaminhamento.</li></ul></div></details>
<details><summary>CNJP Imobiliário</summary><div class="inside"><ul><li>Conflitos entre locador e inquilino.</li><li>Negociações entre comprador e vendedor.</li><li>Organização documental.</li><li>Regularização com rede de profissionais habilitados.</li><li>Parcerias com imobiliárias e corretores.</li></ul></div></details>
<details><summary>Produtos futuros de tecnologia</summary><div class="inside"><ul><li>CRM vertical por segmento.</li><li>Atendimento com IA.</li><li>Sistema de recuperação de leads esquecidos.</li><li>Automação documental.</li><li>Dashboards gerenciais.</li><li>SaaS derivado de soluções repetidas.</li></ul></div></details>
</section>

<section class="section notes">
<h2>Bloco de notas</h2><p>Jogue aqui ideias, dúvidas e coisas que a Fran inventar às 23h47. Fica salvo neste navegador.</p>
<textarea id="notes" placeholder="Ex.: Fran tem certificado X...\nCliente antigo pediu serviço Y...\nPrecisamos verificar possibilidade Z..."></textarea>
<small>Salvamento local automático. Não envia nada para servidor.</small>
</section>

<section class="section">
<h2>Próxima decisão</h2>
<div class="now"><span class="pill">NÃO PULAR ETAPA</span><h2>Terminar o inventário.</h2><p>Quando os seis itens do checklist inicial estiverem levantados, a próxima versão desta página deve receber o <strong>Catálogo Bruto de Serviços</strong>. Só depois começamos a selecionar produtos, preços e campanhas.</p></div>
</section>
</main>
<footer class="footer"><div class="wrap"><strong>CNJP Roadmap</strong><br>Documento interno de planejamento. Conteúdo estratégico não substitui validação jurídica, contábil ou regulatória específica.</div></footer>
<script>
const checks=[...document.querySelectorAll('#checklist input[type=checkbox]')];
const bar=document.getElementById('bar'), progressText=document.getElementById('progressText');
function updateProgress(){let done=0;checks.forEach(c=>{const row=c.closest('.step');if(c.checked){done++;row.classList.add('done')}else row.classList.remove('done')});const pct=Math.round(done/checks.length*100);bar.style.width=pct+'%';progressText.textContent=pct+'% concluído • '+done+' de '+checks.length+' itens';}
checks.forEach(c=>{c.checked=localStorage.getItem('cnjp_'+c.id)==='1';c.addEventListener('change',()=>{localStorage.setItem('cnjp_'+c.id,c.checked?'1':'0');updateProgress()})});
updateProgress();
const notes=document.getElementById('notes');notes.value=localStorage.getItem('cnjp_notes')||'';let t;notes.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>localStorage.setItem('cnjp_notes',notes.value),250)});
</script>
</body>
</html>