<?php
require __DIR__ . '/auth.php';

$_SESSION = [];
session_destroy();
auth_redirect('/auth/login.php');
