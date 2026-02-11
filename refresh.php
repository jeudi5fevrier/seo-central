<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/haloscan.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = getDb();
$haloscan = new Haloscan();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$siteId = (int)($input['site_id'] ?? 0);

$results = [];

if ($action === 'refresh_all') {
    $sites = $db->query('SELECT id, domain FROM sites ORDER BY id')->fetchAll();
    foreach ($sites as $site) {
        $result = refreshOneSite($db, $haloscan, $site['id'], $site['domain']);
        $results[] = $result;
    }
    echo json_encode(['status' => 'ok', 'refreshed' => count($results), 'results' => $results]);
    exit;
}

if ($action === 'refresh_selected') {
    $siteIds = $input['site_ids'] ?? [];
    if (empty($siteIds) || !is_array($siteIds)) {
        http_response_code(400);
        echo json_encode(['error' => 'Aucun site selectionne']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
    $stmt = $db->prepare("SELECT id, domain FROM sites WHERE id IN ($placeholders) ORDER BY id");
    $stmt->execute($siteIds);
    $sites = $stmt->fetchAll();

    foreach ($sites as $site) {
        $result = refreshOneSite($db, $haloscan, $site['id'], $site['domain']);
        $results[] = $result;
    }
    echo json_encode(['status' => 'ok', 'refreshed' => count($results), 'results' => $results]);
    exit;
}

if ($action === 'refresh_site' && $siteId > 0) {
    $stmt = $db->prepare('SELECT id, domain FROM sites WHERE id = :id');
    $stmt->execute(['id' => $siteId]);
    $site = $stmt->fetch();

    if (!$site) {
        http_response_code(404);
        echo json_encode(['error' => 'Site non trouve']);
        exit;
    }

    $result = refreshOneSite($db, $haloscan, $site['id'], $site['domain']);
    echo json_encode(['status' => 'ok', 'result' => $result]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action invalide']);
exit;

/**
 * Rafraichit les donnees d'un site via l'API Haloscan.
 */
function refreshOneSite(PDO $db, Haloscan $haloscan, int $siteId, string $domain): array
{
    $apiData = $haloscan->refreshSite($domain);
    $now = date('c');

    // Mettre a jour le site
    $stmt = $db->prepare('
        UPDATE sites SET current_kw_count = :kw_count, current_traffic = :traffic, last_refresh = :last_refresh
        WHERE id = :id
    ');
    $stmt->execute([
        'kw_count' => $apiData['kw_count'],
        'traffic' => $apiData['traffic'],
        'last_refresh' => $now,
        'id' => $siteId,
    ]);

    // Supprimer les anciens keywords
    $stmt = $db->prepare('DELETE FROM site_keywords WHERE site_id = :site_id');
    $stmt->execute(['site_id' => $siteId]);

    // Inserer les nouveaux
    if (!empty($apiData['keywords'])) {
        $stmt = $db->prepare('
            INSERT INTO site_keywords (site_id, keyword, position, volume, traffic, last_updated)
            VALUES (:site_id, :keyword, :position, :volume, :traffic, :last_updated)
        ');
        foreach ($apiData['keywords'] as $kw) {
            $stmt->execute([
                'site_id' => $siteId,
                'keyword' => $kw['keyword'],
                'position' => $kw['position'],
                'volume' => $kw['volume'],
                'traffic' => $kw['traffic'],
                'last_updated' => $now,
            ]);
        }
    }

    return [
        'site_id' => $siteId,
        'domain' => $domain,
        'kw_count' => $apiData['kw_count'],
        'traffic' => $apiData['traffic'],
    ];
}
