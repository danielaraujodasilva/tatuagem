<?php
$root = dirname(__DIR__);
require_once $root . '/plan/includes/bootstrap.php';

$user = current_user();
$csrf = csrf_token();

$projectNotes = [
    'auth' => ['title' => 'Auth', 'type' => 'Sistema', 'status' => 'Ativo', 'description' => 'Login, cadastro, recuperacao de senha e gestao de usuarios usados por outros modulos.'],
    'calculadora' => ['title' => 'Calculadora de Taxas', 'type' => 'Ferramenta', 'status' => 'Utilitario', 'description' => 'Calculadora HTML simples para taxas, custos ou simulacoes rapidas.'],
    'cnjp' => ['title' => 'CNJP', 'type' => 'Projeto externo', 'status' => 'Ativo', 'description' => 'Landing page e area privada/roadmap para conciliacao, mediacao e arbitragem extrajudicial.'],
    'crm' => ['title' => 'CRM Tattoo', 'type' => 'Operacao', 'status' => 'Critico', 'description' => 'Central de atendimento, leads, agenda, WhatsApp, automacoes, relatorios e assistente IA.'],
    'ficha' => ['title' => 'Ficha de Cliente', 'type' => 'Operacao', 'status' => 'Ativo', 'description' => 'Cadastro de clientes, anamnese, tatuagens, agenda visual, conta do cliente e mapa.'],
    'financeiro' => ['title' => 'Financeiro', 'type' => 'Sistema', 'status' => 'Em construcao', 'description' => 'Sistema financeiro multiusuario com instancias, membros e base para sincronizacao/deploy.'],
    'flash' => ['title' => 'Flash Tattoo', 'type' => 'Campanha', 'status' => 'Publicado', 'description' => 'Pagina promocional de flash tattoo com chamada para agendamento e integracao de pagamento.'],
    'fotos' => ['title' => 'Fotos', 'type' => 'Assets', 'status' => 'Suporte', 'description' => 'Pasta de imagens antigas ou auxiliares usadas por paginas do site.'],
    'fran' => ['title' => 'Fran / CNJP', 'type' => 'Projeto externo', 'status' => 'Revisar', 'description' => 'Pagina sobre nomeacao de arbitros e conciliadores. Parece relacionada ao CNJP.'],
    'galeria' => ['title' => 'Galeria', 'type' => 'Assets', 'status' => 'Ativo', 'description' => 'Fotos de tatuagens e imagens sincronizadas do Instagram usadas na galeria do site principal.'],
    'img' => ['title' => 'Imagens do Site', 'type' => 'Assets', 'status' => 'Suporte', 'description' => 'Imagens, fundos, favicon e midias da pagina principal Daniel Tatuador.'],
    'imoveis' => ['title' => 'Radar Imoveis', 'type' => 'Sistema', 'status' => 'Em construcao', 'description' => 'Rastreador PHP/MySQL/Node para oportunidades imobiliarias em OLX, Mercado Livre, Zap e Viva Real.'],
    'includes' => ['title' => 'Includes', 'type' => 'Base tecnica', 'status' => 'Suporte', 'description' => 'Componentes PHP compartilhados, como menu interno de apps autenticados.'],
    'ink' => ['title' => 'Misturador de Tintas', 'type' => 'Ferramenta', 'status' => 'Experimento', 'description' => 'Ferramenta de calibracao/mistura de tintas.'],
    'instagram' => ['title' => 'Instagram Sync', 'type' => 'Integracao', 'status' => 'Ativo', 'description' => 'Fluxos de callback, feed, sincronizacao e painel para integrar conteudo do Instagram.'],
    'joguiunho' => ['title' => 'Joguiunho', 'type' => 'Experimento', 'status' => 'Teste', 'description' => 'Jogo/teste simples dentro do site Daniel Tatuador.'],
    'meduri' => ['title' => 'Meduri / Ankh Tattoo', 'type' => 'Projeto legado', 'status' => 'Revisar', 'description' => 'Projeto promocional de tattoo com admin, vouchers, Mercado Pago e ferramentas antigas.'],
    'orcamento' => ['title' => 'Orcamento Tattoo', 'type' => 'Operacao', 'status' => 'Ativo', 'description' => 'Ferramenta para montar estimativa de tattoo, hotspots corporais, admin e dados de preco.'],
    'paula' => ['title' => 'Projeto Paula', 'type' => 'Sistema', 'status' => 'Em construcao', 'description' => 'Agente de vagas: curriculos, extracao de perfil, busca em fontes publicas e compatibilidade com vagas.'],
    'plan' => ['title' => 'Plan Financeiro', 'type' => 'Sistema', 'status' => 'Ativo', 'description' => 'Gerenciamento financeiro em PHP/MySQL inspirado em planilha, com importacao de extratos e categorias.'],
    'pressao' => ['title' => 'Pressao Arterial', 'type' => 'Saude', 'status' => 'Ativo', 'description' => 'Pagina de acompanhamento de medicoes de pressao arterial.'],
    'projetocrm' => ['title' => 'Projeto CRM', 'type' => 'Legado', 'status' => 'Revisar', 'description' => 'Possivel copia/versao antiga do CRM. Precisa revisar antes de alterar.'],
    'rifa' => ['title' => 'Rifa Beneficente', 'type' => 'Campanha', 'status' => 'Publicado', 'description' => 'Pagina de rifa/sorteio beneficente com premio de tatuagem.'],
    'storage' => ['title' => 'Storage', 'type' => 'Runtime', 'status' => 'Suporte', 'description' => 'Arquivos gerados, logs ou dados locais de aplicacoes. Nao deve virar pagina publica.'],
    'v2' => ['title' => 'Site V2', 'type' => 'Landing page', 'status' => 'Legado', 'description' => 'Versao alternativa/antiga da landing page Daniel Tatuador.'],
    'witcher' => ['title' => 'Witcher Dub BR', 'type' => 'Projeto externo', 'status' => 'Ativo', 'description' => 'Painel operacional para dublagem PT-BR de The Witcher Enhanced Edition.'],
    'zap' => ['title' => 'Analisador WhatsApp', 'type' => 'Ferramenta', 'status' => 'Experimento', 'description' => 'Ferramenta para analisar conversas exportadas do WhatsApp.'],
];

