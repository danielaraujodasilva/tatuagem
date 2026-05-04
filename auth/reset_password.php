<?php
require __DIR__ . '/auth.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$erro = '';
$ok = '';
$reset = null;

if ($token !== '') {
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('SELECT id, usuario_id FROM senha_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$reset) {
    $erro = 'Link invalido ou expirado.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($password) < 8) {
        $erro = 'Use uma senha com pelo menos 8 caracteres.';
    } elseif ($password !== $confirm) {
        $erro = 'As senhas nao conferem.';
    } else {
        $hashPassword = password_hash($password, PASSWORD_DEFAULT);
        $uid = (int)$reset['usuario_id'];
        $rid = (int)$reset['id'];
        $stmt = $conn->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $hashPassword, $uid);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE senha_resets SET used_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $stmt->close();

        $ok = 'Senha atualizada. Voce ja pode entrar.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redefinir senha</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../ficha/assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame" style="max-width: 620px; margin: 0 auto;">
      <header class="ficha-hero"><span class="ficha-kicker">Senha</span><h1>Redefinir senha</h1></header>
      <div class="ficha-content">
        <?php if ($erro): ?><div class="ficha-alert ficha-alert-danger mb-4"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($ok): ?><div class="ficha-alert ficha-alert-success mb-4"><?php echo htmlspecialchars($ok, ENT_QUOTES, 'UTF-8'); ?> <a class="ficha-card-link" href="login.php">Entrar</a></div><?php endif; ?>
        <?php if ($reset && !$ok): ?>
          <form method="post" class="row g-3">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="col-md-6"><label class="form-label">Nova senha</label><input class="form-control ficha-input" type="password" name="password" minlength="8" required></div>
            <div class="col-md-6"><label class="form-label">Confirmar senha</label><input class="form-control ficha-input" type="password" name="confirm_password" minlength="8" required></div>
            <div class="col-12 d-grid"><button class="btn ficha-btn ficha-btn-primary" type="submit">Salvar senha</button></div>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
