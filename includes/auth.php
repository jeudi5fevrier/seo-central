<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lifetime = SESSION_LIFETIME;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetime) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
