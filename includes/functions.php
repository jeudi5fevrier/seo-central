<?php

/**
 * Ecrit une ligne dans le fichier de log.
 * Niveaux : INFO, ERROR, API
 */
function appLog(string $level, string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../data';
    $logFile = $logDir . '/app.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $line = date('Y-m-d H:i:s') . ' [' . $level . '] ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Extrait le root domain d'une URL ou d'un domaine saisi.
 * Supprime protocole, www, chemin, etc.
 */
function extractRootDomain(string $input): string
{
    $input = trim($input);
    // Ajouter un schema si absent pour que parse_url fonctionne
    if (!preg_match('#^https?://#i', $input)) {
        $input = 'http://' . $input;
    }
    $host = parse_url($input, PHP_URL_HOST);
    if (!$host) {
        return '';
    }
    // Supprimer www.
    $host = preg_replace('/^www\./i', '', $host);
    return strtolower($host);
}

/**
 * Formate un nombre avec separateur de milliers.
 */
function formatNumber($number): string
{
    if ($number === null) {
        return '0';
    }
    return number_format((float)$number, 0, ',', ' ');
}

/**
 * Formate un nombre decimal (trafic).
 */
function formatTraffic($number): string
{
    if ($number === null) {
        return '0';
    }
    $n = (float)$number;
    if ($n >= 1000) {
        return number_format($n, 0, ',', ' ');
    }
    return number_format($n, 1, ',', ' ');
}

/**
 * Formate un delta avec signe et classe CSS.
 * Retourne un tableau ['value' => string, 'class' => string].
 */
function formatDelta($current, $initial): array
{
    $diff = (float)$current - (float)$initial;
    if ($diff > 0) {
        return ['value' => '+' . formatNumber($diff), 'class' => 'delta-positive'];
    } elseif ($diff < 0) {
        return ['value' => formatNumber($diff), 'class' => 'delta-negative'];
    }
    return ['value' => '0', 'class' => 'delta-neutral'];
}

/**
 * Formate une date ISO en format lisible.
 */
function formatDate(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '-';
    }
    return date('d/m/Y H:i', $ts);
}

/**
 * Genere un token CSRF et le stocke en session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifie un token CSRF.
 */
function verifyCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Retourne un champ hidden CSRF pour les formulaires.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}
