<?php
declare(strict_types=1);

/*
 * hardware.php - inventario de hardware do computador que ACESSA a pagina.
 *
 * O navegador sozinho nao le CPU, memoria, disco nem numero de serie. Por isso a pagina tem
 * duas partes:
 *   1) o que o navegador entrega do PC visitante (JavaScript);
 *   2) o inventario completo, coletado por PowerShell NO PC visitante:
 *        irm "https://SEU-ENDERECO/hardware.php?ps=1" | iex
 *      ou baixando ?baixar=coletor. O resultado volta por POST para ?enviar=1 e fica
 *      visivel em qualquer aparelho em "Computadores que enviaram o inventario".
 *
 * JSON do(s) inventario(s) recebido(s): hardware.php?format=json   (&todos=1 para todos)
 * Relatorio de um envio especifico:     hardware.php?ver=ID
 * Limpar a lista:                       hardware.php?limpar=1
 *
 * Ajustes rapidos (topo do arquivo):
 *   HW_AUTH  -> 'none' (padrao, sem login), 'login' ou 'admin' (usa o auth do sistema, exige MySQL)
 *   HW_TOKEN -> vazio (padrao) = aberto na rede; se preenchido, exige ?k=VALOR na URL
 */

const HW_AUTH  = 'none'; // 'none' = sem login; 'login' / 'admin' = exige login do sistema (precisa do MySQL)
const HW_TOKEN = '';

// Opcional: crie hardware.local.php (fora do Git e bloqueado pelo .htaccess) com
// ['token' => 'seu-token'] e/ou ['auth' => 'login'] para sobrescrever os valores acima.
$hwToken = HW_TOKEN;
$hwAuth  = HW_AUTH;
$hwLocalConfig = __DIR__ . '/hardware.local.php';
if (is_file($hwLocalConfig)) {
    $hwLocalData = require $hwLocalConfig;
    if (is_array($hwLocalData)) {
        if (!empty($hwLocalData['token']) && is_string($hwLocalData['token'])) {
            $hwToken = $hwLocalData['token'];
        }
        if (!empty($hwLocalData['auth']) && is_string($hwLocalData['auth'])) {
            $hwAuth = $hwLocalData['auth'];
        }
    }
}

@set_time_limit(120);

$hwCli = (PHP_SAPI === 'cli');

$hwTokenOk = ($hwToken !== '' && hash_equals($hwToken, (string)($_GET['k'] ?? '')));

if (!$hwCli && !$hwTokenOk && ($hwAuth !== 'none' || $hwToken !== '')) {
    if ($hwAuth === 'login' || $hwAuth === 'admin') {
        require __DIR__ . '/auth/auth.php';
        if ($hwAuth === 'admin') {
            require_admin();
        } else {
            require_login();
        }
    } else {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Acesso restrito</title>'
           . '<body style="font:16px system-ui;background:#0e1116;color:#e6edf3;padding:32px">'
           . '<h1>Acesso restrito</h1><p>Esta pagina exige token. Abra a URL com <code>?k=SEU_TOKEN</code>'
           . ' (valor definido em <code>hardware.local.php</code> ou no HW_TOKEN do topo de hardware.php).</p></body>';
        exit;
    }
}

