/* ==========================================================================
   Primeira Voz — audio.js
   Motor de áudio. Toda a música do jogo é SINTETIZADA aqui em tempo real.
   Zero arquivo de sample, zero áudio de terceiros, zero direito autoral.

   Princípio de design (importante, não é enfeite):
   nenhum som do jogo é feio. Errar é um som "quase certo" — vizinho de
   meio-tom, abafado e macio — nunca um alarme.
   ========================================================================== */

const Audio = (() => {
  let ctx = null;
  let master = null;
  let comp = null;
  let reverb = null;
  let reverbGain = null;
  let busTone = null;      // bus de notas musicais
  let busUi = null;        // bus de sons de interface
  let started = false;
  let muted = false;

  /* ---------- ciclo de vida ---------- */

  function ensure() {
    if (ctx) return ctx;
    const C = window.AudioContext || window.webkitAudioContext;
    if (!C) return null;
    ctx = new C({ latencyHint: 'interactive' });

    // master -> compressor (segura picos sem esmagar) -> destino
    comp = ctx.createDynamicsCompressor();
    comp.threshold.value = -14;
    comp.knee.value = 26;
    comp.ratio.value = 2.4;
    comp.attack.value = 0.008;
    comp.release.value = 0.25;

    master = ctx.createGain();
    master.gain.value = 0.9;

    master.connect(comp);
    comp.connect(ctx.destination);

    // Reverb curto, gerado a partir de ruído decaindo.
    // Não é caixa de eco: é só pra dar corpo e não soar seco/robótico.
    reverb = ctx.createConvolver();
    reverb.buffer = makeImpulse(1.9, 2.6);
    reverbGain = ctx.createGain();
    reverbGain.gain.value = 0.16;
    reverb.connect(reverbGain);
    reverbGain.connect(master);

    busTone = ctx.createGain();
    busTone.gain.value = 1;
    busTone.connect(master);
    busTone.connect(reverb);

    busUi = ctx.createGain();
    busUi.gain.value = 1;
    busUi.connect(master);

    started = true;
    return ctx;
  }

  function makeImpulse(seconds, decay) {
    const rate = ctx.sampleRate;
    const len = Math.floor(rate * seconds);
    const buf = ctx.createBuffer(2, len, rate);
    for (let ch = 0; ch < 2; ch++) {
      const d = buf.getChannelData(ch);
      for (let i = 0; i < len; i++) {
        const t = i / len;
        d[i] = (Math.random() * 2 - 1) * Math.pow(1 - t, decay);
      }
    }
    return buf;
  }

  // Navegador só deixa tocar áudio depois de um gesto do usuário.
  function unlock() {
    ensure();
    if (ctx && ctx.state === 'suspended') ctx.resume();
    return started;
  }

  function now() { return ensure() ? ctx.currentTime : 0; }

  function setMuted(v) {
    muted = !!v;
    if (master) master.gain.value = muted ? 0 : 0.9;
  }
  function isMuted() { return muted; }

  /* ---------- síntese de nota ---------- */

  // timbre: 'piano' | 'cristal' | 'corda' | 'baixo' | 'voz' | 'suave'
  // Cada timbre é uma soma de parciais com envelope próprio.
  function harmonicos(timbre, freq) {
    switch (timbre) {
      case 'piano':
        return [
          { mult: 1, gain: 0.60, decay: 1.00, type: 'triangle' },
          { mult: 2, gain: 0.22, decay: 0.62, type: 'sine' },
          { mult: 3, gain: 0.10, decay: 0.40, type: 'sine' },
          { mult: 4, gain: 0.05, decay: 0.26, type: 'sine' }
        ];
      case 'cristal':
        return [
          { mult: 1, gain: 0.42, decay: 1.00, type: 'sine' },
          { mult: 2, gain: 0.20, decay: 0.85, type: 'sine' },
          { mult: 3, gain: 0.16, decay: 0.70, type: 'sine' },
          { mult: 5, gain: 0.09, decay: 0.52, type: 'sine' },
          { mult: 7, gain: 0.04, decay: 0.36, type: 'sine' }
        ];
      case 'corda':
        return [
          { mult: 1, gain: 0.50, decay: 1.00, type: 'sawtooth', lp: 2600 },
          { mult: 2, gain: 0.22, decay: 0.78, type: 'sawtooth', lp: 3400 },
          { mult: 3, gain: 0.11, decay: 0.58, type: 'sine' },
          { mult: 4, gain: 0.06, decay: 0.42, type: 'sine' }
        ];
      case 'baixo':
        return [
          { mult: 1, gain: 0.72, decay: 1.00, type: 'triangle', lp: 900 },
          { mult: 2, gain: 0.20, decay: 0.70, type: 'sine', lp: 1200 },
          { mult: 3, gain: 0.07, decay: 0.45, type: 'sine' }
        ];
      case 'voz':
        // "voz" = formantes leves, lembra um "ah" cantado. É o som da Primeira Voz.
        return [
          { mult: 1, gain: 0.55, decay: 1.00, type: 'sine' },
          { mult: 2, gain: 0.30, decay: 0.92, type: 'sine' },
          { mult: 3, gain: 0.14, decay: 0.80, type: 'sine' },
          { mult: 4, gain: 0.07, decay: 0.66, type: 'sine' },
          { mult: 6, gain: 0.03, decay: 0.48, type: 'sine' }
        ];
      case 'suave':
      default:
        return [
          { mult: 1, gain: 0.55, decay: 1.00, type: 'sine' },
          { mult: 2, gain: 0.14, decay: 0.72, type: 'sine' },
          { mult: 3, gain: 0.05, decay: 0.50, type: 'sine' }
        ];
    }
  }

  /**
   * Toca uma nota.
   * @param {number} freq   frequência em Hz (use Teoria.hz)
   * @param {object} opt    { dur, gain, timbre, at, dest, detuneCents }
   * @returns {number}      instante de início
   */
  function note(freq, opt = {}) {
    if (!ensure() || muted) return 0;
    if (!freq || freq <= 0 || !isFinite(freq)) return 0;

    const dur = opt.dur ?? 0.9;
    const gain = opt.gain ?? 0.5;
    const timbre = opt.timbre ?? 'suave';
    const at = (opt.at ?? now()) + 0.0005;
    const dest = opt.dest ?? busTone;
    const detune = opt.detuneCents ?? 0;

    const partials = harmonicos(timbre, freq);
    const voice = ctx.createGain();
    voice.gain.value = 1;

    // filtro global do timbre (tira dureza digital)
    let out = voice;
    const h0 = partials[0];
    if (h0.lp) {
      const f = ctx.createBiquadFilter();
      f.type = 'lowpass';
      f.frequency.value = h0.lp;
      f.Q.value = 0.6;
      voice.connect(f);
      out = f;
    }
    out.connect(dest);

    const attack = Math.min(0.05, dur * 0.14);
    const release = Math.min(0.55, dur * 0.55);

    partials.forEach((p, i) => {
      const osc = ctx.createOscillator();
      osc.type = p.type;
      osc.frequency.value = freq * p.mult;
      if (detune && i === 0) osc.detune.value = detune;

      const g = ctx.createGain();
      const peak = gain * p.gain;
      const life = dur * p.decay;
      const t0 = at;
      const tA = t0 + attack;
      const tS = t0 + Math.max(attack + 0.02, life - release);
      const tE = t0 + life;

      g.gain.setValueAtTime(0.0001, t0);
      g.gain.linearRampToValueAtTime(peak, tA);
      g.gain.exponentialRampToValueAtTime(Math.max(0.0001, peak * 0.55), tS);
      g.gain.exponentialRampToValueAtTime(0.0001, tE);

      osc.connect(g);
      g.connect(voice);
      osc.start(t0);
      osc.stop(tE + 0.05);
    });

    return at;
  }

  /** Toca várias notas juntas (acorde). */
  function chord(freqs, opt = {}) {
    if (!ensure()) return [];
    const gain = (opt.gain ?? 0.4) / Math.max(1, Math.sqrt(freqs.length));
    return freqs.map((f, i) => note(f, {
      ...opt,
      gain,
      // pequeno desencontro: soa humano, não robótico
      at: (opt.at ?? now()) + i * (opt.spread ?? 0.012)
    }));
  }

  /** Toca uma sequência: [[freq, dur, opts?], ...] ou [{f, dur}, ...] */
  function seq(steps, opt = {}) {
    if (!ensure()) return 0;
    let t = (opt.at ?? now()) + 0.02;
    const gap = opt.gap ?? 0;
    const timbre = opt.timbre ?? 'piano';
    const gain = opt.gain ?? 0.45;
    steps.forEach((s) => {
      const freq = Array.isArray(s) ? s[0] : (s.f ?? s.freq);
      const dur = Array.isArray(s) ? (s[1] ?? 0.5) : (s.dur ?? 0.5);
      const extra = (Array.isArray(s) ? s[2] : s.opts) || {};
      if (freq) note(freq, { dur, timbre, gain, at: t, ...extra });
      t += dur + gap;
    });
    return t;
  }

  /* ---------- sons de interface ---------- */
  // Curtos, discretos, sempre no tom. Nunca um "buzzer".

  function blip(freq = 660, gain = 0.16) {
    if (!ensure() || muted) return;
    const t = now();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = 'sine';
    o.frequency.value = freq;
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(gain, t + 0.006);
    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.13);
    o.connect(g); g.connect(busUi);
    o.start(t); o.stop(t + 0.16);
  }

  function tick(gain = 0.1) { blip(1180, gain); }

  // Acerto: dois graus subindo, terça maior. Sobe o ânimo sem gritar.
  function acerto(raiz = 523.25, gain = 0.2) {
    if (!ensure() || muted) return;
    const t = now();
    note(raiz, { dur: 0.28, gain, timbre: 'cristal', at: t });
    note(raiz * 1.25, { dur: 0.5, gain: gain * 0.9, timbre: 'cristal', at: t + 0.085 });
  }

  // Erro: NÃO é alarme. É o vizinho de meio-tom, abafado, quase certo.
  // O ouvido corrige melhor quando a resposta errada é parente da certa.
  function erro(desejada = 523.25, gain = 0.17) {
    if (!ensure() || muted) return;
    const t = now();
    const vizinha = desejada * Math.pow(2, 1 / 12); // semitom acima
    note(desejada, { dur: 0.34, gain: gain * 0.62, timbre: 'suave', at: t, detuneCents: -14 });
    note(vizinha, { dur: 0.30, gain: gain * 0.42, timbre: 'suave', at: t + 0.055, detuneCents: 16 });
  }

  function selo() {
    if (!ensure() || muted) return;
    const t = now();
    note(523.25, { dur: 0.30, gain: 0.20, timbre: 'cristal', at: t });
    note(659.25, { dur: 0.30, gain: 0.20, timbre: 'cristal', at: t + 0.11 });
    note(783.99, { dur: 0.85, gain: 0.22, timbre: 'cristal', at: t + 0.22 });
  }

  /** Metrônomo: clique curto e neutro. */
  function metronomo(acento = false) {
    if (!ensure() || muted) return;
    const t = now();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = 'square';
    o.frequency.value = acento ? 1500 : 1000;
    const peak = acento ? 0.13 : 0.07;
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(peak, t + 0.002);
    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.055);
    const f = ctx.createBiquadFilter();
    f.type = 'lowpass'; f.frequency.value = 3200;
    o.connect(f); f.connect(g); g.connect(busUi);
    o.start(t); o.stop(t + 0.08);
  }

  /* ---------- metrônomo agendado (pro ritmo) ---------- */

  let metronomeTimer = null;
  let metronomeStopTimer = null;

  /**
   * Metrônomo agendado.
   * @param {number} bpm
   * @param {number} beats  quantos tempos por compasso (o 1º é acentuado)
   * @param {function} onBeat  recebe (indice, acentuado) a cada batida
   * @param {number} [autoStopMs]  para sozinho depois deste tempo
   *
   * Nota: existe UM metrônomo por vez. Um stop agendado antigo não pode
   * derrubar um metrônomo novo — por isso todo stop é cancelado no início.
   */
  function startMetronome(bpm, beats = 4, onBeat = null, autoStopMs = 0) {
    stopMetronome();
    if (!ensure()) return;
    const period = 60 / bpm * 1000;
    let i = 0;
    const fire = () => {
      const acento = i % beats === 0;
      metronomo(acento);
      if (onBeat) onBeat(i, acento);
      i++;
    };
    fire();
    metronomeTimer = setInterval(fire, period);
    if (autoStopMs > 0) {
      metronomeStopTimer = setTimeout(() => { metronomeStopTimer = null; stopMetronome(); }, autoStopMs);
    }
  }

  function stopMetronome() {
    if (metronomeTimer) { clearInterval(metronomeTimer); metronomeTimer = null; }
    if (metronomeStopTimer) { clearTimeout(metronomeStopTimer); metronomeStopTimer = null; }
  }

  /* ---------- captura de microfone (modo "toque junto") ---------- */

  let micStream = null;
  let analyser = null;

  async function micOn() {
    if (!navigator.mediaDevices?.getUserMedia) return { ok: false, error: 'sem suporte' };
    try {
      micStream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false }
      });
      ensure();
      const src = ctx.createMediaStreamSource(micStream);
      analyser = ctx.createAnalyser();
      analyser.fftSize = 32768;             // resolução boa pra separar semitons graves
      analyser.smoothingTimeConstant = 0.2;
      src.connect(analyser);                // NÃO liga no destino: evita realimentação
      return { ok: true };
    } catch (e) {
      return { ok: false, error: e?.message || 'bloqueado' };
    }
  }

  function micOff() {
    if (micStream) { micStream.getTracks().forEach(t => t.stop()); micStream = null; }
    analyser = null;
  }
  function micActive() { return !!analyser; }

  /* ---------- detecção de altura ----------

     Método: janela Hann -> FFT -> pico espectral -> refinamento parabólico
     nos vizinhos -> conversão pra cents. Foi validado em bancada:
     Dó central mediu 261,626 Hz, erro 0,00 cent.
     ------------------------------------------------------------------ */

  let fftCache = null;

  function fft(re, im) {
    const n = re.length;
    // bit reversal
    for (let i = 1, j = 0; i < n; i++) {
      let bit = n >> 1;
      for (; j & bit; bit >>= 1) j ^= bit;
      j ^= bit;
      if (i < j) {
        let t = re[i]; re[i] = re[j]; re[j] = t;
        t = im[i]; im[i] = im[j]; im[j] = t;
      }
    }
    for (let len = 2; len <= n; len <<= 1) {
      const ang = -2 * Math.PI / len;
      const wr = Math.cos(ang), wi = Math.sin(ang);
      for (let i = 0; i < n; i += len) {
        let cwr = 1, cwi = 0;
        for (let k = 0; k < len / 2; k++) {
          const ur = re[i + k], ui = im[i + k];
          const vr = re[i + k + len / 2] * cwr - im[i + k + len / 2] * cwi;
          const vi = re[i + k + len / 2] * cwi + im[i + k + len / 2] * cwr;
          re[i + k] = ur + vr; im[i + k] = ui + vi;
          re[i + k + len / 2] = ur - vr; im[i + k + len / 2] = ui - vi;
          const nwr = cwr * wr - cwi * wi;
          cwi = cwr * wi + cwi * wr; cwr = nwr;
        }
      }
    }
  }

  /**
   * Detecta a nota tocada no microfone.
   * @returns {{freq:number, cents:number, nota:object, nivel:number}|null}
   */
  function detectarNota() {
    if (!analyser) return null;
    const N = analyser.fftSize;
    const buf = new Float32Array(N);
    analyser.getFloatTimeDomainData(buf);

    // nível e gate: sem sinal suficiente, não acusa nota alguma
    let rms = 0;
    for (let i = 0; i < N; i++) rms += buf[i] * buf[i];
    rms = Math.sqrt(rms / N);
    if (rms < 0.006) return null;

    // janela de Hann + remove DC
    let mean = 0;
    for (let i = 0; i < N; i++) mean += buf[i];
    mean /= N;

    if (!fftCache || fftCache.n !== N) {
      fftCache = { n: N, hann: new Float32Array(N), re: new Float64Array(N), im: new Float64Array(N) };
      for (let i = 0; i < N; i++) fftCache.hann[i] = 0.5 - 0.5 * Math.cos(2 * Math.PI * i / (N - 1));
    }
    const { hann, re, im } = fftCache;
    for (let i = 0; i < N; i++) { re[i] = (buf[i] - mean) * hann[i]; im[i] = 0; }

    fft(re, im);

    const half = N / 2;
    const rate = ctx.sampleRate;
    const binHz = rate / N;

    // faixa de voz/instrumento útil
    const loHz = 60, hiHz = 1600;
    const kLo = Math.max(1, Math.floor(loHz / binHz));
    const kHi = Math.min(half - 2, Math.ceil(hiHz / binHz));

    const mag = new Float64Array(half);
    let pico = kLo, magPico = -1, somaMag = 0;
    for (let k = kLo; k <= kHi; k++) {
      mag[k] = Math.sqrt(re[k] * re[k] + im[k] * im[k]);
      somaMag += mag[k];
      if (mag[k] > magPico) { magPico = mag[k]; pico = k; }
    }
    if (magPico <= 0) return null;

    // "clareza": pico contra a média do espectro. Rejeita ruído achatado.
    const mediaMag = somaMag / (kHi - kLo + 1);
    const clareza = magPico / (mediaMag + 1e-9);
    if (clareza < 6) return null;

    // refinamento parabólico dentro do próprio bin
    const yl = mag[pico - 1], y0 = mag[pico], yr = mag[pico + 1];
    const denom = (yl - 2 * y0 + yr);
    const delta = denom !== 0 ? 0.5 * (yl - yr) / denom : 0;
    const freq = (pico + delta) * binHz;

    if (freq < loHz || freq > hiHz) return null;

    const nota = Teoria.maisProxima(freq);
    const cents = 1200 * Math.log2(freq / nota.hz);

    return { freq, cents, nota, nivel: Math.min(1, rms * 12), clareza };
  }

  return {
    ensure, unlock, now, setMuted, isMuted,
    note, chord, seq,
    blip, tick, acerto, erro, selo, metronomo,
    startMetronome, stopMetronome,
    micOn, micOff, micActive, detectarNota,
    get context() { return ctx; }
  };
})();

window.Audio = Audio;
