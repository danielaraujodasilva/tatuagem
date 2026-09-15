/* ==========================================================================
   Primeira Voz — voz.js
   A trilha visual do jogo e o avatar do Daniel.

   Conceito: o jogo não começa em Dó, começa em SILÊNCIO. O jogador conduz a
   primeira voz do mundo — uma linha que nasce sem forma e ganha corpo conforme
   ele domina cada fase:
       silêncio -> tem altura -> tem pulsação -> vira escala
       -> vira acorde -> vira progressão -> vira harmonia completa
   O guia é um avatar do Daniel: sem voz, se comunica por gestos e sons.
   ========================================================================== */

const Voz = (() => {

  // Estágios da jornada. Cada fase dominada avança um estágio.
  const ESTAGIOS = [
    { id: 0,  fase: 0,  nome: 'Silêncio',        desc: 'Antes de tudo, nada. Só a atenção.' },
    { id: 1,  fase: 2,  nome: 'Uma linha',       desc: 'O som ganha direção: sobe, desce, para.' },
    { id: 2,  fase: 3,  nome: 'Pulsação',        desc: 'A linha aprende a respirar no tempo.' },
    { id: 3,  fase: 5,  nome: 'Sete degraus',    desc: 'A linha se dobra em sete casas conhecidas.' },
    { id: 4,  fase: 7,  nome: 'Distâncias',      desc: 'As casas ganham intervalos. Pular agora tem tamanho.' },
    { id: 5,  fase: 9,  nome: 'A armadura',      desc: 'A linha muda de cor conforme a tonalidade.' },
    { id: 6,  fase: 10, nome: 'Três vozes',      desc: 'Uma voz vira três: nasce o primeiro acorde.' },
    { id: 7,  fase: 12, nome: 'Campo',           desc: 'As três vozes descobrem família e função.' },
    { id: 8,  fase: 13, nome: 'Frases',          desc: 'As vozes começam a terminar o que começam.' },
    { id: 9,  fase: 14, nome: 'Cores',           desc: 'A mesma escala, sete humores diferentes.' },
    { id: 10, fase: 15, nome: 'Harmonia',        desc: 'A voz canta a estrutura inteira. Está completa.' }
  ];

  function estagioDe(fasesDominadas) {
    let atual = ESTAGIOS[0];
    ESTAGIOS.forEach(e => { if (fasesDominadas >= e.fase) atual = e; });
    return atual;
  }

  function progressoVisual(fasesDominadas, totalFases = 15) {
    return Math.max(0, Math.min(1, fasesDominadas / totalFases));
  }

  /* ---------- avatar do Daniel ----------
     Traço simples de propósito: silhueta + cabeça + mão que se move.
     Sem voz, sem texto. Ele "fala" apontando e tocando.
     ------------------------------------------------------------------ */

  function avatarDaniel(c, x, y, escala, opts = {}) {
    const t = opts.tempo ?? 0;
    const respiro = Math.sin(t * 1.1) * 1.4;
    const gesto = opts.gesto ?? 0;      // 0..1, controla o braço
    const alpha = opts.alpha ?? 1;

    c.save();
    c.globalAlpha = alpha;
    c.translate(x, y + respiro);
    c.scale(escala, escala);

    const tinta = opts.cor || '#e8e0cf';
    const ouro = Engine.COR.ouro;

    // sombra no chão
    c.save();
    c.globalAlpha = alpha * 0.25;
    c.fillStyle = '#000';
    c.beginPath(); c.ellipse(0, 6, 30, 6, 0, 0, Math.PI * 2); c.fill();
    c.restore();

    // tronco
    c.fillStyle = tinta;
    c.beginPath();
    c.moveTo(-15, 4);
    c.quadraticCurveTo(-18, -34, -11, -48);
    c.lineTo(11, -48);
    c.quadraticCurveTo(18, -34, 15, 4);
    c.closePath();
    c.fill();

    // cabeça
    c.beginPath();
    c.ellipse(0, -60, 12, 13.5, 0, 0, Math.PI * 2);
    c.fill();

    // braço esquerdo (aponta para o campo)
    const bracoAng = -0.6 - gesto * 0.9 + Math.sin(t * 0.8) * 0.05;
    c.save();
    c.translate(-13, -42);
    c.rotate(bracoAng);
    c.strokeStyle = tinta;
    c.lineWidth = 7;
    c.lineCap = 'round';
    c.beginPath(); c.moveTo(0, 0); c.lineTo(26, 0); c.stroke();
    c.fillStyle = ouro;
    c.beginPath(); c.arc(28, 0, 4.2, 0, Math.PI * 2); c.fill();
    c.restore();

    // braço direito (desce, relaxado)
    c.save();
    c.translate(12, -42);
    c.rotate(0.28 + Math.sin(t * 0.8) * 0.05);
    c.strokeStyle = tinta;
    c.lineWidth = 7;
    c.beginPath(); c.moveTo(0, 0); c.lineTo(21, 2); c.stroke();
    c.restore();

    c.restore();
  }

  /* ---------- cena da trilha ----------
     Desenha a linha da voz conforme o estágio atual.
     ------------------------------------------------------------------ */

  function desenharTrilha(c, w, h, opts = {}) {
    const fases = opts.fasesDominadas ?? 0;
    const t = opts.tempo ?? 0;
    const prog = progressoVisual(fases);
    const est = estagioDe(fases);
    const cx = w / 2;
    const cy = h * 0.5;
    // `escala` permite desenhar a trilha num espaço menor (ex.: terço superior)
    const escala = opts.escala ?? 1;
    const largura = Math.min(w * 0.72, 620) * escala;
    const altura = 34 * escala;

    // Linha: começa reta e invisível (silêncio) e vai ganhando estrutura.
    // Cada estágio adiciona uma qualidade visual distinta.
    const pontos = 140;
    const forma = (u) => {
      const x = cx - largura / 2 + largura * u;
      let y = cy;

      // 1. direção (sobe/desce)
      if (est.id >= 1) y -= Math.sin(u * Math.PI * 1.0) * altura * prog;

      // 2. pulsação: a curva ganha "batidas" regulares
      if (est.id >= 2) y += Math.sin(u * Math.PI * 2 * 4 + t * 1.6) * 7 * prog;

      // 3. sete degraus: aparece a escada
      if (est.id >= 3) {
        const d = Math.round(u * 7) / 7;
        y -= Math.sin(d * Math.PI * 1.0) * altura * prog;
        y += (u - d) * 0;
      }

      // 4. intervalos: a curva fica angular, com saltos visíveis
      if (est.id >= 4) {
        const deg = Math.round(u * 12) / 12;
        y += Math.sin(deg * Math.PI * 2) * 9 * prog;
      }

      // 5+ cor por tonalidade
      return { x, y };
    };

    // aura
    const aura = c.createRadialGradient(cx, cy, 0, cx, cy, largura * 0.62);
    const matizes = [210, 200, 190, 45, 35, 280, 320, 340, 20, 175, 150];
    const matiz = matizes[Math.min(matizes.length - 1, est.id)];
    aura.addColorStop(0, `hsla(${matiz}, 62%, 58%, ${0.06 + prog * 0.10})`);
    aura.addColorStop(1, 'hsla(0,0%,0%,0)');
    c.save();
    c.fillStyle = aura;
    c.fillRect(0, 0, w, h);
    c.restore();

    // linha principal
    c.save();
    c.lineCap = 'round';
    c.lineJoin = 'round';

    c.beginPath();
    for (let i = 0; i <= pontos; i++) {
      const p = forma(i / pontos);
      i === 0 ? c.moveTo(p.x, p.y) : c.lineTo(p.x, p.y);
    }
    c.strokeStyle = `hsla(${matiz}, 70%, 68%, ${0.22 + prog * 0.5})`;
    c.lineWidth = 9 + prog * 9;
    c.stroke();

    c.beginPath();
    for (let i = 0; i <= pontos; i++) {
      const p = forma(i / pontos);
      i === 0 ? c.moveTo(p.x, p.y) : c.lineTo(p.x, p.y);
    }
    c.strokeStyle = est.id === 0 ? 'rgba(244,239,228,0.16)' : `hsl(${matiz}, 78%, 76%)`;
    c.lineWidth = 2.2;
    c.stroke();
    c.restore();

    // as três vozes quando vira acorde
    if (est.id >= 6) {
      for (let k = 1; k <= 2; k++) {
        c.save();
        c.beginPath();
        for (let i = 0; i <= pontos; i++) {
          const u = i / pontos;
          const p = forma(u);
          const off = Math.sin(u * Math.PI) * 16 * k;
          i === 0 ? c.moveTo(p.x, p.y - off) : c.lineTo(p.x, p.y - off);
        }
        c.strokeStyle = `hsla(${matiz + k * 26}, 68%, 72%, 0.30)`;
        c.lineWidth = 1.6;
        c.stroke();
        c.restore();
      }
    }

    return { cx, cy, matiz };
  }

  /* ---------- retrato do estágio (tela de fase dominada) ---------- */

  function retratoEstagio(c, w, h, fasesDominadas, tempo = 0) {
    const est = estagioDe(fasesDominadas);
    Engine.fundoGradiente(c, w, h, tempo, 205);
    Engine.grade(c, w, h, 52, 0.035);
    desenharTrilha(c, w, h, { fasesDominadas, tempo });

    const matizes = [210, 200, 190, 45, 35, 280, 320, 340, 20, 175, 150];
    const matiz = matizes[Math.min(matizes.length - 1, est.id)];

    c.save();
    c.textAlign = 'center';
    c.fillStyle = `hsl(${matiz}, 70%, 82%)`;
    c.font = '300 15px "Segoe UI", system-ui, sans-serif';
    c.fillText('A PRIMEIRA VOZ', w / 2, h * 0.16);

    c.fillStyle = '#f4efe4';
    c.font = '600 34px "Segoe UI", system-ui, sans-serif';
    c.fillText(est.nome, w / 2, h * 0.16 + 40);
    c.restore();

    Engine.texto(c, est.desc, w / 2, h * 0.80, {
      size: 15, cor: 'rgba(244,239,228,0.72)', maxW: Math.min(w * 0.8, 420), lineH: 22
    });

    return est;
  }

  /* ---------- balão de fala do Daniel ----------
     Ele não fala: mostra. Então o "balão" é um painel de dica com um
     pequeno gesto desenhado, nunca texto narrativo em primeira pessoa.
     ------------------------------------------------------------------ */

  function dica(c, x, y, w, h, texto, tempo = 0, cor = null) {
    const aparecer = Math.min(1, tempo * 3);
    c.save();
    c.globalAlpha = aparecer;
    c.translate(0, (1 - Engine.Ease.outCubic(aparecer)) * 8);

    const r = 14;
    c.fillStyle = 'rgba(18,16,14,0.80)';
    c.strokeStyle = cor ? Engine.hexA(cor, 0.5) : 'rgba(217,169,74,0.36)';
    c.lineWidth = 1.4;

    c.beginPath();
    c.moveTo(x + r, y);
    c.arcTo(x + w, y, x + w, y + h, r);
    c.arcTo(x + w, y + h, x, y + h, r);
    c.arcTo(x, y + h, x, y, r);
    c.arcTo(x, y, x + w, y, r);
    c.closePath();
    c.fill();
    c.stroke();

    // marquinha apontando pro Daniel
    c.beginPath();
    c.moveTo(x - 8, y + h * 0.5);
    c.lineTo(x + 4, y + h * 0.36);
    c.lineTo(x + 4, y + h * 0.64);
    c.closePath();
    c.fillStyle = 'rgba(18,16,14,0.80)';
    c.fill();

    Engine.texto(c, texto, x + w / 2, y + 16, {
      size: 14.5, cor: '#e8e0cf', maxW: w - 32, lineH: 21
    });
    c.restore();
  }

  return {
    ESTAGIOS, estagioDe, progressoVisual,
    avatarDaniel, desenharTrilha, retratoEstagio, dica
  };
})();

window.Voz = Voz;
