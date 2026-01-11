<?php
// Habilitar exibição de erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Conectar ao banco de dados
$conn = new mysqli("br926.hostgator.com.br", "fran5062_clientes", "Clientes*123", "fran5062_tatuagem");

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Consultar dados dos clientes
$sql = "SELECT nome, endereco, latitude, longitude, telefone FROM clientes WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$result = $conn->query($sql);

$clientes = [];

if ($result->num_rows > 0) {
    // Armazenar os resultados em um array
    while($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
} else {
    echo "Nenhum cliente encontrado!";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Clientes</title>

    <!-- Link para a biblioteca Leaflet (OpenStreetMap) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <style>
        #map {
            height: 600px;
            width: 100%;
        }
    </style>
</head>
<body>
    <h1>Mapa de Clientes</h1>
    <div id="map"></div>

    <script>
        var map = L.map('map').setView([-23.550520, -46.633308], 12); // Centraliza em São Paulo (ajuste conforme necessário)

        // Adiciona a camada do OpenStreetMap ao mapa
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var clientes = <?php echo json_encode($clientes); ?>;

        // Adiciona um marcador para cada cliente no mapa
        clientes.forEach(function(cliente) {
            var latLng = [cliente.latitude, cliente.longitude];

            // Exibir as coordenadas no console para debug
            console.log("Cliente: " + cliente.nome + " - Latitude: " + cliente.latitude + " - Longitude: " + cliente.longitude);

            var marker = L.marker(latLng).addTo(map)
                .bindPopup('<h3>' + cliente.nome + '</h3>' +
                           '<p><strong>Telefone: </strong><a href="https://wa.me/' + cliente.telefone.replace(/\D/g, '') + '" target="_blank">' + cliente.telefone + '</a></p>' +
                           '<p><strong>Endereço: </strong>' + cliente.endereco + '</p>');
        });
    </script>
</body>
</html>
