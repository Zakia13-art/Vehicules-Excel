<?php
/**
 * sync_manager.php
 * Gestion des synchronisations Wialon
 */

require_once 'db.php';

// Vérifier le log de synchronisation
$sync_log_file = 'logs/sync_wialon.log';
$last_sync = 'Jamais';
$last_status = 'Inconnu';
$sync_history = [];

if (file_exists($sync_log_file)) {
    $logs = file($sync_log_file, FILE_IGNORE_NEW_LINES);
    
    // Récupérer les 10 dernières lignes
    $recent_logs = array_slice($logs, -10);
    
    // Trouver la dernière synchronisation
    foreach (array_reverse($logs) as $line) {
        if (strpos($line, 'SYNCHRONISATION') !== false) {
            $last_sync = substr($line, 1, 19); // Extraire le timestamp
            break;
        }
    }
    
    $sync_history = $recent_logs;
}

// Bouton "Synchroniser maintenant"
$sync_triggered = false;
if (isset($_POST['sync_now'])) {
    // Exécuter le script de synchronisation en background
    if (PHP_OS_FAMILY === 'Windows') {
        pclose(popen("start /B php sync_wialon_cron.php", "r"));
    } else {
        exec("php sync_wialon_cron.php > /dev/null 2>&1 &");
    }
    $sync_triggered = true;
}

// Statistiques BDD
try {
    $db = Cnx();
    
    $totalResult = $db->query("SELECT COUNT(*) as total FROM trajets");
    $total_trajets = $totalResult->fetch(PDO::FETCH_ASSOC)['total'];
    
    $dateResult = $db->query("SELECT MAX(debut) as latest FROM trajets");
    $latest_date = $dateResult->fetch(PDO::FETCH_ASSOC)['latest'];
    
} catch (Exception $e) {
    $total_trajets = 0;
    $latest_date = 'Erreur';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire de synchronisation Wialon</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: #fff; border-radius: 12px; padding: 32px; margin-bottom: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }
        .card { background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.07); margin-bottom: 24px; }
        .card h2 { font-size: 1.3rem; color: #0f172a; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 16px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat { background: #f8fafc; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; }
        .stat-label { font-size: 0.875rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 8px; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: #0f172a; }
        .actions { display: flex; gap: 12px; margin-bottom: 24px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-primary:hover { background: #1e293b; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .btn-secondary:hover { background: #cbd5e1; }
        .log-container { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.85rem; max-height: 400px; overflow-y: auto; line-height: 1.6; }
        .log-line { padding: 4px 0; border-bottom: 1px solid #334155; }
        .log-success { color: #22c55e; }
        .log-error { color: #ef4444; }
        .log-warning { color: #f97316; }
        .log-info { color: #3b82f6; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; border-left: 4px solid #16a34a; color: #166534; }
        .alert-info { background: #dbeafe; border-left: 4px solid #3b82f6; color: #1d4ed8; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>📡 Gestionnaire de Synchronisation Wialon</h1>
        <p>Gérez les importations automatiques depuis Wialon</p>
    </div>

    <?php if ($sync_triggered): ?>
    <div class="alert alert-success">
        ✅ Synchronisation lancée! Les données seront mises à jour dans quelques secondes.
    </div>
    <?php endif; ?>

    <!-- STATISTIQUES -->
    <div class="card">
        <h2>📊 Statistiques</h2>
        <div class="stats">
            <div class="stat">
                <div class="stat-label">📍 Trajets en BDD</div>
                <div class="stat-value"><?= $total_trajets ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">📅 Dernière date</div>
                <div class="stat-value" style="font-size: 1rem;"><?= $latest_date ?? 'N/A' ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">⏱️ Dernière sync</div>
                <div class="stat-value" style="font-size: 0.95rem;"><?= $last_sync ?></div>
            </div>
        </div>
    </div>

    <!-- ACTIONS -->
    <div class="card">
        <h2>⚙️ Actions</h2>
        <div class="actions">
            <form method="POST">
                <button type="submit" name="sync_now" class="btn btn-success">🔄 Synchroniser maintenant</button>
            </form>
            <a href="check_trajets.php" class="btn btn-primary">✅ Vérifier données</a>
            <a href="rapports_wialon.php" class="btn btn-secondary">📊 Voir rapports</a>
        </div>

        <div class="alert alert-info" style="margin-top: 16px;">
            <strong>💡 Configuration Cron:</strong>
            <br>Pour automatiser la synchronisation, ajoutez cette ligne à votre crontab:
            <br><code>*/30 * * * * /usr/bin/php /chemin/vers/sync_wialon_cron.php</code>
            <br>(Remplacez le chemin et changez */30 pour votre fréquence souhaitée)
        </div>
    </div>

    <!-- LOG -->
    <div class="card">
        <h2>📝 Journal de synchronisation</h2>
        
        <?php if (!empty($sync_history)): ?>
        <div class="log-container">
            <?php foreach ($sync_history as $line): 
                $class = 'log-info';
                if (strpos($line, '[ERROR]') !== false) $class = 'log-error';
                elseif (strpos($line, '[WARNING]') !== false) $class = 'log-warning';
                elseif (strpos($line, '✅') !== false) $class = 'log-success';
            ?>
            <div class="log-line <?= $class ?>">
                <?= htmlspecialchars($line) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: #94a3b8;">Aucun log de synchronisation trouvé.</p>
        <?php endif; ?>
    </div>

    <!-- GUIDE -->
    <div class="card">
        <h2>📚 Guide d'utilisation</h2>
        
        <h3 style="color: #0f172a; margin: 16px 0 12px 0; font-size: 1rem;">Option 1: Synchronisation manuelle</h3>
        <p style="color: #64748b; margin-bottom: 16px;">
            Cliquez sur "Synchroniser maintenant" pour lancer une synchronisation immédiate.
        </p>

        <h3 style="color: #0f172a; margin: 16px 0 12px 0; font-size: 1rem;">Option 2: Synchronisation automatique (Cron)</h3>
        <p style="color: #64748b; margin-bottom: 8px;">
            <strong>Sur cPanel:</strong>
        </p>
        <ol style="color: #64748b; margin-left: 20px; margin-bottom: 16px;">
            <li>Accédez à cPanel → Cron Jobs</li>
            <li>Ajouter une nouvelle tâche Cron</li>
            <li>Commande: <code>/usr/bin/php /home/user/public_html/vehicules/sync_wialon_cron.php</code></li>
            <li>Fréquence: Toutes les 30 minutes (ou selon vos besoins)</li>
            <li>Cliquer sur "Ajouter une tâche Cron"</li>
        </ol>

        <p style="color: #64748b; margin-bottom: 8px;">
            <strong>Sur VPS/Serveur Linux:</strong>
        </p>
        <ol style="color: #64748b; margin-left: 20px;">
            <li>Ouvrez le terminal</li>
            <li>Tapez: <code>crontab -e</code></li>
            <li>Ajoutez la ligne: <code>*/30 * * * * /usr/bin/php /chemin/vers/sync_wialon_cron.php</code></li>
            <li>Sauvegardez (Ctrl+O, Entrée, Ctrl+X)</li>
        </ol>
    </div>

</div>

</body>
</html>