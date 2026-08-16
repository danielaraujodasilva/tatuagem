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

## Sincronizacao com Meu Pluggy

A integracao automatica e opcional e fica desligada por padrao. O importador de arquivos continua funcionando sem ela.

1. Crie uma conta em `https://meu.pluggy.ai/` e conecte suas contas por Open Finance.
2. No Dashboard Pluggy, conecte o Meu Pluggy na aplicacao de demonstracao.
3. Guarde `Client ID`, `Client Secret` e os `Item IDs` somente em `config.local.php` ou nas variaveis abaixo.

```text
PLAN_BANK_SYNC_ENABLED=true
PLAN_PLUGGY_CLIENT_ID=...
PLAN_PLUGGY_CLIENT_SECRET=...
PLAN_PLUGGY_ITEM_IDS=item-do-santander,item-do-pagbank
```

Para uma execucao manual, use o botao `Sincronizar agora`. Para automatizar no Windows, agende o comando abaixo no Agendador de Tarefas:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\site\plan\scripts\sync-banks.php
```

As tabelas `bank_provider_accounts`, `bank_provider_transactions` e `bank_sync_runs` isolam a integracao. Desligar `PLAN_BANK_SYNC_ENABLED` interrompe as consultas sem remover os extratos ja copiados para o PLAN.
