const {
    default: makeWASocket,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
    DisconnectReason,   // ← Adicionado
    // Boom (se precisar tratar erros melhor)
} = require("@whiskeysockets/baileys");

const axios = require("axios");
const qrcode = require("qrcode-terminal");
const express = require("express");
const pino = require("pino");   // ← Recomendado para debug

const app = express();
app.use(express.json());

let sock = null;

// ==========================
// INICIA BOT
// ==========================
async function startBot() {
    const { state, saveCreds } = await useMultiFileAuthState("./whatsapp/auth_info");
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: "silent" }), // mude para "debug" se quiser ver tudo
        // markOnlineOnConnect: false,     // descomente se quiser ficar "offline"
        // syncFullHistory: false,         // só ative se precisar
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
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

            console.log(`❌ Conexão fechada. Código: ${statusCode || 'desconhecido'}`);

            if (shouldReconnect) {
                console.log("🔄 Tentando reconectar em 5s...");
                setTimeout(startBot, 5000); // delay evita loop rápido
            } else {
                console.log("🚫 Sessão expirada. Apague a pasta 'whatsapp/auth_info' e escaneie novamente.");
            }
        }
    });

    // ==========================
    // RECEBE MENSAGENS (mantido quase igual)
    // ==========================
    sock.ev.on("messages.upsert", async ({ messages }) => {
        const msg = messages[0];
        if (!msg.message) return;

        const texto = msg.message.conversation || msg.message.extendedTextMessage?.text;
        if (!texto) return;

        const jid = msg.key.remoteJid;
        let numero = jid ? jid.replace(/\D/g, '') : null;

        if (!numero || numero.length < 10) return;

        console.log("📩 Mensagem recebida:", numero, "-", texto);

        try {
            await axios.post("http://localhost/crm/webhook.php", { numero, mensagem: texto });
        } catch (err) {
            console.error("❌ Erro ao enviar pro CRM:", err.message);
        }
    });
}

// ==========================
// ENVIO DE MENSAGEM (melhorado)
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

        // Garante que comece com 55 (Brasil)
        if (!numeroLimpo.startsWith('55')) {
            numeroLimpo = '55' + numeroLimpo;
        }

        // Usa onWhatsApp para pegar o JID correto (resolve problema do 9 e números inválidos)
        const [resultado] = await sock.onWhatsApp(numeroLimpo);
        
        if (!resultado?.jid) {
            return res.json({ ok: false, erro: "Número não tem WhatsApp ou não foi encontrado" });
        }

        const jidCorreto = resultado.jid;

        console.log(`📤 Enviando para JID correto: ${jidCorreto}`);

        const result = await sock.sendMessage(jidCorreto, { text: mensagem });

        console.log("✅ Mensagem enviada com sucesso! ID:", result?.key?.id);

        res.json({ ok: true, messageId: result?.key?.id });

    } catch (e) {
        console.error("❌ Erro ao enviar mensagem:", e);
        res.json({ 
            ok: false, 
            erro: e.message || "Erro desconhecido",
            details: e.output?.payload || null 
        });
    }
});

// ==========================
// START SERVIDOR
// ==========================
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
    startBot(); // inicia o bot
});