<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/../includes/app_menu.php';
require_once __DIR__ . '/../includes/system_settings.php';

$settings = system_settings_load();
$model = trim((string)($settings['data_ai_model'] ?? 'qwen3:14b')) ?: 'qwen3:14b';
$timeout = (int)($settings['data_ai_timeout_seconds'] ?? 240);

function data_ai_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistente IA de Dados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            min-height: 100vh;
            color: #e5edf7;
            background:
                radial-gradient(circle at top left, rgba(34, 197, 94, 0.16), transparent 34rem),
                radial-gradient(circle at top right, rgba(56, 189, 248, 0.14), transparent 30rem),
                #07111f;
        }
        .shell {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }
        .panel {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.82);
            box-shadow: 0 26px 80px rgba(0, 0, 0, 0.22);
        }
        .muted { color: #9fb2c8; }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 30px;
            padding: 0 10px;
            border: 1px solid rgba(148, 163, 184, 0.20);
            border-radius: 999px;
            color: #cbd5e1;
            background: rgba(15, 23, 42, 0.78);
            font-size: 0.82rem;
            font-weight: 800;
        }
        .input {
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 16px;
            background: rgba(2, 6, 23, 0.72);
            color: #f8fafc;
            outline: none;
        }
        .input:focus {
            border-color: rgba(56, 189, 248, 0.70);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.10);
        }
        .primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            border-radius: 14px;
            color: #06111f;
            background: linear-gradient(135deg, #67e8f9, #22c55e);
            font-weight: 900;
            transition: transform 0.18s ease, opacity 0.18s ease;
        }
        .primary:hover { transform: translateY(-1px); }
        .primary:disabled {
            cursor: wait;
            opacity: 0.62;
            transform: none;
        }
        .ghost {
            border: 1px solid rgba(148, 163, 184, 0.20);
            border-radius: 12px;
            color: #dbeafe;
            background: rgba(15, 23, 42, 0.70);
            font-weight: 800;
        }
        .ghost:hover { border-color: rgba(56, 189, 248, 0.46); color: #eff6ff; }
        .answer {
            white-space: pre-wrap;
            line-height: 1.68;
        }
        .answer strong { color: #f8fafc; }
        .thinking {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid rgba(103, 232, 249, 0.18);
            border-radius: 14px;
            color: rgba(226, 232, 240, 0.72);
            background: rgba(2, 6, 23, 0.34);
        }
        .thinking-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: rgba(186, 230, 253, 0.78);
            font-size: 0.82rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0;
        }
        .thinking ul {
            margin: 0;
            padding-left: 18px;
        }
        .thinking li + li { margin-top: 6px; }
        .progress-panel {
            margin-top: 18px;
            padding: 16px;
            border: 1px solid rgba(56, 189, 248, 0.22);
            border-radius: 16px;
            background: rgba(2, 6, 23, 0.42);
        }
        .progress-track {
            overflow: hidden;
            height: 8px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.18);
        }
        .progress-bar {
            width: 18%;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #67e8f9, #22c55e);
            transition: width 0.35s ease;
        }
        .progress-steps {
            display: grid;
            gap: 8px;
            margin-top: 14px;
        }
        .progress-step {
            display: flex;
            align-items: center;
            gap: 9px;
            color: rgba(226, 232, 240, 0.58);
            font-size: 0.9rem;
            font-weight: 700;
        }
        .progress-step::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.36);
        }
        .progress-step.is-active {
            color: #e0f2fe;
        }
        .progress-step.is-active::before {
            background: #38bdf8;
            box-shadow: 0 0 0 5px rgba(56, 189, 248, 0.14);
        }
        .progress-step.is-done {
            color: rgba(187, 247, 208, 0.76);
        }
        .progress-step.is-done::before {
            background: #22c55e;
        }
        .query-panel {
            margin-bottom: 18px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 14px;
            background: rgba(15, 23, 42, 0.42);
        }
        .query-panel summary,
        .diagnostic-panel summary {
            cursor: pointer;
            padding: 12px 14px;
            color: rgba(219, 234, 254, 0.84);
            font-size: 0.86rem;
            font-weight: 900;
            list-style: none;
        }
        .query-panel summary::-webkit-details-marker,
        .diagnostic-panel summary::-webkit-details-marker { display: none; }
        .query-list {
            display: grid;
            gap: 10px;
            max-height: 360px;
            overflow: auto;
            padding: 0 14px 14px;
        }
        .query-item {
            border-radius: 12px;
            background: rgba(2, 6, 23, 0.52);
            padding: 11px 12px;
        }
        .query-source {
            color: #93c5fd;
            font-size: 0.78rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .query-sql {
            margin-top: 6px;
            white-space: pre-wrap;
            word-break: break-word;
            color: rgba(226, 232, 240, 0.76);
            font-size: 0.82rem;
            line-height: 1.5;
        }
        .diagnostic-panel {
            margin-bottom: 18px;
            border: 1px solid rgba(251, 191, 36, 0.22);
            border-radius: 14px;
            background: rgba(2, 6, 23, 0.34);
        }
        .diagnostic-body {
            display: grid;
            gap: 12px;
            padding: 0 14px 14px;
        }
        .diagnostic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
        }
        .diagnostic-item {
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.58);
            padding: 10px 11px;
        }
        .diagnostic-label {
            color: rgba(148, 163, 184, 0.92);
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .diagnostic-value {
            margin-top: 4px;
            color: #f8fafc;
            word-break: break-word;
            font-size: 0.88rem;
        }
        .raw-output {
            max-height: 420px;
            overflow: auto;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.26);
            padding: 12px;
            color: rgba(226, 232, 240, 0.78);
            font-size: 0.82rem;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.16);
        }
    </style>
