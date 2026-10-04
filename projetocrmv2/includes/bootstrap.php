<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../auth/auth.php';
date_default_timezone_set('America/Sao_Paulo');

function v2_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $local = [];
    $path = __DIR__ . '/../config.local.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $local = $loaded;
        }
    }

    $cfg = array_merge([
        'tts_engine' => 'windows',
        'piper_bin' => '',
        'piper_voice' => '',
    ], $local);

    return $cfg;
}

/** Conexao com o banco do CRM atual (somente leitura aqui). */
function v2_crm(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $local = [];
    $path = __DIR__ . '/../../crm/config.local.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $local = $loaded;
        }
    }

    $cfg = array_merge([
        'host' => getenv('CRM_DB_HOST') ?: 'localhost',
        'database' => getenv('CRM_DB_NAME') ?: 'crm_simples',
        'username' => getenv('CRM_DB_USER') ?: '',
        'password' => getenv('CRM_DB_PASS') ?: '',
    ], $local);

    if ($cfg['username'] === '') {
        throw new RuntimeException('CRM nao configurado (crm/config.local.php ou CRM_DB_*).');
    }

    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4",
        (string)$cfg['username'],
        (string)$cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );

    return $pdo;
}

/** Conexao com o banco da ficha/agenda. */
function v2_ficha(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $local = [];
    $path = __DIR__ . '/../../ficha/config/conexao.local.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $local = $loaded;
        }
    }

    $cfg = array_merge([
        'host' => getenv('FICHA_DB_HOST') ?: 'localhost',
        'port' => (int)(getenv('FICHA_DB_PORT') ?: 3306),
        'database' => getenv('FICHA_DB_NAME') ?: 'tatuagem_novo',
        'username' => getenv('FICHA_DB_USER') ?: '',
        'password' => getenv('FICHA_DB_PASS') ?: '',
    ], $local);

    if ($cfg['username'] === '') {
        throw new RuntimeException('Ficha nao configurada (ficha/config/conexao.local.php ou FICHA_DB_*).');
    }

    $conn = new mysqli((string)$cfg['host'], (string)$cfg['username'], (string)$cfg['password'], (string)$cfg['database'], (int)$cfg['port']);
    $conn->set_charset('utf8mb4');

    return $conn;
}

