<?php
declare(strict_types=1);
/* Roadmap interno do projeto CNJP Cartório Digital.
   Página noindex: não deve entrar em buscadores nem ser usada como destino de anúncio. */

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$phases = [
    [
        'id' => 'f0',
        'tag' => 'Fase 0',
        'title' => 'Fundação — concluído',
        'goal' => 'Base técnica limpa, publicando sozinha e sem depender deste computador.',
        'state' => 'done',
        'steps' => [
            [
                'id' => 'f0-triagem', 'title' => 'Remover a seção "Triagem guiada"', 'owner' => 'Assistente',
                'prio' => 'feito', 'effort' => 'concluído', 'done' => true,
                'why' => 'A seção saiu da home, junto com o script que a montava (triage-feedback.js).',
                'how' => ['Commit e374801 publicado na branch main.'],
            ],
            [
                'id' => 'f0-cta', 'title' => 'Remover o CTA órfão e o endpoint de IA local', 'owner' => 'Assistente',
                'prio' => 'feito', 'effort' => 'concluído', 'done' => true,
                'why' => 'O botão "Resolver online" apontava para a seção removida. A action=triage era o único uso do Ollama.',
                'how' => ['Commit a867633.', 'Com isso o projeto não depende mais do PC local (nem da extensão curl).'],
            ],
            [
                'id' => 'f0-deploy', 'title' => 'Deploy automático GitHub → HostGator', 'owner' => 'Daniel + Assistente',
                'prio' => 'feito', 'effort' => 'concluído', 'done' => true,
                'why' => 'Todo push na main dispara o workflow "Deploy Production Site", que chama o webhook no servidor e roda git pull.',
                'how' => ['Os commits de hoje subiram sozinhos em ~7 segundos cada.', 'Não existe mais upload manual de arquivos.'],
            ],
            [
                'id' => 'f0-infra', 'title' => 'Servidor validado (PHP + SQLite + acesso)', 'owner' => 'Assistente',
                'prio' => 'feito', 'effort' => 'concluído', 'done' => true,
                'why' => 'O banco SQLite funciona na HostGator: a API responde no ar, o catálogo devolve 25 serviços e o usuário admin já existe.',
                'how' => ['As rotas do painel respondem 401 sem login (proteção ativa).'],
            ],
        ],
    ],
    [
        'id' => 'f1',
        'tag' => 'Fase 1',
        'title' => 'Bloqueadores — resolver ANTES de ligar anúncio',
        'goal' => 'Sem estes itens você paga por cliques que não convertem, e as contas de anúncio podem ser reprovadas.',
        'state' => 'active',
        'steps' => [
            [
                'id' => 'contato', 'title' => 'Dados de contato reais visíveis no site', 'owner' => 'Daniel',
                'prio' => 'P0', 'effort' => '30 min', 'done' => false,
                'why' => 'Hoje não existe telefone, WhatsApp, e-mail nem horário em lugar nenhum. O próprio modal promete "a equipe entrará em contato por WhatsApp" sem informar número. Para serviço local, o botão de WhatsApp é o principal canal de conversão.',
                'how' => [
                    'Definir o número comercial (com DDD) usado no WhatsApp Business.',
                    'Adicionar botão flutuante de WhatsApp e um bloco de contato no rodapé.',
                    'Publicar horário de atendimento e e-mail.',
                ],
            ],
            [
                'id' => 'identidade', 'title' => 'Identidade do anunciante (CNPJ, endereço, responsável)', 'owner' => 'Daniel',
                'prio' => 'P0', 'effort' => '1 h', 'done' => false,
                'why' => 'O Google Ads exige identidade verificável do anunciante e o Meta analisa confiança e conformidade. Site que capta dados sem identificar quem os trata é reprovado ou limitado.',
                'how' => [
                    'Informar CNPJ (ou CPF, se ainda for MEI/pessoa física) e razão social.',
                    'Endereço comercial e telefone de contato.',
                    'Me enviar os dados para eu montar o rodapé legal do site.',
                ],
            ],
            [
                'id' => 'lgpd', 'title' => 'Política de Privacidade e Termos de Uso', 'owner' => 'Assistente redige, Daniel revisa',
                'prio' => 'P0', 'effort' => '2 h', 'done' => false,
                'why' => 'O site coleta nome, telefone e relato do caso, e guarda documentos (PDF/JPG) com retenção de 365 dias. Isso é dado pessoal sob a LGPD. Formulário de lead no Meta exige link de política de privacidade.',
                'how' => [
                    'Eu redijo os dois textos em linguagem simples e você revisa.',
                    'Publicar em /cartorio/politica-de-privacidade e /cartorio/termos.',
                    'Linkar no rodapé e junto ao formulário de envio.',
                ],
            ],
            [
                'id' => 'simulado', 'title' => 'Tirar "simulado" e "beta" de todo o site', 'owner' => 'Assistente',
                'prio' => 'P0', 'effort' => '1 h', 'done' => true,
                'why' => 'A página diz hoje: "Criar pedido simulado", "Estimativa simulada — valores apenas demonstrativos" e "Ambiente beta". Anunciar uma página que avisa que é simulação derruba a conversão e a nota de experiência da página de destino no Google.',
                'how' => [
                    'Trocar os textos por promessas reais ("Enviar pedido", "Pedir orçamento").',
                    'Manter o aviso honesto onde ele é juridicamente necessário, sem tom de maquete.',
                ],
            ],
            [
                'id' => 'aviso', 'title' => 'Aviso automático quando entra um lead', 'owner' => 'Assistente',
                'prio' => 'P0', 'effort' => '2 h', 'done' => false,
                'why' => 'Hoje o pedido cai no banco e ninguém é avisado: é preciso abrir o painel manualmente. Em campanha paga, lead que demora uma hora para ser visto esfria. É o vazamento mais caro do sistema.',
                'how' => [
                    'Enviar e-mail para cada novo pedido (remetente e destino definidos por você).',
                    'Opcional: aviso também no WhatsApp da equipe.',
                    'Aviso curto, com código, serviço, telefone e o relato do cliente.',
                ],
            ],
            [
                'id' => 'autoresposta', 'title' => 'Auto-resposta ao cliente com o código', 'owner' => 'Assistente',
                'prio' => 'P0', 'effort' => '1 h', 'done' => true,
                'why' => 'Quem vem de anúncio espera resposta imediata. A tela já mostra o código; falta confirmar por e-mail ou WhatsApp para o cliente não achar que caiu no vazio.',
                'how' => ['Implementado: campo de e-mail opcional no formulário, auto-resposta transacional com o código e o link do portal (notify.php).', 'Falta só o e-mail de contato/WhatsApp real em notify-config.php para a assinatura ficar completa.'],
            ],
            [
                'id' => 'manual', 'title' => 'Corrigir o manual.html', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '20 min', 'done' => true,
                'why' => 'O manual (link no rodapé) ainda tinha um capítulo "Triagem" dizendo que "a IA local sugere até três serviços" — descrevia a funcionalidade que foi removida.',
                'how' => ['Capítulo reescrito para o fluxo real: o cliente escolhe o serviço no catálogo, a equipe confere na triagem.', 'Simulador de decisão órfão removido (botões e script).'],
            ],
            [
                'id' => 'duplicata', 'title' => 'Remover código duplicado e telas falsas do site', 'owner' => 'Assistente',
                'prio' => 'P0', 'effort' => '1 h', 'done' => true,
                'why' => 'app.js e catalog.js montavam o mesmo grid, e o app.js ainda tinha um formulário inteiro (clientFlow) que nunca aparecia: era inalcançável porque a Triagem guiada foi removida. Pior: havia telas que mostravam códigos fixos (CD-1091, CD-1092) sem salvar nada, dando a impressão de pedido criado.',
                'how' => ['app.js ficou só com o painel administrativo; o fluxo público é 100% catalog.js + enhancements.js.', 'Formulário clientFlow, showService/showTriage/showHuman e as confirmações falsas removidos.', 'Corrigido no caminho um bug real: o serviço escolhido não entrava no pedido e o clique no modal reabria o detalhe.'],
            ],
        ],
    ],
    [
        'id' => 'f2',
        'tag' => 'Fase 2',
        'title' => 'Conversão — fazer o visitante virar pedido',
        'goal' => 'Ajustes que aumentam quantos visitantes pedem orçamento, sem mudar o produto.',
        'state' => 'todo',
        'steps' => [
            [
                'id' => 'portal', 'title' => 'Tirar o portal do cliente de dentro do herói', 'owner' => 'Daniel',
                'prio' => 'P1', 'effort' => 'decisão', 'done' => true,
                'why' => 'Sugestão de tirar a caixa "Acompanhe seu pedido" do herói. Avaliada e recusada pelo Daniel: quem já é cliente precisa achar o acompanhamento de cara. Mantido como está.',
                'how' => ['Decisão registrada: a caixa do portal permanece no herói.', 'Prioridade vai para encurtar o resto da dobra no celular (item cta-mobile).'],
            ],
            [
                'id' => 'cta-mobile', 'title' => 'Encurtar a primeira dobra no celular', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '1 h', 'done' => true,
                'why' => 'Medição em 390x844: o botão principal aparece entre 386px e 438px. A dobra terminava no portal do cliente, então a oferta de serviços só aparecia depois de rolar.',
                'how' => ['Breakpoint novo para telas até 599px: headline, espaçamentos e caixa do portal compactados.', 'O portal permaneceu na dobra (decisão do Daniel); o catálogo subiu de 1433px para 1394px.'],
            ],
            [
                'id' => 'prova', 'title' => 'Prova social e "quem somos"', 'owner' => 'Daniel',
                'prio' => 'P1', 'effort' => '2 h (depende de conteúdo)', 'done' => false,
                'why' => 'Serviço que lida com documentos e dinheiro precisa de confiança. Não há um depoimento, um tempo médio de resposta, um número de casos atendidos.',
                'how' => [
                    'Me enviar depoimentos reais, tempo médio de resposta e formas de contato.',
                    'Eu monto a seção com o material.',
                ],
            ],
            [
                'id' => 'faq', 'title' => 'Bloco de perguntas frequentes', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '2 h', 'done' => true,
                'why' => 'As dúvidas que travam a conversão são sempre as mesmas e não estavam respondidas: quanto custa, qual o prazo, quais documentos, "vocês são um cartório?".',
                'how' => ['8 perguntas em bloco recolhível, entre a seção de atendimento humano e a de transparência.', 'Cada resposta curta, em linguagem de cliente, ligada ao que o sistema faz hoje (código, portal, retenção de 365 dias).'],
            ],
            [
                'id' => 'contagem', 'title' => 'Unificar a contagem de serviços', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '30 min', 'done' => true,
                'why' => 'O selo da página dizia "60+", o catálogo carregava 25 do banco e o arquivo estático tinha outros números. Números diferentes minam a credibilidade.',
                'how' => ['O selo agora mostra a contagem real do banco (24 serviços), sem o "+" que prometia mais do que existe.', 'Removida a duplicata "procuracao-publica", que era o mesmo serviço de "procuracoes".', 'Texto da seção sem número chumbado.'],
            ],
            [
                'id' => 'landing', 'title' => 'Landing page por serviço', 'owner' => 'Assistente',
                'prio' => 'P2', 'effort' => '1 dia', 'done' => true,
                'why' => 'Anúncio de "certidão de nascimento" caindo na home gera cliques caros e pouco foco. Página dedicada por serviço converte muito mais.',
                'how' => ['/cartorio/s/<slug>/ com título, descrição, preço, documentos, etapas e formulário próprio do serviço.', 'Cada landing envia o pedido já com o serviço certo e com a origem marcada.', 'Title e description próprios para o anúncio; URLs bonitas via .htaccess.'],
            ],
            [
                'id' => 'obrigado', 'title' => 'Página de obrigado (thank-you)', 'owner' => 'Assistente',
                'prio' => 'P2', 'effort' => '1 h', 'done' => true,
                'why' => 'É onde se mede conversão com precisão e onde o cliente confirma que o pedido chegou.',
                'how' => ['/cartorio/obrigado/?code=CD-XXXX confirma o pedido, mostra o código, o serviço e os próximos passos.', 'É a URL de conversão a usar no Pixel e no GA4 (item eventos).', 'Dispara o evento cnjp:pedido no navegador para as tags escutarem.'],
            ],
        ],
    ],
    [
        'id' => 'f3',
        'tag' => 'Fase 3',
        'title' => 'Medição — saber de onde vem o cliente',
        'goal' => 'Sem isso a campanha roda no escuro: você paga e não sabe qual anúncio traz cliente.',
        'state' => 'todo',
        'steps' => [
            [
                'id' => 'utm', 'title' => 'Gravar a origem (UTM) em cada pedido', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '2 h', 'done' => true,
                'why' => 'O ticket hoje guarda o canal, mas não guarda utm_source, utm_medium e utm_campaign. Sem isso não há como saber qual campanha gerou qual pedido.',
                'how' => ['origem.js guarda utm_source/medium/campaign/content/term, gclid, fbclid, a página de entrada e o referrer externo na sessão.', 'Os pedidos do site, do pré-orçamento, do atendimento humano e da landing enviam essa origem e ela é salva no banco.', 'O painel mostra um bloco "Origem do pedido" dentro do ticket.'],
            ],
            [
                'id' => 'pixel', 'title' => 'Pixel do Meta + GA4 + tag do Google Ads', 'owner' => 'Daniel + Assistente',
                'prio' => 'P1', 'effort' => '2 h', 'done' => false,
                'why' => 'Hoje não existe nenhum rastreamento instalado. Sem pixel não há remarketing, nem otimização por conversão, nem medição de custo por lead.',
                'how' => [
                    'Você cria (ou me passa) os IDs de Pixel, GA4 e Google Ads.',
                    'Eu instalo as tags no template e valido com o Tag Assistant.',
                ],
            ],
            [
                'id' => 'eventos', 'title' => 'Marcar os eventos de conversão', 'owner' => 'Assistente',
                'prio' => 'P1', 'effort' => '2 h', 'done' => false,
                'why' => 'As plataformas precisam aprender o que é sucesso. Sem evento de conversão, a campanha otimiza por clique — que é justamente o que você paga sem retorno.',
                'how' => ['Disparar evento no envio do pedido e no login do portal.', 'Enviar o código do pedido como valor da conversão.'],
            ],
            [
                'id' => 'consent', 'title' => 'Aviso de cookies e consentimento (LGPD)', 'owner' => 'Assistente',
                'prio' => 'P2', 'effort' => '2 h', 'done' => false,
                'why' => 'Ao instalar pixel e GA4, o site passa a rastrear navegação. Um aviso simples de cookies mantém a operação alinhada à LGPD.',
                'how' => ['Banner discreto com aceite e link para a política de privacidade.'],
            ],
        ],
    ],
    [
        'id' => 'f4',
        'tag' => 'Fase 4',
        'title' => 'Campanha no ar',
        'goal' => 'Ligar o tráfego depois que as fases acima estiverem fechadas.',
        'state' => 'todo',
        'steps' => [
            [
                'id' => 'contas', 'title' => 'Contas de anúncio e verificação', 'owner' => 'Daniel',
                'prio' => 'P1', 'effort' => '1 a 3 dias (análise das plataformas)', 'done' => false,
                'why' => 'Contas novas passam por verificação. Melhor criar antes e deixar aprovada do que descobrir na hora de subir a campanha.',
                'how' => ['Criar e verificar a conta no Meta Ads e no Google Ads.', 'Configurar forma de pagamento e limites.'],
            ],
            [
                'id' => 'posicionamento', 'title' => 'Decidir o posicionamento do nome', 'owner' => 'Daniel',
                'prio' => 'P1', 'effort' => 'decisão', 'done' => false,
                'why' => 'O site se chama "Cartório Digital", mas o próprio texto afirma que a CNJP não é cartório e não pratica ato de fé pública. Quem clica num anúncio de "cartório" espera o cartório: gera lead ruim, reclamação e risco de reprovação do anúncio.',
                'how' => [
                    'Escolher o rótulo: "central de serviços e documentos" em vez de "cartório".',
                    'Decidir se a página fica em /cartorio/ ou ganha caminho/subdomínio próprio (hoje ela vive dentro de danieltatuador.com, um domínio de estúdio de tatuagem).',
                ],
            ],
            [
                'id' => 'estrutura', 'title' => 'Estrutura de campanha por serviço', 'owner' => 'Assistente',
                'prio' => 'P2', 'effort' => '2 h', 'done' => false,
                'why' => 'Campanha separada por serviço permite cortar o que não vende e escalar o que vende.',
                'how' => ['Definir palavras-chave negativas.', 'Um conjunto de anúncios por serviço, cada um para sua landing page.'],
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
<title>Próximos passos — CNJP Cartório Digital</title>
<link rel="stylesheet" href="../assets/local-fonts.css">
<style>
:root{--ink:#162235;--muted:#5b6675;--line:#e4e7ec;--teal:#0f766e;--teal2:#115e59;--tealSoft:#ecfdf5;--white:#fff;--amber:#b45309;--red:#b42318;--radius:18px}
*{box-sizing:border-box}
body{margin:0;background:#f5f7f8;color:var(--ink);font-family:Inter,system-ui,-apple-system,sans-serif;line-height:1.5;-webkit-text-size-adjust:100%}
.wrap{width:min(1080px,calc(100% - 32px));margin-inline:auto}
.topbar{background:#101828;color:#b6c0cc;font-size:.74rem;padding:10px 0}
.topbar .wrap{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:space-between}
.topbar b{color:#5eead4}
header.hero{background:linear-gradient(160deg,#0f2928,#123f3c 60%,#0f766e);color:#fff;padding:52px 0 40px}
.brand{display:inline-flex;align-items:center;gap:9px;text-decoration:none;color:#fff;margin-bottom:26px}
.brand-mark{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.16);font-weight:800}
.brand strong,.brand small{display:block}
.brand strong{font-size:.9rem;line-height:1}
.brand small{font-size:.63rem;color:#9fd8d2;margin-top:3px}
.kicker{font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:#7ff0e0}
h1{font-size:clamp(1.75rem,5vw,3rem);line-height:1.06;letter-spacing:-.04em;margin:14px 0 14px;max-width:780px}
h1 em{font-style:normal;color:#8df3e4}
.lead{color:#dbeeeb;font-size:.97rem;max-width:690px;margin:0}
.meter{margin-top:30px;display:grid;gap:9px;max-width:560px}
.meter .bar{height:13px;border-radius:999px;background:rgba(255,255,255,.18);overflow:hidden}
.meter .bar i{display:block;height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#8df3e4,#14b8a6);border-radius:999px}
.meter .txt{font-size:.8rem;color:#cfe9e6}
.meter .txt b{color:#fff;font-size:1.05rem}
main{padding:30px 0 10px}
.card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:20px;margin-bottom:16px}
.card h2{margin:0 0 8px;font-size:1.08rem;letter-spacing:-.02em}
.need{border:1px solid #fdba74;background:linear-gradient(135deg,#fff7ed,#fff)}
.need>p{margin:0 0 14px;color:#7c4a12;font-size:.8rem}
.need ul{margin:0;padding-left:0;list-style:none;display:grid;gap:8px}
.need li{display:flex;gap:10px;align-items:flex-start;font-size:.8rem}
.need li::before{content:'→';color:#c2410c;font-weight:800;flex:0 0 auto}
.deploy{border:1px solid #93c5fd;background:linear-gradient(135deg,#eff6ff,#fff)}
.deploy p{margin:0 0 12px;color:#3c4a5c;font-size:.8rem}
.flow{display:flex;flex-wrap:wrap;gap:7px;align-items:center;font-size:.73rem;margin:0 0 14px}
.flow span{background:#fff;border:1px solid #cbd5e1;border-radius:999px;padding:6px 11px;font-weight:650}
.flow i{color:#64748b;font-style:normal;font-weight:700}
.phase{border-top:4px solid #cbd5e1;padding-top:10px}
.phase.done{border-top-color:#12b76a}
.phase.active{border-top-color:var(--teal)}
.phase-head{display:flex;gap:12px;align-items:center;flex-wrap:wrap;background:none;border:none;width:100%;text-align:left;padding:12px 4px;margin:0;cursor:pointer;border-radius:10px;transition:background .15s}
.phase-head:hover{background:#f3f6f6}
.phase-tag{background:#eef1f4;color:#3f4a58;border-radius:999px;padding:5px 11px;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em}
.phase.active .phase-tag{background:var(--teal);color:#fff}
.phase.done .phase-tag{background:#d1fadf;color:#05603a}
.phase h2{margin:0;font-size:1.1rem;flex:1;min-width:180px}
.phase-chev{transition:transform .2s;color:var(--muted);flex:0 0 auto;font-size:.8rem}
.phase.closed .phase-chev{transform:rotate(-90deg)}
.phase-goal{margin:0 0 16px;color:var(--muted);font-size:.8rem;padding:0 4px}
/* arvore: indentacao + conector */
.tree{padding-left:6px}
.tree .step{position:relative;margin-left:9px;border-left:1px solid #d7dde3;border-radius:0 14px 14px 0}
.tree .step::before{content:'';position:absolute;left:-1px;top:20px;width:14px;height:1px;background:#d7dde3}
.step.closed{border-bottom-width:1px}
.step{border:1px solid var(--line);border-radius:14px;padding:0;margin-bottom:0;background:#fff;transition:.15s}
.step + .step{margin-top:11px}
.step:hover{border-color:#8ecfc7}
.step.is-done{background:#fbfdfc;border-color:#cdece6}
.step-top{display:flex;gap:11px;align-items:flex-start;padding:14px 15px 13px}
.step.is-closed .step-top{padding:14px 15px 14px}
.step-top input[type=checkbox]{width:19px;height:19px;margin:3px 0 0;accent-color:var(--teal);flex:0 0 auto;cursor:pointer}
.step-body{min-width:0;flex:1}
.step-headline{display:flex;align-items:flex-start;gap:9px;justify-content:space-between}
.step-title{font-weight:750;font-size:.88rem;cursor:pointer;margin-bottom:8px}
.step.is-done .step-title{color:#5b6675;text-decoration:line-through;text-decoration-color:#8ecfc7}
.step-chev{color:var(--muted);cursor:pointer;flex:0 0 auto;font-size:.78rem;margin-left:6px;transition:transform .18s;user-select:none}
.step.closed .step-chev{transform:rotate(-90deg)}
.chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}
.chip{border-radius:999px;padding:4px 9px;font-size:.6rem;font-weight:800;background:#eef1f4;color:#3f4a58;white-space:nowrap;letter-spacing:.02em}
.chip.p0{background:#b42318;color:#fff}
.chip.p1{background:#fef0c7;color:#93370d}
.chip.p2{background:#dbe7fe;color:#1d4ed8}
.chip.ok{background:#d1fadf;color:#05603a}
.chip.owner{background:#ece9fe;color:#5925dc}
.step-detail{overflow:hidden}
.step.closed .step-detail{display:none}
.step-detail{padding:0 15px 15px}
.why{margin:0 0 9px;font-size:.78rem;color:#3f4a58}
.why strong{color:var(--ink)}
.how{margin:0;padding-left:19px;display:grid;gap:5px}
.how li{font-size:.76rem;color:var(--muted)}
.seq{margin:0;padding-left:20px;display:grid;gap:6px}
.seq li{font-size:.79rem;color:#3f4a58}
@media(max-width:520px){
  .tree{padding-left:0}
  .tree .step{margin-left:6px}
}
.reset{border:1px solid #cfd5dc;background:#fff;color:#344054;border-radius:10px;padding:9px 13px;font-size:.72rem;font-weight:700;cursor:pointer}
.reset:hover{border-color:#8ecfc7;color:var(--teal)}
footer{border-top:1px solid var(--line);margin-top:10px;padding:24px 0 44px;color:#9aa4b1;font-size:.73rem;background:#101828}
footer a{color:#5eead4}
footer .wrap{display:grid;gap:9px}
.hidden{display:none}
@media(min-width:700px){
  .card{padding:24px}
  h1{margin-top:16px}
}
</style>
</head>
<body>
<div class="topbar">
  <div class="wrap">
    <span>Página interna do projeto — <b>não indexar e não usar como destino de anúncio</b></span>
    <span>Atualizada em <?= e(date('d/m/Y')) ?></span>
  </div>
</div>

<header class="hero">
  <div class="wrap">
    <a class="brand" href="../"><span class="brand-mark">CN</span><span><strong>CNJP</strong><small>Cartório Digital</small></span></a>
    <span class="kicker">Roadmap do projeto</span>
    <h1>O que falta para essa página <em>vender de verdade.</em></h1>
    <p class="lead">Auditoria honesta da página atual, com o caminho em ordem de prioridade. O que está marcado como P0 trava campanha: sem isso, o dinheiro do anúncio vaza e as plataformas podem reprovar a conta.</p>
    <div class="meter">
      <div class="bar"><i></i></div>
      <span class="txt"><b><?= $doneSteps ?> de <?= $totalSteps ?></b> itens concluídos — <?= $pct ?>% do caminho</span>
    </div>
  </div>
</header>

<main class="wrap">

  <section class="card need">
    <h2>Preciso de você para destravar a Fase 1</h2>
    <p>São informações que só você tem. Com elas eu executo o resto.</p>
    <ul>
      <li>Número de WhatsApp comercial (com DDD) que vai atender os leads.</li>
      <li>Telefone, e-mail e horário de atendimento que devem aparecer no site.</li>
      <li>CNPJ (ou CPF, se ainda for MEI/pessoa física), razão social e endereço comercial.</li>
      <li>E-mail que deve receber o aviso de cada novo pedido.</li>
      <li>IDs de Pixel do Meta, GA4 e Google Ads — ou o aviso para eu criar a estrutura e você apenas colar os IDs.</li>
      <li>Depoimentos e tempo médio de resposta, se existirem.</li>
    </ul>
  </section>

  <section class="card deploy">
    <h2>Como as alterações chegam ao site (já é automático)</h2>
    <p>Não existe mais upload manual de arquivos: a publicação é feita pelo git.</p>
    <div class="flow">
      <span>Edito e testo aqui</span><i>→</i>
      <span>git commit</span><i>→</i>
      <span>git push na main</span><i>→</i>
      <span>GitHub Action</span><i>→</i>
      <span>webhook no servidor</span><i>→</i>
      <span>git pull na HostGator</span>
    </div>
    <p style="margin-bottom:0">Prova: os commits de 14/09/2026 (e374801, a867633 e 874b239) subiram sozinhos, com sucesso, em cerca de 6 a 7 segundos cada — execuções do workflow <b>Deploy Production Site</b>. O mesmo servidor já roda o banco SQLite com o catálogo publicado e o acesso administrativo criado.</p>
  </section>

  <?php $firstOpen = true; ?>
  <?php foreach ($phases as $phase): ?>
  <?php
    $closed = empty($firstOpen);
    $hidden = !empty($firstOpen);
    $firstOpen = false;
  ?>
  <section class="card phase <?= e($phase['state']) ?> <?= $closed ? 'closed' : '' ?>">
    <button type="button" class="phase-head" aria-expanded="<?= $closed ? 'false' : 'true' ?>">
      <span class="phase-tag"><?= e($phase['tag']) ?></span>
      <h2><?= e($phase['title']) ?></h2>
      <span class="phase-chev">▾</span>
    </button>
    <div class="phase-panel<?= $hidden ? '' : ' hidden' ?>">
      <p class="phase-goal"><?= e($phase['goal']) ?></p>
      <div class="tree">
        <?php foreach ($phase['steps'] as $step): ?>
          <article class="step <?= !empty($step['done']) ? 'is-done' : '' ?> closed" data-step="<?= e($step['id']) ?>">
            <div class="step-top">
              <input type="checkbox" <?= !empty($step['done']) ? 'checked' : '' ?> aria-label="<?= e($step['title']) ?>">
              <div class="step-body">
                <div class="step-headline">
                  <span class="step-title"><?= e($step['title']) ?></span>
                  <span class="step-chev" aria-hidden="true">▾</span>
                </div>
                <div class="chips">
                  <span class="chip <?= e($prioClass[$step['prio']] ?? '') ?>"><?= e($step['prio']) ?></span>
                  <span class="chip owner"><?= e($step['owner']) ?></span>
                  <span class="chip"><?= e($step['effort']) ?></span>
                </div>
                <div class="step-detail">
                  <p class="why"><strong>Por que importa:</strong> <?= e($step['why']) ?></p>
                  <ul class="how">
                    <?php foreach ($step['how'] as $line): ?>
                      <li><?= e($line) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

  <section class="card">
    <h2>Sequência recomendada</h2>
    <ol class="seq">
      <li>Você me envia os dados do bloco laranja (contato, CNPJ e e-mail de aviso).</li>
      <li>Eu publico contato, política de privacidade e termos. ("simulado/beta" já foi limpo do site.)</li>
      <li>Eu ligo o aviso automático de lead novo e a auto-resposta ao cliente.</li>
      <li>Eu instalo Pixel, GA4 e Google Ads e marco os eventos de conversão.</li>
      <li>Eu faço os ajustes de conversão (herói, celular, prova social, FAQ).</li>
      <li>Só então subimos a campanha, com uma landing page por serviço.</li>
    </ol>
    <div style="margin-top:16px"><button class="reset" id="reset">Limpar marcações desta página</button></div>
  </section>

</main>

<footer>
  <div class="wrap">
    <span>CNJP Cartório Digital — página interna de planejamento.</span>
    <span>Esta página não aparece em buscadores e não deve ser divulgada. <a href="../">Voltar ao site</a></span>
  </div>
</footer>

<script>
(() => {
  const key = 'cnjp-proximospassos';
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(key) || '{}'); } catch (err) { saved = {}; }

  // Acordeão das fases
  document.querySelectorAll('.phase-head').forEach(head => {
    head.addEventListener('click', () => {
      const phase = head.closest('.phase');
      const panel = phase.querySelector('.phase-panel');
      const closed = phase.classList.toggle('closed');
      panel.classList.toggle('hidden', closed);
      head.setAttribute('aria-expanded', closed ? 'false' : 'true');
    });
  });

  // Acordeão dos passos
  document.querySelectorAll('.step').forEach(step => {
    const titles = [step.querySelector('.step-title'), step.querySelector('.step-chev')]
      .filter(el => el);
    const toggle = () => step.classList.toggle('closed');
    titles.forEach(el => el.addEventListener('click', toggle));
  });

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
