<?php
/*
Script de vérification des données stockées en BDD
Affiche les trajets importés depuis Wialon
*/

// Connexion à la BDD
function Cnx(){
    try 
    {
        $pdo_options[PDO::ATTR_ERRMODE]=PDO::ERRMODE_EXCEPTION;
        $db=new PDO('mysql:host=localhost; dbname=repport','root','');
        return $db;
    }
    catch (Exception $e) 
    {
        die('Erreur de connexion:' .$e->getMessage());
        return null;
    }
}

$db = Cnx();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification des données Wialon en BDD</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        .header h1 { font-size: 1.5rem; font-weight: 600; color: #0f172a; margin-bottom: 12px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 20px; }
        .stat-box { background: #f8fafc; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; }
        .stat-label { font-size: 0.875rem; color: #64748b; margin-bottom: 4px; }
        .stat-value { font-size: 1.75rem; font-weight: 600; color: #0f172a; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        thead { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 12px 14px; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
        td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
        tbody tr:hover { background: #f8fafc; }
        .success { color: #16a34a; font-weight: 600; }
        .error { color: #dc2626; font-weight: 600; }
        .warning { color: #ea580c; font-weight: 600; }
        .no-data { text-align: center; padding: 40px; color: #94a3b8; }
        .actions { display: flex; gap: 12px; margin-top: 24px; }
        .btn { background: #0f172a; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn:hover { background: #1e293b; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #991b1b; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🔍 Vérification des données Wialon en BDD</h1>
        <p>Vérifiez que les trajets importés de Wialon sont bien stockés dans la base de données</p>
    </div>

    <?php
    // ══════════════════════════════════════════════════════════
    // ── Compter le total des trajets ──────────────────────────
    // ══════════════════════════════════════════════════════════
    
    try {
        $countResult = $db->query("SELECT COUNT(*) as total FROM trajets");
        $total = $countResult->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Compter par transporteur
        $byTransportResult = $db->query("SELECT tt.name, COUNT(*) as count FROM trajets t LEFT JOIN transporteurs tt ON t.transporteur = tt.id GROUP BY t.transporteur ORDER BY count DESC");
        $byTransport = $byTransportResult->fetchAll(PDO::FETCH_ASSOC);
        
        // Kilométrage total
        $kmResult = $db->query("SELECT SUM(CAST(REPLACE(REPLACE(kilometrage, 'Km', ''), ' ', '') AS DECIMAL(10,2))) as total_km FROM trajets");
        $totalKm = $kmResult->fetch(PDO::FETCH_ASSOC)['total_km'] ?? 0;
        
        // Date des trajets les plus récents
        $dateResult = $db->query("SELECT MAX(debut) as latest_date, MIN(debut) as oldest_date FROM trajets");
        $dates = $dateResult->fetch(PDO::FETCH_ASSOC);
        
        ?>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-label">📊 Total trajets en BDD</div>
                <div class="stat-value <?= $total > 0 ? 'success' : 'error' ?>"><?= $total ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">🚗 Kilométrage total</div>
                <div class="stat-value"><?= number_format($totalKm, 0, ',', ' ') ?> Km</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">📅 Plus récent</div>
                <div class="stat-value" style="font-size: 1rem;"><?= $dates['latest_date'] ?? 'N/A' ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">📅 Plus ancien</div>
                <div class="stat-value" style="font-size: 1rem;"><?= $dates['oldest_date'] ?? 'N/A' ?></div>
            </div>
        </div>

        <?php if ($total > 0): ?>
            <div class="card" style="margin-top: 24px;">
                <h2>📋 Trajets par Transporteur</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Transporteur</th>
                            <th>Nombre de trajets</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byTransport as $transport): ?>
                        <tr>
                            <td><?= htmlspecialchars($transport['name'] ?? 'Inconnu') ?></td>
                            <td><span class="success"><?= $transport['count'] ?></span></td>
                            <td><?= number_format(($transport['count'] / $total) * 100, 1) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" style="margin-top: 24px;">
                <h2>📍 Les 10 derniers trajets importés</h2>
                <?php
                $lastTrajetsResult = $db->query("SELECT t.*, tt.name as transporteur_name FROM trajets t LEFT JOIN transporteurs tt ON t.transporteur = tt.id ORDER BY t.debut DESC LIMIT 10");
                $lastTrajets = $lastTrajetsResult->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Transporteur</th>
                            <th>Véhicule</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Kilométrage</th>
                            <th>Pénalité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lastTrajets as $trajet): ?>
                        <tr>
                            <td><?= htmlspecialchars($trajet['transporteur_name'] ?? 'Inconnu') ?></td>
                            <td><?= htmlspecialchars($trajet['vehicule']) ?></td>
                            <td><?= htmlspecialchars($trajet['debut']) ?></td>
                            <td><?= htmlspecialchars($trajet['fin']) ?></td>
                            <td><?= htmlspecialchars($trajet['kilometrage']) ?></td>
                            <td><?= htmlspecialchars($trajet['penalite']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" style="margin-top: 24px;">
                <h2>✅ Statut</h2>
                <p><span class="success">✅ Les données sont bien stockées en BDD!</span></p>
                <p style="margin-top: 12px; color: #64748b;">Total: <strong><?= $total ?></strong> trajets trouvés dans la table <code>trajets</code></p>
            </div>

        <?php else: ?>
            <div class="card" style="margin-top: 24px;">
                <div class="no-data">
                    <h2 style="color: #dc2626; margin-bottom: 12px;">❌ Aucune donnée trouvée</h2>
                    <p>La table <code>trajets</code> est vide.</p>
                    <p style="margin-top: 12px;">Veuillez exécuter <strong>import_wialon.php</strong> pour importer les données depuis Wialon.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="actions" style="margin-top: 32px;">
            <a href="import_wialon.php" class="btn">🔄 Importer depuis Wialon</a>
            <a href="rapports.php" class="btn">📊 Voir les rapports</a>
            <a href="javascript:history.back()" class="btn" style="background: #64748b;">← Retour</a>
        </div>

    <?php } catch (Exception $e) { ?>
        <div class="card" style="margin-top: 24px;">
            <div class="no-data">
                <h2 style="color: #dc2626;">❌ Erreur BDD</h2>
                <p><?= htmlspecialchars($e->getMessage()) ?></p>
            </div>
        </div>
    <?php } ?>

</div>

</body>
</html>