$ignoredDirs = [
    '.', '..', '.git', '.github', '.vscode', '.codex-checkpoints', '_audit_backups',
    'node_modules', 'ssl', 'tokens'
];
$runtimeHints = ['auth_info', 'node_modules', 'uploads', 'cache', 'logs', 'backups'];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function human_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1, ',', '.') . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

function extract_title(string $dir): string
{
    foreach (['index.php', 'index.html'] as $entry) {
        $file = $dir . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($file)) {
            continue;
        }
        $html = file_get_contents($file, false, null, 0, 60000);
        if (is_string($html) && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
            return trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8'));
        }
    }
    return '';
}

function dir_stats(string $dir, array $runtimeHints): array
{
    $files = 0;
    $bytes = 0;
    $last = is_dir($dir) ? filemtime($dir) : 0;
    $hasReadme = false;
    $hasEntry = false;
    $hasRuntime = false;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $name = $item->getFilename();
        $path = $item->getPathname();
        foreach ($runtimeHints as $hint) {
            if (stripos($path, DIRECTORY_SEPARATOR . $hint . DIRECTORY_SEPARATOR) !== false) {
                $hasRuntime = true;
                continue 2;
            }
        }
        $last = max($last, (int)$item->getMTime());
        if ($item->isFile()) {
            $files++;
            $bytes += (int)$item->getSize();
            if (preg_match('/^readme/i', $name)) {
                $hasReadme = true;
            }
            if (in_array(strtolower($name), ['index.php', 'index.html'], true)) {
                $hasEntry = true;
            }
        }
    }

    return compact('files', 'bytes', 'last', 'hasReadme', 'hasEntry', 'hasRuntime');
}

