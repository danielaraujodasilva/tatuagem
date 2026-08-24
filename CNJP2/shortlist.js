import { SERVICES, LEGEND } from './services.js';
import { TECH_SERVICES } from './technology.js';

const BASE_METRICS={
'documentos-cartorios':{score:92,ease:95,revenue:70,recurring:70,complexity:25},
'conflitos-extrajudicial':{score:84,ease:78,revenue:80,recurring:72,complexity:45},
imobiliario:{score:94,ease:72,revenue:100,recurring:85,complexity:68},
'empresarial-administrativo':{score:93,ease:85,revenue:90,recurring:95,complexity:48},
'familia-sucessoes':{score:72,ease:50,revenue:92,recurring:45,complexity:82},
consumidor:{score:68,ease:82,revenue:55,recurring:35,complexity:35},b2b:{score:95,ease:72,revenue:100,recurring:100,complexity:65},educacao:{score:55,ease:70,revenue:75,recurring:80,complexity:60}};
const PRICE_RANGES={'documentos-cartorios':[80,450],'conflitos-extrajudicial':[180,1400],imobiliario:[250,3200],'empresarial-administrativo':[150,1500],'familia-sucessoes':[450,3800],consumidor:[100,700],b2b:[500,3500],educacao:[250,1800],'Sites & Presença Digital':[250,2200],'CRM & Sistemas Internos':[700,5500],'WhatsApp & Atendimento':[350,3000],'Automação de Processos':[350,4500],'IA Aplicada a Negócios':[600,5500],'Dados, Relatórios & Financeiro':[300,2600],'Infraestrutura Digital':[120,1600],'Pacotes de Transformação Digital':[900,6000]};
const PRICE_OVERRIDES={'Certidão de nascimento':100,'Certidão de casamento':120,'Certidão de óbito':100,'Certidão de protesto':120,'Assessoria para reconhecimento de firma':100,'Assessoria para autenticação':100,'Consulta de protestos':100,'Pesquisa de matrícula':150,'Matrícula atualizada':150,'Site institucional':1200,'Landing page de captação':700,'Página de serviço':450,'Catálogo digital':900,'Manutenção de site':300,'Hospedagem e gestão técnica':200,'Domínio, DNS e SSL':250,'E-mail profissional':250,'Acesso remoto seguro':350,'Diagnóstico de processos digitais':600,'Kit Presença Digital':1800,'Kit Atendimento':2800,'Kit Escritório Digital':4500,'Kit Automação Administrativa':3500};
const clamp=v=>Math.max(0,Math.min(100,Math.round(v)));
const round50=v=>Math.max(50,Math.round(v/50)*50);
const money=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0}).format(v);
function deriveMetrics(item,parent=null){if(item.metrics)return item.metrics;const p=parent??{score:75,ease:75,revenue:70,recurring:65,complexity:45};const tags=item.tags??[];let ease=0,complexity=0,score=0;if(tags.includes('green')){ease+=8;complexity-=8;score+=4}if(tags.includes('blue')){ease+=2;complexity+=2}if(tags.includes('yellow')){ease-=12;complexity+=15;score-=5}if(tags.includes('red')){ease-=6;complexity+=8;score-=3}if(tags.includes('purple')){ease+=4;score+=2}return{score:clamp(p.score+score),ease:clamp(p.ease+ease),revenue:clamp(p.revenue),recurring:clamp(p.recurring),complexity:clamp(p.complexity+complexity)}}
function estimatePrice(item,key,m){if(PRICE_OVERRIDES[item.title])return PRICE_OVERRIDES[item.title];const[min,max]=PRICE_RANGES[key]??[150,1800];const factor=clamp(m.revenue*.45+m.complexity*.35+m.score*.20)/100;return round50(min+(max-min)*factor)}
function flatten(items,parent=null,key=null,path=[],out=[]){for(const item of items){const metrics=deriveMetrics(item,parent),pricingKey=item.pricingKey??key,uid=[...path,item.title].join(' > ');if(item.children?.length){flatten(item.children,metrics,pricingKey,[...path,item.title],out)}else{const price=estimatePrice(item,pricingKey,metrics);out.push({...item,uid,metrics,price,priceLabel:`~ ${money(price)}`})}}return out}
const legal=SERVICES.map(item=>({...item,metrics:item.metrics??BASE_METRICS[item.id],pricingKey:item.id}));
const tech=TECH_SERVICES.children.map(item=>({...item,pricingKey:item.title}));
const ALL=flatten([{title:'CNJP — Soluções',children:[{title:'Jurídico & Extrajudicial',children:legal},{title:'Tecnologia',children:tech}]}]);
const byUid=new Map(ALL.map(i=>[i.uid,i]));
const bar=document.querySelector('#shortlistBar'),countEl=document.querySelector('#shortlistCount');
const viewBtn=document.querySelector('#viewSelected'),compareBtn=document.querySelector('#compareSelected'),copyBtn=document.querySelector('#copySelected'),clearBtn=document.querySelector('#clearSelected');
function getSelected(){try{return JSON.parse(localStorage.getItem('cnjp2-selected')||'[]')}catch{return[]}}
function update(){const n=getSelected().length;bar.hidden=n===0;countEl.textContent=`${n} ${n===1?'selecionado':'selecionados'}`}
function activateSelectedView(){const btn=document.querySelector('[data-mode="selected"]');if(btn&&!btn.classList.contains('active'))btn.click();document.querySelector('#catalog')?.scrollIntoView({behavior:'smooth',block:'start'})}
function itemText(item){const parts=item.uid.split(' > ');const area=parts[1]||'';const category=parts.slice(2,-1).join(' › ');const tags=(item.tags??[]).map(t=>LEGEND[t]?.label??t).join(', ');return[`📌 *${item.title}*`,`💼 *Área:* ${area}`,category?`🗂️ *Categoria:* ${category}`:null,`💰 *Valor estimado:* ${item.priceLabel}`,`⭐ *Prioridade:* ${item.metrics.score}/100`,`📊 *Indicadores:* Facilidade ${item.metrics.ease} | Receita ${item.metrics.revenue} | Recorrência ${item.metrics.recurring} | Complexidade ${item.metrics.complexity}`,tags?`🏷️ *Atuação:* ${tags}`:null,item.description?`📝 ${item.description}`:null].filter(Boolean).join('\n')}
async function copyText(text){if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(text);return}const ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();document.execCommand('copy');ta.remove()}
viewBtn?.addEventListener('click',activateSelectedView);
compareBtn?.addEventListener('click',()=>{activateSelectedView();const sort=document.querySelector('#sortBy');if(sort){sort.value='score';sort.dispatchEvent(new Event('change',{bubbles:true}))}document.querySelector('#applySort')?.click()});
copyBtn?.addEventListener('click',async()=>{const items=getSelected().map(uid=>byUid.get(uid)).filter(Boolean);if(!items.length)return;const text=['📋 *SERVIÇOS SELECIONADOS PARA DISCUSSÃO*','',...items.flatMap((item,i)=>[`${i+1}. ${itemText(item)}`,''])].join('\n').trim();const original=copyBtn.innerHTML;try{await copyText(text);copyBtn.classList.add('copied');copyBtn.innerHTML='<span class="material-symbols-rounded">check</span><span>Copiado!</span>';setTimeout(()=>{copyBtn.classList.remove('copied');copyBtn.innerHTML=original},1500)}catch{copyBtn.innerHTML='<span class="material-symbols-rounded">error</span><span>Falhou</span>';setTimeout(()=>copyBtn.innerHTML=original,1600)}});
clearBtn?.addEventListener('click',()=>{localStorage.setItem('cnjp2-selected','[]');location.reload()});
new MutationObserver(update).observe(document.querySelector('#summary'),{childList:true,subtree:true,characterData:true});
update();
