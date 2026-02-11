<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$db = getDb();
$pageTitle = 'Mots-cles - ' . APP_NAME;

// Recuperer les thematiques pour le filtre
$thematics = $db->query('SELECT id, name FROM thematics ORDER BY name')->fetchAll();
$thematicsById = [];
foreach ($thematics as $t) {
    $thematicsById[$t['id']] = $t['name'];
}

// Filtres
$filterThematic = isset($_GET['thematic']) ? (int)$_GET['thematic'] : 0;
$filterVolumeMin = isset($_GET['vol_min']) && $_GET['vol_min'] !== '' ? (int)$_GET['vol_min'] : null;
$filterVolumeMax = isset($_GET['vol_max']) && $_GET['vol_max'] !== '' ? (int)$_GET['vol_max'] : null;
$filterPosMin = isset($_GET['pos_min']) && $_GET['pos_min'] !== '' ? (int)$_GET['pos_min'] : null;
$filterPosMax = isset($_GET['pos_max']) && $_GET['pos_max'] !== '' ? (int)$_GET['pos_max'] : null;

// Pagination
$perPageOptions = [20, 50, 100, 500, 1000];
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($perPage, $perPageOptions)) {
    $perPage = 20;
}
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Recuperer les 30 meilleurs mots-cles de chaque site (par trafic)
$sql = '
    SELECT
        sk.keyword,
        sk.position,
        sk.volume,
        sk.traffic,
        s.id AS site_id,
        s.thematic_id
    FROM site_keywords sk
    JOIN sites s ON sk.site_id = s.id
    WHERE sk.id IN (
        SELECT sk2.id FROM site_keywords sk2
        WHERE sk2.site_id = sk.site_id
        ORDER BY sk2.traffic DESC
        LIMIT 30
    )
';

