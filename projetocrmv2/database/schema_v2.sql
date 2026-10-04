-- projetocrmv2 - schema incremental e seguro.
-- Cria SOMENTE tabelas novas (prefixo v2_). Nao altera nem apaga nada existente.
-- Rode no phpMyAdmin, no banco do CRM (crm_simples).

CREATE TABLE IF NOT EXISTS v2_clientes (
    id VARCHAR(60) NOT NULL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL DEFAULT '',
    telefone VARCHAR(40) NOT NULL DEFAULT '',
    telefone_norm VARCHAR(40) NOT NULL DEFAULT '',
    email VARCHAR(150) NOT NULL DEFAULT '',
    origem VARCHAR(80) NOT NULL DEFAULT 'WhatsApp',
    status VARCHAR(40) NOT NULL DEFAULT 'novo',
    etapa VARCHAR(40) NULL,
    interesse TEXT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    atendente VARCHAR(80) NULL,
    modo_atendimento VARCHAR(20) NOT NULL DEFAULT 'bot',
    wa_cliente_id VARCHAR(80) NULL,
    ficha_cliente_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_v2_cli_telefone (telefone_norm),
    KEY idx_v2_cli_status (status),
    KEY idx_v2_cli_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cliente_id VARCHAR(60) NOT NULL,
    tipo VARCHAR(40) NOT NULL,          -- mensagem | lead | orcamento | agendamento | sessao | pagamento | tarefa | nota
    titulo VARCHAR(180) NOT NULL DEFAULT '',
    descricao TEXT NULL,
    origem VARCHAR(40) NOT NULL DEFAULT 'sistema',
    valor DECIMAL(10,2) NULL,
    ref_tabela VARCHAR(60) NULL,
    ref_id VARCHAR(80) NULL,
    aconteceu_em DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_v2_ev_cliente (cliente_id, aconteceu_em),
    KEY idx_v2_ev_tipo (tipo, aconteceu_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_tarefas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cliente_id VARCHAR(60) NULL,
    regra_id VARCHAR(60) NULL,
    titulo VARCHAR(180) NOT NULL,
    mensagem TEXT NULL,
    canal VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
    responder_com_audio TINYINT(1) NOT NULL DEFAULT 0,
    previsto_para DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',   -- pendente | aprovada | enviada | cancelada
    precisa_aprovacao TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_v2_tarefa_data (status, previsto_para),
    KEY idx_v2_tarefa_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_regras (
    id VARCHAR(60) NOT NULL PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    evento VARCHAR(40) NOT NULL,        -- novo_contato | sem_resposta | antes_da_sessao | sessao_concluida | dias_apos_sessao | aniversario
    acao VARCHAR(40) NOT NULL,          -- responder | enviar_mensagem | criar_tarefa | alertar
    atraso_horas INT NOT NULL DEFAULT 0,
    status_destino VARCHAR(40) NULL,
    mensagem TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_aprendizado (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(40) NOT NULL,          -- padrao_voz | objecao | preco | estilo
    chave VARCHAR(120) NOT NULL DEFAULT '',
    valor TEXT NULL,
    ocorrencias INT NOT NULL DEFAULT 1,
    aprovado TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_v2_apr_tipo_chave (tipo, chave),
    KEY idx_v2_apr_tipo (tipo, aprovado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
