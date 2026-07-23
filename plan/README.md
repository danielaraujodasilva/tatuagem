# Plan Financeiro

Sistema PHP/MySQL para gerenciamento financeiro baseado na planilha "Orçamento mensal" do Google Sheets.

## Instalação

1. Importe `plan/database.sql` no phpMyAdmin.
2. Copie `plan/config.example.php` para `plan/config.local.php`.
3. Ajuste host, banco, usuário e senha do MySQL.
4. Acesse `/plan/index.php`.

Login inicial:

- E-mail: `danielaraujodasilva@gmail.com`
- Senha: `Daniel*123`

## Observação de segurança

O banco tem campo para boleto/PIX, mas o seed do repositório não grava códigos sensíveis da planilha. Cadastre esses dados diretamente no sistema quando necessário e troque a senha inicial após publicar.
