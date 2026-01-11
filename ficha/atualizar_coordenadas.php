

<?php
// Configuração do banco de dados
$host = 'br926.hostgator.com.br';
$dbname = 'fran5062_tatuagem';
$user = 'fran5062_clientes';
$pass = 'Clientes*123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta todos os clientes
    $sql = "SELECT id, endereco FROM clientes";
    $stmt = $pdo->query($sql);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($clientes as $cliente) {
        $id = $cliente['id'];
        $endereco = urlencode($cliente['endereco']);

        // Chamada à API do Nominatim
        $url = "https://nominatim.openstreetmap.org/search?q=$endereco&format=json&limit=1";
        $opts = [
            "http" => [
                "header" => "User-Agent: SeuProjeto/1.0 (seu@email.com)\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        $json = file_get_contents($url, false, $context);
        $dados = json_decode($json, true);

        if (!empty($dados)) {
            $latitude = $dados[0]['lat'];
            $longitude = $dados[0]['lon'];

            // Atualiza no banco
            $update = $pdo->prepare("UPDATE clientes SET latitude = ?, longitude = ? WHERE id = ?");
            $update->execute([$latitude, $longitude, $id]);

            echo "Cliente $id atualizado: $latitude, $longitude<br>";
        } else {
            echo "Endereço não encontrado para o cliente $id: {$cliente['endereco']}<br>";
        }

        // Pequena pausa para não sobrecarregar o Nominatim
        usleep(500000); // 0.5 segundos
    }

    echo "Atualização concluída.";

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
