<?php
$id = $_GET['id'] ?? '';

$arquivo = "data/clientes.json";

$clientes = file_exists($arquivo) ? json_decode(file_get_contents($arquivo), true) : [];
if (!is_array($clientes)) {
    $clientes = [];
}

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
<div class="flex-1 overflow-y-auto p-4 space-y-3">
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
            location.reload();
        } else {
            alert("Erro ao enviar");
        }
    });

    input.value = '';
}
</script>

<script>
window.onload = () => {
    const container = document.querySelector('.flex-1');
    container.scrollTop = container.scrollHeight;
};
</script>

</body>
</html>
