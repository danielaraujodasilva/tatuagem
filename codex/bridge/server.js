/**
 * Ponte local entre a pagina /codex (PHP/XAMPP) e o Codex CLI.
 *
 * - Ouve apenas em 127.0.0.1 (nunca fica exposta na internet).
 * - Exige o token de bridge/data/token.txt (o PHP le o mesmo arquivo).
 * - Le os rollouts de $CODEX_HOME/sessions e os nomes de session_index.jsonl.
 * - Roda o prompt com `codex exec [resume <id>] --json` no CODEX_HOME do app,
 *   entao a conversa tambem aparece no app Codex.
 *
 *   node server.js
 */
import http from 'node:http';
import { spawn } from 'node:child_process';
import crypto from 'node:crypto';
import {
  existsSync, mkdirSync, readFileSync, writeFileSync, appendFileSync,
  readdirSync, statSync, openSync, readSync, closeSync, renameSync, unlinkSync,
} from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { listar as listarSessoesDSH, lerMensagens as lerMensagensDSH, rodar as rodarDSH, registrarSessao as registrarSessaoDSH } from './dsh.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const CFG = {
  host: '127.0.0.1',
  port: Number(process.env.CODEX_WEB_PORT || 3051),
  codexHome: process.env.CODEX_WEB_HOME || 'C:\\Users\\server_spd\\.codex',
  workdir: process.env.CODEX_WEB_WORKDIR || 'C:\\Users\\server_spd\\Documents\\ChatGPT\\server',
  exe: process.env.CODEX_WEB_EXE || '',
  timeoutMs: Number(process.env.CODEX_WEB_TIMEOUT_MS || 6 * 60 * 60 * 1000),
  maxConcurrent: 3,
  maxSessions: 300,
};

const DATA_DIR = path.join(__dirname, 'data');
const LOG_FILE = path.join(DATA_DIR, 'bridge.log');
const TOKEN_FILE = path.join(DATA_DIR, 'token.txt');
mkdirSync(DATA_DIR, { recursive: true });

function log(evento, dados = {}) {
  const linha = '[' + new Date().toISOString() + '] ' + evento + ' ' + JSON.stringify(dados) + '\n';
  try { appendFileSync(LOG_FILE, linha); } catch {}
}

function carregarToken() {
  try {
    const existente = readFileSync(TOKEN_FILE, 'utf8').trim();
    if (existente) return existente;
  } catch {}
  const novo = crypto.randomBytes(32).toString('hex');
  try { writeFileSync(TOKEN_FILE, novo, { mode: 0o600 }); } catch {}
  log('token_criado', { arquivo: TOKEN_FILE });
  return novo;
}
const TOKEN = carregarToken();

/** Descobre o codex.exe instalado pelo app (a pasta tem hash e muda a cada update). */
function resolverCodexExe() {
  if (CFG.exe) return CFG.exe;
  const base = path.join(process.env.LOCALAPPDATA || '', 'OpenAI', 'Codex', 'bin');
  try {
    const candidatos = readdirSync(base)
      .map((pasta) => path.join(base, pasta, 'codex.exe'))
      .filter((arq) => existsSync(arq))
      .map((arq) => ({ arq, mtime: statSync(arq).mtimeMs }))
      .sort((a, b) => b.mtime - a.mtime);
    if (candidatos.length) return candidatos[0].arq;
  } catch (erro) {
    log('codex_exe_error', { erro: String(erro?.message || erro) });
  }
  return 'codex';
}

/** Todos os arquivos rollout-*.jsonl dentro de CODEX_HOME/sessions. */
function rollouts() {
  const raiz = path.join(CFG.codexHome, 'sessions');
  const pilha = [raiz];
  const achados = [];
  while (pilha.length) {
    const dir = pilha.pop();
    let entradas = [];
    try { entradas = readdirSync(dir, { withFileTypes: true }); } catch { continue; }
    for (const entrada of entradas) {
      const completo = path.join(dir, entrada.name);
      if (entrada.isDirectory()) pilha.push(completo);
      else if (entrada.name.startsWith('rollout-') && entrada.name.endsWith('.jsonl')) achados.push(completo);
    }
  }
  return achados;
}

function acharRollout(threadId) {
  if (!threadId) return null;
  const alvo = String(threadId);
  return rollouts().find((arq) => path.basename(arq).endsWith(alvo + '.jsonl')) || null;
}

const cacheMeta = new Map();

/** Le a primeira linha em blocos (a linha de metadados e bem grande). */
function lerPrimeiraLinha(arq, maxBytes = 4 * 1024 * 1024) {
  const fd = openSync(arq, 'r');
  try {
    const bloco = Buffer.alloc(65536);
    let acumulado = '';
    let posicao = 0;
    while (posicao < maxBytes) {
      const lidos = readSync(fd, bloco, 0, bloco.length, posicao);
      if (!lidos) break;
      posicao += lidos;
      acumulado += bloco.toString('utf8', 0, lidos);
      const fim = acumulado.indexOf('\n');
      if (fim >= 0) return acumulado.slice(0, fim);
    }
    return acumulado;
  } finally {
    closeSync(fd);
  }
}

/** Le os ultimos bytes do arquivo (rollouts podem ter centenas de MB). */
function lerFinal(arq, maxBytes) {
  const fd = openSync(arq, 'r');
  try {
    const tamanho = statSync(arq).size;
    const inicio = Math.max(0, tamanho - maxBytes);
    const buf = Buffer.alloc(tamanho - inicio);
    let lidos = 0;
    while (lidos < buf.length) {
      const n = readSync(fd, buf, lidos, buf.length - lidos, inicio + lidos);
      if (!n) break;
      lidos += n;
    }
    return buf.toString('utf8', 0, lidos);
  } finally {
    closeSync(fd);
  }
}

