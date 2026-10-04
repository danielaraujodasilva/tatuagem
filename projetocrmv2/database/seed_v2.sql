-- projetocrmv2 - conteudo inicial (motor de rotina + playbook aprendido).
-- Seguro e idempotente: usa INSERT IGNORE / ON DUPLICATE KEY.
-- Rode depois de database/schema_v2.sql, no mesmo banco (crm_simples).

-- ---------------------------------------------------------------- regras
INSERT IGNORE INTO v2_regras (id, titulo, evento, acao, atraso_horas, status_destino, mensagem, ativo) VALUES
('r_boas_vindas',     'Boas-vindas ao 1º contato',        'novo_contato',    'responder',       0,    'em_atendimento', 'Opa, legal! Qual seu nome? E já sabe qual tatuagem quer fazer?', 1),
('r_pedir_nome',      'Pedir nome e ideia da tattoo',      'novo_contato',    'criar_tarefa',    1,    'em_atendimento', 'Qual seu nome? Já sabe qual tattoo quer fazer?', 1),
('r_followup_24h',    'Follow-up 24h sem resposta',        'sem_resposta',    'criar_tarefa',    24,   'sem_retorno',    'Oi? Bora retomar o agendamento da sua tatuagem?', 1),
('r_followup_72h',    '2ª tentativa (3 dias)',             'sem_resposta',    'criar_tarefa',    72,   NULL,             'Opa, e ae, bora retomar?', 1),
('r_vaga_domingo',    'Oferecer a vaga do domingo',        'sem_resposta',    'criar_tarefa',    168,  NULL,             'tenho vaga pra domingo, quer aproveitar?', 1),
('r_reativar_6m',     'Reativar cliente frio (6 meses)',   'sem_resposta',    'criar_tarefa',    4320, NULL,             'Oi! Tudo bem? Faz um tempo que a gente não se fala — quer aproveitar pra fechar a próxima arte?', 1),
('r_lembrete_24h',    'Lembrete 24h antes da sessão',      'antes_da_sessao', 'enviar_mensagem', 24,   'agendado',       'Oi! Passando pra confirmar sua sessão de amanhã 🔥', 1),
('r_pos_tattoo',      'Cuidados pós-tattoo (no dia)',      'sessao_concluida','enviar_mensagem', 0,    'fechado',        'Ficou linda! Agora os cuidados: lavar com sabonete neutro, secar com papel e passar a pomada fininha. Nada de sol, piscina ou mar por 15 dias 🙏', 1),
('r_cicatrizacao_d7', 'Acompanhar cicatrização (D+7)',     'dias_apos_sessao','criar_tarefa',    168,  NULL,             'Oi! Como está a cicatrização? Qualquer coisa me chama 😊', 1),
('r_avaliacao_d30',   'Pedir avaliação (D+30)',            'dias_apos_sessao','criar_tarefa',    720,  NULL,             'E aí, como ficou? Se puder deixar sua avaliação no Google eu agradeço demais 🥰', 1),
('r_nova_arte_d90',   'Oferecer nova arte (D+90)',         'dias_apos_sessao','criar_tarefa',    2160, NULL,             'Oi! Já faz 3 meses da última — bora planejar a próxima? 🔥', 1),
('r_aniversario',     'Parabéns no aniversário',           'aniversario',     'enviar_mensagem', 0,    NULL,             'Feliz aniversário! 🎉 Muita saúde e arte boa esse ano.', 1);

-- ---------------------------------------------------------- playbook
INSERT IGNORE INTO v2_aprendizado (tipo, chave, valor, ocorrencias, aprovado) VALUES
('padrao_voz', 'persona',        'Meu nome é Ellen, sou secretária do estúdio do Daniel tatuador 🔥', 33, 1),
('padrao_voz', 'abertura',       'Opa, legal! Qual seu nome? E já sabe qual tatuagem quer fazer?', 4, 1),
('padrao_voz', 'descoberta',     'Qual seu nome? · Já sabe qual tattoo quer fazer? · Quer fazer quando?', 13, 1),
('padrao_voz', 'fechamento',     'Fechou · Assim que tiver ideia de data me chama 🔥 · Obrigada 😘', 14, 1),
('padrao_voz', 'retomada',       'Oi? Bora retomar o agendamento da sua tatuagem?', 57, 1),
('padrao_voz', 'endereco',       'Rua Catende, 287B, Jd Nordeste, São Paulo — pertinho da estação Artur Alvim do metrô', 21, 1),
('estilo',     'comprimento',    'Mensagem média de 66 caracteres: uma frase resolve, ninguém escreve parágrafo.', 1, 1),
('estilo',     'risada',         'Usa "rs", não "kk" (42 ocorrências).', 42, 1),
('estilo',     'emoji',          '🔥 😘 🥰 🌃 🌺 — emoji no lugar de ponto final, carinho sem textão.', 1, 1),
('estilo',     'horario',        'Pico de conversa entre 9h e 21h; 02h e 23h aparecem em follow-up automático.', 1, 1),
('preco',      'sem_anestesia',  '699 sem pomada anestésica', 12, 1),
('preco',      'com_anestesia',  '1100 com pomada anestésica', 7, 1),
('preco',      'por_regiao',     '700 cada região (costas / braço); antebraço interno 500.', 5, 1),
('preco',      'reserva',        'Sinal de 50,00 para reservar; descontado no dia. Pix cai direto no tatuador.', 1, 1),
('objecao',    'vou_pensar',     '10x. Responder com vaga/urgência e carinho — nunca com desconto.', 10, 1),
('objecao',    'preco',          '63x perguntam valor. Responder curto, direto, com a opção sem/com anestesia.', 63, 1),
('objecao',    'medo_dor',       '9x. Tranquilizar falando da pomada anestésica e que dá pra fazer em etapas.', 9, 1);