</head>
<body>
<main class="shell py-6 md:py-8">
    <?php app_menu_render('assistente_dados'); ?>

    <section class="mt-6 grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-5">
        <div class="panel p-5 md:p-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="chip"><span class="dot"></span> Somente leitura</span>
                        <span class="chip">Modelo: <?= data_ai_h($model) ?></span>
                        <span class="chip">Thinking: desligado</span>
                        <span class="chip">Timeout: <?= data_ai_h($timeout) ?>s</span>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-black tracking-tight">Assistente IA de dados</h1>
                    <p class="muted mt-2 max-w-2xl">
                        Pergunte sobre clientes, leads, WhatsApp, agenda, valores e despesas. Este modulo e separado da IA do WhatsApp e nao altera banco de dados.
                    </p>
                </div>
                <a href="configuracoes.php" class="ghost px-4 py-3 text-sm text-center">Configuracoes</a>
            </div>

            <form id="aiForm" class="mt-6">
                <label for="question" class="block mb-2 font-black">Pergunta</label>
                <textarea id="question" class="input w-full p-4 min-h-[150px]" maxlength="1800" placeholder="Ex: quais clientes tem agendamento futuro e quanto tenho previsto para receber?"></textarea>
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    <button id="askButton" class="primary px-5" type="submit">Perguntar</button>
                    <span id="statusText" class="muted text-sm"></span>
                </div>
            </form>

            <div id="progressPanel" class="progress-panel hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                    <strong id="progressTitle">Preparando consulta...</strong>
                    <span id="progressTimer" class="muted text-sm">0s</span>
                </div>
                <div class="progress-track"><div id="progressBar" class="progress-bar"></div></div>
                <div class="progress-steps">
                    <div class="progress-step" data-step="0">Validando pergunta</div>
                    <div class="progress-step" data-step="1">Lendo CRM, WhatsApp, ficha e agenda</div>
                    <div class="progress-step" data-step="2">Montando contexto somente leitura</div>
                    <div class="progress-step" data-step="3">Gerando analise</div>
                    <div class="progress-step" data-step="4">Organizando resposta</div>
                </div>
            </div>

            <div id="answerPanel" class="mt-6 panel p-5 hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                    <h2 class="text-xl font-black">Resposta</h2>
                    <span id="answerMeta" class="muted text-sm"></span>
                </div>
                <div id="thinkingPanel" class="thinking hidden">
                    <div class="thinking-title">Transparencia da analise</div>
                    <ul id="thinkingList"></ul>
                </div>
                <details id="queryPanel" class="query-panel hidden">
                    <summary id="querySummary">Consultas usadas</summary>
                    <div id="queryList" class="query-list"></div>
                </details>
                <details id="diagnosticPanel" class="diagnostic-panel hidden">
                    <summary id="diagnosticSummary">Diagnostico tecnico</summary>
                    <div class="diagnostic-body">
                        <div id="diagnosticGrid" class="diagnostic-grid"></div>
                        <div id="thinkingFullBlock" class="hidden">
                            <div class="diagnostic-label mb-2">Thinking completo retornado pelo modelo</div>
                            <div id="thinkingFull" class="raw-output"></div>
                        </div>
                        <div id="rawOutputBlock" class="hidden">
                            <div class="diagnostic-label mb-2">Saida bruta do modelo</div>
                            <div id="rawOutput" class="raw-output"></div>
                        </div>
                    </div>
                </details>
                <div id="answer" class="answer text-slate-100"></div>
            </div>
        </div>

        <aside class="panel p-5 md:p-6">
            <h2 class="text-xl font-black">Ideias rapidas</h2>
            <div class="mt-4 grid gap-2">
                <button class="ghost example px-3 py-3 text-left" type="button">Qual foi o faturamento previsto dos agendamentos por status?</button>
                <button class="ghost example px-3 py-3 text-left" type="button">Quais clientes tem agenda futura e quais parecem mais importantes?</button>
                <button class="ghost example px-3 py-3 text-left" type="button">Resumo dos leads por origem, etapa e valor potencial.</button>
                <button class="ghost example px-3 py-3 text-left" type="button">Quais conversas recentes do WhatsApp precisam de atencao?</button>
                <button class="ghost example px-3 py-3 text-left" type="button">Compare despesas cadastradas com valores de tatuagens do mes.</button>
            </div>

            <div class="mt-6 border-t border-white/10 pt-5">
                <h3 class="font-black">Fontes usadas</h3>
                <p class="muted text-sm mt-2">
                    CRM de leads, conversas do WhatsApp, ficha de clientes, agenda de tatuagens e despesas do financeiro.
                </p>
            </div>
        </aside>
    </section>
