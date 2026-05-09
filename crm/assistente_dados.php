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

            <div id="answerPanel" class="mt-6 panel p-5 hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                    <h2 class="text-xl font-black">Resposta</h2>
                    <span id="answerMeta" class="muted text-sm"></span>
                </div>
                <div id="thinkingPanel" class="thinking hidden">
                    <div class="thinking-title">Transparencia da analise</div>
                    <ul id="thinkingList"></ul>
                </div>
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
    answerPanel.classList.add('hidden');
    thinkingPanel.classList.add('hidden');
    thinkingList.innerHTML = '';
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
        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.error || 'Nao foi possivel gerar a resposta.');
        }

        answer.textContent = data.answer || '';
        const notes = Array.isArray(data.transparency) ? data.transparency.filter(Boolean) : [];
        thinkingList.innerHTML = '';
        notes.forEach((note) => {
            const item = document.createElement('li');
            item.textContent = note;
            thinkingList.appendChild(item);
        });
        thinkingPanel.classList.toggle('hidden', notes.length === 0);
        answerMeta.textContent = `${data.model || 'IA'} - ${data.generated_at || ''}`;
        answerPanel.classList.remove('hidden');
        statusText.textContent = 'Resposta gerada com dados somente-leitura.';
    } catch (error) {
        answer.textContent = error.message || 'Erro inesperado.';
        answerMeta.textContent = 'Falha';
        answerPanel.classList.remove('hidden');
        statusText.textContent = 'Nao foi possivel consultar a IA agora.';
    } finally {
        button.disabled = false;
    }
});
</script>
</body>
</html>
