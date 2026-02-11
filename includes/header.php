<?php
require_once __DIR__ . '/functions.php';
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=2">
    <script>var BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
    <header class="main-header">
        <div class="header-inner">
            <span class="app-name"><?= htmlspecialchars(APP_NAME) ?></span>
            <nav class="main-nav">
                <a href="<?= BASE_URL ?>/dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= BASE_URL ?>/add.php" class="<?= $currentPage === 'add' ? 'active' : '' ?>">Ajouter</a>
                <a href="<?= BASE_URL ?>/keywords.php" class="<?= $currentPage === 'keywords' ? 'active' : '' ?>">Mots-cles</a>
                <a href="<?= BASE_URL ?>/logs.php" class="<?= $currentPage === 'logs' ? 'active' : '' ?>">Logs</a>
                <a href="<?= BASE_URL ?>/logout.php" class="nav-logout">Deconnexion</a>
            </nav>
        </div>
    </header>
    <main class="main-content">
