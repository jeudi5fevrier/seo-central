<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$db = getDb();
$pageTitle = 'Dashboard - ' . APP_NAME;

// Recuperer les thematiques pour le filtre
$thematics = $db->query('SELECT id, name FROM thematics ORDER BY name')->fetchAll();

// Filtre par thematique
$filterThematic = isset($_GET['thematic']) ? (int)$_GET['thematic'] : 0;

// Construire la requete
$sql = 'SELECT s.*, t.name AS thematic_name FROM sites s JOIN thematics t ON s.thematic_id = t.id';
$params = [];

if ($filterThematic > 0) {
    $sql .= ' WHERE s.thematic_id = :thematic_id';
    $params['thematic_id'] = $filterThematic;
}

$sql .= ' ORDER BY s.domain ASC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$sites = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <div class="page-actions">
        <span id="refresh-status"></span>
        <button class="btn btn-sm" id="btn-refresh-all" onclick="refreshAll()">Rafraichir tout</button>
        <a href="<?= BASE_URL ?>/add.php" class="btn btn-sm btn-primary">Ajouter des sites</a>
    </div>
</div>

<div class="filters">
    <span class="filter-label">Thematique :</span>
    <select id="filter-thematic" onchange="filterByThematic(this.value)">
        <option value="0">Toutes</option>
        <?php foreach ($thematics as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $filterThematic === $t['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <span class="text-muted text-small"><?= count($sites) ?> site<?= count($sites) > 1 ? 's' : '' ?></span>
    <span class="filter-separator"></span>
    <button class="btn btn-sm" onclick="copySelectedDomains()">Copier NDD</button>
    <button class="btn btn-sm" onclick="copySelectedAll()">Copier tout</button>
</div>

<table class="data-table" id="sites-table">
    <thead>
        <tr>
            <th class="col-check"><input type="checkbox" id="check-all" onchange="toggleAllChecks(this)"></th>
            <th data-sort="string">Domaine</th>
            <th data-sort="string">Thematique</th>
            <th data-sort="number" class="num">KW</th>
            <th data-sort="number" class="num">Trafic</th>
            <th data-sort="number" class="num">Delta KW</th>
            <th data-sort="number" class="num">Delta Trafic</th>
            <th data-sort="date">Dernier refresh</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($sites)): ?>
            <tr class="empty-row">
                <td colspan="8">Aucun site. <a href="<?= BASE_URL ?>/add.php">Ajouter des sites</a></td>
            </tr>
        <?php else: ?>
            <?php foreach ($sites as $site): ?>
                <?php
                    $deltaKw = formatDelta($site['current_kw_count'], $site['initial_kw_count']);
                    $deltaTraffic = formatDelta($site['current_traffic'], $site['initial_traffic']);
                ?>
                <tr data-domain="<?= htmlspecialchars($site['domain']) ?>" data-thematic="<?= htmlspecialchars($site['thematic_name']) ?>" data-kw="<?= $site['current_kw_count'] ?>" data-traffic="<?= $site['current_traffic'] ?>">
                    <td class="col-check"><input type="checkbox" class="row-check"></td>
                    <td><a href="<?= BASE_URL ?>/site.php?id=<?= $site['id'] ?>"><?= htmlspecialchars($site['domain']) ?></a></td>
                    <td><?= htmlspecialchars($site['thematic_name']) ?></td>
                    <td class="num"><?= formatNumber($site['current_kw_count']) ?></td>
                    <td class="num"><?= formatTraffic($site['current_traffic']) ?></td>
                    <td class="num <?= $deltaKw['class'] ?>"><?= $deltaKw['value'] ?></td>
                    <td class="num <?= $deltaTraffic['class'] ?>"><?= $deltaTraffic['value'] ?></td>
                    <td><?= formatDate($site['last_refresh']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
