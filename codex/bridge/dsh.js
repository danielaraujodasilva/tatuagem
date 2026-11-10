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
};

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

/** Cabecalho + titulo + mensagens de uma sessao. */
function resumir(arquivo) {
  const eventos = eventosDaSessao(arquivo);
  if (!eventos.length) return null;
  const meta = eventos.find((e) => e && e.type === 'session') || null;
  if (!meta) return null;
  let titulo = '';
  const mensagens = [];
  let ultimaAtividade = 0;
  let turnos = 0;

  for (const evento of eventos) {
    if (!evento || typeof evento.type !== 'string') continue;
    const dados = evento.data || {};
    if (typeof evento.time === 'number') ultimaAtividade = Math.max(ultimaAtividade, evento.time);
    if (evento.type === 'turn/start') turnos += 1;
    if (evento.type === 'session/title' && dados.title) titulo = String(dados.title);
    if (evento.type === 'user/message') {
      const texto = textoDeConteudo(dados.content);
      // Eventos de sistema (mudanca de permissao etc.) entram como user/message.
      const origem = String(dados.source?.kind || '');
      if (texto && origem !== 'user-approval' && !RUIDO.test(texto)) mensagens.push({ role: 'user', text: texto });
    }
    if (evento.type === 'assistant/message') {
      const texto = textoDeConteudo(dados.message?.content);
      if (texto) mensagens.push({ role: 'assistant', text: texto });
    }
  }

  let st;
  try { st = statSync(arquivo); } catch { st = null; }
  const primeiro = mensagens.find((m) => m.role === 'user');
  return {
    id: String(meta.id || ''),
    cwd: String(meta.cwd || ''),
    titulo: titulo || (primeiro ? primeiro.text.slice(0, 70) : ''),
    mensagens,
    turnos,
    atualizadoEm: ultimaAtividade || (st ? st.mtimeMs : Date.now()),
    arquivo,
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
    model: 'deepseek',
    fonte: 'dsh',
    messages: resumo.mensagens.slice(-200),
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

/** Traduz os eventos NDJSON do `dsh headless --json` para os eventos da ponte. */
function aplicarEvento(run, evento) {
  const tipo = String(evento?.type || '');
  if (tipo === 'session' && evento.sessionId) {
    run.threadId = 'dsh:' + String(evento.sessionId);
    run.idReal = String(evento.sessionId);
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
    run.eventos.push({ id: chave, kind: 'agent', text: texto, done: true });
    run.mapa[chave] = run.eventos[run.eventos.length - 1];
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
  if (tipo === 'status' && evento.phase === 'turn_end') {
    const motivo = evento.reason || {};
    if (motivo.kind === 'error') {
      run.status = 'error';
      run.erro = String(motivo.error?.message || 'turno falhou');
      run.eventos.push({ kind: 'error', text: run.erro });
    }
    return;
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
