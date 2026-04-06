<?php /* seu código PHP de cima permanece igual */ ?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat - <?= htmlspecialchars($cliente['nome']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.socket.io/4.7.1/socket.io.min.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 flex flex-col h-screen">
    <!-- HEADER e MENSAGENS permanecem iguais -->

    <div class="p-4 border-t border-gray-800 flex gap-3">
        <input id="msgInput" type="text" placeholder="Digite sua mensagem..." class="flex-1 bg-gray-800 px-4 py-2 rounded-xl">
        <button id="enviarBtn" class="bg-emerald-600 px-6 py-2 rounded-xl">Enviar</button>
    </div>

<script>
const socket = io("http://127.0.0.1:3001", {   // ← mudamos para 127.0.0.1
    transports: ['polling', 'websocket'],
    reconnection: true,
    reconnectionAttempts: 20,
    reconnectionDelay: 800,
    timeout: 30000
});

// Debug completo
socket.on('connect', () => console.log('✅ Socket.IO conectado com sucesso!'));
socket.on('connect_error', (err) => {
    console.error('❌ Socket.IO erro:', err.message);
    if (err.description) console.error('Descrição:', err.description);
});
socket.on('disconnect', (reason) => console.warn('⚠️ Socket.IO desconectado:', reason));

const container = document.getElementById('mensagensContainer');
const input = document.getElementById('msgInput');
const enviarBtn = document.getElementById('enviarBtn');

function scrollBottom() { container.scrollTop = container.scrollHeight; }

function adicionarMensagem(texto, fromMe) {
    const div = document.createElement('div');
    div.className = 'flex ' + (fromMe ? 'justify-end' : 'justify-start');
    div.innerHTML = `
        <div class="${fromMe ? 'bg-emerald-600' : 'bg-gray-800'} px-4 py-2 rounded-2xl max-w-xs">
            <p>${texto}</p>
            <span class="text-xs text-gray-300 block mt-1">${new Date().toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})}</span>
        </div>`;
    container.appendChild(div);
    scrollBottom();
}

function enviarMensagem() {
    const texto = input.value.trim();
    if (!texto) return;

    fetch('enviar.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ numero: "<?= $cliente['numero'] ?>", mensagem: texto })
    })
    .then(r => r.json())
    .then(res => { if (res.ok) adicionarMensagem(texto, true); })
    .catch(err => console.error(err));

    input.value = '';
}

enviarBtn.addEventListener('click', enviarMensagem);
input.addEventListener('keydown', e => { if (e.key === 'Enter') enviarMensagem(); });

socket.on('nova-mensagem', data => {
    if (data.numero === "<?= $cliente['numero'] ?>") adicionarMensagem(data.mensagem, false);
});

window.onload = scrollBottom;
</script>
</body>
</html>