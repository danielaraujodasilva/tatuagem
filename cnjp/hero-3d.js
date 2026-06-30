(() => {
  "use strict";

  const hero = document.getElementById("hero-3d");
  const canvas = hero?.querySelector("[data-hero-canvas]");
  if (!hero || !canvas) return;

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const mobileCoarse = window.matchMedia("(hover: none), (pointer: coarse)").matches;
  const ctx = canvas.getContext("2d", { alpha: true, desynchronized: true });
  if (!ctx) {
    console.info("CNJP 3D fallback active");
    return;
  }

  console.info("CNJP 3D hero initialized");

  const state = {
    w: 0,
    h: 0,
    dpr: Math.min(window.devicePixelRatio || 1, 1.8),
    scroll: 0,
    targetScroll: 0,
    mouseX: 0,
    mouseY: 0,
    targetMouseX: 0,
    targetMouseY: 0,
    time: 0
  };

  const palette = {
    bgA: "#07100d",
    bgB: "#0b1814",
    panel: "#103126",
    panel2: "#172e24",
    accent: "#bfe985",
    accentSoft: "rgba(191, 233, 133, .25)",
    text: "#f5f7f0",
    paper: "#f3efe2",
    shadow: "rgba(0, 0, 0, .35)"
  };

  function resize() {
    const rect = hero.getBoundingClientRect();
    state.w = Math.max(1, Math.floor(rect.width));
    state.h = Math.max(1, Math.floor(rect.height));
    canvas.width = Math.floor(state.w * state.dpr);
    canvas.height = Math.floor(state.h * state.dpr);
    canvas.style.width = `${state.w}px`;
    canvas.style.height = `${state.h}px`;
    ctx.setTransform(state.dpr, 0, 0, state.dpr, 0, 0);
    draw();
  }

  function project(x, y, z, cx, cy, focal = 700) {
    const s = focal / (focal + z);
    return { x: cx + x * s, y: cy + y * s, s };
  }

  function drawGradientBg(cx, cy) {
    const g = ctx.createRadialGradient(cx, cy, 40, cx, cy, Math.max(state.w, state.h) * 0.8);
    g.addColorStop(0, "rgba(41, 95, 70, .32)");
    g.addColorStop(.4, "rgba(12, 28, 23, .24)");
    g.addColorStop(1, palette.bgA);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, state.w, state.h);

    const band = ctx.createLinearGradient(0, state.h * .1, state.w, state.h * .88);
    band.addColorStop(0, "rgba(191, 233, 133, 0)");
    band.addColorStop(.46, "rgba(191, 233, 133, .08)");
    band.addColorStop(.54, "rgba(191, 233, 133, .16)");
    band.addColorStop(.62, "rgba(191, 233, 133, .08)");
    band.addColorStop(1, "rgba(191, 233, 133, 0)");
    ctx.fillStyle = band;
    ctx.fillRect(0, 0, state.w, state.h);
  }

  function drawRoundedPanel(x, y, w, h, depth, rotation, fill, stroke, label, accent = false) {
    const cx = state.w * .68;
    const cy = state.h * .49;
    const center = project(x, y, depth, cx, cy);
    const scale = center.s;
    const hw = (w * scale) / 2;
    const hh = (h * scale) / 2;
    const skew = rotation * 0.35;

    ctx.save();
    ctx.translate(center.x, center.y);
    ctx.rotate(rotation);
    ctx.scale(scale, scale);

    const lg = ctx.createLinearGradient(-hw, -hh, hw, hh);
    lg.addColorStop(0, fill[0]);
    lg.addColorStop(1, fill[1]);
    ctx.fillStyle = lg;
    ctx.shadowColor = palette.shadow;
    ctx.shadowBlur = 30;
    ctx.beginPath();
    ctx.moveTo(-hw + 12, -hh);
    ctx.lineTo(hw - 10, -hh + 4);
    ctx.lineTo(hw + 14, hh - 6);
    ctx.lineTo(-hw + 2, hh + 2);
    ctx.closePath();
    ctx.fill();

    ctx.shadowBlur = 0;
    ctx.strokeStyle = stroke;
    ctx.lineWidth = 1.25;
    ctx.stroke();

    ctx.fillStyle = accent ? "rgba(191, 233, 133, .08)" : "rgba(255,255,255,.03)";
    ctx.fillRect(-hw + 16, -hh + 16, (hw * 2) - 32, (hh * 2) - 32);

    ctx.fillStyle = accent ? palette.accent : "rgba(255,255,255,.3)";
    ctx.font = "700 12px Inter, ui-sans-serif, sans-serif";
    ctx.textAlign = "left";
    ctx.fillText(label, -hw + 18, -hh + 26);
    ctx.restore();
  }

  function drawDocument(x, y, w, h, depth, rotation) {
    const cx = state.w * .68;
    const cy = state.h * .49;
    const p = project(x, y, depth, cx, cy);
    const scale = p.s;
    const hw = (w * scale) / 2;
    const hh = (h * scale) / 2;

    ctx.save();
    ctx.translate(p.x, p.y);
    ctx.rotate(rotation);
    ctx.scale(scale, scale);
    ctx.shadowColor = "rgba(0,0,0,.42)";
    ctx.shadowBlur = 40;
    ctx.fillStyle = "linear-gradient(180deg, #f6f2e8, #d9ded4)";
    const grad = ctx.createLinearGradient(-hw, -hh, hw, hh);
    grad.addColorStop(0, "#fbf8ee");
    grad.addColorStop(1, "#dde3d9");
    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.moveTo(-hw + 8, -hh + 5);
    ctx.lineTo(hw - 4, -hh + 2);
    ctx.lineTo(hw + 12, hh - 4);
    ctx.lineTo(-hw + 2, hh + 8);
    ctx.closePath();
    ctx.fill();
    ctx.shadowBlur = 0;
    ctx.strokeStyle = "rgba(85, 111, 94, .35)";
    ctx.stroke();

    ctx.fillStyle = "rgba(95, 111, 103, .55)";
    for (let i = 0; i < 4; i++) {
      ctx.fillRect(-hw + 18, -hh + 18 + i * 22, hw * 1.25 - i * 10, 3);
    }
    ctx.fillStyle = "#1a211d";
    ctx.font = "italic 700 20px Georgia, serif";
    ctx.fillText("Acordo possível", -hw + 18, 14);
    ctx.strokeStyle = "rgba(78, 117, 88, .8)";
    ctx.beginPath();
    ctx.arc(hw - 42, hh - 42, 18, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(hw - 49, hh - 38);
    ctx.lineTo(hw - 42, hh - 31);
    ctx.lineTo(hw - 32, hh - 47);
    ctx.stroke();
    ctx.restore();
  }

  function drawConnection(progress, cx, cy) {
    ctx.save();
    const leftX = state.w * .36 + progress * 36;
    const rightX = state.w * .86 - progress * 34;
    const baseY = state.h * .54 - progress * 6;
    ctx.strokeStyle = `rgba(191, 233, 133, ${.22 + progress * .56})`;
    ctx.lineWidth = 2;
    ctx.shadowColor = "rgba(191, 233, 133, .22)";
    ctx.shadowBlur = 18;
    ctx.beginPath();
    ctx.moveTo(leftX, baseY);
    ctx.bezierCurveTo(state.w * .48, baseY - 74 - progress * 24, state.w * .60, baseY + 62 - progress * 12, rightX, baseY - 2);
    ctx.stroke();
    ctx.shadowBlur = 0;

    const n = 10;
    for (let i = 0; i < n; i++) {
      const t = i / (n - 1);
      const x = leftX * (1 - t) + rightX * t;
      const y = baseY - Math.sin(t * Math.PI) * (72 + progress * 26);
      ctx.fillStyle = `rgba(191, 233, 133, ${.14 + .1 * Math.sin((state.time * 1.8) + t * 6)})`;
      ctx.beginPath();
      ctx.arc(x, y, 2.2 + t * 1.4, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.restore();
  }

  function drawEnergyBeam(progress) {
    ctx.save();
    const x = state.w * (0.39 + progress * 0.18);
    const y = state.h * (0.34 + progress * 0.08);
    const beam = ctx.createLinearGradient(x - 140, y - 20, x + 190, y + 210);
    beam.addColorStop(0, "rgba(191, 233, 133, 0)");
    beam.addColorStop(.5, `rgba(191, 233, 133, ${.08 + progress * .22})`);
    beam.addColorStop(1, "rgba(191, 233, 133, 0)");
    ctx.globalCompositeOperation = "screen";
    ctx.fillStyle = beam;
    ctx.beginPath();
    ctx.ellipse(x, y, 240 + progress * 40, 24 + progress * 8, -0.28, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  function draw() {
    const cx = state.w * .68;
    const cy = state.h * .49;
    const progress = state.scroll;
    const mouseInfluenceX = state.mouseX * 24;
    const mouseInfluenceY = state.mouseY * 16;
    drawGradientBg(cx, cy);
    drawEnergyBeam(progress);

    // soft depth rings
    ctx.save();
    ctx.strokeStyle = "rgba(191, 233, 133, .08)";
    for (let i = 0; i < 4; i++) {
      ctx.beginPath();
      ctx.ellipse(cx + mouseInfluenceX * .2, cy + mouseInfluenceY * .2, 110 + i * 42, 78 + i * 28, 0.2 + i * .12, 0, Math.PI * 2);
      ctx.stroke();
    }
    ctx.restore();

    const baseRot = -0.12 + progress * 0.22 + mouseInfluenceX * 0.003;
    const docRot = -0.08 + progress * 0.18 + mouseInfluenceX * 0.0015;
    drawRoundedPanel(state.w * .33 - progress * 40, state.h * .52 + mouseInfluenceY, 240, 150, 40 - progress * 10, baseRot, ["#16392c", "#0d211a"], "rgba(191, 233, 133, .2)", "CONFLITO");
    drawRoundedPanel(state.w * .86 + progress * 32, state.h * .52 - mouseInfluenceY, 240, 150, 40 - progress * 10, -baseRot * .85, ["#173126", "#0f221a"], "rgba(191, 233, 133, .2)", "DIÁLOGO");
    drawConnection(progress, cx, cy);
    drawDocument(state.w * .66 + progress * 12, state.h * .49 - progress * 14, 180 + progress * 18, 250 + progress * 10, 8 - progress * 5, docRot);

    // center glow/seal
    ctx.save();
    const sealX = state.w * .68;
    const sealY = state.h * .59;
    ctx.translate(sealX, sealY);
    ctx.fillStyle = `rgba(191, 233, 133, ${.12 + progress * .2})`;
    ctx.shadowColor = "rgba(191, 233, 133, .42)";
    ctx.shadowBlur = 22;
    ctx.beginPath();
    ctx.arc(0, 0, 38 + progress * 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.shadowBlur = 0;
    ctx.strokeStyle = "rgba(95, 136, 102, .8)";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(0, 0, 28 + progress * 3, 0, Math.PI * 2);
    ctx.stroke();
    ctx.strokeStyle = "rgba(255,255,255,.75)";
    ctx.beginPath();
    ctx.moveTo(-7, 0);
    ctx.lineTo(-1, 7);
    ctx.lineTo(10, -8);
    ctx.stroke();
    ctx.restore();

    ctx.fillStyle = "rgba(205, 216, 209, .55)";
    ctx.font = "700 11px ui-sans-serif, system-ui, sans-serif";
    ctx.letterSpacing = "0.2em";
    ctx.fillText("DO CONFLITO AO ACORDO", state.w * .62, state.h * .73);

    ctx.save();
    ctx.globalCompositeOperation = "screen";
    ctx.strokeStyle = "rgba(191, 233, 133, .08)";
    for (let i = 0; i < 3; i++) {
      ctx.beginPath();
      ctx.arc(state.w * .68, state.h * .49, 140 + i * 52 + Math.sin(state.time * 1.2 + i) * 10, 0.6 + i * 0.1, Math.PI * 1.35 + i * 0.08);
      ctx.stroke();
    }
    ctx.restore();
  }

  function tick(now) {
    state.time = now * 0.001;
    state.targetScroll = Math.min(1, Math.max(0, window.scrollY / Math.max(document.documentElement.scrollHeight - window.innerHeight, 1)));
    state.scroll += (state.targetScroll - state.scroll) * 0.08;
    state.targetMouseX += (state.mouseX - state.targetMouseX) * 0.08;
    state.targetMouseY += (state.mouseY - state.targetMouseY) * 0.08;
    state.mouseX = state.targetMouseX;
    state.mouseY = state.targetMouseY;

    if (reducedMotion || mobileCoarse) {
      state.scroll += (0.35 - state.scroll) * 0.03;
    }

    draw();
    window.requestAnimationFrame(tick);
  }

  hero.addEventListener("pointermove", (event) => {
    const rect = hero.getBoundingClientRect();
    state.mouseX = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
    state.mouseY = ((event.clientY - rect.top) / rect.height - 0.5) * 2;
  }, { passive: true });
  hero.addEventListener("pointerleave", () => {
    state.mouseX = 0;
    state.mouseY = 0;
  });

  window.addEventListener("resize", resize, { passive: true });
  resize();
  if (reducedMotion) {
    console.info("CNJP 3D fallback active");
  }
  window.requestAnimationFrame(tick);
})();