/** Metadados da sessao (id, cwd, data, etc) lidos da primeira linha. */
function lerMeta(arq) {
  try {
    const st = statSync(arq);
    const cache = cacheMeta.get(arq);
    if (cache && cache.mtime === st.mtimeMs) return cache.meta;
    const evento = JSON.parse(lerPrimeiraLinha(arq));
    const meta = evento?.payload || {};
    cacheMeta.set(arq, { mtime: st.mtimeMs, meta });
    return meta;
  } catch {
    return null;
  }
}

function lerIndice() {
  const indice = new Map();
  try {
    const bruto = readFileSync(path.join(CFG.codexHome, 'session_index.jsonl'), 'utf8');
    for (const linha of bruto.split('\n')) {
      const texto = linha.trim();
      if (!texto) continue;
      let obj; try { obj = JSON.parse(texto); } catch { continue; }
      if (obj && obj.id) indice.set(obj.id, obj);
    }
  } catch {}
  return indice;
}

function listarSessoes() {
  const indice = lerIndice();
  const itens = [];
  for (const arq of rollouts()) {
    let st;
    try { st = statSync(arq); } catch { continue; }
    const id = path.basename(arq, '.jsonl').slice(-36);
    const meta = lerMeta(arq);
    const nome = String(indice.get(id)?.thread_name || '').trim();
    itens.push({
      id,
      nome: nome || ('Sessao ' + id.slice(0, 8)),
      cwd: meta?.cwd || '',
      model: meta?.model_provider || '',
      origem: meta?.originator || '',
      fonte: 'codex',
      updatedAt: st.mtime.toISOString(),
    });
  }
  itens.sort((a, b) => (a.updatedAt < b.updatedAt ? 1 : -1));
  return itens.slice(0, CFG.maxSessions);
}

/** Sessoes do DSH (o site roda so com ele; o Codex saiu do caminho). */
function listarTodasSessoes() {
  try {
    return listarSessoesDSH();
  } catch (erro) {
    log('dsh_listar_falhou', { erro: String(erro?.message || erro) });
    return [];
  }
}

