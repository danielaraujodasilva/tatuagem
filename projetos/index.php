<?php
$root = dirname(__DIR__);
require_once $root . '/plan/includes/bootstrap.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ./');
    exit;
}

$user = current_user();
$csrf = csrf_token();

$projectNotes = [
    'auth' => ['title' => 'Auth', 'group' => 'Infra e base tecnica', 'status' => 'Ativo', 'description' => 'Login, cadastro, recuperacao de senha e gestao de usuarios usados por outros modulos.'],
    'calculadora' => ['title' => 'Calculadora de Taxas', 'group' => 'Ferramentas pequenas', 'status' => 'Utilitario', 'description' => 'Calculadora HTML simples para taxas, custos ou simulacoes rapidas.'],
    'cnjp' => ['title' => 'CNJP', 'group' => 'Projetos externos', 'status' => 'Ativo', 'description' => 'Landing page e area privada/roadmap para conciliacao, mediacao e arbitragem extrajudicial.'],
    'crm' => ['title' => 'CRM Tattoo', 'group' => 'Operacao do estudio', 'status' => 'Critico', 'description' => 'Central de atendimento, leads, agenda, WhatsApp, automacoes, relatorios e assistente IA.'],
    'ficha' => ['title' => 'Ficha de Cliente', 'group' => 'Operacao do estudio', 'status' => 'Ativo', 'description' => 'Cadastro de clientes, anamnese, tatuagens, agenda visual, conta do cliente e mapa.'],
    'financeiro' => ['title' => 'Financeiro', 'group' => 'Sistemas de negocio', 'status' => 'Em construcao', 'description' => 'Sistema financeiro multiusuario com instancias, membros e base para sincronizacao/deploy.'],
    'flash' => ['title' => 'Flash Tattoo', 'group' => 'Campanhas e paginas publicas', 'status' => 'Publicado', 'description' => 'Pagina promocional de flash tattoo com chamada para agendamento e integracao de pagamento.'],
    'fotos' => ['title' => 'Fotos', 'group' => 'Assets e midia', 'status' => 'Suporte', 'description' => 'Pasta de imagens antigas ou auxiliares usadas por paginas do site.'],
    'fran' => ['title' => 'Fran / CNJP', 'group' => 'Projetos externos', 'status' => 'Revisar', 'description' => 'Pagina sobre nomeacao de arbitros e conciliadores. Parece relacionada ao CNJP.'],
    'galeria' => ['title' => 'Galeria', 'group' => 'Assets e midia', 'status' => 'Ativo', 'description' => 'Fotos de tatuagens e imagens sincronizadas do Instagram usadas na galeria do site principal.'],
    'img' => ['title' => 'Imagens do Site', 'group' => 'Assets e midia', 'status' => 'Suporte', 'description' => 'Imagens, fundos, favicon e midias da pagina principal Daniel Tatuador.'],
    'imoveis' => ['title' => 'Radar Imoveis', 'group' => 'Sistemas de negocio', 'status' => 'Em construcao', 'description' => 'Rastreador PHP/MySQL/Node para oportunidades imobiliarias em OLX, Mercado Livre, Zap e Viva Real.'],
    'includes' => ['title' => 'Includes', 'group' => 'Infra e base tecnica', 'status' => 'Suporte', 'description' => 'Componentes PHP compartilhados, como menu interno de apps autenticados.'],
    'ink' => ['title' => 'Misturador de Tintas', 'group' => 'Ferramentas pequenas', 'status' => 'Experimento', 'description' => 'Ferramenta de calibracao/mistura de tintas.'],
    'instagram' => ['title' => 'Instagram Sync', 'group' => 'Infra e base tecnica', 'status' => 'Ativo', 'description' => 'Fluxos de callback, feed, sincronizacao e painel para integrar conteudo do Instagram.'],
    'joguiunho' => ['title' => 'Joguiunho', 'group' => 'Ferramentas pequenas', 'status' => 'Teste', 'description' => 'Jogo/teste simples dentro do site Daniel Tatuador.'],
    'meduri' => ['title' => 'Meduri / Ankh Tattoo', 'group' => 'Revisar ou arquivar', 'status' => 'Revisar', 'description' => 'Projeto promocional de tattoo com admin, vouchers, Mercado Pago e ferramentas antigas.'],
    'orcamento' => ['title' => 'Orcamento Tattoo', 'group' => 'Operacao do estudio', 'status' => 'Ativo', 'description' => 'Ferramenta para montar estimativa de tattoo, hotspots corporais, admin e dados de preco.'],
    'paula' => ['title' => 'Projeto Paula', 'group' => 'Sistemas de negocio', 'status' => 'Em construcao', 'description' => 'Agente de vagas: curriculos, extracao de perfil, busca em fontes publicas e compatibilidade com vagas.'],
    'plan' => ['title' => 'Plan Financeiro', 'group' => 'Sistemas de negocio', 'status' => 'Ativo', 'description' => 'Gerenciamento financeiro em PHP/MySQL inspirado em planilha, com importacao de extratos e categorias.'],
    'pressao' => ['title' => 'Pressao Arterial', 'group' => 'Projetos externos', 'status' => 'Ativo', 'description' => 'Pagina de acompanhamento de medicoes de pressao arterial.'],
    'projetocrm' => ['title' => 'Projeto CRM', 'group' => 'Revisar ou arquivar', 'status' => 'Revisar', 'description' => 'Possivel copia/versao antiga do CRM. Precisa revisar antes de alterar.'],
    'rifa' => ['title' => 'Rifa Beneficente', 'group' => 'Campanhas e paginas publicas', 'status' => 'Publicado', 'description' => 'Pagina de rifa/sorteio beneficente com premio de tatuagem.'],
    'storage' => ['title' => 'Storage', 'group' => 'Infra e base tecnica', 'status' => 'Suporte', 'description' => 'Arquivos gerados, logs ou dados locais de aplicacoes. Nao deve virar pagina publica.'],
    'v2' => ['title' => 'Site V2', 'group' => 'Revisar ou arquivar', 'status' => 'Legado', 'description' => 'Versao alternativa/antiga da landing page Daniel Tatuador.'],
    'witcher' => ['title' => 'Witcher Dub BR', 'group' => 'Projetos externos', 'status' => 'Ativo', 'description' => 'Painel operacional para dublagem PT-BR de The Witcher Enhanced Edition.'],
    'zap' => ['title' => 'Analisador WhatsApp', 'group' => 'Ferramentas pequenas', 'status' => 'Experimento', 'description' => 'Ferramenta para analisar conversas exportadas do WhatsApp.'],
];

