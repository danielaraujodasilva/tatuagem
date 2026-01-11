<?php
if (!isset($_GET['id'])) {
    die("Cliente não especificado.");
}

$id = (int) $_GET['id'];

?>

<?php include("config/conexao.php"); ?>

<?php

$sql = "SELECT * FROM clientes WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Cliente não encontrado.");
}

$cliente = $result->fetch_assoc();

// Supondo que você tenha uma tabela 'tatuagens' relacionada
$sql_tatuagens = "SELECT * FROM tatuagens WHERE cliente_id = $id";
$result_tatuagens = $conn->query($sql_tatuagens);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Detalhes de <?php echo $cliente['nome']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
    </style>
</head>
<body>

<h2>Detalhes do Cliente</h2>
<p><strong>Nome:</strong> <?php echo $cliente['nome']; ?></p>
<p><strong>Telefone:</strong> <?php echo $cliente['telefone']; ?></p>
<p><strong>Email:</strong> <?php echo $cliente['email']; ?></p>
<p><strong>Data de Nascimento:</strong> <?php echo $cliente['data_nascimento']; ?></p>
<p><strong>Gênero:</strong> <?php echo $cliente['genero']; ?></p>
<p><strong>Profissão:</strong> <?php echo $cliente['profissao']; ?></p>
<p><strong>Endereço:</strong> <?php echo $cliente['endereco']; ?></p>
<p><strong>Hobbies:</strong> <?php echo $cliente['hobbies']; ?></p>
<p><strong>Estilo de Tatuagem:</strong> <?php echo $cliente['estilo_tatuagem']; ?></p>
<p><strong>Instagram:</strong> <?php echo $cliente['instagram_cliente']; ?></p>

<h3>Tatuagens Realizadas</h3>
<?php if ($result_tatuagens->num_rows > 0): ?>
    <ul>
        <?php while ($tattoo = $result_tatuagens->fetch_assoc()): ?>
            <li>
                <strong>Data:</strong> <?php echo $tattoo['data']; ?> |
                <strong>Estilo:</strong> <?php echo $tattoo['estilo']; ?> |
                <strong>Descrição:</strong> <?php echo $tattoo['descricao']; ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>Nenhuma tatuagem registrada.</p>
<?php endif; ?>

<a href="mapa_clientes.php">← Voltar ao Mapa</a>

</body>
</html>
