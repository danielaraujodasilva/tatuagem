<?php
// ==================== CONFIGURAÇÃO ====================
$host = 'localhost';
$db   = 'crm_simples';           // ← mude pro nome do seu banco
$user = 'root';                // ← usuário do MySQL
$pass = '';                    // ← senha (deixe vazio se for local)

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Etapas do pipeline - ajuste os valores conforme sua tabela
$stages = [
    '1' => '🆕 Novo',
    '2' => '📞 Contato Inicial',
    '3' => '🤝 Reunião',
    '4' => '📄 Proposta Enviada',
    '5' => '💰 Negociação',
    '6' => '✅ Fechado - Ganho',
    '7' => '❌ Fechado - Perdido'
];
