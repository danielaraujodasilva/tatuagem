CREATE TABLE IF NOT EXISTS bank_provider_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(32) NOT NULL,
  external_account_id VARCHAR(100) NOT NULL,
  external_item_id VARCHAR(100) NOT NULL,
  local_account_id INT UNSIGNED NULL,
  bank_name VARCHAR(80) NOT NULL,
  account_label VARCHAR(160) NULL,
  last_balance DECIMAL(12,2) NULL,
  last_synced_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  raw_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_provider_account (provider, external_account_id),
  INDEX idx_provider_account_local (local_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_provider_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(32) NOT NULL,
  external_transaction_id VARCHAR(100) NOT NULL,
  bank_transaction_id BIGINT UNSIGNED NOT NULL,
  raw_hash CHAR(64) NOT NULL,
  synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_provider_transaction (provider, external_transaction_id),
  UNIQUE KEY uniq_provider_bank_transaction (provider, bank_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_sync_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(32) NOT NULL,
  trigger_name VARCHAR(32) NOT NULL,
  status ENUM('running','success','failed') NOT NULL DEFAULT 'running',
  accounts_count INT UNSIGNED NOT NULL DEFAULT 0,
  fetched_rows INT UNSIGNED NOT NULL DEFAULT 0,
  inserted_rows INT UNSIGNED NOT NULL DEFAULT 0,
  updated_rows INT UNSIGNED NOT NULL DEFAULT 0,
  matched_rows INT UNSIGNED NOT NULL DEFAULT 0,
  message VARCHAR(500) NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  INDEX idx_bank_sync_provider_date (provider, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
