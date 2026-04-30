<?php
if (!isset($_GET['id'])) {
    die('Cliente nao especificado.');
}

require __DIR__ . '/config/conexao.php';

$id = (int) $_GET['id'];
$stmt = $conn->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cliente) {
    die('Cliente nao encontrado.');
}

$stmtTat = $conn->prepare('SELECT id, descricao, valor, data_tatuagem, hora_inicio, hora_fim, status FROM tatuagens WHERE cliente_id = ? ORDER BY data_tatuagem DESC, hora_inicio DESC');
$stmtTat->bind_param('i', $id);
$stmtTat->execute();
$tatuagens = $stmtTat->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalhes de <?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame">
      <header class="ficha-hero">
        <span class="ficha-kicker">Cliente</span>
        <h1><?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Visao consolidada do historico do cliente, com dados pessoais, anamnese e tatuagens registradas.</p>
        <div class="ficha-nav">
          <a class="btn ficha-btn ficha-btn-secondary" href="public/clientes.php">Voltar para clientes</a>
          <a class="btn ficha-btn ficha-btn-primary" href="public/cadastrar_tatuagem.php?cliente_id=<?php echo $id; ?>">Novo agendamento</a>
          <a class="btn ficha-btn ficha-btn-warning" href="agenda/">Agenda</a>
        </div>
      </header>

      <div class="ficha-content">
        <div class="row g-4">
          <div class="col-lg-5">
            <div class="ficha-summary">
              <h2 class="ficha-panel-title">Ficha principal</h2>
              <div class="ficha-detail-grid">
                <div class="ficha-detail-card"><span>Telefone</span><?php echo htmlspecialchars($cliente['telefone'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="ficha-detail-card"><span>E-mail</span><?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="ficha-detail-card"><span>Genero</span><?php echo htmlspecialchars((string) $cliente['genero'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="ficha-detail-card"><span>Data de nascimento</span><?php echo htmlspecialchars((string) $cliente['data_nascimento'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="ficha-detail-card"><span>Profissao</span><?php echo htmlspecialchars((string) $cliente['profissao'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="ficha-detail-card"><span>Instagram</span><?php echo htmlspecialchars((string) $cliente['instagram_cliente'], ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
              <div class="ficha-panel mt-4">
                <h3 class="ficha-panel-title">Endereco</h3>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars((string) $cliente['endereco'], ENT_QUOTES, 'UTF-8')); ?></p>
              </div>
              <div class="ficha-panel mt-4">
                <h3 class="ficha-panel-title">Preferencias e autorizacoes</h3>
                <div class="ficha-detail-grid">
                  <div class="ficha-detail-card"><span>Estilo favorito</span><?php echo htmlspecialchars((string) $cliente['estilo_tatuagem'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="ficha-detail-card"><span>Uso de imagem</span><?php echo (int) $cliente['uso_imagem'] === 1 ? 'Sim' : 'Nao'; ?></div>
                  <div class="ficha-detail-card"><span>Marcacao nas redes</span><?php echo (int) $cliente['marcacao'] === 1 ? 'Sim' : 'Nao'; ?></div>
                  <div class="ficha-detail-card"><span>Hobbies</span><?php echo htmlspecialchars((string) $cliente['hobbies'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="ficha-panel mb-4">
              <h2 class="ficha-panel-title">Anamnese</h2>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="ficha-detail-card">
                    <span>Doencas preexistentes</span>
                    <?php echo nl2br(htmlspecialchars((string) $cliente['tem_doencas'], ENT_QUOTES, 'UTF-8')); ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="ficha-detail-card">
                    <span>Uso de medicamentos</span>
                    <?php echo nl2br(htmlspecialchars((string) $cliente['uso_medicamentos'], ENT_QUOTES, 'UTF-8')); ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="ficha-detail-card">
                    <span>Alergias</span>
                    <?php echo nl2br(htmlspecialchars((string) $cliente['alergias'], ENT_QUOTES, 'UTF-8')); ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="ficha-detail-card">
                    <span>Historico de tatuagens</span>
                    <?php echo nl2br(htmlspecialchars((string) $cliente['historico_tatuagens'], ENT_QUOTES, 'UTF-8')); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="ficha-table-panel">
              <div class="ficha-table-head">
                <div>
                  <h2 class="ficha-panel-title mb-1">Tatuagens registradas</h2>
                  <p class="ficha-copy mb-0">Tudo que ja foi marcado ou realizado para este cliente.</p>
                </div>
                <div class="ficha-chip"><?php echo $tatuagens->num_rows; ?> registros</div>
              </div>
              <div class="ficha-table-wrap">
                <table class="table align-middle">
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
                    <?php if ($tatuagens->num_rows > 0): ?>
                      <?php while ($tattoo = $tatuagens->fetch_assoc()): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($tattoo['descricao'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td>R$ <?php echo number_format((float) $tattoo['valor'], 2, ',', '.'); ?></td>
                          <td><?php echo date('d/m/Y', strtotime($tattoo['data_tatuagem'])); ?></td>
                          <td><?php echo htmlspecialchars(substr((string) $tattoo['hora_inicio'], 0, 5) . ' - ' . substr((string) $tattoo['hora_fim'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><span class="ficha-chip"><?php echo htmlspecialchars($tattoo['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr><td colspan="5" class="ficha-empty">Nenhuma tatuagem registrada para este cliente.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
