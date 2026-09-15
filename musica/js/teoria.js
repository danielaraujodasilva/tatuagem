/* ==========================================================================
   Primeira Voz — teoria.js
   A matemática musical do jogo. Puro cálculo, sem áudio e sem tela.
   Tudo aqui é verificável: se um número não bate com a física do som,
   é bug, não é "aproximação".
   ========================================================================== */

const Teoria = (() => {

  const NOMES_PT = ['Dó', 'Dó#', 'Ré', 'Ré#', 'Mi', 'Fá', 'Fá#', 'Sol', 'Sol#', 'Lá', 'Lá#', 'Si'];
  const NOMES_BEMOL = ['Dó', 'Réb', 'Ré', 'Mib', 'Mi', 'Fá', 'Solb', 'Sol', 'Láb', 'Lá', 'Sib', 'Si'];
  const SOLFEJO = ['Dó', 'Ré', 'Mi', 'Fá', 'Sol', 'Lá', 'Si'];
  const CIFRA = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

  // Dó central = C4. Essa é a âncora de tudo.
  const A4 = 440;
  const MIDI_A4 = 69;

  /** Frequência (Hz) de um número MIDI. Fórmula temperada padrão. */
  function hzDoMidi(midi) {
    return A4 * Math.pow(2, (midi - MIDI_A4) / 12);
  }

  /** Nota a partir do MIDI, com oitava científica (C4 = dó central). */
  function doMidi(midi, opts = {}) {
    const pc = ((midi % 12) + 12) % 12;
    const oitava = Math.floor(midi / 12) - 1;
    const nomes = opts.bemol ? NOMES_BEMOL : NOMES_PT;
    return {
      midi,
      pc,
      oitava,
      nome: nomes[pc],
      cifra: CIFRA[pc],
      solfejo: SOLFEJO[((pc % 12) + 12) % 12 >= 0 ? grauDiatonico(pc) : 0],
      hz: hzDoMidi(midi),
      label: `${nomes[pc]}${oitava}`
    };
  }

  // posição na escala de Dó maior (dó=0 .. si=6), com -1 pra acidentes
  function grauDiatonico(pc) {
    const mapa = [0, -1, 1, -1, 2, 3, -1, 4, -1, 5, -1, 6];
    return mapa[((pc % 12) + 12) % 12];
  }

  /** Nota a partir de frequência em Hz. */
  function deHz(freq, opts = {}) {
    const midi = Math.round(69 + 12 * Math.log2(freq / A4));
    return doMidi(midi, opts);
  }

  /** Nota mais próxima de uma frequência, sem arredondar pra fora da faixa. */
  function maisProxima(freq, opts = {}) {
    return deHz(freq, opts);
  }

  /** Nome curtinho de uma nota dentro do contexto (Dó4 -> "Dó"). */
  function nomeCurto(midi) {
    return doMidi(midi).nome;
  }

  /** Distância em semitons entre duas notas (pode ser negativo). */
  function semitons(a, b) { return b - a; }

  /** Distância em cents. 100 cents = 1 semitom. */
  function cents(a, b) { return 1200 * Math.log2(hzDoMidi(b) / hzDoMidi(a)); }

  /* ---------- intervalos ---------- */

  const INTERVALOS = [
    { s: 0,  nome: 'Uníssono',     curto: '1J',  cor: 'o mesmo som' },
    { s: 1,  nome: '2ª menor',     curto: '2m',  cor: 'apertado, tenso' },
    { s: 2,  nome: '2ª maior',     curto: '2M',  cor: 'passo vizinho' },
    { s: 3,  nome: '3ª menor',     curto: '3m',  cor: 'triste, redondo' },
    { s: 4,  nome: '3ª maior',     curto: '3M',  cor: 'alegre, aberto' },
    { s: 5,  nome: '4ª justa',     curto: '4J',  cor: 'solene, suspenso' },
    { s: 6,  nome: 'Trítono',      curto: 'TT',  cor: 'inquieto, perigoso' },
    { s: 7,  nome: '5ª justa',     curto: '5J',  cor: 'vazio, estável' },
    { s: 8,  nome: '6ª menor',     curto: '6m',  cor: 'nostálgico' },
    { s: 9,  nome: '6ª maior',     curto: '6M',  cor: 'doce, lírico' },
    { s: 10, nome: '7ª menor',     curto: '7m',  cor: 'quase, querendo resolver' },
    { s: 11, nome: '7ª maior',     curto: '7M',  cor: 'brilhante, curioso' },
    { s: 12, nome: '8ª justa',     curto: '8J',  cor: 'o mesmo, mais alto' }
  ];

  /** Intervalo entre duas notas (usando a distância menor que uma oitava). */
  function intervalo(midiA, midiB) {
    let s = Math.abs(midiB - midiA) % 12;
    return INTERVALOS[s];
  }

  /** Intervalo invertido (3ª maior <-> 6ª menor). Bom pra ensinar. */
  function inverter(interv) {
    const s = (12 - interv.s) % 12;
    return INTERVALOS[s];
  }

  /* ---------- escalas ---------- */

  const ESCALAS = {
    maior:       { nome: 'Maior (jônio)',        passos: [2, 2, 1, 2, 2, 2, 1], cor: 'alegre, resolvida' },
    menorNat:    { nome: 'Menor natural (eólio)', passos: [2, 1, 2, 2, 1, 2, 2], cor: 'triste, séria' },
    menorHarm:   { nome: 'Menor harmônica',       passos: [2, 1, 2, 2, 1, 3, 1], cor: 'dramática, oriental' },
    dorico:      { nome: 'Dórico',                passos: [2, 1, 2, 2, 2, 1, 2], cor: 'menor com esperança' },
    frigio:      { nome: 'Frígio',                passos: [1, 2, 2, 2, 1, 2, 2], cor: 'espanhol, sombrio' },
    lidio:       { nome: 'Lídio',                 passos: [2, 2, 2, 1, 2, 2, 1], cor: 'etéreo, flutuante' },
    mixolidio:   { nome: 'Mixolídio',             passos: [2, 2, 1, 2, 2, 1, 2], cor: 'maior com blues' },
    locrio:      { nome: 'Lócrio',                passos: [1, 2, 2, 1, 2, 2, 2], cor: 'instável, raro' },
    pentMaior:   { nome: 'Pentatônica maior',     passos: [2, 2, 3, 2, 3],       cor: 'universal, sem erro' },
    pentMenor:   { nome: 'Pentatônica menor',     passos: [3, 2, 2, 3, 2],       cor: 'blues, solo' },
    blues:       { nome: 'Blues',                 passos: [3, 2, 1, 1, 3, 2],    cor: 'azul, sujo' },
    cromatica:   { nome: 'Cromática',             passos: Array(12).fill(1),     cor: 'todos os degraus' }
  };

  /** Notas de uma escala a partir da tônica (MIDI). */
  function escala(tonica, tipo = 'maior', oitavas = 1) {
    const def = ESCALAS[tipo] || ESCALAS.maior;
    const out = [tonica];
    let cur = tonica;
    const total = def.passos.length * oitavas;
    for (let i = 0; i < total; i++) {
      cur += def.passos[i % def.passos.length];
      out.push(cur);
    }
    return out;
  }

  /** Graus da escala (1..7) com nome e solfejo. */
  function graus(tonica, tipo = 'maior') {
    const pcs = escala(tonica, tipo).slice(0, 7);
    return pcs.map((m, i) => ({
      grau: i + 1,
      romano: ROMANOS[i],
      midi: m,
      nome: doMidi(m).nome,
      solfejo: SOLFEJO[grauDiatonico(((m % 12) + 12) % 12)],
      hz: hzDoMidi(m)
    }));
  }

  const ROMANOS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'];

  /* ---------- acordes ---------- */

  const ACORDES = {
    maior:  { nome: 'maior',      passos: [0, 4, 7],       simbolo: '' },
    menor:  { nome: 'menor',      passos: [0, 3, 7],       simbolo: 'm' },
    dim:    { nome: 'diminuto',   passos: [0, 3, 6],       simbolo: '°' },
    aum:    { nome: 'aumentado',  passos: [0, 4, 8],       simbolo: '+' },
    sus4:   { nome: 'suspenso 4', passos: [0, 5, 7],       simbolo: 'sus4' },
    sus2:   { nome: 'suspenso 2', passos: [0, 2, 7],       simbolo: 'sus2' },
    maj7:   { nome: 'maior com 7ª maior', passos: [0, 4, 7, 11], simbolo: '7M' },
    m7:     { nome: 'menor com 7ª menor', passos: [0, 3, 7, 10], simbolo: 'm7' },
    dom7:   { nome: 'dominante',  passos: [0, 4, 7, 10],   simbolo: '7' },
    m7b5:   { nome: 'meio-diminuto', passos: [0, 3, 6, 10], simbolo: 'm7(5-)' }
  };

  /** Constrói um acorde a partir da raiz (MIDI) e do tipo. */
  function acorde(raiz, tipo = 'maior') {
    const def = ACORDES[tipo] || ACORDES.maior;
    const notas = def.passos.map(p => raiz + p);
    return {
      raiz,
      tipo,
      nome: doMidi(raiz).cifra + def.simbolo,
      nomePt: `${doMidi(raiz).nome} ${def.nome}`,
      notas,
      hz: notas.map(hzDoMidi),
      label: notas.map(m => doMidi(m).label)
    };
  }

  /**
   * Campo harmônico: os acordes que "moram" numa tonalidade.
   * Ex.: Dó maior -> C, Dm, Em, F, G, Am, B°
   */
  function campoHarmonico(tonica, tipo = 'maior') {
    const es = escala(tonica, tipo).slice(0, 8);
    const tiposMaior = ['maior', 'menor', 'menor', 'maior', 'maior', 'menor', 'dim'];
    const tiposMenor = ['menor', 'dim', 'maior', 'menor', 'menor', 'maior', 'maior'];
    const tipos = tipo === 'maior' ? tiposMaior : tiposMenor;
    return es.slice(0, 7).map((m, i) => {
      const ac = acorde(m, tipos[i]);
      ac.grau = i + 1;
      ac.romano = ROMANOS[i] + (tipos[i] === 'menor' ? '' : '');
      ac.funcao = FUNCOES[i];
      ac.tipoGrau = tipos[i];
      return ac;
    });
  }

  const FUNCOES = ['Tônica', 'Subdominante', 'Tônica', 'Subdominante', 'Dominante', 'Tônica', 'Dominante'];

  /** Progressão a partir de números de grau. Ex.: [1,5,6,4] em Dó. */
  function progressao(graus_, tonica, tipo = 'maior') {
    const campo = campoHarmonico(tonica, tipo);
    return graus_.map(g => campo[((g - 1) % 7 + 7) % 7]);
  }

  /* ---------- círculo das quintas ---------- */

  // Ordem real do círculo, começando em Dó e subindo quintas justas.
  const CIRCULO_QUINTAS = ['C', 'G', 'D', 'A', 'E', 'B', 'F#', 'Db', 'Ab', 'Eb', 'Bb', 'F'];

  const ARMADURAS = {
    C: 0, G: 1, D: 2, A: 3, E: 4, B: 5, 'F#': 6,
    Db: -5, Ab: -4, Eb: -3, Bb: -2, F: -1
  };

  /** Assinatura de armadura de uma tonalidade. */
  function armadura(cifra) {
    const n = ARMADURAS[cifra] ?? 0;
    if (n === 0) return { sustenidos: 0, bemois: 0, texto: 'nenhuma alteração' };
    if (n > 0) return { sustenidos: n, bemois: 0, texto: `${n} sustenido${n > 1 ? 's' : ''}` };
    const b = -n;
    return { sustenidos: 0, bemois: b, texto: `${b} bemó${b > 1 ? 'is' : 'l'}` };
  }

  /** Relativas: relativa menor de uma maior, e vice-versa. */
  function relativaMenor(midiMaior) { return midiMaior - 3; }
  function relativaMaior(midiMenor) { return midiMenor + 3; }

  /* ---------- classificação de qualidade de acorde ---------- */

  /**
   * Deduz a qualidade a partir de um conjunto de MIDI (o que o jogador montou).
   * Usada nos encaixes da fase 10 em diante.
   */
  function qualidade(notasAbs) {
    if (!notasAbs || notasAbs.length < 2) return null;
    const base = Math.min(...notasAbs);
    const ints = [...new Set(notasAbs.map(n => ((n - base) % 12 + 12) % 12))].sort((a, b) => a - b);
    const chave = ints.join(',');
    const mapa = {
      '0,4,7': 'maior', '0,3,7': 'menor', '0,3,6': 'dim', '0,4,8': 'aum',
      '0,5,7': 'sus4', '0,2,7': 'sus2',
      '0,4,7,11': 'maj7', '0,3,7,10': 'm7', '0,4,7,10': 'dom7', '0,3,6,10': 'm7b5',
      '0,4': 'maior', '0,3': 'menor', '0,5': 'sus4', '0,7': 'aberto', '0,12': 'oitava'
    };
    return mapa[chave] || null;
  }

  /* ---------- utilidades de oitava / registro ---------- */

  // Regiões confortáveis. Grave demais embola no celular; agudo demais fere.
  const ZONA = {
    baixo:    [36, 55],   // Dó2 .. Sol3
    medio:    [48, 72],   // Dó3 .. Dó5
    canto:    [55, 76],   // Sol3 .. Mi5  (onde a voz humana vive bem)
    agudo:    [60, 84]    // Dó4 .. Dó6
  };

  /** Sobe/desce por oitavas até cair na zona dada. */
  function naZona(midi, zona = 'medio') {
    const [lo, hi] = ZONA[zona] || ZONA.medio;
    let m = midi;
    while (m < lo) m += 12;
    while (m > hi) m -= 12;
    return m;
  }

  /** Tônica "amigável" pra uma cifra: sempre em registro confortável. */
  function tonicaDe(cifra, zona = 'medio') {
    const idx = CIFRA.indexOf(cifra);
    if (idx < 0) return 60;
    return naZona(60 + idx, zona);
  }

  /** Transpõe uma progressão de cifras sem sair da zona. */
  function transpor(graus_, deCifra, paraCifra) {
    const de = CIFRA.indexOf(deCifra), para = CIFRA.indexOf(paraCifra);
    if (de < 0 || para < 0) return graus_;
    return graus_.map(m => m + (para - de));
  }

  /* ---------- validação interna ---------- */

  // Checagens que rodam uma vez e gritam no console se a base quebrar.
  function autoteste() {
    const falhas = [];
    const eq = (a, b, tol, msg) => { if (Math.abs(a - b) > tol) falhas.push(`${msg}: ${a} != ${b}`); };

    eq(hzDoMidi(60), 261.6256, 0.001, 'Dó central deve ser 261,6256 Hz');
    eq(hzDoMidi(69), 440, 0.0001, 'Lá4 deve ser 440 Hz');
    eq(hzDoMidi(72), 523.2511, 0.001, 'Dó5 deve ser 523,2511 Hz');
    eq(hzDoMidi(48), 130.8128, 0.001, 'Dó3 deve ser 130,8128 Hz');
    eq(cents(60, 61), 100, 0.001, 'semitom deve ser 100 cents');
    eq(cents(60, 72), 1200, 0.001, 'oitava deve ser 1200 cents');

    const dm = escala(60, 'maior');
    eq(dm.length, 8, 0, 'escala maior de uma oitava tem 8 notas contando a oitava');
    eq(dm[7] - dm[0], 12, 0, 'oitava deve fechar em 12 semitons');

    const campo = campoHarmonico(60, 'maior');
    if (campo[0].nome !== 'C') falhas.push(`primeiro grau de Dó maior deveria ser C, veio ${campo[0].nome}`);
    if (campo[5].nome !== 'Am') falhas.push(`sexto grau deveria ser Am, veio ${campo[5].nome}`);
    if (campo[6].nome !== 'B°') falhas.push(`sétimo grau deveria ser B°, veio ${campo[6].nome}`);

    if (CIRCULO_QUINTAS.length !== 12) falhas.push('círculo das quintas precisa de 12 casas');
    if (armadura('C').texto !== 'nenhuma alteração') falhas.push('Dó maior não tem alteração');
    if (armadura('G').sustenidos !== 1) falhas.push('Sol maior tem 1 sustenido');
    if (armadura('F').bemois !== 1) falhas.push('Fá maior tem 1 bemol');
    if (armadura('F#').sustenidos !== 6) falhas.push('Fá# maior tem 6 sustenidos');

    if (falhas.length) {
      console.error('[Teoria] autoteste FALHOU:\n - ' + falhas.join('\n - '));
    } else {
      console.log('[Teoria] autoteste OK — matemática musical verificada.');
    }
    return falhas;
  }

  /* ---------- MODOS GREGOS como SISTEMA ----------
     O conceito que muda tudo: os sete modos são a MESMA escala (a de Dó maior)
     começando de pontos diferentes. Nada muda nas notas — muda onde você para.
     É por isso que dórico "soa diferente": vista do novo ponto de partida, a
     distância entre os degraus vira outra receita.
     ------------------------------------------------------------------ */

  const MODOS_GREGOS = [
    { nome: 'Jônio',     grauInicio: 1, escala: 'maior',     intervalo: '2-2-1-2-2-2-1', cor: 'o maior puro. Resolve, parece casa.', uso: 'pop, hino, marcha' },
    { nome: 'Dórico',    grauInicio: 2, escala: 'dorico',    intervalo: '2-1-2-2-2-1-2', cor: 'menor, mas com esperança.',         uso: 'jazz, folk, trilha épica' },
    { nome: 'Frígio',    grauInicio: 3, escala: 'frigio',    intervalo: '1-2-2-2-1-2-2', cor: 'espanhol, sombrio. O 2º grau cola.', uso: 'flamenco, metal' },
    { nome: 'Lídio',     grauInicio: 4, escala: 'lidio',     intervalo: '2-2-2-1-2-2-1', cor: 'etéreo, flutuante. A 4ª sobe.',      uso: 'trilha, sonho' },
    { nome: 'Mixolídio', grauInicio: 5, escala: 'mixolidio', intervalo: '2-2-1-2-2-1-2', cor: 'maior com blues. A 7ª desce.',       uso: 'rock, blues' },
    { nome: 'Eólio',     grauInicio: 6, escala: 'menorNat',  intervalo: '2-1-2-2-1-2-2', cor: 'o menor natural. Triste sem drama.', uso: 'balada, rock' },
    { nome: 'Lócrio',    grauInicio: 7, escala: 'locrio',    intervalo: '1-2-2-1-2-2-2', cor: 'instável. A 5ª é diminuta.',         uso: 'raro, sobre acorde meio-diminuto' }
  ];

  /**
   * Os sete modos de uma tonalidade, cada um começando de um grau diferente
   * da MESMA escala maior. É a demonstração viva de que modo = ponto de
   * partida, e não uma escala nova.
   */
  function modosDa(tonica) {
    const es = escala(tonica, 'maior', 2).slice(0, 15);
    return MODOS_GREGOS.map((m, i) => {
      const notas = [];
      for (let k = 0; k < 8; k++) notas.push(es[i + k]);
      const receita = [];
      for (let k = 0; k < 7; k++) receita.push(notas[k + 1] - notas[k]);
      return {
        ...m,
        tonica: notas[0],
        nomeTonica: doMidi(notas[0]).nome,
        notas,
        hz: notas.map(hzDoMidi),
        receita
      };
    });
  }

  /** Onde duas receitas de modo diferem (índices de passo, base 1). */
  function diferencaDeModos(a, b) {
    const dif = [];
    for (let i = 0; i < Math.min(a.receita.length, b.receita.length); i++) {
      if (a.receita[i] !== b.receita[i]) dif.push(i + 1);
    }
    return dif;
  }

  /* ---------- ARPEJOS ----------
     Arpejo = as notas do acorde uma depois da outra, não juntas.
     É a ponte entre acorde e melodia, e o que todo solo usa sem saber.
     ------------------------------------------------------------------ */

  /**
   * Grafia preferida de uma nota dentro de um contexto harmônico.
   * A tecla é a mesma, mas a escrita importa: a 7ª de um acorde se escreve
   * Sib, não Lá#. Ensinar com a grafia errada cria confusão depois.
   * Regra prática: bemóis para 7ª/3ª menor em contexto de acorde, sustenidos
   * para notas que naturalmente sobem (como a 4ª aumentada do lídio).
   */
  const PREFERE_BEMOL = { 1: true, 3: true, 6: false, 8: true, 10: true };
  function grafiaDe(pc, prefereBemol) {
    const p = ((pc % 12) + 12) % 12;
    const usarBemol = prefereBemol === undefined ? PREFERE_BEMOL[p] : prefereBemol;
    return (usarBemol ? NOMES_BEMOL : NOMES_PT)[p];
  }

  /**
   * Arpejo de um acorde: sobe e volta (o ciclo clássico).
   * @param {number} raiz MIDI
   * @param {string} tipo tipo do acorde (ver ACORDES)
   * @param {number} oitavas quantos ciclos de acorde subir
   * @param {boolean} desce devolve só a descida
   */
  function arpejo(raiz, tipo = 'maior', oitavas = 1, desce = false) {
    const ac = acorde(raiz, tipo);
    const notas = [];
    for (let o = 0; o < oitavas; o++) ac.notas.forEach(n => notas.push(n + o * 12));
    const ordem = desce
      ? notas.slice().reverse()
      : notas.concat(notas.slice(0, -1).reverse());
    return {
      raiz, tipo,
      nome: ac.nome,
      notas: ordem,
      hz: ordem.map(hzDoMidi),
      // grafia harmônica: usa bemol onde o músico escreveria bemol
      nomes: ordem.map(m => grafiaDe(((m % 12) + 12) % 12)),
      acorde: ac
    };
  }

  /** Arpejo de um grau do campo harmônico (1..7). */
  function arpejoDoGrau(tonica, grau, oitavas = 1) {
    const campo = campoHarmonico(tonica, 'maior');
    const ac = campo[(((grau - 1) % 7) + 7) % 7];
    return arpejo(ac.raiz, ac.tipoGrau || 'maior', oitavas);
  }

  /* ---------- INVERSÕES ----------
     O mesmo acorde com outra nota no baixo. Muda o peso, não a identidade.
     ------------------------------------------------------------------ */

  function inversao(raiz, tipo = 'maior', qual = 0) {
    const ac = acorde(raiz, tipo);
    const notas = ac.notas.slice();
    for (let i = 0; i < qual; i++) notas.push(notas.shift() + 12);
    const baixo = notas[0];
    const nomeBaixo = doMidi(baixo).nome;
    return {
      raiz, tipo, qual, notas,
      hz: notas.map(hzDoMidi),
      nome: ac.nome,
      baixo, nomeBaixo,
      cifraBaixo: qual === 0 ? ac.nome : `${ac.nome}/${CIFRA[((baixo % 12) + 12) % 12]}`,
      desc: qual === 0 ? 'posição fundamental: a raiz embaixo.'
          : qual === 1 ? `primeira inversão: a 3ª no baixo (${nomeBaixo}).`
          : `segunda inversão: a 5ª no baixo (${nomeBaixo}).`
    };
  }

  function inversoes(raiz, tipo = 'maior') {
    return [0, 1, 2].map(q => inversao(raiz, tipo, q));
  }

  /* ---------- DERIVADAS ÚTEIS ---------- */

  /** Escala de um modo a partir de uma cifra, construída pela receita própria. */
  function escalaDoModo(cifra, tipo) {
    const def = ESCALAS[tipo] || ESCALAS.maior;
    let cur = tonicaDe(cifra, 'medio');
    const notas = [cur];
    def.passos.forEach(p => { cur += p; notas.push(cur); });
    return notas;
  }

  /** Relativa: mesma coleção de notas, outro centro. */
  function relativa(tonica, tipo = 'maior') {
    if (tipo === 'maior') {
      const menor = tonica - 3;
      return { midi: menor, nome: doMidi(menor).nome, escala: 'menorNat', desc: 'relativa menor: mesmas notas, centro no 6º grau.' };
    }
    const maior = tonica + 3;
    return { midi: maior, nome: doMidi(maior).nome, escala: 'maior', desc: 'relativa maior: mesmas notas, centro no 3º grau.' };
  }

  /** As notas de um acorde com o nome da função de cada uma. */
  function notasDoAcorde(raiz, tipo = 'maior') {
    const ac = acorde(raiz, tipo);
    return ac.notas.map((m, i) => ({
      midi: m,
      nome: doMidi(m).nome,
      funcao: i === 0 ? 'raiz' : (['', '', '', '3ª', '3ª', '', '5ª', '5ª', '5ª', '', '7ª', '7ª'][((m - raiz) % 12 + 12) % 12] || 'nota'),
      intervalo: INTERVALOS[((m - raiz) % 12 + 12) % 12].nome
    }));
  }

  /** As duas pentatônicas da mesma família, lado a lado. */
  function pentas(tonica) {
    const maior = escala(tonica, 'pentMaior');
    const menor = escala(tonica - 3, 'pentMenor');
    return {
      maior: { notas: maior, hz: maior.map(hzDoMidi), nomes: maior.map(m => doMidi(m).nome) },
      menor: { notas: menor, hz: menor.map(hzDoMidi), nomes: menor.map(m => doMidi(m).nome) },
      desc: 'A pentatônica maior de Dó usa as mesmas notas que a menor de Lá: é a mesma coleção.'
    };
  }

  /** Grau (1..7) de um MIDI dentro de uma escala, ou null se não pertence. */
  function grauDe(midi, tonica, tipo = 'maior') {
    const es = escala(tonica, tipo);
    const pc = ((midi % 12) + 12) % 12;
    for (let i = 0; i < 7; i++) {
      if (((es[i] % 12) + 12) % 12 === pc) return i + 1;
    }
    return null;
  }

  return {
    NOMES_PT, NOMES_BEMOL, SOLFEJO, CIFRA, ROMANOS, CIRCULO_QUINTAS,
    INTERVALOS, ESCALAS, ACORDES, ZONA, FUNCOES, ARMADURAS, MODOS_GREGOS,
    A4, hzDoMidi, doMidi, deHz, maisProxima, nomeCurto, grauDiatonico,
    semitons, cents, intervalo, inverter,
    escala, graus, acorde, campoHarmonico, progressao,
    armadura, relativaMenor, relativaMaior, qualidade,
    naZona, tonicaDe, transpor, autoteste,
    // capítulo de escalas, acordes e modos
    modosDa, diferencaDeModos, arpejo, arpejoDoGrau,
    inversao, inversoes, escalaDoModo, relativa, notasDoAcorde, pentas, grauDe, grafiaDe
  };
})();

// módulos ficam no escopo global de propósito: index.html carrega por <script>
// em ordem, sem bundler, e main.js consulta todos eles por nome.
window.Teoria = Teoria;
