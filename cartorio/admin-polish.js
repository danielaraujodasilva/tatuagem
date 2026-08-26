setInterval(()=>document.querySelectorAll('.case-row,.kanban-card').forEach(r=>r.querySelectorAll('button').forEach(b=>{if(b.textContent.trim()==='Editar')b.remove()})),300);
