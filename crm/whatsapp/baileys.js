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
const lidToPhone = new Map();   // LID → número real

async function startBot() {
    const { state, saveCreds } = await useMultiFileAuthState("./whatsapp/auth_info");
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: "silent" }),
        markOnlineOnConnect: true,        // ajuda em alguns casos
        syncFullHistory: false,           // evita overload
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
    // RECEBE MENSAGENS (versão mais robusta)
    // ==========================
    sock.ev.on("messages.upsert", async ({ messages, type }) => {
        // type === "notify" → mensagens novas em tempo real
        // type === "append" → mensagens antigas/histórico
        if (type !== "notify") return;   // foca só em mensagens novas

        for (const msg of messages) {     // importante: sempre usar loop!
            if (!msg.message || msg.key.fromMe) continue;

            const key = msg.key;
            const jid = key.remoteJid;

            if (!jid || jid.endsWith("@g.us") || jid.endsWith("@broadcast")) continue;

            // DEBUG (deixe ativado por enquanto)
            console.log("🔍 DEBUG messages.upsert →", JSON.stringify({
                remoteJid: key.remoteJid,
                fromMe: key.fromMe,
                senderPn: key.senderPn,
                id: key.id,
                type: type
            }, null, 2));

            let numeroReal = null;

            if (key.senderPn) {
                numeroReal = key.senderPn.split("@")[0].replace(/\D/g, '');
                if (jid.endsWith("@lid")) {
                    lidToPhone.set(jid.split("@")[0], numeroReal);
                }
            } 
            else if (jid.endsWith("@s.whatsapp.net")) {
                numeroReal = jid.split("@")[0].replace(/\D/g, '');
            } 
            else if (jid.endsWith("@lid")) {
                const lid = jid.split("@")[0];
                numeroReal = lidToPhone.get(lid) || lid;
                if (!lidToPhone.has(lid)) {
                    console.warn(`⚠️ LID sem mapeamento: ${lid}@lid`);
                }
            }

            if (!numeroReal || numeroReal.length < 10) continue;

            // Extrai texto (melhorado)
            let texto = msg.message.conversation ||
                        msg.message.extendedTextMessage?.text ||
                        msg.message.imageMessage?.caption ||
                        msg.message.videoMessage?.caption ||
                        msg.message.documentMessage?.caption ||
                        "";

            if (!texto.trim()) continue;

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
        }
    });

    // Evento auxiliar para mappings (pode ajudar a preencher o mapa)
    sock.ev.on("lid-mapping.update", (updates) => {
        console.log("🔄 LID Mapping recebido:", updates);
        // Você pode popular o lidToPhone aqui se o formato permitir
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
        console.log("✅ Enviada! ID:", result?.key?.id);

        res.json({ ok: true, messageId: result?.key?.id });
    } catch (e) {
        console.error("❌ Erro no envio:", e.message || e);
        res.json({ ok: false, erro: e.message || "Erro desconhecido" });
    }
});

// ==========================
// INICIA TUDO
// ==========================
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
    startBot();
});