</main>

<script>
const form = document.getElementById('aiForm');
const question = document.getElementById('question');
const button = document.getElementById('askButton');
const statusText = document.getElementById('statusText');
const answerPanel = document.getElementById('answerPanel');
const answer = document.getElementById('answer');
const answerMeta = document.getElementById('answerMeta');
const thinkingPanel = document.getElementById('thinkingPanel');
const thinkingList = document.getElementById('thinkingList');
const progressPanel = document.getElementById('progressPanel');
const progressTitle = document.getElementById('progressTitle');
const progressTimer = document.getElementById('progressTimer');
const progressBar = document.getElementById('progressBar');
const progressSteps = Array.from(document.querySelectorAll('.progress-step'));
const queryPanel = document.getElementById('queryPanel');
const querySummary = document.getElementById('querySummary');
const queryList = document.getElementById('queryList');
const diagnosticPanel = document.getElementById('diagnosticPanel');
const diagnosticSummary = document.getElementById('diagnosticSummary');
const diagnosticGrid = document.getElementById('diagnosticGrid');
const thinkingFullBlock = document.getElementById('thinkingFullBlock');
const thinkingFull = document.getElementById('thinkingFull');
const rawOutputBlock = document.getElementById('rawOutputBlock');
const rawOutput = document.getElementById('rawOutput');
const debugMode = new URLSearchParams(window.location.search).get('debug') === '1';

let progressInterval = null;
let pollInterval = null;
const progressPhases = [
    { after: 0, step: 0, width: 12, title: 'Validando pergunta...' },
    { after: 2, step: 1, width: 32, title: 'Lendo dados do sistema...' },
    { after: 5, step: 2, width: 52, title: 'Montando contexto somente leitura...' },
    { after: 9, step: 3, width: 76, title: 'Gerando analise dos dados...' },
    { after: 35, step: 3, width: 86, title: 'Ainda trabalhando, sem travar a tela...' },
    { after: 75, step: 3, width: 92, title: 'Ainda aguardando a resposta...' }
];

function updateProgressStep(activeStep) {
    progressSteps.forEach((step) => {
        const index = Number(step.dataset.step || 0);
        step.classList.toggle('is-done', index < activeStep);
        step.classList.toggle('is-active', index === activeStep);
    });
}

