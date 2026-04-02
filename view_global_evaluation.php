<?php
require_once 'config.php';
$pdo = getDB();

// TOUS les transporteurs - CIMAT 2026 (15 groupes)
$all_transporteurs = array(
    'STE STB', 'SOTRAFOREST', 'SOMATRIN', 'MARATRANS', 'GTC CIMAT',
    'FLEXILOG', 'FIRST LOGISTIQUE', 'FAYSSAL METAL', 'FAST TRANS',
    'COTRAMAB', 'CORYAD', 'CIMATRAK', 'CHOUROUK',
    'BOUTCHRAFIN_CIMAT', 'ANFAL'
);

$transporteur_filter = $_GET['transporteur'] ?? '';
$where = $transporteur_filter ? "WHERE transporteur_nom = '" . addslashes($transporteur_filter) . "'" : "";

$rows = $pdo->query("SELECT * FROM global_evaluation $where ORDER BY debut DESC LIMIT 500")->fetchAll();
$total_penalites = $pdo->query("SELECT SUM(penalites) FROM global_evaluation $where")->fetchColumn();

// Utiliser tous les transporteurs pour le filtre
$transporteurs = $all_transporteurs;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Évaluation - Tous Transporteurs</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #9b59b6; }
        .filter { background: #ecf0f1; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .filter select, .filter button { padding: 8px 15px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #9b59b6; color: white; }
        .total { background: #e8daef; padding: 15px; font-size: 18px; font-weight: bold; }
        a.btn { background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; }
        .eval-A { background: #d5f4e6; }
        .eval-B { background: #fff3cd; }
        .eval-C { background: #fadbd8; }
        tr:hover { background: #f9f9f9; }
    </style>
</head>
<body>
<div class="container">
    <h1>📈 Évaluation Eco-conduite - TOUS LES TRANSPORTEURS</h1>

    <div class="filter">
        <form method="GET">
            <label>Filtrer par:</label>
            <select name="transporteur">
                <option value="">-- Tous les transporteurs --</option>
                <?php foreach ($transporteurs as $t): ?>
                <option value="<?= $t ?>" <?= $transporteur_filter == $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filtrer</button>
            <a href="view_global_evaluation.php" class="btn">Réinitialiser</a>
        </form>
    </div>

    <div class="total">Total Pénalités: <?= number_format($total_penalites, 2) ?> | <?= count($rows) ?> enregistrements</div>
    <a href="import_global.php" class="btn">🔄 Nouvel Import</a>
    <a href="index.php" class="btn">🏠 Accueil</a>

    <table>
        <thead>
            <tr>
                <th>Transporteur</th>
                <th>Véhicule</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Emplacement</th>
                <th>Pénalités</th>
                <th>Évaluation</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr class="eval-<?= substr($r['evaluation'], 0, 1) ?>">
                <td><strong><?= htmlspecialchars($r['transporteur_nom']) ?></strong></td>
                <td><?= htmlspecialchars($r['vehicule']) ?></td>
                <td><?= $r['debut'] ?></td>
                <td><?= $r['fin'] ?></td>
                <td><?= htmlspecialchars($r['emplacement']) ?></td>
                <td><?= number_format($r['penalites'], 2) ?></td>
                <td><strong><?= htmlspecialchars($r['evaluation']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