if ((string)($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    if (isset($_GET['download'])) {
        header('Content-Disposition: attachment; filename="hardware-pc-visitante-' . date('Ymd-His') . '.json"');
    }
    $hwRecebidosJson = hw_recebidos_read();
    if (!isset($_GET['todos'])) {
        $hwRecebidosJson = array_slice($hwRecebidosJson, 0, 1);
    }
    echo json_encode(['recebidos' => $hwRecebidosJson], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$hwIps = hw_ips_locais();

$hwHost    = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$hwPort    = (int)($_SERVER['SERVER_PORT'] ?? 80);
$hwScheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$hwProto   = strtolower(trim((string)explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
if ($hwProto === 'https' || $hwProto === 'http') {
    $hwScheme = $hwProto;
} elseif ($hwScheme === 'http' && !empty($_SERVER['HTTP_CF_VISITOR'])) {
    $cfVisitante = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
    if (is_array($cfVisitante) && ($cfVisitante['scheme'] ?? '') === 'https') {
        $hwScheme = 'https';
    }
}
$hwPortTag = ($hwPort !== 80 && $hwPort !== 443) ? ':' . $hwPort : '';
$hwArquivo = basename(__FILE__);
$hwUrlBase = ($hwToken !== '') ? '?k=' . rawurlencode($hwToken) : '';
$hwUrlJson = '?format=json' . ($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : '');
$hwUrlJsonDl = $hwUrlJson . '&download=1';
$hwUrlRefresh = '?refresh=1' . ($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : '');
$hwSelf = $hwScheme . '://' . $hwHost . $hwPortTag . (string)($_SERVER['SCRIPT_NAME'] ?? ('/' . $hwArquivo));

// ---- recebe o inventario enviado pelo coletor (roda no PC que acessa) ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_GET['enviar'])) {
    header('Content-Type: application/json; charset=utf-8');
    $corpo = (string)@file_get_contents('php://input');
    if ($corpo === '' || strlen($corpo) > 1048576) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensagem' => 'corpo vazio ou muito grande']);
        exit;
    }
    $enviado = json_decode($corpo, true);
    if (!is_array($enviado) || empty($enviado['secoes'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensagem' => 'inventario invalido']);
        exit;
    }
    $hwIdRelatorio = hw_recebidos_add($enviado, hw_ip_visitante());
    echo json_encode([
        'ok'       => true,
        'mensagem' => 'relatorio recebido',
        'id'       => $hwIdRelatorio,
        'url'      => $hwSelf . '?ver=' . $hwIdRelatorio . ($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : ''),
    ]);
    exit;
}

// ---- baixa o coletor (.bat) que roda o PowerShell no computador visitante ----
if ((string)($_GET['baixar'] ?? '') === 'coletor') {
    $hwPostUrl = $hwSelf . '?enviar=1' . ($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : '');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="Coleta-Hardware.bat"');
    echo hw_coletor_bat($hwPostUrl);
    exit;
}

// ---- entrega o coletor como script PowerShell (rodar direto no PC visitante: irm URL?ps=1 | iex) ----
if ((string)($_GET['ps'] ?? '') === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo hw_coletor_ps($hwSelf . '?enviar=1' . ($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : ''));
    exit;
}

if ((string)($_GET['limpar'] ?? '') === '1') {
    @unlink(hw_recebidos_file());
    header('Location: ' . $hwSelf . ($hwToken !== '' ? '?k=' . rawurlencode($hwToken) : ''));
    exit;
}

$hwRecebidos = hw_recebidos_read();

$hwVer = '';
$hwRelatorioVer = null;
if (isset($_GET['ver'])) {
    $hwVer = (string)preg_replace('/[^a-f0-9]/', '', (string)$_GET['ver']);
    foreach ($hwRecebidos as $hwRel) {
        if ($hwVer !== '' && ($hwRel['id'] ?? '') === $hwVer) { $hwRelatorioVer = $hwRel; break; }
    }
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Hardware do computador que esta acessando</title>
<style>
:root{--bg:#0e1116;--panel:#161b22;--line:#242c36;--txt:#e6edf3;--dim:#93a1b0;--acc:#4da3ff;--warn:#ffb86b}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--txt);font:15px/1.5 system-ui,"Segoe UI",Roboto,Arial,sans-serif}
a{color:var(--acc)}
header{padding:14px 20px;border-bottom:1px solid var(--line);background:#111721;display:flex;gap:14px;align-items:center;flex-wrap:wrap}
header h1{margin:0;font-size:17px}
header .meta{color:var(--dim);font-size:12px}
header .acoes{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}
.wrap{max-width:1400px;margin:0 auto;padding:18px 20px 40px}
.box{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:16px}
.box h2{margin:0 0 6px;font-size:12.5px;text-transform:uppercase;letter-spacing:.06em;color:var(--acc)}
.alert{border:1px solid #6b4b16;background:#241c0f;color:var(--warn);border-radius:12px;padding:12px 16px;font-size:13px;margin-bottom:16px}
.mono,code,pre{font-family:ui-monospace,Consolas,"Courier New",monospace}
pre{background:#0b0f14;border:1px solid var(--line);border-radius:8px;padding:10px 12px;overflow:auto;font-size:12.5px;margin:10px 0 0}
.chips{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.chip{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:8px 12px;min-width:150px}
.chip b{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--dim)}
.chip span{font-size:13px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:16px}
.card{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:14px 16px}
.card h2{margin:0 0 10px;font-size:12.5px;text-transform:uppercase;letter-spacing:.06em;color:var(--acc)}
dl{margin:0}
.row{display:flex;gap:12px;padding:6px 0;border-top:1px dashed #202834}
.row:first-child{border-top:0}
dt{flex:0 0 42%;color:var(--dim);font-size:13px}
dd{margin:0;flex:1;word-break:break-word;font-size:13px}
button{background:#1d2732;color:var(--txt);border:1px solid var(--line);border-radius:8px;padding:6px 12px;font-size:13px;cursor:pointer}
button:hover{border-color:var(--acc)}
footer{color:var(--dim);font-size:12px;max-width:1400px;margin:0 auto;padding:0 20px 30px}
@media(max-width:560px){dt{flex:0 0 100%}.row{flex-direction:column;gap:2px}}
</style>
</head>
<body>
<header>
  <h1>Hardware do computador que esta acessando esta pagina</h1>
  <div class="meta"><?= $hwRecebidos ? count($hwRecebidos) . ' computador(es) ja enviaram o inventario completo' : 'Nenhum inventario completo recebido ainda' ?></div>
  <div class="acoes">
    <button type="button" onclick="copyText(location.origin + location.pathname + '<?= hw_h($hwUrlJson) ?>')">Copiar link JSON</button>
    <button type="button" onclick="location.href='<?= hw_h($hwUrlJsonDl) ?>'">Baixar JSON</button>
    <button type="button" onclick="location.reload()">Atualizar</button>
  </div>
</header>
<main class="wrap">
<section class="box" id="este-dispositivo">
  <h2>Este computador (o que esta acessando a pagina)</h2>
  <p style="margin:0 0 10px;color:var(--dim);font-size:13px">
    O navegador <b>nao</b> entrega o modelo do processador, a memoria, os discos nem o numero de serie - nenhum site consegue ler isso.
    Abaixo esta tudo o que da para ler aqui; o inventario completo fica no bloco seguinte, rodando um comando no PowerShell <b>deste</b> computador.
  </p>
  <dl>
    <div class="row"><dt>Sistema / plataforma</dt><dd id="vis-plat">lendo...</dd></div>
    <div class="row"><dt>Arquitetura / bits</dt><dd id="vis-arq">-</dd></div>
    <div class="row"><dt>Nucleos de CPU (informado)</dt><dd id="vis-cores">-</dd></div>
    <div class="row"><dt>Memoria aproximada</dt><dd id="vis-mem">-</dd></div>
    <div class="row"><dt>Processador (modelo)</dt><dd id="vis-cpu">nao informado pelo navegador - veja o inventario completo abaixo</dd></div>
    <div class="row"><dt>Placa de video (WebGL)</dt><dd id="vis-gpu">-</dd></div>
    <div class="row"><dt>Placa de video (WebGPU)</dt><dd id="vis-gpu2">-</dd></div>
    <div class="row"><dt>Tela / densidade</dt><dd id="vis-tela">-</dd></div>
    <div class="row"><dt>Janela do navegador</dt><dd id="vis-janela">-</dd></div>
    <div class="row"><dt>Toque</dt><dd id="vis-touch">-</dd></div>
    <div class="row"><dt>Idioma(s) / fuso</dt><dd id="vis-lang">-</dd></div>
    <div class="row"><dt>Rede (estimativa)</dt><dd id="vis-rede">-</dd></div>
    <div class="row"><dt>Armazenamento disponivel ao site</dt><dd id="vis-armaz">-</dd></div>
    <div class="row"><dt>Cameras / microfones</dt><dd id="vis-midia">-</dd></div>
    <div class="row"><dt>Audio (placa de som)</dt><dd id="vis-audio">-</dd></div>
    <div class="row"><dt>Bateria / energia</dt><dd id="vis-bat">-</dd></div>
    <div class="row"><dt>Cookies / nao rastrear</dt><dd id="vis-cookie">-</dd></div>
    <div class="row"><dt>Navegador (versoes)</dt><dd id="vis-uaver">-</dd></div>
    <div class="row"><dt>Navegador</dt><dd id="vis-ua">-</dd></div>
    <div class="row"><dt>IP de origem</dt><dd><?= hw_h($_SERVER['REMOTE_ADDR'] ?? '-') ?></dd></div>
  </dl>
  <p style="margin:10px 0 0"><button type="button" onclick="copiarResumo()">Copiar resumo</button> <span id="vis-status" style="color:var(--dim);font-size:12px"></span></p>
</section>

<section class="box">
  <h2>Inventario completo deste computador</h2>
  <p style="margin:0 0 8px;font-size:13px">
    Rode <b>neste</b> computador (nao precisa de administrador). Ele coleta processador, memoria, placas, discos,
    rede e sistema <b>deste</b> PC, envia para esta pagina e abre o relatorio completo aqui mesmo.
  </p>
  <p style="margin:0"><a href="?baixar=coletor<?= hw_h($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : '') ?>"><button type="button">Baixar coletor (Coleta-Hardware.bat)</button></a></p>
  <p style="margin:12px 0 4px;font-size:13px">Ou rode direto no <b>PowerShell deste</b> computador, sem baixar arquivo:</p>
  <pre>irm "<?= hw_h($hwSelf) ?>?ps=1<?= hw_h($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : '') ?>" | iex</pre>
  <p style="margin:8px 0 0;color:var(--dim);font-size:12.5px">Esse comando le o hardware <b>deste</b> PC, envia para esta pagina e salva uma copia em <span class="mono">Desktop\hardware-NOMEDOPC.json</span>. Depois abra esta pagina em outro aparelho (celular) e veja o resultado em "Computadores que enviaram o inventario".</p>
  <p style="margin:8px 0 0;font-size:12.5px"><a href="?format=json&amp;recebidos=1<?= hw_h($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : '') ?>">Ver em JSON os inventarios enviados pelos PCs visitantes</a></p>
</section>

<?php if ($hwRelatorioVer): ?>
<section class="box" style="border-color:var(--acc)">
  <h2>Inventario completo deste computador - <?= hw_h((($hwRelatorioVer['nome'] ?? '') !== '' ? $hwRelatorioVer['nome'] : 'computador') . ((($hwRelatorioVer['maquina'] ?? '') !== '') ? ' (' . $hwRelatorioVer['maquina'] . ')' : '')) ?></h2>
  <p style="margin:0;color:var(--dim);font-size:12.5px">Recebido em <?= hw_h($hwRelatorioVer['recebido_em'] ?? '') ?> - este e o PC que rodou o coletor.</p>
  <?php hw_render_relatorio($hwRelatorioVer); ?>
</section>
<?php elseif ($hwVer !== ''): ?>
<section class="box">
  <h2>Inventario nao encontrado</h2>
  <p style="margin:0">O relatorio <span class="mono"><?= hw_h($hwVer) ?></span> nao esta mais na lista (talvez a lista tenha sido limpa).</p>
</section>
<?php endif; ?>

<?php if ($hwRecebidos): ?>
<section class="box">
  <h2>Computadores que enviaram o inventario (<?= count($hwRecebidos) ?>) <a href="?limpar=1<?= hw_h($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : '') ?>" style="font-size:12px;font-weight:400;float:right">limpar lista</a></h2>
  <?php foreach ($hwRecebidos as $rel): ?>
    <details style="margin:8px 0">
      <summary style="cursor:pointer"><?= hw_h((($rel['nome'] ?? '') !== '' ? $rel['nome'] : 'computador') . ((($rel['maquina'] ?? '') !== '') ? ' - ' . $rel['maquina'] : '') . ' - recebido em ' . ($rel['recebido_em'] ?? '') . ((($rel['ip'] ?? '') !== '') ? ' (IP ' . $rel['ip'] . ')' : '')) ?></summary>
      <?php hw_render_relatorio($rel); ?>
    </details>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<script>
(function () {
  function set(id, v) { var el = document.getElementById(id); if (el && v !== undefined && v !== null && v !== '') { el.textContent = v; } }
  var resumo = [];
  function add(rot, v) { if (v !== undefined && v !== null && v !== '') { resumo.push(rot + ': ' + v); } }
  window.copiarResumo = function () {
    var t = resumo.join('\n');
    if (navigator.clipboard) { navigator.clipboard.writeText(t); set('vis-status', 'resumo copiado'); } else { window.prompt('Copie o resumo:', t); }
  };

  var plat = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '';
  set('vis-plat', plat);
  add('Sistema', plat);

  if (navigator.userAgentData && navigator.userAgentData.getHighEntropyValues) {
    navigator.userAgentData.getHighEntropyValues(['platformVersion', 'architecture', 'bitness', 'model', 'uaFullVersion', 'fullVersionList']).then(function (h) {
      var arq = [h.architecture, h.bitness ? h.bitness + ' bits' : '', h.model].filter(Boolean).join(' / ');
      set('vis-arq', arq);
      add('Arquitetura', arq);
      var versoes = (h.fullVersionList || []).map(function (x) { return x.brand + ' ' + x.version; }).join(', ');
      if (versoes) { set('vis-uaver', versoes); add('Navegador (versoes)', versoes); }
      var completa = plat + (h.platformVersion ? ' ' + h.platformVersion : '') + (navigator.userAgentData.mobile ? ' (movel)' : '');
      set('vis-plat', completa);
      resumo[0] = 'Sistema: ' + completa;
    }).catch(function () { });
  }

  var cores = navigator.hardwareConcurrency || '';
  set('vis-cores', cores ? cores + ' nucleos logicos' : 'nao informado');
  add('Nucleos', cores);

  var mem = navigator.deviceMemory || '';
  set('vis-mem', mem ? mem + ' GB (aprox.)' : 'nao informado pelo navegador');
  add('Memoria aprox.', mem ? mem + ' GB' : '');

  try {
    var canvas = document.createElement('canvas');
    var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (gl) {
      var dbg = gl.getExtension('WEBGL_debug_renderer_info');
      var fornecedor = dbg ? gl.getParameter(dbg.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR);
      var modelo = dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER);
      var txtGpu = [modelo, fornecedor].filter(Boolean).join(' - ');
      set('vis-gpu', txtGpu);
      add('GPU', txtGpu);
    } else { set('vis-gpu', 'nao informado'); }
  } catch (e) { set('vis-gpu', 'nao informado'); }

  var tela = screen.width + ' x ' + screen.height + ' px' + (window.devicePixelRatio ? ' (' + window.devicePixelRatio + 'x)' : '') + (screen.colorDepth ? ' - ' + screen.colorDepth + ' bits' : '');
  set('vis-tela', tela);
  add('Tela', tela);

  set('vis-janela', window.innerWidth + ' x ' + window.innerHeight + ' px');
  window.addEventListener('resize', function () { set('vis-janela', window.innerWidth + ' x ' + window.innerHeight + ' px'); });

  var toque = (navigator.maxTouchPoints || 0) + ' ponto(s) de toque' + (('ontouchstart' in window) ? ' - tela sensivel' : '');
  set('vis-touch', toque);
  add('Toque', toque);

  var idiomas = (navigator.languages && navigator.languages.length) ? navigator.languages.join(', ') : (navigator.language || '');
  var fuso = '';
  try { fuso = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { }
  set('vis-lang', [idiomas, fuso].filter(Boolean).join(' - '));
  add('Idioma', idiomas);
  add('Fuso', fuso);

  var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  if (conn) {
    var rede = [conn.effectiveType, conn.downlink ? conn.downlink + ' Mbps' : '', conn.rtt ? conn.rtt + ' ms' : '', conn.saveData ? 'economia de dados' : ''].filter(Boolean).join(' - ');
    set('vis-rede', rede);
    add('Rede', rede);
  } else { set('vis-rede', 'nao informado'); }

  if (navigator.storage && navigator.storage.estimate) {
    navigator.storage.estimate().then(function (e) {
      if (e && e.quota) {
        var gb = Math.round(e.quota / 1073741824 * 10) / 10;
        set('vis-armaz', gb + ' GB que o navegador pode usar neste computador');
        add('Armazenamento', gb + ' GB');
      }
    }).catch(function () { });
  }

  if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
    navigator.mediaDevices.enumerateDevices().then(function (d) {
      var cam = 0, mic = 0, som = 0;
      d.forEach(function (x) {
        if (x.kind === 'videoinput') { cam++; } else if (x.kind === 'audioinput') { mic++; } else if (x.kind === 'audiooutput') { som++; }
      });
      var txt = cam + ' camera(s), ' + mic + ' microfone(s), ' + som + ' saida(s) de audio';
      set('vis-midia', txt);
      add('Cameras/microfones', txt);
    }).catch(function () { });
  }

  var cookies = 'cookies ' + (navigator.cookieEnabled ? 'habilitados' : 'bloqueados') + (navigator.doNotTrack === '1' ? ' - pedido para nao rastrear' : '');
  set('vis-cookie', cookies);
  add('Cookies', cookies);

  if (navigator.gpu && navigator.gpu.requestAdapter) {
    navigator.gpu.requestAdapter().then(function (ad) {
      if (!ad) { set('vis-gpu2', 'sem adaptador WebGPU'); return; }
      var i = ad.info || {};
      var txtGpu2 = [i.vendor, i.architecture, i.device, i.description].filter(Boolean).join(' - ');
      if (!txtGpu2) { txtGpu2 = 'adaptador WebGPU detectado'; }
      set('vis-gpu2', txtGpu2);
      add('GPU (WebGPU)', txtGpu2);
    }).catch(function () { });
  } else { set('vis-gpu2', 'nao disponivel neste navegador'); }

  try {
    var AC = window.AudioContext || window.webkitAudioContext;
    if (AC) {
      var ac = new AC();
      var som = ac.sampleRate + ' Hz' + ((ac.destination && ac.destination.maxChannelCount) ? ' - ' + ac.destination.maxChannelCount + ' canal(is)' : '') + ((typeof ac.baseLatency === 'number') ? ' - latencia ' + Math.round(ac.baseLatency * 1000) + ' ms' : '');
      set('vis-audio', som);
      add('Audio', som);
      if (ac.close) { ac.close(); }
    } else { set('vis-audio', 'nao disponivel neste navegador'); }
  } catch (e) { set('vis-audio', 'nao disponivel neste navegador'); }

  if (navigator.getBattery) {
    navigator.getBattery().then(function (b) {
      var bat = Math.round(b.level * 100) + '%' + (b.charging ? ' - carregando' : ' - na bateria');
      set('vis-bat', bat);
      add('Bateria', bat);
    }).catch(function () { });
  } else { set('vis-bat', 'nao informado (comum em desktop)'); }

  var orientacao = '';
  try { orientacao = (screen.orientation && screen.orientation.type) || ''; } catch (e) { }
  if (orientacao) { var telaFinal = tela + ' - ' + orientacao; set('vis-tela', telaFinal); }

  window.copyText = function (txt) {
    if (navigator.clipboard) { navigator.clipboard.writeText(txt); } else { window.prompt('Copie:', txt); }
  };

  set('vis-ua', navigator.userAgent);
  add('Navegador', navigator.userAgent);
})();
</script>
<div class="box">
  <h2>Acessar de outro dispositivo (celular, tablet, outro PC)</h2>
  <p style="margin:0 0 4px">Este PC precisa estar ligado e na mesma rede/Wi-Fi. Abra no outro aparelho:</p>
<?php if ($hwIps): ?>
<?php foreach ($hwIps as $ip): $hwUrl = $hwScheme . '://' . $ip . $hwPortTag . '/' . $hwArquivo . $hwUrlBase; ?>
  <div class="mono" style="margin:4px 0"><a href="<?= hw_h($hwUrl) ?>"><?= hw_h($hwUrl) ?></a></div>
<?php endforeach; ?>
<?php else: ?>
  <p style="margin:4px 0;color:var(--dim)">Nenhum IP de rede local foi detectado. Veja o IP com <span class="mono">ipconfig</span> e use <span class="mono">http://SEU-IP/<?= hw_h($hwArquivo) ?></span>.</p>
<?php endif; ?>
  <pre># No outro aparelho, abra esta pagina e veja "Computadores que enviaram o inventario".

# Se quiser salvar o ultimo inventario recebido em JSON:
Invoke-WebRequest "<?= hw_h($hwSelf . $hwUrlJson . ($hwToken !== '' ? '&k=' . rawurlencode($hwToken) : '')) ?>" -OutFile "$env:USERPROFILE\Desktop\hardware.json" -SkipCertificateCheck</pre>
</div>
</main>
<footer>Esta pagina mostra apenas o hardware do computador que a acessa. Limite da lista: os 25 inventarios mais recentes<?php if ($hwAuth === 'none' && $hwToken === ''): ?> - <b>sem protecao:</b> qualquer um na mesma rede ve estes dados (use HW_TOKEN ou HW_AUTH = 'login').<?php endif; ?></footer>
</body>
</html>
<?php
function hw_h($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hw_ip_visitante(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $chave) {
        $bruto = trim((string)($_SERVER[$chave] ?? ''));
        if ($bruto === '') { continue; }
        $primeiro = trim((string)explode(',', $bruto)[0]);
        if ($primeiro !== '') { return $primeiro; }
    }
    return '';
}

function hw_ips_locais(): array
{
    $host = gethostname();
    if ($host === false || $host === '') { return []; }
    $lista = @gethostbynamel((string)$host);
    if (!is_array($lista)) { return []; }

    $ips = [];
    foreach ($lista as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) { continue; }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) { continue; }
        $ips[] = (string)$ip;
    }
    return array_values(array_unique($ips));
}

function hw_render_relatorio(array $rel): void
{
    echo '<div class="grid" style="margin-top:10px">';
    foreach (($rel['secoes'] ?? []) as $sec) {
        echo '<section class="card"><h2>' . hw_h($sec['titulo'] ?? '') . '</h2><dl>';
        foreach (($sec['itens'] ?? []) as $it) {
            echo '<div class="row"><dt>' . hw_h($it['rotulo'] ?? '') . '</dt><dd>' . hw_h($it['valor'] ?? '') . '</dd></div>';
        }
        echo '</dl></section>';
    }
    echo '</div>';
}

function hw_recebidos_file(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hw_recebidos_' . md5(__DIR__) . '.json';
}

function hw_recebidos_read(): array
{
    $arquivo = hw_recebidos_file();
    if (!is_file($arquivo)) { return []; }
    $bruto = (string)@file_get_contents($arquivo);
    if ($bruto === '') { return []; }
    $lista = json_decode($bruto, true);
    return is_array($lista) ? $lista : [];
}

function hw_recebidos_add(array $dados, string $ip): string
{
    $nome = '';
    $maquina = '';
    foreach (($dados['secoes'] ?? []) as $secao) {
        if (!is_array($secao) || ($secao['id'] ?? '') !== 'computador') { continue; }
        foreach (($secao['itens'] ?? []) as $item) {
            $rotulo = (string)($item['rotulo'] ?? '');
            if ($rotulo === 'Nome do computador') { $nome = (string)($item['valor'] ?? ''); }
            if ($rotulo === 'Modelo') { $maquina = (string)($item['valor'] ?? ''); }
        }
    }

    $lista = hw_recebidos_read();
    $id = bin2hex(random_bytes(5));
    array_unshift($lista, [
        'id'          => $id,
        'recebido_em' => date('d/m/Y H:i:s'),
        'ip'          => $ip,
        'nome'        => $nome,
        'maquina'     => $maquina,
        'coletado_em' => (string)($dados['coletado_em'] ?? ''),
        'fonte'       => (string)($dados['fonte'] ?? ''),
        'secoes'      => is_array($dados['secoes'] ?? null) ? $dados['secoes'] : [],
    ]);

    @file_put_contents(
        hw_recebidos_file(),
        json_encode(array_slice($lista, 0, 25), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    return $id;
}

function hw_coletor_bat(string $urlPost): string
{
    $paramBloco = "param(\r\n    [Parameter(Mandatory = \$true)][string]\$Out\r\n)";
    $semParam   = '$Out = Join-Path $env:TEMP ("hardware_" + [guid]::NewGuid().ToString("N") + ".json")';
    $script     = str_replace($paramBloco, $semParam, hw_ps_script());
    $script    .= "\r\n" . str_replace('__URL__', $urlPost, hw_coletor_tail());

    $cabeca = <<<'BAT'
@echo off
chcp 65001 >nul
setlocal EnableExtensions
title Coleta de Hardware - %COMPUTERNAME%

set "PSFILE=%TEMP%\coleta_hw_%RANDOM%%RANDOM%.ps1"
powershell -NoProfile -Command "$c=Get-Content -LiteralPath '%~f0' -Raw; $i=$c.IndexOf([char]10+'#:PSSTART'); $f=$c.IndexOf([char]10+'#:PSEND'); if($i -lt 0 -or $f -lt 0){exit 2}; Set-Content -LiteralPath '%PSFILE%' -Value $c.Substring($i+10, $f-$i-10) -Encoding UTF8"
if errorlevel 1 (
  echo Falha ao extrair o script interno.
  pause
  exit /b 1
)

echo Coletando o hardware deste computador. Aguarde...
powershell -NoProfile -ExecutionPolicy Bypass -File "%PSFILE%"
set "RC=%ERRORLEVEL%"
del "%PSFILE%" >nul 2>&1

echo.
echo Finalizado. Volte na pagina de hardware e clique em Atualizar.
pause
exit /b %RC%

#:PSSTART
BAT;

    return $cabeca . $script . "\r\n#:PSEND\r\n";
}

function hw_coletor_ps(string $urlPost): string
{
    $paramBloco = "param(\r\n    [Parameter(Mandatory = \$true)][string]\$Out\r\n)";
    $semParam   = '$Out = Join-Path $env:TEMP ("hardware_" + $env:COMPUTERNAME + ".json")';
    $script     = str_replace($paramBloco, $semParam, hw_ps_script());
    return $script . "\r\n" . str_replace('__URL__', $urlPost, hw_coletor_tail());
}

function hw_coletor_tail(): string
{
    return <<<'PSU'

# ---------- envia o inventario deste computador para a pagina ----------
$ErrorActionPreference = 'Stop'
$urlEnvio = '__URL__'
if ($urlEnvio -like 'http://*' -and $urlEnvio -notmatch '^http://(localhost|127\.|\[::1\]|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)') {
    $urlEnvio = 'https://' + $urlEnvio.Substring(7)
}
try {
    $params = @{
        Uri         = $urlEnvio
        Method      = 'Post'
        InFile      = $Out
        ContentType = 'application/json; charset=utf-8'
        TimeoutSec  = 180
    }
    if ($PSVersionTable.PSVersion.Major -ge 6) {
        $params['SkipCertificateCheck'] = $true
    } else {
        [Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
    }

    $resposta = Invoke-RestMethod @params
    if (-not $resposta.ok) { throw 'o servidor nao confirmou o recebimento do inventario' }
    Write-Host ''
    $salvo = Join-Path ([Environment]::GetFolderPath('Desktop')) ('hardware_' + $env:COMPUTERNAME + '.json')
    Copy-Item -LiteralPath $Out -Destination $salvo -Force -ErrorAction SilentlyContinue
    Write-Host ('OK - ' + [string]$resposta.mensagem + '  (computador ' + $env:COMPUTERNAME + ')') -ForegroundColor Green
    Write-Host ('Inventario salvo em: ' + $salvo) -ForegroundColor Green
    if ($resposta.url) {
        Write-Host 'Abrindo o relatorio completo no navegador deste computador...' -ForegroundColor Green
        try { Start-Process $resposta.url } catch { }
    }
    Write-Host 'Em outro aparelho, abra a pagina de hardware para ver este computador na lista.' -ForegroundColor Green
    Remove-Item -LiteralPath $Out -Force -ErrorAction SilentlyContinue
} catch {
    $salvo = Join-Path ([Environment]::GetFolderPath('Desktop')) ('hardware_' + $env:COMPUTERNAME + '.json')
    Copy-Item -LiteralPath $Out -Destination $salvo -Force -ErrorAction SilentlyContinue
    Write-Host ''
    Write-Host ('Nao foi possivel enviar: ' + $_.Exception.Message) -ForegroundColor Yellow
    Write-Host ('O inventario foi salvo em: ' + $salvo) -ForegroundColor Yellow
    Write-Host 'Rode de novo com o computador do site ligado.' -ForegroundColor Yellow
}
PSU;
}

function hw_ps_script(): string
{
    return <<<'PSS'
param(
    [Parameter(Mandatory = $true)][string]$Out
)

$ErrorActionPreference = 'SilentlyContinue'
$ProgressPreference    = 'SilentlyContinue'

function Q {
    param([string]$Class, [string]$Filter = '', [string]$Namespace = 'root\cimv2')
    if ($Filter) {
        return Get-CimInstance -Namespace $Namespace -ClassName $Class -Filter $Filter -ErrorAction SilentlyContinue
    }
    return Get-CimInstance -Namespace $Namespace -ClassName $Class -ErrorAction SilentlyContinue
}

function One {
    param($Value)
    return @($Value) | Select-Object -First 1
}

function FmtSize {
    param($Value)
    if ($null -eq $Value) { return '' }
    $b = [double]$Value
    if ($b -le 0) { return '' }
    if ($b -ge 1TB) { return ('{0:N2} TB' -f ($b / 1TB)) }
    if ($b -ge 1GB) { return ('{0:N2} GB' -f ($b / 1GB)) }
    if ($b -ge 1MB) { return ('{0:N0} MB' -f ($b / 1MB)) }
    return ('{0:N0} KB' -f ($b / 1KB))
}

function FmtFromKB {
    param($Value)
    if ($null -eq $Value) { return '' }
    $k = [double]$Value
    if ($k -le 0) { return '' }
    return ('{0:N2} GB' -f ($k / 1MB))
}

function FmtClock {
    param($Value)
    if ($null -eq $Value) { return '' }
    $m = [double]$Value
    if ($m -le 0) { return '' }
    return ('{0:0} MHz ({1:N2} GHz)' -f $m, ($m / 1000))
}

function FmtCache {
    param($Value)
    if ($null -eq $Value) { return '' }
    $k = [double]$Value
    if ($k -le 0) { return '' }
    if ($k -ge 1024) { return ('{0:N1} MB' -f ($k / 1024)) }
    return ('{0:N0} KB' -f $k)
}

function FmtBits {
    param($Value)
    if ($null -eq $Value) { return '' }
    $v = [int]$Value
    if ($v -le 0) { return '' }
    return ("$v bits")
}

function FmtBool {
    param($Value)
    if ($null -eq $Value) { return '' }
    if ($Value -eq $true) { return 'Sim' }
    if ($Value -eq $false) { return 'Nao' }
    return ''
}

function FmtDate {
    param($Value)
    if ($null -eq $Value) { return '' }
    try { return ([datetime]$Value).ToString('dd/MM/yyyy HH:mm') } catch { return '' }
}

function Lbl {
    param([string]$Prefixo, $Valor)
    $v = ([string]$Valor).Trim()
    if ([string]::IsNullOrWhiteSpace($v)) { return '' }
    return ($Prefixo + ' ' + $v)
}

function Item {
    param([string]$Rotulo, $Valor)
    $v = ([string]$Valor).Trim()
    if ([string]::IsNullOrWhiteSpace($v)) { return $null }
    return [ordered]@{ rotulo = $Rotulo; valor = $v }
}

function Join-Parts {
    param($Parts)
    $out = @()
    foreach ($p in @($Parts)) {
        $s = ([string]$p).Trim()
        if (-not [string]::IsNullOrWhiteSpace($s)) { $out += $s }
    }
    return ($out -join ' - ')
}

$secoes = New-Object System.Collections.ArrayList
$resumo = New-Object System.Collections.ArrayList

function Add-Sec {
    param([string]$Id, [string]$Titulo, $Itens)
    $lista = New-Object System.Collections.ArrayList
    foreach ($i in @($Itens)) {
        if ($null -eq $i) { continue }
        [void]$lista.Add($i)
    }
    if ($lista.Count -gt 0) {
        [void]$secoes.Add([ordered]@{ id = $Id; titulo = $Titulo; itens = @($lista) })
    }
}

function Add-Resumo {
    param([string]$Rotulo, $Valor)
    $v = ([string]$Valor).Trim()
    if ([string]::IsNullOrWhiteSpace($v)) { return }
    [void]$resumo.Add([ordered]@{ rotulo = $Rotulo; valor = $v })
}

$cs      = One (Q 'Win32_ComputerSystem')
$csp     = One (Q 'Win32_ComputerSystemProduct')
$os      = One (Q 'Win32_OperatingSystem')
$bios    = One (Q 'Win32_BIOS')
$bb      = One (Q 'Win32_BaseBoard')
$enc     = One (Q 'Win32_SystemEnclosure')
$marr    = One (Q 'Win32_PhysicalMemoryArray')
$cpus    = @(Q 'Win32_Processor')
$gpus    = @(Q 'Win32_VideoController')
$mems    = @(Q 'Win32_PhysicalMemory')
$hdds    = @(Q 'Win32_DiskDrive')
$vols    = @(Q 'Win32_LogicalDisk' 'DriveType=3')
$nics    = @(Q 'Win32_NetworkAdapter' 'PhysicalAdapter=True')
$cfgs    = @(Q 'Win32_NetworkAdapterConfiguration' 'IPEnabled=True')
$mons    = @(Q 'Win32_DesktopMonitor')
$snds    = @(Q 'Win32_SoundDevice')
$bats    = @(Q 'Win32_Battery')
$pdisks  = @(Get-PhysicalDisk -ErrorAction SilentlyContinue)
$wmiMons = @(Get-CimInstance -Namespace 'root\wmi' -ClassName WmiMonitorID -ErrorAction SilentlyContinue)
$fws     = @(Get-NetFirewallProfile -ErrorAction SilentlyContinue)
$avs     = @(Get-CimInstance -Namespace 'root\SecurityCenter2' -ClassName AntiVirusProduct -ErrorAction SilentlyContinue)

$ddrMap     = @{ 18 = 'DDR'; 19 = 'DDR2'; 20 = 'DDR'; 21 = 'DDR2'; 22 = 'DDR2 FB-DIMM'; 24 = 'DDR3'; 26 = 'DDR4'; 27 = 'DDR4'; 28 = 'DDR4'; 29 = 'DDR4'; 30 = 'LPDDR4'; 31 = 'LPDDR4'; 32 = 'DDR5'; 33 = 'DDR5'; 34 = 'DDR5'; 35 = 'LPDDR5' }
$formMap    = @{ 3 = 'SIMM'; 4 = 'SIP'; 5 = 'Chip'; 6 = 'DIP'; 7 = 'Zip'; 8 = 'DIMM'; 9 = 'TSOP'; 10 = 'PGA'; 11 = 'RIMM'; 12 = 'SODIMM'; 13 = 'SRIMM'; 15 = 'SSMP'; 21 = 'BGA'; 23 = 'LGA' }
$netMap     = @{ 0 = 'Desconectado'; 1 = 'Conectando'; 2 = 'Conectado'; 3 = 'Desconectando'; 4 = 'Hardware ausente'; 5 = 'Hardware desabilitado'; 6 = 'Problema de hardware'; 7 = 'Midia desconectada'; 8 = 'Autenticando'; 9 = 'Autenticado'; 10 = 'Falha na autenticacao'; 11 = 'Endereco invalido'; 12 = 'Credenciais necessarias' }
$chassisMap = @{ 1 = 'Outro'; 2 = 'Desconhecido'; 3 = 'Desktop'; 4 = 'Desktop (low profile)'; 5 = 'Pizza Box'; 6 = 'Mini Torre'; 7 = 'Torre'; 8 = 'Portatil'; 9 = 'Notebook'; 10 = 'Notebook'; 11 = 'Hand Held'; 12 = 'Docking Station'; 13 = 'All in One'; 14 = 'Sub Notebook'; 15 = 'Space-Saving'; 16 = 'Lunch Box'; 17 = 'Chassi principal'; 18 = 'Expansao'; 19 = 'Sub-chassi'; 20 = 'Barramento'; 21 = 'Periferico'; 22 = 'Armazenamento'; 23 = 'Rack'; 24 = 'Selado'; 30 = 'Tablet'; 31 = 'Conversivel'; 32 = 'Destacavel'; 35 = 'Mini PC'; 36 = 'Stick PC' }
$batMap     = @{ 1 = 'Descarregando'; 2 = 'Conectado a energia (AC)'; 3 = 'Carregando'; 4 = 'Bateria baixa'; 5 = 'Bateria critica'; 6 = 'Carregando'; 7 = 'Carregando'; 8 = 'Carregando'; 9 = 'Carregando'; 10 = 'Indefinido'; 11 = 'Parcialmente carregada' }

function TipoMidia {
    param($Size)
    if (-not $Size) { return '' }
    foreach ($pd in $pdisks) {
        if (-not $pd.Size) { continue }
        $s = [double]$pd.Size
        if ($s -le 0) { continue }
        if (([math]::Abs($s - [double]$Size) / $s) -lt 0.02) {
            $mt = [string]$pd.MediaType
            if ($mt -eq 'Unspecified' -or $mt -eq '0') { return '' }
            return $mt
        }
    }
    return ''
}

function Barramento {
    param($Size)
    if (-not $Size) { return '' }
    foreach ($pd in $pdisks) {
        if (-not $pd.Size) { continue }
        $s = [double]$pd.Size
        if ($s -le 0) { continue }
        if (([math]::Abs($s - [double]$Size) / $s) -lt 0.02) {
            $bt = [string]$pd.BusType
            if ($bt -eq '0' -or $bt -eq 'Unknown') { return '' }
            return $bt
        }
    }
    return ''
}

$uptimeTexto = ''
if ($os -and $os.LastBootUpTime) {
    $u = (Get-Date) - [datetime]$os.LastBootUpTime
    $uptimeTexto = ('{0} dia(s), {1} h {2} min' -f $u.Days, $u.Hours, $u.Minutes)
}

$chassisTexto = ''
if ($enc -and $enc.ChassisTypes) {
    $partes = @()
    foreach ($ct in @($enc.ChassisTypes)) {
        $cti = [int]$ct
        if ($chassisMap[$cti]) { $partes += $chassisMap[$cti] } else { $partes += ("Tipo " + $cti) }
    }
    $chassisTexto = ($partes -join ' / ')
}

$ipsLocal = New-Object System.Collections.ArrayList

$hdTotal = 0
foreach ($h in $hdds) {
    if ($h.Size) { $hdTotal += [double]$h.Size }
}

Add-Resumo 'Processador' ([string](One $cpus).Name)
Add-Resumo 'Memoria RAM' (FmtSize $cs.TotalPhysicalMemory)
Add-Resumo 'Armazenamento' (FmtSize $hdTotal)
Add-Resumo 'Placa de video' ([string](One $gpus).Name)
Add-Resumo 'Sistema' ([string]$os.Caption)
Add-Resumo 'Maquina' (Join-Parts @($cs.Manufacturer, $cs.Model))

Add-Sec 'computador' 'Computador' @(
    (Item 'Nome do computador' $env:COMPUTERNAME)
    (Item 'Usuario conectado' ($env:USERDOMAIN + '\' + $env:USERNAME))
    (Item 'Fabricante' $cs.Manufacturer)
    (Item 'Modelo' $cs.Model)
    (Item 'Tipo de maquina' $chassisTexto)
    (Item 'Dominio / grupo de trabalho' $cs.Domain)
    (Item 'Nome DNS' $cs.DNSHostName)
    (Item 'Numero de serie do computador' $bios.SerialNumber)
    (Item 'UUID do sistema' $csp.UUID)
    (Item 'Identificacao do produto' (Join-Parts @($csp.IdentifyingNumber, $csp.Version)))
)

Add-Sec 'so' 'Sistema operacional' @(
    (Item 'Nome' $os.Caption)
    (Item 'Versao' $os.Version)
    (Item 'Build' $os.BuildNumber)
    (Item 'Arquitetura' $os.OSArchitecture)
    (Item 'Idioma do sistema' $os.OSLanguage)
    (Item 'Pasta do Windows' $os.WindowsDirectory)
    (Item 'Instalado em' (FmtDate $os.InstallDate))
    (Item 'Ultima inicializacao' (FmtDate $os.LastBootUpTime))
    (Item 'Tempo ligado' $uptimeTexto)
    (Item 'Usuario registrado' $os.RegisteredUser)
    (Item 'Disco de boot' $os.BootDevice)
)

$itensCpu = New-Object System.Collections.ArrayList
$nCpu = 0
foreach ($c in $cpus) {
    $nCpu++
    $p = "CPU $nCpu"
    [void]$itensCpu.Add((Item ($p + ' - modelo') $c.Name))
    [void]$itensCpu.Add((Item ($p + ' - fabricante') $c.Manufacturer))
    [void]$itensCpu.Add((Item ($p + ' - arquitetura') (FmtBits $c.AddressWidth)))
    [void]$itensCpu.Add((Item ($p + ' - nucleos fisicos') $c.NumberOfCores))
    [void]$itensCpu.Add((Item ($p + ' - nucleos logicos (threads)') $c.NumberOfLogicalProcessors))
    [void]$itensCpu.Add((Item ($p + ' - clock atual') (FmtClock $c.CurrentClockSpeed)))
    [void]$itensCpu.Add((Item ($p + ' - clock maximo') (FmtClock $c.MaxClockSpeed)))
    [void]$itensCpu.Add((Item ($p + ' - cache L2') (FmtCache $c.L2CacheSize)))
    [void]$itensCpu.Add((Item ($p + ' - cache L3') (FmtCache $c.L3CacheSize)))
    [void]$itensCpu.Add((Item ($p + ' - socket') $c.SocketDesignation))
    [void]$itensCpu.Add((Item ($p + ' - stepping') $c.Stepping))
    [void]$itensCpu.Add((Item ($p + ' - virtualizacao habilitada na BIOS') (FmtBool $c.VirtualizationFirmwareEnabled)))
    [void]$itensCpu.Add((Item ($p + ' - SLAT (VT-x / AMD-V)') (FmtBool $c.SecondLevelAddressTranslationExtensions)))
    [void]$itensCpu.Add((Item ($p + ' - status') $c.Status))
}
Add-Sec 'cpu' 'Processador' $itensCpu

$itensRam = New-Object System.Collections.ArrayList
[void]$itensRam.Add((Item 'Total instalado' (FmtSize $cs.TotalPhysicalMemory)))
[void]$itensRam.Add((Item 'Memoria fisica livre agora' (FmtFromKB $os.FreePhysicalMemory)))
[void]$itensRam.Add((Item 'Memoria virtual total' (FmtFromKB $os.TotalVirtualMemorySize)))
[void]$itensRam.Add((Item 'Memoria virtual livre' (FmtFromKB $os.FreeVirtualMemory)))
$slotsTexto = [string]$mems.Count
if ($marr -and $marr.MemoryDevices) { $slotsTexto = ("" + $mems.Count + ' de ' + $marr.MemoryDevices + ' slots') }
[void]$itensRam.Add((Item 'Pentes instalados' $slotsTexto))
$nMem = 0
foreach ($m in $mems) {
    $nMem++
    $tipo = ''
    if ($m.SMBIOSMemoryType -and $ddrMap[[int]$m.SMBIOSMemoryType]) { $tipo = $ddrMap[[int]$m.SMBIOSMemoryType] }
    $forma = ''
    if ($m.FormFactor -and $formMap[[int]$m.FormFactor]) { $forma = $formMap[[int]$m.FormFactor] }
    $vel = ''
    if ($m.Speed) { $vel = ([string]$m.Speed + ' MHz') }
    $velReal = ''
    if ($m.ConfiguredClockSpeed -and $m.ConfiguredClockSpeed -ne $m.Speed) { $velReal = ('rodando a {0} MHz' -f $m.ConfiguredClockSpeed) }
    $texto = Join-Parts @((FmtSize $m.Capacity), $tipo, $forma, $vel, $velReal, $m.Manufacturer, $m.PartNumber)
    $rot = "Pente $nMem"
    if ($m.DeviceLocator) { $rot = "Pente $nMem ($($m.DeviceLocator))" }
    [void]$itensRam.Add((Item $rot $texto))
}
Add-Sec 'ram' 'Memoria RAM' $itensRam

Add-Sec 'placa' 'Placa-mae e BIOS' @(
    (Item 'Fabricante da placa-mae' $bb.Manufacturer)
    (Item 'Modelo da placa-mae' $bb.Product)
    (Item 'Versao da placa-mae' $bb.Version)
    (Item 'Numero de serie da placa-mae' $bb.SerialNumber)
    (Item 'Fabricante da BIOS' $bios.Manufacturer)
    (Item 'Versao da BIOS' $bios.SMBIOSBIOSVersion)
    (Item 'Data da BIOS' (FmtDate $bios.ReleaseDate))
    (Item 'Versao do SMBIOS' $bios.SMBIOSMajorVersion)
)

$itensDisco = New-Object System.Collections.ArrayList
$nDisco = 0
foreach ($h in $hdds) {
    $nDisco++
    $rot = "Disco $nDisco"
    if ($h.Model) { $rot = "Disco $nDisco ($($h.Model))" }
    $texto = Join-Parts @(
        (FmtSize $h.Size),
        (TipoMidia $h.Size),
        (Barramento $h.Size),
        (Lbl 'Interface WMI:' $h.InterfaceType),
        (Lbl 'Serial:' $h.SerialNumber),
        (Lbl 'Firmware:' $h.FirmwareRevision),
        (Lbl 'Particoes:' $h.Partitions),
        (Lbl 'Setores:' $h.TotalSectors)
    )
    [void]$itensDisco.Add((Item $rot $texto))
}
Add-Sec 'discos' 'Discos fisicos' $itensDisco

$itensVol = New-Object System.Collections.ArrayList
foreach ($v in $vols) {
    $pct = ''
    if ($v.Size -and [double]$v.Size -gt 0) { $pct = ('{0:N0}% livre' -f (([double]$v.FreeSpace / [double]$v.Size) * 100)) }
    $rot = "Unidade $($v.DeviceID)"
    if ($v.VolumeName) { $rot = "Unidade $($v.DeviceID) - $($v.VolumeName)" }
    $texto = Join-Parts @((FmtSize $v.Size), (Lbl 'Livre:' (FmtSize $v.FreeSpace)), $pct, $v.FileSystem)
    [void]$itensVol.Add((Item $rot $texto))
}
Add-Sec 'volumes' 'Unidades e espaco em disco' $itensVol

$itensGpu = New-Object System.Collections.ArrayList
$nGpu = 0
foreach ($vc in $gpus) {
    $nGpu++
    $p = "GPU $nGpu"
    $res = ''
    if ($vc.CurrentHorizontalResolution -and $vc.CurrentVerticalResolution) { $res = ("" + $vc.CurrentHorizontalResolution + ' x ' + $vc.CurrentVerticalResolution) }
    if ($vc.CurrentRefreshRate) { $res = Join-Parts @($res, ("" + $vc.CurrentRefreshRate + ' Hz')) }
    [void]$itensGpu.Add((Item ($p + ' - modelo') $vc.Name))
    [void]$itensGpu.Add((Item ($p + ' - fabricante') $vc.AdapterCompatibility))
    [void]$itensGpu.Add((Item ($p + ' - VRAM reportada') (FmtSize $vc.AdapterRAM)))
    [void]$itensGpu.Add((Item ($p + ' - driver') (Join-Parts @($vc.DriverVersion, (FmtDate $vc.DriverDate)))))
    [void]$itensGpu.Add((Item ($p + ' - resolucao atual') $res))
    [void]$itensGpu.Add((Item ($p + ' - cores de video') (FmtBits $vc.CurrentBitsPerPixel)))
    [void]$itensGpu.Add((Item ($p + ' - processador grafico') $vc.VideoProcessor))
    [void]$itensGpu.Add((Item ($p + ' - status') $vc.Status))
}
Add-Sec 'gpu' 'Placa de video' $itensGpu

$itensMon = New-Object System.Collections.ArrayList
$nMon = 0
foreach ($w in $wmiMons) {
    $nMon++
    $nome = ''
    if ($w.UserFriendlyName) { $nome = (([char[]]$w.UserFriendlyName | Where-Object { $_ -ne [char]0 }) -join '').Trim() }
    $fabric = ''
    if ($w.ManufacturerName) { $fabric = (([char[]]$w.ManufacturerName | Where-Object { $_ -ne [char]0 }) -join '').Trim() }
    $serial = ''
    if ($w.SerialNumberID) { $serial = (([char[]]$w.SerialNumberID | Where-Object { $_ -ne [char]0 }) -join '').Trim() }
    [void]$itensMon.Add((Item "Monitor $nMon" (Join-Parts @($nome, $fabric, (Lbl 'Serial:' $serial)))))
}
foreach ($mo in $mons) {
    $res = ''
    if ($mo.ScreenWidth -and $mo.ScreenHeight) { $res = ("" + $mo.ScreenWidth + ' x ' + $mo.ScreenHeight) }
    $texto = Join-Parts @($mo.Name, $mo.MonitorManufacturer, $res)
    if ($texto) { [void]$itensMon.Add((Item 'Monitor (WMI)' $texto)) }
}
Add-Sec 'monitores' 'Monitores' $itensMon

$itensSom = New-Object System.Collections.ArrayList
$nSom = 0
foreach ($s in $snds) {
    $nSom++
    [void]$itensSom.Add((Item "Dispositivo de audio $nSom" (Join-Parts @($s.Name, $s.Manufacturer))))
}
Add-Sec 'audio' 'Audio' $itensSom

$itensRede = New-Object System.Collections.ArrayList
$nRede = 0
foreach ($n in $nics) {
    $nRede++
    $cfg = One @($cfgs | Where-Object { $_.InterfaceIndex -eq $n.InterfaceIndex })
    $partes = New-Object System.Collections.ArrayList
    [void]$partes.Add((Lbl 'MAC:' $n.MACAddress))
    if ($n.Speed -and [double]$n.Speed -gt 0 -and [double]$n.Speed -lt 1e12) { [void]$partes.Add(('{0:0} Mbps' -f ([double]$n.Speed / 1000000))) }
    if ($cfg) {
        $ipv4 = @($cfg.IPAddress | Where-Object { $_ -and ([string]$_).IndexOf(':') -lt 0 })
        $ipv6 = @($cfg.IPAddress | Where-Object { $_ -and ([string]$_).IndexOf(':') -ge 0 })
        foreach ($ip in $ipv4) {
            $ips = [string]$ip
            if ($ips -match '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)') { [void]$ipsLocal.Add($ips) }
        }
        [void]$partes.Add((Lbl 'IPv4:' ($ipv4 -join ', ')))
        [void]$partes.Add((Lbl 'Mascara:' (@($cfg.IPSubnet | Where-Object { $_ -match '^\d{1,3}(\.\d{1,3}){3}$' }) -join ', ')))
        [void]$partes.Add((Lbl 'Gateway:' (@($cfg.DefaultIPGateway) -join ', ')))
        [void]$partes.Add((Lbl 'DNS:' (@($cfg.DNSServerSearchOrder) -join ', ')))
        [void]$partes.Add((Lbl 'DHCP:' (FmtBool $cfg.DHCPEnabled)))
        if ($ipv6.Count -gt 0) { [void]$partes.Add((Lbl 'IPv6:' ($ipv6 -join ', '))) }
    }
    if ($n.NetConnectionStatus -ne $null) { [void]$partes.Add((Lbl 'Status:' $netMap[[int]$n.NetConnectionStatus])) }
    [void]$itensRede.Add((Item ("Adaptador $nRede - " + [string]$n.Name) (Join-Parts $partes)))
}
Add-Sec 'rede' 'Rede' $itensRede

$itensBat = New-Object System.Collections.ArrayList
$nBat = 0
foreach ($b in $bats) {
    $nBat++
    $rot = "Bateria $nBat"
    if ($b.Name) { $rot = [string]$b.Name }
    $carga = ''
    if ($null -ne $b.EstimatedChargeRemaining) { $carga = Lbl 'Carga:' ("" + $b.EstimatedChargeRemaining + '%') }
    $estado = ''
    if ($null -ne $b.BatteryStatus -and $batMap[[int]$b.BatteryStatus]) { $estado = $batMap[[int]$b.BatteryStatus] }
    [void]$itensBat.Add((Item $rot (Join-Parts @($estado, $carga, $b.DeviceID))))
}
Add-Sec 'bateria' 'Bateria' $itensBat

$itensSeg = New-Object System.Collections.ArrayList
$nAv = 0
foreach ($av in $avs) {
    $nAv++
    $estado = ''
    if ($null -ne $av.productState) {
        $est = [int]$av.productState
        if (((($est -shr 12) -band 0xF) -eq 1)) { $estado = 'Ativo' } else { $estado = 'Inativo' }
    }
    [void]$itensSeg.Add((Item ("Antivirus $nAv") (Join-Parts @($av.displayName, $estado))))
}
foreach ($fw in $fws) {
    [void]$itensSeg.Add((Item ("Firewall (" + [string]$fw.Name + ")") (FmtBool $fw.Enabled)))
}
Add-Sec 'seguranca' 'Seguranca' $itensSeg

$payload = [ordered]@{
    coletado_em = (Get-Date).ToString('dd/MM/yyyy HH:mm:ss')
    fonte       = 'PowerShell / Get-CimInstance'
    resumo      = @($resumo)
    ips_locais  = @($ipsLocal | Sort-Object -Unique)
    secoes      = @($secoes)
}

$json = $payload | ConvertTo-Json -Depth 8
[System.IO.File]::WriteAllText($Out, $json, (New-Object System.Text.UTF8Encoding($false)))
PSS;
}
