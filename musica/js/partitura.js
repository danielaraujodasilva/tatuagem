/* ==========================================================================
   Primeira Voz — partitura.js
   O CAPÍTULO DA ESCRITA: transformar som em símbolo na pauta.

   Método (fiel ao resto do jogo): ouvir -> nomear -> LER -> escrever.
   Nada de decorar posição de bolinha. O ouvido já conhece a nota; agora ele
   descobre onde aquela nota mora escrita.

   O que este módulo faz:
   - Desenha pauta real (5 linhas, clave de sol) em canvas
   - Posiciona notas com haste, seguindo a convenção verdadeira
   - Marca a nota tônica como âncora visual (o "chão" da leitura)
   - Converte posição de rato/tato em passo musical (para os exercícios
     de completar a pauta)
   ========================================================================== */

const Partitura = (() => {

  /* ---------- geometria da pauta ----------
     Referência real: na clave de sol, a linha de baixo é Mi4 (E4) e cada
     linha/espaço sobe uma nota da escala de Dó. Mi4 = MIDI 64 na linha 1.
     ------------------------------------------------------------------ */

  const CLAVE_SOL = { nome: 'Clave de Sol', refMidi: 64, refLinha: 1 };
  // linha 1 (de baixo pra cima) = Mi4. Cada passo = 1 grau diatônico.

  const ACIDENTES = { '': '', '#': '♯', 'b': '♭', 'n': '♮' };

  // Sobe um grau diatônico (não um semitom): respeita a pauta.
  function passoDiatonico(midi, passos) {
    const letras = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];
    // Converte MIDI em (letra da escala, acidente, oitava)
    const mapa = [[0, 0], [1, 1], [2, 0], [3, 1], [4, 0], [5, 0], [6, 1],
                  [7, 0], [8, 1], [9, 0], [10, 1], [11, 0]];
    const pc = ((midi % 12) + 12) % 12;
    const [letraIdx, ac] = mapa[pc];
    const oitava = Math.floor(midi / 12) - 1;

    let total = letraIdx + oitava * 7 + passos;
    const novaOitava = Math.floor(total / 7);
    const novaLetra = ((total % 7) + 7) % 7;
    // volta pra semitom usando a escala maior de Dó
    const base = [0, 2, 4, 5, 7, 9, 11][novaLetra] + (novaOitava + 1) * 12;
    // preserva o acidente quando faz sentido (mesma letra)
    let novoMidi = base + ac;
    // se mover dentro da mesma letra, o acidente segue
    if (passos === 0) novoMidi = midi;
    return novoMidi;
  }

  /**
   * Posição vertical (em "graus") de uma nota na pauta da clave de sol.
   * 0 = linha 1 (Mi4) .. 8 = 5ª linha (Fá5). Negativo desce, positivo sobe.
   *
   * A conta precisa ser ancorada no Mi4 real, não na oitava científica, senão
   * todo grau sai deslocado (foi um bug de 28 graus).
   */
  function grauNaPauta(midi) {
    // pc -> índice da letra na escala (Dó=0, Ré=1, Mi=2, Fá=3, Sol=4, Lá=5, Si=6)
    const letraDoPc = [0, 0, 1, 1, 2, 3, 3, 4, 4, 5, 5, 6];
    const pc = ((midi % 12) + 12) % 12;
    const letra = letraDoPc[pc];

    // Oitava científica: Dó4 = MIDI 60, então a oitava de um MIDI é
    // floor(midi/12) - 1, MAS isso conta a partir do Dó. Para medir distância
    // em letras precisamos da oitava em que a NOTA está, tratando Si->Dó como
    // troca de oitava. Usamos o índice absoluto de letra a partir do Dó4.
    const oitavaCientifica = Math.floor(midi / 12) - 1;

    // índice absoluto da letra contando a partir de Dó4 (MIDI 60 = Dó4 = letraAbs 0)
    const letraAbs = (oitavaCientifica - 4) * 7 + letra;

    // Mi4 = letraAbs 2 (Dó=0, Ré=1, Mi=2). Na pauta, Mi4 é o grau 0.
    return letraAbs - 2;
  }

  /** Y em pixels do "grau" na pauta. linhas = array de y das 5 linhas. */
  function yDoGrau(grau, linhas) {
    const espaco = linhas[1] - linhas[0]; // distância entre linhas
    // grau 0 = linha 1 (linhas[4] se desenharmos de cima pra baixo)
    const yBase = linhas[4];
    return yBase - (grau / 2) * espaco;
  }

  /** Onde as linhas suplementares são necessárias. */
  function linhasSuplementares(grau) {
    const out = [];
    if (grau <= -2) for (let g = -2; g >= grau; g -= 2) out.push(g);   // abaixo
    if (grau >= 10) for (let g = 10; g <= grau; g += 2) out.push(g);   // acima
    return out;
  }

  /* ---------- desenho ---------- */

  /**
   * Desenha a pauta. Retorna a geometria para posicionar notas depois.
   * @param {CanvasRenderingContext2D} c
   * @param {object} opts { x, y, largura, espaco, cor }
   */
  function desenharPauta(c, opts = {}) {
    const x = opts.x ?? 40;
    const y = opts.y ?? 40;
    const largura = opts.largura ?? 520;
    const espaco = opts.espaco ?? 14;   // distância entre linhas
    const cor = opts.cor ?? 'rgba(244,239,228,0.55)';
    const corClave = opts.corClave ?? 'rgba(244,239,228,0.85)';

    c.save();
    c.strokeStyle = cor;
    c.lineWidth = opts.larguraLinha ?? 1.4;
    c.lineCap = 'round';

    const linhas = [];
    for (let i = 0; i < 5; i++) {
      const yy = y + i * espaco;
      linhas.push(yy);
      c.beginPath();
      c.moveTo(x, yy);
      c.lineTo(x + largura, yy);
      c.stroke();
    }

    // barra no fim
    c.lineWidth = 2.2;
    c.beginPath();
    c.moveTo(x + largura, y);
    c.lineTo(x + largura, y + 4 * espaco);
    c.stroke();

    // clave de sol desenhada à mão (curva, sem fonte)
    desenharClaveSol(c, x + 6, linhas, espaco, corClave);

    c.restore();
    return { x, y, largura, espaco, linhas };
  }

  /** Clave de sol: espiral + curva longa. Aproximação reconhecível. */
  function desenharClaveSol(c, x, linhas, espaco, cor) {
    const topo = linhas[0] - espaco * 1.15;
    const base = linhas[4] + espaco * 0.9;
    const meio = linhas[2];
    c.save();
    c.strokeStyle = cor;
    c.lineWidth = 2.4;
    c.lineCap = 'round';
    c.lineJoin = 'round';

    const alt = base - topo;

    // corpo: S alongado
    c.beginPath();
    c.moveTo(x + espaco * 0.32, topo);
    c.bezierCurveTo(
      x + espaco * 1.5, topo + alt * 0.12,
      x + espaco * 1.15, meio,
      x + espaco * 0.12, meio + espaco * 0.15
    );
    c.bezierCurveTo(
      x - espaco * 0.85, meio + espaco * 0.35,
      x - espaco * 0.5, base - espaco * 0.05,
      x + espaco * 0.35, base - espaco * 0.15
    );
    c.stroke();

    // laço superior
    c.beginPath();
    c.moveTo(x + espaco * 0.32, topo);
    c.bezierCurveTo(
      x + espaco * 0.95, topo - espaco * 0.35,
      x + espaco * 1.0, meio - espaco * 0.6,
      x + espaco * 0.62, meio - espaco * 0.25
    );
    c.stroke();

    // gancho de baixo
    c.beginPath();
    c.moveTo(x + espaco * 0.35, base - espaco * 0.15);
    c.bezierCurveTo(
      x + espaco * 0.05, base + espaco * 0.35,
      x + espaco * 0.7, base + espaco * 0.55,
      x + espaco * 0.72, linhas[4] + espaco * 0.05
    );
    c.stroke();

    // pingo no centro
    c.beginPath();
    c.arc(x + espaco * 0.30, linhas[1] + espaco * 0.02, espaco * 0.42, 0, Math.PI * 2);
    c.lineWidth = 3.2;
    c.stroke();

    c.restore();
  }

  /**
   * Desenha uma nota na pauta.
   * @param {object} opts {
   *   grau, midi, cor, x, cheia (bool), haste (bool), acidente, destaque,
   *   raio, alpha
   * }
   */
  function desenharNota(c, geo, opts = {}) {
    const grau = opts.grau ?? 0;
    const raio = opts.raio ?? geo.espaco * 0.62;
    const cor = opts.cor ?? '#f4efe4';
    const x = opts.x ?? geo.x + geo.largura * 0.45;
    const y = yDoGrau(grau, geo.linhas);
    const cheia = opts.cheia !== false;
    const alpha = opts.alpha ?? 1;

    c.save();
    c.globalAlpha = alpha;

    // destaque por trás (quando é a nota-alvo ou o alvo certo)
    if (opts.destaque) {
      c.fillStyle = opts.destaque;
      c.beginPath();
      c.arc(x, y, raio * 2.1, 0, Math.PI * 2);
      c.fill();
    }

    // linhas suplementares
    const sup = linhasSuplementares(grau);
    if (sup.length) {
      c.strokeStyle = 'rgba(244,239,228,0.55)';
      c.lineWidth = 1.3;
      sup.forEach(g => {
        const gy = yDoGrau(g, geo.linhas);
        c.beginPath();
        c.moveTo(x - raio * 2.0, gy);
        c.lineTo(x + raio * 2.0, gy);
        c.stroke();
      });
    }

    // cabeça da nota
    c.fillStyle = cor;
    c.strokeStyle = cor;
    c.beginPath();
    c.ellipse(x, y, raio * 1.18, raio, -0.34, 0, Math.PI * 2);
    if (cheia) c.fill();
    else { c.lineWidth = 2.6; c.stroke(); }

    // haste: sobe à direita para notas abaixo do meio, desce à esquerda acima
    if (opts.haste !== false) {
      const hasteSobe = grau < 6;
      const hx = hasteSobe ? x + raio * 1.15 : x - raio * 1.15;
      const alturaCaule = geo.espaco * 3.4;
      const hy = hasteSobe ? y - alturaCaule : y + alturaCaule;
      c.beginPath();
      c.moveTo(hx, y);
      c.lineTo(hx, hy);
      c.lineWidth = 2.2;
      c.stroke();
    }

    // acidente à esquerda
    if (opts.acidente) {
      c.fillStyle = cor;
      c.font = `${Math.round(raio * 3.1)}px "Segoe UI Symbol", serif`;
      c.textAlign = 'center';
      c.textBaseline = 'middle';
      c.fillText(opts.acidente, x - raio * 3.0, y - (opts.acidente === '♭' ? raio * 0.3 : 0));
    }

    // rótulo opcional (nome da nota) — usado só nos passos de aprendizagem
    if (opts.rotulo) {
      c.fillStyle = opts.rotuloCor || 'rgba(240,209,138,0.95)';
      c.font = '600 13px "Segoe UI", system-ui, sans-serif';
      c.textAlign = 'center';
      c.textBaseline = 'top';
      c.fillText(opts.rotulo, x, y + raio * 2.4);
    }

    c.restore();
    return { x, y };
  }

  /** Desenha a armadura (sustenidos/bemóis) depois da clave. */
  function desenharArmadura(c, geo, qtd, tipo) {
    if (!qtd) return geo.x + geo.espaco * 3.4;
    const ordemSustenidos = [4, 1, 5, 2, 6, 3, 0];   // F C G D A E B em graus diatônicos
    const x0 = geo.x + geo.espaco * 3.4;
    c.save();
    c.fillStyle = 'rgba(244,239,228,0.9)';
    for (let i = 0; i < qtd && i < 7; i++) {
      const grau = ordemSustenidos[i] + 2; // desloca pra faixa audível na pauta
      const y = yDoGrau(grau, geo.linhas);
      const x = x0 + i * geo.espaco * 0.9;
      c.font = `${Math.round(geo.espaco * 2.6)}px "Segoe UI Symbol", serif`;
      c.textAlign = 'center';
      c.textBaseline = 'middle';
      c.fillText(tipo === 'bemois' ? '♭' : '♯', x, y - (tipo === 'bemois' ? geo.espaco * 0.35 : 0));
    }
    c.restore();
    return x0 + qtd * geo.espaco * 0.9 + geo.espaco * 0.8;
  }

  /* ---------- avaliação: onde o jogador soltou a nota ---------- */

  /** Converte um Y de ponteiro no grau mais próximo da pauta. */
  function grauDoY(y, geo) {
    const espaco = geo.espaco;
    const yBase = geo.linhas[4];
    const grauExato = ((yBase - y) / espaco) * 2;
    return Math.round(grauExato);
  }

  /** Nome da nota a partir do grau (assumindo armadura de Dó maior). */
  function nomeDoGrau(grau) {
    const letras = ['Mi', 'Fá', 'Sol', 'Lá', 'Si', 'Dó', 'Ré'];
    // grau 0 = Mi4. Sobe diatonicamente.
    const idx = ((grau % 7) + 7) % 7;
    return letras[idx];
  }

  /** MIDI a partir do grau na pauta (Dó maior, sem acidentes). */
  function midiDoGrau(grau) {
    // Mi4 = 64 no grau 0. Passos diatônicos da escala de Dó.
    const grausMidi = [64, 65, 67, 69, 71, 72, 74]; // E F G A B C D
    const idx = ((grau % 7) + 7) % 7;
    const oitava = Math.floor(grau / 7);
    return grausMidi[idx] + oitava * 12;
  }

  return {
    CLAVE_SOL, ACIDENTES,
    desenharPauta, desenharNota, desenharArmadura, desenharClaveSol,
    grauNaPauta, yDoGrau, grauDoY, linhasSuplementares,
    nomeDoGrau, midiDoGrau, passoDiatonico
  };
})();

window.Partitura = Partitura;
