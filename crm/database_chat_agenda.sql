-- Execute este arquivo no phpMyAdmin.
-- Ele cria as tabelas SQL do chat do CRM e adiciona os campos usados pelo agendamento via chat.

CREATE DATABASE IF NOT EXISTS crm_simples CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_simples;

CREATE TABLE IF NOT EXISTS crm_whatsapp_clientes (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    numero VARCHAR(40) NOT NULL,
    nome VARCHAR(150) NOT NULL DEFAULT 'Cliente',
    status VARCHAR(40) NOT NULL DEFAULT 'novo',
    etapa VARCHAR(40) NULL,
    atendente VARCHAR(80) NULL,
    interesse TEXT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    origem VARCHAR(80) NOT NULL DEFAULT 'WhatsApp',
    data_ultimo_contato DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_crm_whatsapp_numero (numero),
    KEY idx_crm_whatsapp_etapa (etapa),
    KEY idx_crm_whatsapp_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_whatsapp_mensagens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cliente_id VARCHAR(80) NOT NULL,
    de VARCHAR(40) NULL,
    texto MEDIUMTEXT NULL,
    data DATETIME NOT NULL,
    from_me TINYINT(1) NOT NULL DEFAULT 0,
    message_id VARCHAR(180) NULL,
    remote_jid VARCHAR(180) NULL,
    status VARCHAR(40) NULL,
    status_updated_at DATETIME NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'texto',
    media_url VARCHAR(255) NULL,
    media_mime VARCHAR(120) NULL,
    media_file_name VARCHAR(255) NULL,
    transcricao MEDIUMTEXT NULL,
    transcricao_erro TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_crm_msg_cliente_data (cliente_id, data),
    KEY idx_crm_msg_message_id (message_id),
    KEY idx_crm_msg_remote_jid (remote_jid),
    CONSTRAINT fk_crm_msg_cliente
        FOREIGN KEY (cliente_id) REFERENCES crm_whatsapp_clientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Banco da ficha/agenda.
CREATE DATABASE IF NOT EXISTS tatuagem_novo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tatuagem_novo;

ALTER TABLE tatuagens
    ADD COLUMN IF NOT EXISTS observacoes TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS pomadas_anestesicas INT UNSIGNED NOT NULL DEFAULT 0 AFTER observacoes,
    ADD COLUMN IF NOT EXISTS referencia_arte VARCHAR(255) NULL AFTER pomadas_anestesicas;

CREATE INDEX IF NOT EXISTS idx_tatuagens_status ON tatuagens (status);
CREATE INDEX IF NOT EXISTS idx_tatuagens_referencia_cliente ON tatuagens (cliente_id, data_tatuagem);
