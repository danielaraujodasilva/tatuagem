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
const lidToPhone = new Map();   // Backup caso precise no futuro
const startedAt = Math.floor(Date.now() / 1000);

async function startBot() {
    const { state, saveCreds } = await useMultiFileAuthState("./whatsapp/auth_info");
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: "silent" }),
        markOnlineOnConnect: true,
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

            console.log(`❌ Conexão fechada (código: ${code || "desconhecido"})`);

            if (shouldReconnect) {
                console.log("🔄 Reconectando em 5s...");
                setTimeout(startBot, 5000);
            } else {
                console.log("🚫 Sessão inválida. Delete a pasta 'whatsapp/auth_info' e escaneie novamente.");
            }
        }
    });

    // ==========================
    // RECEBE MENSAGENS
    // ==========================
    sock.ev.on("messages.upsert", async ({ messages, type }) => {
        if (!["notify", "append"].includes(type)) return;

        for (const msg of messages) {
            if (!msg.message) continue;
            if (type === "append" && Number(msg.messageTimestamp || 0) < startedAt - 10) continue;

            const key = msg.key;
            const jid = key.remoteJid || "";

            if (!jid || jid.endsWith("@g.us") || jid.endsWith("@broadcast")) continue;

            // === LÓGICA PRINCIPAL PARA PEGAR O NÚMERO REAL ===
            let numeroReal = null;

            if (key.remoteJidAlt) {
                numeroReal = key.remoteJidAlt.split("@")[0].replace(/\D/g, '');
            } else if (key.senderPn) {
                numeroReal = key.senderPn.split("@")[0].replace(/\D/g, '');
            } else if (jid.endsWith("@s.whatsapp.net")) {
                numeroReal = jid.split("@")[0].replace(/\D/g, '');
            } else if (jid.endsWith("@lid")) {
                const lid = jid.split("@")[0];
                numeroReal = lidToPhone.get(lid) || lid;
            }

            if (!numeroReal || numeroReal.length < 10) continue;

            if (jid.endsWith("@lid") && numeroReal !== jid.split("@")[0]) {
                lidToPhone.set(jid.split("@")[0], numeroReal);
            }

            // Extrai texto da mensagem
            let texto = 
                msg.message.conversation ||
                msg.message.extendedTextMessage?.text ||
                msg.message.imageMessage?.caption ||
                msg.message.videoMessage?.caption ||
                msg.message.documentMessage?.caption ||
                "";

            if (!texto.trim()) continue;

            console.log(`📩 Mensagem recebida de ${numeroReal} | JID: ${jid} | Texto: ${texto}`);

            // Envia pro painel/CRM com campos que seu painel espera
            try {
                await axios.post("http://localhost/crm/webhook.php", {
                    numero: numeroReal,
                    mensagem: texto,
                    fromMe: !!key.fromMe,
                    messageId: key.id || null,
                    jidCompleto: jid,
                    isLid: jid.endsWith("@lid"),
                    tipoMensagem: msg.message?.conversation ? "texto" :
                                  msg.message?.imageMessage ? "imagem" :
                                  msg.message?.videoMessage ? "video" :
                                  msg.message?.documentMessage ? "documento" :
                                  "outro"
                });
            } catch (err) {
                console.error("❌ Erro ao enviar pro CRM:", err.message);
            }
        }
    });
}

// ==========================
// ENVIO DE MENSAGEM
// ==========================
app.post("/enviar", async (req, res) => {
    const { numero, mensagem } = req.body;

    if (!numero || !mensagem) {
        return res.json({ ok: false, erro: "Número e mensagem obrigatórios" });
    }

    if (!sock || !sock.user) {
        return res.json({ ok: false, erro: "WhatsApp não conectado" });
    }

    try {
        let numeroLimpo = numero.replace(/\D/g, '');
        if (!numeroLimpo.startsWith('55')) numeroLimpo = '55' + numeroLimpo;

        const [resultado] = await sock.onWhatsApp(numeroLimpo);

        if (!resultado?.jid) {
            console.log("⚠️ Número não encontrado:", numeroLimpo);
            return res.json({ ok: false, erro: "Número não tem WhatsApp" });
        }

        console.log(`📤 Enviando para: ${resultado.jid}`);

        const result = await sock.sendMessage(resultado.jid, { text: mensagem });
        console.log("✅ Mensagem enviada! ID:", result?.key?.id);

        res.json({ ok: true, messageId: result?.key?.id });
    } catch (e) {
        console.error("❌ Erro ao enviar:", e.message || e);
        res.json({ ok: false, erro: e.message || "Erro desconhecido" });
    }
});

// Inicia servidor
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
    startBot();
});
