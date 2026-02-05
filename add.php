<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/haloscan.php';

$db = getDb();
$pageTitle = 'Ajouter des sites - ' . APP_NAME;

// Recuperer les thematiques
$thematics = $db->query('SELECT id, name FROM thematics ORDER BY name')->fetchAll();

$messages = [];

// Traitement du formulaire unitaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $messages[] = ['type' => 'error', 'text' => 'Token CSRF invalide.'];
    } else {
        $haloscan = new Haloscan();

        if ($_POST['action'] === 'add_single') {
            $domain = extractRootDomain($_POST['domain'] ?? '');
            $thematicId = (int)($_POST['thematic_id'] ?? 0);

            if (empty($domain)) {
                $messages[] = ['type' => 'error', 'text' => 'Domaine invalide.'];
            } else {
                $result = addSite($db, $haloscan, $domain, $thematicId);
                $messages[] = $result;
            }
        }

        if ($_POST['action'] === 'add_bulk') {
            $lines = explode("\n", $_POST['bulk_data'] ?? '');
            $added = 0;
            $errors = [];

            foreach ($lines as $lineNum => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Format attendu : domaine.com | Thematique
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) !== 2) {
                    $errors[] = "Ligne " . ($lineNum + 1) . " : format invalide (attendu: domaine | thematique)";
                    continue;
                }

                $domain = extractRootDomain($parts[0]);
                $thematicName = $parts[1];

                if (empty($domain)) {
                    $errors[] = "Ligne " . ($lineNum + 1) . " : domaine invalide";
                    continue;
                }

                // Trouver la thematique
                $stmt = $db->prepare('SELECT id FROM thematics WHERE name = :name COLLATE NOCASE');
                $stmt->execute(['name' => $thematicName]);
                $thematic = $stmt->fetch();

                if (!$thematic) {
                    $errors[] = "Ligne " . ($lineNum + 1) . " : thematique inconnue \"" . htmlspecialchars($thematicName) . "\"";
                    continue;
                }

                $result = addSite($db, $haloscan, $domain, $thematic['id']);
                if ($result['type'] === 'success') {
                    $added++;
                } else {
                    $errors[] = "Ligne " . ($lineNum + 1) . " (" . htmlspecialchars($domain) . ") : " . $result['text'];
                }
            }

            if ($added > 0) {
                $messages[] = ['type' => 'success', 'text' => "$added site(s) ajoute(s)."];
            }
            foreach ($errors as $err) {
                $messages[] = ['type' => 'error', 'text' => $err];
            }
            if ($added === 0 && empty($errors)) {
                $messages[] = ['type' => 'error', 'text' => 'Aucune donnee a importer.'];
            }
        }
    }
}

/**
 * Ajoute un site en BDD et recupere les donnees initiales via Haloscan.
 */
function addSite(PDO $db, Haloscan $haloscan, string $domain, int $thematicId): array
{
    // Verifier que la thematique existe
    $stmt = $db->prepare('SELECT id FROM thematics WHERE id = :id');
    $stmt->execute(['id' => $thematicId]);
    if (!$stmt->fetch()) {
        return ['type' => 'error', 'text' => 'Thematique invalide.'];
    }

    // Verifier que le domaine n'existe pas deja
    $stmt = $db->prepare('SELECT id FROM sites WHERE domain = :domain');
    $stmt->execute(['domain' => $domain]);
    if ($stmt->fetch()) {
        return ['type' => 'error', 'text' => "Le domaine \"$domain\" existe deja."];
    }

    // Appel API Haloscan
    appLog('INFO', "Ajout du site $domain : appel API Haloscan");
    $apiData = $haloscan->refreshSite($domain);
    appLog('INFO', "Resultat API pour $domain", ['kw_count' => $apiData['kw_count'], 'traffic' => $apiData['traffic'], 'keywords' => count($apiData['keywords'])]);

    $now = date('c');

    // Inserer le site
    $stmt = $db->prepare('
        INSERT INTO sites (domain, thematic_id, initial_kw_count, initial_traffic, current_kw_count, current_traffic, date_added, last_refresh)
        VALUES (:domain, :thematic_id, :kw_count, :traffic, :kw_count2, :traffic2, :date_added, :last_refresh)
    ');
    $stmt->execute([
        'domain' => $domain,
        'thematic_id' => $thematicId,
        'kw_count' => $apiData['kw_count'],
        'traffic' => $apiData['traffic'],
        'kw_count2' => $apiData['kw_count'],
        'traffic2' => $apiData['traffic'],
        'date_added' => $now,
        'last_refresh' => $now,
    ]);

    $siteId = $db->lastInsertId();

    // Inserer les keywords
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

    return ['type' => 'success', 'text' => "Site \"$domain\" ajoute (" . $apiData['kw_count'] . " KW, trafic: " . round($apiData['traffic']) . ")."];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Ajouter des sites</h1>
</div>

<?php foreach ($messages as $msg): ?>
    <p class="msg msg-<?= $msg['type'] ?>"><?= $msg['text'] ?></p>
<?php endforeach; ?>

<div class="add-sections">
    <div class="add-section">
        <h2>Ajout unitaire</h2>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_single">
            <div class="form-group">
                <label for="domain">Domaine</label>
                <input type="text" id="domain" name="domain" placeholder="exemple.com" required>
            </div>
            <div class="form-group">
                <label for="thematic_id">Thematique</label>
                <select id="thematic_id" name="thematic_id" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($thematics as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Ajouter</button>
        </form>
    </div>

    <div class="add-section">
        <h2>Import en masse</h2>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_bulk">
            <div class="form-group">
                <label for="bulk_data">Un site par ligne (format : domaine | thematique)</label>
                <textarea id="bulk_data" name="bulk_data" placeholder="exemple.com | Finance / immobilier&#10;autresite.fr | Sport&#10;monsite.com | Cuisine" rows="10" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Importer</button>
        </form>
        <p class="text-muted text-small mt-8">
            Thematiques disponibles : <?= implode(', ', array_map(fn($t) => $t['name'], $thematics)) ?>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
