<?php
/**
 * SEO Central - Configuration
 * Copier ce fichier vers config.php et remplir les valeurs
 */

// --- Authentification ---
define('AUTH_USERNAME', 'admin');
define('AUTH_PASSWORD', 'votre_mot_de_passe'); // A CHANGER
define('SESSION_LIFETIME', 86400); // 24h en secondes

// --- API Haloscan ---
define('HALOSCAN_API_KEY', 'votre_cle_api_haloscan');
define('HALOSCAN_BASE_URL', 'https://api.haloscan.com/api');

// --- Cron ---
define('CRON_SECRET_TOKEN', 'CHANGE_THIS_TO_A_RANDOM_STRING');

// --- Base de donnees ---
define('DB_PATH', __DIR__ . '/data/seo-central.db');

// --- Application ---
define('APP_NAME', 'SEO Central');
define('BASE_URL', '/seo-central'); // Ajuster selon le chemin sur le serveur
