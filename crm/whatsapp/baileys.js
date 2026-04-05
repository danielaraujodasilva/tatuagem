const { 
    default: makeWASocket, 
    useMultiFileAuthState, 
    fetchLatestBaileysVersion,
    DisconnectReason
} = require("@whiskeysockets/baileys");

const fs = require("fs");
const axios = require("axios");
const qrcode = require("qrcode-terminal");

async function startBot() {

    const { state, saveCreds } = await useMultiFileAuthState("./whatsapp/auth_info");
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false
    });

    // salva sessão
    sock.ev.on("creds.update", saveCreds);

    // conexão / QR / reconexão
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

    // recebimento de mensagens
    sock.ev.on("messages.upsert", async ({ messages }) => {
        const msg = messages[0];

        if (!msg.message) return;

        const texto =
            msg.message.conversation ||
            msg.message.extendedTextMessage?.text;

        if (!texto) return;

        const numero = msg.key.remoteJid.replace("@s.whatsapp.net", "");

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

import express from 'express';

const app = express();
app.use(express.json());

app.post('/enviar', async (req, res) => {
    const { numero, mensagem } = req.body;

    try {
        await sock.sendMessage(numero + "@s.whatsapp.net", {
            text: mensagem
        });

        res.json({ ok: true });

    } catch (e) {
        console.log("Erro ao enviar:", e);
        res.json({ ok: false });
    }
});

app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
});

startBot();