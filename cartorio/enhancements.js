import { SERVICES, CATEGORIES, CASES, STATUS } from './data.js?v=20260826-5';

const $=(s,r=document)=>r.querySelector(s);
const $$=(s,r=document)=>[...r.querySelectorAll(s)];
const money=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(v);
const icon=n=>`<span class="material-symbols-rounded">${n}</span>`;
const normalize=(v='')=>v.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const serviceBy=id=>SERVICES.find(s=>s.id===id);
const categoryBy=id=>CATEGORIES.find(c=>c.id===id);

if(document.body.dataset.page==='client') initClientEnhancements();
if(document.body.dataset.page==='admin') initAdminEnhancements();

function initClientEnhancements(){
  const modal=$('#flowModal'),content=$('#flowContent');
  if(!modal||!content)return;
  const open=html=>{content.innerHTML=html;if(!modal.open)modal.showModal()};
  const close=()=>{if(modal.open)modal.close()};

  const guided=$('#guidedTriage');
  if(guided) renderGuidedTriage(guided,open);

  document.addEventListener('click',e=>{
    const card=e.target.closest('[data-service]');
    if(card&&document.body.dataset.page==='client'){
      e.preventDefault();e.stopImmediatePropagation();
      const s=serviceBy(card.dataset.service);
      if(s) showServiceDetail(s,open,close);
      return;
    }
    const track=e.target.closest('[data-track-enhanced]');
    if(track){e.preventDefault();e.stopImmediatePropagation();showEnhancedTracking(track.dataset.trackEnhanced||'CD-1086',open,close)}
  },true);

  const trackForm=$('#trackForm');
  trackForm?.addEventListener('submit',e=>{
    e.preventDefault();e.stopImmediatePropagation();
    showEnhancedTracking(trackForm.querySelector('input')?.value.trim().toUpperCase()||'CD-1086',open,close);
  },true);
}

function renderGuidedTriage(root,open){
  root.innerHTML=`<div class="guided-shell"><div class="guided-copy"><span class="kicker">Triagem guiada</span><h2>Não sabe o nome do serviço? Conte o problema.</h2><p>Escolha um assunto ou descreva em linguagem normal. Esta simulação ainda não usa IA: ela apenas mostra como a experiência pode funcionar antes de ligarmos inteligência de verdade.</p></div><div class="guided-box"><div class="guided-categories">${CATEGORIES.slice(0,8).map(c=>`<button type="button" data-guide-category="${c.id}">${icon(c.icon)}<span>${c.title}</span></button>`).join('')}</div><label class="guided-text"><span class="material-symbols-rounded">chat_bubble</span><textarea id="guidedProblem" placeholder="Ex.: meu pai morreu e deixou uma casa; não sei quais documentos preciso nem por onde começar."></textarea></label><div class="guided-actions"><button class="btn primary" id="guidedSuggest" type="button">Sugerir caminhos</button><button class="text-action" id="guidedHuman" type="button">Prefiro falar com uma pessoa</button></div><div id="guidedSuggestions" class="guided-suggestions" hidden></div></div></div>`;
  $$('[data-guide-category]',root).forEach(b=>b.addEventListener('click',()=>showGuideCategory(b.dataset.guideCategory,root)));
  $('#guidedSuggest',root)?.addEventListener('click',()=>suggestFromText($('#guidedProblem',root)?.value||'',root));
  $('#guidedHuman',root)?.addEventListener('click',()=>open(`<div class="flow-inner"><span class="kicker">Atendimento humano</span><h2>Sem problema. Uma pessoa assume daqui.</h2><p>Na operação real, o atendente faria exatamente a mesma triagem pelo balcão, telefone ou WhatsApp e preencheria o sistema por você.</p><div class="human-channels"><article>${icon('chat')}<div><strong>WhatsApp</strong><span>Conversa com atendente e envio de fotos/documentos.</span></div></article><article>${icon('call')}<div><strong>Telefone</strong><span>O operador registra tudo durante a ligação.</span></div></article><article>${icon('storefront')}<div><strong>Presencial</strong><span>O cliente leva o que tiver e a equipe organiza.</span></div></article></div></div>`));
}

