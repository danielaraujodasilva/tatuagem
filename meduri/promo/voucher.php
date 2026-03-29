<?php include 'config.php'; ?>
<?php $id = $_GET['id']; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher</title>
</head>
<body>
<h1>Detalhe do Voucher <?php echo $id; ?></h1>
<a href="checkout.php?id=<?php echo $id; ?>">Comprar</a>
</body>
</html>