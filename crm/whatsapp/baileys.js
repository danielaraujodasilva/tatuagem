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

const app = express();
app.use(express.json());

let sock = null;

// Mapa para armazenar LID → Número real (telefone)
const lidToPhone = new Map();

async function startBot() {
    const { state, saveCreds } = await useMultiFileAuthState("./whatsapp/auth_info");
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: "silent" }),
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

            console.log(`❌ Conexão fechada. Código: ${code || "desconhecido"}`);

            if (shouldReconnect) {
                console.log("🔄 Tentando reconectar em 5 segundos...");
                setTimeout(startBot, 5000);
            } else {
                console.log("🚫 Sessão expirada. Delete a pasta 'whatsapp/auth_info' e escaneie novamente.");
            }
        }
    });

    // ==========================
    // RECEBE MENSAGENS
    // ==========================
    sock.ev.on("messages.upsert", async ({ messages }) => {
        const msg = messages[0];
        if (!msg.message || msg.key.fromMe) return;

        const key = msg.key;
        const jid = key.remoteJid;

        // Ignora grupos e broadcasts
        if (!jid || jid.endsWith("@g.us") || jid.endsWith("@broadcast")) return;

        // === DEBUG COMPLETO (remova depois que resolver) ===
        console.log("🔍 DEBUG KEY:", JSON.stringify({
            remoteJid: key.remoteJid,
            fromMe: key.fromMe,
            senderPn: key.senderPn,
            senderLid: key.senderLid,
            id: key.id
        }, null, 2));

        let numeroReal = null;

        // 1. Tenta senderPn (melhor caso)
        if (key.senderPn) {
            numeroReal = key.senderPn.split("@")[0].replace(/\D/g, '');
            // Salva no mapa
            if (jid.endsWith("@lid")) {
                lidToPhone.set(jid.split("@")[0], numeroReal);
            }
        }
        // 2. Se for @s.whatsapp.net
        else if (jid.endsWith("@s.whatsapp.net")) {
            numeroReal = jid.split("@")[0].replace(/\D/g, '');
        }
        // 3. Se for @lid → procura no nosso mapa
        else if (jid.endsWith("@lid")) {
            const lid = jid.split("@")[0];
            numeroReal = lidToPhone.get(lid);

            if (!numeroReal) {
                console.warn(`⚠️ LID sem mapeamento conhecido: ${lid}@lid`);
                numeroReal = lid; // fallback (não ideal)
            }
        }

        if (!numeroReal || numeroReal.length < 10) {
            console.log(`❌ Não consegui extrair número do JID: ${jid}`);
            return;
        }

        // Pega o texto
        let texto = 
            msg.message.conversation ||
            msg.message.extendedTextMessage?.text ||
            msg.message.imageMessage?.caption ||
            msg.message.videoMessage?.caption ||
            msg.message.documentMessage?.caption ||
            "";

        if (!texto.trim()) return;

        console.log(`📩 Mensagem recebida de ${numeroReal} | JID: ${jid} | Texto: ${texto}`);

        try {
            await axios.post("http://localhost/crm/webhook.php", {
                numero: numeroReal,
                mensagem: texto,
                jidCompleto: jid,
                isLid: jid.endsWith("@lid")
            });
        } catch (err) {
            console.error("❌ Erro ao enviar pro CRM:", err.message);
        }
    });

    // Evento para capturar novos mapeamentos (pode ajudar em alguns casos)
    sock.ev.on("lid-mapping.update", (update) => {
        console.log("🔄 Novo LID mapping recebido:", update);
        // Aqui você pode popular o lidToPhone se o formato permitir
    });
}

// ==========================
// ENVIO DE MENSAGEM
// ==========================
app.post("/enviar", async (req, res) => {
    const { numero, mensagem } = req.body;

    if (!numero || !mensagem) {
        return res.json({ ok: false, erro: "Número e mensagem são obrigatórios" });
    }

    if (!sock || !sock.user) {
        return res.json({ ok: false, erro: "WhatsApp não está conectado" });
    }

    try {
        let numeroLimpo = numero.replace(/\D/g, '');
        if (!numeroLimpo.startsWith('55')) numeroLimpo = '55' + numeroLimpo;

        const [resultado] = await sock.onWhatsApp(numeroLimpo);

        if (!resultado?.jid) {
            console.log("⚠️ Número não encontrado:", numeroLimpo);
            return res.json({ ok: false, erro: "Número não tem WhatsApp" });
        }

        const jidCorreto = resultado.jid;
        console.log(`📤 Enviando para: ${jidCorreto}`);

        const result = await sock.sendMessage(jidCorreto, { text: mensagem });
        console.log("✅ Mensagem enviada! ID:", result?.key?.id);

        res.json({ ok: true, messageId: result?.key?.id });
    } catch (e) {
        console.error("❌ Erro ao enviar:", e.message || e);
        res.json({ ok: false, erro: e.message || "Erro desconhecido" });
    }
});

// ==========================
// INICIA SERVIDOR
// ==========================
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
    startBot();
});