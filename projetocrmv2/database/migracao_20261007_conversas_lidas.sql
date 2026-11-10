-- 2026-10-07 - Estado de leitura por conversa (feed da ponte -> tabela).
--
-- Regra desta primeira versao (sem depender de evento de leitura do WhatsApp):
--   - se a ULTIMA mensagem da conversa e do estudio (from_me=1) -> lida
--   - se a ULTIMA mensagem e do cliente (from_me=0)              -> nao lida
-- O ponte_sync mantem isso a cada mensagem importada. Quando a ponte passar a
-- mandar o "unreadCount" real do celular, ele sobrescreve as mesmas colunas.
--
-- Incremental e reversivel: nao apaga nada, so acrescenta o estado.
ALTER TABLE whatsapp_conversations
    ADD COLUMN nao_lida TINYINT(1) NOT NULL DEFAULT 0 AFTER last_message_at,
    ADD COLUMN nao_lida_at DATETIME NULL DEFAULT NULL AFTER nao_lida;

-- Backfill do historico que ja esta na tabela.
UPDATE whatsapp_conversations c
SET nao_lida = COALESCE((
        SELECT IF(m.from_me = 1, 0, 1) FROM whatsapp_messages m
         WHERE m.conversation_id = c.id
         ORDER BY COALESCE(m.sent_at, m.created_at) DESC, m.id DESC LIMIT 1), 0),
    nao_lida_at = (
        SELECT COALESCE(m.sent_at, m.created_at) FROM whatsapp_messages m
         WHERE m.conversation_id = c.id
         ORDER BY COALESCE(m.sent_at, m.created_at) DESC, m.id DESC LIMIT 1);