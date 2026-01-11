<?php include("config/conexao.php"); ?>

<?php


// Pegar dados dos clientes
$sql = "SELECT id, nome, telefone, endereco FROM clientes";
$result = $conn->query($sql);

$clientes = [];

while ($row = $result->fetch_assoc()) {
    $clientes[] = $row;
}

// Encerrar conexão
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Mapa de Clientes</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #map { height: 600px; }
        body { font-family: Arial, sans-serif; margin: 0; }
    </style>
</head>
<body>

<h2 style="text-align:center;">Mapa de Clientes Tatuados</h2>
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

<script>
    // Inicializa o mapa
    var map = L.map('map').setView([-23.55052, -46.633308], 11); // Centro SP
    
    // Adiciona camada de mapa
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    var clientes = <?php echo json_encode($clientes); ?>;

    clientes.forEach(cliente => {
        // Usar geocodificação (Nominatim) para pegar lat/lon do endereço
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(cliente.endereco)}`)
        .then(response => response.json())
        .then(data => {
            if(data.length > 0) {
                var lat = data[0].lat;
                var lon = data[0].lon;

                var telefoneFormatado = cliente.telefone.replace(/\D/g, '');
                var linkWhatsapp = "https://wa.me/55" + telefoneFormatado;

                var popupContent = `<strong>${cliente.nome}</strong><br>
                                    <a href="${linkWhatsapp}" target="_blank">WhatsApp</a><br>
                                    <a href="detalhes_cliente.php?id=${cliente.id}" target="_blank">Ver detalhes</a>`;

                // Adiciona marcador no mapa
                L.marker([lat, lon]).addTo(map)
                    .bindPopup(popupContent);
            }
        });
    });
</script>

</body>
</html>
