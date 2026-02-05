<?php
require_once __DIR__ . '/../config.php';

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbDir = dirname(DB_PATH);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $isNew = !file_exists(DB_PATH);

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        initSchema($pdo);
    }

    return $pdo;
}

function initSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS thematics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain TEXT NOT NULL UNIQUE,
            thematic_id INTEGER NOT NULL,
            initial_kw_count INTEGER DEFAULT 0,
            initial_traffic REAL DEFAULT 0,
            current_kw_count INTEGER DEFAULT 0,
            current_traffic REAL DEFAULT 0,
            date_added TEXT NOT NULL,
            last_refresh TEXT,
            FOREIGN KEY (thematic_id) REFERENCES thematics(id)
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS site_keywords (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            keyword TEXT NOT NULL,
            position INTEGER,
            volume INTEGER DEFAULT 0,
            traffic REAL DEFAULT 0,
            last_updated TEXT,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        )
    ');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_site_keywords_site ON site_keywords(site_id)');

    $thematics = [
        'Animaux',
        'Mode / Femme',
        'Sport',
        'Finance / immobilier',
        'Entreprise',
        'Sante',
        'Cuisine',
        'Maison',
        'Vehicule',
        'Generaliste',
        'Informatique',
        'Tourisme',
    ];

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO thematics (name) VALUES (:name)');
    foreach ($thematics as $name) {
        $stmt->execute(['name' => $name]);
    }
}
