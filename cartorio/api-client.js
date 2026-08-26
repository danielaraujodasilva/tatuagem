document.addEventListener('submit',async event=>{
  const form=event.target;
  if(!['clientFlow','enhancedLead'].includes(form.id)) return;
  event.preventDefault(); event.stopImmediatePropagation();
  const fields=new FormData(form), inputs=[...form.querySelectorAll('input,textarea,select')], service=form.querySelector('[data-service]')?.dataset.service||document.querySelector('[data-service]')?.dataset.service||'outros';
  const payload={name:fields.get('name')||inputs[0]?.value||'',phone:fields.get('phone')||inputs[1]?.value||'',service,description:fields.get('description')||inputs.find(x=>x.tagName==='TEXTAREA')?.value||'',channel:form.id==='enhancedLead'?'Site':'Triagem digital'};
  try { const r=await fetch('./api.php?action=request',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const d=await r.json(); if(!r.ok) throw Error(d.message); form.innerHTML=`<div class="flow-inner"><span class="kicker">Pedido criado</span><h2>Recebemos sua solicitação.</h2><p>Guarde este número para acompanhar o andamento.</p><div class="track-summary"><div><span>Pedido</span><strong>${d.code}</strong></div><div><span>Status</span><strong>Recebido</strong></div></div></div>`; }
  catch(error){ const toast=document.querySelector('#toast'); if(toast){toast.textContent=error.message;toast.classList.add('show');} }
},true);
