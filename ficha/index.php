<?php
require __DIR__ . '/config/conexao.php';

function posted(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function isChecked(string $key): bool
{
    return isset($_POST[$key]);
}

$feedback = null;
$feedbackType = 'success';
$clienteId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = posted('nome');
    $email = posted('email');
    $telefone = posted('telefone');
    $dataNascimento = posted('data_nascimento');
    $genero = posted('genero');
    $profissao = posted('profissao');
    $endereco = posted('endereco');
    $hobbies = posted('hobbies');
    $estiloTatuagem = posted('estilo_tatuagem');
    $instagram = posted('instagram_cliente');
    $usoImagem = isChecked('uso_imagem') ? 1 : 0;
    $marcacao = isChecked('marcacao') ? 1 : 0;
    $temDoencas = posted('tem_doencas');
    $usoMedicamentos = posted('uso_medicamentos');
    $alergias = posted('alergias');
    $historico = posted('historico_tatuagens');

    if ($nome === '' || $email === '' || $telefone === '') {
        $feedback = 'Preencha pelo menos nome, e-mail e telefone para salvar a ficha.';
        $feedbackType = 'danger';
    } else {
        $stmt = $conn->prepare('SELECT id FROM clientes WHERE nome = ? AND telefone = ? LIMIT 1');
        $stmt->bind_param('ss', $nome, $telefone);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $clienteId = (int) $existing['id'];
            $feedback = 'Este cliente ja existe. Voce pode abrir a agenda ou cadastrar uma nova tatuagem para ele.';
            $feedbackType = 'info';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO clientes (
                    nome, email, telefone, data_nascimento, genero, profissao, endereco, hobbies,
                    estilo_tatuagem, uso_imagem, autorizou_uso_imagem, marcacao, instagram_cliente,
                    tem_doencas, uso_medicamentos, alergias, historico_tatuagens
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'sssssssssiiisssss',
                $nome,
                $email,
                $telefone,
                $dataNascimento,
                $genero,
                $profissao,
                $endereco,
                $hobbies,
                $estiloTatuagem,
                $usoImagem,
                $usoImagem,
                $marcacao,
                $instagram,
                $temDoencas,
                $usoMedicamentos,
                $alergias,
                $historico
            );
            $stmt->execute();
            $clienteId = $stmt->insert_id;
            $stmt->close();

            $feedback = 'Cliente cadastrado com sucesso.';
            $feedbackType = 'success';
        }
    }
}

