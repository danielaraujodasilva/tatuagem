<?php
declare(strict_types=1);
/**
 * COBRAR O SINAL PELO CELULAR (Pix Mercado Pago) - link simples.
 *
 * POR QUE EXISTE
 * O Daniel atende pelo WhatsApp do celular do estudio, nao pelo CRM. Este link
 * gera a MESMA cobranca Pix que a Irene gera, amarrada ao telefone do cliente:
 * quando o Pix cair, a ponte confirma no WhatsApp do cliente e avisa o estudio.
 *
 * COMO USAR
 *   https://danieltatuador.com/projetocrmv2/cobrar.php?k=CHAVE
 *   https://danieltatuador.com/projetocrmv2/cobrar.php?k=CHAVE&fone=5511999998888&nome=Maria
 * Pode salvar nos favoritos do celular. Sem o telefone na URL, digite na tela.
 *
 * SEGURANCA
 *   - A chave fica em cobrar.local.php (coberto pelo .gitignore: *.local.php).
 *   - Sem chave valida a pagina nao abre e nenhuma cobranca e criada.
 *   - Quem cria o Pix de verdade e a ponte local (127.0.0.1:3210), que e a dona
 *     do token do Mercado Pago. Esta pagina so conversa com ela.
 */

const COBRAR_MAX_VALOR = 500.0;
const COBRAR_MIN_VALOR = 1.0;

function cobrar_cfg(): array
{
    static $cfg = null;
    if (is_array($cfg)) {
        return $cfg;
    }
    $padrao = [
        'chave' => '',
        'ponte' => 'http://127.0.0.1:3210',
        'valor_padrao' => 50.0,
        'valor_max' => COBRAR_MAX_VALOR,
    ];
    $arquivo = __DIR__ . '/cobrar.local.php';
    if (is_file($arquivo)) {
        $lido = require $arquivo;
        if (is_array($lido)) {
            $padrao = array_merge($padrao, $lido);
        }
    }
    return $cfg = $padrao;
}

