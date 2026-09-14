// Capture da origem: guarda os UTMs do primeiro acesso e sobrevive à navegação.
// Também registra a página de entrada e o referrer externo.
(() => {
  const KEY = 'cnjp_origem';
  const CAMPOS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'];

  function capturar() {
    try {
      const qs = new URLSearchParams(location.search);
      const achou = CAMPOS.some(c => (qs.get(c) || '').trim() !== '');
      const externo = document.referrer && !document.referrer.includes(location.host) ? document.referrer : '';
      const atual = JSON.parse(sessionStorage.getItem(KEY) || 'null');

      // Primeira visita com UTM ganha; sem UTM, guarda entrada/referrer uma vez.
      if (achou && (!atual || !atual.tem_utm)) {
        const dados = { tem_utm: true, landing: location.pathname + location.search, referrer: externo };
        CAMPOS.forEach(c => { const v = (qs.get(c) || '').trim(); if (v) dados[c] = v.slice(0, 120); });
        sessionStorage.setItem(KEY, JSON.stringify(dados));
      } else if (!atual) {
        sessionStorage.setItem(KEY, JSON.stringify({ tem_utm: false, landing: location.pathname + location.search, referrer: externo }));
      }
    } catch (e) { /* nunca quebra a página por causa disso */ }
  }

  capturar();

  // expõe para o formulário ler no momento do envio
  window.cnjpOrigem = () => {
    try { return JSON.parse(sessionStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; }
  };
})();
