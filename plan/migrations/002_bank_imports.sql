USE plan_financeiro;

CREATE TABLE IF NOT EXISTS bank_imports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bank_name VARCHAR(80) NOT NULL,
  account_id INT UNSIGNED NULL,
  account_label VARCHAR(160) NULL,
  file_name VARCHAR(220) NOT NULL,
  file_hash CHAR(64) NOT NULL,
  period_start DATE NULL,
  period_end DATE NULL,
  imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
  matched_rows INT UNSIGNED NOT NULL DEFAULT 0,
  imported_by INT UNSIGNED NULL,
  imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_bank_import_file (file_hash),
  INDEX idx_bank_imports_bank_date (bank_name, imported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_id INT UNSIGNED NOT NULL,
  account_id INT UNSIGNED NULL,
  bank_name VARCHAR(80) NOT NULL,
  source_file VARCHAR(220) NOT NULL,
  source_hash CHAR(64) NOT NULL,
  transaction_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  movement_type VARCHAR(120) NULL,
  document_number VARCHAR(80) NULL,
  direction ENUM('credit','debit') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  balance DECIMAL(12,2) NULL,
  category_id INT UNSIGNED NULL,
  matched_transaction_id INT UNSIGNED NULL,
  raw_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_bank_transaction_hash (source_hash),
  INDEX idx_bank_transactions_date (transaction_date),
  INDEX idx_bank_transactions_bank (bank_name),
  INDEX idx_bank_transactions_match (matched_transaction_id),
  CONSTRAINT fk_bank_transactions_import FOREIGN KEY (import_id) REFERENCES bank_imports(id) ON DELETE CASCADE,
  CONSTRAINT fk_bank_transactions_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_bank_transactions_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_bank_transactions_match FOREIGN KEY (matched_transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
