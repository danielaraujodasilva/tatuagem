<?php
require __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/../includes/app_menu.php';

$roles = AUTH_ROLES;
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'update' && $id > 0) {
        $role = in_array($_POST['role'] ?? '', $roles, true) ? (string)$_POST['role'] : 'cliente';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $clienteId = ($_POST['cliente_id'] ?? '') !== '' ? (int)$_POST['cliente_id'] : null;
        $stmt = $conn->prepare('UPDATE usuarios SET role = ?, ativo = ?, cliente_id = ? WHERE id = ?');
        $stmt->bind_param('siii', $role, $ativo, $clienteId, $id);
        $stmt->execute();
        $stmt->close();
        $feedback = 'Usuario atualizado.';
    }
}

$usuarios = $conn->query('SELECT u.*, c.nome AS cliente_nome FROM usuarios u LEFT JOIN clientes c ON c.id = u.cliente_id ORDER BY u.created_at DESC');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuarios</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../ficha/assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame">
      <header class="ficha-hero">
        <span class="ficha-kicker">Administracao</span>
        <h1>Usuarios e acessos</h1>
        <?php app_menu_render('usuarios'); ?>
      </header>
      <div class="ficha-content">
        <?php if ($feedback): ?><div class="ficha-alert ficha-alert-success mb-4"><?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <div class="ficha-table-wrap">
          <table class="table align-middle">
            <thead><tr><th>Usuario</th><th>Contato</th><th>Nivel</th><th>Cliente vinculado</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php while ($u = $usuarios->fetch_assoc()): ?>
                <tr>
                  <form method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                    <td><strong><?php echo htmlspecialchars($u['nome'], ENT_QUOTES, 'UTF-8'); ?></strong><div class="ficha-muted"><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                    <td><?php echo htmlspecialchars($u['telefone'], ENT_QUOTES, 'UTF-8'); ?><div class="ficha-muted"><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                    <td><select class="form-select ficha-input" name="role"><?php foreach ($roles as $role): ?><option value="<?php echo $role; ?>" <?php echo $u['role'] === $role ? 'selected' : ''; ?>><?php echo $role; ?></option><?php endforeach; ?></select></td>
                    <td><input class="form-control ficha-input" name="cliente_id" value="<?php echo htmlspecialchars((string)$u['cliente_id'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="ID do cliente"><div class="ficha-muted"><?php echo htmlspecialchars((string)$u['cliente_nome'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                    <td><label class="form-check"><input class="form-check-input" type="checkbox" name="ativo" <?php echo (int)$u['ativo'] === 1 ? 'checked' : ''; ?>> ativo</label></td>
                    <td><button class="btn ficha-btn ficha-btn-primary btn-sm" type="submit">Salvar</button></td>
                  </form>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
