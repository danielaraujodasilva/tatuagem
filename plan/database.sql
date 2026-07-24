CREATE DATABASE IF NOT EXISTS plan_financeiro
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE plan_financeiro;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  color VARCHAR(16) NOT NULL DEFAULT '#2563eb'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'corrente',
  opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  source_key VARCHAR(120) NULL,
  source_updated_at DATETIME NULL,
  last_manual_edit_at DATETIME NULL,
  last_imported_at DATETIME NULL,
  last_change_source ENUM('manual','sheet') NOT NULL DEFAULT 'manual',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  source_mode VARCHAR(20) NOT NULL DEFAULT 'manual',
  source_updated_at DATETIME NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  changes_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_account_versions_account_date (account_id, created_at),
  INDEX idx_account_versions_user_date (user_id, created_at),
  CONSTRAINT fk_account_versions_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_account_versions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_import_conflicts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NULL,
  import_key VARCHAR(120) NULL,
  source_updated_at DATETIME NULL,
  payload_json JSON NOT NULL,
  current_json JSON NULL,
  conflict_reason VARCHAR(255) NOT NULL,
  resolution VARCHAR(32) NULL,
  resolved_by INT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_account_conflicts_account_date (account_id, created_at),
  INDEX idx_account_conflicts_resolution (resolution, created_at),
  CONSTRAINT fk_account_conflicts_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_account_conflicts_user FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('income','expense','transfer') NOT NULL DEFAULT 'expense',
  amount DECIMAL(12,2) NOT NULL,
  description VARCHAR(220) NOT NULL,
  category_id INT UNSIGNED NULL,
  account_id INT UNSIGNED NULL,
  due_date DATE NULL,
  paid_at DATE NULL,
  status ENUM('pending','paid','late','ignored') NOT NULL DEFAULT 'pending',
  owner VARCHAR(80) NULL,
  payment_code TEXT NULL,
  source_sheet VARCHAR(80) NULL,
  notes TEXT NULL,
  is_fixed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_transactions_due_status (due_date, status),
  INDEX idx_transactions_category (category_id),
  INDEX idx_transactions_account (account_id),
  CONSTRAINT fk_transactions_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_transactions_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  month CHAR(7) NOT NULL,
  limit_amount DECIMAL(12,2) NOT NULL,
  UNIQUE KEY uniq_budget_category_month (category_id, month),
  CONSTRAINT fk_budgets_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  target_amount DECIMAL(12,2) NOT NULL,
  current_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  target_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recurring_rules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(220) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  category_id INT UNSIGNED NULL,
  frequency ENUM('weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
  next_due_date DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recurring_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  changes_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_user_date (user_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, password_hash, is_active)
VALUES ('Daniel Araujo da Silva', 'danielaraujodasilva@gmail.com', '$2y$10$gUYC1izF2GaU5LZuMIx3EO0xvgAmM5CxJKTmsoQ7Avwj.7keVc1V.', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), is_active = VALUES(is_active);

INSERT INTO categories (name, color) VALUES
('Moradia', '#2563eb'),
('Pessoal', '#7c3aed'),
('Saude', '#059669'),
('Alimentacao', '#ea580c'),
('Transporte', '#0891b2'),
('Educacao', '#9333ea'),
('Impostos', '#dc2626'),
('Servicos', '#0f766e'),
('Investimentos', '#16a34a'),
('Sem categoria', '#64748b')
ON DUPLICATE KEY UPDATE color = VALUES(color);

INSERT INTO accounts (name, type, opening_balance) VALUES
('Conta principal', 'corrente', 0.00),
('Cartao Daniel', 'credito', 0.00),
('Cartao Fran', 'credito', 0.00),
('Carteira investimentos', 'investimento', 0.00);

INSERT INTO transactions
(type, amount, description, category_id, account_id, due_date, paid_at, status, owner, payment_code, source_sheet, notes, is_fixed)
VALUES
('expense', 700.00, 'marketing Estudio', (SELECT id FROM categories WHERE name='Pessoal'), 1, '2026-03-10', '2026-03-10', 'paid', 'Daniel', NULL, 'Março - 2026', 'Importado da planilha; codigos de pagamento foram omitidos do seed publico.', 1),
('expense', 1150.00, 'Mazinho', (SELECT id FROM categories WHERE name='Sem categoria'), 1, '2026-03-10', '2026-03-10', 'paid', 'Daniel', NULL, 'Março - 2026', NULL, 1),
('expense', 301.51, 'Condominio', (SELECT id FROM categories WHERE name='Moradia'), 1, '2026-03-10', '2026-03-10', 'paid', 'Daniel', NULL, 'Março - 2026', NULL, 1),
('expense', 210.93, 'convenio dentista', (SELECT id FROM categories WHERE name='Saude'), 1, '2026-03-18', '2026-03-18', 'paid', NULL, NULL, 'Março - 2026', NULL, 1),
('expense', 1575.00, 'Apartamento', (SELECT id FROM categories WHERE name='Moradia'), 1, '2026-03-30', NULL, 'pending', NULL, NULL, 'Março - 2026', NULL, 1),
('expense', 372.65, 'Boleto Imposto de Renda', (SELECT id FROM categories WHERE name='Impostos'), 1, '2026-03-30', NULL, 'pending', NULL, NULL, 'Março - 2026', NULL, 0),
('expense', 700.00, 'marketing Estudio', (SELECT id FROM categories WHERE name='Pessoal'), 1, '2026-04-12', '2026-04-12', 'paid', 'Daniel', NULL, 'Abril - 2026', NULL, 1),
('expense', 210.93, 'convenio dentista-pix copiacola', (SELECT id FROM categories WHERE name='Saude'), 1, '2026-04-18', NULL, 'pending', NULL, NULL, 'Abril - 2026', NULL, 1),
('expense', 121.00, 'Cartao Fran', (SELECT id FROM categories WHERE name='Pessoal'), 3, '2026-04-20', NULL, 'pending', 'Fran', NULL, 'Abril - 2026', NULL, 0),
('expense', 440.00, 'Marisa Terapia', (SELECT id FROM categories WHERE name='Saude'), 1, '2026-04-13', '2026-04-13', 'paid', NULL, NULL, 'Abril - 2026', NULL, 1),
('expense', 679.00, 'faculdade monitor-pixcopcola', (SELECT id FROM categories WHERE name='Educacao'), 1, '2026-04-15', '2026-04-15', 'paid', NULL, NULL, 'Abril - 2026', NULL, 1),
('expense', 550.00, 'marketing Estudio', (SELECT id FROM categories WHERE name='Pessoal'), 1, '2026-05-10', '2026-05-10', 'paid', 'Daniel', NULL, 'Maio-2026', NULL, 1),
('expense', 292.92, 'Condominio', (SELECT id FROM categories WHERE name='Moradia'), 1, '2026-05-10', '2026-05-10', 'paid', NULL, NULL, 'Maio-2026', NULL, 1),
('expense', 784.72, 'Boleto Imposto de Renda', (SELECT id FROM categories WHERE name='Impostos'), 1, '2026-05-30', NULL, 'pending', NULL, NULL, 'Maio-2026', NULL, 0),
('expense', 190.54, 'agua catende erika', (SELECT id FROM categories WHERE name='Pessoal'), 1, '2026-05-21', NULL, 'pending', NULL, NULL, 'Maio-2026', NULL, 0),
('expense', 625.00, 'marketing Estudio', (SELECT id FROM categories WHERE name='Pessoal'), 1, '2026-06-10', '2026-06-10', 'paid', 'Daniel', NULL, 'Junho-2026', NULL, 1),
('expense', 210.93, 'convenio dente cod barra', (SELECT id FROM categories WHERE name='Saude'), 1, '2026-06-18', NULL, 'pending', NULL, NULL, 'Junho-2026', NULL, 1),
('expense', 1575.00, 'Apartamento', (SELECT id FROM categories WHERE name='Moradia'), 1, '2026-06-30', '2026-06-30', 'paid', NULL, NULL, 'Junho-2026', NULL, 1),
('expense', 750.00, 'Boleto caixa engenheiro', (SELECT id FROM categories WHERE name='Moradia'), 1, '2026-06-10', '2026-06-10', 'paid', NULL, NULL, 'Junho-2026', NULL, 0),
('expense', 225.00, 'PREVIDENCIA CAIXA', (SELECT id FROM categories WHERE name='Investimentos'), 4, '2026-06-10', '2026-06-10', 'paid', NULL, NULL, 'Junho-2026', NULL, 1),
('expense', 150.00, 'Mariana Terapia', (SELECT id FROM categories WHERE name='Saude'), 1, '2026-06-10', '2026-06-10', 'paid', NULL, NULL, 'Julho-2026', NULL, 0),
('expense', 237.71, 'Vivo pixcopicola', (SELECT id FROM categories WHERE name='Moradia'), 1, '2026-07-06', '2026-07-06', 'paid', NULL, NULL, 'Julho-2026', NULL, 1),
('expense', 625.00, 'marketing Estudio', (SELECT id FROM categories WHERE name='Pessoal'), 1, '2026-07-10', '2026-07-10', 'paid', 'Daniel', NULL, 'Julho-2026', NULL, 1),
('expense', 679.00, 'faculdade monitor-pixcopcola', (SELECT id FROM categories WHERE name='Educacao'), 1, '2026-07-10', NULL, 'pending', NULL, NULL, 'Julho-2026', NULL, 1),
('expense', 1575.00, 'Apartamento', (SELECT id FROM categories WHERE name='Moradia'), 1, '2026-07-30', NULL, 'pending', NULL, NULL, 'Julho-2026', NULL, 1),
('expense', 396.38, 'Boleto Imposto de Renda', (SELECT id FROM categories WHERE name='Impostos'), 1, '2026-07-30', '2026-07-30', 'paid', NULL, NULL, 'Julho-2026', NULL, 0),
('expense', 32.07, 'luz', (SELECT id FROM categories WHERE name='Moradia'), 1, '2026-08-06', '2026-08-06', 'paid', NULL, NULL, 'Julho-2026', NULL, 1);

INSERT INTO budgets (category_id, month, limit_amount) VALUES
((SELECT id FROM categories WHERE name='Moradia'), '2026-07', 2500.00),
((SELECT id FROM categories WHERE name='Pessoal'), '2026-07', 2200.00),
((SELECT id FROM categories WHERE name='Saude'), '2026-07', 650.00),
((SELECT id FROM categories WHERE name='Educacao'), '2026-07', 800.00),
((SELECT id FROM categories WHERE name='Impostos'), '2026-07', 500.00)
ON DUPLICATE KEY UPDATE limit_amount = VALUES(limit_amount);

INSERT INTO goals (name, target_amount, current_amount, target_date) VALUES
('Reserva de emergencia', 15000.00, 0.00, '2026-12-31'),
('Quitar despesas pendentes', 2254.00, 0.00, '2026-08-31');

INSERT INTO recurring_rules (description, amount, category_id, frequency, next_due_date, is_active) VALUES
('Apartamento', 1575.00, (SELECT id FROM categories WHERE name='Moradia'), 'monthly', '2026-08-30', 1),
('Internet', 149.99, (SELECT id FROM categories WHERE name='Moradia'), 'monthly', '2026-08-10', 1),
('Marketing Estudio', 625.00, (SELECT id FROM categories WHERE name='Pessoal'), 'monthly', '2026-08-10', 1),
('Faculdade monitor', 679.00, (SELECT id FROM categories WHERE name='Educacao'), 'monthly', '2026-08-10', 1),
('Previdencia Caixa', 225.00, (SELECT id FROM categories WHERE name='Investimentos'), 'monthly', '2026-08-10', 1);
