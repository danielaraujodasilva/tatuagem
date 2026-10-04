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
        'conversa' => ['label' => 'Conversa', 'icon' => '💬'],
        'aprendizado' => ['label' => 'Aprendizado', 'icon' => '📚'],
        'rotina' => ['label' => 'Rotina', 'icon' => '🔁'],
        'voz' => ['label' => 'Voz', 'icon' => '🎤'],
    ];
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
<link rel="stylesheet" href="assets/app.css?v=2">
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
            'padrao' => ['rate' => '-4%', 'pitch' => '+2Hz', 'volume' => '+0%'],
            'variacoes' => [
                'calor' => ['rate' => '-9%', 'pitch' => '-3Hz'],
                'animada' => ['rate' => '+3%', 'pitch' => '+9Hz'],
                'serena' => ['rate' => '-13%', 'pitch' => '-6Hz'],
            ],
        ],
        'piper' => [
            'padrao' => ['length_scale' => 1.08, 'sentence_silence' => 0.32, 'noise_scale' => 0.667, 'noise_w' => 0.8],
            'variacoes' => [
                'calor' => ['length_scale' => 1.20, 'sentence_silence' => 0.48],
                'animada' => ['length_scale' => 0.97, 'sentence_silence' => 0.16],
                'serena' => ['length_scale' => 1.30, 'sentence_silence' => 0.55],
            ],
        ],
        'kokoro' => [
            'padrao' => ['speed' => 0.94, 'pausa_ms' => 260],
            'variacoes' => [
                'calor' => ['speed' => 0.86, 'pausa_ms' => 380],
                'animada' => ['speed' => 1.04, 'pausa_ms' => 150],
                'serena' => ['speed' => 0.80, 'pausa_ms' => 480],
            ],
        ],
        'windows' => [
            'padrao' => ['rate' => 0],
            'variacoes' => [
                'calor' => ['rate' => -2],
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
