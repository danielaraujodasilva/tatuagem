<?php
$id = $_GET['id'] ?? '';

$arquivo = "data/clientes.json";
$clientes = file_exists($arquivo) ? json_decode(file_get_contents($arquivo), true) : [];

$cliente = null;
foreach ($clientes as $c) {
    if ($c['id'] == str_replace('wa_', '', $id)) {
        $cliente = $c;
        break;
    }
}

if (!$cliente) {
    die("Cliente não encontrado");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat - <?= htmlspecialchars($cliente['nome']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.socket.io/4.7.1/socket.io.min.js"></script>
</head>

<body class="bg-gray-950 text-gray-100 flex flex-col h-screen">

<!-- HEADER -->
<div class="bg-gray-900 p-4 border-b border-gray-800">
    <h1 class="text-lg font-bold"><?= htmlspecialchars($cliente['nome']) ?></h1>
    <p class="text-sm text-gray-400"><?= $cliente['numero'] ?></p>
</div>

<!-- MENSAGENS -->
<div id="mensagensContainer" class="flex-1 overflow-y-auto p-4 space-y-3">
<?php foreach ($cliente['mensagens'] as $msg): ?>
    <?php $isMe = $msg['fromMe'] ?? false; ?>
    <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?>">
        <div class="<?= $isMe ? 'bg-emerald-600' : 'bg-gray-800' ?> px-4 py-2 rounded-2xl max-w-xs">
            <p><?= htmlspecialchars($msg['texto']) ?></p>
            <span class="text-xs text-gray-300 block mt-1">
                <?= date('H:i', strtotime($msg['data'])) ?>
            </span>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- INPUT -->
<div class="p-4 border-t border-gray-800 flex gap-3">
    <input id="msgInput" type="text" placeholder="Digite sua mensagem..."
           class="flex-1 bg-gray-800 px-4 py-2 rounded-xl focus:outline-none">
    <button id="enviarBtn" 
            class="bg-emerald-600 hover:bg-emerald-700 px-6 py-2 rounded-xl transition-colors">
        Enviar
    </button>
</div>

<script>
// ==================== SOCKET.IO ====================
const socket = io("http://127.0.0.1:3001", {
    transports: ['polling', 'websocket'],
    reconnection: true,
    reconnectionAttempts: 15,
    reconnectionDelay: 1000,
    timeout: 30000
});

socket.on('connect', () => {
    console.log('✅ Socket.IO conectado com sucesso!');
});

socket.on('connect_error', (err) => {
    console.error('❌ Socket.IO erro de conexão:', err.message);
});

socket.on('disconnect', (reason) => {
    console.warn('⚠️ Socket.IO desconectado:', reason);
});

// ==================== FUNÇÕES ====================
const container = document.getElementById('mensagensContainer');
const input = document.getElementById('msgInput');
const enviarBtn = document.getElementById('enviarBtn');

function scrollBottom() {
    container.scrollTop = container.scrollHeight;
}

function adicionarMensagem(texto, fromMe) {
    const divFlex = document.createElement('div');
    divFlex.className = 'flex ' + (fromMe ? 'justify-end' : 'justify-start');

    const msgDiv = document.createElement('div');
    msgDiv.className = (fromMe ? 'bg-emerald-600' : 'bg-gray-800') + ' px-4 py-2 rounded-2xl max-w-xs';

    const p = document.createElement('p');
    p.textContent = texto;

    const span = document.createElement('span');
    span.className = 'text-xs text-gray-300 block mt-1';
    const agora = new Date();
    span.textContent = agora.getHours().toString().padStart(2, '0') + ':' + 
                       agora.getMinutes().toString().padStart(2, '0');

    msgDiv.appendChild(p);
    msgDiv.appendChild(span);
    divFlex.appendChild(msgDiv);
    container.appendChild(divFlex);
    scrollBottom();
}

// ==================== ENVIAR MENSAGEM ====================
function enviarMensagem() {
    const texto = input.value.trim();
    if (!texto) return;

    fetch('enviar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            numero: "<?= $cliente['numero'] ?>",
            mensagem: texto
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            adicionarMensagem(texto, true);
        } else {
            alert("Erro ao enviar: " + (res.erro || ''));
        }
    })
    .catch(err => console.error("Erro ao enviar mensagem:", err));

    input.value = '';
}

enviarBtn.addEventListener('click', enviarMensagem);
input.addEventListener('keydown', e => {
    if (e.key === 'Enter') enviarMensagem();
});

// ==================== RECEBER MENSAGENS ====================
socket.on('nova-mensagem', data => {
    if (data.numero === "<?= $cliente['numero'] ?>") {
        adicionarMensagem(data.mensagem, false);
    }
});

socket.on('mensagem-enviada', data => {
    // Já adicionamos localmente ao enviar
});

// Scroll inicial
window.onload = scrollBottom;
</script>

</body>
</html>