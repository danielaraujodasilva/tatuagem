CREATE DATABASE crm_simples;
USE crm_simples;

CREATE TABLE pipeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_etapa VARCHAR(100),
    ordem INT
);

INSERT INTO pipeline (nome_etapa, ordem) VALUES
('Lead entrou',1),
('Contato feito',2),
('Proposta',3),
('Fechado',4);

CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    telefone VARCHAR(20),
    interesse VARCHAR(255),
    valor DECIMAL(10,2),
    origem VARCHAR(100),
    status VARCHAR(50),
    etapa_funil INT,
    ultimo_contato DATETIME
);

INSERT INTO leads (nome, telefone, etapa_funil) VALUES
('Cliente Teste','5511999999999',1);

CREATE TABLE interacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT,
    mensagem TEXT,
    tipo VARCHAR(10),
    data DATETIME
);
