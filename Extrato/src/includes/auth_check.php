<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || !isset($_SESSION['username']) || empty($_SESSION['username'])) {
    session_destroy();
    header("Location: src/auth/login.php");
    exit();
}

$inactivity_timeout = 3600; // 1 hora
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactivity_timeout) {
    session_destroy();
    header("Location: src/auth/login.php");
    exit();
}

$_SESSION['last_activity'] = time();
?>