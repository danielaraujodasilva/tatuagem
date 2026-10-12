-- Contas fixas de internet: separa as duas Lider Fibra (estudio e ap) para que o
-- coletor de boletos por e-mail atualize cada uma separadamente.
--
-- Contrato 3335 (estudio, 800MB) -> 'Internet Estudio' (regra que existia como 'Internet')
-- Contrato 4375 (ap, 400MB)     -> 'Internet ap'      (regra nova)
--
-- Seguro para rodar mais de uma vez.

UPDATE recurring_rules SET description = 'Internet Estudio'
 WHERE description = 'Internet' AND is_active = 1;

INSERT INTO recurring_rules (description, amount, category_id, frequency, next_due_date, is_active)
SELECT 'Internet ap', 99.99, r.category_id, 'monthly', NULL, 1
  FROM recurring_rules r
 WHERE r.description = 'Internet Estudio'
   AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM recurring_rules WHERE description = 'Internet ap') t);

INSERT INTO recurring_rule_matchers (rule_id, match_key, account_id, bank_name)
SELECT n.id, m.match_key, m.account_id, m.bank_name
  FROM recurring_rules n
  JOIN recurring_rule_matchers m ON m.rule_id = 2
 WHERE n.description = 'Internet ap'
   AND NOT EXISTS (SELECT 1 FROM recurring_rule_matchers x WHERE x.rule_id = n.id AND x.match_key = m.match_key);