const { default: makeWASocket, useMultiFileAuthState, fetchLatestBaileysVersion } = require("@whiskeysockets/baileys");
const fs = require("fs");
const axios = require("axios");

async function startBot() {
    const { state, saveCreds } = await useMultiFileAuthState("./auth_info");

    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        auth: state
    });

    sock.ev.on("creds.update", saveCreds);

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