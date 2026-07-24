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

## Importação de extratos

A tela `Extratos` aceita arquivos `.xlsx`, `.xls` e `.csv` lidos no navegador. Os formatos testados foram:

- PagBank: `Data`, `Tipo`, `Descrição`, `Entradas`, `Saidas`, `Saldo`
- Santander: `Data`, `Descrição`, `Docto`, `Situação`, `Crédito (R$)`, `Débito (R$)`, `Saldo (R$)`

Ao salvar a importação, o sistema cria as tabelas bancárias se elas ainda não existirem, registra o banco de origem, evita duplicidade por hash do arquivo/linha e tenta marcar lançamentos pendentes como pagos quando data e valor batem.

Para bancos já instalados manualmente, a migration equivalente está em `plan/migrations/002_bank_imports.sql`.
