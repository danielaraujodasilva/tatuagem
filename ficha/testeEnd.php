<?php
function obterLatitudeLongitude($endereco) {
    $url = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($endereco) . '&format=json&limit=1';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "TesteGeocodeApp");
    $resposta = curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($resposta, true);
    if (isset($dados[0])) {
        return [
            'latitude' => $dados[0]['lat'],
            'longitude' => $dados[0]['lon']
        ];
    } else {
        return ['latitude' => null, 'longitude' => null];
    }
}

$endereco = 'Rua Rio Jutaí, 83, Parque Jurema, Guarulhos';
$localizacao = obterLatitudeLongitude($endereco);
print_r($localizacao);
?>
