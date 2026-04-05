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

    sock.ev.on("connection.update", ({ connection, qr, lastDisconnect }) => {
        if (qr) {
            console.log("\n📱 Escaneia esse QR aí:\n");
            qrcode.generate(qr, { small: true });
        }
        if (connection === "open") console.log("✅ WhatsApp conectado com sucesso!");
        if (connection === "close") {
            const shouldReconnect = lastDisconnect?.error?.output?.statusCode !== 401;
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

        const texto = msg.message.conversation || msg.message.extendedTextMessage?.text;
        if (!texto) return;

        const jid = msg.key.remoteJid;
        const numero = jid.replace(/\D/g, ''); // só números

        console.log("📩 Mensagem recebida de:", numero, "-", texto);

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

    console.log("📌 Recebi para enviar:", numero, mensagem);

    if (!sock) return res.json({ ok: false, erro: "WhatsApp não conectado ainda" });

    const numeroLimpo = numero.replace(/\D/g, '');
    const jid = numeroLimpo + "@s.whatsapp.net";

    console.log("📤 Tentando enviar para JID:", jid);

    try {
        const result = await sock.sendMessage(jid, { text: mensagem });
        console.log("✅ ENVIO OK:", JSON.stringify(result, null, 2));
        res.json({ ok: true });
    } catch (err) {
        console.log("💥 ERRO REAL DO WHATS:", err);
        res.json({ ok: false, erro: err.message });
    }
});

// ==========================
// INICIA SERVIDOR
// ==========================
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
});

// inicia bot
startBot();