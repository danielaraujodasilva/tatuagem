-- Conta fixa de gas (Comgas). O boleto chega por e-mail (suafatura@comgas.com.br)
-- e o pagamento aparece no banco como COMPANHIA DE GAS DE SP.
-- Seguro para rodar mais de uma vez.

INSERT INTO recurring_rules (description, amount, category_id, frequency, next_due_date, is_active)
SELECT 'Comgas', 0, c.id, 'monthly', NULL, 1
  FROM categories c
 WHERE c.name = 'Moradia' AND c.parent_id IS NULL
   AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM recurring_rules WHERE description = 'Comgas') t);

INSERT INTO recurring_rule_matchers (rule_id, match_key, account_id, bank_name)
SELECT n.id, 'pagamento de boleto outros bancos companhia de gas de sp co', 6, 'Santander'
  FROM recurring_rules n
 WHERE n.description = 'Comgas'
   AND NOT EXISTS (SELECT 1 FROM recurring_rule_matchers x WHERE x.rule_id = n.id);