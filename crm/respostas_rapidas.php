<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require_once __DIR__ . '/data_store.php';

function respostas_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function respostas_redirect(): void
{
    header('Location: respostas_rapidas.php');
    exit;
}

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $respostas = crmCarregarRespostasRapidas();

    if ($action === 'salvar') {
        $id = trim((string)($_POST['id'] ?? ''));
        $payload = [
            'id' => $id !== '' ? $id : uniqid('qr_', true),
            'titulo' => trim((string)($_POST['titulo'] ?? '')),
            'categoria' => trim((string)($_POST['categoria'] ?? 'Geral')),
            'atalho' => trim((string)($_POST['atalho'] ?? '')),
            'texto' => trim((string)($_POST['texto'] ?? '')),
            'ativo' => !empty($_POST['ativo']),
        ];

        $achou = false;
        foreach ($respostas as &$resposta) {
            if ((string)($resposta['id'] ?? '') === $payload['id']) {
                $resposta = $payload;
                $achou = true;
                break;
            }
        }
        unset($resposta);

        if (!$achou) {
            $respostas[] = $payload;
        }

        crmSalvarRespostasRapidas($respostas);
        respostas_redirect();
    }

    if ($action === 'excluir') {
        $id = trim((string)($_POST['id'] ?? ''));
        $respostas = array_values(array_filter($respostas, static function (array $resposta) use ($id): bool {
            return (string)($resposta['id'] ?? '') !== $id;
        }));
        crmSalvarRespostasRapidas($respostas);
        respostas_redirect();
    }
}

$respostas = crmCarregarRespostasRapidas();
usort($respostas, static function (array $a, array $b): int {
    return strcmp((string)($a['categoria'] ?? ''), (string)($b['categoria'] ?? ''))
        ?: strcmp((string)($a['titulo'] ?? ''), (string)($b['titulo'] ?? ''));
});