$totalClientes = (int) $conn->query('SELECT COUNT(*) AS total FROM clientes')->fetch_assoc()['total'];
$totalTatuagens = (int) $conn->query('SELECT COUNT(*) AS total FROM tatuagens')->fetch_assoc()['total'];
$proximas = (int) $conn->query("SELECT COUNT(*) AS total FROM tatuagens WHERE data_tatuagem >= CURDATE() AND status <> 'cancelado'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ficha de Cliente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame">
      <header class="ficha-hero">
        <span class="ficha-kicker">Ficha de cliente</span>
        <h1>Cadastro e anamnese de clientes</h1>
        <p>Organize o historico do atendimento, registre observacoes relevantes e acelere o caminho entre a primeira conversa e o agendamento.</p>
        <div class="ficha-nav">
          <a class="btn ficha-btn ficha-btn-secondary" href="public/clientes.php">Clientes</a>
          <a class="btn ficha-btn ficha-btn-secondary" href="public/cadastrar_tatuagem.php">Cadastrar tatuagem</a>
          <a class="btn ficha-btn ficha-btn-warning" href="agenda/">Agenda</a>
          <a class="btn ficha-btn ficha-btn-secondary" href="mapa_clientes.php">Mapa de clientes</a>
        </div>
      </header>

      <div class="ficha-content">
        <div class="ficha-stats mb-4">
          <div class="ficha-stat">
            <span>Clientes cadastrados</span>
            <strong><?php echo $totalClientes; ?></strong>
          </div>
          <div class="ficha-stat">
            <span>Tatuagens registradas</span>
            <strong><?php echo $totalTatuagens; ?></strong>
          </div>
          <div class="ficha-stat">
            <span>Proximos atendimentos</span>
            <strong><?php echo $proximas; ?></strong>
          </div>
        </div>

        <?php if ($feedback): ?>
          <div class="ficha-alert ficha-alert-<?php echo $feedbackType === 'info' ? 'info' : $feedbackType; ?> mb-4">
            <div><?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if ($clienteId): ?>
              <div class="mt-3 d-flex gap-2 flex-wrap">
                <a class="btn ficha-btn ficha-btn-primary" href="public/cadastrar_tatuagem.php?cliente_id=<?php echo $clienteId; ?>">Adicionar tatuagem para este cliente</a>
                <a class="btn ficha-btn ficha-btn-secondary" href="detalhes_cliente.php?id=<?php echo $clienteId; ?>">Abrir detalhes do cliente</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="ficha-panel">
          <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
            <div>
              <h2 class="ficha-panel-title">Dados principais</h2>
              <p class="ficha-copy mb-0">As informacoes essenciais para localizar, atender e reutilizar o historico desse cliente.</p>
            </div>
            <div class="ficha-chip">Fluxo: cadastro > tatuagem > agenda</div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="ficha-form-label">Nome</label>
              <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars(posted('nome'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">E-mail</label>
              <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars(posted('email'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label">Telefone</label>
              <input type="text" name="telefone" class="form-control" required value="<?php echo htmlspecialchars(posted('telefone'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label">Data de nascimento</label>
              <input type="date" name="data_nascimento" class="form-control" value="<?php echo htmlspecialchars(posted('data_nascimento'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label">Genero</label>
              <select name="genero" class="form-select">
                <option value="">Selecione</option>
                <?php foreach (['Masculino', 'Feminino', 'Outro'] as $opcao): ?>
                  <option value="<?php echo $opcao; ?>" <?php echo posted('genero') === $opcao ? 'selected' : ''; ?>><?php echo $opcao; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Profissao</label>
              <input type="text" name="profissao" class="form-control" value="<?php echo htmlspecialchars(posted('profissao'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Instagram</label>
              <input type="text" name="instagram_cliente" class="form-control" value="<?php echo htmlspecialchars(posted('instagram_cliente'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-12">
              <label class="ficha-form-label">Endereco</label>
              <input type="text" name="endereco" class="form-control" value="<?php echo htmlspecialchars(posted('endereco'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Hobbies</label>
              <textarea name="hobbies" class="form-control" rows="3"><?php echo htmlspecialchars(posted('hobbies'), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Estilo de tatuagem favorito</label>
              <textarea name="estilo_tatuagem" class="form-control" rows="3"><?php echo htmlspecialchars(posted('estilo_tatuagem'), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          </div>

          <hr class="my-4">

          <div class="mb-3">
            <h2 class="ficha-panel-title">Anamnese</h2>
            <p class="ficha-copy mb-0">Campo livre para registrar informacoes que influenciem o atendimento, o preparo e os cuidados posteriores.</p>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="ficha-form-label">Possui alguma doenca preexistente?</label>
              <textarea name="tem_doencas" class="form-control" rows="3"><?php echo htmlspecialchars(posted('tem_doencas'), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Usa algum medicamento atualmente?</label>
              <textarea name="uso_medicamentos" class="form-control" rows="3"><?php echo htmlspecialchars(posted('uso_medicamentos'), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Tem alergias?</label>
              <textarea name="alergias" class="form-control" rows="3"><?php echo htmlspecialchars(posted('alergias'), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Historico de outras tatuagens</label>
              <textarea name="historico_tatuagens" class="form-control" rows="3"><?php echo htmlspecialchars(posted('historico_tatuagens'), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="uso_imagem" id="uso_imagem" <?php echo isChecked('uso_imagem') ? 'checked' : ''; ?>>
                <label class="form-check-label" for="uso_imagem">Autorizo o uso de fotos e videos.</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="marcacao" id="marcacao" <?php echo isChecked('marcacao') ? 'checked' : ''; ?>>
                <label class="form-check-label" for="marcacao">Gostaria de ser marcado nas redes sociais.</label>
              </div>
            </div>
          </div>

          <div class="d-flex flex-column flex-md-row gap-3 mt-4">
            <button type="submit" class="btn ficha-btn ficha-btn-primary flex-fill">Salvar cadastro</button>
            <a href="public/cadastrar_tatuagem.php" class="btn ficha-btn ficha-btn-secondary flex-fill">Ir para cadastro de tatuagem</a>
          </div>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
