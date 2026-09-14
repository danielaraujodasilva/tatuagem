import { SERVICES as STATIC_SERVICES, CATEGORIES, CASES, STATUS } from './data.js?v=20260826-5';

const $=(s,r=document)=>r.querySelector(s);
const $$=(s,r=document)=>[...r.querySelectorAll(s)];
const money=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(v);
const icon=n=>`<span class="material-symbols-rounded">${n}</span>`;
let SERVICES=[...STATIC_SERVICES];
const serviceBy=id=>SERVICES.find(s=>s.id===id);
const categoryBy=id=>CATEGORIES.find(c=>c.id===id);

if(document.body.dataset.page==='client') initClientEnhancements();
if(document.body.dataset.page==='admin') initAdminEnhancements();

function initClientEnhancements(){
  const modal=$('#flowModal'),content=$('#flowContent');
  if(!modal||!content)return;
  fetch('./api.php?action=services_public').then(r=>r.json()).then(d=>{if(!d.ok||!Array.isArray(d.services)||!d.services.length)return;SERVICES=d.services.map(s=>({...s,desc:s.desc||s.description||'',docs:Array.isArray(s.docs)?s.docs:(JSON.parse(s.docs_json||'[]')||[]),steps:Array.isArray(s.steps)?s.steps:(JSON.parse(s.steps_json||'[]')||[])}));document.dispatchEvent(new CustomEvent('cartorio:catalog-loaded'))}).catch(()=>{});
  const open=html=>{content.innerHTML=html;if(!modal.open)modal.showModal()};
  const close=()=>{if(modal.open)modal.close()};

  document.addEventListener('click',e=>{
    const card=e.target.closest('[data-service][data-service]:not(dialog)');
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
  open(`<div class="flow-inner service-detail"><div class="service-detail-head">${icon(s.icon)}<div><span class="kicker">${ctx.cat}</span><h2>${s.title}</h2><p>${s.desc}</p></div></div><div class="service-detail-grid"><section><h3>Quando este serviço ajuda</h3><p>Quando a demanda se encaixa em <strong>${s.title.toLowerCase()}</strong> e o cliente quer evitar descobrir sozinho formulários, documentos, canais, protocolos e próximos passos.</p></section><section><h3>Quem faz o quê</h3><p>${ctx.limits}</p></section></div><section class="case-section"><h3>Para começar, normalmente precisamos de</h3>${s.docs.map(d=>`<div class="doc-line"><span>${d}</span><span class="ok">vamos conferir</span></div>`).join('')}</section><section class="case-section"><h3>Como o processo anda</h3><div class="service-stepper">${s.steps.map((st,i)=>`<div><b>${i+1}</b><span>${st}</span></div>`).join('')}</div></section><section class="quote-preview"><div class="quote-head"><div><span class="kicker">Estimativa</span><h3>Veja como o orçamento é apresentado</h3></div><span>valores estimados</span></div><div class="quote-lines"><div><span>Taxas / emolumentos oficiais estimados</span><strong>${money(est.official)}</strong></div>${est.third?`<div><span>Terceiros / profissional estimado</span><strong>${money(est.third)}</strong></div>`:''}<div><span>Serviço CNJP</span><strong>${money(est.fee)}</strong></div><div class="quote-total"><span>Total estimado</span><strong>${money(est.total)}</strong></div></div><p>O valor real dependerá do caso, da serventia, dos documentos e de eventuais profissionais necessários.</p></section><div class="flow-actions service-detail-actions"><button class="btn secondary" type="button" data-service-human>Falar com uma pessoa</button><button class="btn primary" type="button" data-request-quote>Continuar e pedir orçamento</button></div></div>`);
  $('[data-service-human]',content)?.addEventListener('click',()=>showLeadForm(s,est,open,close,'humano'));
  $('[data-request-quote]',content)?.addEventListener('click',()=>showLeadForm(s,est,open,close,'digital'));
}

function showLeadForm(s,est,open,close,mode){
  const content=$('#flowContent');
  const modal=content?.closest('dialog')||document.querySelector('#flowModal');
  if(modal){modal.dataset.serviceId=s.id||'';modal.dataset.serviceTitle=s.title||'';}
  open(`<div class="flow-inner"><span class="kicker">${mode==='humano'?'Atendimento assistido':'Pré-orçamento'}</span><h2>${s.title}</h2><p>${mode==='humano'?'Deixe um contato e uma pessoa continua a conversa.':'Preencha o básico. O pedido só vira processo depois da conferência e aprovação do orçamento.'}</p><form id="enhancedLead" class="flow-form"><div class="field"><label>Nome</label><input name="name" required placeholder="Seu nome"></div><div class="field"><label>WhatsApp ou telefone</label><input name="phone" required placeholder="(11) 99999-9999"></div><div class="field"><label>E-mail (opcional)</label><input name="email" type="email" placeholder="voce@email.com - recebe o código do pedido"></div><div class="field"><label>Conte o caso</label><textarea placeholder="Explique o que aconteceu e o que você já tem em mãos."></textarea></div><div class="field"><label>Como prefere ser atendido?</label><select><option>${mode==='humano'?'Quero falar com uma pessoa':'Quero continuar online'}</option><option>WhatsApp</option><option>Telefone</option><option>Presencial</option></select></div><section class="quote-mini"><span>Estimativa demonstrativa</span><strong>${money(est.total)}</strong><small>Taxas, terceiros e serviço aparecem separados antes da aprovação.</small></section><div class="flow-actions"><button class="btn primary" type="submit">Gerar pré-pedido</button></div></form></div>`);
}

async function showEnhancedTracking(id,open,close){
  const content=$('#flowContent');let c=CASES.find(x=>x.id===id)||CASES[1];try{const r=await fetch('./api.php?action=track&code='+encodeURIComponent(id));const d=await r.json();if(d.ok)c={...c,id:d.request.code,client:d.request.client,title:d.request.service,channel:d.request.channel,status:d.request.status,amount:Number(d.request.amount),description:d.request.description}}catch(e){};
  const st=STATUS[c.status]||{label:c.status};
  const pending=c.docs.filter(([,s])=>s!=='ok');
  const next=c.status==='exigencia'?'Enviar o documento/correção exigida':c.status==='protocolado'?'Aguardar análise da serventia':c.status==='aguardando'?'Aguardar retorno do terceiro':c.status==='entregue'?'Nenhuma. Processo concluído':'Concluir checklist e conferência';
  open(`<div class="flow-inner portal-order"><span class="kicker">Portal do pedido</span><h2>${c.id} • ${c.title}</h2><p>${c.client} • Atendimento: ${c.channel}</p><div class="portal-status"><div><span>Status atual</span><strong>${st.label}</strong></div><div><span>Próxima ação</span><strong>${next}</strong></div></div><div class="track-summary portal-summary"><div><span>Responsável CNJP</span><strong>${c.owner}</strong></div><div><span>Prazo estimado</span><strong>${c.deadline}</strong></div><div><span>Taxas oficiais</span><strong>${money(c.official)}</strong></div><div><span>Serviço CNJP</span><strong>${money(c.fee)}</strong></div></div><div class="portal-columns"><section class="case-section"><h3>Documentos</h3>${c.docs.map(([d,s])=>`<div class="doc-line"><span>${d}</span><span class="${s}">${s==='ok'?'✓ conferido':s==='missing'?'faltando':'aguardando'}</span></div>`).join('')}</section><section class="case-section"><h3>Pendências</h3>${pending.length?pending.map(([d])=>`<div class="pending-line">${icon('error')}<span>${d}</span></div>`).join(''):'<div class="all-good">✓ Nada pendente com você agora.</div>'}</section></div><section class="case-section"><h3>Linha do tempo</h3><div class="timeline">${c.timeline.map((t,i)=>`<div class="${i<c.timeline.length-1?'done':'active'}">${t}</div>`).join('')}</div></section><section class="portal-files"><div><span class="material-symbols-rounded">folder</span><div><strong>Arquivos do pedido</strong><small>Certidões, recibos e comprovantes aparecem aqui.</small></div></div><button class="btn secondary small" type="button">Ver arquivos</button></section><div class="flow-actions"><button class="btn secondary" type="button" data-portal-close>Fechar</button><button class="btn primary" type="button">Falar com o atendimento</button></div></div>`);
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
