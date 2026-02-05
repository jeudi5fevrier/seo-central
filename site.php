<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$db = getDb();

$siteId = (int)($_GET['id'] ?? 0);
if ($siteId <= 0) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Recuperer le site
$stmt = $db->prepare('SELECT s.*, t.name AS thematic_name FROM sites s JOIN thematics t ON s.thematic_id = t.id WHERE s.id = :id');
$stmt->execute(['id' => $siteId]);
$site = $stmt->fetch();

if (!$site) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pageTitle = $site['domain'] . ' - ' . APP_NAME;

// Recuperer les keywords du site
$stmt = $db->prepare('SELECT * FROM site_keywords WHERE site_id = :site_id ORDER BY traffic DESC');
$stmt->execute(['site_id' => $siteId]);
$keywords = $stmt->fetchAll();

$deltaKw = formatDelta($site['current_kw_count'], $site['initial_kw_count']);
$deltaTraffic = formatDelta($site['current_traffic'], $site['initial_traffic']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="site-header">
            <h1><?= htmlspecialchars($site['domain']) ?></h1>
            <div class="site-meta">
                <span><?= htmlspecialchars($site['thematic_name']) ?></span>
                <span>Ajout : <?= formatDate($site['date_added']) ?></span>
                <span>Dernier refresh : <?= formatDate($site['last_refresh']) ?></span>
            </div>
        </div>
    </div>
    <div class="page-actions">
        <span id="refresh-status"></span>
        <button class="btn btn-sm" id="btn-refresh-site" onclick="refreshSite(<?= $site['id'] ?>)">Rafraichir</button>
        <button class="btn btn-sm btn-danger" onclick="deleteSite(<?= $site['id'] ?>, '<?= htmlspecialchars($site['domain'], ENT_QUOTES) ?>')">Supprimer</button>
    </div>
</div>

<div class="site-stats">
    <div class="stat-box">
        <div class="stat-label">Mots-cles</div>
        <div class="stat-value"><?= formatNumber($site['current_kw_count']) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Trafic estime</div>
        <div class="stat-value"><?= formatTraffic($site['current_traffic']) ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Delta KW</div>
        <div class="stat-value <?= $deltaKw['class'] ?>"><?= $deltaKw['value'] ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Delta Trafic</div>
        <div class="stat-value <?= $deltaTraffic['class'] ?>"><?= $deltaTraffic['value'] ?></div>
    </div>
</div>

<table class="data-table" id="kw-table">
    <thead>
        <tr>
            <th data-sort="string">Mot-cle</th>
            <th data-sort="number" class="num">Position</th>
            <th data-sort="number" class="num">Volume</th>
            <th data-sort="number" class="num">Trafic</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($keywords)): ?>
            <tr class="empty-row">
                <td colspan="4">Aucun mot-cle. Lancez un refresh pour recuperer les donnees.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($keywords as $kw): ?>
                <tr>
                    <td><?= htmlspecialchars($kw['keyword']) ?></td>
                    <td class="num"><?= $kw['position'] ?></td>
                    <td class="num"><?= formatNumber($kw['volume']) ?></td>
                    <td class="num"><?= formatTraffic($kw['traffic']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
