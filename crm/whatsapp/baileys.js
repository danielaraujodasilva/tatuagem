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
const lidToPhone = new Map(); // Backup caso precise

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
        markOnlineOnConnect: true,
    });

    sock.ev.on("creds.update", saveCreds);

    sock.ev.on("connection.update", (update) => {
        const { connection, qr, lastDisconnect } = update;

        if (qr) {
            console.log("\n📱 Escaneia esse QR aí:\n");
            qrcode.generate(qr, { small: true });
        }

        if (connection === "open") console.log("✅ WhatsApp conectado!");
        if (connection === "close") {
            const code = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = code !== DisconnectReason.loggedOut;

            console.log(`❌ Conexão fechada (código: ${code || "desconhecido"})`);
            if (shouldReconnect) setTimeout(startBot, 5000);
            else console.log("🚫 Sessão expirada. Delete 'whatsapp/auth_info' e escaneie QR.");
        }
    });

    // ==========================
    // RECEBE MENSAGENS
    // ==========================
    sock.ev.on("messages.upsert", async ({ messages, type }) => {
        if (type !== "notify") return;

        for (const msg of messages) {
            if (!msg.message || msg.key.fromMe) continue;

            const key = msg.key;
            const jid = key.remoteJid || "";

            // ignora grupos e broadcast
            if (!jid.endsWith("@s.whatsapp.net")) continue;

            // === PEGA O NÚMERO REAL ===
            let numeroReal = null;

            // Prioridade 1: participant (para grupos, não usado aqui mas mantido)
            if (key.participant) numeroReal = key.participant.split("@")[0].replace(/\D/g, '');
            // Prioridade 2: remoteJid normal
            else if (jid.endsWith("@s.whatsapp.net")) numeroReal = jid.split("@")[0].replace(/\D/g, '');
            // Prioridade 3: fallback para lid
            else if (jid.endsWith("@lid")) numeroReal = lidToPhone.get(jid.split("@")[0]) || jid.split("@")[0];

            if (!numeroReal || numeroReal.length < 10) continue;

            // salva lid mapping
            if (jid.endsWith("@lid") && numeroReal !== jid.split("@")[0]) {
                lidToPhone.set(jid.split("@")[0], numeroReal);
            }

            // pega texto
            const texto =
                msg.message.conversation ||
                msg.message.extendedTextMessage?.text ||
                msg.message.imageMessage?.caption ||
                msg.message.videoMessage?.caption ||
                msg.message.documentMessage?.caption ||
                "";

            if (!texto.trim()) continue;

            console.log(`📩 Mensagem de ${numeroReal}: ${texto}`);

            try {
                await axios.post("http://localhost/crm/webhook.php", {
                    numero: numeroReal,
                    mensagem: texto
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

    if (!numero || !mensagem) return res.json({ ok: false, erro: "Número e mensagem obrigatórios" });
    if (!sock || !sock.user) return res.json({ ok: false, erro: "WhatsApp não conectado" });

    try {
        let numeroLimpo = numero.replace(/\D/g, '');
        if (!numeroLimpo.startsWith('55')) numeroLimpo = '55' + numeroLimpo;

        const [resultado] = await sock.onWhatsApp(numeroLimpo);
        if (!resultado?.jid) return res.json({ ok: false, erro: "Número não tem WhatsApp" });

        console.log(`📤 Enviando para: ${resultado.jid}`);
        const result = await sock.sendMessage(resultado.jid, { text: mensagem });
        console.log("✅ Mensagem enviada! ID:", result?.key?.id);

        res.json({ ok: true, messageId: result?.key?.id });
    } catch (e) {
        console.error("❌ Erro ao enviar:", e.message || e);
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