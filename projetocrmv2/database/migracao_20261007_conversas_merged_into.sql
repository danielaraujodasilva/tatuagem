-- 2026-10-07 - Conversas duplicadas por LID (ponte do WhatsApp).
--
-- A ponte Baileys as vezes manda o chat com o "jid_original" (LID) e sem o
-- telefone resolvido. Ate agora isso criava uma conversa nova por LID, e quando
-- o telefone real aparecia depois ficavam DUAS conversas do mesmo cliente - a
-- fantasma quase sempre com a ultima mensagem recebida, poluindo a aba
-- "Sem resposta" da tela ?page=conversa.
--
-- tools/ponte_sync.php passa a mover as mensagens da fantasma para a conversa
-- real e a marcar a fantasma aqui em merged_into_id. Nada e apagado.
-- Incremental e reversivel: basta remover a coluna para voltar ao estado atual.
ALTER TABLE whatsapp_conversations
    ADD COLUMN merged_into_id BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER customer_id,
    ADD INDEX idx_conversas_merged_into (merged_into_id);