$params = [];
if ($filterThematic > 0) {
    $sql .= ' AND s.thematic_id = :thematic_id';
    $params['thematic_id'] = $filterThematic;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$allKeywords = $stmt->fetchAll();

// Grouper les mots-cles : pour chaque KW unique, on garde la meilleure position et les thematiques
$keywordsData = [];
foreach ($allKeywords as $row) {
    $kw = mb_strtolower($row['keyword']);

    if (!isset($keywordsData[$kw])) {
        $keywordsData[$kw] = [
            'keyword' => $row['keyword'],
            'top_position' => $row['position'],
            'volume' => $row['volume'],
            'total_traffic' => $row['traffic'],
            'thematics' => [],
        ];
    } else {
        // Meilleure position (plus petite = meilleure)
        if ($row['position'] < $keywordsData[$kw]['top_position']) {
            $keywordsData[$kw]['top_position'] = $row['position'];
        }
        // Additionner le trafic
        $keywordsData[$kw]['total_traffic'] += $row['traffic'];
        // Prendre le volume le plus eleve
        if ($row['volume'] > $keywordsData[$kw]['volume']) {
            $keywordsData[$kw]['volume'] = $row['volume'];
        }
    }

    // Ajouter la thematique si pas deja presente
    $thematicId = $row['thematic_id'];
    if (!in_array($thematicId, $keywordsData[$kw]['thematics'])) {
        $keywordsData[$kw]['thematics'][] = $thematicId;
    }
}

// Appliquer les filtres volume et position
$keywordsData = array_filter($keywordsData, function($kw) use ($filterVolumeMin, $filterVolumeMax, $filterPosMin, $filterPosMax) {
    if ($filterVolumeMin !== null && $kw['volume'] < $filterVolumeMin) return false;
    if ($filterVolumeMax !== null && $kw['volume'] > $filterVolumeMax) return false;
    if ($filterPosMin !== null && $kw['top_position'] < $filterPosMin) return false;
    if ($filterPosMax !== null && $kw['top_position'] > $filterPosMax) return false;
    return true;
});

// Trier par trafic total descendant
usort($keywordsData, function($a, $b) {
    return $b['total_traffic'] <=> $a['total_traffic'];
});

// Pagination
$totalKeywords = count($keywordsData);
$totalPages = max(1, ceil($totalKeywords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$keywordsPage = array_slice($keywordsData, $offset, $perPage);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Base de mots-cles</h1>
    <div class="page-actions">
        <span class="text-muted text-small"><?= $totalKeywords ?> mot<?= $totalKeywords > 1 ? 's' : '' ?>-cle<?= $totalKeywords > 1 ? 's' : '' ?> unique<?= $totalKeywords > 1 ? 's' : '' ?></span>
    </div>
</div>

<div class="filters">
    <form method="GET" class="filters-inline">
        <select name="thematic" onchange="this.form.submit()">
            <option value="0">Thematique</option>
            <?php foreach ($thematics as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $filterThematic === $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="vol_min" onchange="this.form.submit()">
            <option value="">Volume min</option>
            <option value="100" <?= $filterVolumeMin === 100 ? 'selected' : '' ?>>100+</option>
            <option value="500" <?= $filterVolumeMin === 500 ? 'selected' : '' ?>>500+</option>
            <option value="1000" <?= $filterVolumeMin === 1000 ? 'selected' : '' ?>>1 000+</option>
            <option value="5000" <?= $filterVolumeMin === 5000 ? 'selected' : '' ?>>5 000+</option>
            <option value="10000" <?= $filterVolumeMin === 10000 ? 'selected' : '' ?>>10 000+</option>
        </select>

        <select name="pos_max" onchange="this.form.submit()">
            <option value="">Position max</option>
            <option value="3" <?= $filterPosMax === 3 ? 'selected' : '' ?>>Top 3</option>
            <option value="10" <?= $filterPosMax === 10 ? 'selected' : '' ?>>Top 10</option>
            <option value="20" <?= $filterPosMax === 20 ? 'selected' : '' ?>>Top 20</option>
            <option value="50" <?= $filterPosMax === 50 ? 'selected' : '' ?>>Top 50</option>
            <option value="100" <?= $filterPosMax === 100 ? 'selected' : '' ?>>Top 100</option>
        </select>

        <select name="per_page" onchange="this.form.submit()">
            <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?>/page</option>
            <?php endforeach; ?>
        </select>

        <?php if ($filterThematic || $filterVolumeMin || $filterPosMax): ?>
            <a href="<?= BASE_URL ?>/keywords.php" class="btn btn-sm">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($totalPages > 1): ?>
        <span class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-sm">&laquo;</a>
            <?php endif; ?>
            <span class="page-info"><?= $page ?>/<?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-sm">&raquo;</a>
            <?php endif; ?>
        </span>
    <?php endif; ?>
</div>

<table class="data-table" id="keywords-table">
    <thead>
        <tr>
            <th data-sort="string">Mot-cle</th>
            <th data-sort="number" class="num">Volume</th>
            <th data-sort="number" class="num">Top Position</th>
            <th data-sort="number" class="num">Trafic cumule</th>
            <th>Thematiques</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($keywordsPage)): ?>
            <tr class="empty-row">
                <td colspan="5">Aucun mot-cle trouve.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($keywordsPage as $kw): ?>
                <tr>
                    <td><?= htmlspecialchars($kw['keyword']) ?></td>
                    <td class="num"><?= formatNumber($kw['volume']) ?></td>
                    <td class="num"><?= $kw['top_position'] ?></td>
                    <td class="num"><?= formatTraffic($kw['total_traffic']) ?></td>
                    <td class="thematics-cell">
                        <?php foreach ($kw['thematics'] as $thematicId): ?>
                            <span class="thematic-tag"><?= htmlspecialchars($thematicsById[$thematicId] ?? 'Inconnu') ?></span>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
<div class="pagination-bottom">
    <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-sm">&laquo; Precedent</a>
    <?php endif; ?>
    <span class="page-info">Page <?= $page ?> / <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-sm">Suivant &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
