<?php
require __DIR__ . '/config/conexao.php';

function publicPosted(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function publicIsChecked(string $key): bool
{
    return isset($_POST[$key]);
}

function publicDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

$clienteId = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : (int)($_POST['cliente_id'] ?? 0);
$telefoneUrl = trim((string)($_GET['telefone'] ?? ''));
$cliente = null;
$feedback = null;
$feedbackType = 'success';

if ($clienteId > 0) {
    $stmt = $conn->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($telefoneUrl !== '') {
    $digits = '%' . publicDigits($telefoneUrl) . '%';
    $stmt = $conn->prepare('
        SELECT * FROM clientes
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, " ", ""), "-", ""), "(", ""), ")", "") LIKE ?
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->bind_param('s', $digits);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $clienteId = $cliente ? (int)$cliente['id'] : 0;
}

function publicValue(string $key, ?array $cliente, string $default = ''): string
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return publicPosted($key, $default);
    }

    return (string)($cliente[$key] ?? $default);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = publicPosted('nome');
    $email = publicPosted('email');
    $telefone = publicPosted('telefone');
    $dataNascimento = publicPosted('data_nascimento');
    $genero = publicPosted('genero');
    $profissao = publicPosted('profissao');
    $endereco = publicPosted('endereco');
    $hobbies = publicPosted('hobbies');
    $estiloTatuagem = publicPosted('estilo_tatuagem');
    $instagram = publicPosted('instagram_cliente');
    $usoImagem = publicIsChecked('uso_imagem') ? 1 : 0;
    $marcacao = publicIsChecked('marcacao') ? 1 : 0;
    $temDoencas = publicPosted('tem_doencas');
    $usoMedicamentos = publicPosted('uso_medicamentos');
    $alergias = publicPosted('alergias');
    $historico = publicPosted('historico_tatuagens');

    if ($nome === '' || $email === '' || $telefone === '') {
        $feedback = 'Preencha nome, e-mail e telefone para concluir a ficha.';
        $feedbackType = 'danger';
    } else {
        if ($clienteId <= 0) {
            $stmt = $conn->prepare('
                SELECT id FROM clientes
                WHERE telefone = ? OR (nome = ? AND telefone = ?)
                LIMIT 1
            ');
            $stmt->bind_param('sss', $telefone, $nome, $telefone);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $clienteId = $existing ? (int)$existing['id'] : 0;
        }

        if ($clienteId > 0) {
            $stmt = $conn->prepare(
                'UPDATE clientes SET
                    nome = ?, email = ?, telefone = ?, data_nascimento = ?, genero = ?, profissao = ?,
                    endereco = ?, hobbies = ?, estilo_tatuagem = ?, uso_imagem = ?,
                    autorizou_uso_imagem = ?, marcacao = ?, instagram_cliente = ?,
                    tem_doencas = ?, uso_medicamentos = ?, alergias = ?, historico_tatuagens = ?
                WHERE id = ?'
            );
            $stmt->bind_param(
                'sssssssssiiisssssi',
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
                $historico,
                $clienteId
            );
            $stmt->execute();
            $stmt->close();
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
            $clienteId = (int)$stmt->insert_id;
            $stmt->close();
        }

        $feedback = 'Ficha enviada com sucesso. Obrigado!';
        $feedbackType = 'success';
        $stmt = $conn->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $cliente = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
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
        <h1>Complete seu cadastro</h1>
        <p>Preencha suas informacoes para deixar tudo pronto antes do atendimento.</p>
      </header>

      <div class="ficha-content">
        <?php if ($feedback): ?>
          <div class="ficha-alert ficha-alert-<?php echo $feedbackType; ?> mb-4">
            <?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>

        <form method="post" class="ficha-panel">
          <input type="hidden" name="cliente_id" value="<?php echo (int)$clienteId; ?>">
          <div class="mb-4">
            <h2 class="ficha-panel-title">Dados principais</h2>
            <p class="ficha-copy mb-0">Esses dados ajudam a organizar seu atendimento e seu historico.</p>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="ficha-form-label">Nome</label>
              <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars(publicValue('nome', $cliente), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">E-mail</label>
              <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars(publicValue('email', $cliente), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label">Telefone</label>
              <input type="text" name="telefone" class="form-control" required value="<?php echo htmlspecialchars(publicValue('telefone', $cliente, $telefoneUrl), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label">Data de nascimento</label>
              <input type="date" name="data_nascimento" class="form-control" value="<?php echo htmlspecialchars(publicValue('data_nascimento', $cliente), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4">
              <label class="ficha-form-label">Genero</label>
              <select name="genero" class="form-select">
                <option value="">Selecione</option>
                <?php foreach (['Masculino', 'Feminino', 'Outro'] as $opcao): ?>
                  <option value="<?php echo $opcao; ?>" <?php echo publicValue('genero', $cliente) === $opcao ? 'selected' : ''; ?>><?php echo $opcao; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Profissao</label>
              <input type="text" name="profissao" class="form-control" value="<?php echo htmlspecialchars(publicValue('profissao', $cliente), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Instagram</label>
              <input type="text" name="instagram_cliente" class="form-control" value="<?php echo htmlspecialchars(publicValue('instagram_cliente', $cliente), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-12">
              <label class="ficha-form-label">Endereco</label>
              <input type="text" name="endereco" class="form-control" value="<?php echo htmlspecialchars(publicValue('endereco', $cliente), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Hobbies</label>
              <textarea name="hobbies" class="form-control" rows="3"><?php echo htmlspecialchars(publicValue('hobbies', $cliente), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Estilo de tatuagem favorito</label>
              <textarea name="estilo_tatuagem" class="form-control" rows="3"><?php echo htmlspecialchars(publicValue('estilo_tatuagem', $cliente), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          </div>

          <hr class="my-4">

          <div class="mb-3">
            <h2 class="ficha-panel-title">Anamnese</h2>
            <p class="ficha-copy mb-0">Conte informacoes importantes para um atendimento mais seguro.</p>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="ficha-form-label">Possui alguma doenca preexistente?</label>
              <textarea name="tem_doencas" class="form-control" rows="3"><?php echo htmlspecialchars(publicValue('tem_doencas', $cliente), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Usa algum medicamento atualmente?</label>
              <textarea name="uso_medicamentos" class="form-control" rows="3"><?php echo htmlspecialchars(publicValue('uso_medicamentos', $cliente), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Tem alergias?</label>
              <textarea name="alergias" class="form-control" rows="3"><?php echo htmlspecialchars(publicValue('alergias', $cliente), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="ficha-form-label">Historico de outras tatuagens</label>
              <textarea name="historico_tatuagens" class="form-control" rows="3"><?php echo htmlspecialchars(publicValue('historico_tatuagens', $cliente), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="uso_imagem" id="uso_imagem" <?php echo publicValue('uso_imagem', $cliente) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="uso_imagem">Autorizo o uso de fotos e videos.</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="marcacao" id="marcacao" <?php echo publicValue('marcacao', $cliente) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="marcacao">Gostaria de ser marcado nas redes sociais.</label>
              </div>
            </div>
          </div>

          <div class="d-flex mt-4">
            <button type="submit" class="btn ficha-btn ficha-btn-primary flex-fill">Enviar ficha</button>
          </div>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
