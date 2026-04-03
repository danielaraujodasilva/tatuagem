<?php

$username = "meduritattoo";
$maxPosts = 9;

$url = "https://www.instagram.com/meduritattoo/";
$html = @file_get_contents($url);

if(!$html){
    die("Erro ao acessar Instagram");
}

preg_match('/window\._sharedData = (.*);<\/script>/', $html, $matches);

if (!isset($matches[1])) {
    die("Erro ao pegar dados do Instagram");
}

$data = json_decode($matches[1], true);

$posts = $data['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'];

$result = [];
$count = 0;

foreach ($posts as $post) {

    if ($count >= $maxPosts) break;

    $img = $post['node']['display_url'];
    $id = $post['node']['id'];

    $imgData = file_get_contents($img);

    $fileName = "imagens/$id.jpg";

    file_put_contents(__DIR__ . "/" . $fileName, $imgData);

    $image = @imagecreatefromjpeg(__DIR__ . "/" . $fileName);
    if($image){
        imagejpeg($image, __DIR__ . "/" . $fileName, 75);
        imagedestroy($image);
    }

    $result[] = [
        "img" => "imagens/$id.jpg"
    ];

    $count++;
}

file_put_contents(__DIR__ . "/instagram.json", json_encode($result));

echo "Atualizado com sucesso!";