const PREFIXOS_CONTEXTO = /^(#\s*AGENTS\.md instructions|<(environment_context|user_instructions|permissions instructions|collaboration_mode|skills_instructions))/i;

function lerMensagens(threadId) {
  const arq = acharRollout(threadId);
  if (!arq) return null;
  const meta = lerMeta(arq);
  const bruto = lerFinal(arq, 12 * 1024 * 1024);
  const mensagens = [];
  let primeira = true;
  for (const linha of bruto.split('\n')) {
    if (primeira) { primeira = false; continue; }
    const texto = linha.trim();
    if (!texto) continue;
    let evento; try { evento = JSON.parse(texto); } catch { continue; }
    if (evento.type !== 'response_item') continue;
    const payload = evento.payload || {};
    if (payload.type !== 'message') continue;
    if (payload.role !== 'user' && payload.role !== 'assistant') continue;
    const conteudo = (Array.isArray(payload.content) ? payload.content : [])
      .map((parte) => (parte && typeof parte.text === 'string' ? parte.text : ''))
      .join('')
      .trim();
    if (!conteudo || PREFIXOS_CONTEXTO.test(conteudo)) continue;
    mensagens.push({ role: payload.role, text: conteudo });
  }
  return {
    id: String(meta?.session_id || threadId),
    cwd: meta?.cwd || '',
    model: meta?.model_provider || '',
    messages: mensagens.slice(-200),
  };
}

/** Recorta um trecho do texto ao redor do termo achado. */
function recorte(texto, pos, tam, margem = 60) {
  const inicio = Math.max(0, pos - margem);
  const fim = Math.min(texto.length, pos + tam + margem);
  return (inicio > 0 ? '...' : '') + texto.slice(inicio, fim).replace(/\s+/g, ' ').trim() + (fim < texto.length ? '...' : '');
}

/** Procura o termo dentro das mensagens das conversas mais recentes. */
function buscarConteudo(termo, limite = 40) {
  const alvo = String(termo || '').trim().toLowerCase();
  if (alvo.length < 2) return [];
  const indice = lerIndice();
  const arquivos = rollouts()
    .map((arq) => { let st; try { st = statSync(arq); } catch { return null; } return { arq, mtime: st.mtimeMs }; })
    .filter(Boolean)
    .sort((a, b) => b.mtime - a.mtime)
    .slice(0, 60);

  const resultados = [];
  for (const { arq, mtime } of arquivos) {
    if (resultados.length >= limite) break;
    const id = idDoRollout(path.basename(arq));
    const linhas = lerFinal(arq, 512 * 1024).split('\n');
    linhas.shift();
    for (const linha of linhas) {
      const texto = linha.trim();
      if (!texto || texto.charCodeAt(0) !== 123) continue;
      let evento; try { evento = JSON.parse(texto); } catch { continue; }
      if (evento.type !== 'response_item') continue;
      const p = evento.payload || {};
      if (p.type !== 'message' || (p.role !== 'user' && p.role !== 'assistant')) continue;
      const conteudo = (Array.isArray(p.content) ? p.content : [])
        .map((parte) => (parte && typeof parte.text === 'string' ? parte.text : ''))
        .join('')
        .trim();
      if (!conteudo || PREFIXOS_CONTEXTO.test(conteudo)) continue;
      const pos = conteudo.toLowerCase().indexOf(alvo);
      if (pos < 0) continue;
      const nome = String(indice.get(id)?.thread_name || '').trim();
      resultados.push({
        id,
        nome: nome || ('Sessao ' + id.slice(0, 8)),
        role: p.role,
        trecho: recorte(conteudo, pos, alvo.length),
        updatedAt: new Date(mtime).toISOString(),
      });
      break;
    }
  }
  return resultados;
}

/** Publica a thread na sidebar do app Codex (coluna source = 'vscode'). */
async function publicarThread(threadId, nome) {
  try {
    const arquivo = path.join(CFG.codexHome, 'state_5.sqlite');
    if (!existsSync(arquivo)) return false;
    const { DatabaseSync } = await import('node:sqlite');
    const banco = new DatabaseSync(arquivo);
    try {
      const linha = banco.prepare('SELECT source, name FROM threads WHERE id = ?').get(threadId);
      if (!linha) return false;
      const novo = nome || linha.name;
      if (linha.source !== 'vscode' || novo !== linha.name) {
        banco.prepare('UPDATE threads SET source = ?, name = ? WHERE id = ?').run('vscode', novo, threadId);
        log('thread_publicada', { threadId, nome: novo });
      }
      return true;
    } finally {
      banco.close();
    }
  } catch (erro) {
    log('thread_publicar_falhou', { threadId, erro: String(erro?.message || erro) });
    return false;
  }
}

/** Registra a thread no session_index.jsonl do app (lista de conversas). */
function indexarSessao(threadId, nome) {
  try {
    const arquivo = path.join(CFG.codexHome, 'session_index.jsonl');
    if (!existsSync(arquivo)) return false;
    const bruto = readFileSync(arquivo, 'utf8');
    if (bruto.includes('"' + threadId + '"')) return false;
    const linha = JSON.stringify({ id: threadId, thread_name: nome || 'Web', updated_at: new Date().toISOString() });
    appendFileSync(arquivo, (bruto.endsWith('\n') ? '' : '\n') + linha + '\n');
    log('thread_indexada', { threadId });
    return true;
  } catch (erro) {
    log('thread_indexar_falhou', { threadId, erro: String(erro?.message || erro) });
    return false;
  }
}

/** Modelo usado pelo CLI: config.toml (ou CODEX_WEB_MODEL). Evita herdar
 *  o modelo de sessoes antigas do app, que pode nao existir no provedor. */
/** Renomeia a sessao no session_index.jsonl e na sidebar do app. */
async function renomearSessao(threadId, nome) {
  const novo = String(nome || '').replace(/\s+/g, ' ').trim().slice(0, 80);
  if (!threadId || !novo) return false;
  try {
    const arquivo = path.join(CFG.codexHome, 'session_index.jsonl');
    const saida = [];
    let achou = false;
    if (existsSync(arquivo)) {
      for (const linha of readFileSync(arquivo, 'utf8').split('\n')) {
        const texto = linha.trim();
        if (!texto) continue;
        let obj; try { obj = JSON.parse(texto); } catch { continue; }
        if (obj && obj.id === threadId) {
          obj.thread_name = novo;
          obj.updated_at = new Date().toISOString();
          achou = true;
        }
        saida.push(JSON.stringify(obj));
      }
    }
    if (!achou) saida.push(JSON.stringify({ id: threadId, thread_name: novo, updated_at: new Date().toISOString() }));
    writeFileSync(arquivo, saida.join('\n') + '\n');
  } catch (erro) {
    log('sessao_renomear_falhou', { threadId, erro: String(erro?.message || erro) });
    return false;
  }
  await publicarThread(threadId, novo);
  log('sessao_renomeada', { threadId, nome: novo });
  return true;
}

/** Remove a thread da sidebar do app (state_5.sqlite). */
async function removerThreadApp(threadId) {
  try {
    const arquivo = path.join(CFG.codexHome, 'state_5.sqlite');
    if (!existsSync(arquivo)) return false;
    const { DatabaseSync } = await import('node:sqlite');
    const banco = new DatabaseSync(arquivo);
    try { banco.prepare('DELETE FROM threads WHERE id = ?').run(threadId); return true; }
    finally { banco.close(); }
  } catch (erro) {
    log('thread_remover_falhou', { threadId, erro: String(erro?.message || erro) });
    return false;
  }
}

/** Remove a thread do session_index.jsonl (sem mexer no resto do arquivo). */
function removerDoIndice(threadId) {
  try {
    const arquivo = path.join(CFG.codexHome, 'session_index.jsonl');
    if (!existsSync(arquivo)) return;
    const saida = [];
    for (const linha of readFileSync(arquivo, 'utf8').split('\n')) {
      const texto = linha.trim();
      if (!texto) continue;
      let obj; try { obj = JSON.parse(texto); } catch { continue; }
      if (obj && obj.id === threadId) continue;
      saida.push(JSON.stringify(obj));
    }
    writeFileSync(arquivo, saida.join('\n') + (saida.length ? '\n' : ''));
  } catch (erro) {
    log('sessao_desindexar_falhou', { threadId, erro: String(erro?.message || erro) });
  }
}

// ------------------------------------------------------------------ lixeira

function caminhoLixeira() {
  return path.join(DATA_DIR, 'sessoes_apagadas');
}

/** Metadados da sessao apagada ficam num .meta.json ao lado do rollout. */
function caminhoMetaLixeira(arquivoRollout) {
  return arquivoRollout.replace(/\.jsonl$/i, '') + '.meta.json';
}

function gravarMetaLixeira(arquivoRollout, meta) {
  try { writeFileSync(caminhoMetaLixeira(arquivoRollout), JSON.stringify(meta), 'utf8'); } catch {}
}

function lerMetaLixeira(arquivoRollout) {
  try { return JSON.parse(readFileSync(caminhoMetaLixeira(arquivoRollout), 'utf8')); } catch { return null; }
}

/** Ultimos 36 caracteres do nome do rollout = id da thread. */
function idDoRollout(nomeArquivo) {
  return path.basename(nomeArquivo, '.jsonl').slice(-36);
}

function listarLixeira() {
  const itens = [];
  let entradas = [];
  try { entradas = readdirSync(caminhoLixeira()); } catch { return itens; }
  for (const nome of entradas) {
    if (!nome.startsWith('rollout-') || !nome.endsWith('.jsonl')) continue;
    const completo = path.join(caminhoLixeira(), nome);
    let st; try { st = statSync(completo); } catch { continue; }
    const meta = lerMetaLixeira(completo) || {};
    const id = String(meta.id || idDoRollout(nome));
    itens.push({
      id,
      nome: String(meta.nome || '').trim() || ('Sessao ' + id.slice(0, 8)),
      tamanho: st.size,
      apagadoEm: meta.apagadoEm || st.mtime.toISOString(),
    });
  }
  itens.sort((a, b) => (a.apagadoEm < b.apagadoEm ? 1 : -1));
  return itens;
}

/** Devolve o rollout da lixeira (ou null). */
function acharNaLixeira(threadId) {
  let entradas = [];
  try { entradas = readdirSync(caminhoLixeira()); } catch { return null; }
  const nome = entradas.find((n) => n.startsWith('rollout-') && n.endsWith(threadId + '.jsonl'));
  return nome ? path.join(caminhoLixeira(), nome) : null;
}

async function restaurarSessao(threadId) {
  const alvo = String(threadId || '').trim();
  if (!alvo) return { ok: false, motivo: 'nao_encontrada' };
  const origem = acharNaLixeira(alvo);
  if (!origem) return { ok: false, motivo: 'nao_encontrada' };

  const meta = lerMetaLixeira(origem) || {};
  let destino = String(meta.origem || '');
  if (!destino || !path.isAbsolute(destino)) {
    destino = path.join(CFG.codexHome, 'sessions', 'restauradas', path.basename(origem));
  }
  try {
    mkdirSync(path.dirname(destino), { recursive: true });
    renameSync(origem, destino);
  } catch (erro) {
    log('sessao_restaurar_falhou', { threadId: alvo, erro: String(erro?.message || erro) });
    return { ok: false, motivo: 'falhou' };
  }
  try { unlinkSync(caminhoMetaLixeira(origem)); } catch {}
  indexarSessao(alvo, String(meta.nome || '').trim() || ('Sessao ' + alvo.slice(0, 8)));
  log('sessao_restaurada', { threadId: alvo });
  return { ok: true, motivo: 'ok' };
}

/** Apaga da lixeira os rollouts mais antigos que `dias`. Devolve quantos sairam. */
function expurgarLixeira(dias = 30) {
  const limite = Date.now() - Math.max(1, Number(dias) || 30) * 24 * 60 * 60 * 1000;
  let entradas = [];
  try { entradas = readdirSync(caminhoLixeira()); } catch { return 0; }
  let removidos = 0;
  for (const nome of entradas) {
    if (!nome.endsWith('.jsonl')) continue;
    const completo = path.join(caminhoLixeira(), nome);
    let st; try { st = statSync(completo); } catch { continue; }
    if (st.mtimeMs >= limite) continue;
    try {
      unlinkSync(completo);
      try { unlinkSync(caminhoMetaLixeira(completo)); } catch {}
      removidos += 1;
    } catch {}
  }
  if (removidos) log('lixeira_expurgada', { removidos });
  return removidos;
}

/** Apaga a sessao: rollout vai para a lixeira, sai do indice e da sidebar. */
async function apagarSessao(threadId) {
  const alvo = String(threadId || '').trim();
  if (!alvo) return { ok: false, motivo: 'nao_encontrada' };
  if (runAtivaNaThread(alvo)) return { ok: false, motivo: 'em_uso' };
  const arq = acharRollout(alvo);
  if (!arq) return { ok: false, motivo: 'nao_encontrada' };

  const nome = String(lerIndice().get(alvo)?.thread_name || '').trim();
  try {
    mkdirSync(caminhoLixeira(), { recursive: true });
    const destino = path.join(caminhoLixeira(), path.basename(arq));
    renameSync(arq, destino);
    gravarMetaLixeira(destino, { id: alvo, nome, origem: arq, apagadoEm: new Date().toISOString() });
  } catch (erro) {
    log('sessao_apagar_falhou', { threadId: alvo, erro: String(erro?.message || erro) });
    return { ok: false, motivo: 'falhou' };
  }
  removerDoIndice(alvo);
  await removerThreadApp(alvo);
  expurgarLixeira();
  log('sessao_apagada', { threadId: alvo });
  return { ok: true, motivo: 'ok' };
}

function lerModeloConfig() {
  if (process.env.CODEX_WEB_MODEL) return process.env.CODEX_WEB_MODEL;
  try {
    const bruto = readFileSync(path.join(CFG.codexHome, 'config.toml'), 'utf8');
    const achado = bruto.match(/^\s*model\s*=\s*["']([^"']+)["']/m);
    if (achado) return achado[1];
  } catch {}
  return 'deepseek-flash';
}
const MODELO = lerModeloConfig();

// Provedor/modelo usados pela pagina web. O app Codex do desktop continua no
// config.toml dele (ChatGPT); so a ponte do site passa esses overrides.
const PROVEDOR = process.env.CODEX_WEB_PROVIDER || 'deepseek';
const PROVEDOR_CHAVE = process.env.CODEX_WEB_PROVIDER_KEY_VAR || 'DEEPSEEK_CODEX_KEY';

function argsProvedor() {
  return [
    '-c', 'model_provider=' + PROVEDOR,
    '-c', 'model_providers.' + PROVEDOR + '.env_key="' + PROVEDOR_CHAVE + '"',
  ];
}

function nomeDoThread(prompt) {
  const limpo = String(prompt || '').replace(/\s+/g, ' ').trim().slice(0, 50);
  return 'Web - ' + (limpo || 'conversa');
}

// ---------------------------------------------------------------- execucao

const runs = new Map();
let runSeq = 0;
let ativos = 0;
const filaGlobal = [];

function normalizarItem(item) {
  if (!item || typeof item !== 'object') return null;
  const tipo = String(item.type || '');
  if (tipo === 'agent_message') return { kind: 'agent', text: String(item.text || '') };
  if (tipo === 'reasoning') return { kind: 'reasoning', text: String(item.text || '') };
  if (tipo === 'command_execution') {
    return {
      kind: 'command',
      command: String(item.command || ''),
      output: String(item.aggregated_output || ''),
      exitCode: typeof item.exit_code === 'number' ? item.exit_code : null,
    };
  }
  if (tipo === 'file_change') {
    const mudancas = Array.isArray(item.changes) ? item.changes : [];
    return { kind: 'file', text: mudancas.map((m) => (m?.kind || 'edit') + ' ' + (m?.path || '')).join('\n') };
  }
  if (tipo === 'error') {
    const aviso = String(item.message || '');
    if (/^Model metadata for/.test(aviso)) return null;
    return { kind: 'error', text: aviso };
  }
  if (typeof item.text === 'string' && item.text) return { kind: 'info', text: item.text };
  return null;
}

/** Aplica um evento do `codex exec --json` na run (agrupa por item.id). */
function aplicarEvento(run, evento) {
  if (evento.type === 'thread.started' && evento.thread_id) { run.threadId = evento.thread_id; return; }
  if (evento.type === 'turn.failed') {
    run.status = 'error';
    run.erro = String(evento?.error?.message || 'turno falhou');
    run.eventos.push({ kind: 'error', text: run.erro });
    return;
  }
  if (evento.type !== 'item.started' && evento.type !== 'item.updated' && evento.type !== 'item.completed') return;
  const item = evento.item || {};
  const dado = normalizarItem(item);
  if (!dado) return;
  const chave = item.id || ('i' + run.eventos.length);
  dado.id = chave;
  dado.done = evento.type === 'item.completed';
  const atual = run.mapa[chave];
  if (atual) Object.assign(atual, dado);
  else { run.mapa[chave] = dado; run.eventos.push(dado); }
  if (dado.kind === 'agent' && dado.done) run.texto = dado.text;
}

/** Converte uma linha do rollout (.jsonl) em um evento da run (mesmo formato do exec). */
function normalizarRollout(evento) {
  const p = (evento && evento.payload) || {};
  if (evento.type === 'response_item') {
    if (p.type === 'reasoning') {
      const doConteudo = (Array.isArray(p.content) ? p.content : [])
        .filter((c) => c && c.type === 'reasoning_text')
        .map((c) => c.text || '').join('\n');
      const doResumo = (Array.isArray(p.summary) ? p.summary : []).map((s) => s.text || '').join('\n');
      const texto = (doConteudo || doResumo).trim();
      if (!texto) return null;
      return { id: p.id, kind: 'reasoning', text: texto, done: true };
    }
    if (p.type === 'function_call') {
      let cmd = '';
      try {
        const args = JSON.parse(String(p.arguments || '{}'));
        cmd = String(args.cmd || args.command || args.path || '').trim();
      } catch {}
      return { id: p.call_id || p.id, kind: 'command', command: cmd || String(p.name || 'ferramenta'), output: '', exitCode: null };
    }
    if (p.type === 'function_call_output') {
      const saida = String(p.output || '');
      const achado = saida.match(/Process exited with code (-?\d+)/);
      return { id: p.call_id || p.id, kind: 'command', output: saida, exitCode: achado ? Number(achado[1]) : null, done: true };
    }
    if (p.type === 'message' && p.role === 'assistant') {
      const texto = (Array.isArray(p.content) ? p.content : []).map((c) => c.text || '').join('').trim();
      if (!texto) return null;
      return { id: p.id, kind: 'agent', text: texto, done: true };
    }
  }
  return null;
}

function aplicarRollout(run, evento) {
  const dado = normalizarRollout(evento);
  if (!dado) return;
  const chave = dado.id || ('r' + run.eventos.length);
  dado.id = chave;
  const atual = run.mapa[chave];
  if (atual) Object.assign(atual, dado);
  else { run.mapa[chave] = dado; run.eventos.push(dado); }
  if (dado.kind === 'agent' && dado.done) run.texto = dado.text;
}

function imagensValidas(imagens) {
  if (!Array.isArray(imagens)) return [];
  return imagens
    .map((caminho) => String(caminho || '').trim())
    .filter((caminho) => {
      if (!caminho) return false;
      try { return statSync(caminho).isFile(); } catch { return false; }
    })
    .slice(0, 10);
}

function argsImagens(imagens) {
  const args = [];
  for (const caminho of imagens) args.push('-i', caminho);
  return args;
}

function rodarExec(run, prompt, threadId) {
  const exe = resolverCodexExe();
  const imagens = imagensValidas(run.images);
  // A pagina roda como "dono da maquina": sem sandbox e sem pedir aprovacao.
  // Sem isso o `codex exec` cai em workspace-write, nega escrita fora da pasta
  // de trabalho e o agente nao consegue resolver nada pelo site.
  const args = threadId
    ? ['exec', 'resume', '--json', '--skip-git-repo-check', '--dangerously-bypass-approvals-and-sandbox', ...argsProvedor(), '-m', MODELO, ...argsImagens(imagens), threadId, '-']
    : ['exec', '--json', '--skip-git-repo-check', '--dangerously-bypass-approvals-and-sandbox', ...argsProvedor(), '-m', MODELO, ...argsImagens(imagens), '-'];
  const cwd = (threadId && lerMeta(acharRollout(threadId))?.cwd) || CFG.workdir;

  return new Promise((resolve, reject) => {
    const filho = spawn(exe, args, {
      cwd,
      env: { ...process.env, CODEX_HOME: CFG.codexHome },
      windowsHide: true,
      stdio: ['pipe', 'pipe', 'pipe'],
    });
    run.proc = filho;
    let pendente = '';
    let erros = '';
    let terminou = false;

    const timer = setTimeout(() => {
      if (terminou) return;
      terminou = true;
      try { filho.kill(); } catch {}
      run.eventos.push({ kind: 'error', text: 'tempo esgotado (' + Math.round(CFG.timeoutMs / 1000) + 's)' });
      reject(new Error('timeout'));
    }, CFG.timeoutMs);

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
      if (codigo !== 0 && run.status !== 'error') {
        run.status = 'error';
        run.erro = 'codex saiu com codigo ' + codigo;
        run.eventos.push({ kind: 'error', text: run.erro });
        return reject(Object.assign(new Error(run.erro), { stderr: erros }));
      }
      if (run.status !== 'error') run.status = 'done';
      resolve();
    });

    try { filho.stdin.write(prompt, 'utf8'); filho.stdin.end(); } catch {}
  });
}

