const {
    default: makeWASocket,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    DisconnectReason
} = require("@whiskeysockets/baileys");

const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const axios = require("axios");
const qrcode = require("qrcode-terminal");
const pino = require("pino");

const app = express();
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, {
    cors: { origin: "*" }
});

let sock = null;
const lidToPhone = new Map();

// ======================
// INICIALIZAÇÃO DO BOT
// ======================
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
            console.log(`❌ WhatsApp desconectado (código: ${code})`);
            if (code !== DisconnectReason.loggedOut) {
                setTimeout(startBot, 5000);
            }
        }
    });

    // Recebe mensagens
    sock.ev.on("messages.upsert", async ({ messages, type }) => {
        if (type !== "notify") return;

        for (const msg of messages) {
            if (!msg.message || msg.key.fromMe) continue;

            const key = msg.key;
            const jid = key.remoteJid || "";

            if (!jid || jid.endsWith("@g.us") || jid.endsWith("@broadcast")) continue;

            let numeroReal = key.remoteJidAlt 
                ? key.remoteJidAlt.split("@")[0].replace(/\D/g, '')
                : key.senderPn 
                ? key.senderPn.split("@")[0].replace(/\D/g, '')
                : jid.endsWith("@s.whatsapp.net") 
                ? jid.split("@")[0].replace(/\D/g, '')
                : jid.endsWith("@lid") ? (lidToPhone.get(jid.split("@")[0]) || jid.split("@")[0]) : null;

            if (!numeroReal || numeroReal.length < 10) continue;

            let texto = msg.message.conversation || 
                        msg.message.extendedTextMessage?.text || 
                        msg.message.imageMessage?.caption || "";

            if (!texto.trim()) continue;

            console.log(`📩 Mensagem recebida de ${numeroReal}: ${texto}`);

            // Webhook
            try {
                await axios.post("http://localhost/crm/webhook.php", { numero: numeroReal, mensagem: texto });
            } catch (e) {}

            // Socket.IO
            io.emit("nova-mensagem", { numero: numeroReal, mensagem: texto });
        }
    });
}

// Rota de envio
app.post("/enviar", async (req, res) => {
    const { numero, mensagem } = req.body;
    if (!numero || !mensagem) return res.json({ ok: false, erro: "Faltam dados" });

    if (!sock || !sock.user) return res.json({ ok: false, erro: "WhatsApp não conectado" });

    try {
        let numeroLimpo = numero.replace(/\D/g, '');
        if (!numeroLimpo.startsWith('55')) numeroLimpo = '55' + numeroLimpo;

        const [resultado] = await sock.onWhatsApp(numeroLimpo);
        if (!resultado?.jid) return res.json({ ok: false, erro: "Número inválido" });

        await sock.sendMessage(resultado.jid, { text: mensagem });
        io.emit("mensagem-enviada", { numero: numeroLimpo, mensagem });

        res.json({ ok: true });
    } catch (e) {
        res.json({ ok: false, erro: e.message });
    }
});

// ======================
// INICIA SERVIDOR
// ======================
server.listen(3001, '0.0.0.0', () => {
    console.log("🚀 Servidor WhatsApp + Socket.IO iniciado na porta 3001");
    console.log("   → Teste: http://127.0.0.1:3001");
    startBot();
}); 