<?php
require_once __DIR__ . '/../auth/auth.php';
require_staff();
require __DIR__ . '/config/conexao.php';

$result = $conn->query('SELECT id, nome, telefone, endereco FROM clientes WHERE endereco IS NOT NULL AND endereco <> "" ORDER BY nome ASC');
$clientes = [];
while ($row = $result->fetch_assoc()) {
    $clientes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mapa de Clientes</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
</head>
<body class="ficha-body">
  <main class="ficha-shell">
    <section class="ficha-frame">
      <header class="ficha-hero">
        <span class="ficha-kicker">Mapa de clientes</span>
        <h1>Mapa de clientes</h1>
        <p>Veja onde sua base esta concentrada e abra rapidamente o WhatsApp ou os detalhes de cada cliente a partir do mapa.</p>
        <div class="ficha-nav">
          <a class="btn ficha-btn ficha-btn-secondary" href="index.php">Nova ficha</a>
          <a class="btn ficha-btn ficha-btn-secondary" href="public/clientes.php">Clientes</a>
          <a class="btn ficha-btn ficha-btn-warning" href="agenda/">Agenda</a>
        </div>
      </header>

      <div class="ficha-content">
        <div class="ficha-map-shell">
          <aside class="ficha-calendar-sidebar">
            <div class="ficha-summary">
              <h2 class="ficha-panel-title">Base geolocalizada</h2>
              <p class="ficha-copy mb-0">Os pontos sao montados a partir do endereco salvo na ficha de cada cliente.</p>
            </div>

            <div class="ficha-summary">
              <h2 class="ficha-panel-title">Resumo</h2>
              <div class="ficha-stats">
                <div class="ficha-stat">
                  <span>Clientes no mapa</span>
                  <strong><?php echo count($clientes); ?></strong>
                </div>
                <div class="ficha-stat">
                  <span>Uso sugerido</span>
                  <strong>Planejar rotas</strong>
                </div>
                <div class="ficha-stat">
                  <span>Acesso rapido</span>
                  <strong>WhatsApp e ficha</strong>
                </div>
              </div>
            </div>
          </aside>

          <section class="ficha-map-panel">
            <div id="map"></div>
          </section>
        </div>
      </div>
    </section>
  </main>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    const map = L.map('map').setView([-23.55052, -46.633308], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const clientes = <?php echo json_encode($clientes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    clientes.forEach((cliente) => {
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(cliente.endereco)}`)
        .then((response) => response.json())
        .then((data) => {
          if (!data.length) {
            return;
          }

          const telefone = String(cliente.telefone || '').replace(/\D/g, '');
          const linkWhatsapp = telefone ? `https://wa.me/55${telefone}` : '#';
          const popupContent = `
            <strong>${cliente.nome}</strong><br>
            ${telefone ? `<a href="${linkWhatsapp}" target="_blank">WhatsApp</a><br>` : ''}
            <a href="detalhes_cliente.php?id=${cliente.id}" target="_blank">Ver detalhes</a>`;

          L.marker([data[0].lat, data[0].lon]).addTo(map).bindPopup(popupContent);
        });
    });
  </script>
</body>
</html>