function v2_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function v2_money($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function v2_norm(?string $phone): string
{
    return auth_normalize_phone((string)$phone);
}

function v2_try(callable $fn, $fallback = [])
{
    try {
        return $fn();
    } catch (Throwable $e) {
        return $fallback;
    }
}

function v2_tabela_existe(PDO $pdo, string $table): bool
{
    return v2_try(static function () use ($pdo, $table) {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }, false);
}

function v2_instalado(): bool
{
    return v2_try(static function (): bool {
        return v2_tabela_existe(v2_crm(), 'v2_clientes');
    }, false);
}

function v2_paginas(): array
{
    return [
        'hoje' => ['label' => 'Hoje', 'icon' => '☀'],
        'cliente' => ['label' => 'Cliente', 'icon' => '👤'],
        'conversa' => ['label' => 'WhatsApp', 'icon' => '💬'],
        'aprendizado' => ['label' => 'Aprendizado', 'icon' => '📚'],
        'rotina' => ['label' => 'Rotina', 'icon' => '🔁'],
        'voz' => ['label' => 'Voz', 'icon' => '🎤'],
        'simulador' => ['label' => 'Simulador', 'icon' => '🎭'],
    ];
}

/* ---------- Status, periodo, filtros e URLs (compartilhado pelas telas) ---------- */

/** Rotulos amigaveis dos status usados no CRM do WhatsApp. */
function v2_status_opcoes(): array
{
    return [
        'novo' => 'Novo',
        'em_atendimento' => 'Em atendimento',
        'lead_quente' => 'Lead quente',
        'sem_retorno' => 'Sem retorno',
        'agendado' => 'Agendado',
        'fechado' => 'Fechado',
        'perdido' => 'Perdido',
    ];
}

function v2_status_label(?string $status): string
{
    $status = (string)$status;
    $map = v2_status_opcoes();
    return $map[$status] ?? ($status !== '' ? str_replace('_', ' ', $status) : '—');
}

/** Cor do selo para cada status. */
function v2_status_classe(?string $status): string
{
    switch ((string)$status) {
        case 'novo':
        case 'lead_quente':
            return 'b-red';
        case 'em_atendimento':
            return 'b-blue';
        case 'agendado':
        case 'fechado':
            return 'b-green';
        case 'sem_retorno':
            return 'b-amber';
    }
    return 'b-gray';
}

function v2_periodo_opcoes(): array
{
    return [
        'tudo' => 'Tudo',
        'hoje' => 'Hoje',
        '7d' => '7 dias',
        '30d' => '30 dias',
        'mes' => 'Este mês',
        '90d' => '90 dias',
    ];
}

/** Periodo escolhido na URL (ou "custom" quando veio de/ate). */
function v2_periodo_atual(): string
{
    if (trim((string)($_GET['de'] ?? '')) !== '' || trim((string)($_GET['ate'] ?? '')) !== '') {
        return 'custom';
    }
    $p = (string)($_GET['periodo'] ?? 'tudo');
    return array_key_exists($p, v2_periodo_opcoes()) ? $p : 'tudo';
}

/** Intervalo [inicio, fim] em Y-m-d H:i:s, ou null quando o periodo e "tudo". */
function v2_periodo_intervalo(?string $chave = null): ?array
{
    $chave = $chave ?? v2_periodo_atual();
    $fim = date('Y-m-d') . ' 23:59:59';

    switch ($chave) {
        case 'hoje':
            return [date('Y-m-d') . ' 00:00:00', $fim];
        case '7d':
            return [date('Y-m-d', strtotime('-6 days')) . ' 00:00:00', $fim];
        case '30d':
            return [date('Y-m-d', strtotime('-29 days')) . ' 00:00:00', $fim];
        case '90d':
            return [date('Y-m-d', strtotime('-89 days')) . ' 00:00:00', $fim];
        case 'mes':
            return [date('Y-m-01') . ' 00:00:00', $fim];
        case 'custom':
            $de = trim((string)($_GET['de'] ?? ''));
            $ate = trim((string)($_GET['ate'] ?? ''));
            $ini = preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) === 1 ? $de . ' 00:00:00' : null;
            $f = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) === 1 ? $ate . ' 23:59:59' : null;
            if ($ini === null && $f === null) {
                return null;
            }
            return [$ini ?? '2000-01-01 00:00:00', $f ?? $fim];
    }

    return null;
}

function v2_periodo_label(?string $chave = null): string
{
    $chave = $chave ?? v2_periodo_atual();
    if ($chave === 'custom') {
        $de = trim((string)($_GET['de'] ?? ''));
        $ate = trim((string)($_GET['ate'] ?? ''));
        $a = $de !== '' ? date('d/m', strtotime($de)) : 'início';
        $b = $ate !== '' ? date('d/m', strtotime($ate)) : 'hoje';
        return $a . ' a ' . $b;
    }
    return v2_periodo_opcoes()[$chave] ?? 'Tudo';
}