$projects = [];
foreach (scandir($root) ?: [] as $name) {
    $path = $root . DIRECTORY_SEPARATOR . $name;
    if (!is_dir($path) || in_array($name, $ignoredDirs, true)) {
        continue;
    }
    $stats = dir_stats($path, $runtimeHints);
    $note = $projectNotes[$name] ?? [];
    $autoTitle = extract_title($path);
    $projects[] = [
        'slug' => $name,
        'title' => $note['title'] ?? ($autoTitle ?: ucwords(str_replace(['-', '_'], ' ', $name))),
        'autoTitle' => $autoTitle,
        'type' => $note['type'] ?? 'Sem categoria',
        'status' => $note['status'] ?? 'Mapear',
        'description' => $note['description'] ?? 'Projeto ou pasta ainda sem descricao manual. Abrir e revisar antes de tomar decisoes.',
        'url' => '../' . rawurlencode($name) . '/',
        'stats' => $stats,
    ];
}

usort($projects, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));
$types = array_values(array_unique(array_map(static fn(array $p): string => $p['type'], $projects)));
sort($types);
$totalFiles = array_sum(array_map(static fn(array $p): int => $p['stats']['files'], $projects));
$totalBytes = array_sum(array_map(static fn(array $p): int => $p['stats']['bytes'], $projects));
$lastUpdate = max(array_map(static fn(array $p): int => $p['stats']['last'], $projects) ?: [time()]);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Painel de Projetos | Daniel Tatuador</title>
  <meta name="robots" content="noindex,nofollow">
  <style>
    :root{--bg:#0b0d10;--panel:#141820;--panel2:#10141b;--text:#f4f7fb;--muted:#9ca8b8;--line:#273142;--accent:#d6a24c;--accent2:#51b3ff;--ok:#55d68b;--warn:#ffca58;--danger:#ff6b6b}
    *{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,#08090c,#10141b 42%,#0b0d10);color:var(--text);font-family:Inter,Segoe UI,system-ui,sans-serif}a{color:inherit}.wrap{width:min(1180px,calc(100% - 32px));margin:auto}.top{position:sticky;top:0;z-index:5;background:rgba(11,13,16,.86);backdrop-filter:blur(16px);border-bottom:1px solid var(--line)}.top-in{min-height:72px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{font-weight:900;letter-spacing:.03em}.brand small{display:block;color:var(--muted);font-weight:600;margin-top:4px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:0 14px;border:1px solid var(--line);border-radius:8px;background:#10141b;color:var(--text);text-decoration:none;font-weight:800}.hero{padding:42px 0 24px}.hero h1{margin:0;font-size:clamp(34px,6vw,72px);line-height:.94;letter-spacing:0}.hero p{max-width:760px;color:var(--muted);line-height:1.7}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:26px 0}.stat{padding:18px;border:1px solid var(--line);border-radius:8px;background:rgba(20,24,32,.8)}.stat b{display:block;font-size:28px}.stat span{color:var(--muted);font-size:13px}.toolbar{display:grid;grid-template-columns:1fr auto auto;gap:12px;margin:24px 0}.input,.select{min-height:44px;border:1px solid var(--line);border-radius:8px;background:#0e1218;color:var(--text);padding:0 13px;font:inherit}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding-bottom:54px}.card{display:flex;flex-direction:column;gap:15px;min-height:285px;padding:18px;border:1px solid var(--line);border-radius:8px;background:linear-gradient(180deg,var(--panel),var(--panel2));box-shadow:0 18px 45px rgba(0,0,0,.22)}.card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.card h2{margin:0;font-size:22px;letter-spacing:0}.slug{color:var(--muted);font-family:Consolas,monospace;font-size:12px}.badge{display:inline-flex;align-items:center;white-space:nowrap;min-height:28px;padding:0 9px;border:1px solid var(--line);border-radius:999px;color:#dbe7f5;background:rgba(255,255,255,.03);font-size:12px;font-weight:800}.status-Critico,.status-Ativo,.status-Publicado{border-color:rgba(85,214,139,.35);color:#b9ffd5}.status-Revisar,.status-Mapear{border-color:rgba(255,202,88,.38);color:#ffe0a1}.status-Em-construcao,.status-Experimento,.status-Teste{border-color:rgba(81,179,255,.38);color:#b9e1ff}.desc{margin:0;color:#c9d3df;line-height:1.55}.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:auto}.meta div{padding:10px;border:1px solid rgba(39,49,66,.75);border-radius:8px;background:rgba(255,255,255,.025)}.meta b{display:block;font-size:13px}.meta span{display:block;margin-top:3px;color:var(--muted);font-size:12px}.actions{display:flex;gap:8px;flex-wrap:wrap}.actions .btn{min-height:38px}.ghost{color:var(--muted)}.empty{display:none;padding:24px;border:1px solid var(--line);border-radius:8px;background:var(--panel);color:var(--muted)}@media(max-width:920px){.grid{grid-template-columns:1fr 1fr}.stats{grid-template-columns:1fr 1fr}.toolbar{grid-template-columns:1fr}}@media(max-width:620px){.grid,.stats{grid-template-columns:1fr}.top-in{align-items:flex-start;flex-direction:column;padding:14px 0}.hero{padding-top:30px}}
  </style>
</head>
<body>
  <header class="top">
    <div class="wrap top-in">
      <div class="brand">Painel de Projetos <small>Raiz do site danieltatuador.com</small></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="../">Voltar ao site</a>
        <?php if ($user): ?><a class="btn" href="../plan/logout.php">Sair</a><?php endif; ?>
      </div>
    </div>
  </header>

<?php if (!$user): ?>
  <main class="wrap">
    <section class="hero">
      <h1>Entra primeiro, depois a gente organiza a casa.</h1>
      <p>Este painel usa o mesmo login do Plan Financeiro, com o mesmo banco de dados e a mesma sessao.</p>
    </section>
    <section class="card" style="max-width:460px;margin:0 0 54px">
      <form id="loginForm" style="display:grid;gap:14px">
        <div>
          <h2 style="margin:0 0 6px">Login</h2>
          <p class="ghost" style="margin:0">Acesse com o usuario do Plan.</p>
        </div>
        <label style="display:grid;gap:7px;color:var(--muted);font-weight:800">
          E-mail
          <input class="input" name="email" type="email" value="danielaraujodasilva@gmail.com" autocomplete="email" required>
        </label>
        <label style="display:grid;gap:7px;color:var(--muted);font-weight:800">
          Senha
          <input class="input" name="password" type="password" autocomplete="current-password" required>
        </label>
        <button class="btn" type="submit" style="background:var(--accent);color:#15100a;border-color:var(--accent)">Entrar</button>
        <p id="loginMessage" class="ghost" style="margin:0;min-height:22px"></p>
      </form>
    </section>
  </main>
  <script>
    const loginForm = document.getElementById('loginForm');
    const loginMessage = document.getElementById('loginMessage');
    loginForm.addEventListener('submit', async event => {
      event.preventDefault();
      loginMessage.textContent = 'Entrando...';
      const form = new FormData(loginForm);
      const response = await fetch('../plan/api.php?action=login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': <?= json_encode($csrf) ?>
        },
        body: JSON.stringify({
          email: form.get('email'),
          password: form.get('password')
        })
      });
      const data = await response.json().catch(() => ({ok:false,message:'Nao foi possivel fazer login.'}));
      if (!response.ok || !data.ok) {
        loginMessage.textContent = data.message || 'E-mail ou senha invalidos.';
        return;
      }
      location.reload();
    });
  </script>
