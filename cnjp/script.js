(() => {
  "use strict";

  const doc = document;
  const root = doc.documentElement;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)").matches;

  const pushDataLayer = (payload) => {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(payload);
  };

  doc.querySelectorAll(".js-whatsapp-cta").forEach((cta) => {
    cta.addEventListener("click", () => {
      const ctaId = cta.id || "";
      const ctaText = (cta.innerText || cta.textContent || "").trim();

      pushDataLayer({
        event: "click_whatsapp",
        lead_type: "acordo_extrajudicial",
        page_type: "landing_page",
        page_name: "cnjp_acordo",
        cta_id: ctaId,
        cta_text: ctaText
      });

      if (typeof window.gtag === "function") {
        window.gtag("event", "click_whatsapp", {
          event_category: "lead",
          event_label: "acordo_extrajudicial",
          value: 1
        });
      }

      if (typeof window.fbq === "function") {
        window.fbq("trackCustom", "ClickWhatsAppCNJP", {
          service: "acordo_extrajudicial",
          cta_id: ctaId
        });
      }
    });
  });

  const header = doc.querySelector("[data-header]");
  const menuButton = doc.querySelector(".menu-toggle");

  if (header && menuButton) {
    const closeMenu = () => {
      header.classList.remove("open");
      menuButton.setAttribute("aria-expanded", "false");
      menuButton.setAttribute("aria-label", "Abrir menu");
    };

    menuButton.addEventListener("click", () => {
      const isOpen = header.classList.toggle("open");
      menuButton.setAttribute("aria-expanded", String(isOpen));
      menuButton.setAttribute("aria-label", isOpen ? "Fechar menu" : "Abrir menu");
    });

    header.querySelectorAll("nav a").forEach((link) => link.addEventListener("click", closeMenu));
    doc.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeMenu();
    });
  }

  doc.querySelectorAll(".faq-list details").forEach((item) => {
    item.addEventListener("toggle", () => {
      if (!item.open) return;

      doc.querySelectorAll(".faq-list details").forEach((other) => {
        if (other !== item) other.open = false;
      });

      const question = item.querySelector("summary")?.childNodes[0]?.textContent?.trim() || "";
      pushDataLayer({
        event: "faq_open",
        faq_question: question,
        page_name: "cnjp_acordo"
      });
    });
  });

  const scrollThresholds = new Set(["50", "90"]);
  let ticking = false;

  const updateScrollState = () => {
    const scrollable = doc.documentElement.scrollHeight - window.innerHeight;
    const depth = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 100;

    ["50", "90"].forEach((threshold) => {
      if (depth >= Number(threshold) && scrollThresholds.has(threshold)) {
        scrollThresholds.delete(threshold);
        pushDataLayer({
          event: "lp_scroll_depth",
          scroll_depth: threshold,
          page_name: "cnjp_acordo"
        });
      }
    });

    const floatingCta = doc.getElementById("cta-floating-whatsapp");
    if (floatingCta) floatingCta.classList.toggle("is-visible", window.scrollY > 180);

    const scene = doc.querySelector("[data-hero-scene]");
    if (scene && !reducedMotion) {
      const hero = scene.closest(".hero");
      const heroProgress = Math.min(1, Math.max(0, -hero.getBoundingClientRect().top / Math.max(hero.offsetHeight, 1)));
      scene.style.setProperty("--scroll", heroProgress.toFixed(3));
    }

    const process = doc.querySelector("[data-process]");
    if (process && !reducedMotion) {
      const rect = process.getBoundingClientRect();
      const progress = Math.min(1, Math.max(0, (window.innerHeight * .78 - rect.top) / Math.max(rect.height, 1)));
      process.style.setProperty("--progress", `${(progress * 100).toFixed(1)}%`);
    }

    ticking = false;
  };

  window.addEventListener("scroll", () => {
    if (!ticking) {
      window.requestAnimationFrame(updateScrollState);
      ticking = true;
    }
  }, { passive: true });

  if (!reducedMotion && "IntersectionObserver" in window) {
    root.classList.add("motion-ready");
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: .08 });

    doc.querySelectorAll(".reveal").forEach((element) => revealObserver.observe(element));
  }

  const scene = doc.querySelector("[data-hero-scene]");
  if (scene && finePointer && !reducedMotion) {
    scene.addEventListener("pointermove", (event) => {
      const rect = scene.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width - .5;
      const y = (event.clientY - rect.top) / rect.height - .5;
      scene.style.setProperty("--ry", `${(x * 12).toFixed(2)}deg`);
      scene.style.setProperty("--rx", `${(y * -10).toFixed(2)}deg`);
    }, { passive: true });

    scene.addEventListener("pointerleave", () => {
      scene.style.setProperty("--ry", "-8deg");
      scene.style.setProperty("--rx", "-5deg");
    });
  }

  const year = doc.getElementById("year");
  if (year) year.textContent = String(new Date().getFullYear());

  updateScrollState();
})();
