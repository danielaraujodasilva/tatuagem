<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = $_POST['id'] ?? 0;
    $nome = $_POST['nome'] ?? '';
    $ordem = $_POST['ordem'] ?? 0;
    $cor = $_POST['cor'] ?? '#ffffff';

    if (!$id || !$nome) {
        die("Dados inválidos");
    }

    try {
        $stmt = $conn->prepare("
            UPDATE pipelines 
            SET nome = :nome, ordem = :ordem, cor = :cor 
            WHERE id = :id
        ");

        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':ordem', $ordem);
        $stmt->bindParam(':cor', $cor);
        $stmt->bindParam(':id', $id);

        $stmt->execute();

        header('Location: configuracoes.php');
        exit;

    } catch (PDOException $e) {
        die("Erro ao atualizar: " . $e->getMessage());
    }
}