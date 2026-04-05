// ==========================
// ENVIO DE MENSAGEM (versão corrigida para Brasil)
// ==========================
app.post("/enviar", async (req, res) => {
    const { numero, mensagem } = req.body;

    if (!numero || !mensagem) {
        return res.json({ ok: false, erro: "Número e mensagem são obrigatórios" });
    }

    if (!sock || !sock.user) {
        return res.json({ ok: false, erro: "WhatsApp não está conectado" });
    }

    try {
        let numeroLimpo = numero.replace(/\D/g, '');

        // Garante que comece com 55
        if (!numeroLimpo.startsWith('55')) {
            numeroLimpo = '55' + numeroLimpo;
        }

        console.log(`📤 Tentando enviar para: ${numeroLimpo}`);

        let jidCorreto = null;

        // Tenta primeiro com o número como veio (geralmente com 9)
        let testJid = numeroLimpo + '@s.whatsapp.net';
        let [resultado] = await sock.onWhatsApp(testJid);

        if (resultado?.exists) {
            jidCorreto = resultado.jid;
            console.log(`✅ Encontrado com 9: ${jidCorreto}`);
        } 
        else {
            // Se não achou, tenta REMOVENDO o 9 (caso comum fora de SP/RJ)
            if (numeroLimpo.length === 13 && numeroLimpo[4] === '9') {  // 55 + DD + 9 + 8 dígitos
                const semNove = numeroLimpo.slice(0, 4) + numeroLimpo.slice(5); // remove o 9
                testJid = semNove + '@s.whatsapp.net';
                [resultado] = await sock.onWhatsApp(testJid);

                if (resultado?.exists) {
                    jidCorreto = resultado.jid;
                    console.log(`✅ Encontrado SEM o 9: ${jidCorreto}`);
                }
            }
        }

        if (!jidCorreto) {
            return res.json({ 
                ok: false, 
                erro: "Número não tem WhatsApp ou não foi encontrado (testamos com e sem o 9)" 
            });
        }

        const result = await sock.sendMessage(jidCorreto, { text: mensagem });

        console.log("✅ Mensagem enviada com sucesso! ID:", result?.key?.id);

        res.json({ ok: true, messageId: result?.key?.id, jidUsado: jidCorreto });

    } catch (e) {
        console.error("❌ Erro ao enviar mensagem:", e);
        res.json({ 
            ok: false, 
            erro: e.message || "Erro desconhecido no Baileys"
        });
    }
});