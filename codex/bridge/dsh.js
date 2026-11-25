/**
 * Leitor e executor de sessoes do DeepSeek Harness (DSH) para a ponte do Codex Web.
 *
 * Uma sessao do DSH e um arquivo `session.v4.jsonl.zstd`: JSONL comprimido em
 * zstd com um frame por registro, tudo concatenado no mesmo arquivo. O primeiro
 * registro e o cabecalho (`type: "session"`); o resto sao eventos com `seq`.
 *
 * Executar um turno e chamar o CLI do proprio app:
 *   dsh headless --json [--session-id <id>] -
 * O prompt vai por stdin e a saida e NDJSON. Como o motor e o mesmo do app,
 * a conversa aparece tambem na janela do DSH.
 */
import zlib from 'node:zlib';
import crypto from 'node:crypto';
import { spawn } from 'node:child_process';
import { readdirSync, statSync, readFileSync, writeFileSync, renameSync, unlinkSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const MAGIC = Buffer.from([0x28, 0xb5, 0x2f, 0xfd]);

/** Root do app (uma pasta acima de codex/bridge) — nunca o cwd do processo. */
const RAIZ_APP = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

export const CFG_DSH = {
  home: process.env.DSH_WEB_HOME || path.join(process.env.USERPROFILE || '', '.dsh'),
  cli: process.env.DSH_WEB_CLI || '',
  // Vazio = usa o workspace padrao do app (workspace.json), como as conversas
  // abertas na janela do DSH. NUNCA process.cwd(): a ponte sobe por tarefa
  // agendada e o cwd virava C:\Windows\System32.
  workdir: process.env.DSH_WEB_WORKDIR || process.env.CODEX_WEB_WORKDIR || '',
  // O site roda turnos como o dono da maquina: sandbox desligado e sem
  // aprovacao. `workspace-write` (padrao do CLI) e o que travava tudo.
  permissionMode: process.env.DSH_WEB_PERMISSION_MODE || 'danger-full-access',
  maxSessions: 200,
  // Usados no modal de informacoes quando o evento nao traz o modelo.
  modelo: process.env.DSH_WEB_MODEL || process.env.CODEX_WEB_MODEL || 'deepseek-flash',
  provedor: process.env.DSH_WEB_PROVIDER || 'deepseek-official',
};

// ------------------------------------------------------------------ autores
//
// O log da sessao do DSH nao guarda quem enviou a mensagem: ele so diz
// `source.kind = "user"`. Quando o turno vem do site, a ponte grava aqui o
// login/IP/navegador que disparou o prompt; na leitura, casamos pelo hash do
// texto e pela hora para mostrar isso no modal. Mensagens antigas (ou criadas
// direto na janela do app) aparecem como "nao registrado".

const AUTORES_FILE = path.join(path.dirname(fileURLToPath(import.meta.url)), 'data', 'autores.json');
const AUTORES_VALIDADE_MS = 30 * 24 * 60 * 60 * 1000;
let cacheAutores = { mtimeMs: -1, dados: { registros: [] } };

function autoresVazios() {
  return { versao: 1, registros: [] };
}

function lerAutores() {
  let st;
  try { st = statSync(AUTORES_FILE); } catch { return autoresVazios(); }
  if (cacheAutores.mtimeMs === st.mtimeMs) return cacheAutores.dados;
  try {
    const dados = JSON.parse(readFileSync(AUTORES_FILE, 'utf8'));
    const registros = Array.isArray(dados?.registros) ? dados.registros : [];
    cacheAutores = { mtimeMs: st.mtimeMs, dados: { versao: 1, registros } };
  } catch {
    cacheAutores = { mtimeMs: st.mtimeMs, dados: autoresVazios() };
  }
  return cacheAutores.dados;
}

function gravarAutores(dados) {
  const temporario = AUTORES_FILE + '.tmp-dsh-web';
  try {
    writeFileSync(temporario, JSON.stringify(dados, null, 2), 'utf8');
    renameSync(temporario, AUTORES_FILE);
    cacheAutores = { mtimeMs: -1, dados };
    return true;
  } catch {
    try { unlinkSync(temporario); } catch {}
    return false;
  }
}

function limparUsuario(usuario) {
  if (!usuario || typeof usuario !== 'object') return null;
  const id = Number(usuario.id);
  const nome = String(usuario.nome ?? usuario.name ?? '').trim().slice(0, 160);
  const email = String(usuario.email ?? '').trim().slice(0, 200);
  if (!nome && !email && !Number.isFinite(id)) return null;
  return {
    id: Number.isFinite(id) ? id : null,
    nome,
    email,
    fonte: String(usuario.fonte || 'site').slice(0, 40),
  };
}

/**
 * Guarda quem enviou um prompt pelo site. Chamado quando o turno termina,
 * porque so entao a sessao nova tem id definitivo.
 */
export function registrarAutor(sessionId, prompt, usuario, extras = {}) {
  const id = String(sessionId || '').replace(/^dsh:/, '');
  const texto = String(prompt || '');
  const autor = limparUsuario(usuario);
  if (!id || !texto || !autor) return false;

  const agora = Date.now();
  const dados = lerAutores();
  const promptHash = crypto.createHash('sha1').update(texto).digest('hex');
  const registros = (Array.isArray(dados.registros) ? dados.registros : [])
    .filter((r) => r && typeof r === 'object'
      && agora - Number(r.enviadoEm || 0) < AUTORES_VALIDADE_MS
      && !(r.sessionId === id && r.promptHash === promptHash))
    .slice(-499);
  const enviadoEm = Number(extras.enviadoEm) || agora;
  registros.push({
    sessionId: id,
    promptHash,
    promptPreview: texto.replace(/\s+/g, ' ').slice(0, 160),
    enviadoEm,
    concluidoEm: Number(extras.concluidoEm) || agora,
    autor,
    ip: String(extras.ip || '').slice(0, 80),
    userAgent: String(extras.userAgent || '').slice(0, 300),
    runId: String(extras.runId || '').slice(0, 80),
  });

  return gravarAutores({ versao: 1, registros });
}

function autorDaMensagem(sessionId, texto, time) {
  const id = String(sessionId || '').replace(/^dsh:/, '');
  const quando = Number(time) || Date.now();
  if (!id) return null;
  const promptHash = crypto.createHash('sha1').update(String(texto || '')).digest('hex');
  const registros = (lerAutores().registros || [])
    .filter((r) => r && r.sessionId === id && r.promptHash === promptHash)
    .map((r) => ({ ...r, distancia: Math.abs(Number(r.enviadoEm || 0) - quando) }))
    .sort((a, b) => a.distancia - b.distancia);
  const achado = registros[0];
  if (!achado || achado.distancia > 6 * 60 * 60 * 1000) return null;
  return {
    ...achado.autor,
    ip: achado.ip || '',
    userAgent: achado.userAgent || '',
    enviadoEm: achado.enviadoEm || null,
    fonte: achado.autor?.fonte || 'site',
  };
}

/**
 * Registra a sessao no workspace do app (~/.dsh/storages/workspace.json).
 * Sem isso o arquivo existe em disco, mas a janela do DSH nao lista a conversa.
 */
export function registrarSessao(id, cwd) {
  const arquivo = path.join(CFG_DSH.home, 'storages', 'workspace.json');
  const idLimpo = String(id || '').replace(/^dsh:/, '');
  if (!idLimpo) return false;
  let banco;
  try { banco = JSON.parse(readFileSync(arquivo, 'utf8')); } catch { return false; }
  if (!banco?.tables?.workspaces) return false;

  const tabela = banco.tables.workspaces;
  const caminho = String(cwd || cwdPadrao() || '').toLowerCase();
  let alvo = Object.values(tabela).find((w) => String(w?.path || '').toLowerCase() === caminho.trim());

  if (!alvo) {
    const novoId = crypto.randomUUID();
    alvo = {
      path: String(cwd || cwdPadrao() || ''),
      title: path.basename(String(cwd || cwdPadrao() || 'workspace')) || 'workspace',
      sessionIds: [],
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
    tabela[novoId] = alvo;
    banco.global.workspaceIds = Array.isArray(banco.global.workspaceIds) ? banco.global.workspaceIds : [];
    banco.global.workspaceIds.push(novoId);
    banco.global.defaultWorkspaceId = banco.global.defaultWorkspaceId || novoId;
  }

  alvo.sessionIds = Array.isArray(alvo.sessionIds) ? alvo.sessionIds : [];
  if (alvo.sessionIds.includes(idLimpo)) return true;
  alvo.sessionIds.unshift(idLimpo);
  alvo.updatedAt = new Date().toISOString();

  const temporario = arquivo + '.tmp-dsh-web';
  try {
    writeFileSync(temporario, JSON.stringify(banco, null, 2), 'utf8');
    renameSync(temporario, arquivo);
    return true;
  } catch {
    try { unlinkSync(temporario); } catch {}
    return false;
  }
}

/**
 * cwd em que a sessao foi gravada. O DSH recusa retomar uma sessao em outro
 * diretorio ("session ... was recorded in X, not Y"), entao continuar uma
 * conversa antiga (ex.: criada quando a ponte subia com cwd System32) precisa
 * rodar no mesmo cwd do cabecalho da sessao.
 */
function cwdDaSessao(idBruto) {
  const id = String(idBruto || '').replace(/^dsh:/, '');
  if (!id) return '';
  const arquivo = arquivosSessao().find((arq) => path.basename(path.dirname(arq)) === id);
  if (!arquivo) return '';
  return String(resumir(arquivo)?.cwd || '');
}

/**
 * Remove o `agentPreset` do cabecalho da sessao.
 *
 * POR QUE: o runner one-shot do DSH (`dsh headless --session-id`) recusa
 * qualquer sessao criada com preset de agente ("runs under agent preset ...
 * which the one-shot runner does not compose"). O site nao compoe presets; o
 * app desktop carimba o preset nas conversas novas. Tirar o campo do cabecalho
 * devolve a sessao para a composicao padrao SEM perder nenhuma mensagem.
 * Grava backup ao lado do arquivo e nao mexe em sessao modificada nos ultimos
 * 60 s (provavelmente aberta no app).
 *
 * @returns `{ ok: true, preset, backup }` ou `{ ok: false, motivo }`.
 */
export function removerPresetDaSessao(idBruto) {
  const id = String(idBruto || '').replace(/^dsh:/, '');
  if (!id) return { ok: false, motivo: 'sem_id' };
  const arquivo = arquivosSessao().find((arq) => path.basename(path.dirname(arq)) === id);
  if (!arquivo) return { ok: false, motivo: 'nao_encontrada' };
  let st;
  try { st = statSync(arquivo); } catch { return { ok: false, motivo: 'sem_arquivo' }; }
  if (Date.now() - st.mtimeMs < 60000) return { ok: false, motivo: 'em_uso' };
  let buf;
  try { buf = readFileSync(arquivo); } catch { return { ok: false, motivo: 'leitura' }; }
  const i = buf.indexOf(MAGIC);
  const j = buf.indexOf(MAGIC, i + 4);
  if (i < 0) return { ok: false, motivo: 'formato' };
  const fimFrame = j < 0 ? buf.length : j;
  let texto;
  try {
    texto = zlib.zstdDecompressSync(buf.subarray(i, fimFrame)).toString('utf8');
  } catch {
    return { ok: false, motivo: 'zstd' };
  }
  const linhas = texto.split('\n');
  let cabecalho;
  try { cabecalho = JSON.parse(linhas[0]); } catch { return { ok: false, motivo: 'json' }; }
  if (!cabecalho || typeof cabecalho !== 'object' || !('agentPreset' in cabecalho)) {
    return { ok: false, motivo: 'sem_preset' };
  }
  const preset = String(cabecalho.agentPreset || '');
  delete cabecalho.agentPreset;
  linhas[0] = JSON.stringify(cabecalho);
  let frameNovo;
  try {
    frameNovo = zlib.zstdCompressSync(Buffer.from(linhas.join('\n'), 'utf8'));
  } catch {
    return { ok: false, motivo: 'compressao' };
  }
  const backup = arquivo + '.bak-preset-' + Date.now();
  const temporario = arquivo + '.tmp-preset-' + Date.now();
  try {
    writeFileSync(backup, buf);
    writeFileSync(temporario, Buffer.concat([frameNovo, buf.subarray(fimFrame)]));
    renameSync(temporario, arquivo);
  } catch (erro) {
    try { unlinkSync(temporario); } catch {}
    return { ok: false, motivo: 'escrita', erro: String(erro?.message || erro) };
  }
  return { ok: true, preset, backup };
}

/** Workspace padrao configurado no app (onde as conversas novas caem). */
function cwdPadrao() {
  if (CFG_DSH.workdir) return CFG_DSH.workdir;
  try {
    const arquivo = path.join(CFG_DSH.home, 'storages', 'workspace.json');
    const banco = JSON.parse(readFileSync(arquivo, 'utf8'));
    const id = banco?.global?.defaultWorkspaceId;
    const caminho = banco?.tables?.workspaces?.[id]?.path;
    if (caminho) return String(caminho);
  } catch {}
  return RAIZ_APP;
}

/** Todas as sessoes dentro de DSH_HOME/sessions. */
function arquivosSessao() {
  const raiz = path.join(CFG_DSH.home, 'sessions');
  const pilha = [raiz];
  const achados = [];
  while (pilha.length) {
    const dir = pilha.pop();
    let entradas = [];
    try { entradas = readdirSync(dir, { withFileTypes: true }); } catch { continue; }
    for (const entrada of entradas) {
      const completo = path.join(dir, entrada.name);
      if (entrada.isDirectory()) pilha.push(completo);
      else if (entrada.name.endsWith('.jsonl.zstd')) achados.push(completo);
    }
  }
  return achados;
}

function descomprimir(buf, inicio, fim) {
  try {
    return zlib.zstdDecompressSync(buf.subarray(inicio, fim)).toString('utf8');
  } catch {
    return null;
  }
}

/** Decodifica o arquivo inteiro, frame por frame. */
function lerEventos(arquivo) {
  let buf;
  try { buf = readFileSync(arquivo); } catch { return []; }
  const limites = [];
  let i = 0;
  for (;;) {
    const p = buf.indexOf(MAGIC, i);
    if (p < 0) break;
    limites.push(p);
    i = p + 1;
  }
  const eventos = [];
  let pos = 0;
  let guarda = 0;
  while (pos < buf.length && guarda < 100000) {
    guarda += 1;
    // Fim do frame = proximo magic depois de `pos`. Se esse limite nao
    // descomprimir (falso positivo dentro do frame), tenta o seguinte.
    let fim = limites.find((o) => o > pos) ?? buf.length;
    let texto = descomprimir(buf, pos, fim);
    let tentativas = 0;
    while (texto === null && tentativas < 40 && fim < buf.length) {
      fim = limites.find((o) => o > fim) ?? buf.length;
      texto = descomprimir(buf, pos, fim);
      tentativas += 1;
    }
    if (texto) {
      for (const linha of texto.split('\n')) {
        const t = linha.trim();
        if (!t) continue;
        try { eventos.push(JSON.parse(t)); } catch { /* linha parcial */ }
      }
    }
    if (fim <= pos) break;
    pos = fim;
  }
  return eventos;
}

const cache = new Map();

function eventosDaSessao(arquivo) {
  let st;
  try { st = statSync(arquivo); } catch { return []; }
  const guardado = cache.get(arquivo);
  if (guardado && guardado.mtime === st.mtimeMs && guardado.size === st.size) return guardado.eventos;
  const eventos = lerEventos(arquivo);
  cache.set(arquivo, { mtime: st.mtimeMs, size: st.size, eventos });
  return eventos;
}

// Blocos que o app injeta sozinho no turno e nao fazem parte da conversa.
const RUIDO = /^(Current runtime context\.|Time sampled while preparing|Browser time zone for this request|The approval policy changed|<system-reminder>|<environment_context>|<user_instructions>|<permissions instructions>|Tokens prefixed with @ are paths)/;

function textoDeConteudo(conteudo, tipos = ['text', 'output_text']) {  if (!Array.isArray(conteudo)) return '';
  return conteudo
    .filter((parte) => parte && tipos.includes(String(parte.type || '')))
    .map((parte) => String(parte.text || ''))
    .join('')
    .trim();
}

// ---------------------------------------------------------- metadados da msg

function somarUso(destino, origem) {
  if (!origem || typeof origem !== 'object') return destino;
  const saida = destino ? { ...destino } : {};
  for (const chave of [
    'inputTokens', 'outputTokens', 'cacheReadTokens', 'cacheWriteTokens',
    'totalTokens', 'thoughtTokens', 'reasoningTokens',
  ]) {
    const valor = Number(origem[chave]);
    if (!Number.isFinite(valor)) continue;
    saida[chave] = (Number(saida[chave]) || 0) + valor;
  }
  return saida;
}

function resumoUso(uso) {
  if (!uso || typeof uso !== 'object') return null;
  const inputTokens = Number(uso.inputTokens) || 0;
  const outputTokens = Number(uso.outputTokens) || 0;
  const cacheReadTokens = Number(uso.cacheReadTokens) || 0;
  const cacheWriteTokens = Number(uso.cacheWriteTokens) || 0;
  const totalInformado = Number(uso.totalTokens);
  const saida = {
    inputTokens,
    outputTokens,
    cacheReadTokens,
    cacheWriteTokens,
    totalTokens: Number.isFinite(totalInformado)
      ? totalInformado
      : inputTokens + outputTokens + cacheReadTokens + cacheWriteTokens,
  };
  const thoughtTokens = Number(uso.thoughtTokens);
  if (Number.isFinite(thoughtTokens)) saida.thoughtTokens = thoughtTokens;
  const reasoningTokens = Number(uso.reasoningTokens);
  if (Number.isFinite(reasoningTokens)) saida.reasoningTokens = reasoningTokens;
  return saida;
}

function estimarTokens(texto) {
  const limpo = String(texto || '').trim();
  if (!limpo) return 0;
  // Aproximacao conservadora (~4 caracteres por token). O valor exato de
  // entrada/saida vem do provedor e aparece separado no modal.
  return Math.max(1, Math.round(limpo.length / 4));
}

function contarTexto(texto) {
  const t = String(texto || '');
  const blocos = (t.match(/```/g) || []).length;
  return {
    caracteres: t.length,
    palavras: (t.match(/\S+/g) || []).length,
    linhas: t ? t.split(/\r?\n/).length : 0,
    blocosCodigo: Math.floor(blocos / 2),
    links: (t.match(/https?:\/\/|www\./gi) || []).length,
  };
}

function primeiroTempoDoStream(stream) {
  let primeiro = null;
  for (const registro of Array.isArray(stream) ? stream : []) {
    const t = typeof registro?.time === 'number'
      ? registro.time
      : (typeof registro?.time0 === 'number' ? registro.time0 : null);
    if (t === null) continue;
    if (primeiro === null || t < primeiro) primeiro = t;
  }
  return primeiro;
}

function novoTurno(numero, inicio) {
  return {
    numero: Number(numero) || 0,
    inicio: Number.isFinite(inicio) ? inicio : null,
    fim: null,
    primeiraRespostaEm: null,
    status: 'aberto',
    motivo: null,
    passos: 0,
    ferramentas: 0,
    nomesFerramentas: [],
    uso: null,
    autor: null,
    _passos: new Map(),
  };
}

function garantirTurno(turnos, numero, inicio) {
  const n = Number(numero) || 0;
  if (!turnos.has(n)) turnos.set(n, novoTurno(n, inicio));
  const turno = turnos.get(n);
  if (turno.inicio === null && Number.isFinite(inicio)) turno.inicio = inicio;
  return turno;
}

function garantirPasso(turno, numero) {
  const n = Number(numero) || 0;
  if (!turno._passos.has(n)) {
    turno._passos.set(n, { numero: n, inicio: null, fim: null, uso: null });
    turno.passos += 1;
  }
  return turno._passos.get(n);
}

function resumoTurno(turno) {
  const temInicio = Number.isFinite(turno.inicio);
  const temFim = Number.isFinite(turno.fim);
  return {
    numero: turno.numero,
    inicio: turno.inicio,
    fim: turno.fim,
    duracaoMs: temInicio && temFim ? Math.max(0, turno.fim - turno.inicio) : null,
    primeiraRespostaMs: temInicio && Number.isFinite(turno.primeiraRespostaEm)
      ? Math.max(0, turno.primeiraRespostaEm - turno.inicio)
      : null,
    status: turno.status,
    motivo: turno.motivo,
    passos: turno.passos,
    ferramentas: turno.ferramentas,
    nomesFerramentas: turno.nomesFerramentas.slice(0, 20),
    uso: resumoUso(turno.uso),
    autor: turno.autor,
  };
}

/**
 * Cabecalho + titulo + mensagens de uma sessao, com o maximo de metadados que
 * o log do DSH oferece: tokens por passo, tempos, modelo, turno, ferramentas
 * e o autor (quando o prompt veio do site).
 */
function resumir(arquivo) {
  const eventos = eventosDaSessao(arquivo);
  if (!eventos.length) return null;
  const meta = eventos.find((e) => e && e.type === 'session') || null;
  if (!meta) return null;
  const sessionId = String(meta.id || '');
  const mensagens = [];
  const turnos = new Map();
  let titulo = '';
  let ultimaAtividade = 0;
  let inicioSessao = null;
  let fimSessao = null;
  let criadoEm = Number(meta.createdAt) || 0;
  let provedor = '';
  let modelo = '';
  let contextWindow = null;
  let turnoAtual = 0;
  let totalFerramentas = 0;
  const nomesFerramentas = new Set();

  for (const evento of eventos) {
    if (!evento || typeof evento.type !== 'string') continue;
    const dados = evento.data || {};
    const quando = typeof evento.time === 'number' ? evento.time : null;
    if (quando !== null) {
      ultimaAtividade = Math.max(ultimaAtividade, quando);
      if (inicioSessao === null || quando < inicioSessao) inicioSessao = quando;
      if (fimSessao === null || quando > fimSessao) fimSessao = quando;
    }

    if (evento.type === 'session') {
      if (!criadoEm && Number(meta.createdAt)) criadoEm = Number(meta.createdAt);
      continue;
    }
    if (evento.type === 'request/header') {
      provedor = String(dados.header?.config?.provider || provedor);
      modelo = String(dados.header?.config?.model || modelo);
      continue;
    }
    if (evento.type === 'request/context') {
      provedor = String(dados.provider || provedor);
      modelo = String(dados.model || modelo);
      const janela = Number(dados.contextWindow);
      if (Number.isFinite(janela)) contextWindow = janela;
      continue;
    }
    if (evento.type === 'session/title') {
      if (dados.title) titulo = String(dados.title);
      continue;
    }
    if (evento.type === 'turn/start') {
      turnoAtual = Number(dados.turn) || turnoAtual;
      garantirTurno(turnos, turnoAtual, quando);
      continue;
    }
    if (evento.type === 'step/start') {
      const turno = garantirTurno(turnos, Number(dados.turn) || turnoAtual, quando);
      const passo = garantirPasso(turno, Number(dados.step));
      if (passo.inicio === null) passo.inicio = quando;
      continue;
    }
    if (evento.type === 'step/end') {
      const turno = garantirTurno(turnos, Number(dados.turn) || turnoAtual, quando);
      const passo = garantirPasso(turno, Number(dados.step));
      passo.fim = quando;
      continue;
    }
    if (evento.type === 'turn/end') {
      const turno = garantirTurno(turnos, Number(dados.turn) || turnoAtual, quando);
      turno.fim = quando;
      turno.motivo = dados.reason || null;
      const tipo = String(dados.reason?.kind || '');
      turno.status = tipo === 'error' ? 'erro' : (tipo === 'cancelled' ? 'cancelada' : 'concluida');
      continue;
    }
    if (evento.type === 'tool/call') {
      const turno = garantirTurno(turnos, Number(dados.turn) || turnoAtual, quando);
      turno.ferramentas += 1;
      totalFerramentas += 1;
      const nome = String(dados.name || 'ferramenta');
      if (!turno.nomesFerramentas.includes(nome)) turno.nomesFerramentas.push(nome);
      nomesFerramentas.add(nome);
      continue;
    }

    if (evento.type === 'user/message') {
      const texto = textoDeConteudo(dados.content);
      const origem = String(dados.source?.kind || '');
      if (!texto || origem === 'user-approval' || RUIDO.test(texto)) continue;
      const numeroTurno = turnoAtual || 0;
      const turno = garantirTurno(turnos, numeroTurno, quando);
      const autor = autorDaMensagem(sessionId, texto, quando);
      if (!turno.autor && autor) turno.autor = autor;
      const contagem = contarTexto(texto);
      mensagens.push({
        role: 'user',
        text: texto,
        time: quando,
        meta: {
          versao: 1,
          role: 'user',
          live: false,
          time: quando,
          messageId: String(dados.id || ''),
          seq: Number(evento.seq) || null,
          turn: numeroTurno || null,
          sourceKind: origem || 'user',
          author: autor,
          texto: { ...contagem, tokensEstimados: estimarTokens(texto) },
          turno: null,
          tempoAteRespostaMs: null,
        },
      });
      continue;
    }

    if (evento.type === 'assistant/message') {
      const numeroTurno = Number(dados.turn) || turnoAtual || 0;
      const numeroPasso = Number(dados.step) || null;
      const turno = garantirTurno(turnos, numeroTurno, quando);
      const passo = garantirPasso(turno, numeroPasso || 0);
      turno.uso = somarUso(turno.uso, dados.usage);
      passo.uso = somarUso(passo.uso, dados.usage);
      if (turno.primeiraRespostaEm === null) turno.primeiraRespostaEm = quando;
      if (passo.inicio === null && quando !== null) passo.inicio = quando;

      const conteudo = Array.isArray(dados.message?.content) ? dados.message.content : [];
      const blocosTexto = conteudo.filter((parte) => parte && (parte.type === 'text' || parte.type === 'output_text'));
      const blocosRaciocinio = conteudo.filter((parte) => parte && parte.type === 'reasoning');
      const blocosFerramenta = conteudo.filter((parte) => parte && parte.type === 'tool-call');
      const texto = blocosTexto.map((parte) => String(parte.text || '')).join('').trim();
      if (!texto) continue;

      const inicioPasso = passo.inicio ?? turno.inicio;
      const primeiroStream = primeiroTempoDoStream(dados.stream);
      const tipos = [...new Set(conteudo.map((parte) => String(parte?.type || '')).filter(Boolean))];
      const charRaciocinio = blocosRaciocinio.reduce((soma, parte) => soma + String(parte.text || '').length, 0);
      const charTexto = blocosTexto.reduce((soma, parte) => soma + String(parte.text || '').length, 0);
      const duracaoPasso = Number.isFinite(inicioPasso)
        ? Math.max(0, (Number.isFinite(passo.fim) ? passo.fim : quando) - inicioPasso)
        : null;
      const geracaoMs = Number.isFinite(inicioPasso)
        ? Math.max(0, (quando ?? inicioPasso) - (primeiroStream ?? inicioPasso))
        : null;
      const primeiroTokenMs = primeiroStream !== null && Number.isFinite(inicioPasso)
        ? Math.max(0, primeiroStream - inicioPasso)
        : null;

      mensagens.push({
        role: 'assistant',
        text: texto,
        time: quando,
        meta: {
          versao: 1,
          role: 'assistant',
          live: false,
          time: quando,
          messageId: String(dados.message?.id || ''),
          seq: Number(evento.seq) || null,
          turn: numeroTurno || null,
          step: numeroPasso,
          provider: String(dados.message?.source?.provider || provedor || CFG_DSH.provedor),
          model: String(dados.message?.source?.model || modelo || CFG_DSH.modelo),
          usage: resumoUso(dados.usage),
          durationMs: duracaoPasso,
          generationMs: geracaoMs,
          firstTokenMs: primeiroTokenMs,
          contentTypes: tipos,
          reasoningChars: charRaciocinio,
          textChars: charTexto,
          toolCalls: blocosFerramenta.map((parte) => ({
            id: String(parte.id || ''),
            nome: String(parte.name || 'ferramenta'),
          })),
          interrupted: dados.interrupted === true,
          author: null,
          turno: null,
        },
      });
    }
  }

  for (const mensagem of mensagens) {
    const numeroTurno = Number(mensagem.meta?.turn);
    const turno = Number.isFinite(numeroTurno) ? turnos.get(numeroTurno) : null;
    if (!turno) continue;
    mensagem.meta.turno = resumoTurno(turno);
    if (!mensagem.meta.author && turno.autor) mensagem.meta.author = turno.autor;
    if (mensagem.role === 'user' && Number.isFinite(turno.primeiraRespostaEm)
        && Number.isFinite(mensagem.time)) {
      mensagem.meta.tempoAteRespostaMs = Math.max(0, turno.primeiraRespostaEm - mensagem.time);
    }
  }

  let usoTotal = null;
  for (const turno of turnos.values()) usoTotal = somarUso(usoTotal, turno.uso);

  let st;
  try { st = statSync(arquivo); } catch { st = null; }
  const primeiro = mensagens.find((m) => m.role === 'user');
  const tituloFinal = titulo || (primeiro ? primeiro.text.slice(0, 70) : '');
  const metaSessao = {
    id: sessionId,
    titulo: tituloFinal,
    cwd: String(meta.cwd || ''),
    criadoEm: criadoEm || inicioSessao || (st ? st.mtimeMs : null),
    atualizadoEm: ultimaAtividade || (st ? st.mtimeMs : Date.now()),
    modelo: modelo || CFG_DSH.modelo,
    provedor: provedor || CFG_DSH.provedor,
    contextWindow,
    turnos: turnos.size,
    mensagens: mensagens.length,
    ferramentas: totalFerramentas,
    nomesFerramentas: [...nomesFerramentas].slice(0, 30),
    uso: resumoUso(usoTotal),
    duracaoMs: inicioSessao !== null && fimSessao !== null ? Math.max(0, fimSessao - inicioSessao) : null,
    arquivo: path.basename(arquivo),
    maquina: process.env.USERNAME || process.env.USER || '',
    fonte: 'dsh',
  };

  return {
    id: sessionId,
    cwd: metaSessao.cwd,
    titulo: tituloFinal,
    mensagens,
    turnos: turnos.size,
    atualizadoEm: metaSessao.atualizadoEm,
    arquivo,
    meta: metaSessao,
  };
}

/** Lista as sessoes do DSH, da mais recente para a mais antiga. */
export function listar() {
  const itens = [];
  for (const arquivo of arquivosSessao()) {
    const resumo = resumir(arquivo);
    if (!resumo || !resumo.id) continue;
    itens.push({
      id: 'dsh:' + resumo.id,
      idReal: resumo.id,
      nome: resumo.titulo || ('Sessao ' + resumo.id.slice(-8)),
      cwd: resumo.cwd,
      model: 'deepseek',
      origem: 'dsh',
      fonte: 'dsh',
      mensagens: resumo.mensagens.length,
      updatedAt: new Date(resumo.atualizadoEm).toISOString(),
    });
  }
  itens.sort((a, b) => (a.updatedAt < b.updatedAt ? 1 : -1));
  return itens.slice(0, CFG_DSH.maxSessions);
}

/** Mensagens de uma sessao (id pode vir com ou sem o prefixo `dsh:`). */
export function lerMensagens(idBruto) {
  const id = String(idBruto || '').replace(/^dsh:/, '');
  if (!id) return null;
  const arquivo = arquivosSessao().find((arq) => path.basename(path.dirname(arq)) === id);
  if (!arquivo) return null;
  const resumo = resumir(arquivo);
  if (!resumo) return null;
  return {
    id: 'dsh:' + resumo.id,
    cwd: resumo.cwd,
    model: resumo.meta?.modelo || 'deepseek-flash',
    fonte: 'dsh',
    messages: resumo.mensagens.slice(-200),
    meta: resumo.meta,
  };
}

/** Descobre como chamar o CLI do app (a pasta de runtime tem versao e muda). */
function resolverCli() {
  if (CFG_DSH.cli) return { comando: CFG_DSH.cli, prefixo: [], shell: true };
  const base = path.join(process.env.LOCALAPPDATA || '', 'Programs', 'DeepSeek Harness', 'resources', 'runtime', 'cli', 'bin', 'dsh.cmd');
  if (!existsSync(base)) return { comando: 'dsh', prefixo: [], shell: true };
  // Evita shell: chama o proprio executavel do app com ELECTRON_RUN_AS_NODE=1,
  // igual ao dsh.cmd faz (o caminho tem espacos, o shell complicaria).
  const instalacao = path.resolve(path.dirname(base), '..', '..', '..', '..');
  const exe = path.join(instalacao, 'DeepSeek Harness.exe');
  const cliJs = path.join(instalacao, 'resources', 'app.asar', 'dsh', 'node_modules',
    '@deepseek-ai', 'dsh-desktop-host', 'lib', 'cli.js');
  // O cli.js vive dentro do app.asar: existsSync nao enxerga ali (quem resolve
  // caminho asar e o proprio executavel do Electron), entao so conferimos o exe.
  if (existsSync(exe) && existsSync(path.join(instalacao, 'resources', 'app.asar'))) {
    return { comando: exe, prefixo: ['--expose-internals', cliJs], shell: false };
  }
  return { comando: base, prefixo: [], shell: true };
}

function textoDaFerramenta(nome, entrada) {
  if (!entrada || typeof entrada !== 'object') return String(nome || 'ferramenta');
  const pedaco = entrada.command || entrada.description || entrada.path || entrada.url || entrada.query || '';
  return String(pedaco || nome || 'ferramenta').slice(0, 400);
}

/**
 * Roda um turno na sessao (ou cria uma nova quando sessionId e null).
 * `run` e o objeto da ponte: preenche eventos/texto/status.
 */
export function rodar(run, prompt, sessionIdReal, timeoutMs) {
  run.modelo = run.modelo || CFG_DSH.modelo;
  run.provedor = run.provedor || CFG_DSH.provedor;
  run.uso = run.uso || null;
  run.passos = Array.isArray(run.passos) ? run.passos : [];
  run.turno = run.turno ?? null;
  run.etapa = run.etapa ?? null;
  run.inicioTurno = run.inicioTurno ?? null;
  run.inicioEtapa = run.inicioEtapa ?? null;
  run.fimTurno = run.fimTurno ?? null;
  run.motivo = run.motivo ?? null;

  const { comando, prefixo, shell } = resolverCli();
  const args = [...prefixo, 'headless', '--json'];
  if (sessionIdReal) args.push('--session-id', sessionIdReal);
  args.push('-');

  // Continua a conversa no diretorio em que ela foi gravada; so usa o padrao
  // quando e uma sessao nova (ou quando a pasta original nao existe mais).
  let cwd = run.cwd || (sessionIdReal ? cwdDaSessao(sessionIdReal) : '');
  if (!cwd || !existsSync(cwd)) cwd = cwdPadrao();
  run.cwd = cwd;
  return new Promise((resolve, reject) => {
    const filho = spawn(comando, args, {
      cwd,
      shell,
      windowsHide: true,
      stdio: ['pipe', 'pipe', 'pipe'],
      env: {
        ...process.env,
        DSH_PERMISSION_MODE: CFG_DSH.permissionMode,
        ELECTRON_RUN_AS_NODE: shell ? process.env.ELECTRON_RUN_AS_NODE : '1',
      },
    });
    run.proc = filho;

    let pendente = '';
    let erros = '';
    let terminou = false;

    const timer = setTimeout(() => {
      if (terminou) return;
      terminou = true;
      try { filho.kill(); } catch {}
      run.eventos.push({ kind: 'error', text: 'tempo esgotado (' + Math.round(timeoutMs / 1000) + 's)' });
      reject(new Error('timeout'));
    }, timeoutMs);

    filho.stdout.on('data', (buf) => {
      pendente += buf.toString('utf8');
      const linhas = pendente.split('\n');
      pendente = linhas.pop() ?? '';
      for (const linha of linhas) {
        const texto = linha.trim();
        if (!texto.startsWith('{')) continue;
        let evento; try { evento = JSON.parse(texto); } catch { continue; }
        aplicarEvento(run, evento);
      }
    });
    filho.stderr.on('data', (buf) => { erros += buf.toString('utf8'); });

    filho.on('error', (erro) => {
      if (terminou) return;
      terminou = true;
      clearTimeout(timer);
      reject(erro);
    });
    filho.on('close', (codigo) => {
      if (terminou) return;
      terminou = true;
      clearTimeout(timer);
      run.proc = null;
      if (codigo !== 0 && run.status !== 'error' && run.status !== 'cancelled') {
        const detalhe = erros.trim().split('\n').filter(Boolean).slice(-1)[0] || ('dsh saiu com codigo ' + codigo);
        run.status = 'error';
        run.erro = detalhe;
        run.eventos.push({ kind: 'error', text: detalhe });
        return reject(Object.assign(new Error(detalhe), { stderr: erros }));
      }
      resolve();
    });

    try { filho.stdin.write(prompt, 'utf8'); filho.stdin.end(); } catch {}
  });
}

/** Cola o uso do passo no ultimo texto emitido naquele turno/passo. */
function anexarUsoAoPasso(run, passo) {
  for (let i = run.eventos.length - 1; i >= 0; i -= 1) {
    const ev = run.eventos[i];
    if (ev.kind !== 'agent' || !ev.meta) continue;
    if (ev.meta.turn !== passo.turn || ev.meta.step !== passo.step) continue;
    if (ev.meta.usage) break;
    ev.meta.usage = resumoUso(passo.uso);
    ev.meta.endedAt = passo.fim;
    ev.meta.durationMs = passo.inicio !== null && passo.fim !== null
      ? Math.max(0, passo.fim - passo.inicio)
      : null;
    break;
  }
}

/** Traduz os eventos NDJSON do `dsh headless --json` para os eventos da ponte. */
function aplicarEvento(run, evento) {
  const tipo = String(evento?.type || '');
  if (tipo === 'session' && evento.sessionId) {
    run.threadId = 'dsh:' + String(evento.sessionId);
    run.idReal = String(evento.sessionId);
    run.sessionId = String(evento.sessionId);
    if (evento.cwd) run.cwd = String(evento.cwd);
    return;
  }
  if (tipo === 'thinking') {
    const texto = String(evento.text || '').trim();
    if (texto) run.eventos.push({ kind: 'reasoning', text: texto, done: true });
    return;
  }
  if (tipo === 'text') {
    const texto = String(evento.text || '');
    if (!texto) return;
    const chave = 'txt-' + run.eventos.length;
    const quando = Date.now();
    const meta = {
      versao: 1,
      role: 'assistant',
      live: true,
      time: quando,
      runId: run.id,
      sessionId: run.threadId || null,
      turn: run.turno,
      step: run.etapa,
      provider: run.provedor,
      model: run.modelo,
      startedAt: run.inicioEtapa || run.inicioExecucao || run.inicio || quando,
      usage: null,
      durationMs: null,
      firstTokenMs: null,
      interrupted: false,
    };
    const dado = { id: chave, kind: 'agent', text: texto, done: true, time: quando, meta };
    run.eventos.push(dado);
    run.mapa[chave] = dado;
    run.texto = texto;
    return;
  }
  if (tipo === 'tool_call') {
    const chave = String(evento.callId || ('t' + run.eventos.length));
    const dado = {
      id: chave,
      kind: 'command',
      command: textoDaFerramenta(evento.tool, evento.input),
      output: '',
      exitCode: null,
      done: false,
    };
    run.mapa[chave] = dado;
    run.eventos.push(dado);
    return;
  }
  if (tipo === 'tool_result') {
    const chave = String(evento.callId || '');
    const atual = chave ? run.mapa[chave] : null;
    const saida = typeof evento.result === 'string' ? evento.result : JSON.stringify(evento.result ?? '');
    if (atual) {
      atual.output = saida;
      atual.done = true;
      atual.exitCode = evento.status === 'completed' ? 0 : null;
    } else {
      run.eventos.push({ kind: 'command', command: '', output: saida, exitCode: null, done: true });
    }
    return;
  }
  if (tipo === 'status') {
    const fase = String(evento.phase || '');
    if (fase === 'turn_start') {
      run.turno = Number(evento.turn) || run.turno;
      run.inicioTurno = Date.now();
      run.fimTurno = null;
      run.motivo = null;
      return;
    }
    if (fase === 'step_start') {
      run.turno = Number(evento.turn) || run.turno;
      run.etapa = Number(evento.step) || run.etapa;
      run.inicioEtapa = Date.now();
      return;
    }
    if (fase === 'step_end') {
      const passo = {
        turn: Number(evento.turn) || run.turno,
        step: Number(evento.step) || run.etapa,
        inicio: run.inicioEtapa,
        fim: Date.now(),
        uso: evento.usage ? { ...evento.usage } : null,
      };
      run.passos.push(passo);
      if (evento.usage) {
        run.uso = somarUso(run.uso, evento.usage);
        anexarUsoAoPasso(run, passo);
      }
      run.etapa = passo.step;
      run.inicioEtapa = null;
      return;
    }
    if (fase === 'turn_end') {
      run.fimTurno = Date.now();
      run.motivo = evento.reason || null;
      run.turno = Number(evento.turn) || run.turno;
      const motivo = evento.reason || {};
      if (motivo.kind === 'error') {
        run.status = 'error';
        run.erro = String(motivo.error?.message || 'turno falhou');
        run.eventos.push({ kind: 'error', text: run.erro });
      }
      return;
    }
  }
  if (tipo === 'final') {
    const texto = String(evento.text || '').trim();
    if (texto) run.texto = texto;
    return;
  }
  if (tipo === 'error') {
    run.status = 'error';
    run.erro = String(evento.message || 'erro');
    run.eventos.push({ kind: 'error', text: run.erro });
  }
}
