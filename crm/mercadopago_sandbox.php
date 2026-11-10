<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth.php';
require_staff();
require_once __DIR__ . '/mercadopago_lib.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function mp_sandbox_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$config = mercadopago_config();
$error = '';
$preference = null;
$reference = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking = [
        'name' => trim((string)($_POST['nome'] ?? '')),
        'phone' => trim((string)($_POST['telefone'] ?? '')),
        'date' => trim((string)($_POST['data'] ?? '')),
        'time' => trim((string)($_POST['hora'] ?? '')),
        'end_time' => trim((string)($_POST['hora_fim'] ?? '')),
        'description' => trim((string)($_POST['descricao'] ?? '')),
        'artist' => trim((string)($_POST['tatuador'] ?? '')),
        'agendamento_id' => 'TESTE',
        'reference' => 'sinal_teste_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)),
    ];

    if ($booking['name'] === '' || $booking['date'] === '' || $booking['time'] === '' || $booking['description'] === '' || $booking['artist'] === '') {
        $error = 'Preencha nome, data, hora, descricao e tatuador.';
    } else {
        $result = mercadopago_create_sinal_preference($booking);
        if ($result['ok']) {
            $preference = $result['data'];
            $reference = (string)($result['reference'] ?? '');
        } else {
            $error = $result['error'];
            if (!empty($result['data']['message'])) {
                $error .= ' ' . (string)$result['data']['message'];
            }
        }
    }
}

$events = mercadopago_recent_events(10);
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$link = is_array($preference) ? (string)($preference['init_point'] ?? $preference['sandbox_init_point'] ?? '') : '';
$sandboxLink = is_array($preference) ? (string)($preference['sandbox_init_point'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teste Mercado Pago - Sinal</title>
<style>
    body { margin: 0; background: #0f1117; color: #eef0f5; font-family: Arial, sans-serif; }
    main { max-width: 980px; margin: 0 auto; padding: 32px 20px 60px; }
    h1 { font-size: 28px; margin: 0 0 8px; }
    h2 { font-size: 18px; margin: 0 0 12px; }
    .muted { color: #9ca3af; }
    .card { background: #171a23; border: 1px solid #2a2f3b; border-radius: 14px; padding: 20px; margin: 16px 0; }
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    label { display: block; font-size: 13px; color: #c5cad3; margin-bottom: 6px; }
    input, select, textarea { width: 100%; box-sizing: border-box; background: #0e1118; border: 1px solid #303644; color: #fff; border-radius: 9px; padding: 11px 12px; }
    textarea { min-height: 80px; resize: vertical; }
    button, .button { display: inline-block; border: 0; border-radius: 9px; padding: 12px 18px; background: #ef4444; color: #fff; font-weight: 700; text-decoration: none; cursor: pointer; }
    .button.secondary { background: #374151; }
    .alert { border-radius: 10px; padding: 12px 14px; margin: 12px 0; }
    .alert.error { background: #451a1a; color: #fecaca; border: 1px solid #7f1d1d; }
    .alert.ok { background: #12351f; color: #bbf7d0; border: 1px solid #166534; }
    .alert.warn { background: #3b2f12; color: #fde68a; border: 1px solid #854d0e; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #2a2f3b; vertical-align: top; }
    th { color: #9ca3af; font-weight: 600; }
    code { background: #0e1118; padding: 2px 5px; border-radius: 4px; }
    .status-approved { color: #86efac; font-weight: 700; }
    .status-pending { color: #fde68a; font-weight: 700; }
    @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<main>
    <h1>Teste de sinal - Mercado Pago</h1>
    <p class="muted">Fluxo isolado. O valor fixo e R$ 50,00 e nada aqui altera o CRM ou o banco.</p>

    <div class="card">
        <strong>Ambiente:</strong> <?= mp_sandbox_h($config['environment'] ?? 'test') ?> ·
        <strong>Access Token:</strong> <?= trim((string)($config['access_token'] ?? '')) !== '' ? 'configurado' : 'ausente' ?> ·
        <strong>Webhook:</strong> <?= mp_sandbox_h($config['notification_url'] ?? 'nao configurado') ?><br>
        <span class="muted">Assinatura obrigatoria: <?= !empty($config['require_webhook_signature']) ? 'sim' : 'nao (somente teste)' ?></span>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert error"><?= mp_sandbox_h($error) ?></div>
    <?php endif; ?>

    <?php if ($link !== ''): ?>
        <div class="alert ok">
            Link de teste gerado para R$ 50,00.<br><br>
            <a class="button" href="<?= mp_sandbox_h($link) ?>" target="_blank" rel="noopener">Abrir checkout de teste</a>
            <?php if ($sandboxLink !== '' && $sandboxLink !== $link): ?>
                <a class="button secondary" href="<?= mp_sandbox_h($sandboxLink) ?>" target="_blank" rel="noopener">Abrir sandbox_init_point</a>
            <?php endif; ?>
            <p class="muted">Referencia: <code><?= mp_sandbox_h($reference) ?></code></p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Gerar cobranca fictícia</h2>
        <form method="post">
            <div class="grid">
                <div><label>Nome completo</label><input name="nome" value="Cliente Teste Irene" required></div>
                <div><label>Telefone</label><input name="telefone" value="(11) 99999-9999" required></div>
                <div><label>Data</label><input type="date" name="data" value="<?= mp_sandbox_h($tomorrow) ?>" required></div>
                <div><label>Tatuador</label>
                    <select name="tatuador" required>
                        <option>Daniel</option>
                        <option>Theo</option>
                        <option>Fernando</option>
                    </select>
                </div>
                <div><label>Hora inicio</label><input type="time" name="hora" value="14:00" required></div>
                <div><label>Hora fim</label><input type="time" name="hora_fim" value="15:00" required></div>
                <div style="grid-column: 1 / -1"><label>O que sera tatuado</label><textarea name="descricao" required>Tatuagem teste - rosa no braco</textarea></div>
            </div>
            <p><button type="submit">Gerar link de R$ 50,00</button></p>
        </form>
    </div>

    <div class="card">
        <h2>Ultimos webhooks recebidos</h2>
        <?php if (!$events): ?>
            <p class="muted">Nenhum webhook recebido ainda. Depois de pagar, atualize esta pagina.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Recebido</th><th>Pagamento</th><th>Status</th><th>Valor</th><th>Referencia</th></tr></thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <?php $paymentStatus = (string)($event['payment_status'] ?? ''); ?>
                    <tr>
                        <td><?= mp_sandbox_h($event['received_at'] ?? '') ?><br><span class="muted">assinatura: <?= !empty($event['signature_ok']) ? 'ok' : 'nao validada' ?></span></td>
                        <td><code><?= mp_sandbox_h($event['data_id'] ?? '') ?></code><br><span class="muted"><?= mp_sandbox_h($event['payment_method'] ?? '') ?></span></td>
                        <td class="<?= $paymentStatus === 'approved' ? 'status-approved' : 'status-pending' ?>"><?= mp_sandbox_h($paymentStatus ?: 'sem status') ?></td>
                        <td>R$ <?= number_format((float)($event['transaction_amount'] ?? 0), 2, ',', '.') ?></td>
                        <td><?= mp_sandbox_h($event['external_reference'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if (empty($config['webhook_secret'])): ?>
        <div class="alert warn">O webhook ainda esta sem segredo. Isso e aceitavel apenas neste teste isolado; antes de producao, o segredo sera obrigatorio.</div>
    <?php endif; ?>
</main>
</body>
</html>