function cobrar_h($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/** So os digitos do telefone, com o 55 do Brasil quando faltar. */
function cobrar_telefone(string $bruto): string
{
    $digitos = preg_replace('/\D+/', '', $bruto) ?? '';
    $digitos = substr($digitos, 0, 15);
    if ($digitos === '') {
        return '';
    }
    if (strlen($digitos) <= 11) {
        $digitos = '55' . $digitos;
    }
    return $digitos;
}

/** Mostra o telefone do jeito que o Daniel reconhece. */
function cobrar_telefone_bonito(string $digitos): string
{
    $local = strlen($digitos) > 11 ? substr($digitos, 2) : $digitos;
    if (strlen($local) === 11) {
        return '(' . substr($local, 0, 2) . ') ' . substr($local, 2, 5) . '-' . substr($local, 7, 4);
    }
    if (strlen($local) === 10) {
        return '(' . substr($local, 0, 2) . ') ' . substr($local, 2, 4) . '-' . substr($local, 6, 4);
    }
    return $digitos;
}

/**
 * Conversa com a ponte local. Devolve [ok, dados, erro].
 * Timeout curto: gerar Pix leva 1-3s; acima disso tem algo errado.
 */
function cobrar_ponte(string $caminho, string $metodo = 'GET', ?array $corpo = null): array
{
    $cfg = cobrar_cfg();
    $url = rtrim((string)$cfg['ponte'], '/') . $caminho;
    if (!function_exists('curl_init')) {
        return [false, null, 'cURL indisponivel no PHP'];
    }
    $ch = curl_init($url);
    $cabecalhos = ['Accept: application/json'];
    if ($metodo === 'POST') {
        $cabecalhos[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo ?? [], JSON_UNESCAPED_UNICODE));
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $cabecalhos,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resposta = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $falha = curl_error($ch);
    curl_close($ch);

    if ($resposta === false) {
        return [false, null, $falha !== '' ? $falha : 'a ponte nao respondeu'];
    }
    $json = json_decode((string)$resposta, true);
    if (!is_array($json)) {
        return [false, null, 'a ponte devolveu algo inesperado (HTTP ' . $status . ')'];
    }
    if (empty($json['ok'])) {
        return [false, $json, (string)($json['erro'] ?? ('HTTP ' . $status))];
    }
    return [true, $json, ''];
}

$cfg = cobrar_cfg();
$chave = (string)($cfg['chave'] ?? '');
$atalho = (string)($cfg['atalho'] ?? '');
$informada = (string)($_GET['k'] ?? $_POST['k'] ?? '');
$atalhoInformado = (string)($_GET['atalho'] ?? '');

/* Dois jeitos de entrar: o link limpo (/sinal/) ou o link com chave (?k=CHAVE). */
$acessoPorChave = $chave !== '' && $informada !== '' && hash_equals($chave, $informada);
$acessoPorAtalho = $atalho !== '' && $atalhoInformado !== '' && hash_equals($atalho, $atalhoInformado);

/* Cookie "este celular pode mandar Pix": e gravado quando o celular abre o link
   com a chave uma vez. Depois disso o link limpo (/sinal/) ja pode enviar. */
$tokenCelular = $chave !== '' ? hash_hmac('sha256', 'sinal-envio', $chave) : '';
$cookieCelular = (string)($_COOKIE['sinal_envio'] ?? '');
$celularAutorizado = $tokenCelular !== '' && $cookieCelular !== '' && hash_equals($tokenCelular, $cookieCelular);

if ($acessoPorChave && !$celularAutorizado && !headers_sent()) {
    setcookie('sinal_envio', $tokenCelular, [
        'expires' => time() + 400 * 86400,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    /* volta para o link bonito, ja autorizado */
    if (is_dir(__DIR__ . '/../sinal')) {
        header('Location: /sinal/', true, 303);
        exit;
    }
}

/* --- Sem chave configurada / chave errada: nada de cobranca ------------- */
if (!$acessoPorChave && !$acessoPorAtalho) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<body style="font-family:system-ui;background:#0f1115;color:#e6e8ee;padding:28px">';
    echo '<h1 style="font-size:18px">Link invalido</h1>';
    echo '<p style="color:#98a2b3">Falta a chave de acesso (ou ela esta errada). '
        . 'Abra o link completo que o estudio usa.</p></body>';
    exit;
}

/* Mantem o acesso nas telas seguintes (gerar outro / consultar status).
   No link limpo (/sinal/) nao precisa carregar parametro nenhum. */
$linkBase = $acessoPorChave ? '?k=' . rawurlencode($informada) : '';
$linkSep = $linkBase === '' ? '?' : '&';
/* Para onde volta quem toca em "Gerar outro". */
$linkInicio = $acessoPorChave ? $linkBase : '/sinal/';
/* So o link com chave (ou o celular que ja abriu o link com chave) pode MANDAR
   mensagem no WhatsApp do cliente: a pagina publica nao vira torre de disparo. */
$podeEnviar = $acessoPorChave || $celularAutorizado;

$acao = (string)($_GET['acao'] ?? '');

/* --- Polling do status (a pagina se atualiza sozinha) ------------------- */
if ($acao === 'status') {
    header('Content-Type: application/json; charset=utf-8');
    $id = preg_replace('/[^a-zA-Z0-9-]/', '', (string)($_GET['id'] ?? '')) ?? '';
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'id ausente']);
        exit;
    }
    [$ok, $dados, $erro] = cobrar_ponte('/pix/api?charge=' . urlencode($id));
    http_response_code($ok ? 200 : 502);
    echo json_encode($ok ? ['ok' => true] + $dados : ['ok' => false, 'erro' => $erro], JSON_UNESCAPED_UNICODE);
    exit;
}

$erro = '';
$cobranca = null;
$reaproveitada = false;