function setProgress(seconds) {
    const phase = progressPhases.reduce((selected, item) => seconds >= item.after ? item : selected, progressPhases[0]);
    progressTitle.textContent = phase.title;
    progressTimer.textContent = `${seconds}s`;
    progressBar.style.width = `${phase.width}%`;
    updateProgressStep(phase.step);
}

function startProgress() {
    const startedAt = Date.now();
    clearInterval(progressInterval);
    progressPanel.classList.remove('hidden');
    setProgress(0);
    progressInterval = setInterval(() => {
        setProgress(Math.floor((Date.now() - startedAt) / 1000));
    }, 1000);
}

function finishProgress(success) {
    clearInterval(progressInterval);
    progressInterval = null;
    clearInterval(pollInterval);
    pollInterval = null;
    progressBar.style.width = '100%';
    progressTitle.textContent = success ? 'Resposta pronta.' : 'Consulta encerrada.';
    updateProgressStep(success ? 4 : 3);
    window.setTimeout(() => progressPanel.classList.add('hidden'), 900);
}

function applyJobProgress(job) {
    progressPanel.classList.remove('hidden');
    const progress = Math.max(1, Math.min(100, Number(job.progress || 1)));
    progressBar.style.width = `${progress}%`;
    progressTitle.textContent = job.stage_label || job.stage || 'Processando em segundo plano...';
    if (progress < 25) {
        updateProgressStep(1);
    } else if (progress < 50) {
        updateProgressStep(2);
    } else if (progress < 90) {
        updateProgressStep(3);
    } else {
        updateProgressStep(4);
    }
}

function stringifyValue(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }
    return String(value);
}

function renderDiagnostic(data, failed = false) {
    if (!debugMode) {
        diagnosticPanel.classList.add('hidden');
        diagnosticPanel.open = false;
        thinkingFullBlock.classList.add('hidden');
        rawOutputBlock.classList.add('hidden');
        return;
    }

    const diagnostic = data && typeof data.diagnostic === 'object' && data.diagnostic !== null ? data.diagnostic : {};
    const details = data && typeof data.details === 'object' && data.details !== null ? data.details : {};
    const items = [];

    if (failed) {
        items.push(['Tipo do erro', data.error_type || 'erro_desconhecido']);
        items.push(['Etapa', data.stage || diagnostic.stage || '-']);
        items.push(['Mensagem', data.error || '-']);
    }

    Object.entries(diagnostic).forEach(([key, value]) => items.push([key, value]));
    Object.entries(details).forEach(([key, value]) => items.push([key, value]));

    diagnosticGrid.innerHTML = '';
    items.forEach(([label, value]) => {
        const item = document.createElement('div');
        item.className = 'diagnostic-item';
        const labelEl = document.createElement('div');
        labelEl.className = 'diagnostic-label';
        labelEl.textContent = String(label).replace(/_/g, ' ');
        const valueEl = document.createElement('div');
        valueEl.className = 'diagnostic-value';
        valueEl.textContent = stringifyValue(value);
        item.appendChild(labelEl);
        item.appendChild(valueEl);
        diagnosticGrid.appendChild(item);
    });

    thinkingFull.textContent = data.thinking || details.thinking_preview || '';
    thinkingFullBlock.classList.toggle('hidden', !(data.thinking || details.thinking_preview));
    rawOutput.textContent = data.raw_model_output || details.raw_preview || '';
    rawOutputBlock.classList.toggle('hidden', !(data.raw_model_output || details.raw_preview));
    diagnosticSummary.textContent = failed ? 'Diagnostico tecnico do erro' : 'Diagnostico tecnico da resposta';
    diagnosticPanel.classList.toggle('hidden', items.length === 0 && !data.thinking && !data.raw_model_output && !details.raw_preview);
    diagnosticPanel.open = failed;
}

function renderQueries(queries) {
    if (!debugMode) {
        queryPanel.classList.add('hidden');
        queryPanel.open = false;
        return;
    }

    queryList.innerHTML = '';
    queries.forEach((query, index) => {
        const item = document.createElement('div');
        item.className = 'query-item';
        const source = document.createElement('div');
        source.className = 'query-source';
        source.textContent = `${index + 1}. ${query.fonte || 'Fonte'}`;
        const sql = document.createElement('div');
        sql.className = 'query-sql';
        sql.textContent = query.sql || '';
        item.appendChild(source);
        item.appendChild(sql);
        queryList.appendChild(item);
    });
    querySummary.textContent = `Consultas completas usadas (${queries.length})`;
    queryPanel.classList.toggle('hidden', queries.length === 0);
    queryPanel.open = queries.length > 0;
}