/** Monta um link mantendo os filtros atuais; passe null/vazio no valor para remover. */
function v2_url(array $overrides = [], array $remove = []): string
{
    $q = $_GET;
    foreach ($remove as $k) {
        unset($q[$k]);
    }
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

/** Barra de filtros reutilizavel: periodo (atalhos + intervalo livre). */
function v2_filtro_periodo(array $remove = []): void
{
    $atual = v2_periodo_atual();
    $de = trim((string)($_GET['de'] ?? ''));
    $ate = trim((string)($_GET['ate'] ?? ''));
    $bloqueados = array_merge($remove, ['de', 'ate', 'periodo']);
    ?>
    <div class="fbar">
      <span class="flabel">📅 Período</span>
      <?php foreach (v2_periodo_opcoes() as $k => $label): ?>
        <a class="fchip<?= $k === $atual ? ' on' : '' ?>" href="<?= v2_h(v2_url(['periodo' => $k], ['de', 'ate'])) ?>"><?= v2_h($label) ?></a>
      <?php endforeach; ?>
      <form class="frange" method="get">
        <?php foreach ($_GET as $k => $v): if (in_array($k, $bloqueados, true) || !is_scalar($v)) { continue; } ?>
          <input type="hidden" name="<?= v2_h((string)$k) ?>" value="<?= v2_h((string)$v) ?>">
        <?php endforeach; ?>
        <input type="date" name="de" value="<?= v2_h($de) ?>" aria-label="de">
        <span class="fsep">→</span>
        <input type="date" name="ate" value="<?= v2_h($ate) ?>" aria-label="ate">
        <button class="fgo" type="submit">aplicar</button>
      </form>
    </div>
    <?php
}

/** Barra de busca livre (nome/telefone/interesse) mantendo os demais filtros. */
function v2_barra_busca(string $placeholder = 'Buscar por nome, telefone ou interesse...'): void
{
    $atual = (string)($_GET['busca'] ?? '');
    ?>
    <form class="fbar" method="get">
      <?php foreach ($_GET as $k => $v): if ($k === 'busca' || !is_scalar($v)) { continue; } ?>
        <input type="hidden" name="<?= v2_h((string)$k) ?>" value="<?= v2_h((string)$v) ?>">
      <?php endforeach; ?>
      <div class="fsearch">
        <input type="text" name="busca" value="<?= v2_h($atual) ?>" placeholder="<?= v2_h($placeholder) ?>">
        <button type="submit">buscar</button>
      </div>
      <?php if ($atual !== ''): ?><a class="fchip" href="<?= v2_h(v2_url([], ['busca'])) ?>">✕ limpar</a><?php endif; ?>
    </form>
    <?php
}

/** Iniciais para o avatar. */
function v2_iniciais(?string $nome): string
{
    $limpo = preg_replace('/[^A-Za-zÀ-ÿ ]/', '', (string)$nome) ?: 'C';
    $limpo = trim(preg_replace('/\s+/', ' ', $limpo) ?? 'C');
    if ($limpo === '') {
        return 'C';
    }
    $partes = explode(' ', $limpo);
    $ini = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $ini .= mb_substr($partes[count($partes) - 1], 0, 1);
    }
    return mb_strtoupper($ini, 'UTF-8');
}

/** Texto normalizado (minusculo, sem acento) para casar palavras-chave. */
function v2_texto_normalizado(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'ê' => 'e', 'è' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ]);
}

/**
 * Sugestao de resposta montada com o que a Irene aprendeu nas conversas.
 * Nunca envia nada: e so um rascunho para o Daniel aprovar.
 */
function v2_sugestao_resposta(string $texto): ?array
{
    $t = v2_texto_normalizado($texto);
    $tem = static function (string $regex) use ($t): bool {
        return preg_match($regex, $t) === 1;
    };

    if ($tem('/\b(preco|valor|quanto|custa|custar|caro|barato|orcamento)\b/')) {
        return ['motivo' => 'Perguntou preço', 'texto' => 'Oi! Depende do tamanho e do lugar, mas fica 699 sem pomada anestésica e 1100 com ela 🙌 Me conta o que você quer fazer que eu te passo certinho'];
    }
    if ($tem('/\b(endereco|onde|local|fica|chegar|estudio)\b/')) {
        return ['motivo' => 'Perguntou o endereço', 'texto' => 'Fica na Rua Catende, 287B, Jd Nordeste — pertinho da estação Artur Alvim do metrô 🙌'];
    }
    if ($tem('/\b(doeu|doi|dor|medo|sofrer|aguent)\b/')) {
        return ['motivo' => 'Medo de dor', 'texto' => 'Relaxa! A gente usa pomada anestésica e dá pra fazer em etapas 🙌 Você vai ficar tranquila'];
    }
    if ($tem('/\b(cuidado|cuidados|pomada|cicatriz|sol|piscina|mar|banho)\b/')) {
        return ['motivo' => 'Dúvida de cuidados', 'texto' => 'Lavar com sabonete neutro, secar com papel e passar a pomada fininha 🙌 Nada de sol, piscina ou mar por 15 dias'];
    }
    if ($tem('/\b(horario|hora|horas|dia|dias|agenda|quando|sabado|domingo|vaga)\b/')) {
        return ['motivo' => 'Perguntou horário', 'texto' => 'Tenho vaga sim! Qual dia e horário fica melhor pra você? 🙌'];
    }
    if ($tem('/\b(pensar|depois|vou ver|mais pra frente)\b/')) {
        return ['motivo' => 'Cliente frio', 'texto' => 'Tranquilo! Só te digo que as vagas de fim de semana enchem rápido 🙌 quando quiser eu reservo'];
    }
    if (trim($texto) === '') {
        return null;
    }

    return ['motivo' => 'Retomada aprendida (57x nas conversas)', 'texto' => 'Oi? Bora retomar o agendamento da sua tatuagem?'];
}

