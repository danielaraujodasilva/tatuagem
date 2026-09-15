/* ==========================================================================
   Primeira Voz — jogo.js  (camada de jogo interativo)
   Substitui a navegação de menu por uma experiência jogável de imediato.

   REGRA DE OURO: nenhum clique em vão. A tela de entrada já é um jogo
   tocando. Você aperta JOGAR e está dentro da fase na mesma batida.
   ========================================================================== */

(() => {
  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));
  const rnd = (a, b) => a + Math.random() * (b - a);
  const escolher = (a) => a[Math.floor(Math.random() * a.length)];
  const embaralhar = (a) => {
    const c = a.slice();
    for (let i = c.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [c[i], c[j]] = [c[j], c[i]]; }
    return c;
  };

  /* ======================================================================
     MODO LIVRE na capa: um mini-jogo já ativo ao abrir.
     Adivinhe a nota que o jogo toca. Sem explicação prévia.
     ====================================================================== */

  const Livre = {
    ativo: false,
    alvo: null,
    acertos: 0,
    tentativas: 0,
    combo: 0,
    melhorCombo: 0,

    iniciar() {
      this.ativo = true;
      this.acertos = 0; this.tentativas = 0; this.combo = 0; this.melhorCombo = 0;
      this.novaRodada();
      this.atualizarPlacar();
    },

    parar() { this.ativo = false; },

    novaRodada() {
      // nota aleatória da escala de Dó, registro confortável
      const brancas = [60, 62, 64, 65, 67, 69, 71, 72];
      const midi = escolher(brancas);
      this.alvo = { midi, hz: Teoria.hzDoMidi(midi), nome: Teoria.doMidi(midi).nome };
      setTimeout(() => { if (this.ativo) this.tocarAlvo(); }, 260);
    },

    tocarAlvo() {
      if (!this.alvo) return;
      Audio.note(this.alvo.hz, { dur: 1.4, timbre: 'cristal', gain: 0.42 });
      const vis = $('#livre-onda');
      if (vis) { vis.classList.remove('onda-on'); void vis.offsetWidth; vis.classList.add('onda-on'); }
    },

    responder(midi) {
      if (!this.ativo || !this.alvo) return;
      this.tentativas++;
      const acertou = midi === this.alvo.midi;
      Audio.unlock();

      if (acertou) {
        this.acertos++;
        this.combo++;
        this.melhorCombo = Math.max(this.melhorCombo, this.combo);
        Audio.acerto(Teoria.hzDoMidi(midi), 0.2);
        Escore.estourar(midi, true);
        $('#livre-feedback').textContent = escolher(['Isso!', 'Na mosca.', 'Ouvido afiado.', 'Certou de primeira.', 'Boa!']);
        $('#livre-feedback').className = 'livre-feedback ok';
      } else {
        this.combo = 0;
        Audio.erro(this.alvo.hz, 0.16);
        Escore.estourar(midi, false);
        $('#livre-feedback').textContent = `Quase. Era ${this.alvo.nome}.`;
        $('#livre-feedback').className = 'livre-feedback nao';
      }
      this.atualizarPlacar();
      setTimeout(() => { if (this.ativo) this.novaRodada(); }, acertou ? 760 : 1150);
    },

    atualizarPlacar() {
      const pct = this.tentativas ? Math.round((this.acertos / this.tentativas) * 100) : 0;
      const el = $('#livre-placar');
      if (el) {
        el.textContent = this.tentativas
          ? `${this.acertos}/${this.tentativas} · ${pct}%${this.combo >= 3 ? `  ·  ${this.combo} seguidos!` : ''}`
          : '';
        el.className = 'livre-placar' + (this.combo >= 3 ? ' combo' : '');
      }
      const cb = $('#livre-combo');
      if (cb) cb.style.width = `${Math.min(100, this.combo * 20)}%`;
    }
  };

  /* ======================================================================
     ESCORE: feedback que salta na tela. É o que faz parecer jogo.
     ====================================================================== */

  const Escore = {
    estourar(midi, bom) {
      const x = rnd(window.innerWidth * 0.2, window.innerWidth * 0.8);
      const y = rnd(window.innerHeight * 0.35, window.innerHeight * 0.6);
      const el = document.createElement('div');
      el.className = 'estouro ' + (bom ? 'bom' : 'ruim');
      el.textContent = bom ? escolher(['+1', '+1', 'BOM!', '+1']) : escolher(['por pouco', 'quase', 'opa']);
      el.style.left = x + 'px';
      el.style.top = y + 'px';
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 900);
      Particulas2.emitir(x, y, bom ? 16 : 7, bom ? '#f0d18a' : '#c8453c');
    }
  };

  /* partículas leves em DOM (o canvas fica pro fundo) */
  const Particulas2 = {
    emitir(x, y, n, cor) {
      for (let i = 0; i < n; i++) {
        const d = document.createElement('div');
        d.className = 'faisca';
        d.style.left = x + 'px';
        d.style.top = y + 'px';
        d.style.background = cor;
        const ang = Math.random() * Math.PI * 2;
        const dist = 30 + Math.random() * 90;
        d.style.setProperty('--dx', Math.cos(ang) * dist + 'px');
        d.style.setProperty('--dy', Math.sin(ang) * dist + 'px');
        document.body.appendChild(d);
        setTimeout(() => d.remove(), 800);
      }
    }
  };

  /* ======================================================================
     TELAS
     ====================================================================== */

  window.Jogo = {
    Livre, Escore,
    telas: {},
    estado: {
      fase: null,
      rodadas: [],
      idx: 0,
      acertos: 0,
      combo: 0,
      melhorCombo: 0,
      modo: 'praticar',
      travado: false
    }
  };

  const J = window.Jogo;
  const CHAVE = 'primeiravoz.v3';
  const CHAVE_ANTIGA = 'primeiravoz.v2';

  // Migração de progresso: quando o capítulo do ritmo entrou, os NÚMEROS das
  // fases mudaram de significado — a antiga 21 ("A Escala") virou 28, e a 21
  // agora é "Compasso 4/4". Sem migrar, quem já tinha estrelas veria as
  // conquistas aparecendo na lição errada.
  const MAPA_V2_V3 = {
    1: 1, 2: 2, 3: 3, 4: 4, 5: 5, 6: 6, 7: 7, 8: 8, 9: 9, 10: 10,
    11: 11, 12: 12, 13: 13, 14: 14, 15: 15, 16: 16, 17: 17, 18: 18, 19: 19, 20: 20,
    21: 28, 22: 29, 23: 30, 24: 31, 25: 32, 26: 33
  };

  function carregar() {
    try {
      const raw = localStorage.getItem(CHAVE);
      if (raw) {
        const p = JSON.parse(raw);
        return {
          dominadas: p.dominadas || [],
          selos: p.selos || [],
          melhor: p.melhor || {},
          estrelas: p.estrelas || {},
          mudo: !!p.mudo,
          livreRecorde: p.livreRecorde || 0
        };
      }
      // sem a versão nova: tenta converter a antiga
      const velho = localStorage.getItem(CHAVE_ANTIGA);
      if (velho) {
        const p = JSON.parse(velho);
        const convLista = (l) => (l || []).map(x => MAPA_V2_V3[x]).filter(x => x !== undefined);
        const convMapa = (o) => {
          const out = {};
          Object.keys(o || {}).forEach(k => {
            const n = MAPA_V2_V3[k];
            if (n !== undefined) out[n] = o[k];
          });
          return out;
        };
        const migrado = {
          dominadas: convLista(p.dominadas),
          selos: convLista(p.selos),
          melhor: convMapa(p.melhor),
          estrelas: convMapa(p.estrelas),
          mudo: !!p.mudo,
          livreRecorde: p.livreRecorde || 0
        };
        try { localStorage.setItem(CHAVE, JSON.stringify(migrado)); } catch (e) {}
        return migrado;
      }
    } catch (e) {}
    return { dominadas: [], selos: [], melhor: {}, estrelas: {}, mudo: false, livreRecorde: 0 };
  }
  J.progresso = carregar();
  function salvar() { try { localStorage.setItem(CHAVE, JSON.stringify(J.progresso)); } catch (e) {} }
  J.salvar = salvar;

  function mostrar(id) {
    $$('.tela').forEach(t => t.hidden = true);
    const el = document.getElementById(id);
    if (el) el.hidden = false;
  }
  J.mostrar = mostrar;

  /* ======================================================================
     JOGO: roda uma fase de verdade, com combo, estrelas e ritmo
     ====================================================================== */

  /* --- fase 1 é ritmo puro: mecânica própria e interativa ---
     Regras pensadas para ser justo e divertido:
     1. Tem contagem de entrada (4 cliques) para você se preparar — sem isso,
        quem abre a fase perde as primeiras batidas sem entender o porquê.
     2. Só entra no julgamento DEPOIS da contagem.
     3. A rodada é curta (12 batidas), termina rápido e você quer repetir.
     4. Não existe "nota" negativa: batida sem toque não é erro, é espaço.
        Só conta o que você tocou, então tocar pouco nunca zera o placar.
     ------------------------------------------------------------------- */
  const Ritmo = {
    ativo: false,
    alvoBpm: 76,
    batidas: [],
    acertos: 0,
    tentativas: 0,
    combo: 0,
    total: 12,

    iniciar() {
      // Cancela a demonstração da tela de modo: se ela ficar pendente, o
      // stopMetronome atrasado dela mataria o metrônomo deste jogo.
      if (J.timerDemo) { clearTimeout(J.timerDemo); J.timerDemo = null; }

      this.ativo = true;
      this.batidas = [];
      this.acertos = 0;
      this.tentativas = 0;
      this.combo = 0;
      this.contador = 0;
      this.contagem = 4;      // cliques de entrada
      this.julgando = false;
      const compasso = 4;
      const periodo = 60000 / this.alvoBpm;

      $('#jogo-pergunta').textContent = 'Sinta o pulso. Depois bata junto — bem em cima.';
      $('#jogo-opcoes').hidden = true;
      $('#btn-bater').hidden = false;
      $('#jogo-contador').textContent = 'Preparar…';
      $('#jogo-dica').textContent = 'O primeiro tempo de cada grupo de 4 é mais forte. Deixe o corpo marcar, não a cabeça.';

      Audio.unlock();
      Audio.startMetronome(this.alvoBpm, compasso, (i, acento) => {
        this.pulsoVisual(acento);

        // contagem de entrada: só escuta
        if (this.contagem > 0) {
          this.contagem--;
          $('#jogo-contador').textContent = `Preparar… ${this.contagem + 1}`;
          if (this.contagem === 0) {
            this.julgando = true;
            $('#jogo-contador').textContent = `0 / ${this.total} em cima`;
          }
          return;
        }

        // fase de jogo: registra a batida para julgar
        this.batidas.push({ t: performance.now(), acento });
        this.contador++;
        if (this.contador > this.total) { this.finalizar(); return; }
        $('#jogo-contador').textContent =
          `${this.acertos} de ${this.tentativas} em cima · ${this.contador}/${this.total}`;
      });
    },

    pulsoVisual(acento) {
      const wrap = $('#jogo-visual');
      if (!wrap) return;
      const el = document.createElement('div');
      el.className = 'anel-pulso' + (acento ? ' acento' : '');
      wrap.appendChild(el);
      setTimeout(() => el.remove(), 720);
    },

    bater() {
      if (!this.ativo || !this.julgando) return;
      Audio.unlock();
      const agora = performance.now();
      const periodo = 60000 / this.alvoBpm;

      // procura a batida mais próxima, aceitando tocar um pouco antes
      let dist = Infinity;
      this.batidas.forEach(b => {
        const d = Math.abs(agora - b.t);
        const d2 = Math.abs(agora - (b.t + periodo));  // antecipar é tocar no tempo
        if (d < dist) dist = d;
        if (d2 < dist) dist = d2;
      });
      if (!isFinite(dist)) return;

      this.tentativas++;
      const emCima = dist < 150;
      if (emCima) {
        this.acertos++;
        this.combo++;
        if (this.combo > 2) this.combo = 2;   // teto: o combo visual não some sozinho
        Audio.acerto(659.25, 0.15);
        Escore.estourar(window.innerWidth / 2, window.innerHeight * 0.45, true);
      } else {
        this.combo = 0;
        Audio.erro(493.88, 0.12);
      }
      const cb = $('#ritmo-combo');
      if (cb) cb.style.width = `${Math.min(100, this.combo * 50)}%`;
      $('#jogo-contador').textContent =
        `${this.acertos} de ${this.tentativas} em cima · ${this.contador}/${this.total}`;
    },

    finalizar() {
      if (!this.ativo) return;
      this.ativo = false;
      this.julgando = false;
      Audio.stopMetronome();
      $('#btn-bater').hidden = true;
      // Sem toque nenhum não há o que medir: manda de volta pro começo em vez
      // de cravar 0% como se o jogador tivesse errado tudo.
      if (this.tentativas === 0) {
        J.finalizarFase(0, 0);
        return;
      }
      J.finalizarFase(this.acertos, this.tentativas);
    },

    parar() {
      this.ativo = false;
      this.julgando = false;
      Audio.stopMetronome();
    }
  };
  J.Ritmo = Ritmo;

  /* --- fases 2-15: ouve e responde, com combo e tempo --- */
  J.comecarFase = function (idFase, modo = 'praticar') {
    Audio.unlock();
    if (J.progresso.mudo) Audio.setMuted(true);
    Livre.parar();
    Ritmo.parar();
    // zera qualquer avanço ou demonstração pendente da tela anterior
    if (J.timerAvancar) { clearTimeout(J.timerAvancar); J.timerAvancar = null; }
    if (J.timerDemo) { clearTimeout(J.timerDemo); J.timerDemo = null; }

    const fase = Fases.porId(idFase);
    if (!fase) return;
    J.estado.fase = fase;
    J.estado.modo = modo;
    J.estado.idx = 0;
    J.estado.acertos = 0;
    J.estado.combo = 0;
    J.estado.melhorCombo = 0;
    J.estado.travado = false;

    $('#titulo-fase').textContent = `${fase.id}. ${fase.nome}`;
    $('#btn-voltar').hidden = false;
    mostrar('tela-jogo');

    if (fase.id === 1) { Ritmo.iniciar(); return; }

    const dados = Fases.gerar(idFase, modo);
    J.estado.rodadas = dados.rodadas;
    J.estado.total = dados.rodadas.length;
    $('#jogo-opcoes').hidden = false;
    $('#btn-bater').hidden = true;
    J.proximaRodada();
  };

  J.proximaRodada = function () {
    const e = J.estado;
    if (e.idx >= e.rodadas.length) { J.finalizarFase(e.acertos, e.rodadas.length); return; }

    const r = e.rodadas[e.idx];
    e.travado = false;
    e.escreveu = null;   // fase 19: zera as notas já colocadas nesta rodada
    $('#jogo-contador').textContent = `${e.idx + 1} / ${e.rodadas.length}`;
    $('#jogo-pergunta').textContent = J.perguntaDe(r);
    $('#jogo-dica').textContent = (e.modo === 'dominar') ? '' : (r.dica || '');
    $('#jogo-visual').innerHTML = '';
    $('#jogo-opcoes').innerHTML = '';

    J.montarVisual(r);
    J.montarOpcoes(r);
    J.tocarRodada(r);

    const cb = $('#ritmo-combo');
    if (cb) cb.style.width = `${Math.min(100, e.combo * 20)}%`;
  };

  J.perguntaDe = function (r) {
    const mapa = {
      direcao: 'A segunda nota subiu ou desceu?',
      contorno: 'Qual desenho elas fazem?',
      cor: 'Alegre ou triste?',
      teclado: 'Toque a nota que você ouviu.',
      degrau: 'Perto ou longe?',
      intervalo: 'Qual distância é essa?',
      solfejo: 'Que nota da escala é essa?',
      armadura: 'Quantas alterações?',
      acorde: 'Que acorde é esse?',
      rota: 'Quantos passos no círculo?',
      funcao: 'Esse acorde repousa, prepara ou empurra?',
      cadencia: 'Como essa frase termina?',
      modo: 'Que cor de escala é essa?',
      analise: 'Qual é a roda de acordes?',
      ler_pauta: 'Ouça a nota. Onde ela mora na pauta?',
      ouvir_pauta: 'Que som está escrito aqui?',
      figura: 'Qual figura dura isso?',
      escrever: 'Escreva a melodia que você ouviu.',
      ler_tocar: 'Leia a frase escrita. O que ela toca?',
      receita: 'Que receita de passos é essa escala?',
      empilhar: 'Que acorde essas terças empilhadas formam?',
      arpejo: 'Qual acorde esse arpejo está desenhando?',
      inversao: 'Qual nota do acorde está embaixo?',
      modo_origem: 'De qual grau da escala essa melodia parte?',
      compara_modo: 'Em qual passo esses dois modos divergem?',
      tempo_forte: 'Em qual tempo cai o acento mais forte?',
      contagem: 'Quantos tempos tem esse compasso?',
      formula: 'Qual fórmula de compasso é essa?',
      agrupamento: 'É marcha (dois) ou valsa (três)?',
      subdivisao: 'O compasso é simples ou composto?',
      pausa: 'Quanto tempo de silêncio é isso?',
      duracao: 'Essa nota dura um tempo, um e meio, ou dois?',
      deslocamento: 'O acento cai no tempo ou fora dele?',
      subdivisao_qtd: 'Quantas notas cabem nesse espaço?'
    };
    return mapa[r.tipo] || 'O que você ouviu?';
  };

  J.tocarRodada = function (r) {
    if (!r.oQueSoa) return;
    if (r.tipo === 'acorde' || r.tipo === 'funcao') {
      Audio.chord(r.oQueSoa, { dur: 1.1, timbre: 'piano', gain: 0.4 });
    } else if (r.tipo === 'cadencia' || r.tipo === 'analise') {
      J.tocarProgressao(r.progressaoHz);
    } else if (r.tipo === 'intervalo') {
      Audio.note(r.oQueSoa[0], { dur: 0.7, timbre: 'cristal', gain: 0.42 });
      setTimeout(() => Audio.note(r.oQueSoa[1], { dur: 1.3, timbre: 'cristal', gain: 0.42 }), 700);
    } else if (r.tipo === 'figura') {
      // o ritmo soa com durações reais: é o que a figura escreve
      Audio.seq(r.sequencia.map(x => [x[0], x[1]]), { timbre: 'piano', gain: 0.42, gap: 0.02 });
    } else if (r.tipo === 'arpejo') {
      // as notas do acorde uma depois da outra, com a duração do arpejo real
      const seq = r.arpejoHz.map(hz => [hz, 0.26]);
      Audio.seq(seq, { timbre: 'piano', gain: 0.42, gap: 0.015 });
    } else if (r.tipo === 'receita' || r.tipo === 'modo_origem') {
      // escala completa, nota por nota, para o ouvido seguir a receita
      Audio.seq(r.oQueSoa.map(hz => [hz, 0.34]), { timbre: 'cristal', gain: 0.4, gap: 0.02 });
    } else if (r.tipo === 'compara_modo') {
      // modo A, pequena pausa, modo B: a comparação é o exercício
      const a = (r.hzA || []).map(hz => [hz, 0.3]);
      const b = (r.hzB || []).map(hz => [hz, 0.3]);
      Audio.seq(a, { timbre: 'cristal', gain: 0.38, gap: 0.02 });
      const esperaA = a.reduce((s, x) => s + x[1] + 0.02, 0) + 0.35;
      setTimeout(() => Audio.seq(b, { timbre: 'cristal', gain: 0.38, gap: 0.02 }), esperaA * 1000);
    } else if (r.tipo === 'empilhar' || r.tipo === 'inversao') {
      // o acorde como bloco: é o contraste com o arpejo
      Audio.chord(r.oQueSoa, { dur: 1.3, timbre: 'piano', gain: 0.4 });
    } else if (['tempo_forte', 'contagem', 'formula', 'agrupamento', 'subdivisao'].includes(r.tipo)) {
      // O RITMO soa como compasso de verdade: acento no forte, fraco nos
      // outros. É a única forma de o ouvido aprender métrica.
      J.tocarCompasso(r);
    } else if (r.tipo === 'pausa') {
      // O silêncio é o conteúdo. Toca uma nota longa e deixa o silêncio
      // depois dela ser tão audível quanto o som.
      Audio.note(r.oQueSoa[0], { dur: Math.max(0.3, r.pausa.valor * 0.42), timbre: 'piano', gain: 0.42 });
    } else if (r.tipo === 'duracao') {
      // a duração real: 1, 1,5 ou 2 tempos a ~90bpm
      const segPorTempo = 0.62;
      const total = r.total * segPorTempo;
      if (r.correta === 'ligada') {
        // duas notas ligadas: soa contínuo
        Audio.note(r.oQueSoa[0], { dur: total, timbre: 'piano', gain: 0.42 });
      } else {
        Audio.note(r.oQueSoa[0], { dur: total, timbre: 'piano', gain: 0.42 });
      }
    } else if (r.tipo === 'deslocamento') {
      // o padrão de ataques: é o deslocamento que o ouvido precisa sentir
      Audio.seq(r.sequencia.map(x => [x[0], x[1]]), { timbre: 'piano', gain: 0.42, gap: 0.01 });
      // junto com o pulso de fundo, para comparar ataque contra tempo
      const pulso = 0.4;
      for (let k = 0; k < 4; k++) {
        Audio.metronomo(k === 0);
      }
    } else if (r.tipo === 'subdivisao_qtd') {
      // as N notas no espaço, com o pulso subjacente
      const cada = 0.5;
      r.oQueSoa.forEach((hz, i) => {
        Audio.note(hz, { dur: cada * 0.85, timbre: 'piano', gain: 0.4, at: Audio.now() + 0.03 + i * cada });
      });
    } else if (r.tipo === 'escrever' || r.tipo === 'ler_tocar' || r.tipo === 'ler_pauta') {
      // melodia: toca em sequência, uma nota clara depois da outra
      Audio.seq(r.oQueSoa.map(hz => [hz, 0.5]), { timbre: 'cristal', gain: 0.42, gap: 0.1 });
    } else if (r.tipo === 'ouvir_pauta') {
      // mostra a nota escrita e toca só ela; os candidatos são ouvidos
      // um a um pelo próprio jogador (no botão)
      Audio.note(r.oQueSoa[0], { dur: 1.0, timbre: 'cristal', gain: 0.42 });
    } else if (r.tipo === 'cor') {
      Audio.seq(r.oQueSoa.map(hz => [hz, 0.32]), { timbre: 'piano', gain: 0.32, gap: 0.015 });
    } else if (r.oQueSoa.length === 1) {
      Audio.note(r.oQueSoa[0], { dur: 1.2, timbre: 'cristal', gain: 0.42 });
    } else {
      Audio.seq(r.oQueSoa.map(hz => [hz, 0.5]), { timbre: 'cristal', gain: 0.4, gap: 0.07 });
    }
  };

  /**
   * Toca um compasso de verdade: acento no forte, fraco nos outros tempos.
   * É assim que o ouvido aprende métrica — não descrevendo, tocando.
   */
  J.tocarCompasso = function (r) {
    const c = r.compasso || Teoria.compasso('4/4');
    const bpm = r.bpm || 76;
    const segPorTempo = 60 / bpm;
    const t0 = Audio.now() + 0.06;
    for (let i = 0; i < c.tempos; i++) {
      const at = t0 + i * segPorTempo;
      const forte = c.forte.includes(i + 1);
      const meio = c.meio.includes(i + 1);
      // no compasso composto cada tempo vale 3 subdivisões
      const subdiv = c.subdivisao === 'composto' ? 3 : 1;
      for (let s = 0; s < subdiv; s++) {
        const at2 = at + s * (segPorTempo / subdiv);
        const ganho = s === 0 ? (forte ? 0.46 : meio ? 0.34 : 0.24) : 0.16;
        Audio.note(Teoria.hzDoMidi(67), { dur: 0.3, timbre: 'piano', gain: ganho, at: at2 });
      }
    }
  };

  J.tocarProgressao = function (hzs) {
    if (!hzs) return;
    const t = Audio.now() + 0.05;
    hzs.forEach((grupo, i) => {
      const hz = Array.isArray(grupo) ? grupo : [grupo];
      Audio.chord(hz, { dur: 0.7, timbre: 'piano', gain: 0.34, at: t + i * 0.78 });
    });
  };

  /* ====================================================================
     CAPÍTULO DA HARMONIA (21-26)
     Escalas, acordes, arpejos e modos — desenhados, não apenas nomeados.
     ==================================================================== */

  /** Desenha uma sequência de notas como degraus: vê-se o salto de cada passo. */
  function desenharDegraus(vis, hzs, opts = {}) {
    const larg = Math.min(window.innerWidth - 90, 520);
    const alt = 150;
    const dpr = Math.min(2.5, window.devicePixelRatio || 1);
    const cv = document.createElement('canvas');
    cv.className = 'cv-pauta';
    cv.style.width = larg + 'px';
    cv.style.height = alt + 'px';
    cv.width = Math.floor(larg * dpr);
    cv.height = Math.floor(alt * dpr);
    vis.appendChild(cv);
    const c = cv.getContext('2d');
    c.setTransform(dpr, 0, 0, dpr, 0, 0);

    // converte Hz em altura relativa (log, como o ouvido percebe)
    const hz = hzs.filter(h => h && isFinite(h));
    if (!hz.length) return;
    const log = hz.map(h => Math.log2(h));
    const lo = Math.min(...log), hi = Math.max(...log);
    const faixa = Math.max(0.05, hi - lo);
    const padX = 34, padY = 24;
    const passoX = (larg - padX * 2) / Math.max(1, hz.length - 1);
    const y = (l) => alt - padY - ((l - lo) / faixa) * (alt - padY * 2);

    // linha de base tracejada
    c.save();
    c.strokeStyle = 'rgba(244,239,228,0.12)';
    c.setLineDash([4, 6]);
    c.beginPath();
    c.moveTo(padX, alt - padY + 6);
    c.lineTo(larg - padX, alt - padY + 6);
    c.stroke();
    c.restore();

    // degraus
    const cor = opts.cor || '#f0d18a';
    c.save();
    c.strokeStyle = cor;
    c.lineWidth = 2.4;
    c.lineCap = 'round';
    c.beginPath();
    hz.forEach((_, i) => {
      const px = padX + passoX * i;
      const py = y(log[i]);
      i === 0 ? c.moveTo(px, py) : c.lineTo(px, py);
    });
    c.stroke();
    c.restore();

    // marcadores + nome do intervalo entre cada par (se pedido)
    c.save();
    c.font = '600 11px "Segoe UI", system-ui, sans-serif';
    c.textAlign = 'center';
    c.textBaseline = 'middle';
    hz.forEach((h, i) => {
      const px = padX + passoX * i;
      const py = y(log[i]);
      c.fillStyle = cor;
      c.beginPath(); c.arc(px, py, 4.5, 0, Math.PI * 2); c.fill();
      // etiqueta do passo até a próxima nota
      if (i < hz.length - 1 && opts.nomesPassos) {
        const meio = (px + padX + passoX * (i + 1)) / 2;
        const diff = opts.nomesPassos[i];
        if (diff) {
          c.fillStyle = diff === 1 ? 'rgba(95,179,161,0.95)' : 'rgba(217,169,74,0.95)';
          c.fillText(diff === 1 ? '½' : '1', meio, alt - padY + 22);
        }
      }
    });
    c.restore();
  }

  /** Fase 21: a receita de passos, desenhada como escada. */
  function visualReceita(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    desenharDegraus(vis, r.hz, { nomesPassos: r.receita });
    const el = document.createElement('div');
    el.className = 'receita-tag';
    el.textContent = r.receita.join(' – ');
    vis.appendChild(el);
  }

  /** Fase 22: as terças empilhadas, mostrando o que forma o acorde. */
  function visualEmpilhar(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'pilha-tercas';
    const notas = r.notasNomes || [];
    notas.slice().reverse().forEach((n, i) => {
      const b = document.createElement('span');
      b.className = 'terca-pilha';
      b.textContent = n;
      b.style.setProperty('--i', i);
      b.style.width = (60 + i * 22) + 'px';
      wrap.appendChild(b);
    });
    vis.appendChild(wrap);
    const el = document.createElement('div');
    el.className = 'receita-tag';
    el.textContent = notas.join(' + ');
    vis.appendChild(el);
  }

  /** Fase 23: o arpejo em fila, com a ordem visível. */
  function visualArpejo(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'arpejo-fila';
    (r.notasNomes || []).forEach((n, i) => {
      const b = document.createElement('span');
      b.className = 'arpejo-nota';
      b.textContent = n;
      b.style.setProperty('--i', i);
      wrap.appendChild(b);
    });
    vis.appendChild(wrap);
  }

  /** Fase 24: as três inversões do acorde, lado a lado, com o baixo marcado. */
  function visualInversao(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const inv = r.inversao;
    const wrap = document.createElement('div');
    wrap.className = 'inversao-box';
    wrap.innerHTML = `
      <span class="ac-nome">${inv.nome}</span>
      <small>${inv.cifraBaixo}</small>
      <div class="inv-notas">${inv.notas.map((m, i) =>
        `<span class="inv-nota${i === 0 ? ' baixo' : ''}">${Teoria.grafiaDe(((m % 12) + 12) % 12)}</span>`
      ).join('')}</div>
      <small class="miudo">a nota destacada é a que está embaixo</small>
    `;
    vis.appendChild(wrap);
  }

  /** Fase 25: a escala maior marcada, com o ponto de partida destacado. */
  function visualModoOrigem(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larg = Math.min(window.innerWidth - 90, 520);
    const alt = 130;
    const dpr = Math.min(2.5, window.devicePixelRatio || 1);
    const cv = document.createElement('canvas');
    cv.className = 'cv-pauta';
    cv.style.width = larg + 'px';
    cv.style.height = alt + 'px';
    cv.width = Math.floor(larg * dpr);
    cv.height = Math.floor(alt * dpr);
    vis.appendChild(cv);
    const c = cv.getContext('2d');
    c.setTransform(dpr, 0, 0, dpr, 0, 0);

    // desenha as 7 notas brancas como degraus, com o início destacado
    const base = r.todasNotas || [];
    const hz = base.map(h => h);
    if (!hz.length) return;
    const log = hz.map(h => Math.log2(h));
    const lo = Math.min(...log), hi = Math.max(...log);
    const faixa = Math.max(0.05, hi - lo);
    const padX = 30, padY = 22;
    const passoX = (larg - padX * 2) / Math.max(1, hz.length - 1);
    const y = (l) => alt - padY - ((l - lo) / faixa) * (alt - padY * 2);

    c.save();
    c.strokeStyle = 'rgba(244,239,228,0.22)';
    c.lineWidth = 2;
    c.beginPath();
    hz.forEach((_, i) => {
      const px = padX + passoX * i, py = y(log[i]);
      i === 0 ? c.moveTo(px, py) : c.lineTo(px, py);
    });
    c.stroke();

    // marcadores: o inicial em dourado e maior
    hz.forEach((_, i) => {
      const px = padX + passoX * i, py = y(log[i]);
      const inicial = i === 0;
      c.fillStyle = inicial ? '#f0d18a' : 'rgba(244,239,228,0.30)';
      c.beginPath(); c.arc(px, py, inicial ? 7 : 4, 0, Math.PI * 2); c.fill();
      if (inicial) {
        c.strokeStyle = 'rgba(240,209,138,0.5)';
        c.lineWidth = 2;
        c.beginPath(); c.arc(px, py, 12, 0, Math.PI * 2); c.stroke();
      }
    });
    c.restore();

    const el = document.createElement('div');
    el.className = 'receita-tag';
    el.textContent = 'de onde a escala começa?';
    vis.appendChild(el);
  }

  /** Fase 26: dois modos lado a lado, com os passos visíveis para comparar. */
  function visualComparaModo(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'modos-lado';
    [[r.modoA, r.receitaA, 'A'], [r.modoB, r.receitaB, 'B']].forEach(([m, rec, tag]) => {
      const box = document.createElement('div');
      box.className = 'modo-box';
      box.innerHTML = `
        <b>${tag}</b>
        <small>${m.nome}</small>
        <span class="receita-mini">${rec.join('-')}</span>
      `;
      wrap.appendChild(box);
    });
    vis.appendChild(wrap);
  }

  /* ====================================================================
     CAPÍTULO DO RITMO (27-33)
     O compasso precisa ser VISTO para fazer sentido: barra, tempos, acentos.
     ==================================================================== */

  /**
   * Desenha um compasso com os tempos marcados.
   * Mostra a barra, a fórmula e quais tempos são fortes.
   */
  function desenharCompasso(vis, c, opts = {}) {
    const larg = Math.min(window.innerWidth - 90, 520);
    const alt = 132;
    const dpr = Math.min(2.5, window.devicePixelRatio || 1);
    const cv = document.createElement('canvas');
    cv.className = 'cv-compasso';
    cv.style.width = larg + 'px';
    cv.style.height = alt + 'px';
    cv.width = Math.floor(larg * dpr);
    cv.height = Math.floor(alt * dpr);
    vis.appendChild(cv);
    const g2 = cv.getContext('2d');
    g2.setTransform(dpr, 0, 0, dpr, 0, 0);

    const padX = 46, padY = 34;
    const larguraUtil = larg - padX * 2;
    const n = c.porCompasso;
    const passo = larguraUtil / n;
    const yLinha = alt - padY;

    // fórmula de compasso grande à esquerda
    g2.save();
    g2.fillStyle = '#f0d18a';
    g2.textAlign = 'center';
    g2.textBaseline = 'middle';
    g2.font = '700 22px "Segoe UI", system-ui, sans-serif';
    g2.fillText(String(c.numerador), padX - 22, padY + 8);
    g2.fillText(String(c.denominador), padX - 22, padY + 34);
    g2.restore();

    // linha do tempo
    g2.save();
    g2.strokeStyle = 'rgba(244,239,228,0.5)';
    g2.lineWidth = 2;
    g2.beginPath();
    g2.moveTo(padX - 10, yLinha);
    g2.lineTo(larg - padX + 10, yLinha);
    g2.stroke();

    // barra final
    g2.lineWidth = 2.5;
    g2.beginPath();
    g2.moveTo(larg - padX + 10, yLinha - 22);
    g2.lineTo(larg - padX + 10, yLinha + 8);
    g2.stroke();
    g2.restore();

    // cada tempo: ponto, número, marca de acento
    for (let i = 0; i < n; i++) {
      const x = padX + passo * i + passo / 2;
      const ehForte = c.forte.includes(i + 1);
      const ehMeio = c.meio.includes(i + 1);
      g2.save();
      // ponto do tempo
      g2.fillStyle = ehForte ? '#f0d18a' : ehMeio ? 'rgba(217,169,74,0.6)' : 'rgba(244,239,228,0.28)';
      g2.beginPath();
      g2.arc(x, yLinha, ehForte ? 7 : 4.5, 0, Math.PI * 2);
      g2.fill();
      // anel no forte
      if (ehForte) {
        g2.strokeStyle = 'rgba(240,209,138,0.5)';
        g2.lineWidth = 2;
        g2.beginPath(); g2.arc(x, yLinha, 13, 0, Math.PI * 2); g2.stroke();
      }
      // número do tempo
      g2.fillStyle = ehForte ? '#f0d18a' : 'rgba(244,239,228,0.45)';
      g2.font = (ehForte ? '700 ' : '400 ') + '12px "Segoe UI", system-ui, sans-serif';
      g2.textAlign = 'center';
      g2.textBaseline = 'top';
      g2.fillText(String(i + 1), x, yLinha + 20);
      g2.restore();
    }

    // agrupamento (mostra a subdivisão)
    if (opts.grupos) {
      g2.save();
      g2.strokeStyle = 'rgba(95,179,161,0.5)';
      g2.lineWidth = 2;
      g2.setLineDash([4, 4]);
      const tamGrupo = c.porCompasso / c.tempos;
      for (let t = 1; t < c.tempos; t++) {
        const x = padX + passo * tamGrupo * t;
        g2.beginPath();
        g2.moveTo(x, yLinha - 34);
        g2.lineTo(x, yLinha + 10);
        g2.stroke();
      }
      g2.restore();
    }

    return { cv, g: g2, larg, alt };
  }

  function visualCompasso(r) { desenharCompasso($('#jogo-visual'), r.compasso, { grupos: true }); }
  function visualAgrupamento(r) { desenharCompasso($('#jogo-visual'), r.compasso, { grupos: true }); }
  function visualSubdivisao(r) { desenharCompasso($('#jogo-visual'), r.compasso, { grupos: true }); }

  /** Fase 30: a pausa desenhada na pauta, no lugar da nota. */
  function visualPausa(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larg = Math.min(window.innerWidth - 90, 460);
    const alt = 120;
    const dpr = Math.min(2.5, window.devicePixelRatio || 1);
    const cv = document.createElement('canvas');
    cv.className = 'cv-pauta';
    cv.style.width = larg + 'px';
    cv.style.height = alt + 'px';
    cv.width = Math.floor(larg * dpr);
    cv.height = Math.floor(alt * dpr);
    vis.appendChild(cv);
    const c = cv.getContext('2d');
    c.setTransform(dpr, 0, 0, dpr, 0, 0);

    const espaco = 13;
    const y0 = (alt - espaco * 4) / 2;
    const linhas = [];
    c.save();
    c.strokeStyle = 'rgba(244,239,228,0.3)';
    c.lineWidth = 1.2;
    for (let i = 0; i < 5; i++) {
      linhas.push(y0 + i * espaco);
      c.beginPath(); c.moveTo(20, y0 + i * espaco); c.lineTo(larg - 20, y0 + i * espaco); c.stroke();
    }
    c.restore();

    // desenha o símbolo da pausa
    const cx = larg * 0.5, cy = linhas[2];
    c.save();
    c.strokeStyle = '#f0d18a';
    c.fillStyle = '#f0d18a';
    c.lineWidth = 2.2;
    switch (r.correta) {
      case 'semibreve':
        c.fillRect(cx - 16, linhas[1] - 3, 32, 6);
        break;
      case 'minima':
        c.fillRect(cx - 14, linhas[2] - 3, 28, 6);
        break;
      case 'seminima':
        c.beginPath();
        c.moveTo(cx - 10, cy - 14);
        c.lineTo(cx + 2, cy + 6);
        c.lineTo(cx + 8, cy - 2);
        c.lineTo(cx - 4, cy - 18);
        c.closePath();
        c.fill();
        break;
      case 'colcheia':
        c.beginPath();
        c.moveTo(cx - 6, cy - 12);
        c.lineTo(cx + 2, cy + 4);
        c.stroke();
        c.beginPath();
        c.arc(cx + 4, cy + 6, 4, 0, Math.PI * 2);
        c.fill();
        break;
      case 'semicolcheia':
        c.beginPath();
        c.moveTo(cx - 6, cy - 12);
        c.lineTo(cx + 2, cy + 4);
        c.stroke();
        c.beginPath(); c.arc(cx + 4, cy + 6, 4, 0, Math.PI * 2); c.fill();
        c.beginPath(); c.arc(cx - 4, cy + 10, 4, 0, Math.PI * 2); c.fill();
        break;
    }
    c.restore();
  }

  /** Fase 31: mostra a nota com ponto ou ligadura. */
  function visualDuracao(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larg = Math.min(window.innerWidth - 90, 420);
    const alt = 120;
    const dpr = Math.min(2.5, window.devicePixelRatio || 1);
    const cv = document.createElement('canvas');
    cv.className = 'cv-pauta';
    cv.style.width = larg + 'px';
    cv.style.height = alt + 'px';
    cv.width = Math.floor(larg * dpr);
    cv.height = Math.floor(alt * dpr);
    vis.appendChild(cv);
    const c = cv.getContext('2d');
    c.setTransform(dpr, 0, 0, dpr, 0, 0);
    const espaco = 13;
    const y0 = (alt - espaco * 4) / 2;
    const linhas = [];
    c.save();
    c.strokeStyle = 'rgba(244,239,228,0.3)';
    c.lineWidth = 1.2;
    for (let i = 0; i < 5; i++) {
      linhas.push(y0 + i * espaco);
      c.beginPath(); c.moveTo(20, y0 + i * espaco); c.lineTo(larg - 20, y0 + i * espaco); c.stroke();
    }
    c.restore();
    const geo = { x: 20, y: y0, largura: larg - 40, espaco, linhas };
    const meio = larg * 0.5;
    const cheia = '#f0d18a';

    if (r.correta === 'simples') {
      Partitura.desenharNota(c, geo, { grau: 4, x: meio, cor: cheia, haste: true });
    } else if (r.correta === 'pontuada') {
      Partitura.desenharNota(c, geo, { grau: 4, x: meio - 10, cor: cheia, haste: true });
      c.save();
      c.fillStyle = cheia;
      c.font = '700 24px "Segoe UI Symbol", serif';
      c.textAlign = 'center'; c.textBaseline = 'middle';
      c.fillText('·', meio + 14, Partitura.yDoGrau(4, linhas));
      c.restore();
    } else {
      // ligada: duas notas com uma curva por cima
      Partitura.desenharNota(c, geo, { grau: 4, x: meio - 26, cor: cheia, haste: true });
      Partitura.desenharNota(c, geo, { grau: 4, x: meio + 16, cor: cheia, haste: true });
      const y = Partitura.yDoGrau(4, linhas);
      c.save();
      c.strokeStyle = cheia;
      c.lineWidth = 2.4;
      c.beginPath();
      c.moveTo(meio - 18, y + 16);
      c.quadraticCurveTo(meio - 5, y + 26, meio + 8, y + 16);
      c.stroke();
      c.restore();
    }
  }

  /** Fase 32: os ataques desenhados sobre a régua do compasso. */
  function visualDeslocamento(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larg = Math.min(window.innerWidth - 90, 520);
    const alt = 118;
    const dpr = Math.min(2.5, window.devicePixelRatio || 1);
    const cv = document.createElement('canvas');
    cv.className = 'cv-compasso';
    cv.style.width = larg + 'px';
    cv.style.height = alt + 'px';
    cv.width = Math.floor(larg * dpr);
    cv.height = Math.floor(alt * dpr);
    vis.appendChild(cv);
    const c = cv.getContext('2d');
    c.setTransform(dpr, 0, 0, dpr, 0, 0);

    const padX = 40, y = alt - 44;
    const larguraUtil = larg - padX * 2;
    const total = r.padrao.reduce((a, b) => a + b, 0);
    const porTempo = larguraUtil / total;

    // régua com os tempos marcados
    c.save();
    c.strokeStyle = 'rgba(244,239,228,0.45)';
    c.lineWidth = 2;
    c.beginPath(); c.moveTo(padX, y); c.lineTo(larg - padX, y); c.stroke();
    for (let t = 0; t <= total; t++) {
      const x = padX + porTempo * t;
      c.strokeStyle = t % 1 === 0 ? 'rgba(244,239,228,0.5)' : 'rgba(244,239,228,0.16)';
      c.beginPath(); c.moveTo(x, y - 6); c.lineTo(x, y + 6); c.stroke();
      if (t < total) {
        c.fillStyle = 'rgba(244,239,228,0.4)';
        c.font = '11px "Segoe UI", system-ui, sans-serif';
        c.textAlign = 'center'; c.textBaseline = 'top';
        c.fillText(String(t + 1), x + porTempo / 2, y + 12);
      }
    }
    c.restore();

    // barra do ataque: onde o som COMEÇA
    let t = 0;
    c.save();
    r.padrao.forEach((dur, i) => {
      const x0 = padX + porTempo * t;
      const x1 = padX + porTempo * (t + dur);
      const noTempo = Math.abs(t % 1) < 0.01;
      c.fillStyle = noTempo ? 'rgba(240,209,138,0.85)' : 'rgba(200,69,60,0.85)';
      c.fillRect(x0 + 2, y - 34, Math.max(4, x1 - x0 - 4), 16);
      // marca onde cai
      c.fillStyle = noTempo ? '#f0d18a' : '#e9948c';
      c.beginPath(); c.arc(x0 + 4, y - 38, 4, 0, Math.PI * 2); c.fill();
      t += dur;
    });
    c.restore();

    const el = document.createElement('div');
    el.className = 'receita-tag';
    el.textContent = 'dourado = cai no tempo · vermelho = fora do tempo';
    vis.appendChild(el);
  }

  /** Fase 33: as notas da subdivisão, com o espaço marcado. */
  function visualSubdivisaoQtd(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'arpejo-fila';
    for (let i = 0; i < r.subdivisao.quantas; i++) {
      const b = document.createElement('span');
      b.className = 'arpejo-nota';
      b.textContent = '♪';
      b.style.setProperty('--i', i);
      wrap.appendChild(b);
    }
    vis.appendChild(wrap);
    const el = document.createElement('div');
    el.className = 'receita-tag';
    el.textContent = r.subdivisao.desc;
    vis.appendChild(el);
  }

  J.montarVisual = function (r) {
    const vis = $('#jogo-visual');
    if (r.tipo === 'teclado') {
      const brancas = [60, 62, 64, 65, 67, 69, 71, 72];
      const nomes = ['Dó', 'Ré', 'Mi', 'Fá', 'Sol', 'Lá', 'Si', 'Dó'];
      const kb = document.createElement('div');
      kb.className = 'teclado';
      brancas.forEach((m, i) => {
        const t = document.createElement('button');
        t.className = 'tecla';
        t.dataset.midi = m;
        t.textContent = nomes[i];
        t.addEventListener('click', () => { Audio.note(Teoria.hzDoMidi(m), { dur: 0.6, timbre: 'cristal', gain: 0.4 }); J.responderTeclado(m, r, t); });
        kb.appendChild(t);
      });
      vis.appendChild(kb);
      return;
    }
    if (r.tipo === 'rota') {
      const circ = document.createElement('div');
      circ.className = 'circulo';
      Teoria.CIRCULO_QUINTAS.forEach((c, i) => {
        const el = document.createElement('span');
        el.className = 'casa' + (c === r.de ? ' de' : '') + (c === r.para ? ' para' : '');
        el.textContent = c;
        const ang = (i * 30 - 90) * Math.PI / 180;
        el.style.left = `${50 + Math.cos(ang) * 36}%`;
        el.style.top = `${50 + Math.sin(ang) * 36}%`;
        circ.appendChild(el);
      });
      vis.appendChild(circ);
      return;
    }
    if (r.tipo === 'armadura') {
      const el = document.createElement('div');
      el.className = 'armadura';
      el.innerHTML = `<b>${r.cifra}</b><small>maior · ${r.tipoAlt || ''}</small>`;
      vis.appendChild(el);
      return;
    }
    if (r.tipo === 'acorde' || r.tipo === 'funcao') {
      const el = document.createElement('div');
      el.className = 'acorde-card';
      el.innerHTML = `<span class="ac-nome">?</span><small>ouça e escolha</small>`;
      vis.appendChild(el);
      return;
    }
    if (r.tipo === 'cadencia' || r.tipo === 'analise') {
      const el = document.createElement('div');
      el.className = 'progressao';
      (r.nomes || []).forEach(() => {
        const s = document.createElement('span');
        s.className = 'ac-nome oculto';
        s.textContent = '?';
        el.appendChild(s);
      });
      vis.appendChild(el);
      return;
    }
    if (r.tipo === 'contorno') {
      const el = document.createElement('div');
      el.className = 'contorno';
      for (let i = 0; i < 3; i++) {
        const d = document.createElement('span');
        d.className = 'ponto';
        el.appendChild(d);
      }
      vis.appendChild(el);
      return;
    }

    // ---- capítulo da escrita: tudo desenhado em pauta real ----
    if (r.tipo === 'ler_pauta')   { visualLerPauta(r);    return; }
    if (r.tipo === 'ouvir_pauta') { visualOuvirPauta(r);  return; }
    if (r.tipo === 'figura')      { visualFigura(r);      return; }
    if (r.tipo === 'escrever')    { visualEscrever(r);    return; }
    if (r.tipo === 'ler_tocar')   { visualLerTocar(r);    return; }

    // ---- capítulo da harmonia ----
    if (r.tipo === 'receita')       { visualReceita(r);       return; }
    if (r.tipo === 'empilhar')      { visualEmpilhar(r);      return; }
    if (r.tipo === 'arpejo')        { visualArpejo(r);        return; }
    if (r.tipo === 'inversao')      { visualInversao(r);      return; }
    if (r.tipo === 'modo_origem')   { visualModoOrigem(r);    return; }
    if (r.tipo === 'compara_modo')  { visualComparaModo(r);   return; }

    // ---- capítulo do ritmo ----
    if (r.tipo === 'tempo_forte' || r.tipo === 'contagem' || r.tipo === 'formula') { visualCompasso(r); return; }
    if (r.tipo === 'agrupamento')     { visualAgrupamento(r);      return; }
    if (r.tipo === 'subdivisao')      { visualSubdivisao(r);      return; }
    if (r.tipo === 'pausa')           { visualPausa(r);           return; }
    if (r.tipo === 'duracao')         { visualDuracao(r);         return; }
    if (r.tipo === 'deslocamento')    { visualDeslocamento(r);    return; }
    if (r.tipo === 'subdivisao_qtd')  { visualSubdivisaoQtd(r);   return; }
    // ícone de onda para tipos baseados em escuta
    const el = document.createElement('div');
    el.className = 'onda-ouvir';
    el.innerHTML = '<span></span><span></span><span></span><span></span><span></span>';
    vis.appendChild(el);
  };

  /* ---------- desenho da PAUTA (capítulo da escrita) ----------
     A pauta é desenhada em canvas porque precisa de linha, clave e nota
     posicionadas com precisão musical — div não dá conta.
     ------------------------------------------------------------------ */

  /** Cria (ou reusa) um canvas de pauta dentro de #jogo-visual. */
  function canvasPauta(larguraCss, alturaCss) {
    let cv = document.getElementById('cv-pauta');
    if (!cv) {
      cv = document.createElement('canvas');
      cv.id = 'cv-pauta';
      cv.className = 'cv-pauta';
      $('#jogo-visual').appendChild(cv);
    }
    const dpr = Math.min(2.5, window.devicePixelRatio || 1);
    cv.style.width = larguraCss + 'px';
    cv.style.height = alturaCss + 'px';
    cv.width = Math.floor(larguraCss * dpr);
    cv.height = Math.floor(alturaCss * dpr);
    const c = cv.getContext('2d');
    c.setTransform(dpr, 0, 0, dpr, 0, 0);
    c.clearRect(0, 0, larguraCss, alturaCss);
    return { cv, c, w: larguraCss, h: alturaCss };
  }

  /** Geometria padrão da pauta para o canvas atual. */
  function geometriaPauta(w, h, opts = {}) {
    const espaco = opts.espaco ?? Math.max(11, Math.min(16, w / 34));
    const largura = w - 96;
    return { x: 62, y: (h - espaco * 4) / 2 - (opts.dy ?? 0), largura, espaco };
  }

  /** Desenha a pauta com uma nota destacada (fase 16) ou para ouvir (17). */
  function desenharPautaComNota(alvo, opts = {}) {
    const { c, w, h } = canvasPauta(Math.min(wOr(window.innerWidth - 90, 560), 560), 150);
    const geo = Partitura.desenharPauta(c, geometriaPauta(w, h, opts));

    // mostra todas as notas da zona como fantasmas (aprender) ou só a alvo
    if (opts.mostrarZona) {
      (opts.zona || []).forEach(g => {
        Partitura.desenharNota(c, geo, {
          grau: g, x: geo.x + geo.largura * 0.30,
          cor: 'rgba(244,239,228,0.22)', haste: false, alpha: 0.55
        });
      });
    }

    if (alvo !== null && alvo !== undefined) {
      Partitura.desenharNota(c, geo, {
        grau: alvo,
        x: geo.x + geo.largura * (opts.xRel ?? 0.45),
        cor: opts.cor || '#f0d18a',
        destaque: opts.destaque,
        rotulo: opts.rotulo,
        haste: true
      });
    }

    // marca a linha de baixo como âncora ("chão" da leitura)
    if (opts.ancora) {
      const y = Partitura.yDoGrau(0, geo.linhas);
      c.save();
      c.fillStyle = 'rgba(95,179,161,0.9)';
      c.font = '600 11px "Segoe UI", system-ui, sans-serif';
      c.textAlign = 'right';
      c.textBaseline = 'middle';
      c.fillText('Mi', geo.x - 10, y);
      c.beginPath();
      c.arc(geo.x - 26, y, 3.5, 0, Math.PI * 2);
      c.fill();
      c.restore();
    }

    return { geo, c, w, h };
  }

  function wOr(a, b) { return Math.min(a, b); }

  /** Fase 16: a nota soa, e você acha onde ela mora na pauta. */
  function visualLerPauta(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larguraCss = Math.min(window.innerWidth - 90, 560);
    const { c, w, h } = canvasPauta(larguraCss, 132);
    const geo = Partitura.desenharPauta(c, geometriaPauta(w, h));

    // as opções são as casas da pauta; cada uma é um alvo clicável
    const zona = r.zona || [0, 1, 2, 3, 4];
    zona.forEach(g => {
      Partitura.desenharNota(c, geo, {
        grau: g, x: geo.x + geo.largura * 0.30,
        cor: 'rgba(244,239,228,0.18)', haste: false
      });
    });
    // nota-alvo escondida no meio, como interrogação
    Partitura.desenharNota(c, geo, {
      grau: 2, x: geo.x + geo.largura * 0.62,
      cor: 'rgba(240,209,138,0.35)', haste: true
    });
    c.save();
    c.fillStyle = 'rgba(240,209,138,0.85)';
    c.font = '700 22px "Segoe UI", system-ui, sans-serif';
    c.textAlign = 'center';
    c.fillText('?', geo.x + geo.largura * 0.62, Partitura.yDoGrau(4, geo.linhas) - 6);
    c.restore();

    // casas clicáveis sobrepostas (alinham com os graus da zona)
    const casas = document.createElement('div');
    casas.className = 'casas-pauta';
    casas.style.width = larguraCss + 'px';
    zona.forEach(g => {
      const b = document.createElement('button');
      b.className = 'casa-pauta';
      b.dataset.midi = Partitura.midiDoGrau(g);
      b.dataset.grau = g;
      const yGrau = Partitura.yDoGrau(g, geo.linhas);
      b.style.top = (yGrau + 8) + 'px';   // +8 = offset do canvas dentro do wrapper
      b.style.left = (geo.x + geo.largura * 0.30 - 34) + 'px';
      b.title = Partitura.nomeDoGrau(g);
      b.textContent = Partitura.nomeDoGrau(g);
      b.addEventListener('click', () => {
        const midi = Partitura.midiDoGrau(g);
        Audio.note(Teoria.hzDoMidi(midi), { dur: 0.7, timbre: 'cristal', gain: 0.42 });
        J.responder(String(midi), { ...r, correta: r.correta }, b);
      });
      casas.appendChild(b);
    });
    vis.appendChild(casas);
    $('#jogo-opcoes').innerHTML = '<small class="ajuda">Toque o nome da nota no lugar certo da pauta.</small>';
  }

  /** Fase 17: vê a nota escrita e escolhe o som. */
  function visualOuvirPauta(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larguraCss = Math.min(window.innerWidth - 90, 520);
    const { c, w, h } = canvasPauta(larguraCss, 138);
    const geo = Partitura.desenharPauta(c, geometriaPauta(w, h));
    Partitura.desenharNota(c, geo, {
      grau: r.grau,
      x: geo.x + geo.largura * 0.34,
      cor: '#f0d18a', haste: true
    });
  }

  /** Fase 18: as figuras de tempo desenhadas. */
  function desenharFigura(c, x, y, espaco, desenho, cor) {
    c.save();
    c.strokeStyle = cor;
    c.fillStyle = cor;
    const r = espaco * 0.62;
    c.beginPath();
    c.ellipse(x, y, r * 1.18, r, -0.34, 0, Math.PI * 2);

    if (desenho === 'inteira') {
      c.lineWidth = 2.6; c.stroke();          // vazada, sem haste
    } else if (desenho === 'minima') {
      c.lineWidth = 2.6; c.stroke();          // vazada, com haste
      c.beginPath(); c.moveTo(x + r * 1.15, y); c.lineTo(x + r * 1.15, y - espaco * 3.2); c.stroke();
    } else if (desenho === 'seminima') {
      c.fill();                                // cheia, com haste
      c.beginPath(); c.moveTo(x + r * 1.15, y); c.lineTo(x + r * 1.15, y - espaco * 3.2);
      c.lineWidth = 2.2; c.stroke();
    } else if (desenho === 'colcheia') {
      c.fill();                                // cheia, haste + bandeirola
      const hx = x + r * 1.15, hy = y - espaco * 3.2;
      c.beginPath(); c.moveTo(hx, y); c.lineTo(hx, hy); c.lineWidth = 2.2; c.stroke();
      c.beginPath();
      c.moveTo(hx, hy);
      c.quadraticCurveTo(hx + espaco * 0.95, hy + espaco * 0.55, hx + espaco * 0.62, hy + espaco * 1.5);
      c.quadraticCurveTo(hx + espaco * 0.75, hy + espaco * 0.6, hx, hy + espaco * 0.35);
      c.closePath(); c.fill();
    }
    c.restore();
  }

  function visualFigura(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larguraCss = Math.min(window.innerWidth - 90, 460);
    const { c, w, h } = canvasPauta(larguraCss, 130);
    // pauta simples para apoiar as figuras
    const espaco = 13;
    const y0 = (h - espaco * 4) / 2;
    const linhas = [];
    c.save();
    c.strokeStyle = 'rgba(244,239,228,0.35)';
    c.lineWidth = 1.2;
    for (let i = 0; i < 5; i++) {
      linhas.push(y0 + i * espaco);
      c.beginPath(); c.moveTo(24, y0 + i * espaco); c.lineTo(w - 24, y0 + i * espaco); c.stroke();
    }
    c.restore();
    // desenha o padrão rítmico lado a lado, na linha do meio
    const n = r.figuras.length;
    const passo = (w - 80) / n;
    r.figuras.forEach((f, i) => {
      desenharFigura(c, 52 + passo * i + passo / 2, linhas[2], espaco, f.desenho, '#f0d18a');
    });
  }

  /** Fase 19: você arrasta as notas até a linha certa. */
  function visualEscrever(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larguraCss = Math.min(window.innerWidth - 90, 540);
    const { c, w, h } = canvasPauta(larguraCss, 168);
    const geo = Partitura.desenharPauta(c, geometriaPauta(w, h));

    // slots onde as notas devem entrar
    const n = r.graus.length;
    const passo = geo.largura / (n + 1);
    const slots = [];
    for (let i = 0; i < n; i++) {
      const x = geo.x + passo * (i + 1) * 0.85;
      slots.push({ x, grau: null });
      // guia pontilhado no slot
      c.save();
      c.setLineDash([3, 5]);
      c.strokeStyle = 'rgba(244,239,228,0.22)';
      c.beginPath();
      c.moveTo(x, geo.y - 8);
      c.lineTo(x, geo.y + geo.espaco * 4 + 8);
      c.stroke();
      c.restore();
    }

    // casa clicável por grau: a pessoa coloca a nota acertando a linha
    const casas = document.createElement('div');
    casas.className = 'casas-escrever';
    casas.style.width = larguraCss + 'px';
    const zona = [-2, -1, 0, 1, 2, 3, 4, 5, 6];
    zona.forEach(g => {
      const b = document.createElement('button');
      b.className = 'casa-escrever';
      b.dataset.grau = g;
      b.textContent = Partitura.nomeDoGrau(g);
      const yGrau = Partitura.yDoGrau(g, geo.linhas);
      b.style.top = (yGrau + 8) + 'px';
      b.addEventListener('click', () => {
        const midi = Partitura.midiDoGrau(g);
        Audio.note(Teoria.hzDoMidi(midi), { dur: 0.6, timbre: 'cristal', gain: 0.4 });
        J.escreverNota(g);
      });
      casas.appendChild(b);
    });
    vis.appendChild(casas);
  }

  /** Fase 20: a frase escrita, para ler e reconhecer. */
  function visualLerTocar(r) {
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larguraCss = Math.min(window.innerWidth - 90, 540);
    const { c, w, h } = canvasPauta(larguraCss, 150);
    const geo = Partitura.desenharPauta(c, geometriaPauta(w, h));
    const n = r.graus.length;
    const passo = geo.largura / (n + 1);
    r.graus.forEach((g, i) => {
      Partitura.desenharNota(c, geo, {
        grau: g,
        x: geo.x + passo * (i + 1) * 0.9,
        cor: '#f0d18a', haste: true
      });
    });
  }

  J.montarOpcoes = function (r) {
    if (r.tipo === 'teclado' || r.tipo === 'ler_pauta' || r.tipo === 'escrever') return;
    const cont = $('#jogo-opcoes');
    const rotulos = {
      direcao: { sobe: '↑ Subiu', desce: '↓ Desceu' },
      contorno: {
        '1,1': '↗↗ subiu, subiu', '1,-1': '↗↘ subiu, desceu',
        '-1,-1': '↘↘ desceu, desceu', '-1,1': '↘↗ desceu, subiu',
        '1,0': '↗→ subiu, parou', '-1,0': '↘→ desceu, parou'
      },
      cor: { alegre: 'Maior · alegre', triste: 'Menor · triste' },
      degrau: { curto: 'Perto · meio-tom', longo: 'Longe · tom inteiro' },
      figura: {
        inteira: 'Semibreve · o compasso todo',
        minima: 'Mínima · metade',
        seminima: 'Semínima · um tempo',
        colcheia: 'Colcheia · meio tempo'
      },
      inversao: { 'a raiz': 'a raiz (posição fundamental)', 'a terça': 'a terça (1ª inversão)', 'a quinta': 'a quinta (2ª inversão)' },
      modo_origem: { '1': '1º grau · Jônio', '2': '2º grau · Dórico', '3': '3º grau · Frígio', '4': '4º grau · Lídio', '5': '5º grau · Mixolídio', '6': '6º grau · Eólio', '7': '7º grau · Lócrio' },
      agrupamento: { marcha: 'Marcha · grupos de 2', valsa: 'Valsa · grupos de 3' },
      subdivisao: { simples: 'Simples · divide em 2', composto: 'Composto · divide em 3' },
      pausa: {
        semibreve: 'Semibreve · 4 tempos', minima: 'Mínima · 2 tempos',
        seminima: 'Semínima · 1 tempo', colcheia: 'Colcheia · meio tempo',
        semicolcheia: 'Semicolcheia · 1/4 de tempo'
      },
      duracao: { simples: 'Um tempo', pontuada: 'Um e meio (pontuada)', ligada: 'Dois tempos (ligada)' },
      deslocamento: {
        regular: 'No tempo, certinho', sincope: 'Síncope · atravessa o forte',
        contratempo: 'Contratempo · cai na pausa', anacruse: 'Anacruse · começa antes'
      }
    };
    const sufixo = (r.tipo === 'armadura' && r.tipoAlt)
      ? (r.tipoAlt === 'sustenidos' ? ' ♯' : ' ♭') : '';
    (r.opcoes || []).forEach(op => {
      const b = document.createElement('button');
      b.className = 'opcao';
      b.dataset.op = String(op);
      const rot = (rotulos[r.tipo] && rotulos[r.tipo][op]) || op;
      // na fase 17 o próprio botão toca o candidato ao passar/ouvir
      if (r.tipo === 'ouvir_pauta') {
        const hz = (r.opcoesHz || [])[r.opcoes.indexOf(op)];
        b.textContent = '♪ tocar';
        b.addEventListener('mouseenter', () => { if (hz) Audio.note(hz, { dur: 0.8, timbre: 'cristal', gain: 0.38 }); });
        b.addEventListener('click', () => { if (hz) Audio.note(hz, { dur: 0.8, timbre: 'cristal', gain: 0.38 }); });
      } else {
        b.textContent = rot + sufixo;
      }
      b.addEventListener('click', () => J.responder(op, r, b));
      cont.appendChild(b);
    });
  };

  J.responderTeclado = function (midi, r, teclaEl) {
    if (J.estado.travado) return;
    J.estado.travado = true;
    const acertou = midi === r.correta;
    const todas = $$('.tecla');
    todas.forEach(t => t.classList.remove('certa', 'errada'));
    if (acertou) {
      teclaEl.classList.add('certa'); J.registrarAcerto(true);
      Audio.acerto(Teoria.hzDoMidi(midi), 0.2);
    } else {
      teclaEl.classList.add('errada');
      const alvo = $(`.tecla[data-midi="${r.correta}"]`);
      if (alvo) alvo.classList.add('certa');
      J.registrarAcerto(false);
      Audio.erro(Teoria.hzDoMidi(r.correta), 0.16);
    }
    J.avancar(acertou ? 820 : 1300);
  };

  J.responder = function (op, r, btn) {
    if (J.estado.travado) return;
    J.estado.travado = true;
    const acertou = String(op) === String(r.correta);
    if (acertou) {
      btn.classList.add('certa');
      J.registrarAcerto(true);
      Audio.acerto(523.25, 0.19);
    } else {
      btn.classList.add('errada');
      [...btn.parentElement.children].forEach(b => {
        if (b.dataset && b.dataset.op === String(r.correta)) b.classList.add('certa');
      });
      J.registrarAcerto(false);
      const base = Array.isArray(r.oQueSoa?.[0]) ? 523.25 : (r.oQueSoa?.[0] || 523.25);
      Audio.erro(base, 0.16);
    }
    J.avancar(acertou ? 800 : 1250);
  };

  /* ---------- fase 19: escrever a melodia nota a nota ----------
     O jogador coloca as notas uma por uma. Cada nota tem de cair no grau
     certo. Acerta todas = acertou a rodada. Erra uma = a rodada está errada,
     mas ele vê onde errou e continua.
     ------------------------------------------------------------------ */
  J.escreverNota = function (grau) {
    const e = J.estado;
    if (e.travado) return;
    const r = e.rodadas[e.idx];
    if (!r || r.tipo !== 'escrever') return;

    const pos = e.escreveu ? e.escreveu.length : 0;
    if (pos >= r.graus.length) return;

    const esperado = r.graus[pos];
    const certo = grau === esperado;

    if (!e.escreveu) e.escreveu = [];
    e.escreveu.push({ grau, certo });

    // redesenha a pauta com o que já foi colocado
    const vis = $('#jogo-visual');
    vis.innerHTML = '';
    const larguraCss = Math.min(window.innerWidth - 90, 540);
    const dpr = Math.min(2.5, window.devicePixelRatio || 1);
    const cv = document.createElement('canvas');
    cv.className = 'cv-pauta';
    cv.style.width = larguraCss + 'px';
    cv.style.height = '168px';
    cv.width = Math.floor(larguraCss * dpr);
    cv.height = Math.floor(168 * dpr);
    vis.appendChild(cv);
    const c = cv.getContext('2d');
    c.setTransform(dpr, 0, 0, dpr, 0, 0);

    const geo = Partitura.desenharPauta(c, geometriaPauta(larguraCss, 168));
    const passo = geo.largura / (r.graus.length + 1);

    e.escreveu.forEach((n, i) => {
      const x = geo.x + passo * (i + 1) * 0.85;
      const cor = n.certo ? '#5fb3a1' : '#c8453c';
      Partitura.desenharNota(c, geo, { grau: n.grau, x, cor, haste: true });
      // mostra a certa quando errou, para aprender
      if (!n.certo) {
        Partitura.desenharNota(c, geo, {
          grau: r.graus[i], x, cor: 'rgba(240,209,138,0.9)', haste: false, alpha: 0.75
        });
      }
    });

    // slots ainda vazios
    for (let i = e.escreveu.length; i < r.graus.length; i++) {
      const x = geo.x + passo * (i + 1) * 0.85;
      c.save();
      c.setLineDash([3, 5]);
      c.strokeStyle = 'rgba(244,239,228,0.25)';
      c.beginPath();
      c.moveTo(x, geo.y - 8);
      c.lineTo(x, geo.y + geo.espaco * 4 + 8);
      c.stroke();
      c.restore();
    }

    // casas clicáveis, agora reposicionadas
    const casas = document.createElement('div');
    casas.className = 'casas-escrever';
    casas.style.width = larguraCss + 'px';
    [-2, -1, 0, 1, 2, 3, 4, 5, 6].forEach(g => {
      const b = document.createElement('button');
      b.className = 'casa-escrever';
      b.dataset.grau = g;
      b.textContent = Partitura.nomeDoGrau(g);
      b.style.top = (Partitura.yDoGrau(g, geo.linhas) + 8) + 'px';
      b.addEventListener('click', () => {
        Audio.note(Teoria.hzDoMidi(Partitura.midiDoGrau(g)), { dur: 0.6, timbre: 'cristal', gain: 0.4 });
        J.escreverNota(g);
      });
      casas.appendChild(b);
    });
    vis.appendChild(casas);

    // feedback imediato por nota
    if (certo) {
      Audio.acerto(Teoria.hzDoMidi(Partitura.midiDoGrau(grau)), 0.18);
    } else {
      Audio.erro(Teoria.hzDoMidi(Partitura.midiDoGrau(esperado)), 0.15);
    }

    // terminou a melodia?
    if (e.escreveu.length === r.graus.length) {
      e.travado = true;
      const todosCertos = e.escreveu.every(n => n.certo);
      J.registrarAcerto(todosCertos);
      $('#jogo-opcoes').innerHTML = '';
      J.avancar(todosCertos ? 1000 : 1600);
    }
  };

  J.registrarAcerto = function (bom) {
    const e = J.estado;
    if (bom) {
      e.acertos++;
      e.combo++;
      e.melhorCombo = Math.max(e.melhorCombo, e.combo);
      Escore.estourar(window.innerWidth / 2, window.innerHeight * 0.4, true);
      if (e.combo >= 3) J.flashCombo(e.combo);
    } else {
      e.combo = 0;
    }
  };

  J.flashCombo = function (n) {
    const el = document.createElement('div');
    el.className = 'combo-flash';
    el.textContent = `${n} seguidas!`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 1100);
  };

  J.avancar = function (ms) {
    // Cancela um avanço pendente antes de agendar outro. Sem isso, um timeout
    // antigo pode disparar depois de o jogador trocar de fase e embaralhar o
    // estado — a rodada fica travada e o jogo parece congelar.
    if (J.timerAvancar) { clearTimeout(J.timerAvancar); J.timerAvancar = null; }
    J.timerAvancar = setTimeout(() => {
      J.timerAvancar = null;
      J.estado.idx++;
      J.proximaRodada();
    }, ms);
  };

  /* ======================================================================
     FIM DE FASE: celebrado, com estrelas
     ====================================================================== */

  J.finalizarFase = function (acertos, total) {
    const fase = J.estado.fase;
    if (!fase) return;

    // Caso especial do ritmo: ninguém tocou nada. Não é fracasso, é falta de
    // tentativa. Devolve uma tela clara em vez de cravar 0% como se a pessoa
    // tivesse errado — isso desmotiva quem só abriu para ver.
    const semTentativa = total === 0;

    const pct = semTentativa ? 0
      : Math.max(0, Math.min(100, Math.round((acertos / Math.max(1, total)) * 100)));

    // estrelas: 1 = passou, 2 = bem, 3 = excelente
    const estrelas = semTentativa ? 0 : (pct >= 90 ? 3 : pct >= 70 ? 2 : pct >= 50 ? 1 : 0);
    const dominou = !semTentativa && pct >= 80;

    if (!semTentativa) {
      if ((J.progresso.melhor[fase.id] || 0) < pct) J.progresso.melhor[fase.id] = pct;
      if ((J.progresso.estrelas[fase.id] || 0) < estrelas) J.progresso.estrelas[fase.id] = estrelas;
    }
    let novo = false;
    if (dominou && !J.progresso.dominadas.includes(fase.id)) {
      J.progresso.dominadas.push(fase.id);
      J.progresso.selos.push(fase.id);
      novo = true;
    }
    salvar();

    mostrar('tela-fim');
    $('#fim-titulo').textContent = semTentativa ? 'Você só escutou.'
      : novo ? 'Fase dominada!'
      : dominou ? 'De novo, firme.'
      : 'Continue tentando.';
    $('#fim-pct').textContent = semTentativa ? '—' : `${pct}%`;
    $('#fim-detalhe').textContent = semTentativa
      ? 'Bata no botão junto com o pulso para valer.'
      : `${acertos} de ${total} · melhor combo ${J.estado.melhorCombo}`;

    // estrelas aparecem uma a uma
    const box = $('#fim-estrelas');
    box.innerHTML = '';
    for (let i = 0; i < 3; i++) {
      const s = document.createElement('span');
      s.className = 'estrela' + (i < estrelas ? ' ganha' : '');
      s.textContent = '★';
      s.style.animationDelay = `${0.15 + i * 0.18}s`;
      box.appendChild(s);
    }
    if (estrelas > 0) Audio.selo();

    const dom = J.progresso.dominadas.length;
    const est = Voz.estagioDasDominadas(J.progresso.dominadas);
    $('#fim-estagio').textContent = est.nome;
    $('#fim-estagio-desc').textContent = est.desc;

    const prox = Fases.LISTA.find(f => !J.progresso.dominadas.includes(f.id));
    $('#fim-prox').textContent = prox ? `Próxima parada: ${prox.id}. ${prox.nome}` : 'Você dominou todas as 15 fases.';

    J.desenharRetrato();
  };

  J.desenharRetrato = function () {
    const dom = J.progresso.dominadas.length;
    let gesto = 0;
    Engine.play({
      update(dt, t) { gesto = 0.5 + 0.5 * Math.sin(t * 0.9); },
      draw(ctx, w, h, t) {
        Voz.retratoEstagio(ctx, w, h, J.progresso.dominadas, t);
      }
    });
  };

  /* ======================================================================
     CAPA: já jogável
     ====================================================================== */

  J.montarCapa = function () {
    const grid = $('#grid-fases');
    if (!grid) return;
    grid.innerHTML = '';

    // Cada capítulo começa com um divisor. O mapa dos capítulos:
    const CAPITULOS = {
      ritmo:    { cls: 'ritmo',    rotulo: '♩ CAPÍTULO DO RITMO · COMPASSO E MÉTRICA' },
      escrita:  { cls: 'escrita',  rotulo: '✎ CAPÍTULO DA ESCRITA · PARTITURA' },
      harmonia: { cls: 'harmonia', rotulo: '♪ CAPÍTULO DA HARMONIA · ESCALAS, ACORDES E MODOS' }
    };
    const jaPosto = {};

    Fases.LISTA.forEach(f => {
      const cap = CAPITULOS[f.nivel];
      if (cap && !jaPosto[f.nivel]) {
        jaPosto[f.nivel] = true;
        const d = document.createElement('div');
        d.className = 'grid-divisor ' + cap.cls;
        d.innerHTML = `<span>${cap.rotulo}</span>`;
        grid.appendChild(d);
      }
      const est = J.progresso.estrelas[f.id] || 0;
      const feita = J.progresso.dominadas.includes(f.id);
      const b = document.createElement('button');
      b.className = 'no' + (feita ? ' feita' : '') + (cap ? ' ' + cap.cls : '');
      b.dataset.fase = f.id;
      b.title = f.nome;
      b.innerHTML = `
        <span class="no-num">${f.id}</span>
        <span class="no-nome">${f.nome}</span>
        <span class="no-estrelas">${'★'.repeat(est)}${'☆'.repeat(3 - est)}</span>
      `;
      b.addEventListener('click', () => J.abrirFase(f.id));
      grid.appendChild(b);
    });
    J.atualizarCapa();
  };

  J.atualizarCapa = function () {
    const dom = J.progresso.dominadas.length;
    const est = Voz.estagioDasDominadas(J.progresso.dominadas);
    const el = $('#capa-estagio');
    if (el) el.textContent = est.nome;
    const barra = $('#capa-barra');
    if (barra) barra.style.width = `${(dom / Fases.total) * 100}%`;
    const cont = $('#capa-contagem');
    if (cont) cont.textContent = `${dom} / ${Fases.total}`;
    const rec = $('#livre-recorde');
    if (rec && J.progresso.livreRecorde) rec.textContent = `Melhor sequência: ${J.progresso.livreRecorde}`;
  };

  /* --- escolha de modo como um jogo, não uma lista --- */
  J.abrirFase = function (id) {
    Audio.tick();
    const f = Fases.porId(id);
    if (!f) return;
    J.estado.fasePendente = f;

    $('#titulo-fase').textContent = `${f.id}. ${f.nome}`;
    $('#btn-voltar').hidden = false;
    mostrar('tela-modo');

    $('#modo-ensina').textContent = f.ensina;
    $('#modo-ouve').textContent = f.ouve;

    const est = J.progresso.estrelas[f.id] || 0;
    $('#modo-estrelas').textContent = est ? '★'.repeat(est) + '☆'.repeat(3 - est) : '☆☆☆';
    const best = J.progresso.melhor[f.id];
    $('#modo-melhor').textContent = best ? `seu melhor: ${best}%` : 'nunca jogada';

    // demonstra o som da fase ali mesmo, para você saber no que está entrando
    setTimeout(() => J.demonstrarFase(f), 200);
  };

  J.demonstrarFase = function (f) {
    Audio.unlock();
    // Guarda o timer para cancelar se o jogador entrar na fase antes do fim da
    // demo. O autoStopMs do Audio cuida da parada; este campo só marca que a
    // demo está em curso.
    if (J.timerDemo) { clearTimeout(J.timerDemo); J.timerDemo = null; }
    try {
      const d = Fases.gerar(f.id, 'praticar');
      if (f.id === 1) {
        // autoStopMs deixa o próprio Audio garantir a parada, sem timer solto
        Audio.startMetronome(76, 4, () => {}, 2400);
        return;
      }
      const r = d.rodadas[0];
      if (r && r.oQueSoa) {
        if (r.tipo === 'acorde' || r.tipo === 'funcao') Audio.chord(r.oQueSoa, { dur: 0.9, timbre: 'piano', gain: 0.36 });
        else if (r.tipo === 'cadencia' || r.tipo === 'analise') J.tocarProgressao(r.progressaoHz);
        else Audio.seq(r.oQueSoa.flat().map(hz => [hz, 0.4]), { timbre: 'cristal', gain: 0.36, gap: 0.05 });
      }
    } catch (e) {}
  };

  /* ======================================================================
     CANVAS DE FUNDO: a voz, agora visível
     ====================================================================== */

  J.montarFundo = function () {
    const dom = () => J.progresso.dominadas.length;
    let gesto = 0;
    Engine.play({
      update(dt, t) { gesto = 0.5 + 0.5 * Math.sin(t * 0.85); },
      draw(ctx, w, h, t) {
        Engine.fundoGradiente(ctx, w, h, t, 212);
        Engine.grade(ctx, w, h, 50, 0.035);
        // A VOZ: no topo, acima do conteúdo, sempre visível
        Voz.desenharTrilha(ctx, w, h * 0.62, {
          fasesDominadas: dom(), tempo: t, escala: 0.62
        });
        // O Daniel fica no rodapé, bem apagado: presença, não decoração.
        // Antes ele subia e brigava com os botões.
        Voz.avatarDaniel(ctx, w * 0.5, h - 24, Math.min(0.5, w / 1200), {
          tempo: t, gesto, alpha: 0.07
        });
      }
    });
  };

  /* ======================================================================
     TECLADO do mini-jogo livre
     ====================================================================== */

  J.montarTecladoLivre = function () {
    const kb = $('#livre-teclado');
    if (!kb) return;
    const brancas = [60, 62, 64, 65, 67, 69, 71, 72];
    const nomes = ['Dó', 'Ré', 'Mi', 'Fá', 'Sol', 'Lá', 'Si', 'Dó'];
    kb.innerHTML = '';
    brancas.forEach((m, i) => {
      const t = document.createElement('button');
      t.className = 'tecla-livre';
      t.dataset.midi = m;
      t.innerHTML = `<span>${nomes[i]}</span>`;
      t.addEventListener('click', () => {
        Audio.note(Teoria.hzDoMidi(m), { dur: 0.7, timbre: 'cristal', gain: 0.4 });
        Livre.responder(m);
      });
      kb.appendChild(t);
    });
  };

  /* ======================================================================
     LIGAÇÕES
     ====================================================================== */

  J.ligar = function () {
    $('#btn-voltar')?.addEventListener('click', () => {
      Audio.tick(); Ritmo.parar();
      if (!$('#tela-modo').hidden) { J.montarCapa(); mostrar('tela-capa'); }
      else if (!$('#tela-jogo').hidden || !$('#tela-fim').hidden) { J.montarCapa(); mostrar('tela-capa'); }
      else { J.montarCapa(); mostrar('tela-capa'); }
      $('#titulo-fase').textContent = '';
      $('#btn-voltar').hidden = true;
      Livre.iniciar();
    });

    $('#btn-som')?.addEventListener('click', () => {
      const m = !Audio.isMuted();
      Audio.setMuted(m);
      J.progresso.mudo = m; salvar();
      $('#btn-som').textContent = m ? '🔇' : '🔊';
      if (!m) Audio.tick();
    });

    $('#btn-repetir')?.addEventListener('click', () => {
      const r = J.estado.rodadas[J.estado.idx];
      if (r) J.tocarRodada(r);
      else Livre.tocarAlvo();
    });

    $('#livre-tocar')?.addEventListener('click', () => { Audio.unlock(); Livre.tocarAlvo(); });

    $('#btn-bater')?.addEventListener('click', () => Ritmo.bater());

    // modos
    $$('[data-modo]').forEach(b => {
      b.addEventListener('click', () => {
        const f = J.estado.fasePendente;
        if (!f) return;
        J.comecarFase(f.id, b.dataset.modo);
      });
    });

    // fim de fase
    $('#fim-repetir')?.addEventListener('click', () => {
      Audio.tick();
      J.comecarFase(J.estado.fase.id, J.estado.modo);
    });
    $('#fim-proxima')?.addEventListener('click', () => {
      Audio.tick();
      const dom = J.progresso.dominadas;
      const prox = Fases.LISTA.find(f => !dom.includes(f.id));
      if (prox) J.comecarFase(prox.id, 'praticar');
      else { J.montarCapa(); mostrar('tela-capa'); }
    });
    $('#fim-menu')?.addEventListener('click', () => {
      Audio.tick(); J.montarCapa(); mostrar('tela-capa');
      $('#titulo-fase').textContent = ''; $('#btn-voltar').hidden = true;
      Livre.iniciar();
    });

    // teclado físico
    window.addEventListener('keydown', (e) => {
      if (e.code === 'Space') {
        e.preventDefault();
        if (Ritmo.ativo) Ritmo.bater();
      }
      if (e.key === 'r' || e.key === 'R') $('#btn-repetir')?.click();
      // números 1-5 respondem as opções: jogar pelo teclado é mais rápido
      const n = parseInt(e.key, 10);
      if (n >= 1 && n <= 5 && $('#tela-jogo').hidden === false) {
        const ops = $$('#jogo-opcoes .opcao');
        if (ops[n - 1]) ops[n - 1].click();
      }
    });
  };

  /* ======================================================================
     INÍCIO
     ====================================================================== */

  J.init = function () {
    Engine.init(document.getElementById('palco'));
    if (J.progresso.mudo) Audio.setMuted(true);
    $('#btn-som').textContent = Audio.isMuted() ? '🔇' : '🔊';
    J.montarFundo();
    J.montarCapa();
    J.montarTecladoLivre();
    J.ligar();
    mostrar('tela-capa');
    Livre.iniciar();
    Teoria.autoteste();
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => J.init());
  else J.init();
})();
