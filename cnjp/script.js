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

  let threeState = null;

  const updateThreeScene = () => {
    if (!threeState || reducedMotion) return;

    const { renderer, camera, group, shell, core, ribbons, particles, hero } = threeState;
    const rect = hero.getBoundingClientRect();
    const progress = Math.min(1, Math.max(0, (window.innerHeight - rect.top) / Math.max(rect.height, 1)));
    const drift = Math.min(1, Math.max(0, (window.innerHeight * .6 - rect.top) / Math.max(rect.height, 1)));

    group.rotation.x = 0.35 + progress * 0.7;
    group.rotation.y = -0.45 + progress * 1.15;
    group.rotation.z = -0.08 + progress * 0.12;
    shell.rotation.z = progress * 0.7;
    core.rotation.x = progress * 1.05;
    core.rotation.y = progress * 1.4;
    ribbons.rotation.y = -progress * 1.35;
    particles.rotation.z = progress * 0.45;
    group.position.y = drift * -1.2;
    camera.position.z = 6.6 - progress * 1.1;
    camera.position.x = Math.sin(progress * Math.PI) * 0.16;
    camera.position.y = Math.cos(progress * Math.PI * .5) * 0.08;
    camera.lookAt(0, 0, 0);
    renderer.render(threeState.scene, camera);
  };

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

    const scene = doc.querySelector("[data-hero-scene]");
    if (scene && !reducedMotion) {
      const hero = scene.closest(".hero");
      const heroRect = hero.getBoundingClientRect();
      const viewportInfluence = Math.min(1, Math.max(0, (window.innerHeight - heroRect.top) / Math.max(heroRect.height, 1)));
      const leaveInfluence = Math.min(1, Math.max(0, heroRect.bottom / Math.max(window.innerHeight, 1)));
      const heroProgress = Math.max(viewportInfluence, 1 - leaveInfluence);
      scene.style.setProperty("--scroll", heroProgress.toFixed(3));
      scene.style.setProperty("--tilt", `${(-heroProgress * 16).toFixed(2)}deg`);
    }

    const process = doc.querySelector("[data-process]");
    if (process && !reducedMotion) {
      const rect = process.getBoundingClientRect();
      const progress = Math.min(1, Math.max(0, (window.innerHeight * .78 - rect.top) / Math.max(rect.height, 1)));
      process.style.setProperty("--progress", `${(progress * 100).toFixed(1)}%`);
    }

    updateThreeScene();
    ticking = false;
  };

  window.addEventListener("scroll", () => {
    if (!ticking) {
      window.requestAnimationFrame(updateScrollState);
      ticking = true;
    }
  }, { passive: true });
  window.setInterval(updateScrollState, 120);

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

  const canvas = doc.querySelector("[data-hero-canvas]");
  if (canvas && finePointer && !reducedMotion && typeof window.THREE !== "undefined") {
    const THREE = window.THREE;
    const hero = canvas.closest(".hero-scene");
    const scene3d = new THREE.Scene();
    scene3d.fog = new THREE.Fog(0x08110d, 8, 16);

    const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 60);
    camera.position.set(0, 0.15, 6.1);

    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: "high-performance" });
    renderer.setClearColor(0x000000, 0);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));

    const resize = () => {
      const width = hero.clientWidth;
      const height = hero.clientHeight;
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      renderer.render(scene3d, camera);
    };

    const glow = new THREE.Mesh(
      new THREE.SphereGeometry(1.55, 48, 48),
      new THREE.MeshBasicMaterial({ color: 0x7abf74, transparent: true, opacity: 0.11 })
    );
    glow.scale.set(1.25, .92, 1.25);
    scene3d.add(glow);

    const group = new THREE.Group();
    scene3d.add(group);

    const shell = new THREE.Mesh(
      new THREE.TorusKnotGeometry(1.15, .34, 240, 24),
      new THREE.MeshStandardMaterial({
        color: 0xc9f59b,
        metalness: .28,
        roughness: .42,
        emissive: 0x0b1a13,
        emissiveIntensity: .18
      })
    );
    group.add(shell);

    const core = new THREE.Mesh(
      new THREE.IcosahedronGeometry(.68, 1),
      new THREE.MeshStandardMaterial({
        color: 0xf2f7e4,
        metalness: .08,
        roughness: .18,
        emissive: 0x203525,
        emissiveIntensity: .22
      })
    );
    group.add(core);

    const ribbons = new THREE.Group();
    for (let i = 0; i < 3; i++) {
      const ring = new THREE.Mesh(
        new THREE.TorusGeometry(2.18 + i * .16, .035, 14, 180),
        new THREE.MeshStandardMaterial({ color: i === 1 ? 0xd9c07a : 0x7bb28a, metalness: .22, roughness: .25, transparent: true, opacity: .8 })
      );
      ring.rotation.x = i * 0.84;
      ring.rotation.y = i * 0.46;
      ribbons.add(ring);
    }
    group.add(ribbons);

    const particles = new THREE.Group();
    const particleMaterial = new THREE.MeshStandardMaterial({ color: 0xd6ff9d, metalness: .1, roughness: .75 });
    for (let i = 0; i < 42; i++) {
      const dot = new THREE.Mesh(new THREE.SphereGeometry(i % 3 === 0 ? .06 : .04, 12, 12), particleMaterial);
      const angle = (i / 42) * Math.PI * 2;
      const radius = 2.65 + (i % 5) * .11;
      dot.position.set(Math.cos(angle) * radius, Math.sin(angle * 1.7) * 1.1, Math.sin(angle) * radius * .36);
      particles.add(dot);
    }
    group.add(particles);

    const ambient = new THREE.AmbientLight(0xcde7c2, 1.6);
    scene3d.add(ambient);
    const key = new THREE.DirectionalLight(0xffffff, 2.4);
    key.position.set(3, 4, 5);
    scene3d.add(key);
    const fill = new THREE.DirectionalLight(0xa7d88b, 1.05);
    fill.position.set(-4, -1, 3);
    scene3d.add(fill);

    const rim = new THREE.PointLight(0x8ff0b0, 1.9, 18);
    rim.position.set(-2.5, 1.5, 3.8);
    scene3d.add(rim);

    threeState = { renderer, camera, group, shell, core, ribbons, particles, scene: scene3d, hero };
    resize();
    updateThreeScene();

    window.addEventListener("resize", resize, { passive: true });
    const animate = () => {
      window.requestAnimationFrame(animate);
      group.rotation.z += 0.0025;
      shell.rotation.y += 0.0035;
      core.rotation.z -= 0.002;
      particles.rotation.x += 0.0016;
      if (window.scrollY < 10) renderer.render(scene3d, camera);
    };
    animate();
  }

  const year = doc.getElementById("year");
  if (year) year.textContent = String(new Date().getFullYear());

  const versionBadge = doc.querySelector("[data-version-badge]");
  if (versionBadge) {
    const versionDate = versionBadge.getAttribute("data-version-date") || "";
    const versionCommit = versionBadge.getAttribute("data-version-commit") || "";
    versionBadge.innerHTML = `<strong>${versionDate}</strong> · ${versionCommit}`;
  }

  updateScrollState();
})();
