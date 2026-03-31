<?php
require 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $stmt = $conn->prepare("UPDATE pipelines SET ordem = :ordem WHERE id = :id");

    foreach ($data as $item) {
        $stmt->bindParam(':ordem', $item['ordem']);
        $stmt->bindParam(':id', $item['id']);
        $stmt->execute();
    }
}