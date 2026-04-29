const {
    default: makeWASocket,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    downloadMediaMessage,
    DisconnectReason
} = require("@whiskeysockets/baileys");

const axios = require("axios");
const qrcode = require("qrcode-terminal");
const express = require("express");
const pino = require("pino");
const fs = require("fs");
const path = require("path");

const app = express();
app.use(express.json({ limit: "30mb" }));

let sock = null;
const lidToPhone = new Map();   // Backup caso precise no futuro
const startedAt = Math.floor(Date.now() / 1000);

function getMessageContent(message) {
    return message?.ephemeralMessage?.message ||
        message?.viewOnceMessage?.message ||
        message?.viewOnceMessageV2?.message ||
        message;
}

function getMediaMessage(message) {
    const content = getMessageContent(message);
    const mediaTypes = [
        ["image", content?.imageMessage],
        ["video", content?.videoMessage],
        ["audio", content?.audioMessage],
        ["document", content?.documentMessage],
        ["sticker", content?.stickerMessage]
    ];

    for (const [type, payload] of mediaTypes) {
        if (payload) return { type, payload };
    }

    return null;
}

function extractText(message) {
    const content = getMessageContent(message);
    return content?.conversation ||
        content?.extendedTextMessage?.text ||
        content?.imageMessage?.caption ||
        content?.videoMessage?.caption ||
        content?.documentMessage?.caption ||
        "";
}

function toUnixTimestamp(value) {
    if (!value) return Math.floor(Date.now() / 1000);
    if (typeof value === "number") return value;
    if (typeof value === "string") return Number(value) || Math.floor(Date.now() / 1000);
    if (typeof value.toNumber === "function") return value.toNumber();
    if (typeof value.low === "number") return value.low;
    return Math.floor(Date.now() / 1000);
}

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

            let texto = extractText(msg.message);
            const mediaInfo = getMediaMessage(msg.message);
            let mediaPayload = {};

            if (mediaInfo) {
                try {
                    const buffer = await downloadMediaMessage(
                        msg,
                        "buffer",
                        {},
                        { logger: pino({ level: "silent" }) }
                    );
                    mediaPayload = {
                        mediaBase64: buffer.toString("base64"),
                        mediaMime: mediaInfo.payload.mimetype || "",
                        mediaFileName: mediaInfo.payload.fileName || `${mediaInfo.type}_${key.id || Date.now()}`,
                    };
                } catch (err) {
                    console.error("Erro ao baixar midia:", err.message);
                }
            }

            if (!texto.trim() && !mediaInfo) continue;

            console.log(`📩 Mensagem recebida de ${numeroReal} | JID: ${jid} | Texto: ${texto}`);

            // Envia pro painel/CRM com campos que seu painel espera
            try {
                await axios.post("http://localhost/crm/webhook.php", {
                    numero: numeroReal,
                    mensagem: texto,
                    fromMe: !!key.fromMe,
                    messageId: key.id || null,
                    timestamp: toUnixTimestamp(msg.messageTimestamp),
                    jidCompleto: jid,
                    isLid: jid.endsWith("@lid"),
                    tipoMensagem: mediaInfo?.type || "texto",
                    ...mediaPayload
                });
            } catch (err) {
                console.error("❌ Erro ao enviar pro CRM:", err.message);
            }
        }
    });

    sock.ev.on("messages.update", async (updates) => {
        for (const item of updates) {
            const messageId = item.key?.id;
            const status = item.update?.status;
            if (!messageId || status === undefined || status === null) continue;

            try {
                await axios.post("http://localhost/crm/webhook.php", {
                    statusUpdate: true,
                    messageId,
                    status
                });
            } catch (err) {
                console.error("Erro ao atualizar status da mensagem:", err.message);
            }
        }
    });

    sock.ev.on("message-receipt.update", async (updates) => {
        for (const item of updates) {
            const messageId = item.key?.id;
            const receipt = item.receipt || {};
            if (!messageId) continue;

            let status = "";
            if (receipt.playedTimestamp) status = "played";
            else if (receipt.readTimestamp) status = "read";
            else if (receipt.receiptTimestamp) status = "delivered";
            else if (receipt.status !== undefined && receipt.status !== null) status = receipt.status;

            if (!status) continue;

            try {
                await axios.post("http://localhost/crm/webhook.php", {
                    statusUpdate: true,
                    messageId,
                    status
                });
            } catch (err) {
                console.error("Erro ao atualizar recibo da mensagem:", err.message);
            }
        }
    });
}

// ==========================
// ENVIO DE MENSAGEM
// ==========================
app.post("/enviar", async (req, res) => {
    const { numero, mensagem, media } = req.body;

    if (!numero || (!mensagem && !media?.base64)) {
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

        let payload = { text: mensagem };
        if (media?.base64) {
            const buffer = Buffer.from(media.base64, "base64");
            const mime = media.mime || "application/octet-stream";
            const fileName = media.fileName || "arquivo";

            if (mime.startsWith("image/")) {
                payload = { image: buffer, mimetype: mime, caption: mensagem || "" };
            } else if (mime.startsWith("video/")) {
                payload = { video: buffer, mimetype: mime, caption: mensagem || "" };
            } else if (mime.startsWith("audio/")) {
                payload = { audio: buffer, mimetype: mime };
            } else {
                payload = { document: buffer, mimetype: mime, fileName, caption: mensagem || "" };
            }
        }

        const result = await sock.sendMessage(resultado.jid, payload);
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
