<?php include("config/conexao.php"); ?>

<?php
// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $telefone = $_POST['telefone'];
    
    // Verificar se o cliente já existe
    $sql = "SELECT * FROM clientes WHERE nome = '$nome' AND telefone = '$telefone'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        // Cliente já existe
        $cliente = $result->fetch_assoc();
        $cliente_id = $cliente['id'];
        $cliente_existe = true;
    } else {
        // Cliente não existe, cadastrar novo cliente
        $email = $_POST['email'];
        $data_nascimento = $_POST['data_nascimento'];
        $genero = $_POST['genero'];
        $profissao = $_POST['profissao'];
        $endereco = $_POST['endereco'];
        $hobbies = $_POST['hobbies'];
        $estilo_tatuagem = $_POST['estilo_tatuagem'];
        $uso_imagem = isset($_POST['uso_imagem']) ? 1 : 0;
        $marcacao = isset($_POST['marcacao']) ? 1 : 0;
        $instagram_cliente = $_POST['instagram_cliente'];

        $sql = "INSERT INTO clientes (nome, email, telefone, data_nascimento, genero, profissao, endereco, hobbies, estilo_tatuagem, uso_imagem, marcacao, instagram_cliente) 
                VALUES ('$nome', '$email', '$telefone', '$data_nascimento', '$genero', '$profissao', '$endereco', '$hobbies', '$estilo_tatuagem', '$uso_imagem', '$marcacao', '$instagram_cliente')";

        if ($conn->query($sql) === TRUE) {
            $cliente_id = $conn->insert_id;
            $cliente_existe = false;
        } else {
            $error = "Erro ao cadastrar cliente: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Cadastro de Cliente - Estúdio de Tatuagem</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-dark text-light p-5">
    
    
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
            <a class="nav-link active" href="https://danieltatuador.com/ficha/">Cadastrar Cliente</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="https://danieltatuador.com/ficha/public/cadastrar_tatuagem.php">Cadastrar Tatuagem</a>
          </li>
        </ul>
      </div>
    </div>
    <br><br><hr>
  </nav>
    
<div class="container bg-secondary p-4 rounded">
  <h2 class="text-center mb-4">Cadastro de Cliente</h2>

  <?php if (isset($error)): ?>
    <div class="alert alert-danger">
      <?php echo $error; ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label>Nome:</label>
      <input type="text" name="nome" class="form-control" required value="<?php echo isset($nome) ? $nome : ''; ?>">
    </div>
    <div class="mb-3">
      <label>E-mail:</label>
      <input type="email" name="email" class="form-control" required value="<?php echo isset($email) ? $email : ''; ?>">
    </div>
    <div class="mb-3">
      <label>Telefone:</label>
      <input type="text" name="telefone" class="form-control" required value="<?php echo isset($telefone) ? $telefone : ''; ?>">
    </div>
    <div class="mb-3">
      <label>Data de nascimento:</label>
      <input type="date" name="data_nascimento" class="form-control" required value="<?php echo isset($data_nascimento) ? $data_nascimento : ''; ?>">
    </div>
    <div class="mb-3">
      <label>Gênero:</label>
      <select name="genero" class="form-select" required>
        <option value="">Selecione</option>
        <option value="Masculino" <?php echo isset($genero) && $genero == 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
        <option value="Feminino" <?php echo isset($genero) && $genero == 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
        <option value="Outro" <?php echo isset($genero) && $genero == 'Outro' ? 'selected' : ''; ?>>Outro</option>
      </select>
    </div>
    <div class="mb-3">
      <label>Profissão:</label>
      <input type="text" name="profissao" class="form-control" value="<?php echo isset($profissao) ? $profissao : ''; ?>">
    </div>
    <div class="mb-3">
      <label>Endereço:</label>
      <input type="text" name="endereco" class="form-control" value="<?php echo isset($endereco) ? $endereco : ''; ?>">
    </div>
    <div class="mb-3">
      <label>Hobbies:</label>
      <textarea name="hobbies" class="form-control"><?php echo isset($hobbies) ? $hobbies : ''; ?></textarea>
    </div>
    <div class="mb-3">
      <label>Estilo de tatuagem favorito:</label>
      <input type="text" name="estilo_tatuagem" class="form-control" value="<?php echo isset($estilo_tatuagem) ? $estilo_tatuagem : ''; ?>">
    </div>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="uso_imagem" required <?php echo isset($uso_imagem) && $uso_imagem ? 'checked' : ''; ?>>
      <label class="form-check-label">Autorizo o uso de fotos e vídeos.</label>
    </div>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="marcacao" <?php echo isset($marcacao) && $marcacao ? 'checked' : ''; ?>>
      <label class="form-check-label">Gostaria de ser marcado nas redes sociais</label>
    </div>
    <div class="mb-3">
      <label>Instagram:</label>
      <input type="text" name="instagram_cliente" class="form-control" value="<?php echo isset($instagram_cliente) ? $instagram_cliente : ''; ?>">
    </div>

    <!-- Seção de Anamnese -->
    <h4 class="mt-4">Ficha de Anamnese para Tatuagem</h4>
    <div class="mb-3">
      <label>Você possui alguma doença preexistente?</label>
      <textarea name="tem_doencas" class="form-control"><?php echo isset($tem_doencas) ? $tem_doencas : ''; ?></textarea>
    </div>
    <div class="mb-3">
      <label>Está utilizando algum medicamento atualmente?</label>
      <textarea name="uso_medicamentos" class="form-control"><?php echo isset($uso_medicamentos) ? $uso_medicamentos : ''; ?></textarea>
    </div>
    <div class="mb-3">
      <label>Você possui alguma alergia? Se sim, quais?</label>
      <textarea name="alergias" class="form-control"><?php echo isset($alergias) ? $alergias : ''; ?></textarea>
    </div>
    <div class="mb-3">
      <label>Já fez outras tatuagens? Se sim, descreva.</label>
      <textarea name="historico_tatuagens" class="form-control"><?php echo isset($historico_tatuagens) ? $historico_tatuagens : ''; ?></textarea>
    </div>

    <?php if (isset($cliente_existe) && $cliente_existe): ?>
      <div class="alert alert-info">
        Cliente já cadastrado! O ID do cliente é <?php echo $cliente_id; ?>.
      </div>
    <?php else: ?>
      <button type="submit" class="btn btn-success">Salvar Cadastro</button>
    <?php endif; ?>
  </form>

  <?php if (isset($cliente_existe) && !$cliente_existe): ?>
    <div class="alert alert-success mt-3">
      Cliente cadastrado com sucesso! <a href="public/cadastrar_tatuagem.php?cliente_id=<?php echo $cliente_id; ?>">Clique aqui para adicionar uma tatuagem para o cliente</a>.
    </div>
  <?php endif; ?>
</div>
</body>
</html>

<?php
$conn->close();
?>
