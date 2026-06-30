(() => {
  "use strict";

  const message = "Olá, vim pelo Google e quero entender se meu caso pode ser resolvido por acordo extrajudicial.";
  const whatsappUrl = `https://wa.me/5511947287318?text=${encodeURIComponent(message)}`;

  document.querySelectorAll(".whatsapp-link").forEach((link) => {
    link.href = whatsappUrl;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.addEventListener("click", () => {
      if (typeof window.gtag === "function") {
        window.gtag("event", "click_whatsapp", {
          event_category: "lead",
          event_label: "acordo_extrajudicial",
          cta_location: link.dataset.location || "indefinido"
        });
      }
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({
        event: "click_whatsapp",
        lead_type: "acordo_extrajudicial",
        source_page: "lp_acordo",
        cta_location: link.dataset.location || "indefinido"
      });
    });
  });

  document.getElementById("year").textContent = new Date().getFullYear();

  const header = document.querySelector(".site-header");
  const menuButton = document.querySelector(".menu-toggle");
  menuButton.addEventListener("click", () => {
    const isOpen = header.classList.toggle("open");
    menuButton.setAttribute("aria-expanded", String(isOpen));
    menuButton.setAttribute("aria-label", isOpen ? "Fechar menu" : "Abrir menu");
  });
  header.querySelectorAll("nav a").forEach((link) => link.addEventListener("click", () => {
    header.classList.remove("open");
    menuButton.setAttribute("aria-expanded", "false");
  }));

  document.querySelectorAll(".faq-list details").forEach((item) => {
    item.addEventListener("toggle", () => {
      if (!item.open) return;
      document.querySelectorAll(".faq-list details").forEach((other) => {
        if (other !== item) other.open = false;
      });
    });
  });

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!reducedMotion && window.gsap && window.ScrollTrigger) {
    gsap.registerPlugin(ScrollTrigger);
    gsap.utils.toArray(".reveal").forEach((element) => {
      gsap.from(element, {
        opacity: 0,
        y: 38,
        duration: 0.85,
        ease: "power3.out",
        scrollTrigger: { trigger: element, start: "top 88%", once: true }
      });
    });
    gsap.to(".document", {
      yPercent: -8,
      rotation: 2,
      ease: "none",
      scrollTrigger: { trigger: ".hero", start: "top top", end: "bottom top", scrub: 1 }
    });
    gsap.to(".orbit-two", {
      rotation: 80,
      ease: "none",
      scrollTrigger: { trigger: ".hero", start: "top top", end: "bottom top", scrub: 1 }
    });
  } else {
    document.querySelectorAll(".reveal").forEach((element) => {
      element.style.opacity = "1";
      element.style.transform = "none";
    });
  }
})();