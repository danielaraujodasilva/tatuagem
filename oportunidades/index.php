<?php
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow');

require dirname(__DIR__) . '/plan/includes/bootstrap.php';

$erro = '';

if (!current_user() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tokenOk = hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['password'] ?? '');

    if (!$tokenOk) {
        $erro = 'Sessao expirada. Tente novamente.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $encontrado = $stmt->fetch();

        if ($encontrado && password_verify($senha, (string)$encontrado['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$encontrado['id'];
        } else {
            $erro = 'E-mail ou senha invalidos.';
        }
    }
}

$usuario = current_user();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Estudio Cereja 2 - Oportunidades de Venda</title>
<style>
  :root {
    --bg: #0f1115;
    --panel: #171a21;
    --panel-2: #1e222b;
    --line: #2a2f3a;
    --txt: #e7eaf0;
    --muted: #98a2b3;
    --hot: #22c55e;
    --warm: #f59e0b;
    --cold: #64748b;
    --peach: #ff7a7a;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--txt);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    font-size: 14px;
  }
  header {
    padding: 22px 24px 16px;
    border-bottom: 1px solid var(--line);
    background: linear-gradient(180deg, #1a1020 0%, #0f1115 100%);
  }
  h1 { margin: 0 0 4px; font-size: 20px; letter-spacing: .2px; }
  h1 span { color: var(--peach); }
  .sub { color: var(--muted); font-size: 12.5px; }
  .wrap { padding: 18px 24px 40px; max-width: 1500px; margin: 0 auto; }

  .cards { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
  .card {
    background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
    padding: 12px 16px; min-width: 132px; flex: 1 1 132px;
  }
  .card .lbl { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .6px; }
  .card .val { font-size: 24px; font-weight: 700; margin-top: 4px; }
  .card.hot .val { color: var(--hot); }
  .card.warm .val { color: var(--warm); }
  .card.cold .val { color: var(--cold); }

  .controls {
    background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
    padding: 12px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
    margin-bottom: 14px;
  }
  .controls input[type="search"], .controls select {
    background: var(--panel-2); color: var(--txt); border: 1px solid var(--line);
    border-radius: 8px; padding: 8px 10px; font-size: 13px; outline: none;
  }
  .controls input[type="search"] { flex: 1 1 220px; min-width: 180px; }
  .controls input:focus, .controls select:focus { border-color: #4b5563; }
  .range { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 12px; }
  .range input[type="range"] { width: 130px; accent-color: var(--peach); }
  .chk { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: 12.5px; cursor: pointer; }
  .chk input { accent-color: var(--peach); }
  button.reset {
    background: transparent; color: var(--muted); border: 1px solid var(--line);
    border-radius: 8px; padding: 8px 12px; cursor: pointer; font-size: 12.5px;
  }
  button.reset:hover { color: var(--txt); border-color: #4b5563; }
  .topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 12px; }
  .who { color: var(--muted); font-size: 12.5px; }
  .who a { color: #7dd3fc; text-decoration: none; }

  table { width: 100%; border-collapse: collapse; background: var(--panel); border-radius: 12px; overflow: hidden; }
  thead th {
    text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .6px;
    color: var(--muted); padding: 11px 12px; border-bottom: 1px solid var(--line);
    background: var(--panel-2); white-space: nowrap;
  }
  thead th.sortable { cursor: pointer; user-select: none; }
  thead th.sortable:hover { color: var(--txt); }
  thead th .arrow { opacity: .35; font-size: 10px; margin-left: 4px; }
  thead th.active .arrow { opacity: 1; color: var(--peach); }
  tbody td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: middle; }
  tbody tr:hover { background: #1c2029; }
  tbody tr:last-child td { border-bottom: none; }

  .name { font-weight: 600; }
  .resumo { color: var(--muted); font-size: 12.5px; max-width: 420px; }
  a.wa {
    color: #7dd3fc; text-decoration: none; font-variant-numeric: tabular-nums;
    border-bottom: 1px dotted rgba(125,211,252,.4);
  }
  a.wa:hover { color: #bae6fd; }

  .score { display: flex; align-items: center; gap: 8px; min-width: 116px; }
  .score b { width: 26px; text-align: right; font-variant-numeric: tabular-nums; }
  .bar { flex: 1; height: 7px; border-radius: 6px; background: #262b36; overflow: hidden; }
  .bar i { display: block; height: 100%; border-radius: 6px; }
  .bar.hot i { background: var(--hot); }
  .bar.warm i { background: var(--warm); }
  .bar.cold i { background: var(--cold); }

  .pill {
    display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11.5px;
    border: 1px solid var(--line); white-space: nowrap; color: var(--muted); background: #1b1f27;
  }
  .pill.hot { color: #86efac; border-color: #14532d; background: #0f2417; }
  .pill.warm { color: #fcd34d; border-color: #78350f; background: #26190a; }
  .pill.cold { color: #cbd5e1; border-color: #334155; background: #191d24; }
  .pill.done { color: #a5b4fc; border-color: #3730a3; background: #191a35; }

  .empty { padding: 30px; text-align: center; color: var(--muted); }
  footer { color: var(--muted); font-size: 11.5px; margin-top: 14px; line-height: 1.6; }

  .login-shell {
    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
  }
  .login-panel {
    width: 100%; max-width: 380px; background: var(--panel); border: 1px solid var(--line);
    border-radius: 14px; padding: 26px;
  }
  .login-panel .brand { color: var(--peach); font-weight: 700; letter-spacing: .5px; font-size: 12px; text-transform: uppercase; }
  .login-panel h1 { font-size: 19px; margin: 8px 0 18px; }
  .login-panel label { display: block; color: var(--muted); font-size: 12.5px; margin-bottom: 12px; }
  .login-panel input {
    width: 100%; margin-top: 6px; background: var(--panel-2); color: var(--txt);
    border: 1px solid var(--line); border-radius: 8px; padding: 10px; font-size: 14px; outline: none;
  }
  .login-panel input:focus { border-color: #4b5563; }
  .login-panel button {
    width: 100%; margin-top: 6px; background: var(--peach); color: #2a0d0d; border: none;
    border-radius: 8px; padding: 11px; font-size: 14px; font-weight: 700; cursor: pointer;
  }
  .login-panel button:hover { filter: brightness(1.06); }
  .login-erro { color: #fca5a5; font-size: 12.5px; margin: 10px 0 0; }
  .login-dica { color: var(--muted); font-size: 11.5px; margin-top: 14px; line-height: 1.5; }

  @media (max-width: 780px) {
    .resumo { max-width: 200px; }
    thead th:nth-child(7), tbody td:nth-child(7) { display: none; }
  }
</style>
</head>
<body>
<?php if (!$usuario): ?>
  <main class="login-shell">
    <section class="login-panel">
      <div class="brand">Estudio Cereja 2</div>
      <h1>Oportunidades de venda</h1>
      <form method="post" action="index.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label>
          E-mail
          <input name="email" type="email" autocomplete="email" required>
        </label>
        <label>
          Senha
          <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Entrar</button>
        <?php if ($erro !== ''): ?>
          <p class="login-erro"><?= htmlspecialchars($erro, ENT_QUOTES) ?></p>
        <?php endif; ?>
      </form>
      <p class="login-dica">Use o mesmo e-mail e senha do Plan Financeiro.</p>
    </section>
  </main>
<?php else: ?>
  <header>
    <h1>Estudio <span>Cereja 2</span> &middot; Oportunidades de venda</h1>
    <div class="sub">Conversas de WhatsApp de setembro/2026 &middot; nota de 0 a 100 = chance estimada de fechamento</div>
  </header>

  <div class="wrap">
    <div class="cards">
      <div class="card"><div class="lbl">Oportunidades</div><div class="val" id="kpiTotal">-</div></div>
      <div class="card hot"><div class="lbl">Quentes (70+)</div><div class="val" id="kpiHot">-</div></div>
      <div class="card warm"><div class="lbl">Mornas (45-69)</div><div class="val" id="kpiWarm">-</div></div>
      <div class="card cold"><div class="lbl">Frias (&lt;45)</div><div class="val" id="kpiCold">-</div></div>
      <div class="card"><div class="lbl">Nota media</div><div class="val" id="kpiAvg">-</div></div>
    </div>

    <div class="controls">
      <input type="search" id="q" placeholder="Buscar por nome, numero ou resumo...">
      <select id="fSituacao"><option value="">Situacao: todas</option></select>
      <select id="fOrigem"><option value="">Origem: todas</option></select>
      <select id="fTemp">
        <option value="">Temperatura: todas</option>
        <option value="hot">Quente (70+)</option>
        <option value="warm">Morna (45-69)</option>
        <option value="cold">Fria (&lt;45)</option>
      </select>
      <div class="range">Nota min <input type="range" id="fNota" min="0" max="100" step="5" value="0"><b id="fNotaLbl">0</b></div>
      <label class="chk"><input type="checkbox" id="fOcultar"> ocultar ja agendados/atendidos</label>
      <button class="reset" id="btnReset">Limpar filtros</button>
      <div class="topbar-actions">
        <span class="who"><?= htmlspecialchars((string)$usuario['name'], ENT_QUOTES) ?> &middot; <a href="logout.php">sair</a></span>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th class="sortable" data-key="n">Nome <span class="arrow">&#9650;&#9660;</span></th>
          <th class="sortable" data-key="f">WhatsApp <span class="arrow">&#9650;&#9660;</span></th>
          <th class="sortable" data-key="s">Nota (0-100) <span class="arrow">&#9650;&#9660;</span></th>
          <th class="sortable" data-key="t">Temperatura <span class="arrow">&#9650;&#9660;</span></th>
          <th class="sortable" data-key="st">Situacao <span class="arrow">&#9650;&#9660;</span></th>
          <th class="sortable" data-key="d">Ultimo contato <span class="arrow">&#9650;&#9660;</span></th>
          <th>Resumo da conversa</th>
        </tr>
      </thead>
      <tbody id="tb"></tbody>
    </table>
    <div class="empty" id="mensagem">Carregando...</div>

    <footer>
      Dados lidos em tempo real das conversas de WhatsApp e da agenda do CRM do estudio.
      Contem dados pessoais de clientes &mdash; acesso restrito.
    </footer>
  </div>

<script>
var DADOS = [];
var DONE = { "Agendado": 1, "Atendido": 1 };
var estado = { sortKey: "s", sortDir: -1 };

function temp(s) { return s >= 70 ? "hot" : (s >= 45 ? "warm" : "cold"); }
function tempLbl(s) { return s >= 70 ? "Quente" : (s >= 45 ? "Morna" : "Fria"); }
function esc(s) {
  return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function filtra() {
  var q = document.getElementById("q").value.trim().toLowerCase();
  var fi = document.getElementById("fSituacao").value;
  var fo = document.getElementById("fOrigem").value;
  var ft = document.getElementById("fTemp").value;
  var fn = parseInt(document.getElementById("fNota").value, 10);
  var ocultar = document.getElementById("fOcultar").checked;

  return DADOS.filter(function (r) {
    if (ocultar && DONE[r.st]) return false;
    if (fi && r.st !== fi) return false;
    if (fo && r.o !== fo) return false;
    if (ft && temp(r.s) !== ft) return false;
    if (r.s < fn) return false;
    if (q) {
      var alvo = (r.n + " " + r.f + " " + r.r + " " + r.st).toLowerCase();
      if (alvo.indexOf(q) === -1) return false;
    }
    return true;
  });
}

function ordena(rows) {
  var k = estado.sortKey, dir = estado.sortDir;
  return rows.slice().sort(function (a, b) {
    var x, y;
    if (k === "s") { x = a.s; y = b.s; }
    else if (k === "t") { x = a.s; y = b.s; }
    else { x = String(a[k] || "").toLowerCase(); y = String(b[k] || "").toLowerCase(); }
    if (x < y) return -1 * dir;
    if (x > y) return 1 * dir;
    return 0;
  });
}

function render() {
  var rows = ordena(filtra());
  var tb = document.getElementById("tb");
  var aviso = document.getElementById("mensagem");
  tb.innerHTML = "";
  aviso.style.display = rows.length ? "none" : "block";
  if (!rows.length && !DADOS.length) return;
  if (!rows.length) aviso.textContent = "Nenhuma oportunidade com os filtros atuais.";

  rows.forEach(function (r) {
    var t = temp(r.s);
    var tr = document.createElement("tr");
    var pillSt = DONE[r.st] ? "pill done" : ("pill " + t);
    tr.innerHTML =
      '<td class="name">' + esc(r.n) + '</td>' +
      '<td><a class="wa" href="https://wa.me/' + r.f + '" target="_blank" rel="noopener">+' + r.f + '</a></td>' +
      '<td><div class="score ' + t + '"><b>' + r.s + '</b><span class="bar ' + t + '"><i style="width:' + r.s + '%"></i></span></div></td>' +
      '<td><span class="pill ' + t + '">' + tempLbl(r.s) + '</span></td>' +
      '<td><span class="' + pillSt + '">' + esc(r.st) + '</span></td>' +
      '<td>' + esc(r.d) + '</td>' +
      '<td class="resumo">' + esc(r.r) + '</td>';
    tb.appendChild(tr);
  });

  var ths = document.querySelectorAll("thead th.sortable");
  for (var i = 0; i < ths.length; i++) {
    ths[i].classList.toggle("active", ths[i].dataset.key === estado.sortKey);
  }
}

function kpis() {
  var abertas = DADOS.filter(function (r) { return !DONE[r.st]; });
  var hot = abertas.filter(function (r) { return r.s >= 70; }).length;
  var warm = abertas.filter(function (r) { return r.s >= 45 && r.s < 70; }).length;
  var cold = abertas.filter(function (r) { return r.s < 45; }).length;
  var media = abertas.length ? Math.round(abertas.reduce(function (a, r) { return a + r.s; }, 0) / abertas.length) : 0;
  document.getElementById("kpiTotal").textContent = abertas.length;
  document.getElementById("kpiHot").textContent = hot;
  document.getElementById("kpiWarm").textContent = warm;
  document.getElementById("kpiCold").textContent = cold;
  document.getElementById("kpiAvg").textContent = media;
}

function populaSelects() {
  var st = [], or = [], i;
  DADOS.forEach(function (r) {
    if (st.indexOf(r.st) === -1) st.push(r.st);
    if (or.indexOf(r.o) === -1) or.push(r.o);
  });
  var ordemSt = ["Quase fechando", "Negociando", "Orcamento enviado", "Sem resposta", "Agendado", "Atendido"];
  st.sort(function (a, b) { return ordemSt.indexOf(a) - ordemSt.indexOf(b); });
  or.sort();
  var s1 = document.getElementById("fSituacao"), s2 = document.getElementById("fOrigem");
  s1.innerHTML = '<option value="">Situacao: todas</option>';
  s2.innerHTML = '<option value="">Origem: todas</option>';
  for (i = 0; i < st.length; i++) s1.insertAdjacentHTML("beforeend", '<option value="' + esc(st[i]) + '">' + esc(st[i]) + '</option>');
  for (i = 0; i < or.length; i++) s2.insertAdjacentHTML("beforeend", '<option value="' + esc(or[i]) + '">' + esc(or[i]) + '</option>');
}

var ths = document.querySelectorAll("thead th.sortable");
for (var i = 0; i < ths.length; i++) {
  ths[i].addEventListener("click", function () {
    var k = this.dataset.key;
    if (estado.sortKey === k) estado.sortDir *= -1;
    else { estado.sortKey = k; estado.sortDir = (k === "s") ? -1 : 1; }
    render();
  });
}

["q", "fSituacao", "fOrigem", "fTemp", "fOcultar"].forEach(function (id) {
  document.getElementById(id).addEventListener("input", render);
  document.getElementById(id).addEventListener("change", render);
});
document.getElementById("fNota").addEventListener("input", function () {
  document.getElementById("fNotaLbl").textContent = this.value;
  render();
});
document.getElementById("btnReset").addEventListener("click", function () {
  document.getElementById("q").value = "";
  document.getElementById("fSituacao").value = "";
  document.getElementById("fOrigem").value = "";
  document.getElementById("fTemp").value = "";
  document.getElementById("fNota").value = 0;
  document.getElementById("fNotaLbl").textContent = "0";
  document.getElementById("fOcultar").checked = false;
  render();
});

fetch("api.php", { credentials: "same-origin" })
  .then(function (r) { return r.json(); })
  .then(function (json) {
    if (!json.ok) throw new Error(json.message || "Falha ao carregar os dados.");
    DADOS = json.rows || [];
    populaSelects();
    kpis();
    render();
    if (!DADOS.length) {
      document.getElementById("mensagem").style.display = "block";
      document.getElementById("mensagem").textContent = "Nenhuma conversa analisada foi encontrada no banco do CRM.";
    }
  })
  .catch(function (e) {
    document.getElementById("mensagem").style.display = "block";
    document.getElementById("mensagem").textContent = "Erro ao carregar: " + e.message;
  });
</script>
<?php endif; ?>
</body>
</html>
