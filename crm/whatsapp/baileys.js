const {
    default: makeWASocket,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    DisconnectReason
} = require("@whiskeysockets/baileys");

const axios = require("axios");
const qrcode = require("qrcode-terminal");
const express = require("express");
const pino = require("pino");

const app = express();
app.use(express.json());

let sock = null;

// ==========================
// INICIA BOT
// ==========================
async function startBot() {
    const { state, saveCreds } = await useMultiFileAuthState("./whatsapp/auth_info");
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: "silent" }),
        // getMessage: ... (pode adicionar depois se precisar de retries)
    });

    sock.ev.on("creds.update", saveCreds);

    sock.ev.on("connection.update", (update) => {
        const { connection, qr, lastDisconnect } = update;

        if (qr) {
            console.log("\n📱 Escaneia esse QR aí:\n");
            qrcode.generate(qr, { small: true });
        }

        if (connection === "open") {
            console.log("✅ WhatsApp conectado com sucesso!");
        }

        if (connection === "close") {
            const code = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = code !== DisconnectReason.loggedOut;

            console.log(`❌ Conexão fechada. Código: ${code || "desconhecido"}`);

            if (shouldReconnect) {
                console.log("🔄 Tentando reconectar em 5 segundos...");
                setTimeout(startBot, 5000);
            } else {
                console.log("🚫 Sessão expirada. Delete a pasta 'whatsapp/auth_info' e escaneie o QR novamente.");
            }
        }
    });

    // ==========================
    // RECEBE MENSAGENS
    // ==========================
    sock.ev.on("messages.upsert", async ({ messages }) => {
        const msg = messages[0];
        if (!msg.message || msg.key.fromMe) return;

        const key = msg.key;
        let jid = key.remoteJid;

        // Ignora grupos, canais, status e broadcast
        if (!jid || jid.endsWith("@g.us") || jid.endsWith("@broadcast")) return;

        let numeroReal = null;

        // === LÓGICA PRINCIPAL PARA PEGAR O NÚMERO REAL ===
        if (key.senderPn) {
            // senderPn é o mais confiável quando disponível (formato com telefone real)
            numeroReal = key.senderPn.split("@")[0];
        } 
        else if (jid.endsWith("@s.whatsapp.net")) {
            // Caso clássico
            numeroReal = jid.split("@")[0];
        } 
        else if (jid.endsWith("@lid")) {
            // Fallback quando é LID puro
            numeroReal = jid.split("@")[0];
            console.warn(`⚠️ Mensagem recebida via LID: ${jid}`);
        }

        if (!numeroReal || numeroReal.length < 10) {
            console.log(`❌ Não foi possível extrair número válido do JID: ${jid}`);
            return;
        }

        // Limpa o número (remove tudo que não for dígito)
        numeroReal = numeroReal.replace(/\D/g, '');

        // Extrai o texto da mensagem (suporta texto simples, texto estendido, legenda de imagem/vídeo)
        let texto = 
            msg.message.conversation ||
            msg.message.extendedTextMessage?.text ||
            msg.message.imageMessage?.caption ||
            msg.message.videoMessage?.caption ||
            msg.message.documentMessage?.caption ||
            "";

        if (!texto.trim()) return;

        console.log(`📩 Mensagem recebida de ${numeroReal} | JID: ${jid} | Texto: ${texto}`);

        // Envia para o seu CRM
        try {
            await axios.post("http://localhost/crm/webhook.php", {
                numero: numeroReal,
                mensagem: texto,
                jidCompleto: jid,
                isLid: jid.endsWith("@lid")
            });
        } catch (err) {
            console.error("❌ Erro ao enviar pro CRM:", err.message);
        }
    });

    // Evento opcional útil para debug de LIDs (pode remover depois)
    sock.ev.on("lid-mapping.update", (mappings) => {
        console.log("🔄 LID Mapping atualizado:", mappings);
    });
}

// ==========================
// ENVIO DE MENSAGEM (mantido quase igual, só melhorias)
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
        if (!numeroLimpo.startsWith('55')) numeroLimpo = '55' + numeroLimpo;

        const [resultado] = await sock.onWhatsApp(numeroLimpo + "@s.whatsapp.net");

        if (!resultado?.jid) {
            console.log("⚠️ Número não encontrado no WhatsApp:", numeroLimpo);
            return res.json({ ok: false, erro: "Número não possui WhatsApp ou não foi encontrado" });
        }

        const jidCorreto = resultado.jid;
        console.log(`📤 Enviando mensagem para: ${jidCorreto}`);

        const result = await sock.sendMessage(jidCorreto, { text: mensagem });

        console.log("✅ Mensagem enviada com sucesso! ID:", result?.key?.id);
        res.json({ ok: true, messageId: result?.key?.id });
    } catch (e) {
        console.error("❌ Erro ao enviar mensagem:", e);
        res.json({ ok: false, erro: e.message || "Erro desconhecido" });
    }
});

// ==========================
// INICIA SERVIDOR
// ==========================
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
    startBot();
});