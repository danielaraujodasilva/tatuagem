<?php
require_once __DIR__ . '/../../auth/auth.php';
require_staff();
require __DIR__ . '/../config/conexao.php';

$clientes = $conn->query(
    'SELECT c.*, COUNT(t.id) AS total_tatuagens
     FROM clientes c
     LEFT JOIN tatuagens t ON t.cliente_id = c.id
     GROUP BY c.id
     ORDER BY c.nome ASC'
);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes e Tatuagens</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame">
      <header class="ficha-hero">
        <span class="ficha-kicker">Ficha de cliente</span>
        <h1>Clientes e historico de tatuagens</h1>
        <p>Uma leitura mais rapida do relacionamento com cada cliente, com acesso direto para detalhes, agenda e novos agendamentos.</p>
        <div class="ficha-nav">
          <a class="btn ficha-btn ficha-btn-secondary" href="../index.php">Nova ficha</a>
          <a class="btn ficha-btn ficha-btn-secondary" href="cadastrar_tatuagem.php">Cadastrar tatuagem</a>
          <a class="btn ficha-btn ficha-btn-warning" href="../agenda/">Agenda</a>
          <a class="btn ficha-btn ficha-btn-secondary" href="../mapa_clientes.php">Mapa de clientes</a>
        </div>
      </header>

      <div class="ficha-content">
        <div class="ficha-table-panel">
          <div class="ficha-table-head">
            <div>
              <h2 class="ficha-panel-title mb-1">Base de clientes</h2>
              <p class="ficha-copy mb-0">Expanda cada linha para consultar as tatuagens registradas sem sair da tela.</p>
            </div>
            <div class="ficha-chip"><?php echo $clientes->num_rows; ?> clientes</div>
          </div>
          <div class="ficha-table-wrap">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Contato</th>
                  <th>Tatuagens</th>
                  <th>Acoes</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($cliente = $clientes->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="ficha-muted"><?php echo htmlspecialchars((string) $cliente['instagram_cliente'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td>
                      <a class="ficha-card-link" href="https://wa.me/<?php echo preg_replace('/\D/', '', $cliente['telefone']); ?>" target="_blank">
                        <?php echo htmlspecialchars($cliente['telefone'], ENT_QUOTES, 'UTF-8'); ?>
                      </a>
                      <div class="ficha-muted"><?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td><span class="ficha-chip"><?php echo (int) $cliente['total_tatuagens']; ?> registradas</span></td>
                    <td>
                      <div class="d-flex gap-2 flex-wrap">
                        <button class="btn ficha-btn ficha-btn-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#tatuagens-<?php echo (int) $cliente['id']; ?>">Ver tatuagens</button>
                        <a class="btn ficha-btn ficha-btn-secondary btn-sm" href="../detalhes_cliente.php?id=<?php echo (int) $cliente['id']; ?>">Detalhes</a>
                        <a class="btn ficha-btn ficha-btn-primary btn-sm" href="cadastrar_tatuagem.php?cliente_id=<?php echo (int) $cliente['id']; ?>">Novo agendamento</a>
                      </div>
                    </td>
                  </tr>
                  <tr class="collapse" id="tatuagens-<?php echo (int) $cliente['id']; ?>">
                    <td colspan="4">
                      <div class="ficha-panel mt-2">
                        <h3 class="ficha-panel-title">Tatuagens de <?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="ficha-table-wrap">
                          <table class="table align-middle mb-0">
                            <thead>
                              <tr>
                                <th>Descricao</th>
                                <th>Valor</th>
                                <th>Data</th>
                                <th>Horario</th>
                                <th>Status</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                              $stmtTat = $conn->prepare('SELECT descricao, valor, data_tatuagem, hora_inicio, hora_fim, status FROM tatuagens WHERE cliente_id = ? ORDER BY data_tatuagem DESC, hora_inicio DESC');
                              $stmtTat->bind_param('i', $cliente['id']);
                              $stmtTat->execute();
                              $tatuagens = $stmtTat->get_result();
                              ?>
                              <?php if ($tatuagens->num_rows > 0): ?>
                                <?php while ($tatuagem = $tatuagens->fetch_assoc()): ?>
                                  <tr>
                                    <td><?php echo htmlspecialchars($tatuagem['descricao'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>R$ <?php echo number_format((float) $tatuagem['valor'], 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($tatuagem['data_tatuagem'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr((string) $tatuagem['hora_inicio'], 0, 5) . ' - ' . substr((string) $tatuagem['hora_fim'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="ficha-chip"><?php echo htmlspecialchars($tatuagem['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                  </tr>
                                <?php endwhile; ?>
                              <?php else: ?>
                                <tr><td colspan="5" class="ficha-empty">Nenhuma tatuagem encontrada para este cliente.</td></tr>
                              <?php endif; ?>
                              <?php $stmtTat->close(); ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
