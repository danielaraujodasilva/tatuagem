-- 010_contas_out2026.sql
-- Contas fixas de outubro/2026 (aba "Despesas" da planilha).
-- 1) Atualiza as regras de contas fixas existentes com valores/vencimentos deste mes.
-- 2) Cria as regras que ainda nao existiam.
-- 3) Lanca as 20 contas de outubro/2026 em transactions (aba Contas / periodo).
-- Seguro/idempotente: nao apaga nem altera nomes; guarda por chave natural.
--
-- ATENCAO: os codigos de boleto/PIX NAO entram neste arquivo (dados sensiveis).
-- Eles ficam em 010_contas_out2026.codigos.local.sql, que nao e versionado.
SET NAMES utf8mb4;
USE plan_financeiro;

-- 1) Regras existentes: valor + proximo vencimento de outubro/2026
UPDATE recurring_rules SET amount = 221.87,  next_due_date = '2026-10-06' WHERE description = 'Enel';
UPDATE recurring_rules SET amount = 163.96,  next_due_date = '2026-10-20' WHERE description = 'Sabesp';
UPDATE recurring_rules SET amount = 237.69,  next_due_date = '2026-10-06' WHERE description = 'Vivo';
UPDATE recurring_rules SET amount = 310.24,  next_due_date = '2026-10-13' WHERE description = 'Condominio';
UPDATE recurring_rules SET amount = 99.99,   next_due_date = '2026-10-13' WHERE description = 'Internet Estudio';
UPDATE recurring_rules SET amount = 149.99,  next_due_date = '2026-10-10' WHERE description = 'Internet ap';
UPDATE recurring_rules SET amount = 675.00,  next_due_date = '2026-10-10' WHERE description = 'Terapia Luna';
UPDATE recurring_rules SET amount = 226.49,  next_due_date = '2026-09-18' WHERE description = 'Convênio Amil';
UPDATE recurring_rules SET amount = 508.26,  next_due_date = '2026-10-16' WHERE description = 'Cartão crédito';
UPDATE recurring_rules SET amount = 700.00,  next_due_date = '2026-10-10' WHERE description = 'Marketing Estudio';
UPDATE recurring_rules SET amount = 1100.00, next_due_date = '2026-10-20' WHERE description = 'Aluguel Estudio';
UPDATE recurring_rules SET amount = 0.00,    next_due_date = '2026-10-22' WHERE description = 'Comgas';
UPDATE recurring_rules SET amount = 399.99,  next_due_date = '2026-10-15' WHERE description = 'FAUSP';
UPDATE recurring_rules SET amount = 1547.37, next_due_date = '2026-10-12' WHERE description = 'Aluguel ap';

-- 2) Regras novas (nao existiam)
INSERT INTO recurring_rules (description, amount, category_id, frequency, next_due_date, is_active)
SELECT v.description, v.amount,
       (SELECT c.id FROM categories c WHERE c.name = v.category LIMIT 1),
       'monthly', v.next_due_date, 1
FROM (
  SELECT 'Enel Estúdio' AS description, 0.00 AS amount, '2026-10-27' AS next_due_date, 'Moradia' AS category
  UNION ALL SELECT 'credito fran', 41.71, '2026-10-20', 'Pessoal'
  UNION ALL SELECT 'seguro caixa', 230.00, '2026-10-15', 'Servicos'
  UNION ALL SELECT 'credito Daniel', 145.88, '2026-10-20', 'Pessoal'
  UNION ALL SELECT 'CNPJ Tattoo', 600.00, '2026-10-10', 'Impostos'
  UNION ALL SELECT 'Parcela Beth 1/100', 500.00, '2026-10-10', 'Pessoal'
) v
WHERE NOT EXISTS (SELECT 1 FROM recurring_rules r WHERE r.description = v.description);

-- 3) Lancamentos das contas de outubro/2026
DROP TEMPORARY TABLE IF EXISTS tmp_contas_out2026;
CREATE TEMPORARY TABLE tmp_contas_out2026 (
  amount DECIMAL(12,2) NOT NULL,
  description VARCHAR(220) NOT NULL,
  due_date DATE NOT NULL,
  category_name VARCHAR(120) NOT NULL
);

INSERT INTO tmp_contas_out2026 (amount, description, due_date, category_name) VALUES
(1547.37, 'Caixa', '2026-10-12', 'Moradia'),
(0.00, 'Enel Estúdio', '2026-10-27', 'Moradia'),
(237.69, 'vivo', '2026-10-06', 'Moradia'),
(221.87, 'Luz-AP', '2026-10-06', 'Moradia'),
(163.96, 'agua estudio', '2026-10-20', 'Moradia'),
(310.24, 'condominio', '2026-10-13', 'Moradia'),
(99.99, 'internet estudio', '2026-10-13', 'Moradia'),
(149.99, 'internet apartamento', '2026-10-10', 'Moradia'),
(675.00, 'Mariana terapia', '2026-10-10', 'Saude'),
(226.49, 'convenio dentista', '2026-09-18', 'Saude'),
(41.71, 'credito fran', '2026-10-20', 'Pessoal'),
(399.99, 'Fausp', '2026-10-15', 'Educacao'),
(700.00, 'marketing Estudio', '2026-10-10', 'Pessoal'),
(508.26, 'cartao pag seguro', '2026-10-16', 'Pessoal'),
(1100.00, 'aluguel cantende erika cpf joaquim', '2026-10-20', 'Moradia'),
(0.00, 'Gas encanado', '2026-10-22', 'Moradia'),
(230.00, 'seguro caixa', '2026-10-15', 'Servicos'),
(145.88, 'credito Daniel', '2026-10-20', 'Pessoal'),
(600.00, 'CNPJ Tattoo', '2026-10-10', 'Impostos'),
(500.00, 'Parcela Beth 1/100', '2026-10-10', 'Pessoal');

INSERT INTO transactions
(type, amount, description, category_id, account_id, due_date, paid_at, status, owner, payment_code, source_sheet, reference_month, notes, is_fixed, created_at, updated_at)
SELECT 'expense', s.amount, s.description,
       (SELECT c.id FROM categories c WHERE c.name = s.category_name LIMIT 1),
       1, s.due_date, NULL, 'pending', NULL, NULL, 'Outubro-2026', '2026-10', NULL, 1, NOW(), NOW()
FROM tmp_contas_out2026 s
WHERE NOT EXISTS (
  SELECT 1 FROM transactions t
  WHERE t.reference_month = '2026-10'
    AND t.description = s.description
    AND t.due_date = s.due_date
);

DROP TEMPORARY TABLE IF EXISTS tmp_contas_out2026;