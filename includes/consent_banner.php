<div id="dtConsent" class="dt-consent" role="dialog" aria-live="polite" aria-label="Preferências de cookies" hidden>
  <div class="dt-consent-inner">
    <p><strong>Cookies e medição.</strong> Usamos cookies para medir o site e os anúncios e melhorar o atendimento. Você pode aceitar ou continuar só com o essencial. <a href="/privacy.html">Política de Privacidade</a>.</p>
    <div class="dt-consent-actions">
      <button type="button" id="dtConsentReject">Só essenciais</button>
      <button type="button" id="dtConsentAccept">Aceitar</button>
    </div>
  </div>
</div>
<style>
  .dt-consent{position:fixed;left:0;right:0;bottom:0;z-index:200;background:rgba(6,5,4,.98);border-top:1px solid rgba(226,184,113,.38);color:#f8f3ea;padding:16px;box-shadow:0 -14px 50px rgba(0,0,0,.55);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  .dt-consent[hidden]{display:none !important}
  .dt-consent-inner{width:min(1120px,100%);margin:auto;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}
  .dt-consent p{margin:0;max-width:760px;color:#ddd6ca;font-size:13px;line-height:1.55}
  .dt-consent p strong{color:#f8f3ea}
  .dt-consent a{color:#e2b871;text-decoration:underline}
  .dt-consent-actions{display:flex;gap:10px;flex-wrap:wrap}
  .dt-consent button{min-height:44px;padding:10px 18px;border-radius:8px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;cursor:pointer;transition:.2s}
  #dtConsentReject{background:transparent;border:1px solid rgba(226,184,113,.45);color:#e2b871}
  #dtConsentReject:hover{background:rgba(226,184,113,.12)}
  #dtConsentAccept{background:linear-gradient(135deg,#e2b871,#c9964a);border:1px solid transparent;color:#111}
  #dtConsentAccept:hover{filter:brightness(1.05)}
  @media(max-width:600px){
    .dt-consent{padding:14px}
    .dt-consent-actions{width:100%}
    .dt-consent-actions button{flex:1}
  }
</style>
<script>
(function(){
  function ready(fn){ if(document.readyState !== 'loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function(){
    var box = document.getElementById('dtConsent');
    if (!box) { return; }
    var stored = window.dtConsent && window.dtConsent.get ? window.dtConsent.get() : null;
    if (!stored) { box.hidden = false; }
    var accept = document.getElementById('dtConsentAccept');
    var reject = document.getElementById('dtConsentReject');
    if (accept) { accept.addEventListener('click', function(){ if(window.dtConsent){ window.dtConsent.set(true); } box.hidden = true; }); }
    if (reject) { reject.addEventListener('click', function(){ if(window.dtConsent){ window.dtConsent.set(false); } box.hidden = true; }); }
  });
})();
</script>
