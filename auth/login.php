<?php
require __DIR__ . '/auth.php';

$erro = '';
$next = $_GET['next'] ?? $_POST['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $phone = auth_normalize_phone($identifier);

    $stmt = $conn->prepare(
        "SELECT * FROM usuarios
         WHERE ativo = 1
           AND (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, ' ', ''), '-', ''), '(', ''), ')', '') = ?)
         LIMIT 1"
    );
    $stmt->bind_param('sss', $identifier, $identifier, $phone);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, (string)$user['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = (int)$user['id'];

        $destino = $next !== '' ? $next : auth_url(auth_default_path($user));
        header('Location: ' . $destino);
        exit;
    }

    $erro = 'Usuario, telefone, e-mail ou senha invalidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../ficha/assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame" style="max-width: 560px; margin: 0 auto;">
      <header class="ficha-hero">
        <span class="ficha-kicker">Acesso seguro</span>
        <h1>Entrar</h1>
      </header>
      <div class="ficha-content">
        <?php if ($erro): ?><div class="ficha-alert ficha-alert-danger mb-4"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" class="row g-3">
          <input type="hidden" name="next" value="<?php echo htmlspecialchars((string)$next, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="col-12">
            <label class="form-label">Usuario, telefone ou e-mail</label>
            <input class="form-control ficha-input" name="identifier" autocomplete="username" required>
          </div>
          <div class="col-12">
            <label class="form-label">Senha</label>
            <input class="form-control ficha-input" type="password" name="password" autocomplete="current-password" required>
          </div>
          <div class="col-12 d-grid">
            <button class="btn ficha-btn ficha-btn-primary" type="submit">Entrar</button>
          </div>
        </form>
        <div class="d-flex justify-content-between gap-3 mt-4 flex-wrap">
          <a class="ficha-card-link" href="register.php">Criar conta</a>
          <a class="ficha-card-link" href="forgot_password.php">Recuperar senha</a>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
