<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$dataFile = __DIR__ . '/medicoes.json';
$message = '';
$messageType = 'success';

function pressao_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pressao_load_measurements(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $json = file_get_contents($file);
    if ($json === false) {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? array_values($decoded) : [];
}

function pressao_save_measurements(string $file, array $items): bool
{
    $json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($file, $json . PHP_EOL, LOCK_EX) !== false;
}

function pressao_generate_id(array $item): string
{
    $date = preg_replace('/\D/', '', (string)($item['date'] ?? ''));
    $time = preg_replace('/\D/', '', str_replace(':', '', (string)($item['time'] ?? '')));
    $suffix = bin2hex(random_bytes(3));

    return 'bp-' . ($date !== '' ? substr($date, 0, 8) : date('Ymd')) . '-' . ($time !== '' ? str_pad(substr($time, 0, 4), 4, '0') : '0000') . '-' . $suffix;
}

function pressao_normalize_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }

    return '';
}

function pressao_normalize_time(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return null;
    }

    return $value;
}

function pressao_normalize_measurement(array $input, ?string $id = null): array
{
    $date = pressao_normalize_date((string)($input['date'] ?? ''));
    $time = pressao_normalize_time((string)($input['time'] ?? ''));
    $systolic = (int)preg_replace('/\D/', '', (string)($input['systolic'] ?? ''));
    $diastolic = (int)preg_replace('/\D/', '', (string)($input['diastolic'] ?? ''));
    $pulse = (int)preg_replace('/\D/', '', (string)($input['pulse'] ?? ''));

    return [
        'id' => $id !== null && $id !== '' ? $id : pressao_generate_id([
            'date' => $date,
            'time' => $time,
        ]),
        'date' => $date,
        'time' => $time,
        'systolic' => $systolic,
        'diastolic' => $diastolic,
        'pulse' => $pulse,
    ];
}

function pressao_time_to_minutes(?string $value): int
{
    if (!is_string($value) || $value === '') {
        return 24 * 60;
    }

    if (!preg_match('/^(\d{2}):(\d{2})$/', $value, $matches)) {
        return 24 * 60;
    }

    return ((int)$matches[1] * 60) + (int)$matches[2];
}

function pressao_sort_measurements(array &$items): void
{
    usort($items, static function (array $a, array $b): int {
        $dateA = (string)($a['date'] ?? '');
        $dateB = (string)($b['date'] ?? '');
        if ($dateA !== $dateB) {
            return strcmp($dateA, $dateB);
        }

        $timeA = pressao_time_to_minutes($a['time'] ?? null);
        $timeB = pressao_time_to_minutes($b['time'] ?? null);
        if ($timeA !== $timeB) {
            return $timeA <=> $timeB;
        }

        return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
    });
}

function pressao_find_measurement_index(array $items, string $id): ?int
{
    foreach ($items as $index => $item) {
        if ((string)($item['id'] ?? '') === $id) {
            return $index;
        }
    }

    return null;
}

function pressao_format_date(string $value): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt) {
        return $value;
    }

    return $dt->format('d/m/Y');
}

function pressao_format_date_short(string $value): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt) {
        return $value;
    }

    return $dt->format('d/m/y');
}

function pressao_format_time(?string $value): string
{
    return is_string($value) && $value !== '' ? $value : '--:--';
}

function pressao_stats(array $items): array
{
    $count = count($items);
    $avgSys = 0.0;
    $avgDia = 0.0;
    $avgPulse = 0.0;
    $maxSys = null;
    $maxDia = null;
    $latest = $count > 0 ? $items[$count - 1] : null;

    if ($count === 0) {
        return [
            'count' => 0,
            'avgSys' => 0,
            'avgDia' => 0,
            'avgPulse' => 0,
            'maxSys' => null,
            'maxDia' => null,
            'latest' => null,
        ];
    }

    foreach ($items as $item) {
        $avgSys += (float)($item['systolic'] ?? 0);
        $avgDia += (float)($item['diastolic'] ?? 0);
        $avgPulse += (float)($item['pulse'] ?? 0);

        if ($maxSys === null || (int)$item['systolic'] > (int)$maxSys['systolic']) {
            $maxSys = $item;
        }

        if ($maxDia === null || (int)$item['diastolic'] > (int)$maxDia['diastolic']) {
            $maxDia = $item;
        }
    }

    return [
        'count' => $count,
        'avgSys' => $avgSys / $count,
        'avgDia' => $avgDia / $count,
        'avgPulse' => $avgPulse / $count,
        'maxSys' => $maxSys,
        'maxDia' => $maxDia,
        'latest' => $latest,
    ];
}

