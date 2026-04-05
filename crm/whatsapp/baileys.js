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

    const { state, saveCreds } = await useMultiFileAuthState("./auth_info");
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false // vamos gerar manual
    });

    sock.ev.on("creds.update", saveCreds);

    // 🔥 MOSTRAR QR CODE
    sock.ev.on("connection.update", (update) => {
        const { connection, qr } = update;

        if (qr) {
            console.log("📱 Escaneia esse QR aí:");
            qrcode.generate(qr, { small: true });
        }

        if (connection === "open") {
            console.log("✅ WhatsApp conectado!");
        }

        if (connection === "close") {
            console.log("❌ Conexão fechada");
        }
    });

    sock.ev.on("messages.upsert", async ({ messages }) => {
        const msg = messages[0];

        if (!msg.message) return;

        const texto = msg.message.conversation || msg.message.extendedTextMessage?.text;
        if (!texto) return;

        const numero = msg.key.remoteJid.replace("@s.whatsapp.net", "");

        console.log("Mensagem recebida:", numero, texto);

        try {
            await axios.post("http://localhost/crm/webhook.php", {
                numero,
                mensagem: texto
            });
        } catch (err) {
            console.log("Erro ao enviar pro CRM:", err.message);
        }
    });
}

startBot();