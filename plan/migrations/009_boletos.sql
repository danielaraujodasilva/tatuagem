-- Boletos coletados por e-mail: guarda o codigo de barras e o PDF para conferir
-- e pagar direto na tela de Contas fixas.
-- Seguro para rodar mais de uma vez.

ALTER TABLE recurring_rules ADD COLUMN IF NOT EXISTS boleto_codigo VARCHAR(120) NULL AFTER next_due_date;
ALTER TABLE recurring_rules ADD COLUMN IF NOT EXISTS boleto_arquivo VARCHAR(180) NULL AFTER boleto_codigo;
ALTER TABLE recurring_rules ADD COLUMN IF NOT EXISTS boleto_fonte VARCHAR(40) NULL AFTER boleto_arquivo;
ALTER TABLE recurring_rules ADD COLUMN IF NOT EXISTS boleto_atualizado_em DATETIME NULL AFTER boleto_fonte;