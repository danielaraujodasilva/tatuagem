USE plan_financeiro;

ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS manual_override TINYINT(1) NOT NULL DEFAULT 0 AFTER is_fixed,
  ADD COLUMN IF NOT EXISTS manual_override_at DATETIME NULL AFTER manual_override;

ALTER TABLE bank_imports
  ADD COLUMN IF NOT EXISTS duplicate_rows INT UNSIGNED NOT NULL DEFAULT 0 AFTER matched_rows,
  ADD COLUMN IF NOT EXISTS protected_rows INT UNSIGNED NOT NULL DEFAULT 0 AFTER duplicate_rows;
