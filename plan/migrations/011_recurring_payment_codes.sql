-- 011_recurring_payment_codes.sql
-- Permite guardar PIX copia e cola (que passa de 120 caracteres) junto dos
-- codigos de boleto na Conta fixa. Seguro para rodar mais de uma vez.
--
-- O proprio Plan tambem ajusta a coluna automaticamente no bootstrap.

ALTER TABLE recurring_rules MODIFY COLUMN boleto_codigo TEXT NULL;
