<?php
include("../config/conexao.php");

// Buscar todos os clientes
$sql_clientes = "SELECT * FROM clientes";
$result_clientes = $conn->query($sql_clientes);

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes e Tatuagens</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f5f5f5; }
    .container { margin-top: 20px; }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body>

  <!-- Cabeçalho e Menu -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Sistema de Cadastro</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link active" href="cadastrar_cliente.php">Cadastrar Cliente</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="cadastrar_tatuagem.php">Cadastrar Tatuagem</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container">
    <h1 class="mb-4">Clientes e Tatuagens</h1>

    <!-- Tabela de Clientes -->
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Nome Completo</th>
          <th>Telefone</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($cliente = $result_clientes->fetch_assoc()): ?>
          <tr>
            <td><?php echo $cliente['nome']; ?></td>
            <td>
              <!-- Link para WhatsApp -->
              <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $cliente['telefone']); ?>" target="_blank">
                <?php echo $cliente['telefone']; ?>
              </a>
            </td>
            <td>
              <!-- Botão para colapsar/expandir as tatuagens -->
              <button class="btn btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#tatuagens-<?php echo $cliente['id']; ?>" aria-expanded="false" aria-controls="tatuagens-<?php echo $cliente['id']; ?>">
                Ver Tatuagens
              </button>
            </td>
          </tr>

          <!-- Sub-tabela de Tatuagens do Cliente -->
          <tr class="collapse" id="tatuagens-<?php echo $cliente['id']; ?>">
            <td colspan="3">
              <table class="table table-bordered mt-3">
                <thead>
                  <tr>
                    <th>Descrição</th>
                    <th>Valor (R$)</th>
                    <th>Data</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // Buscar tatuagens desse cliente
                  $sql_tatuagens = "SELECT * FROM tatuagens WHERE cliente_id = " . $cliente['id'];
                  $result_tatuagens = $conn->query($sql_tatuagens);

                  if ($result_tatuagens->num_rows > 0) {
                    while ($tatuagem = $result_tatuagens->fetch_assoc()) {
                      echo "<tr>";
                      echo "<td>" . $tatuagem['descricao'] . "</td>";
                      echo "<td>" . number_format($tatuagem['valor'], 2, ',', '.') . "</td>";
                      echo "<td>" . ($tatuagem['data_tatuagem'] ? date("d/m/Y", strtotime($tatuagem['data_tatuagem'])) : 'N/A') . "</td>";
                      echo "</tr>";
                    }
                  } else {
                    echo "<tr><td colspan='3'>Nenhuma tatuagem encontrada para este cliente.</td></tr>";
                  }
                  ?>
                </tbody>
              </table>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
