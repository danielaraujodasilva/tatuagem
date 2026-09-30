<?php
require dirname(__DIR__) . '/plan/includes/bootstrap.php';

session_destroy();
header('Location: index.php');
exit;
