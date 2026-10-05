# projetocrmv2

Nova versao do CRM do estudio, construida **ao lado** do CRM atual.
Nada e apagado, nada e alterado: o schema cria apenas tabelas novas com prefixo `v2_`.

## Ideia

- **Cliente unico** com camadas (essencial / atendimento / tecnico / negocio) e visao "ver como" (dono x secretaria).
- **Funil que comeca no "oi"**: todo contato no WhatsApp ja entra como lead, sem palavra-chave.
- **Motor de rotina**: as regras viram tarefas com data (follow-up, pos-tattoo, cicatrizacao, avaliacao, nova arte, aniversario, reativacao).
- **Voz**: a Irene responde em audio apenas quando recebe audio.
- **Aprendizado**: o playbook de voz do estudio, extraido das conversas reais.

## Telas

| pagina | o que faz |
|---|---|
| `?page=hoje` | o que precisa de voce agora: conversas, agenda e rotina |
| `?page=cliente` | busca, lista de clientes e a ficha em 5 camadas + historico completo |
| `?page=conversa` | WhatsApp do estudio: lista de conversas, historico e compositor (rascunho + audio) |
| `?page=rotina` | regras (`v2_regras`) e fila de tarefas, com filtro de periodo |
| `?page=voz` | comparacao de motores e prosodia |
| `?page=aprendizado` | o que foi aprendido lendo as conversas reais |
| `?page=estudio` | os fatos do estudio (preco, endereco, reserva...) que a Irene usa para responder |

## Filtros e busca

Todas as telas de lista aceitam os mesmos parametros na URL:

- `periodo=tudo|hoje|7d|30d|mes|90d` ou `de=AAAA-MM-DD&ate=AAAA-MM-DD`
- `busca=` (nome, telefone ou interesse)
- `status=` (novo, em_atendimento, lead_quente, sem_retorno, agendado, fechado, perdido)
- `ordem=recentes|parados|valor|nome` (nas listas)
- `aguardando=1` (so conversas em que a ultima mensagem e do cliente)

## Compositor (fase de validacao)

`?page=conversa` mostra a conversa real e deixa escrever a resposta, mas **nao envia nada**:
o texto vira rascunho local (localStorage) e o audio e gerado por `api/tts.php` so para ouvir.
O envio (Baileys) entra apenas depois da validacao da voz.

## Estrutura

```
projetocrmv2/
  index.php                roteador (?page=)
  config.local.example.php copie para config.local.php
  database/schema_v2.sql   tabelas novas (v2_*) - CREATE TABLE IF NOT EXISTS
  database/seed_v2.sql     regras do motor + playbook aprendido
  includes/bootstrap.php   conexoes, helpers, TTS e layout
  assets/app.css           tema claro
  views/                   telas
  api/tts.php              geracao de audio (kokoro / piper / edge / windows)
  api/voz.php              salva a voz escolhida na tela Voz (data/voz.json)
  api/kokoro_tts.py        sintese offline com pausa entre frases
  data/tts/                cache de audio (ignorado pelo Git)
```

## Banco

`schema_v2.sql` cria apenas `v2_clientes`, `v2_eventos`, `v2_tarefas`, `v2_regras` e `v2_aprendizado`.
`v2_regras` e `v2_aprendizado` continuam no banco legado (`crm_simples`) e os ajustes de voz em `data/`.

As telas de conversa, cliente, agenda e aprendizado leem o **banco atual do estudio**
(`projetocrm_<slug>`, ex.: `projetocrm_cereja`), que tem `leads`, `customers`,
`appointments`, `whatsapp_conversations` e `whatsapp_messages`. A conexao e descoberta
automaticamente pela tabela `studios` da plataforma, usando as credenciais de
`projetocrm/config/database.php`; para fixar no `config.local.php`, use
`studio_host` / `studio_database` / `studio_username` / `studio_password`.
Tudo em modo somente leitura - nada e apagado nem alterado no CRM atual.

## Como rodar

1. Copie `config.local.example.php` para `config.local.php` e ajuste as credenciais e a voz.
2. Rode `database/schema_v2.sql` e depois `database/seed_v2.sql` (phpMyAdmin ou `mysql`).
3. Acesse `/projetocrmv2/index.php`.

> As credenciais locais ficam em `crm/config.local.php` e `ficha/config/conexao.local.php`,
> ambos cobertos pelo `.gitignore` (`*.local.php`).

## Voz (prosodia)

Cada motor tem um ajuste padrao e variacoes nomeadas (`calor`, `calor_rapido`, `animada`, `serena`),
ouviveis em `?page=voz` e aplicaveis via `api/tts.php?engine=edge&v=calor&text=...`.

A tela `?page=voz` tem um player so: escolhe motor, voz, sentimento, ritmo e tom e salva.
O que fica salvo em `data/voz.json` e o que o sistema usa - conversa, simulador e o audio
automatico da Irene (`api/tts.php` sem parametros ja pega o que esta salvo).
Pela URL tambem da para passar tudo: `?engine=`, `?voice=`, `?v=`, `?vel=` e `?tom=`.

## Simulador (`?page=simulador`)

O seletor "cerebro" oferece os modelos do LM Studio e tambem `cerebro|deepseek-chat`:
o mesmo modelo que roda o Codex, chamado direto na API (`api/irene.php`). A chave vem da
variavel de ambiente `DEEPSEEK_CODEX_KEY` e nunca e gravada em arquivo, log ou tela.
Se a API falhar, o simulador cai no modelo local e o campo `motor` diz qual respondeu.

## Abertura da conversa (regra fixa)

Antes de qualquer resposta, a Irene se apresenta: diz que e a inteligencia artificial do
estudio, que o Daniel e a Hellen estao ocupados, e oferece `1` (aguardar falar com eles)
ou `2` (tirar duvidas com ela enquanto isso). Essa mensagem e o menu `1`/`2` **nao passam
pelo modelo** - sao texto fixo em `api/irene.php` (`irene_abertura()` e `irene_opcao()`),
entao saem sempre iguais e na hora. O campo `estado` da resposta diz em que ponto esta:
`menu`, `humano`, `ajudando` ou `ia`.

| motor | offline | observacao |
|---|---|---|
| `kokoro` | sim | mais natural que roda 100% na CPU; pausa entre frases |
| `piper` | sim | rapido e leve; em pt-BR so existe voz masculina |
| `edge` | nao | a mais expressiva, sem chave e sem custo |
| `windows` | sim | plano B nativo, qualidade media |

Voz clonada (voz do estudio) exige GPU (XTTS v2 / Chatterbox) - proximo degrau.
