# Project Notes

## Objetivo deste arquivo

Este arquivo e o mapa rapido do projeto para orientar trabalhos futuros no Codex. Atualize sempre que houver decisoes importantes de produto, deploy, seguranca, arquitetura ou fluxo de trabalho.

## Repositorio e deploy

- GitHub: https://github.com/danielaraujodasilva/tatuagem
- Branch principal: `main`
- Fluxo combinado: o Codex pode editar, commitar e enviar direto para `main`.
- Deploy: pushes na `main` podem acionar o webhook ja configurado no servidor.
- Checkpoint inicial antes deste arquivo: tag `checkpoint-before-codex-project-notes`.
- Repositorio local de trabalho do Codex neste computador: `C:\Users\server_spd\Documents\New project\tatuagem`.

## Stack geral

- Projeto majoritariamente PHP, HTML, CSS e JavaScript, sem framework unico global.
- Estrutura por modulos/pastas independentes.
- Configuracoes sensiveis devem ficar em variaveis de ambiente ou arquivos locais ignorados pelo Git.
- Nao versionar senhas, tokens, logs, backups temporarios ou dados reais de clientes.

## Modulos principais

### Raiz

- `index.php`: pagina principal do site.
- `.htaccess`: regras Apache do projeto.
- `deploy.php`: webhook de deploy que valida assinatura `X-Hub-Signature-256` e executa `git pull` no caminho configurado.
- `SECURITY.md`: guia de variaveis de ambiente e cuidados com segredos.
- `privacy.html` e `terms.html`: paginas legais.

### Auth

- Pasta: `auth/`
- Responsavel por login, logout, registro, usuarios e recuperacao/reset de senha.

### CRM

- Pasta: `crm/`
- Responsavel por clientes, leads, pipeline, agenda, chat, integracoes de mensagem/audio e relatorios.
- Arquivos de configuracao local esperados em `crm/config.local.php` ou variaveis `CRM_*`.
- Dados runtime e logs em `crm/data/` devem permanecer fora do Git conforme `.gitignore`.
- `crm/atendimento.php`: inbox geral de conversas com filtros, assumir conversa, status, chat e respostas rapidas.
- `crm/respostas_rapidas.php`: cadastro de scripts/mensagens prontas usados pela Central de Atendimento.
- `crm/data/quick_replies.json`: armazenamento runtime das respostas rapidas, ignorado pelo Git.
- `crm/assets/crm-theme.css`: base visual dark/vermelha usada nas novas telas do CRM.

### Ficha

- Pasta: `ficha/`
- Responsavel por cadastro publico, clientes, ficha/anamnese, conta, mapa e agenda.
- Existe documentacao propria em `ficha/README.md`.
- Configuracao local esperada em `ficha/config/conexao.local.php` ou variaveis `FICHA_*`.

### Calculadora

- Pasta: `calculadora/`
- Ferramenta HTML para calculos/orcamentos simples.

### Orcamento

- Pasta: `orcamento/`
- Area relacionada a orcamentos.

### Flash

- Pasta: `flash/`
- Pagina/fluxo de flashes, incluindo integracao com Mercado Pago.
- Segredos devem vir de `MP_ACCESS_TOKEN` e configuracoes permitidas como `FLASH_ALLOWED_ORIGIN`.

### Rifa

- Pasta: `rifa/`
- Area de rifa/sorteio.
- Usar configuracoes de origem permitida via `RIFA_ALLOWED_ORIGIN` quando aplicavel.

### Meduri

- Pasta: `meduri/`
- Area promocional com banco, Mercado Pago, SMTP, URL base e admin configurados por variaveis `PROMO_*`.

### Assets e paginas auxiliares

- `img/` e `fotos/`: imagens usadas pelo site.
- `fran/`, `ink/`, `ssl/`, `zap/`, `includes/`: areas auxiliares ou paginas especificas que devem ser analisadas no contexto antes de alterar.

## Regras de trabalho combinadas

- O usuario aceitou trabalhar direto na `main`.
- Antes de mudancas grandes ou arriscadas, criar um checkpoint por tag ou commit claro.
- Para cada bloco de trabalho, preferir:
  1. entender o modulo afetado;
  2. fazer alteracoes focadas;
  3. testar o que for possivel;
  4. commitar com mensagem objetiva;
  5. fazer push para o GitHub.
- Se uma mudanca puder afetar dados reais, login, pagamento, webhook, banco ou endpoint publico, revisar com cuidado extra.

## Cuidados de seguranca

- Nunca commitar arquivos `*.local.php`, `*.local.json`, `.env`, logs ou backups.
- Nunca commitar tokens do Mercado Pago, senhas de banco, segredos de webhook ou credenciais SMTP.
- Se algum segredo aparecer no historico, considerar exposto e trocar no provedor.
- Endpoints publicos devem validar entrada, permissao e origem quando aplicavel.
- Ao mexer em deploy, manter validacao HMAC do GitHub.

## Pendencias e ideias para backlog

- Revisar endpoints publicos de `crm/` e `ficha/` quanto a autenticacao, validacao e vazamento de dados.
- Padronizar tratamento de erro em respostas JSON.
- Mapear quais arquivos dependem de banco e quais dependem de JSON/runtime local.
- Melhorar documentacao dos modulos principais conforme forem sendo alterados.
- Criar testes ou scripts de verificacao simples para fluxos criticos, se o projeto permitir.

## Como usar este mapa

Ao iniciar uma tarefa no Codex, pedir para ler este arquivo primeiro quando o contexto estiver frio ou quando a conversa tiver mudado de dispositivo. Exemplo:

`Leia o PROJECT_NOTES.md e depois mexa no CRM.`
