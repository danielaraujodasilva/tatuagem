<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require_once __DIR__ . '/data_store.php';

$id = $_GET['id'] ?? '';

$clientes = crmCarregarClientes();

$cliente = null;

foreach ($clientes as $c) {
    if ($c['id'] == str_replace('wa_', '', $id)) {
        $cliente = $c;
        break;
    }
}

function mensagemEnviadaPorMim($msg) {
    if (!empty($msg['fromMe'])) return true;

    $autor = strtolower($msg['de'] ?? $msg['autor'] ?? '');
    return in_array($autor, ['eu', 'me', 'atendente', 'humano', 'bot'], true);
}

if (!$cliente) {
    die("Cliente não encontrado");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-950 text-gray-100 flex flex-col h-screen">

<!-- HEADER -->
<div class="bg-gray-900 p-4 border-b border-gray-800">
    <h1 class="text-lg font-bold"><?= $cliente['nome'] ?></h1>
    <p class="text-sm text-gray-400"><?= $cliente['numero'] ?></p>
</div>

<!-- MENSAGENS -->
<div id="messages" class="flex-1 overflow-y-auto p-4 space-y-3">
<?php foreach (($cliente['mensagens'] ?? []) as $msg): ?>
    
    <?php $isMe = mensagemEnviadaPorMim($msg); ?>

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
           class="flex-1 bg-gray-800 px-4 py-2 rounded-xl">

    <button onclick="enviarMensagem()"
            class="bg-emerald-600 px-4 py-2 rounded-xl">
        Enviar
    </button>
</div>

<script>
const chatId = <?= json_encode($id) ?>;
let lastSignature = '';

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function renderMessages(mensagens) {
    const container = document.getElementById('messages');
    const shouldStickToBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 80;

    container.innerHTML = mensagens.map(msg => `
        <div class="flex ${msg.fromMe ? 'justify-end' : 'justify-start'}">
            <div class="${msg.fromMe ? 'bg-emerald-600' : 'bg-gray-800'} px-4 py-2 rounded-2xl max-w-xs">
                <p>${escapeHtml(msg.texto)}</p>
                <span class="text-xs text-gray-300 block mt-1">${escapeHtml(msg.hora)}</span>
            </div>
        </div>
    `).join('');

    if (shouldStickToBottom) {
        container.scrollTop = container.scrollHeight;
    }
}

function loadMessages(forceScroll = false) {
    fetch(`api_chat.php?id=${encodeURIComponent(chatId)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;

            const signature = JSON.stringify(data.mensagens.map(msg => [msg.texto, msg.data, msg.fromMe]));
            if (signature === lastSignature) return;

            lastSignature = signature;
            renderMessages(data.mensagens);

            if (forceScroll) {
                const container = document.getElementById('messages');
                container.scrollTop = container.scrollHeight;
            }
        });
}

function enviarMensagem() {
    const input = document.getElementById('msgInput');
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
            loadMessages(true);
        } else {
            alert("Erro ao enviar");
        }
    });

    input.value = '';
}
</script>

<script>
window.onload = () => {
    const container = document.getElementById('messages');
    container.scrollTop = container.scrollHeight;
    loadMessages(true);
    setInterval(loadMessages, 3000);
};
</script>

</body>
</html>
