(()=>{
  const style=document.createElement('style');
  style.textContent='.ticket-table-wrap td[data-label="Ações"]{display:flex!important;align-items:center;justify-content:flex-end;gap:6px}.ticket-table-wrap td[data-label="Ações"] .ticket-action{position:static!important;transform:none!important;margin:0!important;width:30px!important;height:30px!important;font-size:16px!important}.ticket-table-wrap td[data-label="Ações"] .ticket-urgency{position:static!important;transform:none!important;margin:0!important}';
  document.head.append(style);
  const fix=()=>document.querySelectorAll('.ticket-table-wrap tr.case-row').forEach(row=>{
    const actions=row.querySelector('td[data-label="Ações"]');
    if(!actions)return;
    row.querySelectorAll(':scope > .ticket-action, :scope > .ticket-urgency').forEach(button=>{
      if(button.parentElement!==actions)actions.append(button);
    });
  });
  new MutationObserver(fix).observe(document.body,{childList:true,subtree:true});
  fix();
})();
