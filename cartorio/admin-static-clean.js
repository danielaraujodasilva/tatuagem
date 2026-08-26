setTimeout(()=>{document.querySelectorAll('.panel').forEach(p=>{const h=p.querySelector('h3');if(h&&h.textContent.includes('Fila de atenção'))p.remove()})},1000);