/* --- Gerou o Pix ------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $telefone = cobrar_telefone((string)($_POST['telefone'] ?? ''));
    $nome = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nome'] ?? '')) ?? '');
    $valor = (float)str_replace(',', '.', (string)($_POST['valor'] ?? ''));

    if ($telefone === '' || strlen($telefone) < 12) {
        $erro = 'Informe o telefone do cliente com DDD.';
    } elseif ($valor < COBRAR_MIN_VALOR || $valor > (float)$cfg['valor_max']) {
        $erro = 'Valor fora do permitido (de R$ ' . number_format(COBRAR_MIN_VALOR, 2, ',', '.')
            . ' a R$ ' . number_format((float)$cfg['valor_max'], 2, ',', '.') . ').';
    } else {
        [$ok, $dados, $falha] = cobrar_ponte('/pix/api', 'POST', [
            'telefone' => $telefone,
            'nome' => $nome,
            'valor' => $valor,
            'avisar' => true,
        ]);
        if ($ok && !empty($dados['cobranca']['qr_code'])) {
            $cobranca = $dados['cobranca'];
            $reaproveitada = !empty($dados['reaproveitada']);

            /* "Gerar e mandar no WhatsApp": manda o Pix pela ponte, direto na
               conversa do cliente. So existe no link com chave. */
            $extra = $reaproveitada ? '&r=1' : '';
            if ($podeEnviar && !empty($_POST['enviar_wa'])) {
                [$okEnv, , $erroEnv] = cobrar_ponte('/pix/api/enviar', 'POST', [
                    'charge_id' => (string)$cobranca['charge_id'],
                ]);
                $extra .= $okEnv
                    ? '&enviado=1'
                    : '&falha_envio=' . rawurlencode((string)$erroEnv);
            }

            /* Redireciona para a mesma tela com ?ver=<id>: recarregar nao gera
               outro Pix, e o status continua atualizando no celular. */
            header('Location: ' . $linkBase . $linkSep . 'ver=' . rawurlencode((string)$cobranca['charge_id'])
                . $extra, true, 303);
            exit;
        }
        $erro = 'Nao consegui gerar o Pix agora: ' . ($falha !== '' ? $falha : 'sem codigo');
    }
}

/* --- Abriu de novo (ou recarregou) com ?ver=<id>: mostra a mesma cobranca - */
$verId = preg_replace('/[^a-zA-Z0-9-]/', '', (string)($_GET['ver'] ?? '')) ?? '';
if ($cobranca === null && $verId !== '') {
    [$okVer, $dadosVer, $erroVer] = cobrar_ponte('/pix/api?charge=' . urlencode($verId));
    if ($okVer && is_array($dadosVer)) {
        $cobranca = [
            'charge_id' => (string)($dadosVer['charge_id'] ?? $verId),
            'qr_code' => (string)($dadosVer['qr_code'] ?? ''),
            'ticket_url' => (string)($dadosVer['ticket_url'] ?? ''),
            'mensagem' => (string)($dadosVer['mensagem'] ?? ''),
            'valor' => (float)($dadosVer['valor'] ?? 0),
            'nome' => (string)($dadosVer['nome'] ?? ''),
            'telefone' => (string)($dadosVer['telefone'] ?? ''),
            'status' => (string)($dadosVer['status'] ?? ''),
        ];
        $reaproveitada = !empty($_GET['r']);
    } else {
        $erro = 'Nao consegui carregar essa cobranca: ' . $erroVer;
    }
}