function v2_layout_top(string $page, string $title): void
{
    $paginas = v2_paginas();
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= v2_h($title) ?> · Irene</title>
<link rel="stylesheet" href="assets/app.css?v=3">
</head>
<body>
<div class="shell">
  <header class="top">
    <div class="brand">
      <div class="logo">IR</div>
      <div><h1>Irene · projetocrm<strong>v2</strong></h1><p>o sistema do estúdio</p></div>
    </div>
    <span class="chip"><span class="dot"></span> v2 em construção · o CRM atual segue intacto</span>
  </header>
  <nav class="tabs">
    <?php foreach ($paginas as $key => $info): ?>
      <a class="tab<?= $key === $page ? ' on' : '' ?>" href="index.php?page=<?= v2_h($key) ?>"><?= $info['icon'] ?> <?= v2_h($info['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!v2_instalado()): ?>
    <div class="note n-amber" style="margin-bottom:16px">&#9888;&#65039; As tabelas <code>v2_*</code> ainda não foram criadas. Rode <code>database/schema_v2.sql</code>. As telas abaixo já mostram os dados atuais (somente leitura).</div>
  <?php endif; ?>
<?php
}

function v2_layout_bottom(): void
{
    ?>
  <p class="foot">projetocrmv2 · protótipo funcional · nada é apagado ou alterado no CRM atual.</p>
</div>
</body>
</html>
<?php
}


/* ---------- Voz (TTS): utilitarios compartilhados pela tela e pela API ---------- */

/** Localiza um executavel no PATH (Windows). */
function v2_which(string $bin): ?string
{
    $out = @shell_exec('where ' . escapeshellarg($bin) . ' 2>NUL');
    $first = trim((string)strtok((string)$out, "\n"));
    return $first !== '' ? $first : null;
}

/** Interpretador Python usado pelos motores neurais offline. */
function v2_python(): ?string
{
    foreach (['C:\\Program Files\\Python310\\python.exe', 'C:\\Program Files\\Python311\\python.exe'] as $cand) {
        if (is_file($cand)) {
            return $cand;
        }
    }
    return v2_which('python');
}

/** Executavel do edge-tts (voz neural online, sem chave). */
function v2_edge_exe(): ?string
{
    $cands = [
        (string)getenv('APPDATA') . '\\Python\\Python310\\Scripts\\edge-tts.exe',
        (string)getenv('APPDATA') . '\\Python\\Python311\\Scripts\\edge-tts.exe',
    ];
    foreach ($cands as $c) {
        if ($c !== '' && is_file($c)) {
            return $c;
        }
    }
    return v2_which('edge-tts');
}

/** Executavel do Piper (neural offline). */
function v2_piper_bin(): string
{
    $bin = (string)(v2_config()['piper_bin'] ?? '');
    if ($bin !== '' && is_file($bin)) {
        return $bin;
    }
    return (string)(v2_which('piper') ?? '');
}

/** O motor consegue rodar neste servidor? */
function v2_tts_available(string $engine): bool
{
    switch ($engine) {
        case 'windows':
            return stripos(PHP_OS, 'WIN') === 0;
        case 'piper':
            $voice = (string)(v2_config()['piper_voice'] ?? '');
            return v2_piper_bin() !== '' && $voice !== '' && is_file($voice);
        case 'edge':
            return v2_edge_exe() !== null;
        case 'kokoro':
            return v2_python() !== null && is_file(__DIR__ . '/../api/kokoro_tts.py');
    }
    return false;
}

/**
 * Ajustes de prosodia (o "sentimento" da voz).
 * Cada motor tem um padrao e variacoes nomeadas, ouviveis na tela Voz.
 */
function v2_tts_presets(): array
{
    return [
        'edge' => [
            'padrao' => ['rate' => '+4%', 'pitch' => '+2Hz', 'volume' => '+0%'],
            'variacoes' => [
                'calor' => ['rate' => '+14%', 'pitch' => '-3Hz'],
                'calor_rapido' => ['rate' => '+30%', 'pitch' => '-3Hz'],
                'animada' => ['rate' => '+24%', 'pitch' => '+9Hz'],
                'serena' => ['rate' => '-6%', 'pitch' => '-6Hz'],
            ],
        ],
        'piper' => [
            'padrao' => ['length_scale' => 0.98, 'sentence_silence' => 0.32, 'noise_scale' => 0.667, 'noise_w' => 0.8],
            'variacoes' => [
                'calor' => ['length_scale' => 1.06, 'sentence_silence' => 0.48],
                'calor_rapido' => ['length_scale' => 0.88, 'sentence_silence' => 0.26],
                'animada' => ['length_scale' => 0.90, 'sentence_silence' => 0.16],
                'serena' => ['length_scale' => 1.22, 'sentence_silence' => 0.55],
            ],
        ],
        'kokoro' => [
            'padrao' => ['speed' => 1.02, 'pausa_ms' => 220],
            'variacoes' => [
                'calor' => ['speed' => 0.96, 'pausa_ms' => 300],
                'calor_rapido' => ['speed' => 1.14, 'pausa_ms' => 190],
                'animada' => ['speed' => 1.12, 'pausa_ms' => 120],
                'serena' => ['speed' => 0.90, 'pausa_ms' => 400],
            ],
        ],
        'windows' => [
            'padrao' => ['rate' => 0],
            'variacoes' => [
                'calor' => ['rate' => -2],
                'calor_rapido' => ['rate' => 3],
                'animada' => ['rate' => 2],
            ],
        ],
    ];
}

/** Nomes das variacoes de prosodia disponiveis para um motor. */
function v2_tts_variacoes(string $engine): array
{
    $presets = v2_tts_presets();
    return array_keys($presets[$engine]['variacoes'] ?? []);
}

/** Combina padrao + variacao escolhida. */
function v2_tts_prosodia(string $engine, string $variant): array
{
    $presets = v2_tts_presets();
    $p = $presets[$engine]['padrao'] ?? [];
    if ($variant !== '' && isset($presets[$engine]['variacoes'][$variant])) {
        $p = array_merge($p, $presets[$engine]['variacoes'][$variant]);
    }
    return $p;
}

/* ---------- A voz escolhida na tela Voz (salva e usada pelo sistema) ---------- */

/** Voz = numero inteiro com sinal, no formato que o edge-tts aceita. */
function v2_voz_pct(int $n): string
{
    return ($n >= 0 ? '+' : '') . $n . '%';
}

function v2_voz_hz(int $n): string
{
    return ($n >= 0 ? '+' : '') . $n . 'Hz';
}

/** Nomes de voz disponiveis em cada motor (o valor e o que o motor aceita). */
function v2_voz_opcoes(): array
{
    return [
        'edge' => [
            'pt-BR-FranciscaNeural' => 'Francisca · feminina, a mais natural',
            'pt-BR-ThalitaNeural' => 'Thalita · feminina, jovem',
            'pt-BR-YaraNeural' => 'Yara · feminina, leve',
            'pt-BR-GiovannaNeural' => 'Giovanna · feminina',
            'pt-BR-ManuelaNeural' => 'Manuela · feminina',
            'pt-BR-BrendaNeural' => 'Brenda · feminina',
            'pt-BR-ElzaNeural' => 'Elza · feminina, madura',
            'pt-BR-LeilaNeural' => 'Leila · feminina, madura',
            'pt-BR-LeticiaNeural' => 'Leticia · feminina',
            'pt-BR-AntonioNeural' => 'Antonio · masculina',
            'pt-BR-DonatoNeural' => 'Donato · masculina',
            'pt-BR-FabioNeural' => 'Fabio · masculina',
            'pt-BR-HumbertoNeural' => 'Humberto · masculina',
            'pt-BR-JulioNeural' => 'Julio · masculina',
            'pt-BR-NicolauNeural' => 'Nicolau · masculina',
        ],
        'kokoro' => [
            'pf_dora' => 'Dora · feminina',
            'pm_alex' => 'Alex · masculina',
            'pm_santa' => 'Santa · masculina',
        ],
        'windows' => [
            'Microsoft Maria' => 'Maria · feminina',
            'Microsoft Maria Desktop' => 'Maria Desktop · feminina',
            'Microsoft Daniel' => 'Daniel · masculina',
        ],
        'piper' => [],
    ];
}

/** Campo do config.local.php que guarda a voz de cada motor. */
function v2_voz_campo(string $engine): string
{
    return $engine === 'piper' ? 'piper_voice' : $engine . '_voice';
}

/** Voz do config.local.php quando nada foi escolhido na tela. */
function v2_voz_padrao(string $engine): string
{
    $cfg = v2_config();
    $valor = trim((string)($cfg[v2_voz_campo($engine)] ?? ''));
    if ($valor !== '') {
        return $valor;
    }
    $opcoes = v2_voz_opcoes()[$engine] ?? [];
    return (string)(array_key_first($opcoes) ?? '');
}

function v2_voz_arquivo(): string
{
    return __DIR__ . '/../data/voz.json';
}

/** Voz salva na tela (vazio = ainda nao escolheu). */
function v2_voz_salva(): array
{
    static $dados = null;
    if ($dados !== null) {
        return $dados;
    }
    $dados = [];
    $bruto = @file_get_contents(v2_voz_arquivo());
    if ($bruto !== false) {
        $json = json_decode((string)$bruto, true);
        if (is_array($json)) {
            $dados = $json;
        }
    }
    return $dados;
}

/** Ajustes em uso agora: o que foi salvo na tela, senao o config.local.php. */
function v2_voz_atual(): array
{
    $salva = v2_voz_salva();
    $engine = (string)($salva['engine'] ?? '');
    if ($engine === '' || !isset(v2_tts_presets()[$engine])) {
        $engine = (string)(v2_config()['tts_engine'] ?? 'windows');
    }
    if (!isset(v2_tts_presets()[$engine])) {
        $engine = 'windows';
    }
    $voz = trim((string)($salva['voz'] ?? ''));
    $variacao = preg_replace('/[^a-z_]/', '', strtolower((string)($salva['variacao'] ?? ''))) ?? '';
    $permitidas = array_merge(['natural'], v2_tts_variacoes($engine));
    if ($variacao === '') {
        /* Nada escolhido ainda: vale o ritmo do estudio (calor +30%). */
        $variacao = in_array('calor_rapido', $permitidas, true) ? 'calor_rapido' : 'natural';
    } elseif (!in_array($variacao, $permitidas, true)) {
        $variacao = 'natural';
    }
    return [
        'engine' => $engine,
        'voz' => $voz !== '' ? $voz : v2_voz_padrao($engine),
        'variacao' => $variacao,
        'vel' => (int)($salva['vel'] ?? 0),
        'tom' => (int)($salva['tom'] ?? 0),
        'salva' => $salva !== [],
    ];
}

function v2_voz_gravar(array $dados): bool
{
    $dir = dirname(v2_voz_arquivo());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return $json !== false && @file_put_contents(v2_voz_arquivo(), $json, LOCK_EX) !== false;
}

/** Aplica velocidade (%) e tom (Hz) por cima da variacao escolhida. */
function v2_voz_ajustar(string $engine, array $p, int $vel, int $tom): array
{
    $vel = max(-40, min(80, $vel));
    $tom = max(-20, min(20, $tom));
    $fator = 1 + $vel / 100;
    switch ($engine) {
        case 'edge':
            $p['rate'] = v2_voz_pct((int)($p['rate'] ?? 0) + $vel);
            $p['pitch'] = v2_voz_hz((int)($p['pitch'] ?? 0) + $tom);
            break;
        case 'windows':
            $p['rate'] = max(-10, min(10, (int)($p['rate'] ?? 0) + (int)round($vel / 10)));
            break;
        case 'piper':
            $p['length_scale'] = round(max(0.4, (float)($p['length_scale'] ?? 1.0)) / $fator, 3);
            break;
        case 'kokoro':
            $p['speed'] = round(max(0.4, (float)($p['speed'] ?? 1.0)) * $fator, 3);
            break;
    }
    return $p;
}

/* ---------- Mensagens do WhatsApp: rotulo e resumo ---------- */

/** Rotulo curto para cada tipo de mensagem (icone + nome). */
function v2_msg_rotulo(string $tipo, ?string $arquivo = null): string
{
    switch ($tipo) {
        case 'audio':
            return '🎙 áudio';
        case 'image':
            return '📷 imagem';
        case 'video':
            return '🎬 vídeo';
        case 'document':
            return '📄 ' . (($arquivo !== null && $arquivo !== '') ? $arquivo : 'documento');
        case 'sticker':
            return '🌟 figurinha';
    }
    return '';
}

/** Resumo de uma mensagem para a lista de conversas. */
function v2_msg_preview(?string $tipo, ?string $texto, ?string $transcricao): string
{
    $tipo = (string)$tipo;
    if ($tipo !== '' && $tipo !== 'texto') {
        $rot = v2_msg_rotulo($tipo);
        $extra = $tipo === 'audio' ? trim((string)$transcricao) : trim((string)$texto);
        $extra = preg_replace('/\s+/u', ' ', (string)$extra) ?? '';
        $extra = mb_substr($extra, 0, 70);
        return $rot . ($extra !== '' ? ' · ' . $extra : '');
    }
    $txt = preg_replace('/\s+/u', ' ', trim((string)$texto)) ?? '';
    return mb_substr($txt, 0, 90);
}

/** "ha 3 dias" a partir de uma data. */
function v2_desde(?string $data): string
{
    if ($data === null || trim($data) === '' || strtotime($data) === false) {
        return 'sem data';
    }
    $seg = time() - (int)strtotime($data);
    if ($seg < 3600) {
        return 'há ' . max(1, (int)round($seg / 60)) . ' min';
    }
    if ($seg < 86400) {
        return 'há ' . (int)round($seg / 3600) . 'h';
    }
    $dias = (int)round($seg / 86400);
    if ($dias < 30) {
        return 'há ' . $dias . ' dia' . ($dias > 1 ? 's' : '');
    }
    return date('d/m/Y', (int)strtotime($data));
}

/* ---------- Motor de IA local do simulador (LM Studio) ---------- */

/** Endereco da API local do LM Studio (OpenAI-compativel). */
function v2_lmstudio_url(): string
{
    $url = trim((string)(v2_config()['lmstudio_url'] ?? ''));
    if ($url === '') {
        $url = 'http://127.0.0.1:1234/v1';
    }
    return rtrim($url, '/');
}

/** GET simples em JSON com timeout curto (nunca derruba a tela). */
function v2_http_json(string $url, int $timeout = 4): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $corpo = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($corpo === false || $status !== 200) {
        return null;
    }
    $json = json_decode((string)$corpo, true);
    return is_array($json) ? $json : null;
}

/** Modelos carregados no LM Studio (ignora os de embedding). */
function v2_lmstudio_modelos(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $json = v2_http_json(v2_lmstudio_url() . '/models', 4);
    foreach (($json['data'] ?? []) as $m) {
        $id = trim((string)($m['id'] ?? ''));
        if ($id !== '' && stripos($id, 'embed') === false) {
            $cache[] = $id;
        }
    }
    return $cache;
}

/** Modelo do LM Studio preferido: o do config.local.php ou o primeiro carregado. */
function v2_lmstudio_modelo_preferido(): string
{
    $escolhido = trim((string)(v2_config()['lmstudio_model'] ?? ''));
    if ($escolhido !== '') {
        return $escolhido;
    }
    $modelos = v2_lmstudio_modelos();
    return $modelos[0] ?? '';
}

/* ---------- O "cerebro" da Irene: o mesmo modelo que roda o Codex ---------- */

/**
 * Modelo/provider que o Codex usa nesta maquina. A chave vem do ambiente
 * (GET do Apache) e nunca e escrita em arquivo, log ou tela.
 */
function v2_cerebro(): array
{
    $cfg = v2_config();
    $chave = trim((string)($cfg['cerebro_key'] ?? ''));
    if ($chave === '') {
        $chave = trim((string)(getenv('DEEPSEEK_CODEX_KEY') ?: ''));
    }
    $url = trim((string)($cfg['cerebro_url'] ?? ''));
    $modelo = trim((string)($cfg['cerebro_model'] ?? ''));
    return [
        'url' => rtrim($url !== '' ? $url : 'https://api.deepseek.com/v1', '/'),
        'key' => $chave,
        'modelo' => $modelo !== '' ? $modelo : 'deepseek-chat',
        'ligado' => $chave !== '',
    ];
}

/** Catalogo para o seletor da tela: cada item e "backend|modelo". */
function v2_irene_modelos(): array
{
    $lista = [];
    $preferido = v2_lmstudio_modelo_preferido();
    foreach (v2_lmstudio_modelos() as $m) {
        $lista['lmstudio|' . $m] = 'LM Studio · ' . $m . ($m === $preferido ? ' · padrão' : '');
    }
    $cerebro = v2_cerebro();
    if ($cerebro['ligado']) {
        $lista['cerebro|' . $cerebro['modelo']] = 'Meu cérebro (Codex) · ' . $cerebro['modelo'];
    }
    if (!$lista) {
        $lista['lmstudio|qwen/qwen3-8b'] = 'LM Studio · qwen/qwen3-8b (carregue um modelo no LM Studio)';
    }
    return $lista;
}
