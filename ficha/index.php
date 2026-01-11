<?php include("config/conexao.php"); ?>

<?php
// Função para obter latitude e longitude com base no endereço usando a API fornecida
function obterLatitudeLongitude($endereco) {
    // Formatar a URL para o Nominatim
    $url = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($endereco) . '&format=json&limit=1';

    // Iniciar a requisição cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "SeuAppDeCadastroTatuagem"); // User-Agent é obrigatório no Nominatim
    $resposta = curl_exec($ch);
    curl_close($ch);

    // Verificar se a resposta foi bem-sucedida
    if ($resposta === false) {
        echo "<script>console.log('Erro na requisição cURL');</script>";
        return ['latitude' => null, 'longitude' => null];
    }

    // Decodificar o JSON da resposta
    $dados = json_decode($resposta, true);

    // Verificar e exibir o conteúdo da resposta no console
    echo "<script>console.log('Resposta da API: " . json_encode($dados) . "');</script>";

    if (isset($dados[0]) && !empty($dados[0]['lat']) && !empty($dados[0]['lon'])) {
        $latitude = $dados[0]['lat'];
        $longitude = $dados[0]['lon'];
        echo "<script>console.log('Latitude: $latitude, Longitude: $longitude');</script>";
        return ['latitude' => $latitude, 'longitude' => $longitude];
    } else {
        echo "<script>console.log('Localização não encontrada para o endereço fornecido');</script>";
        return ['latitude' => null, 'longitude' => null];
    }
}

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

        // Exibir o endereço no console para depuração
        echo "<script>console.log('Endereço fornecido: " . $endereco . "');</script>"; // Depuração

        // Obter latitude e longitude com base no endereço
        $localizacao = obterLatitudeLongitude($endereco);
        $latitude = $localizacao['latitude'];
        $longitude = $localizacao['longitude'];

        // Exibir valores de latitude e longitude no console
        echo "<script>console.log('Latitude e Longitude: " . $latitude . ", " . $longitude . "');</script>"; // Depuração

        // Se não encontrou latitude/longitude, podemos definir valores padrão ou gerar erro
        if ($latitude === null || $longitude === null) {
            $latitude = 0; // Ou outro valor padrão
            $longitude = 0; // Ou outro valor padrão
            $erro_localizacao = "Erro ao obter localização do endereço.";
        }

        // Campos de anamnese
        $tem_doencas = $_POST['tem_doencas'];
        $uso_medicamentos = $_POST['uso_medicamentos'];
        $alergias = $_POST['alergias'];
        $historico_tatuagens = $_POST['historico_tatuagens'];

        // Inserir os dados no banco de dados
        $sql = "INSERT INTO clientes (nome, email, telefone, data_nascimento, genero, profissao, endereco, hobbies, estilo_tatuagem, uso_imagem, marcacao, instagram_cliente, latitude, longitude, tem_doencas, uso_medicamentos, alergias, historico_tatuagens) 
                VALUES ('$nome', '$email', '$telefone', '$data_nascimento', '$genero', '$profissao', '$endereco', '$hobbies', '$estilo_tatuagem', '$uso_imagem', '$marcacao', '$instagram_cliente', '$latitude', '$longitude', '$tem_doencas', '$uso_medicamentos', '$alergias', '$historico_tatuagens')";

        if ($conn->query($sql) === TRUE) {
            $cliente_id = $conn->insert_id;
            $cliente_existe = false;
            $sucesso = "Cliente cadastrado com sucesso!";
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
  <script>
    function preencherFormulario() {
      document.getElementById('nome').value = 'João Silva';
      document.getElementById('telefone').value = '11987654321';
      document.getElementById('email').value = 'joao.silva@email.com';
      document.getElementById('data_nascimento').value = '1990-05-10';
      document.getElementById('genero').value = 'Masculino';
      document.getElementById('profissao').value = 'Designer';
      document.getElementById('endereco').value = 'Rua Rio Jutaí, 83, Parque Jurema, Guarulhos';
      document.getElementById('hobbies').value = 'Futebol, Leitura, Música';
      document.getElementById('estilo_tatuagem').value = 'Old School';
      document.getElementById('uso_imagem').checked = true;
      document.getElementById('marcacao').checked = false;
      document.getElementById('instagram_cliente').value = '@joaosilva';
      document.getElementById('tem_doencas').value = 'Não';
      document.getElementById('uso_medicamentos').value = 'Não';
      document.getElementById('alergias').value = 'Nenhuma';
      document.getElementById('historico_tatuagens').value = 'Sim';
    }
  </script>
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
  </nav>

  <div class="container bg-secondary p-4 rounded">
    <h2 class="text-center mb-4">Cadastro de Cliente</h2>

    <button class="btn btn-warning mb-4" onclick="preencherFormulario()">Preencher com Dados Fictícios</button>

    <?php if (isset($sucesso)): ?>
      <div class="alert alert-success">
        <?php echo $sucesso; ?>
      </div>
    <?php elseif (isset($error)): ?>
      <div class="alert alert-danger">
        <?php echo $error; ?>
      </div>
    <?php elseif (isset($erro_localizacao)): ?>
      <div class="alert alert-warning">
        <?php echo $erro_localizacao; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="nome" class="form-label">Nome</label>
          <input type="text" class="form-control" id="nome" name="nome" required>
        </div>
        <div class="col-md-6 mb-3">
          <label for="telefone" class="form-label">Telefone</label>
          <input type="text" class="form-control" id="telefone" name="telefone" required>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="email" class="form-label">E-mail</label>
          <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="col-md-6 mb-3">
          <label for="data_nascimento" class="form-label">Data de Nascimento</label>
          <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" required>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="genero" class="form-label">Gênero</label>
          <select class="form-select" id="genero" name="genero" required>
            <option value="Masculino">Masculino</option>
            <option value="Feminino">Feminino</option>
            <option value="Outro">Outro</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label for="profissao" class="form-label">Profissão</label>
          <input type="text" class="form-control" id="profissao" name="profissao">
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <label for="endereco" class="form-label">Endereço</label>
          <input type="text" class="form-control" id="endereco" name="endereco" required>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <label for="hobbies" class="form-label">Hobbies</label>
          <textarea class="form-control" id="hobbies" name="hobbies"></textarea>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <label for="estilo_tatuagem" class="form-label">Estilo de Tatuagem</label>
          <input type="text" class="form-control" id="estilo_tatuagem" name="estilo_tatuagem">
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <input type="checkbox" id="uso_imagem" name="uso_imagem"> Aceito o uso de minha imagem
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <input type="checkbox" id="marcacao" name="marcacao"> Permito a marcação em redes sociais
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <label for="instagram_cliente" class="form-label">Instagram</label>
          <input type="text" class="form-control" id="instagram_cliente" name="instagram_cliente">
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <label for="tem_doencas" class="form-label">Tem alguma doença?</label>
          <select class="form-select" id="tem_doencas" name="tem_doencas">
            <option value="Não">Não</option>
            <option value="Sim">Sim</option>
          </select>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <label for="uso_medicamentos" class="form-label">Usa algum medicamento?</label>
          <select class="form-select" id="uso_medicamentos" name="uso_medicamentos">
            <option value="Não">Não</option>
            <option value="Sim">Sim</option>
          </select>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <label for="alergias" class="form-label">Tem alergias?</label>
          <textarea class="form-control" id="alergias" name="alergias"></textarea>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12 mb-3">
          <label for="historico_tatuagens" class="form-label">Já tem tatuagens?</label>
          <select class="form-select" id="historico_tatuagens" name="historico_tatuagens">
            <option value="Não">Não</option>
            <option value="Sim">Sim</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-success w-100">Cadastrar</button>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
