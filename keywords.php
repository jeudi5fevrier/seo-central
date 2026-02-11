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

// Filtre par thematique
$filterThematic = isset($_GET['thematic']) ? (int)$_GET['thematic'] : 0;

// Recuperer les 30 meilleurs mots-cles de chaque site (par trafic)
// On utilise une sous-requete avec ROW_NUMBER pour limiter a 30 par site
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

// Trier par trafic total descendant
usort($keywordsData, function($a, $b) {
    return $b['total_traffic'] <=> $a['total_traffic'];
});

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Base de mots-cles</h1>
    <div class="page-actions">
        <span class="text-muted text-small"><?= count($keywordsData) ?> mot<?= count($keywordsData) > 1 ? 's' : '' ?>-cle<?= count($keywordsData) > 1 ? 's' : '' ?> unique<?= count($keywordsData) > 1 ? 's' : '' ?></span>
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
        <?php if (empty($keywordsData)): ?>
            <tr class="empty-row">
                <td colspan="5">Aucun mot-cle trouve.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($keywordsData as $kw): ?>
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

<script>
function filterByThematic(value) {
    const url = new URL(window.location);
    if (value === '0') {
        url.searchParams.delete('thematic');
    } else {
        url.searchParams.set('thematic', value);
    }
    window.location = url;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
