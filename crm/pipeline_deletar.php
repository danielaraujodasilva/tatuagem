<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require 'config.php';

$id = $_GET['id'] ?? 0;

if (!$id) {
    die("ID inválido");
}

try {
    // 🔍 Buscar a primeira etapa disponível (menor ordem)
    $stmt = $conn->query("SELECT id FROM pipelines ORDER BY ordem ASC LIMIT 1");
    $firstStage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$firstStage) {
        die("Nenhuma etapa encontrada no pipeline");
    }

    $novaEtapa = $firstStage['id'];

    // ⚠️ Evita mover pra ela mesma caso esteja deletando a primeira
    if ($novaEtapa == $id) {
        // pega a próxima etapa
        $stmt = $conn->prepare("SELECT id FROM pipelines WHERE id != :id ORDER BY ordem ASC LIMIT 1");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $nextStage = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nextStage) {
            die("Não é possível excluir a única etapa existente");
        }

        $novaEtapa = $nextStage['id'];
    }

    // 🔄 Move os leads para a nova etapa válida
    $stmt = $conn->prepare("
        UPDATE leads 
        SET etapa_funil = :nova 
        WHERE etapa_funil = :antiga
    ");

    $stmt->bindParam(':nova', $novaEtapa);
    $stmt->bindParam(':antiga', $id);
    $stmt->execute();

    // 🗑️ Deleta a etapa
    $stmt = $conn->prepare("DELETE FROM pipelines WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    header('Location: configuracoes.php');
    exit;

} catch (PDOException $e) {
    die("Erro ao deletar: " . $e->getMessage());
}