function showGuideCategory(category,root){
  const cat=categoryBy(category),items=SERVICES.filter(s=>s.category===category).slice(0,6),box=$('#guidedSuggestions',root);
  box.hidden=false;
  box.innerHTML=`<div class="guided-result-head"><strong>${cat?.title||'Sugestões'}</strong><span>${items.length} caminhos iniciais</span></div>${items.map(s=>`<button type="button" class="guided-result" data-service="${s.id}">${icon(s.icon)}<span><strong>${s.title}</strong><small>${s.desc}</small></span></button>`).join('')}<button type="button" class="guided-open-catalog" id="guideOpenCatalog">Ver catálogo completo desta área</button>`;
  $('#guideOpenCatalog',box)?.addEventListener('click',()=>{const full=$('#fullCatalog'),show=$('#showFullCatalog');if(full){full.hidden=false;show?.setAttribute('aria-expanded','true');full.scrollIntoView({behavior:'smooth',block:'start'});const chip=$(`[data-category="${category}"]`,$('#categoryFilters'));chip?.click()}});
}

function suggestFromText(text,root){
  const q=normalize(text),box=$('#guidedSuggestions',root);
  if(!q){box.hidden=false;box.innerHTML='<div class="guided-empty">Escreva pelo menos um resumo do problema. A burocracia já é vaga o bastante por conta própria.</div>';return}
  const keywords={
    familia:['morreu','falecido','inventario','heranca','divorcio','casamento','filho','nascimento'],
    imoveis:['casa','apartamento','imovel','terreno','matricula','escritura','proprietario','vendeu','comprou'],
    cobranca:['divida','deve','cobrar','calote','protesto','pagamento','inadimplente'],
    mediacao:['briga','conflito','acordo','vizinho','condominio','escola','familia','empresa'],
    arbitragem:['contrato','clausula','arbitragem','socios','empresarial','obra'],
    notas:['firma','assinatura','autenticar','procuracao','apostila','documento'],
    civil:['certidao','nascimento','casamento','obito','segunda via'],
    rtdpj:['empresa','associacao','estatuto','ata','contrato','registro']
  };
  const scores={};
  Object.entries(keywords).forEach(([cat,words])=>scores[cat]=words.reduce((n,w)=>n+(q.includes(w)?1:0),0));
  const cats=Object.entries(scores).sort((a,b)=>b[1]-a[1]).filter(x=>x[1]>0).slice(0,2).map(x=>x[0]);
  let items=SERVICES.filter(s=>cats.includes(s.category));
  const terms=q.split(/\s+/).filter(x=>x.length>3);
  items=items.map(s=>({s,score:terms.reduce((n,t)=>n+(normalize(`${s.title} ${s.desc}`).includes(t)?1:0),0)+(cats.indexOf(s.category)===0?2:1)})).sort((a,b)=>b.score-a.score).map(x=>x.s).slice(0,5);
  if(!items.length)items=SERVICES.filter(s=>s.category==='apoio').slice(0,4);
  box.hidden=false;
  box.innerHTML=`<div class="guided-result-head"><strong>Caminhos que parecem fazer sentido</strong><span>simulação de triagem</span></div>${items.map(s=>`<button type="button" class="guided-result" data-service="${s.id}">${icon(s.icon)}<span><strong>${s.title}</strong><small>${s.desc}</small></span></button>`).join('')}<p class="guided-disclaimer">Numa versão funcional, a sugestão será revisada antes de virar procedimento. A ferramenta não substitui análise jurídica ou ato de profissional habilitado.</p>`;
}

function serviceContext(s){
  const cat=categoryBy(s.category)?.title||'Serviço';
  const limits=s.kind==='camara'
    ?'A CNJP administra e organiza o procedimento dentro das regras da Câmara. A decisão arbitral, quando houver, cabe ao árbitro constituído para o caso.'
    :s.kind==='profissional'
      ?'A CNJP cuida da organização e operação administrativa. Partes que exigem advogado, tabelião, registrador ou outro profissional habilitado são praticadas por eles.'
      :'A CNJP organiza, solicita, acompanha e comunica. Quando existir ato de fé pública ou competência exclusiva, ele é realizado pela serventia ou autoridade competente.';
  return {cat,limits};
}

function estimate(s){
  const fee=s.from||0;
  const official=Math.max(0,Math.round(fee*(s.kind==='camara'?0.12:s.category==='mediacao'?0.05:0.38)));
  const third=s.kind==='profissional'?Math.max(180,Math.round(fee*.25)):s.category==='imoveis'?80:0;
  return {fee,official,third,total:fee+official+third};
}

