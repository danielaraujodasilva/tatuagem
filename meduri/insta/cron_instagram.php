<?php

$username = "meduritattoo";
$maxPosts = 9;

// cria contexto com user-agent (fundamental)
$options = [
    "http" => [
        "header" => "User-Agent: Mozilla/5.0"
    ]
];

$context = stream_context_create($options);

$url = "https://www.instagram.com/$username/";
$html = file_get_contents($url, false, $context);

if (!$html) {
    die("Erro ao acessar Instagram");
}

// pega JSON moderno
preg_match('/<script type="application\/json" data-sjs>(.*?)<\/script>/', $html, $matches);

if (!isset($matches[1])) {
    die("Instagram mudou tudo de novo (sim, já começou...)");
}

$data = json_decode($matches[1], true);

// tenta encontrar imagens
$result = [];
$count = 0;

function findImages($array, &$result, &$count, $maxPosts) {
    foreach ($array as $key => $value) {

        if ($count >= $maxPosts) return;

        if ($key === "display_url" && is_string($value)) {
            $id = md5($value);
            $fileName = "imagens/$id.jpg";

            $imgData = @file_get_contents($value);

            if ($imgData) {
                file_put_contents(__DIR__ . "/" . $fileName, $imgData);

                $image = @imagecreatefromjpeg(__DIR__ . "/" . $fileName);
                if ($image) {
                    imagejpeg($image, __DIR__ . "/" . $fileName, 75);
                    imagedestroy($image);
                }

                $result[] = ["img" => $fileName];
                $count++;
            }
        }

        if (is_array($value)) {
            findImages($value, $result, $count, $maxPosts);
        }
    }
}

findImages($data, $result, $count, $maxPosts);

if (empty($result)) {
    die("Não achou nenhuma imagem. Instagram tá te bloqueando bonito.");
}

file_put_contents(__DIR__ . "/instagram.json", json_encode($result));

echo "Atualizado com sucesso!";