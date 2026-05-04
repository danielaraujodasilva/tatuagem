<?php
require __DIR__ . '/auth.php';

$erro = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($nome === '' || $username === '' || ($email === '' && $telefone === '') || strlen($password) < 8) {
        $erro = 'Preencha nome, usuario, e-mail ou telefone, e uma senha com pelo menos 8 caracteres.';
    } elseif ($password !== $confirm) {
        $erro = 'As senhas nao conferem.';
    } else {
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM usuarios WHERE LOWER(username) = LOWER(?) OR (email <> "" AND LOWER(email) = LOWER(?)) OR (telefone <> "" AND telefone = ?)');
        $telefoneLimpo = auth_normalize_phone($telefone);
        $stmt->bind_param('sss', $username, $email, $telefoneLimpo);
        $stmt->execute();
        $exists = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($exists > 0) {
            $erro = 'Ja existe uma conta com esse usuario, telefone ou e-mail.';
        } else {
            $totalUsers = (int)$conn->query('SELECT COUNT(*) AS total FROM usuarios')->fetch_assoc()['total'];
            $role = $totalUsers === 0 ? 'adm' : 'cliente';
            $clienteId = $role === 'cliente' ? auth_find_cliente_id($email, $telefone) : null;
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare('INSERT INTO usuarios (cliente_id, username, nome, email, telefone, senha_hash, role, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->bind_param('issssss', $clienteId, $username, $nome, $email, $telefoneLimpo, $hash, $role);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            session_regenerate_id(true);
            $_SESSION['auth_user_id'] = (int)$newId;
            auth_redirect(auth_default_path(['role' => $role]));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Criar conta</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../ficha/assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame" style="max-width: 720px; margin: 0 auto;">
      <header class="ficha-hero">
        <span class="ficha-kicker">Acesso</span>
        <h1>Criar conta</h1>
      </header>
      <div class="ficha-content">
        <?php if ($erro): ?><div class="ficha-alert ficha-alert-danger mb-4"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" class="row g-3">
          <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control ficha-input" name="nome" required></div>
          <div class="col-md-6"><label class="form-label">Usuario</label><input class="form-control ficha-input" name="username" required></div>
          <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control ficha-input" type="email" name="email"></div>
          <div class="col-md-6"><label class="form-label">Telefone</label><input class="form-control ficha-input" name="telefone"></div>
          <div class="col-md-6"><label class="form-label">Senha</label><input class="form-control ficha-input" type="password" name="password" minlength="8" required></div>
          <div class="col-md-6"><label class="form-label">Confirmar senha</label><input class="form-control ficha-input" type="password" name="confirm_password" minlength="8" required></div>
          <div class="col-12 d-grid"><button class="btn ficha-btn ficha-btn-primary" type="submit">Cadastrar</button></div>
        </form>
        <a class="ficha-card-link d-inline-block mt-4" href="login.php">Ja tenho conta</a>
      </div>
    </section>
  </main>
</body>
</html>