function showServiceDetail(s,open,close){
  const content=$('#flowContent'),ctx=serviceContext(s),est=estimate(s);
  open(`<div class="flow-inner service-detail"><div class="service-detail-head">${icon(s.icon)}<div><span class="kicker">${ctx.cat}</span><h2>${s.title}</h2><p>${s.desc}</p></div></div><div class="service-detail-grid"><section><h3>Quando este serviço ajuda</h3><p>Quando a demanda se encaixa em <strong>${s.title.toLowerCase()}</strong> e o cliente quer evitar descobrir sozinho formulários, documentos, canais, protocolos e próximos passos.</p></section><section><h3>Quem faz o quê</h3><p>${ctx.limits}</p></section></div><section class="case-section"><h3>Para começar, normalmente precisamos de</h3>${s.docs.map(d=>`<div class="doc-line"><span>${d}</span><span class="ok">vamos conferir</span></div>`).join('')}</section><section class="case-section"><h3>Como o processo anda</h3><div class="service-stepper">${s.steps.map((st,i)=>`<div><b>${i+1}</b><span>${st}</span></div>`).join('')}</div></section><section class="quote-preview"><div class="quote-head"><div><span class="kicker">Estimativa simulada</span><h3>Veja como o orçamento seria apresentado</h3></div><span>valores apenas demonstrativos</span></div><div class="quote-lines"><div><span>Taxas / emolumentos oficiais estimados</span><strong>${money(est.official)}</strong></div>${est.third?`<div><span>Terceiros / profissional estimado</span><strong>${money(est.third)}</strong></div>`:''}<div><span>Serviço CNJP</span><strong>${money(est.fee)}</strong></div><div class="quote-total"><span>Total estimado</span><strong>${money(est.total)}</strong></div></div><p>O valor real dependerá do caso, da serventia, dos documentos e de eventuais profissionais necessários.</p></section><div class="flow-actions service-detail-actions"><button class="btn secondary" type="button" data-service-human>Falar com uma pessoa</button><button class="btn primary" type="button" data-request-quote>Continuar e pedir orçamento</button></div></div>`);
  $('[data-service-human]',content)?.addEventListener('click',()=>showLeadForm(s,est,open,close,'humano'));
  $('[data-request-quote]',content)?.addEventListener('click',()=>showLeadForm(s,est,open,close,'digital'));
}

function showLeadForm(s,est,open,close,mode){
  const content=$('#flowContent');
  open(`<div class="flow-inner"><span class="kicker">${mode==='humano'?'Atendimento assistido':'Pré-orçamento'}</span><h2>${s.title}</h2><p>${mode==='humano'?'Deixe um contato e uma pessoa continua a conversa.':'Preencha o básico. O pedido só vira processo depois da conferência e aprovação do orçamento.'}</p><form id="enhancedLead" class="flow-form"><div class="field"><label>Nome</label><input required placeholder="Seu nome"></div><div class="field"><label>WhatsApp ou telefone</label><input required placeholder="(11) 99999-9999"></div><div class="field"><label>Conte o caso</label><textarea placeholder="Explique o que aconteceu e o que você já tem em mãos."></textarea></div><div class="field"><label>Como prefere ser atendido?</label><select><option>${mode==='humano'?'Quero falar com uma pessoa':'Quero continuar online'}</option><option>WhatsApp</option><option>Telefone</option><option>Presencial</option></select></div><section class="quote-mini"><span>Estimativa demonstrativa</span><strong>${money(est.total)}</strong><small>Taxas, terceiros e serviço aparecem separados antes da aprovação.</small></section><div class="flow-actions"><button class="btn primary" type="submit">Gerar pré-pedido simulado</button></div></form></div>`);
  $('#enhancedLead',content)?.addEventListener('submit',e=>{e.preventDefault();open(`<div class="flow-inner success-flow"><span class="success-icon material-symbols-rounded">task_alt</span><span class="kicker">Pré-pedido criado</span><h2>CD-1092</h2><p>Agora a equipe conferiria os dados e documentos. Depois disso o cliente receberia o orçamento final para aprovar antes de qualquer protocolo.</p><div class="track-summary"><div><span>Serviço</span><strong>${s.title}</strong></div><div><span>Status</span><strong>Aguardando conferência</strong></div><div><span>Estimativa</span><strong>${money(est.total)}</strong></div><div><span>Próxima ação</span><strong>Equipe revisar</strong></div></div><div class="flow-actions"><button class="btn primary" type="button" data-close-success>Entendi</button></div></div>`);$('[data-close-success]',content)?.addEventListener('click',close)})
}