/** Alternativa quando a thread esta aberta no app (writer ativo): entrega via `codex queue`. */
function rodarQueue(run, prompt, threadId) {
  const rollout = acharRollout(threadId);
  if (!rollout) return Promise.reject(new Error('rollout da thread nao encontrado'));
  const offset = statSync(rollout).size;
  const desde = Math.floor(Date.now() / 1000);
  const exe = resolverCodexExe();

  return new Promise((resolve, reject) => {
    const imagens = imagensValidas(run.images);
    const mensagem = imagens.length
      ? prompt + '\n\n[anexos de imagem]' + imagens.map((c) => '\n- ' + c).join('')
      : prompt;
    const filho = spawn(exe, ['queue', '--thread', threadId, '--message', mensagem, ...argsProvedor()], {
      env: { ...process.env, CODEX_HOME: CFG.codexHome },
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let saida = '';
    filho.stdout.on('data', (buf) => { saida += buf.toString('utf8'); });
    filho.stderr.on('data', (buf) => { saida += buf.toString('utf8'); });
    filho.on('error', reject);
    filho.on('close', (codigo) => {
      if (codigo !== 0) return reject(new Error('queue saiu com codigo ' + codigo + ': ' + saida.slice(-200)));
      const inicio = Date.now();
      let lido = offset;
      let resto = '';
      const timer = setInterval(() => {
        let tamanho = 0;
        try { tamanho = statSync(rollout).size; } catch {}
        let concluido = false;
        let resposta = '';
        if (tamanho > lido) {
          const buf = Buffer.alloc(tamanho - lido);
          const fd = openSync(rollout, 'r');
          try { readSync(fd, buf, 0, buf.length, lido); } finally { closeSync(fd); }
          lido = tamanho;
          const partes = (resto + buf.toString('utf8')).split('\n');
          resto = partes.pop() ?? '';
          for (const linha of partes) {
            const texto = linha.trim();
            if (!texto) continue;
            let evento; try { evento = JSON.parse(texto); } catch { continue; }
            if (evento.type === 'event_msg' && evento.payload?.type === 'task_complete'
                && (evento.payload.started_at || 0) >= desde) {
              concluido = true;
              resposta = String(evento.payload.last_agent_message || '').trim();
              break;
            }
            aplicarRollout(run, evento);
          }
        }
        if (concluido) {
          clearInterval(timer);
          run.threadId = threadId;
          run.status = 'done';
          const ultimo = [...run.eventos].reverse().find((e) => e.kind === 'agent');
          if (resposta && (!ultimo || ultimo.text !== resposta)) {
            run.texto = resposta;
            run.eventos.push({ id: 'agent-final', kind: 'agent', text: resposta, done: true });
          } else if (ultimo) {
            ultimo.done = true;
            run.texto = ultimo.text;
          }
          return resolve();
        }
        if (Date.now() - inicio > CFG.timeoutMs) {
          clearInterval(timer);
          reject(new Error('timeout aguardando o turno do queue'));
        }
      }, 900);
    });
  });
}

async function executarRun(run) {
  run.status = 'running';
  run.inicioExecucao = Date.now();
  log('run_inicio', { run: run.id, threadId: run.threadId, novo: run.novo });
  try {
    if (run.fonte === 'dsh') {
      await rodarDSH(run, run.prompt, run.idReal, CFG.timeoutMs);
    } else if (run.threadId) {
      try {
        await rodarExec(run, run.prompt, run.threadId);
      } catch (erro) {
        const detalhe = String(erro?.stderr || erro?.message || erro);
        if (!/active writer/i.test(detalhe)) throw erro;
        run.status = 'running';
        run.erro = null;
        run.eventos = run.eventos.filter((e) => !(e.kind === 'error' && /^codex saiu com codigo/.test(String(e.text || ''))));
        run.eventos.push({ kind: 'status', text: 'Conversa aberta no app; enviando pela fila do Codex...' });
        await rodarQueue(run, run.prompt, run.threadId);
      }
    } else {
      await rodarExec(run, run.prompt, null);
    }
    if (run.status !== 'error') run.status = 'done';
    // Conversa nova do DSH: registra no workspace do app para aparecer na janela do DSH.
    if (run.fonte === 'dsh' && run.novo && run.idReal && run.status === 'done') {
      try { registrarSessaoDSH(run.idReal, run.cwd); } catch (erro) {
        log('dsh_registrar_falhou', { id: run.idReal, erro: String(erro?.message || erro) });
      }
    }
    if (run.fonte !== 'dsh' && run.threadId && run.novo) {
      await publicarThread(run.threadId, nomeDoThread(run.prompt));
      indexarSessao(run.threadId, nomeDoThread(run.prompt));
    }
  } catch (erro) {
    run.status = 'error';
    run.erro = String(erro?.message || erro);
    if (!run.eventos.some((e) => e.kind === 'error')) run.eventos.push({ kind: 'error', text: run.erro });
    log('run_erro', { run: run.id, erro: run.erro });
  } finally {
    run.fim = Date.now();
    run.proc = null;
    log('run_fim', { run: run.id, status: run.status, ms: run.fim - run.inicioExecucao });
  }
}

function agendar(run) {
  const iniciar = () => {
    ativos += 1;
    executarRun(run).finally(() => {
      ativos -= 1;
      const proximo = filaGlobal.shift();
      if (proximo) proximo();
    });
  };
  if (ativos < CFG.maxConcurrent) iniciar();
  else {
    run.status = 'queued';
    run.eventos.push({ kind: 'status', text: 'Na fila (ja existem ' + ativos + ' execucoes em andamento)...' });
    filaGlobal.push(iniciar);
  }
}

function runAtivaNaThread(threadId) {
  if (!threadId) return false;
  for (const run of runs.values()) {
    if (run.threadId === threadId && (run.status === 'running' || run.status === 'queued')) return true;
  }
  return false;
}

// ------------------------------------------------------------------ stream

function snapshotRun(run) {
  return {
    id: run.id,
    status: run.status,
    threadId: run.threadId,
    novo: run.novo,
    texto: run.texto,
    erro: run.erro,
    eventos: run.eventos,
    ms: (run.fim || Date.now()) - run.inicio,
  };
}

/** Assinatura barata pra saber se a run mudou desde o ultimo envio. */
function assinaturaRun(run) {
  return run.status + '|' + run.eventos.length + '|' + (run.texto || '').length + '|' + (run.erro || '');
}

/** SSE: envia o estado da run quando muda e encerra quando o turno termina. */
function abrirStream(req, res, run) {
  res.writeHead(200, {
    'Content-Type': 'text/event-stream; charset=utf-8',
    'Cache-Control': 'no-store',
    Connection: 'keep-alive',
    'X-Accel-Buffering': 'no',
  });
  res.write(': conectado\n\n');

  let vivo = true;
  let ultima = '';
  const inicio = Date.now();

  const encerrar = () => {
    if (!vivo) return;
    vivo = false;
    clearInterval(dados);
    clearInterval(ping);
    try { res.end(); } catch {}
  };

  const enviar = () => {
    const atual = runs.get(run.id);
    if (!atual) { try { res.write('event: fim\ndata: {}\n\n'); } catch {} return encerrar(); }
    const assinatura = assinaturaRun(atual);
    if (assinatura === ultima) return;
    ultima = assinatura;
    const corpo = JSON.stringify({ ok: true, run: snapshotRun(atual) });
    try { res.write('data: ' + corpo + '\n\n'); } catch { return encerrar(); }
    if (atual.status !== 'running' && atual.status !== 'queued') {
      try { res.write('event: fim\ndata: ' + corpo + '\n\n'); } catch {}
      encerrar();
    }
  };

  const dados = setInterval(() => {
    if (Date.now() - inicio > CFG.timeoutMs + 60_000) return encerrar();
    enviar();
  }, 350);
  const ping = setInterval(() => {
    if (!vivo) return;
    try { res.write(': ping\n\n'); } catch { encerrar(); }
  }, 15000);

  req.on('close', encerrar);
  enviar();
}

// ------------------------------------------------------------------ HTTP

function responderJson(res, status, corpo) {
  const texto = JSON.stringify(corpo);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(texto),
    'Cache-Control': 'no-store',
  });
  res.end(texto);
}

