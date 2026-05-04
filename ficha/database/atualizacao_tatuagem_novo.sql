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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NOT NULL DEFAULT '' AFTER nome,
    ADD COLUMN IF NOT EXISTS telefone VARCHAR(40) NOT NULL DEFAULT '' AFTER email,
    ADD COLUMN IF NOT EXISTS data_nascimento DATE NULL AFTER telefone,
    ADD COLUMN IF NOT EXISTS genero VARCHAR(40) NULL AFTER data_nascimento,
    ADD COLUMN IF NOT EXISTS profissao VARCHAR(120) NULL AFTER genero,
    ADD COLUMN IF NOT EXISTS endereco VARCHAR(255) NULL AFTER profissao,
    ADD COLUMN IF NOT EXISTS hobbies TEXT NULL AFTER endereco,
    ADD COLUMN IF NOT EXISTS estilo_tatuagem VARCHAR(120) NULL AFTER hobbies,
    ADD COLUMN IF NOT EXISTS instagram_cliente VARCHAR(150) NULL AFTER estilo_tatuagem,
    ADD COLUMN IF NOT EXISTS uso_imagem TINYINT(1) NOT NULL DEFAULT 0 AFTER instagram_cliente,
    ADD COLUMN IF NOT EXISTS autorizou_uso_imagem TINYINT(1) NOT NULL DEFAULT 0 AFTER uso_imagem,
    ADD COLUMN IF NOT EXISTS marcacao TINYINT(1) NOT NULL DEFAULT 0 AFTER autorizou_uso_imagem,
    ADD COLUMN IF NOT EXISTS vai_tatuar VARCHAR(10) NULL AFTER marcacao,
    ADD COLUMN IF NOT EXISTS tem_doencas TEXT NULL AFTER vai_tatuar,
    ADD COLUMN IF NOT EXISTS uso_medicamentos TEXT NULL AFTER tem_doencas,
    ADD COLUMN IF NOT EXISTS alergias TEXT NULL AFTER uso_medicamentos,
    ADD COLUMN IF NOT EXISTS historico_tatuagens TEXT NULL AFTER alergias,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER historico_tatuagens;

CREATE INDEX IF NOT EXISTS idx_clientes_nome ON clientes (nome);
CREATE INDEX IF NOT EXISTS idx_clientes_telefone ON clientes (telefone);

CREATE TABLE IF NOT EXISTS tatuagens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    data_tatuagem DATE NOT NULL,
    hora_inicio TIME NULL,
    hora_fim TIME NULL,
    status ENUM('agendado', 'confirmado', 'cancelado', 'concluido') NOT NULL DEFAULT 'agendado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE tatuagens
    ADD COLUMN IF NOT EXISTS cliente_id INT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS descricao VARCHAR(255) NOT NULL DEFAULT '' AFTER cliente_id,
    ADD COLUMN IF NOT EXISTS valor DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER descricao,
    ADD COLUMN IF NOT EXISTS data_tatuagem DATE NULL AFTER valor,
    ADD COLUMN IF NOT EXISTS hora_inicio TIME NULL AFTER data_tatuagem,
    ADD COLUMN IF NOT EXISTS hora_fim TIME NULL AFTER hora_inicio,
    ADD COLUMN IF NOT EXISTS status ENUM('agendado', 'confirmado', 'cancelado', 'concluido') NOT NULL DEFAULT 'agendado' AFTER hora_fim,
    ADD COLUMN IF NOT EXISTS observacoes TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS pomadas_anestesicas INT UNSIGNED NOT NULL DEFAULT 0 AFTER observacoes,
    ADD COLUMN IF NOT EXISTS referencia_arte VARCHAR(255) NULL AFTER pomadas_anestesicas,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER referencia_arte;

CREATE INDEX IF NOT EXISTS idx_tatuagens_cliente ON tatuagens (cliente_id);
CREATE INDEX IF NOT EXISTS idx_tatuagens_data ON tatuagens (data_tatuagem);

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL,
    username VARCHAR(80) NOT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL DEFAULT '',
    telefone VARCHAR(40) NOT NULL DEFAULT '',
    senha_hash VARCHAR(255) NOT NULL,
    role ENUM('cliente', 'funcionario', 'adm') NOT NULL DEFAULT 'cliente',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_username (username),
    KEY idx_usuarios_email (email),
    KEY idx_usuarios_telefone (telefone),
    KEY idx_usuarios_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS senha_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_senha_resets_token (token_hash),
    KEY idx_senha_resets_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
