/* Aviso de cookies e consentimento (LGPD) — CNJP Cartório Digital.
   Fica DORMENTE enquanto não houver rastreador configurado em window.CNJP_TRACKING.
   Ao configurar Pixel/GA4/Google Ads, o banner aparece, o visitante decide, e a escolha
   é guardada em localStorage. Scripts de medição devem escutar o evento 'cnjp:consent'. */
(() => {
  const KEY = 'cnjp-consent';
  const cfg = window.CNJP_TRACKING || {};
  const hasTracking = !!(cfg.metaPixel || cfg.ga4 || cfg.googleAds);

  const read = () => { try { return localStorage.getItem(KEY); } catch (err) { return null; } };
  const save = (v) => { try { localStorage.setItem(KEY, v); } catch (err) {} };

  const fire = (granted) => {
    document.dispatchEvent(new CustomEvent('cnjp:consent', { detail: { granted } }));
  };

  const choice = read();
  if (choice === 'granted') { fire(true); return; }
  if (choice === 'denied') { return; }

  // Sem rastreador instalado não há o que consentir: não exibimos o aviso.
  if (!hasTracking) return;

  const style = document.createElement('style');
  style.textContent = `
    .cnjp-consent{position:fixed;left:0;right:0;bottom:0;z-index:80;background:#101828;color:#dbe4ee;
      padding:16px 18px;display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;
      box-shadow:0 -6px 20px rgba(16,24,40,.22);font:400 .82rem/1.5 Inter,system-ui,sans-serif}
    .cnjp-consent p{margin:0;max-width:640px}
    .cnjp-consent a{color:#5eead4}
    .cnjp-consent .actions{display:flex;gap:9px;flex:0 0 auto}
    .cnjp-consent button{border:none;border-radius:10px;padding:10px 18px;font-weight:700;font-size:.8rem;cursor:pointer}
    .cnjp-consent .accept{background:#0f766e;color:#fff}
    .cnjp-consent .reject{background:transparent;color:#cbd5e1;border:1px solid #3a4759}
    @media(max-width:600px){.cnjp-consent{padding:14px}.cnjp-consent .actions{width:100%}.cnjp-consent button{flex:1}}
  `;
  document.head.appendChild(style);

  const bar = document.createElement('div');
  bar.className = 'cnjp-consent';
  bar.setAttribute('role', 'dialog');
  bar.setAttribute('aria-label', 'Aviso de cookies');
  bar.innerHTML = `
    <p>Usamos cookies essenciais para o funcionamento do portal e, com o seu aceite, medição para entender como o site é usado. Você pode recusar a medição. Detalhes na <a href="./politica-de-privacidade.php">Política de Privacidade</a>.</p>
    <div class="actions">
      <button type="button" class="reject">Recusar medição</button>
      <button type="button" class="accept">Aceitar</button>
    </div>`;
  document.body.appendChild(bar);

  bar.querySelector('.accept').addEventListener('click', () => { save('granted'); bar.remove(); fire(true); });
  bar.querySelector('.reject').addEventListener('click', () => { save('denied'); bar.remove(); fire(false); });
})();