$categorias = [];
foreach ($respostas as $resposta) {
    $cat = trim((string)($resposta['categoria'] ?? 'Geral')) ?: 'Geral';
    $categorias[$cat] = ($categorias[$cat] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respostas Rápidas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/crm-theme.css">
    <style>
        .scripts-layout {
            display: grid;
            grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        .script-card {
            padding: 16px;
        }

        .script-text {
            white-space: pre-wrap;
        }

        @media (max-width: 1100px) {
            .scripts-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="crm-shell">
<div class="crm-page">
    <header class="crm-header">
        <div>
            <div class="crm-eyebrow"><i class="fa-solid fa-bolt"></i> Scripts de atendimento</div>
            <h1 class="crm-title">Respostas <strong>Rápidas</strong></h1>
            <p class="crm-subtitle">Mensagens prontas para orçamento, referência, sinal, cuidados e follow-up.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="crm-button" href="index.php"><i class="fa-solid fa-comments"></i> Atendimento</a>
            <a class="crm-button" href="dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        </div>
    </header>

    <div class="crm-workspace">
        <aside class="crm-sidebar">
            <div class="crm-brand">
                <span class="crm-brand-name">Daniel <span>Tatuador</span></span>
                <small>Scripts para ganhar tempo sem perder tom</small>
            </div>
            <nav class="crm-nav" aria-label="Menu CRM">
                <a href="dashboard.php"><i class="fa-solid fa-chart-simple"></i> Dashboard</a>
                <a href="index.php"><i class="fa-solid fa-comments"></i> Atendimento</a>
                <a class="is-active" href="respostas_rapidas.php"><i class="fa-solid fa-bolt"></i> Respostas Rapidas</a>
                <a href="../ficha/agenda/"><i class="fa-regular fa-calendar"></i> Agenda</a>
                <a href="../ficha/index.php"><i class="fa-regular fa-clipboard"></i> Ficha / Anamnese</a>
                <a href="relatorios.php"><i class="fa-solid fa-chart-line"></i> Relatorios</a>
                <a href="configuracoes.php"><i class="fa-solid fa-gear"></i> Configuracoes</a>
            </nav>
        </aside>

        <main class="crm-main">
            <section class="crm-grid-4 mb-4">
                <div class="crm-card p-4">
                    <div class="crm-muted text-sm">Scripts</div>
                    <div class="text-3xl font-black mt-1"><?= count($respostas) ?></div>
                </div>
                <div class="crm-card p-4">
                    <div class="crm-muted text-sm">Ativos</div>
                    <div class="text-3xl font-black mt-1 text-green-400"><?= count(array_filter($respostas, static fn(array $r): bool => !empty($r['ativo']))) ?></div>
                </div>
                <div class="crm-card p-4">
                    <div class="crm-muted text-sm">Categorias</div>
                    <div class="text-3xl font-black mt-1 text-red-400"><?= count($categorias) ?></div>
                </div>
                <div class="crm-card p-4">
                    <div class="crm-muted text-sm">Uso</div>
                    <div class="text-xl font-black mt-2">Central de Atendimento</div>
                </div>
            </section>

            <section class="scripts-layout">
                <form method="post" class="crm-panel p-5" id="replyForm">
                    <input type="hidden" name="action" value="salvar">
                    <input type="hidden" name="id" id="replyId">

                    <div class="flex items-center justify-between gap-3 mb-5">
                        <h2 class="crm-panel-title"><i class="fa-solid fa-pen-to-square"></i> Script</h2>
                        <button type="button" class="crm-button" onclick="resetForm()"><i class="fa-solid fa-plus"></i> Novo</button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="crm-muted text-sm block mb-2" for="titulo">Titulo</label>
                            <input class="crm-input" id="titulo" name="titulo" required placeholder="Ex: Pedido de referencia">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="crm-muted text-sm block mb-2" for="categoria">Categoria</label>
                                <input class="crm-input" id="categoria" name="categoria" list="categorias" placeholder="Orcamento">
                                <datalist id="categorias">
                                    <?php foreach (array_keys($categorias) as $categoria): ?>
                                        <option value="<?= respostas_h($categoria) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div>
                                <label class="crm-muted text-sm block mb-2" for="atalho">Atalho</label>
                                <input class="crm-input" id="atalho" name="atalho" placeholder="/referencia">
                            </div>
                        </div>

                        <div>
                            <label class="crm-muted text-sm block mb-2" for="texto">Mensagem</label>
                            <textarea class="crm-textarea" id="texto" name="texto" required rows="8" placeholder="Digite a resposta pronta..."></textarea>
                        </div>

                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" id="ativo" name="ativo" value="1" checked class="accent-red-600">
                            <span>Ativa na Central de Atendimento</span>
                        </label>

                        <button class="crm-button crm-button-primary w-full" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar resposta
                        </button>
                    </div>
                </form>

                <div class="crm-panel">
                    <div class="crm-panel-header">
                        <h2 class="crm-panel-title"><i class="fa-solid fa-list-check"></i> Biblioteca</h2>
                        <input id="searchScripts" class="crm-input max-w-sm" placeholder="Buscar scripts">
                    </div>
                    <div id="scriptsList" class="p-4 grid grid-cols-1 xl:grid-cols-2 gap-3">
                        <?php foreach ($respostas as $resposta): ?>
                            <article class="crm-card script-card script-item"
                                     data-search="<?= respostas_h(strtolower(($resposta['titulo'] ?? '') . ' ' . ($resposta['categoria'] ?? '') . ' ' . ($resposta['atalho'] ?? '') . ' ' . ($resposta['texto'] ?? ''))) ?>">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-black text-lg truncate"><?= respostas_h($resposta['titulo'] ?? '') ?></h3>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <span class="crm-status"><?= respostas_h($resposta['categoria'] ?? 'Geral') ?></span>
                                            <?php if (!empty($resposta['atalho'])): ?>
                                                <span class="crm-status crm-status-hot"><?= respostas_h($resposta['atalho']) ?></span>
                                            <?php endif; ?>
                                            <span class="crm-status <?= !empty($resposta['ativo']) ? 'crm-status-green' : 'crm-status-amber' ?>">
                                                <?= !empty($resposta['ativo']) ? 'Ativa' : 'Inativa' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button class="crm-button" type="button"
                                                onclick='editReply(<?= json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'
                                                title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <form method="post" onsubmit="return confirm('Excluir esta resposta rápida?')">
                                            <input type="hidden" name="action" value="excluir">
                                            <input type="hidden" name="id" value="<?= respostas_h($resposta['id'] ?? '') ?>">
                                            <button class="crm-button" type="submit" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <p class="script-text crm-muted mt-4 text-sm"><?= respostas_h($resposta['texto'] ?? '') ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
function editReply(reply) {
    document.getElementById('replyId').value = reply.id || '';
    document.getElementById('titulo').value = reply.titulo || '';
    document.getElementById('categoria').value = reply.categoria || '';
    document.getElementById('atalho').value = reply.atalho || '';
    document.getElementById('texto').value = reply.texto || '';
    document.getElementById('ativo').checked = Boolean(reply.ativo);
    document.getElementById('titulo').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('replyForm').reset();
    document.getElementById('replyId').value = '';
    document.getElementById('ativo').checked = true;
    document.getElementById('titulo').focus();
}

document.getElementById('searchScripts').addEventListener('input', event => {
    const term = event.target.value.toLowerCase().trim();
    document.querySelectorAll('.script-item').forEach(item => {
        item.style.display = !term || item.dataset.search.includes(term) ? '' : 'none';
    });
});
</script>
</body>
</html>
