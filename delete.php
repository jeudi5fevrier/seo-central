<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = getDb();
$input = json_decode(file_get_contents('php://input'), true);
$siteId = (int)($input['site_id'] ?? 0);

if ($siteId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID invalide']);
    exit;
}

// Verifier que le site existe
$stmt = $db->prepare('SELECT id, domain FROM sites WHERE id = :id');
$stmt->execute(['id' => $siteId]);
$site = $stmt->fetch();

if (!$site) {
    http_response_code(404);
    echo json_encode(['error' => 'Site non trouve']);
    exit;
}

// Supprimer les keywords (CASCADE devrait le faire, mais on s'assure)
$stmt = $db->prepare('DELETE FROM site_keywords WHERE site_id = :site_id');
$stmt->execute(['site_id' => $siteId]);

// Supprimer le site
$stmt = $db->prepare('DELETE FROM sites WHERE id = :id');
$stmt->execute(['id' => $siteId]);

echo json_encode(['status' => 'ok', 'domain' => $site['domain']]);
