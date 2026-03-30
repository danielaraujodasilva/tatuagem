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

// Etapas do pipeline (você pode adicionar/remover aqui)
$stages = [
    'novo'              => '🆕 Novo',
    'contato'           => '📞 Contato Inicial',
    'reuniao'           => '🤝 Reunião',
    'proposta'          => '📄 Proposta Enviada',
    'negociacao'        => '💰 Negociação',
    'ganho'             => '✅ Fechado - Ganho',
    'perdido'           => '❌ Fechado - Perdido'
];