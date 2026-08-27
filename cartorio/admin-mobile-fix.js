(()=>{
  const style=document.createElement('style');
  style.textContent='.ticket-table-wrap{width:100%!important;max-width:100%!important;overflow:visible!important}.ticket-table-wrap td[data-label="Ações"]{display:flex!important;align-items:center;justify-content:flex-end;gap:6px}.ticket-table-wrap td[data-label="Ações"] .ticket-action{position:static!important;transform:none!important;margin:0!important;width:30px!important;height:30px!important;font-size:16px!important;display:inline-grid!important;visibility:visible!important;opacity:1!important}.ticket-table-wrap td[data-label="Ações"] .ticket-urgency{position:static!important;transform:none!important;margin:0!important}@media(max-width:700px){html,body{max-width:100%;overflow-x:hidden!important}.ticket-table-wrap table,.ticket-table-wrap tbody{display:block!important;width:100%!important;min-width:0!important}.ticket-table-wrap thead{display:none!important}.ticket-table-wrap tr.case-row{display:block!important;width:100%!important;min-width:0!important;margin:10px 0!important;padding:10px!important;border:1px solid #eaecf0!important;border-radius:12px!important}.ticket-table-wrap tr.case-row td{display:flex!important;width:100%!important;justify-content:space-between!important;align-items:center!important;gap:14px!important;white-space:normal!important;padding:7px 4px!important;border:0!important}.ticket-table-wrap tr.case-row td:before{display:block!important;content:attr(data-label)!important;font-weight:700!important;color:#667085!important}.ticket-table-wrap tr.case-row td[data-label="Ações"]{justify-content:flex-end!important}}';
  document.head.append(style);
  const fix=()=>document.querySelectorAll('.ticket-table-wrap tr.case-row').forEach(row=>{
    const actions=row.querySelector('td[data-label="Ações"]');
    if(!actions)return;
    row.querySelectorAll('.ticket-action,.ticket-urgency').forEach(button=>{
      if(button.parentElement!==actions)actions.append(button);
    });
  });
  new MutationObserver(fix).observe(document.body,{childList:true,subtree:true});
  document.addEventListener('click',event=>{const cell=event.target.closest('.ticket-table-wrap td[data-label="Ações"]');if(!cell||event.target.closest('.ticket-action'))return;cell.querySelector('.ticket-action')?.click()},true);
  fix();
})();
