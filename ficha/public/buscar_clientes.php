<?php
include("../config/conexao.php");

if (isset($_GET['busca'])) {
  $busca = $conn->real_escape_string($_GET['busca']);
  $sql = "SELECT id, nome, telefone, email FROM clientes 
          WHERE nome LIKE '%$busca%' OR telefone LIKE '%$busca%' OR email LIKE '%$busca%'
          LIMIT 10";

  $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    while ($cliente = $result->fetch_assoc()) {
      echo "<div class='autocomplete-suggestion' data-id='".$cliente['id']."'>"
            .$cliente['nome']." - ".$cliente['telefone']." - ".$cliente['email'].
            "</div>";
    }
  } else {
    echo "<div class='autocomplete-suggestion'>Nenhum cliente encontrado</div>";
  }
}
?>
