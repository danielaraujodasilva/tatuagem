const {
    default: makeWASocket,
    useMultiFileAuthState,
    fetchLatestBaileysVersion
} = require("@whiskeysockets/baileys");

const axios = require("axios");
const qrcode = require("qrcode-terminal");
const express = require("express");

const app = express();
app.use(express.json());

let sock;

// ==========================
// INICIA BOT
// ==========================
async function startBot() {

    const { state, saveCreds } = await useMultiFileAuthState("./whatsapp/auth_info");
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false
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

    // ==========================
    // RECEBE MENSAGENS
    // ==========================
    sock.ev.on("messages.upsert", async ({ messages }) => {
        const msg = messages[0];

        if (!msg.message) return;

        const texto =
            msg.message.conversation ||
            msg.message.extendedTextMessage?.text;

        if (!texto) return;

        const jid = msg.key.remoteJid;
        let numero = null;

        // número normal
        if (jid.includes("@s.whatsapp.net")) {
            numero = jid.split("@")[0];
        }

        // fallback (alguns casos @lid)
        else if (msg.key.participant) {
            numero = msg.key.participant.replace(/\D/g, '');
        }

        if (!numero) {
            console.log("⚠️ Não consegui extrair número:", jid);
            return;
        }

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

    // ==========================
    // TESTE AUTOMÁTICO
    // ==========================
    setTimeout(async () => {
        try {
            const numeroTeste = "55119SEUNUMERO"; // <<< COLOCA SEU NÚMERO

            console.log("🧪 Testando envio direto...");

            const [res] = await sock.onWhatsApp(numeroTeste);

            if (!res) {
                console.log("❌ Número inválido no teste");
                return;
            }

            await sock.sendMessage(res.jid, {
                text: "🔥 teste direto baileys"
            });

            console.log("🚀 TESTE DIRETO FUNCIONOU");

        } catch (e) {
            console.log("💥 TESTE DIRETO FALHOU:", e);
        }
    }, 8000);
}

// ==========================
// ROTA: ENVIAR MENSAGEM
// ==========================
app.post("/enviar", async (req, res) => {
    const { numero, mensagem } = req.body;

    try {
        if (!sock) {
            return res.json({ ok: false, erro: "WhatsApp não conectado ainda" });
        }

        const numeroLimpo = numero.replace(/\D/g, '');

        console.log("🔍 Validando número:", numeroLimpo);

        const [resultNumero] = await sock.onWhatsApp(numeroLimpo);

        if (!resultNumero) {
            console.log("❌ Número não existe no WhatsApp:", numeroLimpo);
            return res.json({ ok: false, erro: "Número inválido" });
        }

        const jid = resultNumero.jid;

        console.log("📤 Enviando para:", jid);

        try {
            const result = await sock.sendMessage(jid, {
                text: mensagem
            });

            console.log("✅ ENVIO OK");

            res.json({ ok: true });

        } catch (err) {
            console.log("💥 ERRO REAL DO WHATS:", err);
            res.json({ ok: false, erro: err.message });
        }

    } catch (e) {
        console.log("❌ Erro geral:", e);
        res.json({ ok: false, erro: e.message });
    }
});

// ==========================
// INICIA SERVIDOR
// ==========================
app.listen(3001, () => {
    console.log("🚀 API WhatsApp rodando na porta 3001");
});

startBot();