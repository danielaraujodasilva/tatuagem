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

        // 🔥 extrai só números (IMPORTANTE)
        const numero = jid.replace(/\D/g, '');

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
// ROTA: ENVIAR MENSAGEM
// ==========================
app.post("/enviar", async (req, res) => {
    const { numero, mensagem } = req.body;

    try {
        if (!sock) {
            return res.json({ ok: false, erro: "WhatsApp não conectado ainda" });
        }

        // 🔥 limpa número (ESSENCIAL)
        const numeroLimpo = numero.replace(/\D/g, '');

        const jid = numeroLimpo + "@s.whatsapp.net";

        console.log("📤 Enviando para:", jid);

        const result = await sock.sendMessage(jid, {
            text: mensagem
        });

        console.log("✅ Enviado com sucesso:", result);

        res.json({ ok: true });

    } catch (e) {
        console.log("❌ Erro ao enviar:", e);
        res.json({ ok: false, erro: e.message });
    }
});

// ==========================
// INICIA SERVIDOR
// ==========================
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
});

startBot();