CREATE DATABASE IF NOT EXISTS tatuagem_novo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tatuagem_novo;

CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefone VARCHAR(40) NOT NULL,
    data_nascimento DATE NULL,
    genero VARCHAR(40) NULL,
    profissao VARCHAR(120) NULL,
    endereco VARCHAR(255) NULL,
    hobbies TEXT NULL,
    estilo_tatuagem VARCHAR(120) NULL,
    instagram_cliente VARCHAR(150) NULL,
    uso_imagem TINYINT(1) NOT NULL DEFAULT 0,
    autorizou_uso_imagem TINYINT(1) NOT NULL DEFAULT 0,
    marcacao TINYINT(1) NOT NULL DEFAULT 0,
    vai_tatuar VARCHAR(10) NULL,
    tem_doencas TEXT NULL,
    uso_medicamentos TEXT NULL,
    alergias TEXT NULL,
    historico_tatuagens TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_clientes_nome (nome),
    KEY idx_clientes_telefone (telefone)
);

CREATE TABLE IF NOT EXISTS tatuagens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    data_tatuagem DATE NOT NULL,
    hora_inicio TIME NULL,
    hora_fim TIME NULL,
    status ENUM('agendado', 'confirmado', 'cancelado', 'concluido') NOT NULL DEFAULT 'agendado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tatuagens_cliente (cliente_id),
    KEY idx_tatuagens_data (data_tatuagem),
    CONSTRAINT fk_tatuagens_clientes
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE
);
