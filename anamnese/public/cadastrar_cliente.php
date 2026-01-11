<?php
include("../config/conexao.php");

$nome = $_POST['nome'];
$telefone = $_POST['telefone'];
$vai_tatuar = $_POST['vai_tatuar'];
$email = $_POST['email'];
$data_nascimento = $_POST['data_nascimento'];
$genero = $_POST['genero'];
$profissao = $_POST['profissao'];
$endereco = $_POST['endereco'];
$hobbies = $_POST['hobbies'];
$estilo_tatuagem = $_POST['estilo_tatuagem'];
$autorizou_uso_imagem = isset($_POST['autorizou_uso_imagem']) ? 1 : 0;
$instagram_cliente = $_POST['instagram_cliente'];

$sql = "INSERT INTO clientes 
(nome, telefone, vai_tatuar, email, data_nascimento, genero, profissao, endereco, hobbies, estilo_tatuagem, autorizou_uso_imagem, instagram_cliente)
VALUES
('$nome', '$telefone', '$vai_tatuar', '$email', '$data_nascimento', '$genero', '$profissao', '$endereco', '$hobbies', '$estilo_tatuagem', '$autorizou_uso_imagem', '$instagram_cliente')";

if ($conn->query($sql) === TRUE) {
  $cliente_id = $conn->insert_id;
  if ($vai_tatuar == 'sim') {
    header("Location: cadastrar_tatuagem.php?cliente_id=$cliente_id");
  } else {
    echo "Cliente cadastrado com sucesso! <a href='../index.php'>Voltar</a>";
  }
} else {
  echo "Erro: " . $sql . "<br>" . $conn->error;
}
$conn->close();
?>
