<?php
/**
 * Endpoint pour le cron automatique.
 * Protege par un token secret dans l'URL.
 * Usage cPanel : wget -q -O /dev/null "https://mondomaine.com/seo-central/cron.php?token=SECRET"
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/haloscan.php';

// Verifier le token
$token = $_GET['token'] ?? '';
if (!hash_equals(CRON_SECRET_TOKEN, $token)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Augmenter les limites pour le cron
set_time_limit(300);

$db = getDb();
$haloscan = new Haloscan();

$sites = $db->query('SELECT id, domain FROM sites ORDER BY id')->fetchAll();
$refreshed = 0;
$errors = 0;

foreach ($sites as $site) {
    $apiData = $haloscan->refreshSite($site['domain']);
    $now = date('c');

    $stmt = $db->prepare('
        UPDATE sites SET current_kw_count = :kw_count, current_traffic = :traffic, last_refresh = :last_refresh
        WHERE id = :id
    ');
    $stmt->execute([
        'kw_count' => $apiData['kw_count'],
        'traffic' => $apiData['traffic'],
        'last_refresh' => $now,
        'id' => $site['id'],
    ]);

    // Supprimer les anciens keywords
    $stmt = $db->prepare('DELETE FROM site_keywords WHERE site_id = :site_id');
    $stmt->execute(['site_id' => $site['id']]);

    // Inserer les nouveaux
    if (!empty($apiData['keywords'])) {
        $stmt = $db->prepare('
            INSERT INTO site_keywords (site_id, keyword, position, volume, traffic, last_updated)
            VALUES (:site_id, :keyword, :position, :volume, :traffic, :last_updated)
        ');
        foreach ($apiData['keywords'] as $kw) {
            $stmt->execute([
                'site_id' => $site['id'],
                'keyword' => $kw['keyword'],
                'position' => $kw['position'],
                'volume' => $kw['volume'],
                'traffic' => $kw['traffic'],
                'last_updated' => $now,
            ]);
        }
    }

    $refreshed++;

    // Pause entre les appels pour eviter de surcharger l'API
    sleep(2);
}

echo "Cron termine : $refreshed site(s) rafraichi(s).";