<?php else: ?>
  <main class="wrap">
    <section class="hero">
      <h1>Mapa vivo da bagunca organizada.</h1>
      <p>Esta pagina lista as pastas da raiz, identifica o que parece ser projeto, campanha, ferramenta, asset ou runtime, e mostra quando cada coisa foi mexida pela ultima vez. As descricoes principais podem ser refinadas conforme formos limpando a casa.</p>
      <p class="ghost">Logado como <?= h((string)($user['name'] ?? $user['email'] ?? 'usuario')) ?>.</p>
      <div class="stats">
        <div class="stat"><b><?= count($projects) ?></b><span>pastas mapeadas</span></div>
        <div class="stat"><b><?= number_format($totalFiles, 0, ',', '.') ?></b><span>arquivos contabilizados</span></div>
        <div class="stat"><b><?= h(human_bytes($totalBytes)) ?></b><span>tamanho aproximado</span></div>
        <div class="stat"><b><?= h(date('d/m/Y', $lastUpdate)) ?></b><span>ultima alteracao</span></div>
      </div>
    </section>

    <section class="toolbar" aria-label="Filtros">
      <input class="input" id="search" type="search" placeholder="Buscar por nome, pasta, descricao ou status">
      <select class="select" id="typeFilter">
        <option value="">Todos os tipos</option>
        <?php foreach ($types as $type): ?>
          <option value="<?= h($type) ?>"><?= h($type) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="select" id="sortBy">
        <option value="name">Ordenar por nome</option>
        <option value="recent">Mais recentes primeiro</option>
        <option value="files">Mais arquivos primeiro</option>
      </select>
    </section>

    <div class="empty" id="empty">Nada encontrado nesse filtro.</div>
    <section class="grid" id="projectGrid">
      <?php foreach ($projects as $project): $statusClass = 'status-' . preg_replace('/[^A-Za-z0-9]+/', '-', $project['status']); ?>
        <article class="card"
          data-name="<?= h(mb_strtolower($project['title'] . ' ' . $project['slug'] . ' ' . $project['description'] . ' ' . $project['status'], 'UTF-8')) ?>"
          data-type="<?= h($project['type']) ?>"
          data-time="<?= (int)$project['stats']['last'] ?>"
          data-files="<?= (int)$project['stats']['files'] ?>">
          <div class="card-head">
            <div>
              <h2><?= h($project['title']) ?></h2>
              <div class="slug">/<?= h($project['slug']) ?></div>
            </div>
            <span class="badge <?= h($statusClass) ?>"><?= h($project['status']) ?></span>
          </div>
          <p class="desc"><?= h($project['description']) ?></p>
          <?php if ($project['autoTitle'] && $project['autoTitle'] !== $project['title']): ?>
            <div class="ghost">Titulo detectado: <?= h($project['autoTitle']) ?></div>
          <?php endif; ?>
          <div class="meta">
            <div><b><?= h($project['type']) ?></b><span>tipo</span></div>
            <div><b><?= h(date('d/m/Y H:i', $project['stats']['last'])) ?></b><span>ultima alteracao</span></div>
            <div><b><?= number_format($project['stats']['files'], 0, ',', '.') ?></b><span>arquivos</span></div>
            <div><b><?= h(human_bytes($project['stats']['bytes'])) ?></b><span>tamanho</span></div>
          </div>
          <div class="actions">
            <?php if ($project['stats']['hasEntry']): ?>
              <a class="btn" href="<?= h($project['url']) ?>">Abrir</a>
            <?php endif; ?>
            <?php if ($project['stats']['hasReadme']): ?>
              <span class="badge">tem README</span>
            <?php endif; ?>
            <?php if ($project['stats']['hasRuntime']): ?>
              <span class="badge">tem runtime local</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  </main>

  <script>
    const grid = document.getElementById('projectGrid');
    const cards = [...grid.querySelectorAll('.card')];
    const search = document.getElementById('search');
    const typeFilter = document.getElementById('typeFilter');
    const sortBy = document.getElementById('sortBy');
    const empty = document.getElementById('empty');

    function applyFilters() {
      const term = search.value.trim().toLowerCase();
      const type = typeFilter.value;
      let visible = 0;

      cards.forEach(card => {
        const ok = (!term || card.dataset.name.includes(term)) && (!type || card.dataset.type === type);
        card.style.display = ok ? '' : 'none';
        if (ok) visible++;
      });

      const sorted = [...cards].sort((a, b) => {
        if (sortBy.value === 'recent') return Number(b.dataset.time) - Number(a.dataset.time);
        if (sortBy.value === 'files') return Number(b.dataset.files) - Number(a.dataset.files);
        return a.querySelector('h2').textContent.localeCompare(b.querySelector('h2').textContent, 'pt-BR');
      });
      sorted.forEach(card => grid.appendChild(card));
      empty.style.display = visible ? 'none' : 'block';
    }

    [search, typeFilter, sortBy].forEach(el => el.addEventListener('input', applyFilters));
    applyFilters();
  </script>
<?php endif; ?>
</body>
</html>
