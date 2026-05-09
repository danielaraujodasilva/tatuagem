<?php require 'config.php'; ?>
<?php
require_once __DIR__ . '/../auth/auth.php';
require_admin();
require_once __DIR__ . '/../includes/app_menu.php';
require_once __DIR__ . '/../includes/system_settings.php';

$systemSettings = system_settings_load();
$valorPomada = system_pomada_unit_price();
$embedded = !empty($_GET['embed']) || !empty($_POST['embed']);
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/crm-theme.css?v=20260505-embedded-redesign">
    <style>
        :root { color-scheme: dark; }

        body.settings-body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 82% 0%, rgba(225, 29, 40, 0.16), transparent 32rem),
                radial-gradient(circle at 8% 18%, rgba(239, 68, 68, 0.08), transparent 24rem),
                #050505;
            color: #f6f7fb;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .settings-shell {
            max-width: 1320px;
        }

        .settings-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(239, 68, 68, 0.24);
            border-radius: 8px;
            background:
                linear-gradient(135deg, rgba(225, 29, 40, 0.2), rgba(14, 15, 18, 0.96)),
                radial-gradient(circle at 90% 15%, rgba(239, 68, 68, 0.22), transparent 24rem);
            padding: 22px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.36);
        }

        .settings-hero::after {
            content: "";
            position: absolute;
            right: 24px;
            bottom: 0;
            width: 48%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(239, 68, 68, 0.8));
        }

        .settings-card {
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 8px;
            background: linear-gradient(180deg, rgba(18, 19, 23, 0.97), rgba(10, 10, 12, 0.97));
            box-shadow: 0 18px 44px rgba(0, 0, 0, 0.28);
        }

        .settings-kicker {
            color: #ef4444;
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .settings-muted {
            color: #a3a6ad;
        }

        .settings-input {
            min-height: 48px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(5, 5, 5, 0.68);
            color: #f6f7fb;
            outline: none;
        }

        .settings-input:focus {
            border-color: rgba(239, 68, 68, 0.74);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.16);
        }

        .settings-button {
            min-height: 48px;
            border-radius: 8px;
            background: linear-gradient(180deg, #ef4444, #b91420);
            color: #ffffff;
            font-weight: 900;
            box-shadow: 0 14px 30px rgba(225, 29, 40, 0.24);
            transition: transform 0.18s ease, opacity 0.18s ease;
        }

        .settings-button:hover {
            transform: translateY(-1px);
            opacity: 0.96;
        }

        .settings-row {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.045);
        }

        .settings-row:hover {
            background: rgba(239, 68, 68, 0.06);
            border-color: rgba(239, 68, 68, 0.22);
        }

        .settings-action {
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.06);
            color: #f6f7fb;
            font-weight: 800;
        }

        .settings-action-edit {
            border: 1px solid rgba(245, 158, 11, 0.28);
            color: #fde68a;
        }

        .settings-action-delete {
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fecaca;
        }

        .settings-dot {
            width: 14px;
            height: 14px;
            border-radius: 5px;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.04);
        }

        .app-menu-panel {
            right: 0;
        }

        .settings-embedded {
            padding: 0;
        }

        .settings-embedded .settings-hero {
            display: none;
        }
    </style>
</head>

<body class="settings-body">