function pressao_format_decimal(float $value): string
{
    $formatted = number_format($value, 1, ',', '.');
    return str_replace(',0', '', $formatted);
}

$measurements = pressao_load_measurements($dataFile);
pressao_sort_measurements($measurements);
$stats = pressao_stats($measurements);
$editingId = trim((string)($_GET['edit'] ?? ''));
$editingItem = null;

if ($editingId !== '') {
    $index = pressao_find_measurement_index($measurements, $editingId);
    if ($index !== null) {
        $editingItem = $measurements[$index];
    } else {
        $editingId = '';
    }
}

$form = [
    'id' => $editingItem['id'] ?? '',
    'date' => $editingItem['date'] ?? '',
    'time' => $editingItem['time'] ?? '',
    'systolic' => $editingItem['systolic'] ?? '',
    'diastolic' => $editingItem['diastolic'] ?? '',
    'pulse' => $editingItem['pulse'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    $postedId = trim((string)($_POST['id'] ?? ''));
    $form = [
        'id' => $postedId,
        'date' => (string)($_POST['date'] ?? ''),
        'time' => (string)($_POST['time'] ?? ''),
        'systolic' => (string)($_POST['systolic'] ?? ''),
        'diastolic' => (string)($_POST['diastolic'] ?? ''),
        'pulse' => (string)($_POST['pulse'] ?? ''),
    ];

    if ($action === 'delete') {
        if ($postedId === '') {
            $message = 'Selecione uma medição para excluir.';
            $messageType = 'error';
        } else {
            $measurements = array_values(array_filter($measurements, static fn(array $item): bool => (string)($item['id'] ?? '') !== $postedId));
            if (!pressao_save_measurements($dataFile, $measurements)) {
                $message = 'Nao foi possivel salvar a exclusao no JSON.';
                $messageType = 'error';
            } else {
                header('Location: gerenciar.php?ok=deleted');
                exit;
            }
        }
    } else {
        $normalized = pressao_normalize_measurement($form, $postedId !== '' ? $postedId : null);
        $errors = [];

        if ($normalized['date'] === '') {
            $errors[] = 'Informe uma data valida.';
        }
        if ($normalized['systolic'] <= 0) {
            $errors[] = 'Informe a sistolica.';
        }
        if ($normalized['diastolic'] <= 0) {
            $errors[] = 'Informe a diastolica.';
        }
        if ($normalized['pulse'] <= 0) {
            $errors[] = 'Informe o pulso.';
        }

        if ($errors !== []) {
            $message = implode(' ', $errors);
            $messageType = 'error';
            $form = [
                'id' => $postedId,
                'date' => (string)($_POST['date'] ?? ''),
                'time' => (string)($_POST['time'] ?? ''),
                'systolic' => (string)($_POST['systolic'] ?? ''),
                'diastolic' => (string)($_POST['diastolic'] ?? ''),
                'pulse' => (string)($_POST['pulse'] ?? ''),
            ];
            $editingId = $postedId;
        } else {
            $replaced = false;
            foreach ($measurements as $index => $item) {
                if ((string)($item['id'] ?? '') === ($postedId !== '' ? $postedId : $normalized['id'])) {
                    $measurements[$index] = $normalized;
                    $replaced = true;
                    break;
                }
            }

            if (!$replaced) {
                $measurements[] = $normalized;
            }

            pressao_sort_measurements($measurements);

            if (!pressao_save_measurements($dataFile, $measurements)) {
                $message = 'Nao foi possivel salvar o JSON.';
                $messageType = 'error';
            } else {
                header('Location: gerenciar.php?ok=saved');
                exit;
            }
        }
    }
}

if (isset($_GET['ok'])) {
    if ($_GET['ok'] === 'saved') {
        $message = 'Medição salva com sucesso.';
    } elseif ($_GET['ok'] === 'deleted') {
        $message = 'Medição excluida com sucesso.';
    }
}

$summary = pressao_stats($measurements);
$latest = $summary['latest'];
$latestDate = $latest ? pressao_format_date((string)$latest['date']) : 'sem dados';
$latestTime = $latest ? pressao_format_time($latest['time'] ?? null) : '--:--';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
  <meta name="theme-color" content="#07101d" />
  <title>Gerenciar medições de pressão</title>
  <style>
    :root{--bg:#07101d;--card:#0d1929;--card2:#111f31;--text:#edf5ff;--muted:#92a7bd;--line:#20334a;--cyan:#50c8ff;--green:#60d394;--amber:#ffc857;--red:#ff6b6b;--violet:#a78bfa;--shadow:0 22px 70px rgba(0,0,0,.28)}
    *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 10% -5%,rgba(80,200,255,.17),transparent 27%),radial-gradient(circle at 92% 2%,rgba(167,139,250,.12),transparent 25%),var(--bg);color:var(--text);line-height:1.55}
    body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.015) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.015) 1px,transparent 1px);background-size:32px 32px;mask-image:linear-gradient(to bottom,black,transparent 75%)}
    .wrap{width:min(1240px,calc(100% - 28px));margin:auto;padding:28px 0 72px}
    .hero{padding:34px 0 24px;animation:rise .8s ease both;display:flex;flex-direction:column;gap:10px}
    .eyebrow{display:inline-flex;gap:8px;align-items:center;color:#b9dff2;background:rgba(80,200,255,.09);border:1px solid rgba(80,200,255,.22);padding:7px 11px;border-radius:999px;font-size:.78rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;width:fit-content}
    .pulse-dot{width:8px;height:8px;border-radius:50%;background:var(--green);box-shadow:0 0 0 0 rgba(96,211,148,.6);animation:pulse 2s infinite}
    .hero h1{margin:6px 0 0;font-size:clamp(2rem,7vw,4.2rem);line-height:.98;letter-spacing:-.055em;max-width:920px}
    .hero p{max-width:820px;color:var(--muted);font-size:clamp(1rem,2.5vw,1.08rem);margin:0}
    .hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
    .btn{appearance:none;border:1px solid rgba(255,255,255,.13);background:rgba(255,255,255,.055);color:var(--text);padding:10px 14px;border-radius:12px;font-weight:800;text-decoration:none;cursor:pointer;transition:.2s}
    .btn:hover{transform:translateY(-1px);background:rgba(80,200,255,.1);border-color:rgba(80,200,255,.3)}
    .btn.primary{background:linear-gradient(180deg,rgba(80,200,255,.22),rgba(80,200,255,.12));border-color:rgba(80,200,255,.38)}
    .grid{display:grid;gap:14px}
    .stats{grid-template-columns:repeat(2,1fr);margin:22px 0}
    .card{background:linear-gradient(180deg,rgba(17,31,49,.95),rgba(10,23,38,.95));border:1px solid rgba(255,255,255,.075);border-radius:20px;box-shadow:var(--shadow);overflow:hidden;position:relative}
    .card:after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(120deg,transparent 15%,rgba(255,255,255,.025),transparent 60%)}
    .stat{padding:18px;min-height:126px;animation:rise .75s ease both}
    .stat small{color:var(--muted);font-weight:700}
    .stat strong{display:block;font-size:clamp(1.5rem,5vw,2.2rem);letter-spacing:-.045em;margin-top:4px}
    .stat span{font-size:.78rem;color:#a8bed3}
    .good{color:var(--green)}.warn{color:var(--amber)}
    .layout{display:grid;gap:16px;grid-template-columns:1fr}
    .panel{padding:18px}
    .panel h2{margin:0 0 4px;font-size:1.1rem}
    .panel .sub{color:var(--muted);font-size:.88rem;margin-bottom:16px}
    .alert{padding:12px 14px;border-radius:14px;margin-bottom:14px;border:1px solid transparent}
    .alert.success{background:rgba(96,211,148,.1);border-color:rgba(96,211,148,.25);color:#c8f5d7}
    .alert.error{background:rgba(255,107,107,.1);border-color:rgba(255,107,107,.25);color:#ffd0d0}
    .form-grid{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}
    .field{display:flex;flex-direction:column;gap:8px}
    .field.full{grid-column:1 / -1}
    label{font-size:.78rem;letter-spacing:.05em;text-transform:uppercase;color:#bcd0e3;font-weight:800}
    input{width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:#0c1726;color:var(--text);padding:12px 13px;font:inherit;outline:none;transition:.2s}
    input:focus{border-color:rgba(80,200,255,.45);box-shadow:0 0 0 3px rgba(80,200,255,.1)}
    .form-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
    .table-wrap{overflow:auto;border-radius:14px;border:1px solid rgba(255,255,255,.06)}
    table{border-collapse:collapse;width:100%;min-width:760px;background:rgba(5,14,25,.35)}
    th,td{text-align:left;padding:12px 13px;border-bottom:1px solid rgba(255,255,255,.055);font-size:.88rem;vertical-align:top}
    th{position:sticky;top:0;background:#111f31;color:#bcd0e3;font-size:.73rem;text-transform:uppercase;letter-spacing:.055em}
    tbody tr:hover{background:rgba(80,200,255,.055)}
    .actions{display:flex;gap:8px;flex-wrap:wrap}
    .mini{padding:8px 10px;border-radius:10px;font-size:.78rem}
    .mini.danger{border-color:rgba(255,107,107,.3);background:rgba(255,107,107,.08)}
    .mini.danger:hover{background:rgba(255,107,107,.15)}
    .badge{display:inline-flex;align-items:center;padding:5px 8px;border-radius:999px;font-size:.72rem;font-weight:800}
    .badge-ok{background:rgba(96,211,148,.12);color:#8fe5b2}.badge-att{background:rgba(255,200,87,.11);color:#ffd778}.badge-high{background:rgba(255,107,107,.11);color:#ff9898}
    .footer{margin-top:22px;color:#71869a;font-size:.76rem;text-align:center}
    @keyframes rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
    @keyframes pulse{70%{box-shadow:0 0 0 9px rgba(96,211,148,0)}100%{box-shadow:0 0 0 0 rgba(96,211,148,0)}}
    @media(min-width:760px){.wrap{padding-top:36px}.stats{grid-template-columns:repeat(4,1fr)}.panel{padding:24px}.layout{grid-template-columns:1fr 1.15fr}.panel.wide{grid-column:1 / -1}}
    @media(max-width:720px){.form-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<main class="wrap">
  <header class="hero">
    <div class="eyebrow"><span class="pulse-dot"></span>Gestao do JSON de medições</div>
    <h1>Adicionar, editar e excluir medições sem mexer no HTML.</h1>
    <p>Esta pagina escreve direto em <strong>medicoes.json</strong>. A pagina publica consome o mesmo arquivo e reflete tudo depois do deploy.</p>
    <div class="hero-actions">
      <a class="btn primary" href="./">Abrir pagina publica</a>
      <span class="btn" style="cursor:default">JSON: <?= pressao_h(basename($dataFile)) ?></span>
    </div>
  </header>

  <section class="grid stats">
    <article class="card stat"><small>Total de medições</small><strong><?= (int)$summary['count'] ?></strong><span>itens registrados no JSON</span></article>
    <article class="card stat"><small>Média do período</small><strong><?= pressao_format_decimal((float)$summary['avgSys']) ?>/<?= pressao_format_decimal((float)$summary['avgDia']) ?></strong><span>mmHg</span></article>
    <article class="card stat"><small>Leitura mais recente</small><strong class="good"><?= $latest ? (int)$latest['systolic'] . '/' . (int)$latest['diastolic'] : '--/--' ?></strong><span><?= $latest ? pressao_h(pressao_format_date($latest['date'])) . ' · ' . pressao_h($latestTime) : 'sem dados' ?></span></article>
    <article class="card stat"><small>Maior sistolica</small><strong class="warn"><?= $summary['maxSys'] ? (int)$summary['maxSys']['systolic'] : '--' ?></strong><span><?= $summary['maxSys'] ? pressao_h(pressao_format_date($summary['maxSys']['date'])) : 'sem dados' ?></span></article>
  </section>

  <?php if ($message !== ''): ?>
    <div class="alert <?= pressao_h($messageType) ?>"><?= pressao_h($message) ?></div>
  <?php endif; ?>

  <section class="layout">
    <article class="card panel">
      <h2><?= $editingItem ? 'Editar medição' : 'Nova medição' ?></h2>
      <div class="sub">Preencha os campos e salve no JSON. Use a tabela abaixo para editar ou excluir registros existentes.</div>
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= pressao_h((string)$form['id']) ?>">
        <div class="form-grid">
          <div class="field">
            <label for="date">Data</label>
            <input id="date" name="date" type="date" value="<?= pressao_h((string)$form['date']) ?>" required>
          </div>
          <div class="field">
            <label for="time">Hora</label>
            <input id="time" name="time" type="time" value="<?= pressao_h((string)($form['time'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="systolic">Sistolica</label>
            <input id="systolic" name="systolic" type="number" min="1" step="1" value="<?= pressao_h((string)$form['systolic']) ?>" required>
          </div>
          <div class="field">
            <label for="diastolic">Diastolica</label>
            <input id="diastolic" name="diastolic" type="number" min="1" step="1" value="<?= pressao_h((string)$form['diastolic']) ?>" required>
          </div>
          <div class="field full">
            <label for="pulse">Pulso</label>
            <input id="pulse" name="pulse" type="number" min="1" step="1" value="<?= pressao_h((string)$form['pulse']) ?>" required>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn primary" type="submit"><?= $editingItem ? 'Salvar alteração' : 'Adicionar medição' ?></button>
          <a class="btn" href="gerenciar.php">Limpar</a>
        </div>
      </form>
    </article>

    <article class="card panel">
      <h2>Medições atuais</h2>
      <div class="sub">Clique em editar para carregar os campos acima. Excluir remove o item do arquivo JSON.</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Hora</th>
              <th>PA</th>
              <th>Pulso</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($measurements as $item): ?>
              <tr>
                <td><?= pressao_h(pressao_format_date_short((string)$item['date'])) ?></td>
                <td><?= pressao_h(pressao_format_time($item['time'] ?? null)) ?></td>
                <td><strong><?= (int)$item['systolic'] ?>/<?= (int)$item['diastolic'] ?></strong></td>
                <td><?= (int)$item['pulse'] ?> bpm</td>
                <td>
                  <div class="actions">
                    <a class="btn mini" href="gerenciar.php?edit=<?= pressao_h((string)$item['id']) ?>">Editar</a>
                    <form method="post" onsubmit="return confirm('Excluir esta medição?');" style="margin:0">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= pressao_h((string)$item['id']) ?>">
                      <button class="btn mini danger" type="submit">Excluir</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>

    <article class="card panel wide">
      <h2>Arquivo JSON</h2>
      <div class="sub">O arquivo abaixo e a origem unica dos dados exibidos na pagina publica.</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Arquivo</th>
              <th>Ultima leitura</th>
              <th>Quantidade</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= pressao_h(basename($dataFile)) ?></td>
              <td><?= $latest ? pressao_h(pressao_format_date((string)$latest['date'])) . ' · ' . pressao_h($latestTime) : 'sem dados' ?></td>
              <td><?= (int)$summary['count'] ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>

  <div class="footer">Gerenciamento local de medições via JSON. Depois de salvar, publique o push para atualizar o site.</div>
</main>
</body>
</html>
