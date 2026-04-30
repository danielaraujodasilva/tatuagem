# Ficha

Esta pasta recebeu as melhorias da ficha atualizada do projeto `meduri/tools/ficha`, adaptadas para rodar neste projeto em `/ficha`.

## O que foi limpo nesta migracao

- layout mais novo e estilos centralizados em `assets/style.css`
- cadastro de cliente com anamnese
- cadastro de tatuagem integrado ao cliente
- listagem, detalhes, mapa de clientes e agenda visual
- conexao aceita variaveis `FICHA_DB_*`, `config/conexao.local.php` ou os defaults atuais do projeto

## Estrutura

- `index.php`: cadastro de cliente e anamnese
- `public/cadastrar_tatuagem.php`: cadastro de tatuagem e agendamento
- `public/clientes.php`: lista de clientes e tatuagens
- `agenda/`: agenda visual com FullCalendar
- `database/schema.sql`: esquema inicial do banco `tatuagem_novo`
- `database/atualizacao_tatuagem_novo.sql`: arquivo para importar no phpMyAdmin em bancos existentes

## Setup rapido

1. Importe `ficha/database/atualizacao_tatuagem_novo.sql` no phpMyAdmin se o banco atual ainda nao tiver as tabelas/colunas novas.
2. Ajuste `config/conexao.local.php` apenas se precisar trocar host, banco, usuario ou senha.
3. Acesse `/ficha/` no navegador.

## Observacao

O arquivo `conexao.local.php` esta ignorado no git de proposito. Sem ele, a ficha usa os defaults atuais: banco `tatuagem_novo`, usuario `tatu_user`.
