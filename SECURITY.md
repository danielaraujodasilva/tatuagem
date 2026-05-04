# Seguranca e configuracao local

Este repositorio nao deve versionar senhas, tokens, logs ou dados reais de clientes.

## Variaveis de ambiente

Configure os valores sensiveis na hospedagem, painel do servidor ou arquivos locais ignorados pelo Git.

### Ficha

- `FICHA_DB_HOST`
- `FICHA_DB_PORT`
- `FICHA_DB_NAME`
- `FICHA_DB_USER`
- `FICHA_DB_PASS`

Alternativa local: copie `ficha/config/conexao.local.example.php` para `ficha/config/conexao.local.php`.

### CRM

- `CRM_DB_HOST`
- `CRM_DB_NAME`
- `CRM_DB_USER`
- `CRM_DB_PASS`

Alternativa local: copie `crm/config.local.example.php` para `crm/config.local.php`.

### Deploy

- `DEPLOY_WEBHOOK_SECRET`
- `DEPLOY_PATH`

Alternativa local: copie `deploy.local.example.php` para `deploy.local.php` no servidor e preencha o `secret` com o mesmo segredo configurado no webhook do GitHub.

### Mercado Pago / Flash

- `MP_ACCESS_TOKEN`
- `FLASH_ALLOWED_ORIGIN`

### Rifa

- `RIFA_ALLOWED_ORIGIN`

### Meduri Promo

- `PROMO_DB_HOST`
- `PROMO_DB_NAME`
- `PROMO_DB_USER`
- `PROMO_DB_PASS`
- `PROMO_MP_ACCESS_TOKEN`
- `PROMO_SMTP_HOST`
- `PROMO_SMTP_USER`
- `PROMO_SMTP_PASS`
- `PROMO_SMTP_PORT`
- `PROMO_BASE_URL`
- `PROMO_ADMIN_USER`
- `PROMO_ADMIN_PASS_HASH`

Para gerar `PROMO_ADMIN_PASS_HASH`, use `password_hash()` no PHP.

## Depois de um vazamento

Mesmo removendo do codigo atual, qualquer segredo que ja foi commitado deve ser considerado exposto. Troque tokens do Mercado Pago, segredo do webhook de deploy e senhas de banco no provedor.
