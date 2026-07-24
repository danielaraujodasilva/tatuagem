USE plan_financeiro;

SET @column_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions'
    AND COLUMN_NAME = 'reference_month'
);

SET @add_column := IF(
  @column_exists = 0,
  'ALTER TABLE transactions ADD COLUMN reference_month CHAR(7) NULL AFTER source_sheet',
  'SELECT 1'
);
PREPARE add_column_stmt FROM @add_column;
EXECUTE add_column_stmt;
DEALLOCATE PREPARE add_column_stmt;

SET @index_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions'
    AND INDEX_NAME = 'idx_transactions_reference_month'
);

SET @add_index := IF(
  @index_exists = 0,
  'CREATE INDEX idx_transactions_reference_month ON transactions (reference_month)',
  'SELECT 1'
);
PREPARE add_index_stmt FROM @add_index;
EXECUTE add_index_stmt;
DEALLOCATE PREPARE add_index_stmt;
