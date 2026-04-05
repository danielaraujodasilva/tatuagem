const {
    default: makeWASocket,
    useMultiFileAuthState,
    fetchLatestBaileysVersion
} = require("@whiskeysockets/baileys");

const axios = require("axios");
const qrcode = require("qrcode-terminal");
const express = require("express");

const app = express();
app.use(express.json());

let sock;

// ==========================
// INICIA BOT
// ==========================
async function startBot() {

    const { state, saveCreds } = await useMultiFileAuthState("./whatsapp/auth_info");
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false
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
            const shouldReconnect =
                lastDisconnect?.error?.output?.statusCode !== 401;

            console.log("❌ Conexão fechada.");

            if (shouldReconnect) {
                console.log("🔄 Tentando reconectar...");
                startBot();
            } else {
                console.log("🚫 Sessão expirada. Apague auth_info e escaneie novamente.");
            }
        }
    });

    // ==========================
    // RECEBE MENSAGENS
    // ==========================
    sock.ev.on("messages.upsert", async ({ messages }) => {
        const msg = messages[0];

        if (!msg.message) return;

        const texto =
            msg.message.conversation ||
            msg.message.extendedTextMessage?.text;

        if (!texto) return;

        const jid = msg.key.remoteJid;

        let numero = null;

        // tenta extrair número de qualquer formato
        if (jid) {
            numero = jid.replace(/\D/g, '');
        }

        // fallback (casos estranhos)
        if (!numero && msg.key.participant) {
            numero = msg.key.participant.replace(/\D/g, '');
        }

        // valida número mínimo
        if (!numero || numero.length < 10) {
            console.log("⚠️ Número inválido:", jid);
            return;
        }

        console.log("📩 Mensagem recebida:", numero, "-", texto);

        try {
            await axios.post("http://localhost/crm/webhook.php", {
                numero,
                mensagem: texto
            });
        } catch (err) {
            console.log("❌ Erro ao enviar pro CRM:", err.message);
        }
    });
}

// ==========================
// ENVIO DE MENSAGEM
// ==========================
app.post("/enviar", async (req, res) => {
    const { numero, mensagem } = req.body;

    try {
        if (!sock) {
            return res.json({ ok: false, erro: "WhatsApp não conectado ainda" });
        }

        const numeroLimpo = numero.replace(/\D/g, '');

        if (!numeroLimpo || numeroLimpo.length < 10) {
            return res.json({ ok: false, erro: "Número inválido" });
        }

        const jid = numeroLimpo + "@s.whatsapp.net";

        console.log("📤 Enviando para:", jid);

        const result = await sock.sendMessage(jid, {
            text: mensagem
        });

        console.log("✅ Mensagem enviada:", result);

        res.json({ ok: true });

    } catch (e) {
        console.log("❌ Erro ao enviar:", e);
        res.json({ ok: false, erro: e.message });
    }
});

// ==========================
// START SERVIDOR
// ==========================
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
});

// inicia bot
startBot();