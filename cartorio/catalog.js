import { SERVICES, CATEGORIES } from './data.js';

const $=(s,r=document)=>r.querySelector(s);
const $$=(s,r=document)=>[...r.querySelectorAll(s)];
const money=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0}).format(v);
const normalize=(v='')=>v.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const categoryBy=id=>CATEGORIES.find(c=>c.id===id);

if(document.body.dataset.page==='client') initClientCatalog();
if(document.body.dataset.page==='admin') initAdminCatalog();

function initClientCatalog(){
  const grid=$('#serviceGrid'),filters=$('#categoryFilters'),search=$('#serviceSearch'),count=$('#serviceCount'),label=$('#activeCategoryLabel'),empty=$('#serviceEmpty');
  if(!grid||!filters)return;
  let category='all',query='';

  filters.innerHTML=[`<button class="category-chip active" data-category="all"><span class="material-symbols-rounded">apps</span>Todos <b>${SERVICES.length}</b></button>`,...CATEGORIES.map(c=>`<button class="category-chip" data-category="${c.id}"><span class="material-symbols-rounded">${c.icon}</span>${c.title} <b>${SERVICES.filter(s=>s.category===c.id).length}</b></button>`)].join('');

  const render=()=>{
    const q=normalize(query);
    const items=SERVICES.filter(s=>(category==='all'||s.category===category)&&(!q||normalize(`${s.title} ${s.desc} ${s.channel} ${categoryBy(s.category)?.title||''}`).includes(q)));
    grid.innerHTML=items.map(s=>{
      const cat=categoryBy(s.category);
      const badge=s.kind==='camara'?'Câmara arbitral':s.kind==='profissional'?'Atuação coordenada':'Atendimento assistido';
      return `<article class="service-card catalog-service-card" data-service="${s.id}"><div class="service-card-top"><span class="service-icon material-symbols-rounded">${s.icon}</span><span class="catalog-kind ${s.kind||''}">${badge}</span></div><span class="service-category">${cat?.title||'Serviço'}</span><strong>${s.title}</strong><p>${s.desc}</p><div class="service-meta"><span>${s.from?`a partir de ${money(s.from)}`:'triagem gratuita'}</span><b>Começar →</b></div></article>`
    }).join('');
    count.textContent=`${items.length} ${items.length===1?'serviço':'serviços'}`;
    label.textContent=category==='all'?'Todos os assuntos':categoryBy(category)?.title||'';
    empty.hidden=items.length>0;
  };

  filters.addEventListener('click',e=>{const b=e.target.closest('[data-category]');if(!b)return;category=b.dataset.category;$$('[data-category]',filters).forEach(x=>x.classList.toggle('active',x===b));render()});
  search?.addEventListener('input',e=>{query=e.target.value.trim();render()});
  render();
}

function initAdminCatalog(){
  const root=$('#adminContent');
  if(!root)return;
  const enhance=()=>{
    const cards=$$('.admin-service-card',root);
    if(!cards.length||$('.admin-catalog-toolbar',root))return;
    cards.forEach((card,i)=>{const s=SERVICES[i];if(s)card.dataset.category=s.category});
    const toolbar=document.createElement('div');
    toolbar.className='admin-catalog-toolbar';
    toolbar.innerHTML=`<label><span class="material-symbols-rounded">search</span><input id="adminServiceSearch" type="search" placeholder="Buscar serviço no catálogo…"></label><select id="adminCategory"><option value="all">Todas as categorias (${SERVICES.length})</option>${CATEGORIES.map(c=>`<option value="${c.id}">${c.title} (${SERVICES.filter(s=>s.category===c.id).length})</option>`).join('')}</select><span id="adminCatalogCount">${SERVICES.length} serviços</span>`;
    root.prepend(toolbar);
    const input=$('#adminServiceSearch',toolbar),select=$('#adminCategory',toolbar),counter=$('#adminCatalogCount',toolbar);
    const apply=()=>{const q=normalize(input.value),cat=select.value;let visible=0;cards.forEach((card,i)=>{const s=SERVICES[i];const show=s&&(cat==='all'||s.category===cat)&&(!q||normalize(`${s.title} ${s.desc} ${s.channel}`).includes(q));card.hidden=!show;if(show)visible++});counter.textContent=`${visible} ${visible===1?'serviço':'serviços'}`};
    input.addEventListener('input',apply);select.addEventListener('change',apply);
  };
  new MutationObserver(enhance).observe(root,{childList:true,subtree:true});
  enhance();
}
