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
const http = require('http');
const { Server } = require('socket.io');

const app = express();
app.use(express.json());

const server = http.createServer(app);

const io = new Server(server, {
    cors: {
        origin: "*",        
        methods: ["GET", "POST"]
    },
    transports: ['polling', 'websocket'],
    pingTimeout: 60000,
    pingInterval: 25000
});

let sock = null;
const lidToPhone = new Map();

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
                console.log("🚫 Sessão inválida. Delete a pasta 'whatsapp/auth_info'.");
            }
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

            if (!jid || jid.endsWith("@g.us") || jid.endsWith("@broadcast")) continue;

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

            let texto = 
                msg.message.conversation ||
                msg.message.extendedTextMessage?.text ||
                msg.message.imageMessage?.caption ||
                msg.message.videoMessage?.caption ||
                msg.message.documentMessage?.caption ||
                "";

            if (!texto.trim()) continue;

            console.log(`📩 Mensagem recebida de ${numeroReal} | JID: ${jid}`);

            // Envia para o webhook.php
            try {
                await axios.post("http://localhost/crm/webhook.php", {
                    numero: numeroReal,
                    mensagem: texto,
                    jidCompleto: jid,
                    isLid: jid.endsWith("@lid")
                });
            } catch (err) {
                console.error("❌ Erro webhook:", err.message);
            }

            // Emite via Socket.IO para o painel
            io.emit("nova-mensagem", {
                numero: numeroReal,
                mensagem: texto,
                jidCompleto: jid,
                isLid: jid.endsWith("@lid")
            });
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

        // Emite para o painel
        io.emit("mensagem-enviada", {
            numero: numeroLimpo,
            mensagem: mensagem
        });

        console.log("✅ Mensagem enviada! ID:", result?.key?.id);
        res.json({ ok: true, messageId: result?.key?.id });
    } catch (e) {
        console.error("❌ Erro ao enviar:", e.message);
        res.json({ ok: false, erro: e.message || "Erro desconhecido" });
    }
});

// Inicia o servidor
server.listen(3001, '0.0.0.0', () => {
    console.log("🚀 Servidor WhatsApp + Socket.IO rodando em http://localhost:3001");
    console.log("📡 Socket.IO pronto para conexões");
    startBot();
});