function lerCorpo(req) {
  return new Promise((resolve, reject) => {
    let bruto = '';
    req.on('data', (parte) => {
      bruto += parte.toString('utf8');
      if (bruto.length > 2_000_000) reject(new Error('corpo grande demais'));
    });
    req.on('end', () => resolve(bruto));
    req.on('error', reject);
  });
}

const servidor = http.createServer(async (req, res) => {
  const url = new URL(req.url || '/', 'http://' + CFG.host + ':' + CFG.port);

  if (req.headers['x-bridge-token'] !== TOKEN) {
    return responderJson(res, 403, { ok: false, error: 'token invalido' });
  }

  try {
    if (req.method === 'GET' && (url.pathname === '/health' || url.pathname === '/')) {
      let dshSessoes = 0;
      try { dshSessoes = listarSessoesDSH().length; } catch {}
      return responderJson(res, 200, {
        ok: true,
        workdir: CFG.workdir,
        motor: 'dsh',
        ativos,
        sessoes: dshSessoes,
      });
    }

    if (req.method === 'GET' && url.pathname === '/sessions') {
      return responderJson(res, 200, { ok: true, sessions: listarTodasSessoes() });
    }

    if (req.method === 'GET' && url.pathname === '/session') {
      const id = url.searchParams.get('id') || '';
      const sessao = id.startsWith('dsh:') ? lerMensagensDSH(id) : lerMensagens(id);
      if (!sessao) return responderJson(res, 404, { ok: false, error: 'sessao nao encontrada' });
      return responderJson(res, 200, { ok: true, session: sessao });
    }

    if (req.method === 'GET' && url.pathname === '/search') {
      return responderJson(res, 200, { ok: true, resultados: buscarConteudo(url.searchParams.get('q') || '') });
    }

    if (req.method === 'GET' && url.pathname === '/lixeira') {
      return responderJson(res, 200, { ok: true, apagadas: listarLixeira() });
    }

    if (req.method === 'POST' && url.pathname === '/session-restore') {
      const corpo = JSON.parse(await lerCorpo(req) || '{}');
      const id = String(corpo?.id || '').trim();
      if (!id) return responderJson(res, 400, { ok: false, error: 'id obrigatorio' });
      const r = await restaurarSessao(id);
      if (r.ok) return responderJson(res, 200, { ok: true });
      return responderJson(res, r.motivo === 'nao_encontrada' ? 404 : 500, {
        ok: false,
        error: r.motivo === 'nao_encontrada' ? 'essa conversa nao esta na lixeira' : 'nao consegui restaurar a conversa',
      });
    }

    if (req.method === 'POST' && url.pathname === '/session-rename') {
      const corpo = JSON.parse(await lerCorpo(req) || '{}');
      const id = String(corpo?.id || '').trim();
      const nome = String(corpo?.nome || '').trim();
      if (!id || !nome) return responderJson(res, 400, { ok: false, error: 'id e nome sao obrigatorios' });
      const ok = await renomearSessao(id, nome);
      return responderJson(res, ok ? 200 : 500, ok ? { ok: true } : { ok: false, error: 'nao consegui renomear a sessao' });
    }

    if (req.method === 'POST' && url.pathname === '/session-delete') {
      const corpo = JSON.parse(await lerCorpo(req) || '{}');
      const id = String(corpo?.id || '').trim();
      if (!id) return responderJson(res, 400, { ok: false, error: 'id obrigatorio' });
      const r = await apagarSessao(id);
      if (r.ok) return responderJson(res, 200, { ok: true });
      const mensagem = {
        em_uso: 'essa conversa esta rodando agora; pare o turno antes de apagar',
        nao_encontrada: 'conversa nao encontrada',
        falhou: 'nao consegui apagar a conversa',
      }[r.motivo] || 'nao consegui apagar a conversa';
      return responderJson(res, r.motivo === 'em_uso' ? 409 : 404, { ok: false, error: mensagem });
    }

    if (req.method === 'POST' && url.pathname === '/send') {
      const corpo = JSON.parse(await lerCorpo(req) || '{}');
      const prompt = String(corpo?.prompt || '').trim();
      const threadId = String(corpo?.threadId || '').trim() || null;
      const images = (Array.isArray(corpo?.images) ? corpo.images : [])
        .map((caminho) => String(caminho || '').trim())
        .filter(Boolean)
        .slice(0, 10);
      if (!prompt) return responderJson(res, 400, { ok: false, error: 'prompt vazio' });
      if (runAtivaNaThread(threadId)) {
        return responderJson(res, 409, { ok: false, error: 'ja existe um turno em andamento nesta conversa' });
      }
      // O site agora conversa apenas com o DeepSeek Harness.
      const fonte = 'dsh';
      const idReal = fonte === 'dsh' && threadId ? String(threadId).replace(/^dsh:/, '') : null;
      const run = {
        id: 'run_' + (++runSeq).toString(36) + '_' + Date.now().toString(36),
        prompt,
        threadId,
        idReal,
        fonte,
        cwd: String(corpo?.cwd || '') || null,
        images,
        novo: !threadId,
        status: 'queued',
        eventos: [],
        mapa: Object.create(null),
        texto: '',
        erro: null,
        inicio: Date.now(),
        fim: null,
        proc: null,
      };
      runs.set(run.id, run);
      limparRunsAntigas();
      agendar(run);
      return responderJson(res, 200, { ok: true, runId: run.id, threadId: run.threadId });
    }

    if (req.method === 'GET' && url.pathname === '/run') {
      const run = runs.get(url.searchParams.get('id') || '');
      if (!run) return responderJson(res, 404, { ok: false, error: 'run nao encontrada' });
      return responderJson(res, 200, { ok: true, run: snapshotRun(run) });
    }

    if (req.method === 'GET' && url.pathname === '/run/stream') {
      const run = runs.get(url.searchParams.get('id') || '');
      if (!run) return responderJson(res, 404, { ok: false, error: 'run nao encontrada' });
      return abrirStream(req, res, run);
    }

    if (req.method === 'POST' && url.pathname === '/cancel') {
      const corpo = JSON.parse(await lerCorpo(req) || '{}');
      const run = runs.get(String(corpo?.runId || ''));
      if (!run) return responderJson(res, 404, { ok: false, error: 'run nao encontrada' });
      run.status = 'error';
      run.erro = 'cancelado';
      try { run.proc?.kill(); } catch {}
      return responderJson(res, 200, { ok: true });
    }

    responderJson(res, 404, { ok: false, error: 'rota nao encontrada' });
  } catch (erro) {
    responderJson(res, 500, { ok: false, error: String(erro?.message || erro) });
  }
});

function limparRunsAntigas() {
  if (runs.size <= 60) return;
  for (const [chave, run] of runs) {
    if (run.status !== 'running' && run.status !== 'queued') runs.delete(chave);
    if (runs.size <= 40) break;
  }
}

// Um erro solto nao pode derrubar a ponte (a pagina ficaria sem conversas).
process.on('uncaughtException', (erro) => {
  log('erro_nao_tratado', { erro: String(erro?.stack || erro?.message || erro) });
});
process.on('unhandledRejection', (motivo) => {
  log('promessa_rejeitada', { erro: String(motivo?.stack || motivo?.message || motivo) });
});

servidor.on('error', (erro) => {
  if (erro?.code === 'EADDRINUSE') {
    log('porta_em_uso', { porta: CFG.port });
    process.exit(0);
  }
  log('servidor_erro', { erro: String(erro?.message || erro) });
  process.exit(1);
});

servidor.listen(CFG.port, CFG.host, () => {
  expurgarLixeira();
  log('bridge_iniciada', {
    url: 'http://' + CFG.host + ':' + CFG.port,
    workdir: CFG.workdir,
    codexHome: CFG.codexHome,
    codexExe: resolverCodexExe(),
    modelo: MODELO,
  });
});