$foneUrl = (string)($_GET['fone'] ?? $_POST['telefone'] ?? '');
$nomeUrl = (string)($_GET['nome'] ?? $_POST['nome'] ?? '');
$valorPadrao = (float)$cfg['valor_padrao'];

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<meta name="robots" content="noindex, nofollow">
<title>Sinal R$ <?= cobrar_h(number_format($cobranca['valor'] ?? $valorPadrao, 2, ',', '.')) ?> · Estúdio Cereja</title>
<style>
  :root{color-scheme:dark}
  *{box-sizing:border-box}
  body{margin:0;padding:18px 16px 40px;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
       background:#0f1115;color:#e6e8ee;max-width:520px;margin-inline:auto}
  h1{font-size:19px;margin:0 0 4px}
  .sub{color:#98a2b3;font-size:13px;margin:0 0 18px}
  label{display:block;font-size:13px;color:#9aa3b5;margin:14px 0 6px}
  input{width:100%;padding:14px 14px;font-size:17px;border-radius:12px;border:1px solid #2f3644;
        background:#171a21;color:#e6e8ee}
  input:focus{outline:2px solid #2f6fed;border-color:#2f6fed}
  button,.botao{display:block;width:100%;padding:15px 16px;font-size:16px;font-weight:800;border-radius:12px;
        border:0;background:#2f6fed;color:#fff;text-align:center;text-decoration:none;margin-top:16px;cursor:pointer}
  .botao.verde{background:#1f7a45}
  .botao.cinza{background:#232833}
  .cartao{background:#171a21;border:1px solid #262b36;border-radius:14px;padding:16px;margin-top:16px}
  .valor{font-size:30px;font-weight:900;letter-spacing:-.02em}
  .cliente{color:#98a2b3;font-size:13px;margin-top:4px}
  .selo{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800}
  .selo.espera{background:#3a3115;color:#f5c542}
  .selo.pago{background:#12351f;color:#37d67a}
  .selo.ruim{background:#3a2028;color:#ff6b6b}
  #qr{display:block;margin:14px auto 6px;width:240px;height:240px;background:#fff;border-radius:12px;padding:8px}
  #qr img,#qr canvas{display:block;width:100%;height:100%}
  textarea{width:100%;height:86px;padding:10px;font-size:11px;font-family:ui-monospace,monospace;border-radius:10px;
           border:1px solid #2f3644;background:#232833;color:#c9d1dc;resize:none}
  .erro{background:#3a2028;color:#ffb4b4;border-radius:12px;padding:12px;font-size:14px;margin-top:14px}
  .aviso{color:#8b93a7;font-size:12px;margin-top:14px;line-height:1.5}
  .pix-ok{color:#37d67a;font-weight:800}
  .linha{display:flex;gap:8px;align-items:stretch}
  .linha input{flex:1}
  .mini{width:auto;margin:0;padding:0 18px;font-size:14px;font-weight:700;background:#232833;border:1px solid #2f3644;color:#c9d1dc}
  button.verde{background:#1f7a45}
  button.cinza{background:#232833;border:1px solid #2f3644}
</style>
</head>
<body>

<?php if (!$cobranca): ?>
  <h1>Sinal do estúdio</h1>
  <p class="sub">Gera o Pix de <?= cobrar_h(number_format($valorPadrao, 2, ',', '.')) ?> amarrado ao WhatsApp do cliente. Quando cair, a Irene confirma sozinha.</p>

  <?php if ($erro !== ''): ?><div class="erro"><?= cobrar_h($erro) ?></div><?php endif; ?>

  <form method="post" action="<?= $linkBase === '' ? '' : cobrar_h($linkBase) ?>">
    <label for="telefone">Telefone do cliente (com DDD)</label>
    <div class="linha">
      <input id="telefone" name="telefone" type="tel" inputmode="numeric" autocomplete="off"
             placeholder="11 99999-8888" value="<?= cobrar_h($foneUrl) ?>" required>
      <button type="button" class="mini" id="btnColar" title="Colar o telefone copiado">colar</button>
    </div>

    <label for="nome">Nome do cliente (opcional)</label>
    <input id="nome" name="nome" type="text" autocomplete="off" placeholder="Maria" value="<?= cobrar_h($nomeUrl) ?>">

    <label for="valor">Valor do sinal (R$)</label>
    <input id="valor" name="valor" inputmode="decimal" value="<?= cobrar_h(number_format($valorPadrao, 2, '.', '')) ?>" required>

    <?php if ($podeEnviar): ?>
      <button type="submit" class="verde" name="enviar_wa" value="1">Gerar e mandar no WhatsApp</button>
      <button type="submit" class="cinza">Só gerar o código</button>
      <p class="aviso">O Pix chega na conversa do cliente pela ponte do WhatsApp, sem você precisar copiar nada.</p>
    <?php else: ?>
      <button type="submit">Gerar Pix</button>
    <?php endif; ?>
  </form>
  <p class="aviso">O código vale por 30 minutos. Se o cliente pagar, o WhatsApp dele recebe a confirmação e o horário fica reservado.</p>

<?php else: ?>
  <?php
  $telefone = (string)($cobranca['telefone'] ?? '');
  $mensagem = (string)($cobranca['mensagem'] ?? '');
  $wa = 'https://wa.me/' . $telefone . '?text=' . rawurlencode($mensagem);
  $jaPago = in_array((string)($cobranca['status'] ?? ''), ['agendado', 'pago_manual', 'pago_sem_vaga'], true);
  $foiEnviado = !empty($cobranca['enviado']) || !empty($_GET['enviado']);
  $falhaEnvio = (string)($_GET['falha_envio'] ?? '');
  ?>
  <h1>Sinal do estúdio</h1>
  <p class="sub"><?= $reaproveitada ? 'Reaproveitei o Pix que você acabou de gerar para este cliente.' : 'Pix gerado agora.' ?></p>

  <?php if ($falhaEnvio !== ''): ?>
    <div class="erro">Pix gerado, mas o envio no WhatsApp falhou (<?= cobrar_h($falhaEnvio) ?>).
      Use o botão abaixo para mandar na mão.</div>
  <?php endif; ?>

  <div class="cartao">
    <div class="valor">R$ <?= cobrar_h(number_format((float)($cobranca['valor'] ?? 0), 2, ',', '.')) ?></div>
    <div class="cliente"><?= cobrar_h((string)($cobranca['nome'] ?? '')) ?> ·
      <?= cobrar_h(cobrar_telefone_bonito($telefone)) ?></div>
    <div style="margin-top:10px">
      <span class="selo <?= $jaPago ? 'pago' : 'espera' ?>" id="selo">
        <?= $jaPago ? 'pago ✅' : 'aguardando o Pix' ?>
      </span>
      <?php if ($foiEnviado): ?>
        <span class="selo pago" style="margin-left:6px">enviado no WhatsApp ✅</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="cartao">
    <div id="qr"></div>
    <textarea id="copia" readonly><?= cobrar_h((string)($cobranca['qr_code'] ?? '')) ?></textarea>
    <button type="button" class="botao cinza" id="btnCopiar">Copiar código Pix</button>
    <?php if (!$foiEnviado): ?>
      <a class="botao verde" href="<?= cobrar_h($wa) ?>" target="_blank" rel="noopener">Abrir WhatsApp do cliente</a>
    <?php endif; ?>
    <a class="botao cinza" href="<?= cobrar_h($linkInicio) ?>">Gerar outro</a>
  </div>

  <p class="aviso">
    <?php if ($foiEnviado): ?>
      O código já foi mandado na conversa do cliente. Quando ele pagar, a Irene confirma aqui e no WhatsApp dele.
    <?php else: ?>
      Dica: toque em <strong>Abrir WhatsApp</strong> que a mensagem com o código já vai escrita.
      O pagamento é confirmado automaticamente aqui e no WhatsApp do cliente.
    <?php endif; ?>
  </p>

  <script src="/projetocrmv2/assets/qrcodejs.min.js?v=1"></script>
  <script>
    (function () {
      var texto = document.getElementById('copia').value;
      var alvo = document.getElementById('qr');
      if (window.QRCode && texto) {
        // qrcodejs: desenha dentro da div (gera a imagem do QR no proprio navegador)
        new QRCode(alvo, { text: texto, width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });
      } else if (alvo) {
        alvo.innerHTML = '<div class="aviso">QR indisponível — use o código copia e cola.</div>';
      }

      // "colar": pega o telefone que o Daniel copiou no WhatsApp
      var btnColar = document.getElementById('btnColar');
      if (btnColar) {
        btnColar.addEventListener('click', function () {
          if (!navigator.clipboard || !navigator.clipboard.readText) {
            btnColar.textContent = 'sem acesso';
            return;
          }
          navigator.clipboard.readText().then(function (txt) {
            var digitos = (txt || '').replace(/\D+/g, '');
            if (digitos.length >= 10 && digitos.length <= 15) {
              document.getElementById('telefone').value = digitos;
              btnColar.textContent = 'colado!';
            } else {
              btnColar.textContent = 'nada copiado';
            }
            setTimeout(function () { btnColar.textContent = 'colar'; }, 2000);
          }, function () { btnColar.textContent = 'permita o acesso'; });
        });
      }

      document.getElementById('btnCopiar').addEventListener('click', function () {
        var campo = document.getElementById('copia');
        var botao = this;
        function pronto() { botao.textContent = 'Copiado!'; setTimeout(function () { botao.textContent = 'Copiar código Pix'; }, 1800); }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(campo.value).then(pronto, function () { campo.select(); document.execCommand('copy'); pronto(); });
        } else {
          campo.select(); document.execCommand('copy'); pronto();
        }
      });

      // Confirma o pagamento sozinho, sem precisar recarregar.
      var id = <?= json_encode((string)($cobranca['charge_id'] ?? '')) ?>;
      var selo = document.getElementById('selo');
      var pago = <?= $jaPago ? 'true' : 'false' ?>;
      if (!pago && id) {
        var timer = setInterval(function () {
          fetch(<?= json_encode($linkBase . $linkSep . 'acao=status&id=') ?> + encodeURIComponent(id), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              if (!d || !d.ok) return;
              if (['agendado', 'pago_manual', 'pago_sem_vaga'].indexOf(d.status) >= 0) {
                selo.className = 'selo pago';
                selo.textContent = 'pago ✅';
                clearInterval(timer);
              } else if (['recusado', 'expirado', 'valor_divergente', 'erro'].indexOf(d.status) >= 0) {
                selo.className = 'selo ruim';
                selo.textContent = d.status === 'expirado' ? 'expirado' : 'cancelado';
                clearInterval(timer);
              }
            })
            .catch(function () {});
        }, 8000);
      }
    })();
  </script>
<?php endif; ?>

</body>
</html>




