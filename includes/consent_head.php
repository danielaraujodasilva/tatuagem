<?php
/**
 * Consent Mode v2 + Meta Pixel condicionado ao consentimento.
 * Incluir ANTES do GTM/gtag, no <head>.
 *
 * A escolha fica em localStorage (dt_consent_v1). O banner em
 * includes/consent_banner.php permite aceitar ou continuar só com o essencial.
 */
?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
(function(){
  var KEY = 'dt_consent_v1';
  function read(){
    try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; }
  }
  function state(granted){
    return {
      ad_storage: granted ? 'granted' : 'denied',
      ad_user_data: granted ? 'granted' : 'denied',
      ad_personalization: granted ? 'granted' : 'denied',
      analytics_storage: granted ? 'granted' : 'denied'
    };
  }
  var stored = read();
  var granted = !!(stored && stored.marketing === true);
  var defaults = state(granted);
  defaults.functionality_storage = 'granted';
  defaults.security_storage = 'granted';
  defaults.wait_for_update = 500;
  gtag('consent', 'default', defaults);
  if (stored) { gtag('consent', 'update', state(granted)); }

  window.dtInitMeta = function(){
    if (window.__dtMetaInit) { return; }
    window.__dtMetaInit = true;
    !function(f,b,e,v,n,t,s){
      if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];
      t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s);
    }(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '734722824598560');
    fbq('track', 'PageView');
  };

  window.dtConsent = {
    get: read,
    set: function(granted){
      var data = {marketing: !!granted, ts: Date.now()};
      try { localStorage.setItem(KEY, JSON.stringify(data)); } catch (e) {}
      gtag('consent', 'update', state(!!granted));
      try { document.dispatchEvent(new CustomEvent('dt-consent', {detail: data})); } catch (e) {}
      if (granted) { window.dtInitMeta(); }
      var box = document.getElementById('dtConsent');
      if (box) { box.hidden = true; }
    },
    open: function(){
      var box = document.getElementById('dtConsent');
      if (box) { box.hidden = false; }
    }
  };

  if (granted) { window.dtInitMeta(); }
})();
</script>