$ignoredDirs = [
    '.', '..', '.git', '.github', '.vscode', '.codex-checkpoints', '_audit_backups',
    'node_modules', 'ssl', 'tokens', 'projetos'
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
        'group' => $note['group'] ?? 'Revisar ou arquivar',
        'status' => $note['status'] ?? 'Mapear',
        'description' => $note['description'] ?? 'Projeto ou pasta ainda sem descricao manual. Abrir e revisar antes de tomar decisoes.',
        'url' => '../' . rawurlencode($name) . '/',
        'stats' => $stats,
    ];
}

usort($projects, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));
$groupOrder = [
    'Operacao do estudio',
    'Sistemas de negocio',
    'Campanhas e paginas publicas',
    'Ferramentas pequenas',
    'Projetos externos',
    'Assets e midia',
    'Infra e base tecnica',
    'Revisar ou arquivar',
];
$groups = [];
foreach ($groupOrder as $group) {
    $groups[$group] = [];
}
foreach ($projects as $project) {
    $groups[$project['group']][] = $project;
}
$groups = array_filter($groups);
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
    :root{--bg:#f4f0e8;--ink:#151515;--muted:#6f6a61;--panel:#fffaf1;--panel2:#ebe3d5;--line:#d8ccba;--dark:#202020;--accent:#b8782d;--blue:#315f86;--green:#23724f;--red:#9d3c31}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,Segoe UI,system-ui,sans-serif}a{color:inherit}.wrap{width:min(1220px,calc(100% - 34px));margin:auto}.top{position:sticky;top:0;z-index:5;background:rgba(244,240,232,.9);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}.top-in{min-height:70px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{font-weight:900}.brand small{display:block;color:var(--muted);font-weight:650;margin-top:3px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border:1px solid var(--line);border-radius:7px;background:#fffaf1;color:var(--ink);text-decoration:none;font-weight:850}.btn:hover{border-color:var(--accent);color:#7a4312}.hero{padding:34px 0 14px}.hero h1{max-width:850px;margin:0;font-size:clamp(34px,5vw,62px);line-height:1;letter-spacing:0}.hero p{max-width:790px;color:var(--muted);line-height:1.65}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:22px 0}.stat{padding:16px;border:1px solid var(--line);border-radius:7px;background:var(--panel)}.stat b{display:block;font-size:26px}.stat span{color:var(--muted);font-size:13px}.toolbar{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;margin:20px 0}.input,.select{min-height:42px;border:1px solid var(--line);border-radius:7px;background:#fffaf1;color:var(--ink);padding:0 12px;font:inherit}.group-tabs{display:flex;gap:8px;overflow:auto;padding:0 0 12px;margin-bottom:10px}.tab{white-space:nowrap;border:1px solid var(--line);border-radius:999px;background:#fffaf1;padding:9px 12px;color:var(--muted);font-weight:850;cursor:pointer}.tab.is-active{background:var(--dark);border-color:var(--dark);color:#fff}.layout{display:grid;grid-template-columns:260px 1fr;gap:18px;padding-bottom:54px}.side{position:sticky;top:90px;align-self:start;display:grid;gap:10px}.side-card,.group{border:1px solid var(--line);border-radius:8px;background:var(--panel)}.side-card{padding:16px}.side-card h2,.group h2{margin:0;font-size:18px}.side-card p{margin:8px 0 0;color:var(--muted);line-height:1.5}.group{margin-bottom:16px;overflow:hidden}.group-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid var(--line);background:#f8f1e6}.group-head small{color:var(--muted);font-weight:800}.project-list{display:grid}.card{display:grid;grid-template-columns:minmax(210px,.8fr) minmax(260px,1.2fr) 210px auto;gap:14px;align-items:center;padding:15px 18px;border-bottom:1px solid var(--line)}.card:last-child{border-bottom:0}.card h3{margin:0;font-size:19px}.slug{margin-top:4px;color:var(--muted);font-family:Consolas,monospace;font-size:12px}.desc{margin:0;color:#4c4841;line-height:1.45}.meta{display:flex;gap:8px;flex-wrap:wrap}.pill,.badge{display:inline-flex;align-items:center;white-space:nowrap;min-height:26px;padding:0 8px;border:1px solid var(--line);border-radius:999px;background:#fffaf1;color:var(--muted);font-size:12px;font-weight:850}.status-Critico{border-color:rgba(157,60,49,.35);color:var(--red)}.status-Ativo,.status-Publicado{border-color:rgba(35,114,79,.35);color:var(--green)}.status-Revisar,.status-Mapear,.status-Legado{border-color:rgba(184,120,45,.38);color:#8d581f}.status-Em-construcao,.status-Experimento,.status-Teste{border-color:rgba(49,95,134,.38);color:var(--blue)}.actions{display:flex;justify-content:flex-end}.ghost{color:var(--muted)}.empty{display:none;padding:20px;border:1px solid var(--line);border-radius:8px;background:var(--panel);color:var(--muted)}@media(max-width:1040px){.layout{grid-template-columns:1fr}.side{position:static;grid-template-columns:repeat(2,1fr)}.card{grid-template-columns:1fr}.actions{justify-content:flex-start}}@media(max-width:680px){.stats,.side{grid-template-columns:1fr}.toolbar{grid-template-columns:1fr}.top-in{align-items:flex-start;flex-direction:column;padding:13px 0}.card{padding:16px}.hero{padding-top:26px}}
  </style>
</head>
<body>
  <header class="top">
    <div class="wrap top-in">
      <div class="brand">Painel de Projetos <small>Raiz do site danieltatuador.com</small></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="../">Voltar ao site</a>
        <?php if ($user): ?><a class="btn" href="?logout=1">Sair</a><?php endif; ?>
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
      <h1>Mapa dos projetos da raiz.</h1>
      <p>Separei as pastas por papel real: operacao do estudio, sistemas de negocio, campanhas, ferramentas, projetos externos, assets, infra e coisas para revisar. Agora da para bater o olho sem a mente pedir demissao.</p>
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
      <select class="select" id="sortBy">
        <option value="name">Nome</option>
        <option value="recent">Mais recentes</option>
        <option value="files">Mais arquivos</option>
      </select>
    </section>

    <nav class="group-tabs" aria-label="Grupos de projetos">
      <button class="tab is-active" type="button" data-group="">Tudo</button>
      <?php foreach ($groups as $groupName => $items): ?>
        <button class="tab" type="button" data-group="<?= h($groupName) ?>"><?= h($groupName) ?> · <?= count($items) ?></button>
      <?php endforeach; ?>
    </nav>

    <div class="empty" id="empty">Nada encontrado nesse filtro.</div>
    <div class="layout">
      <aside class="side">
        <section class="side-card">
          <h2>Prioridade pratica</h2>
          <p>Comecar por CRM, Ficha, Orcamento e Plan. Essas pastas parecem sustentar operacao real.</p>
        </section>
        <section class="side-card">
          <h2>Limpar depois</h2>
          <p>Meduri, Projeto CRM e V2 parecem legado ou copia. Melhor revisar antes de mexer ou apagar.</p>
        </section>
      </aside>

      <section id="projectGrid">
        <?php foreach ($groups as $groupName => $items): ?>
          <section class="group" data-group-section="<?= h($groupName) ?>">
            <div class="group-head">
              <h2><?= h($groupName) ?></h2>
              <small><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></small>
            </div>
            <div class="project-list">
              <?php foreach ($items as $project): $statusClass = 'status-' . preg_replace('/[^A-Za-z0-9]+/', '-', $project['status']); ?>
                <article class="card"
                  data-name="<?= h(mb_strtolower($project['title'] . ' ' . $project['slug'] . ' ' . $project['description'] . ' ' . $project['status'] . ' ' . $project['group'], 'UTF-8')) ?>"
                  data-group="<?= h($project['group']) ?>"
                  data-time="<?= (int)$project['stats']['last'] ?>"
                  data-files="<?= (int)$project['stats']['files'] ?>">
                  <div>
                    <h3><?= h($project['title']) ?></h3>
                    <div class="slug">/<?= h($project['slug']) ?></div>
                  </div>
                  <p class="desc"><?= h($project['description']) ?></p>
                  <div class="meta">
                    <span class="badge <?= h($statusClass) ?>"><?= h($project['status']) ?></span>
                    <span class="pill"><?= h(date('d/m/Y', $project['stats']['last'])) ?></span>
                    <span class="pill"><?= number_format($project['stats']['files'], 0, ',', '.') ?> arq.</span>
                    <span class="pill"><?= h(human_bytes($project['stats']['bytes'])) ?></span>
                    <?php if ($project['stats']['hasReadme']): ?><span class="pill">README</span><?php endif; ?>
                    <?php if ($project['stats']['hasRuntime']): ?><span class="pill">runtime local</span><?php endif; ?>
                  </div>
                  <div class="actions">
                    <?php if ($project['stats']['hasEntry']): ?>
                      <a class="btn" href="<?= h($project['url']) ?>">Abrir</a>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </section>
    </div>
  </main>

  <script>
    const cards = [...document.querySelectorAll('.card')];
    const groups = [...document.querySelectorAll('[data-group-section]')];
    const search = document.getElementById('search');
    const sortBy = document.getElementById('sortBy');
    const empty = document.getElementById('empty');
    const tabs = [...document.querySelectorAll('.tab')];
    let activeGroup = '';

    function applyFilters() {
      const term = search.value.trim().toLowerCase();
      let visible = 0;

      cards.forEach(card => {
        const ok = (!term || card.dataset.name.includes(term)) && (!activeGroup || card.dataset.group === activeGroup);
        card.style.display = ok ? '' : 'none';
        if (ok) visible++;
      });

      groups.forEach(group => {
        const groupCards = [...group.querySelectorAll('.card')];
        const groupVisible = groupCards.some(card => card.style.display !== 'none');
        group.style.display = groupVisible ? '' : 'none';
        const list = group.querySelector('.project-list');
        groupCards.sort((a, b) => {
          if (sortBy.value === 'recent') return Number(b.dataset.time) - Number(a.dataset.time);
          if (sortBy.value === 'files') return Number(b.dataset.files) - Number(a.dataset.files);
          return a.querySelector('h3').textContent.localeCompare(b.querySelector('h3').textContent, 'pt-BR');
        }).forEach(card => list.appendChild(card));
      });

      empty.style.display = visible ? 'none' : 'block';
    }

    tabs.forEach(tab => tab.addEventListener('click', () => {
      activeGroup = tab.dataset.group;
      tabs.forEach(item => item.classList.toggle('is-active', item === tab));
      applyFilters();
    }));
    [search, sortBy].forEach(el => el.addEventListener('input', applyFilters));
    applyFilters();
  </script>
<?php endif; ?>
</body>
</html>
