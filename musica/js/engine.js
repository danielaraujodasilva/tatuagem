/* ==========================================================================
   Primeira Voz — engine.js
   Laço de jogo, canvas, física vetorial e utilidades de desenho.

   A ideia visual central: as notas vivem em um CAMPO. A distância no campo
   é proporcional à distância em semitons. Ouvir uma 5ª justa e ver a mesma
   distância que já se viu antes — isso é o que faz escala virar intuição
   em vez de decoreba.
   ========================================================================== */

const Engine = (() => {

  let canvas = null, ctx = null, dpr = 1;
  let W = 0, H = 0;
  let raf = null;
  let cena = null;
  let t0 = 0, tPrev = 0, tNow = 0;
  let running = false;

  /* ---------- inicialização ---------- */

  function init(cv) {
    canvas = cv;
    ctx = canvas.getContext('2d', { alpha: true });
    resize();
    window.addEventListener('resize', resize);
    return { W, H };
  }

  function resize() {
    if (!canvas) return;
    const box = canvas.parentElement?.getBoundingClientRect() || { width: window.innerWidth, height: window.innerHeight };
    dpr = Math.min(2.5, window.devicePixelRatio || 1);
    W = Math.max(320, Math.floor(box.width));
    H = Math.max(320, Math.floor(box.height));
    canvas.width = Math.floor(W * dpr);
    canvas.height = Math.floor(H * dpr);
    canvas.style.width = W + 'px';
    canvas.style.height = H + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    if (cena && cena.resize) cena.resize(W, H);
  }

  /* ---------- laço ---------- */

  function play(novaCena) {
    cena = novaCena;
    if (cena && cena.resize) cena.resize(W, H);
    if (cena && cena.enter) cena.enter();
    t0 = performance.now();
    tPrev = t0;
    if (!running) {
      running = true;
      raf = requestAnimationFrame(loop);
    }
  }

  function stop() {
    running = false;
    if (raf) cancelAnimationFrame(raf);
    raf = null;
  }

  function loop(now) {
    if (!running) return;
    tPrev = tNow;
    tNow = now;
    const dt = Math.min(0.05, (tNow - tPrev) / 1000 || 0.016); // trava em 20fps mínimos
    const tempo = (now - t0) / 1000;

    ctx.clearRect(0, 0, W, H);

    if (cena) {
      if (cena.update) cena.update(dt, tempo);
      if (cena.draw) cena.draw(ctx, W, H, tempo);
    }
    raf = requestAnimationFrame(loop);
  }

  /* ---------- vetores ---------- */

  const V = {
    add:  (a, b) => ({ x: a.x + b.x, y: a.y + b.y }),
    sub:  (a, b) => ({ x: a.x - b.x, y: a.y - b.y }),
    mul:  (a, k) => ({ x: a.x * k, y: a.y * k }),
    len:  (a) => Math.hypot(a.x, a.y),
    norm: (a) => { const l = Math.hypot(a.x, a.y) || 1; return { x: a.x / l, y: a.y / l }; },
    dist: (a, b) => Math.hypot(a.x - b.x, a.y - b.y),
    dot:  (a, b) => a.x * b.x + a.y * b.y,
    lerp: (a, b, t) => ({ x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t }),
    rot:  (a, ang) => {
      const c = Math.cos(ang), s = Math.sin(ang);
      return { x: a.x * c - a.y * s, y: a.x * s + a.y * c };
    }
  };

  /* ---------- utilidades de desenho ---------- */

  const COR = {
    tinta:    '#12100e',
    papel:    '#f4efe4',
    papel2:   '#e8e0cf',
    vermelho: '#c8453c',
    ouro:     '#d9a94a',
    ouroClaro:'#f0d18a',
    menta:    '#5fb3a1',
    azul:     '#4a7fa8',
    roxo:     '#7d5f9c',
    sombra:   'rgba(18,16,14,0.52)'
  };

  function limpar(c, w, h, cor = COR.papel) {
    c.save();
    c.fillStyle = cor;
    c.fillRect(0, 0, w, h);
    c.restore();
  }

  /** Fundo com brilho suave, dá profundidade sem usar imagem. */
  function fundoGradiente(c, w, h, t = 0, matiz = 210) {
    const g = c.createLinearGradient(0, 0, 0, h);
    g.addColorStop(0, `hsl(${matiz}, 22%, 13%)`);
    g.addColorStop(0.55, `hsl(${matiz}, 24%, 9%)`);
    g.addColorStop(1, `hsl(${matiz + 12}, 26%, 7%)`);
    c.save();
    c.fillStyle = g;
    c.fillRect(0, 0, w, h);

    // halo que respira devagar
    const pulso = 0.5 + 0.5 * Math.sin(t * 0.42);
    const r = Math.max(w, h) * (0.42 + 0.05 * pulso);
    const rg = c.createRadialGradient(w * 0.5, h * 0.56, 0, w * 0.5, h * 0.56, r);
    rg.addColorStop(0, `hsla(${matiz + 40}, 60%, 58%, ${0.075 + 0.035 * pulso})`);
    rg.addColorStop(1, 'hsla(0,0%,0%,0)');
    c.fillStyle = rg;
    c.fillRect(0, 0, w, h);
    c.restore();
  }

  /** Grade de pontos discretíssima: dá escala espacial sem poluir. */
  function grade(c, w, h, passo = 46, alpha = 0.05) {
    c.save();
    c.fillStyle = `rgba(244,239,228,${alpha})`;
    for (let x = passo / 2; x < w; x += passo) {
      for (let y = passo / 2; y < h; y += passo) {
        c.beginPath();
        c.arc(x, y, 1.1, 0, Math.PI * 2);
        c.fill();
      }
    }
    c.restore();
  }

  /** Anel/orbe: o elemento visual base das notas. */
  function orbe(c, x, y, raio, cor, opts = {}) {
    const alpha = opts.alpha ?? 1;
    const glow = opts.glow ?? 0.5;
    c.save();
    c.globalAlpha = alpha;

    if (glow > 0) {
      const g = c.createRadialGradient(x, y, raio * 0.2, x, y, raio * (2.1 + glow));
      g.addColorStop(0, hexA(cor, 0.34 * glow));
      g.addColorStop(0.6, hexA(cor, 0.10 * glow));
      g.addColorStop(1, hexA(cor, 0));
      c.fillStyle = g;
      c.beginPath(); c.arc(x, y, raio * (2.1 + glow), 0, Math.PI * 2); c.fill();
    }

    const g2 = c.createRadialGradient(x - raio * 0.3, y - raio * 0.35, raio * 0.1, x, y, raio);
    g2.addColorStop(0, misturar(cor, '#ffffff', 0.45));
    g2.addColorStop(1, cor);
    c.fillStyle = g2;
    c.beginPath(); c.arc(x, y, raio, 0, Math.PI * 2); c.fill();

    if (opts.anel) {
      c.strokeStyle = hexA(opts.anel, 0.85);
      c.lineWidth = opts.anelW ?? 2;
      c.beginPath(); c.arc(x, y, raio + 4, 0, Math.PI * 2); c.stroke();
    }

    if (opts.label) {
      c.fillStyle = opts.labelCor || COR.papel;
      c.font = `${opts.labelSize || 14}px "Segoe UI", system-ui, sans-serif`;
      c.textAlign = 'center';
      c.textBaseline = 'middle';
      c.fillText(opts.label, x, y + (opts.labelDy || 0));
    }
    c.restore();
  }

  /** Onda radiando: o "som se espalhando". */
  function onda(c, x, y, raio, cor, alpha) {
    c.save();
    c.globalAlpha = alpha;
    c.strokeStyle = cor;
    c.lineWidth = 1.6;
    c.beginPath(); c.arc(x, y, raio, 0, Math.PI * 2); c.stroke();
    c.restore();
  }

  function hexA(hex, a) {
    const h = hex.replace('#', '');
    const n = parseInt(h.length === 3 ? h.split('').map(s => s + s).join('') : h, 16);
    return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`;
  }

  function misturar(a, b, t) {
    const pa = parse(a), pb = parse(b);
    const m = pa.map((v, i) => Math.round(v + (pb[i] - v) * t));
    return `rgb(${m[0]},${m[1]},${m[2]})`;
  }
  function parse(hex) {
    const h = hex.replace('#', '');
    const n = parseInt(h.length === 3 ? h.split('').map(s => s + s).join('') : h, 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }

  /** Texto com quebra de linha dentro de uma largura. */
  function texto(c, str, x, y, opts = {}) {
    const size = opts.size ?? 16;
    const lineH = opts.lineH ?? size * 1.45;
    const maxW = opts.maxW ?? 400;
    const align = opts.align ?? 'center';
    c.save();
    c.fillStyle = opts.cor || COR.papel;
    c.font = `${opts.peso || 400} ${size}px ${opts.fonte || '"Segoe UI", system-ui, sans-serif'}`;
    c.textAlign = align;
    c.textBaseline = 'top';

    const palavras = String(str).split(' ');
    const linhas = [];
    let linha = '';
    palavras.forEach(p => {
      const teste = linha ? linha + ' ' + p : p;
      if (c.measureText(teste).width > maxW && linha) { linhas.push(linha); linha = p; }
      else linha = teste;
    });
    if (linha) linhas.push(linha);

    linhas.forEach((l, i) => {
      const yy = y + i * lineH;
      if (opts.sombra) {
        c.fillStyle = 'rgba(0,0,0,0.45)';
        c.fillText(l, x + 1, yy + 1);
        c.fillStyle = opts.cor || COR.papel;
      }
      c.fillText(l, x, yy);
    });
    c.restore();
    return linhas.length * lineH;
  }

  function medirTexto(c, str, size, peso = 400) {
    c.save();
    c.font = `${peso} ${size}px "Segoe UI", system-ui, sans-serif`;
    const w = c.measureText(String(str)).width;
    c.restore();
    return w;
  }

  /* ---------- easing ---------- */

  const Ease = {
    outCubic: t => 1 - Math.pow(1 - t, 3),
    outQuint: t => 1 - Math.pow(1 - t, 5),
    inOutCubic: t => t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2,
    outElastic: t => {
      const c4 = (2 * Math.PI) / 3;
      return t === 0 ? 0 : t === 1 ? 1 : Math.pow(2, -10 * t) * Math.sin((t * 10 - 0.75) * c4) + 1;
    },
    outBack: t => { const c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); }
  };

  /* ---------- física: corda vibrante (usada no campo) ---------- */

  /**
   * Corda de N pontos com integração verlet + restrições de distância.
   * Quando você "puxa" uma corda numa nota, ela pulsa; a frequência visual
   * acompanha a frequência real. Ver e ouvir viram a mesma coisa.
   */
  class Corda {
    constructor(x0, y0, x1, y1, n = 26) {
      this.n = n;
      this.pts = [];
      for (let i = 0; i < n; i++) {
        const t = i / (n - 1);
        this.pts.push({
          x: x0 + (x1 - x0) * t,
          y: y0 + (y1 - y0) * t,
          px: x0 + (x1 - x0) * t,
          py: y0 + (y1 - y0) * t,
          fixo: i === 0 || i === n - 1
        });
      }
      this.seg = V.dist({ x: x0, y: y0 }, { x: x1, y: y1 }) / (n - 1);
      this.amp = 0;
      this.fase = 0;
      this.freq = 1;
    }

    puxar(amp = 14, freq = 1) {
      this.amp = Math.max(this.amp, amp);
      this.freq = freq;
      for (let i = 1; i < this.n - 1; i++) {
        const t = i / (this.n - 1);
        this.pts[i].py += Math.sin(Math.PI * t) * amp;
      }
    }

    update(dt, grav = 340) {
      const p = this.pts;
      this.fase += dt * this.freq * 9;
      const amort = Math.pow(0.86, dt * 60);

      for (let i = 1; i < this.n - 1; i++) {
        const pt = p[i];
        const vx = (pt.x - pt.px) * amort;
        const vy = (pt.y - pt.py) * amort;
        pt.px = pt.x; pt.py = pt.y;
        pt.x += vx;
        pt.y += vy + grav * dt * dt;
      }

      // restrições de distância (a corda não estica)
      for (let k = 0; k < 8; k++) {
        for (let i = 0; i < this.n - 1; i++) {
          const a = p[i], b = p[i + 1];
          const dx = b.x - a.x, dy = b.y - a.y;
          const d = Math.hypot(dx, dy) || 0.0001;
          const diff = (d - this.seg) / d * 0.5;
          const ox = dx * diff, oy = dy * diff;
          if (!a.fixo) { a.x += ox; a.y += oy; }
          if (!b.fixo) { b.x -= ox; b.y -= oy; }
        }
      }

      // onda estacionária colorindo a corda enquanto vibra
      if (this.amp > 0.4) {
        for (let i = 1; i < this.n - 1; i++) {
          const t = i / (this.n - 1);
          this.pts[i].py += Math.sin(Math.PI * t) * Math.sin(this.fase) * 0.55;
        }
        this.amp *= amort;
      }
    }

    draw(c, cor, larg = 2) {
      const p = this.pts;
      c.save();
      c.strokeStyle = cor;
      c.lineWidth = larg;
      c.lineCap = 'round';
      c.beginPath();
      c.moveTo(p[0].x, p[0].y);
      for (let i = 1; i < this.n; i++) c.lineTo(p[i].x, p[i].y);
      c.stroke();
      c.restore();
    }
  }

  /* ---------- partículas simples (pro feedback) ---------- */

  class Particulas {
    constructor() { this.lista = []; }
    emitir(x, y, n, cor, opts = {}) {
      for (let i = 0; i < n; i++) {
        const a = opts.angulo ?? (Math.random() * Math.PI * 2);
        const esp = opts.vel ?? (40 + Math.random() * 130);
        this.lista.push({
          x, y,
          vx: Math.cos(a + (Math.random() - 0.5) * (opts.abertura ?? 1.2)) * esp * (0.4 + Math.random()),
          vy: Math.sin(a + (Math.random() - 0.5) * (opts.abertura ?? 1.2)) * esp * (0.4 + Math.random()),
          vida: 1, cor,
          r: opts.r ?? (1.6 + Math.random() * 2.6)
        });
      }
    }
    update(dt) {
      for (let i = this.lista.length - 1; i >= 0; i--) {
        const p = this.lista[i];
        p.x += p.vx * dt; p.y += p.vy * dt;
        p.vy += 60 * dt;
        p.vx *= 0.985; p.vy *= 0.985;
        p.vida -= dt * 0.9;
        if (p.vida <= 0) this.lista.splice(i, 1);
      }
    }
    draw(c) {
      c.save();
      this.lista.forEach(p => {
        c.globalAlpha = Math.max(0, p.vida);
        c.fillStyle = p.cor;
        c.beginPath(); c.arc(p.x, p.y, p.r * p.vida, 0, Math.PI * 2); c.fill();
      });
      c.restore();
    }
    get vazio() { return this.lista.length === 0; }
  }

  /* ---------- interação: ponteiro unificado ---------- */

  function ponteiro(el, handlers = {}) {
    const pos = (e) => {
      const r = el.getBoundingClientRect();
      const src = e.touches?.[0] || e.changedTouches?.[0] || e;
      return { x: src.clientX - r.left, y: src.clientY - r.top };
    };
    let ativo = false;

    const down = (e) => {
      e.preventDefault();
      ativo = true;
      handlers.down && handlers.down(pos(e));
    };
    const move = (e) => {
      if (!ativo && !handlers.sempre) return;
      e.preventDefault();
      handlers.move && handlers.move(pos(e), ativo);
    };
    const up = (e) => {
      if (!ativo) return;
      ativo = false;
      handlers.up && handlers.up(pos(e));
    };

    el.addEventListener('mousedown', down);
    el.addEventListener('mousemove', move);
    window.addEventListener('mouseup', up);
    el.addEventListener('touchstart', down, { passive: false });
    el.addEventListener('touchmove', move, { passive: false });
    el.addEventListener('touchend', up);

    return () => {
      el.removeEventListener('mousedown', down);
      el.removeEventListener('mousemove', move);
      window.removeEventListener('mouseup', up);
      el.removeEventListener('touchstart', down);
      el.removeEventListener('touchmove', move);
      el.removeEventListener('touchend', up);
    };
  }

  return {
    init, resize, play, stop, get cena() { return cena; },
    get W() { return W; }, get H() { return H; }, get ctx() { return ctx; },
    V, COR, Ease, Corda, Particulas, ponteiro,
    limpar, fundoGradiente, grade, orbe, onda, texto, medirTexto, hexA, misturar
  };
})();

window.Engine = Engine;
