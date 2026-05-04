<?php
require __DIR__ . '/auth.php';

$message = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $phone = auth_normalize_phone($identifier);
    $stmt = $conn->prepare(
        "SELECT id, email FROM usuarios
         WHERE ativo = 1
           AND (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, ' ', ''), '-', ''), '(', ''), ')', '') = ?)
         LIMIT 1"
    );
    $stmt->bind_param('sss', $identifier, $identifier, $phone);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $stmt = $conn->prepare('INSERT INTO senha_resets (usuario_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $uid = (int)$user['id'];
        $stmt->bind_param('iss', $uid, $hash, $expires);
        $stmt->execute();
        $stmt->close();

        $resetLink = auth_url('/auth/reset_password.php?token=' . urlencode($token));
        auth_send_password_reset((string)$user['email'], $resetLink);
    }

    if (getenv('AUTH_SHOW_RESET_LINK') !== '1') {
        $resetLink = '';
    }

    $message = 'Se encontramos essa conta, enviamos um link de recuperacao para o e-mail cadastrado.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar senha</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../ficha/assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame" style="max-width: 620px; margin: 0 auto;">
      <header class="ficha-hero"><span class="ficha-kicker">Senha</span><h1>Recuperar senha</h1></header>
      <div class="ficha-content">
        <?php if ($message): ?><div class="ficha-alert ficha-alert-info mb-4"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?><?php if ($resetLink): ?><div class="mt-3"><a class="ficha-card-link" href="<?php echo htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8'); ?>">Abrir redefinicao de senha</a></div><?php endif; ?></div><?php endif; ?>
        <form method="post" class="row g-3">
          <div class="col-12"><label class="form-label">Usuario, telefone ou e-mail</label><input class="form-control ficha-input" name="identifier" required></div>
          <div class="col-12 d-grid"><button class="btn ficha-btn ficha-btn-primary" type="submit">Gerar link</button></div>
        </form>
        <a class="ficha-card-link d-inline-block mt-4" href="login.php">Voltar ao login</a>
      </div>
    </section>
  </main>
</body>
</html>
