# Codex Web

Pagina web para conversar com o Codex que roda nesta maquina, ver as sessoes
existentes e continuar qualquer conversa. Usa o mesmo login/senha do `/plan`.

- Pagina: `/codex/` (ex.: https://danieltatuador.com/codex/)
- Ponte local: `codex/bridge/server.js` (Node, `127.0.0.1:3051`)
- Login: mesma tabela `users` do Plan (`plan/includes/bootstrap.php`)

## Como funciona

1. O navegador fala com `codex/api.php` (PHP, autenticado pela sessao do Plan).
2. O PHP chama a ponte Node em `127.0.0.1:3051` usando um token local.
3. A ponte roda `codex exec [resume <id>] --json` no `CODEX_HOME` do app,
   entao a conversa tambem aparece no app Codex.

Nada disso fica exposto na internet: a ponte so escuta em `127.0.0.1` e exige
o token em `codex/bridge/data/token.txt`.

## Anexos

- O botao de clipe no composer (e colar imagens com Ctrl+V) envia arquivos
  para `codex/upload.php`, que valida sessao/CSRF e salva em `codex/uploads/`.
- Imagens vao para o Codex via `-i <arquivo>`; outros arquivos entram no texto
  como `[anexos] nome: caminho` para o agente abrir.
- `codex/uploads/` e `codex/bridge/data/` ficam fora do git.
- Cada anexo tem limite de tamanho (`CODEX_WEB_MAX_UPLOAD_MB`, definido em
  `codex/.htaccess` como 512 MB no site; padrao do codigo: 25 MB) e os mais
  antigos que `CODEX_WEB_UPLOAD_DIAS` dias sao apagados sozinhos.
- Os limites de upload (tamanho de cada arquivo, `post_max_size` e tempo de
  envio) tambem ficam em `codex/.htaccess`; ajuste la se precisar de mais.

## Conversas

- Cada aba do navegador tem a propria conversa aberta (`sessionStorage`). Abrir
  a conversa B numa aba nao troca mais a conversa da aba A.
- Cada conversa tem a propria fila de prompts, o proprio rascunho/anexos e o
  proprio turno. Da para deixar duas conversas rodando ao mesmo tempo e trocar
  entre elas sem a resposta de uma cair na outra.
- A conversa aberta (e o turno em andamento) fica salva na aba: ao recarregar a
  pagina voce volta para onde estava.
- Acompanhamento do turno via SSE (`api.php?action=stream`); se o navegador ou
  proxy nao suportar, cai sozinho para polling no `/run`.
- A busca na barra lateral procura no titulo e tambem dentro das mensagens.
- Apagar uma conversa move o rollout para `codex/bridge/data/sessoes_apagadas/`.
  O botao `Apagadas` na barra lateral lista e restaura; o que passar de 30 dias
  e removido de vez.
- O pontinho verde na lista indica turno rodando naquela conversa. Se o mesmo
  turno for aberto em outra aba, a segunda aba acompanha o mesmo turno e poe o
  prompt dela na fila (a ponte responde 409 com o `runId`).

## Informacoes da mensagem

Cada balao (seu ou do agente) tem um botao discreto `i` ao lado de `copiar`.
Ele abre um modal com o que o log do DSH permite saber sobre aquela mensagem:

- data/hora (local, ISO/UTC e fuso), tempo de geracao, tempo ate o primeiro
  token, duracao do turno e tokens por segundo;
- tokens de entrada/saida, cache de leitura/escrita, total do passo, total do
  turno e consumo acumulado da sessao;
- login/e-mail de quem enviou (quando o prompt veio do site), IP, navegador e
  usuario do Windows que roda a ponte;
- modelo/provedor, turno, passo, ferramentas usadas, tamanho do texto e o JSON
  bruto para conferencia.

O autor e gravado pela ponte em `bridge/data/autores.json` quando o turno
termina (o log do DSH nao guarda o login). Mensagens antigas, ou criadas direto
na janela do app, aparecem como "nao registrado". O modal se atualiza sozinho
enquanto a resposta esta sendo gerada.

## Subir

```powershell
# no root do site (C:\Users\server_spd\Documents\ChatGPT\server)
powershell -ExecutionPolicy Bypass -File codex\start-codex-bridge.ps1
```

Para conferir: `curl -H "X-Bridge-Token: <token>" http://127.0.0.1:3051/health`
(token em `codex/bridge/data/token.txt`).

Para manter a ponte de pe (reergue sozinha se cair):

```powershell
powershell -ExecutionPolicy Bypass -File codex\start-codex-bridge.ps1 -Watch
```

Para subir junto com o Windows (tarefa agendada, no logon e no boot):

```powershell
powershell -ExecutionPolicy Bypass -File codex\start-codex-bridge.ps1 -Register
```

`-Unregister` remove a tarefa. O log do watchdog fica em
`codex/bridge/data/watchdog.log`.

> **Nesta maquina (05/11/2026):** registrar a tarefa exige elevacao e da
> `Acesso negado` sem admin. O autostart que existe de fato e o atalho
> `%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\codex-bridge.vbs`,
> que roda `-Watch` **no logon** (nao no boot). Sem login, a ponte nao sobe.

## Configuracao (opcional)

Variaveis de ambiente lidas pela ponte:

| Variavel | Padrao |
| --- | --- |
| `CODEX_WEB_PORT` | `3051` |
| `CODEX_WEB_TIMEOUT_MS` | `21600000` (6 horas por turno) |
| `CODEX_WEB_HOME` | `C:\Users\server_spd\.codex` |
| `CODEX_WEB_WORKDIR` | `C:\Users\server_spd\Documents\ChatGPT\server` |
| `CODEX_WEB_EXE` | descobre sozinho o `codex.exe` do app |
| `CODEX_WEB_MODEL` | `deepseek-flash` (padrao do script de start) |
| `CODEX_WEB_PROVIDER` | `deepseek` (provedor do `config.toml` usado so pela ponte) |
| `CODEX_WEB_PROVIDER_KEY_VAR` | `DEEPSEEK_CODEX_KEY` (variavel de ambiente com a chave) |
| `CODEX_WEB_MAX_UPLOAD_MB` | `512` (no site, via `codex/.htaccess`); `25` se nao houver `.htaccess` |
| `CODEX_WEB_UPLOAD_DIAS` | `14` (anexos mais velhos sao removidos) |
| `CODEX_WEB_ALLOW_IPS` | vazio = sem restricao (aceita IPs e CIDR IPv4) |
| `DSH_WEB_HOME` | `%USERPROFILE%\.dsh` |
| `DSH_WEB_CLI` | descobre sozinho o CLI do app DeepSeek Harness |
| `DSH_WEB_WORKDIR` | pasta do app (`...\Documents\ChatGPT\server`); nunca o cwd do processo |
| `DSH_WEB_PERMISSION_MODE` | `danger-full-access` (acesso total, sem aprovacao) |

O PHP usa `CODEX_WEB_PORT` (se definido) para achar a ponte.

### Permissoes dos motores (acesso total)

A pagina roda os agentes como o dono da maquina, de proposito:

- **DSH** (`dsh headless`): a ponte exporta `DSH_PERMISSION_MODE=danger-full-access`
  no processo filho. Isso desliga o sandbox de arquivos e fixa a aprovacao em
  `never` (`dsh-base` deriva a politica de aprovacao desse mesmo modo). Sessoes
  novas nascem no preset `danger-full-access`.
- **Codex** (`codex exec`): passa `--dangerously-bypass-approvals-and-sandbox`.
- O **cwd padrao** e a pasta do app, nao o cwd do processo: como a ponte sobe por
  tarefa agendada, o cwd podia virar `C:\Windows\System32` e as sessoes ficavam
  presas ali (sandbox `workspace-write` numa pasta sem permissao de escrita).

Sessoes DSH **antigas** guardam no proprio log o modo com que nasceram
(`workspace-write`). Para destravar uma conversa velha, troque o preset na
janela do app (seletor de permissoes) ou comece uma conversa nova pelo site.

## Deploy

`C:\xampp\htdocs\site\codex` e um **junction** para esta pasta, entao o que voce
edita aqui ja aparece no site.

## Subdominio codex.danieltatuador.com

Ja configurado:

- Apache: vhost `<VirtualHost *:80>` em `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
  com `ServerName codex.danieltatuador.com` e
  `DocumentRoot "C:/xampp/htdocs/site/codex"`.
- Cloudflare: hostname no `C:\Users\server_spd\.cloudflared\config.yml`
  (tunel `danieltatuador2`) e CNAME `codex` -> `ed8430da-...cfargotunnel.com`.
- Backups: `httpd-vhosts.conf.bak-codex-*` e `config.yml.bak-codex-*`.

Ao mudar o vhost do Apache, reinicie o httpd (nao ha servico):
`Stop-Process` no `httpd.exe` e depois inicie `C:\xampp\apache\bin\httpd.exe`.
Ao mudar o `config.yml`, reinicie o processo `cloudflared tunnel run danieltatuador2`
(o watchdog do site monitora e sobe de novo se cair).

## Seguranca

- O Codex roda com acesso total a maquina. Quem entra na pagina manda no agente.
- Mantenha a senha do Plan forte e nao compartilhe o link publico.
- Nunca exponha a porta 3051 no roteador/tunel.
- `codex/bridge/data/` fica fora do git (token e logs).
- Login com limite de tentativas (bloqueia por 15 min apos 8 erros).
- Opcional: `CODEX_WEB_ALLOW_IPS` restringe o acesso a IPs/CIDR conhecidos.

## Conversas do DSH (janela do app DeepSeek Harness)

A pagina tambem lista e continua as conversas do **DeepSeek Harness** que roda
nesta maquina, para dar para comecar uma conversa aqui e terminar na janela do
app (ou o contrario).

- O seletor **"Conversa nova em"** (barra lateral) escolhe o motor: `Codex` ou
  `DSH`. A escolha fica salva no navegador.
- Conversas do DSH aparecem na mesma lista, com o selo `DSH`.
- O motor do DSH e o proprio CLI do app:
  `DeepSeek Harness.exe --expose-internals ...\dsh-desktop-host\lib\cli.js headless --json [--session-id <id>] -`
  com o prompt no stdin. Como e o mesmo motor, o turno roda igual ao da janela.
- Leitura das sessoes: `%USERPROFILE%\.dsh\sessions\*\session-*\session.v4.jsonl.zstd`
  (JSONL com um frame zstd por registro; `bridge/dsh.js` descomprime frame a frame).
- Ao criar uma conversa nova pelo site, a sessao e registrada em
  `%USERPROFILE%\.dsh\storages\workspace.json` para aparecer tambem na janela do DSH.
- A lista de conversas do site se atualiza sozinha a cada ~20 s e ao voltar para
  a aba: conversa criada direto na janela do DSH aparece no site sem F5. O
  contrario (site -> janela do DSH) depende de o app reler o `workspace.json`;
  se a janela ja estava aberta, feche/abra a lista ou reinicie o app para ver a
  sessao nova.
- Ao **continuar** uma conversa existente, o turno roda no `cwd` gravado no cabecalho
  daquela sessao (`dsh.js: cwdDaSessao`), nao no workspace padrao. O DSH recusa retomar
  uma sessao em outro diretorio (`session ... was recorded in X, not Y`); era o que
  quebrava conversas antigas criadas em `C:\Windows\System32`.
- A ponte precisa de `DEEPSEEK_API_KEY` (o `start-codex-bridge.ps1` copia de
  `DEEPSEEK_CODEX_KEY`). Sem isso o `dsh headless` falha com `MISSING_CREDENTIAL`.

Limite conhecido: a ponte so enxerga os turnos que ela mesma iniciou. Se a
mesma conversa estiver gerando **na janela do DSH** e voce mandar um prompt pelo
site ao mesmo tempo, os dois escritores podem disputar a sessao. O site evita
dois turnos simultaneos na mesma conversa quando os dois vieram dele (409), mas
nao consegue ver o turno aberto dentro do app. Nesse caso, espere a resposta do
app terminar antes de mandar pelo site (ou vice-versa).
