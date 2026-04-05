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

</body>
</html>