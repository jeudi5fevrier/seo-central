<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Logs - ' . APP_NAME;

$logFile = __DIR__ . '/data/app.log';
$logContent = '';
$lineCount = 0;

// Vider les logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    header('Location: ' . BASE_URL . '/logs.php');
    exit;
}

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $lineCount = count($lines);
    // Afficher les 200 dernieres lignes, les plus recentes en premier
    $lines = array_slice($lines, -200);
    $lines = array_reverse($lines);
    $logContent = $lines;
} else {
    $logContent = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Logs (<?= $lineCount ?> lignes)</h1>
    <div class="page-actions">
        <form method="POST" action="" style="display:inline">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Vider tous les logs ?')">Vider les logs</button>
        </form>
    </div>
</div>

<div class="log-container">
    <?php if (empty($logContent)): ?>
        <p class="text-muted">Aucun log.</p>
    <?php else: ?>
        <?php foreach ($logContent as $line): ?>
            <?php
                $class = 'log-line';
                if (str_contains($line, '[ERROR]')) $class .= ' log-error';
                elseif (str_contains($line, '[API]')) $class .= ' log-api';
            ?>
            <div class="<?= $class ?>"><?= htmlspecialchars($line) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
