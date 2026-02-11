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
    } else {
        migrateSchema($pdo);
    }

    return $pdo;
}

function migrateSchema(PDO $pdo): void
{
    // Migration: ajouter la colonne slug si elle n'existe pas
    $cols = $pdo->query("PRAGMA table_info(thematics)")->fetchAll();
    $hasSlug = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'slug') {
            $hasSlug = true;
            break;
        }
    }

    if (!$hasSlug) {
        $pdo->exec('ALTER TABLE thematics ADD COLUMN slug TEXT');

        // Mapping name -> slug
        $slugMap = [
            'Animaux' => 'fr-animaux',
            'Cuisine' => 'fr-cuisine',
            'Entreprise' => 'fr-entreprise',
            'Finance / immobilier' => 'fr-finance-immobilier',
            'Generaliste' => 'fr-generaliste',
            'Informatique' => 'fr-informatique',
            'Maison' => 'fr-maison',
            'Mode / Femme' => 'fr-mode-femme',
            'Sante' => 'fr-sante',
            'Sport' => 'fr-sport',
            'Tourisme' => 'fr-tourisme',
            'Vehicule' => 'fr-vehicule',
        ];

        $stmt = $pdo->prepare('UPDATE thematics SET slug = :slug WHERE name = :name');
        foreach ($slugMap as $name => $slug) {
            $stmt->execute(['name' => $name, 'slug' => $slug]);
        }
    }
}

function initSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS thematics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE
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
        ['name' => 'Animaux', 'slug' => 'fr-animaux'],
        ['name' => 'Cuisine', 'slug' => 'fr-cuisine'],
        ['name' => 'Entreprise', 'slug' => 'fr-entreprise'],
        ['name' => 'Finance / immobilier', 'slug' => 'fr-finance-immobilier'],
        ['name' => 'Generaliste', 'slug' => 'fr-generaliste'],
        ['name' => 'Informatique', 'slug' => 'fr-informatique'],
        ['name' => 'Maison', 'slug' => 'fr-maison'],
        ['name' => 'Mode / Femme', 'slug' => 'fr-mode-femme'],
        ['name' => 'Sante', 'slug' => 'fr-sante'],
        ['name' => 'Sport', 'slug' => 'fr-sport'],
        ['name' => 'Tourisme', 'slug' => 'fr-tourisme'],
        ['name' => 'Vehicule', 'slug' => 'fr-vehicule'],
    ];

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO thematics (name, slug) VALUES (:name, :slug)');
    foreach ($thematics as $t) {
        $stmt->execute(['name' => $t['name'], 'slug' => $t['slug']]);
    }
}