<main class="settings-shell mx-auto p-4 md:p-6 <?= $embedded ? 'settings-embedded' : '' ?>">

    <header class="settings-hero mb-6">
        <div class="flex flex-col gap-3">
            <span class="settings-kicker">Configuracoes do CRM</span>
            <h1 class="text-3xl md:text-4xl font-black">Pipeline e gatilhos</h1>
            <p class="settings-muted max-w-3xl">Organize etapas comerciais, ajuste a ordem do funil e defina mensagens que ajudam o atendimento a reagir mais rapido.</p>
        </div>

        <?php app_menu_render('config'); ?>
    </header>

    <section class="settings-card p-5 md:p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-5">
            <div>
                <span class="settings-kicker">Nova etapa</span>
                <h2 class="text-xl font-black mt-1">Adicionar ao funil</h2>
            </div>
            <p class="settings-muted text-sm max-w-xl">Use nomes curtos e cores bem distintas para que o time entenda o status do cliente batendo o olho.</p>
        </div>

        <form method="POST" action="pipeline_salvar.php"
              class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

            <input type="text" name="nome" placeholder="Nome da etapa" required
                   class="settings-input px-4 py-3 col-span-2">

            <input type="number" name="ordem" placeholder="Ordem"
                   class="settings-input px-4 py-3">

            <input type="color" name="cor"
                   class="settings-input h-[48px] w-full p-1">

            <button class="settings-button px-4 py-3 col-span-1 md:col-span-4">
                + Adicionar Etapa
            </button>
        </form>
    </section>

    <section class="settings-card p-5 md:p-6">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-5">
            <div>
                <span class="settings-kicker">Ordem do pipeline</span>
                <h2 class="text-xl font-black mt-1">Etapas do funil</h2>
            </div>
            <p class="settings-muted text-sm">Arraste para reorganizar. A ordem salva automaticamente.</p>
        </div>

        <div id="pipelineList" class="space-y-3">
        <?php
        $res = $conn->query("SELECT * FROM pipelines ORDER BY ordem");

        while($p = $res->fetch(PDO::FETCH_ASSOC)):
        ?>

        <div data-id="<?= $p['id'] ?>"
             class="settings-row p-4 flex flex-col md:flex-row md:justify-between md:items-center gap-3 cursor-move transition">

            <div class="flex items-center gap-3">
                <i class="fas fa-grip-vertical text-gray-500"></i>
                <span class="settings-dot" style="background: <?= $p['cor'] ?>"></span>
                <span class="font-medium"><?= $p['nome'] ?></span>
            </div>

            <div class="flex gap-2">
                <a href="pipeline_editar.php?id=<?= $p['id'] ?>"
                   class="settings-action settings-action-edit px-3 py-2 text-sm">
                    Editar
                </a>
                <a href="pipeline_deletar.php?id=<?= $p['id'] ?>"
                   class="settings-action settings-action-delete px-3 py-2 text-sm">
                    Excluir
                </a>
            </div>
        </div>

        <?php endwhile; ?>
        </div>
    </section>

    <section class="settings-card p-5 md:p-6 mt-6">
        <form method="POST" action="salvar_config.php">
            <?php if ($embedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex-1">
                    <span class="settings-kicker">Automacao</span>
                    <label class="block mt-1 mb-2 text-xl font-black">Mensagem gatilho</label>
                    <input type="text" name="mensagem_trigger"
                           class="settings-input px-4 py-3 w-full"
                           value="<?= htmlspecialchars((string)($systemSettings['mensagem_trigger'] ?? 'oi'), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Ex: oi">
                </div>

                <div class="flex-1">
                    <span class="settings-kicker">Financeiro</span>
                    <label class="block mt-1 mb-2 text-xl font-black">Valor por pomada anestesica</label>
                    <input type="number" step="0.01" min="0" name="valor_pomada_anestesica"
                           class="settings-input px-4 py-3 w-full"
                           value="<?= htmlspecialchars(number_format($valorPomada, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="md:col-span-2 border border-white/10 rounded-lg p-4 bg-black/20">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-4">
                        <div>
                            <span class="settings-kicker">Ollama local</span>
                            <h2 class="text-xl font-black mt-1">IA de atendimento</h2>
                            <p class="settings-muted text-sm mt-1">Quando uma conversa estiver em modo IA/Bot, novas mensagens do cliente podem receber uma resposta automatica usando o modelo local do Ollama.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a class="settings-action px-3 py-2 text-sm" href="ia_diagnostico.php" target="_blank">Ver diagnostico</a>
                                <a class="settings-action px-3 py-2 text-sm" href="ia_diagnostico.php?testar=1" target="_blank">Testar IA local</a>
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 font-bold">
                            <input type="checkbox" name="openai_enabled" value="1" <?= !empty($systemSettings['openai_enabled']) ? 'checked' : '' ?>>
                            Ativar IA
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block mb-2 font-bold">URL do Ollama</label>
                            <input type="text" name="ollama_url"
                                   class="settings-input px-4 py-3 w-full"
                                   value="<?= htmlspecialchars((string)($systemSettings['ollama_url'] ?? 'http://localhost:11434'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div>
                            <label class="block mb-2 font-bold">Modelo</label>
                            <input type="text" name="ollama_model"
                                   class="settings-input px-4 py-3 w-full"
                                   value="<?= htmlspecialchars((string)($systemSettings['ollama_model'] ?? 'qwen3:14b'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div>
                            <label class="block mb-2 font-bold">Mensagens de contexto</label>
                            <input type="number" min="4" max="60" name="openai_max_history"
                                   class="settings-input px-4 py-3 w-full"
                                   value="<?= htmlspecialchars((string)($systemSettings['openai_max_history'] ?? 20), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div>
                            <label class="block mb-2 font-bold">Timeout da IA (s)</label>
                            <input type="number" min="20" max="180" name="ai_timeout_seconds"
                                   class="settings-input px-4 py-3 w-full"
                                   value="<?= htmlspecialchars((string)($systemSettings['ai_timeout_seconds'] ?? 120), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div>
                            <label class="block mb-2 font-bold">Tamanho da resposta</label>
                            <input type="number" min="40" max="450" name="ai_num_predict"
                                   class="settings-input px-4 py-3 w-full"
                                   value="<?= htmlspecialchars((string)($systemSettings['ai_num_predict'] ?? 220), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="md:col-span-3">
                            <label class="block mb-2 font-bold">Instrucoes da IA</label>
                            <textarea name="openai_business_prompt" rows="6"
                                      class="settings-input px-4 py-3 w-full"><?= htmlspecialchars((string)($systemSettings['openai_business_prompt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="md:col-span-3 border border-white/10 rounded-lg p-4 bg-black/20">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                                <div>
                                    <span class="settings-kicker">Assistente de dados</span>
                                    <p class="settings-muted text-sm mt-1">Modulo separado para perguntas internas. Ele apenas le consultas controladas do banco e continua funcionando mesmo com respostas automaticas do WhatsApp desativadas.</p>
                                </div>
                                <a class="settings-action px-3 py-2 text-sm" href="assistente_dados.php">Abrir assistente</a>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block mb-2 font-bold">Modelo do assistente</label>
                                    <input type="text" name="data_ai_model"
                                           class="settings-input px-4 py-3 w-full"
                                           value="<?= htmlspecialchars((string)($systemSettings['data_ai_model'] ?? 'qwen3:14b'), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div>
                                    <label class="block mb-2 font-bold">Timeout do assistente (s)</label>
                                    <input type="number" min="30" max="420" name="data_ai_timeout_seconds"
                                           class="settings-input px-4 py-3 w-full"
                                           value="<?= htmlspecialchars((string)($systemSettings['data_ai_timeout_seconds'] ?? 240), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div>
                                    <label class="block mb-2 font-bold">Tamanho da analise</label>
                                    <input type="number" min="120" max="1600" name="data_ai_num_predict"
                                           class="settings-input px-4 py-3 w-full"
                                           value="<?= htmlspecialchars((string)($systemSettings['data_ai_num_predict'] ?? 900), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="settings-button px-5 py-3 md:col-span-2">
                    Salvar
                </button>
            </div>
        </form>
    </section>

</main>

<script>
Sortable.create(document.getElementById('pipelineList'), {
    animation: 150,
    ghostClass: 'opacity-50',

    onEnd: function () {

        let ordem = [];
        document.querySelectorAll('#pipelineList > div').forEach((el, index) => {
            ordem.push({
                id: el.dataset.id,
                ordem: index + 1
            });
        });

        fetch('pipeline_ordem.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(ordem)
        });
    }
});
</script>

</body>
</html>