function renderSuccess(data) {
    answer.textContent = data.answer || '';
    const notes = Array.isArray(data.transparency) ? data.transparency.filter(Boolean) : [];
    thinkingList.innerHTML = '';
    notes.forEach((note) => {
        const item = document.createElement('li');
        item.textContent = note;
        thinkingList.appendChild(item);
    });
    thinkingPanel.classList.toggle('hidden', !debugMode || notes.length === 0);
    renderQueries(Array.isArray(data.queries) ? data.queries : []);
    renderDiagnostic(data, false);
    answerMeta.textContent = `${data.model || 'IA'} - ${data.generated_at || ''}`;
    answerPanel.classList.remove('hidden');
    statusText.textContent = data.fallback
        ? 'Resposta gerada por resumo local porque o modelo demorou demais.'
        : 'Resposta gerada com dados somente-leitura.';
}

function waitForJob(jobId) {
    const startedAt = Date.now();

    return new Promise((resolve, reject) => {
        const poll = async () => {
            try {
                const response = await fetch(`assistente_dados_api.php?job=${encodeURIComponent(jobId)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const raw = await response.text();
                let job = null;
                try {
                    job = JSON.parse(raw);
                } catch (parseError) {
                    reject({
                        ok: false,
                        error: `Resposta invalida consultando job. HTTP ${response.status}.`,
                        error_type: 'job_json_invalido',
                        stage: 'job_poll',
                        details: {
                            parse_error: parseError.message,
                            raw_preview: raw.slice(0, 2500)
                        }
                    });
                    return;
                }

                if (!job.ok && !job.status) {
                    reject(job);
                    return;
                }

                applyJobProgress(job);
                const seconds = Math.floor((Date.now() - startedAt) / 1000);
                progressTimer.textContent = `${seconds}s`;
                statusText.textContent = job.stage_label || 'Processando em segundo plano...';

                if (job.status === 'done') {
                    resolve(job.result || job);
                    return;
                }
                if (job.status === 'error') {
                    reject(job.result || job);
                    return;
                }
            } catch (error) {
                reject({
                    ok: false,
                    error: error && error.message ? error.message : 'Falha ao consultar job.',
                    error_type: 'job_poll_exception',
                    stage: 'job_poll',
                    details: debugDump(error)
                });
            }
        };

        poll();
        pollInterval = setInterval(poll, 2500);
    });
}

function debugDump(value) {
    try {
        if (value instanceof Error) {
            return {
                name: value.name,
                message: value.message,
                stack: value.stack || ''
            };
        }
        return JSON.parse(JSON.stringify(value));
    } catch (dumpError) {
        return String(value);
    }
}

function showFailure(payload) {
    const safePayload = payload && typeof payload === 'object' ? payload : {
        ok: false,
        error: 'Falha desconhecida no navegador.',
        error_type: 'frontend_unknown',
        stage: 'browser',
        details: { payload: debugDump(payload) }
    };

    if (!safePayload.details || typeof safePayload.details !== 'object') {
        safePayload.details = { details: stringifyValue(safePayload.details) };
    }
    if (!safePayload.error) {
        safePayload.error = 'Falha sem mensagem retornada pela API.';
        safePayload.details.payload_completo = debugDump(payload);
    }
    if (!safePayload.error_type) {
        safePayload.error_type = 'frontend_payload_sem_error';
    }
    if (!safePayload.stage) {
        safePayload.stage = 'browser_payload';
    }
    if (debugMode && !safePayload.details.payload_completo) {
        safePayload.details.payload_completo = debugDump(payload);
    }

    const stage = safePayload.stage ? ` na etapa ${safePayload.stage}` : '';
    const type = safePayload.error_type ? ` (${safePayload.error_type})` : '';
    if (safePayload.error_type === 'ollama_timeout') {
        answer.textContent = 'O modelo local demorou demais para responder. Eu reduzi o contexto enviado para as proximas consultas; tente perguntar novamente. Se continuar, o ideal e trocar para um modelo menor nas configuracoes do assistente.';
    } else if (safePayload.error_type === 'ollama_conexao') {
        answer.textContent = 'Nao consegui conectar ao Ollama agora. Verifique se o Ollama esta aberto no servidor e tente novamente.';
    } else {
        answer.textContent = debugMode
            ? `${safePayload.error || 'Falha sem mensagem.'}${type}${stage}`
            : (safePayload.error || 'Nao foi possivel consultar a IA agora.');
    }
    answerMeta.textContent = 'Falha';
    renderQueries(Array.isArray(safePayload.queries) ? safePayload.queries : []);

    try {
        renderDiagnostic(safePayload, true);
    } catch (diagnosticError) {
        diagnosticGrid.innerHTML = '';
        rawOutput.textContent = JSON.stringify({
            erro_renderizando_diagnostico: debugDump(diagnosticError),
            payload_original: debugDump(safePayload)
        }, null, 2);
        rawOutputBlock.classList.remove('hidden');
        thinkingFullBlock.classList.add('hidden');
        diagnosticSummary.textContent = 'Diagnostico tecnico do erro';
        diagnosticPanel.classList.remove('hidden');
        diagnosticPanel.open = true;
    }

    answerPanel.classList.remove('hidden');
    statusText.textContent = debugMode ? 'Nao foi possivel consultar a IA agora.' : 'Falha tratada sem detalhes tecnicos na tela.';
}

document.querySelectorAll('.example').forEach((item) => {
    item.addEventListener('click', () => {
        question.value = item.textContent.trim();
        question.focus();
    });
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const pergunta = question.value.trim();
    if (!pergunta) {
        statusText.textContent = 'Digite uma pergunta primeiro.';
        question.focus();
        return;
    }

    button.disabled = true;
    statusText.textContent = 'Lendo dados e consultando a IA... modelos maiores podem demorar um pouco.';
    startProgress();
    answerPanel.classList.add('hidden');
    thinkingPanel.classList.add('hidden');
    thinkingList.innerHTML = '';
    queryPanel.classList.add('hidden');
    queryList.innerHTML = '';
    diagnosticPanel.classList.add('hidden');
    diagnosticGrid.innerHTML = '';
    thinkingFull.textContent = '';
    rawOutput.textContent = '';
    answer.textContent = '';
    answerMeta.textContent = '';

    try {
        const response = await fetch('assistente_dados_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ pergunta })
        });
        const rawResponse = await response.text();
        let data = null;
        try {
            data = JSON.parse(rawResponse);
        } catch (parseError) {
            throw {
                payload: {
                    ok: false,
                    error: `Resposta invalida do servidor. HTTP ${response.status}.`,
                    error_type: 'api_json_invalido',
                    stage: 'api_response',
                    details: {
                        http_status: response.status,
                        parse_error: parseError.message,
                        raw_preview: rawResponse.slice(0, 2500)
                    }
                }
            };
        }
        if (!data.ok) {
            throw { payload: Object.assign({
                error: data.error || 'A API retornou ok=false sem mensagem.',
                error_type: data.error_type || 'api_ok_false_sem_error',
                stage: data.stage || 'api_response',
                details: Object.assign({ resposta_completa: data }, data.details || {})
            }, data) };
        }

        if (data.job_id && data.status) {
            clearInterval(progressInterval);
            progressInterval = null;
            applyJobProgress(data);
            statusText.textContent = data.stage_label || 'Processando em segundo plano...';
            const result = await waitForJob(data.job_id);
            renderSuccess(result);
        } else {
            renderSuccess(data);
        }
        finishProgress(true);
    } catch (error) {
        const payload = error && error.payload ? error.payload : (
            error && typeof error === 'object' && (error.error || error.error_type || error.ok === false)
                ? error
                : {
            ok: false,
            error: error && error.message ? error.message : 'Erro inesperado no navegador.',
            error_type: 'frontend_exception',
            stage: 'browser',
            details: debugDump(error)
        });
        showFailure(payload);
        finishProgress(false);
    } finally {
        button.disabled = false;
    }
});
</script>
</body>
</html>
