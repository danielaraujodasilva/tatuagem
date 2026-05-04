<?php
require __DIR__ . '/../auth/auth.php';
$user = require_login();

if ($user['role'] !== 'cliente') {
    auth_redirect('/ficha/public/clientes.php');
}

$clienteId = (int)($user['cliente_id'] ?? 0);
$cliente = null;
$tatuagens = null;

if ($clienteId > 0) {
    $stmt = $conn->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('SELECT descricao, data_tatuagem, hora_inicio, hora_fim, status, referencia_arte FROM tatuagens WHERE cliente_id = ? ORDER BY data_tatuagem DESC, hora_inicio DESC');
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $tatuagens = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Minha conta</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame">
      <header class="ficha-hero">
        <span class="ficha-kicker">Area do cliente</span>
        <h1><?php echo htmlspecialchars((string)$user['nome'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="ficha-nav">
          <a class="btn ficha-btn ficha-btn-secondary" href="../auth/logout.php">Sair</a>
        </div>
      </header>
      <div class="ficha-content">
        <?php if (!$cliente): ?>
          <div class="ficha-alert ficha-alert-info">
            Sua conta ainda nao esta vinculada a uma ficha. Use o mesmo telefone ou e-mail cadastrado na ficha, ou fale com a equipe para vincular seu acesso.
          </div>
        <?php else: ?>
          <div class="row g-4">
            <div class="col-lg-5">
              <div class="ficha-summary">
                <h2 class="ficha-panel-title">Meus dados</h2>
                <div class="ficha-detail-grid">
                  <div class="ficha-detail-card"><span>Telefone</span><?php echo htmlspecialchars($cliente['telefone'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="ficha-detail-card"><span>E-mail</span><?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="ficha-detail-card"><span>Instagram</span><?php echo htmlspecialchars((string)$cliente['instagram_cliente'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="ficha-detail-card"><span>Estilo favorito</span><?php echo htmlspecialchars((string)$cliente['estilo_tatuagem'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
              </div>
            </div>
            <div class="col-lg-7">
              <div class="ficha-table-panel">
                <div class="ficha-table-head">
                  <h2 class="ficha-panel-title mb-0">Minhas tatuagens</h2>
                  <div class="ficha-chip"><?php echo $tatuagens ? $tatuagens->num_rows : 0; ?> registros</div>
                </div>
                <div class="ficha-table-wrap">
                  <table class="table align-middle">
                    <thead><tr><th>Descricao</th><th>Data</th><th>Horario</th><th>Status</th></tr></thead>
                    <tbody>
                      <?php if ($tatuagens && $tatuagens->num_rows > 0): ?>
                        <?php while ($tattoo = $tatuagens->fetch_assoc()): ?>
                          <tr>
                            <td><?php echo htmlspecialchars($tattoo['descricao'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $tattoo['data_tatuagem'] ? date('d/m/Y', strtotime($tattoo['data_tatuagem'])) : '-'; ?></td>
                            <td><?php echo htmlspecialchars(substr((string)$tattoo['hora_inicio'], 0, 5) . ' - ' . substr((string)$tattoo['hora_fim'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="ficha-chip"><?php echo htmlspecialchars($tattoo['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                          </tr>
                        <?php endwhile; ?>
                      <?php else: ?>
                        <tr><td colspan="4" class="ficha-empty">Nenhum registro encontrado.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
