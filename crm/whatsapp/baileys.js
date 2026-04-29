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

function jidToDigits(jid) {
    return String(jid || "").split("@")[0].replace(/\D/g, "");
}

function isPhoneJid(jid) {
    return String(jid || "").endsWith("@s.whatsapp.net");
}

function isLidJid(jid) {
    return String(jid || "").endsWith("@lid");
}

function extractContactNumber(key) {
    const jid = key?.remoteJid || "";
    const phoneCandidates = [
        key?.remoteJidAlt,
        key?.senderPn,
        key?.participantAlt,
        key?.participant,
        isPhoneJid(jid) ? jid : "",
    ];

    for (const candidate of phoneCandidates) {
        if (isPhoneJid(candidate)) {
            const digits = jidToDigits(candidate);
            if (digits.length >= 10) return { numero: digits, source: "phone_jid" };
        }
    }

    const lidCandidates = [
        isLidJid(jid) ? jid : "",
        isLidJid(key?.senderLid) ? key.senderLid : "",
        isLidJid(key?.participant) ? key.participant : "",
    ];

    for (const candidate of lidCandidates) {
        const lid = jidToDigits(candidate);
        const mappedPhone = lidToPhone.get(lid);
        if (mappedPhone) return { numero: mappedPhone, source: "lid_map", lid };
        if (lid.length >= 6) return { numero: lid, source: "lid_fallback", lid };
    }

    return { numero: "", source: "not_found" };
}

function logIgnoredMessage(reason, key, text) {
    console.log("Mensagem ignorada:", {
        reason,
        remoteJid: key?.remoteJid || "",
        remoteJidAlt: key?.remoteJidAlt || "",
        senderPn: key?.senderPn || "",
        senderLid: key?.senderLid || "",
        participant: key?.participant || "",
        participantAlt: key?.participantAlt || "",
        id: key?.id || "",
        text: String(text || "").slice(0, 80)
    });
}

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

function normalizeBaileysStatus(status) {
    if (status === undefined || status === null) return "";
    if (typeof status === "number") return status;

    const value = String(status).toLowerCase();
    const map = {
        error: 0,
        pending: 1,
        server_ack: 2,
        delivery_ack: 3,
        read: 4,
        played: 5,
    };

    return map[value] ?? value;
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

            const identity = extractContactNumber(key);
            const numeroReal = identity.numero;

            if (!numeroReal) {
                logIgnoredMessage("numero_nao_identificado", key, extractText(msg.message));
                continue;
            }

            if (jid.endsWith("@lid") && identity.source !== "lid_fallback") {
                lidToPhone.set(jidToDigits(jid), numeroReal);
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

            if (!texto.trim() && !mediaInfo) {
                logIgnoredMessage("sem_texto_ou_midia", key, texto);
                continue;
            }

            console.log(`📩 Mensagem recebida de ${numeroReal} | JID: ${jid} | Origem: ${identity.source} | Texto: ${texto}`);

            // Envia pro painel/CRM com campos que seu painel espera
            try {
                const crmResponse = await axios.post("http://localhost/crm/webhook.php", {
                    numero: numeroReal,
                    mensagem: texto,
                    fromMe: !!key.fromMe,
                    messageId: key.id || null,
                    remoteJid: key.remoteJid || jid,
                    timestamp: toUnixTimestamp(msg.messageTimestamp),
                    jidCompleto: jid,
                    isLid: jid.endsWith("@lid"),
                    tipoMensagem: mediaInfo?.type || "texto",
                    ...mediaPayload
                });
                console.log("Resposta CRM:", crmResponse.data);
            } catch (err) {
                console.error("❌ Erro ao enviar pro CRM:", err.message);
            }
        }
    });

    sock.ev.on("messages.update", async (updates) => {
        for (const item of updates) {
            const messageId = item.key?.id;
            const status = normalizeBaileysStatus(item.update?.status);
            if (!messageId || status === undefined || status === null) continue;
            console.log("Status update:", messageId, status, item.update);

            try {
                await axios.post("http://localhost/crm/webhook.php", {
                    statusUpdate: true,
                    messageId,
                    remoteJid: item.key?.remoteJid || "",
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
            status = normalizeBaileysStatus(status);
            console.log("Receipt update:", messageId, status, receipt);

            try {
                await axios.post("http://localhost/crm/webhook.php", {
                    statusUpdate: true,
                    messageId,
                    remoteJid: item.key?.remoteJid || "",
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
        let mediaSize = 0;
        if (media?.base64) {
            const buffer = Buffer.from(media.base64, "base64");
            mediaSize = buffer.length;
            const mime = media.mime || "application/octet-stream";
            const fileName = media.fileName || "arquivo";

            if (mime.startsWith("image/")) {
                payload = { image: buffer, mimetype: mime, caption: mensagem || "" };
            } else if (mime.startsWith("video/")) {
                payload = { video: buffer, mimetype: mime, caption: mensagem || "" };
            } else if (mime.startsWith("audio/")) {
                payload = {
                    audio: buffer,
                    mimetype: media.ptt ? "audio/ogg; codecs=opus" : mime,
                    ptt: !!media.ptt
                };
            } else {
                payload = { document: buffer, mimetype: mime, fileName, caption: mensagem || "" };
            }
        }

        console.log("Payload de envio:", {
            hasMedia: !!media?.base64,
            mime: media?.mime || "",
            ptt: !!media?.ptt,
            fileName: media?.fileName || "",
            size: mediaSize
        });

        const result = await sock.sendMessage(resultado.jid, payload);
        console.log("✅ Mensagem enviada! ID:", result?.key?.id);

        res.json({ ok: true, messageId: result?.key?.id, remoteJid: result?.key?.remoteJid });
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