function showEnhancedTracking(id,open,close){
  const content=$('#flowContent'),c=CASES.find(x=>x.id===id)||CASES[1];if(!c)return;
  const st=STATUS[c.status]||{label:c.status};
  const pending=c.docs.filter(([,s])=>s!=='ok');
  const next=c.status==='exigencia'?'Enviar o documento/correção exigida':c.status==='protocolado'?'Aguardar análise da serventia':c.status==='aguardando'?'Aguardar retorno do terceiro':c.status==='entregue'?'Nenhuma. Processo concluído':'Concluir checklist e conferência';
  open(`<div class="flow-inner portal-order"><span class="kicker">Portal do pedido</span><h2>${c.id} • ${c.title}</h2><p>${c.client} • Atendimento: ${c.channel}</p><div class="portal-status"><div><span>Status atual</span><strong>${st.label}</strong></div><div><span>Próxima ação</span><strong>${next}</strong></div></div><div class="track-summary portal-summary"><div><span>Responsável CNJP</span><strong>${c.owner}</strong></div><div><span>Prazo estimado</span><strong>${c.deadline}</strong></div><div><span>Taxas oficiais</span><strong>${money(c.official)}</strong></div><div><span>Serviço CNJP</span><strong>${money(c.fee)}</strong></div></div><div class="portal-columns"><section class="case-section"><h3>Documentos</h3>${c.docs.map(([d,s])=>`<div class="doc-line"><span>${d}</span><span class="${s}">${s==='ok'?'✓ conferido':s==='missing'?'faltando':'aguardando'}</span></div>`).join('')}</section><section class="case-section"><h3>Pendências</h3>${pending.length?pending.map(([d])=>`<div class="pending-line">${icon('error')}<span>${d}</span></div>`).join(''):'<div class="all-good">✓ Nada pendente com você agora.</div>'}</section></div><section class="case-section"><h3>Linha do tempo</h3><div class="timeline">${c.timeline.map((t,i)=>`<div class="${i<c.timeline.length-1?'done':'active'}">${t}</div>`).join('')}</div></section><section class="portal-files"><div><span class="material-symbols-rounded">folder</span><div><strong>Arquivos do pedido</strong><small>Na versão funcional, certidões, recibos e comprovantes aparecerão aqui.</small></div></div><button class="btn secondary small" type="button">Ver arquivos simulados</button></section><div class="flow-actions"><button class="btn secondary" type="button" data-portal-close>Fechar</button><button class="btn primary" type="button">Falar com o atendimento</button></div></div>`);
  $('[data-portal-close]',content)?.addEventListener('click',close);
}

function initAdminEnhancements(){
  const root=$('#adminContent');if(!root)return;
  const observer=new MutationObserver(()=>enhanceAdmin(root));
  observer.observe(root,{childList:true,subtree:false});
  enhanceAdmin(root);
}

function enhanceAdmin(root){
  if($('#adminPrototypeSpec',root))return;
  const serviceGrid=$('.service-admin-grid',root);
  const knowledge=$('.knowledge-grid',root);
  const target=knowledge||serviceGrid;
  if(!target)return;
  const section=document.createElement('section');
  section.id='adminPrototypeSpec';
  section.className='prototype-spec panel';
  section.innerHTML=`<div class="panel-head"><div><h3>Estrutura do processo funcional</h3><p>Campos que o backend deverá persistir quando esta demo virar sistema real.</p></div><span class="prototype-ready">Blueprint</span></div><div class="prototype-flow">${[['person','Cliente','dados, contatos, preferências e consentimentos'],['inventory_2','Serviço','categoria, checklist, preço-base e responsável'],['route','Fluxo','etapa atual, próxima ação, prazo e dependências'],['folder','Documentos','arquivo, tipo, situação, conferência e acesso'],['payments','Financeiro','taxa oficial, terceiro, CNJP, pagamento e comprovante'],['confirmation_number','Protocolos','órgão, número, data, prazo e devolutiva'],['chat','Comunicação','WhatsApp, telefone, e-mail e histórico'],['history','Auditoria','quem alterou, quando e o que aconteceu']].map(([i,t,d])=>`<article>${icon(i)}<div><strong>${t}</strong><span>${d}</span></div></article>`).join('')}</div><div class="prototype-note"><strong>Regra central:</strong> atendimento digital, WhatsApp, telefone e balcão geram o mesmo objeto de processo. Só muda quem digitou os dados.</div>`;
  target.parentNode.insertBefore(section,target.nextSibling);
}
