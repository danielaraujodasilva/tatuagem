<?php
$data = json_decode(file_get_contents(__DIR__ . "/instagram.json"), true);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Galeria</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#000; color:#fff; }

.galeria-item {
    cursor: pointer;
    overflow: hidden;
    border-radius: 15px;
}

.galeria-item img {
    width: 100%;
    transition: 0.4s;
    filter: grayscale(100%);
}

.galeria-item:hover img {
    transform: scale(1.05);
    filter: grayscale(0%);
}
</style>
</head>

<body>

<div class="container py-5">
<h2 class="text-center mb-5">Galeria</h2>

<div class="row g-4">

<?php if($data): foreach($data as $item): ?>
    <div class="col-md-4">
        <div class="galeria-item">
            <img src="<?= $item['img'] ?>">
        </div>
    </div>
<?php endforeach; endif; ?>

</div>
</div>

</body>
</html>
