<?php
require_once 'config.php';
$pdo = getDB();

$transporteur_filter = $_GET['transporteur'] ?? '';
$where = $transporteur_filter ? "WHERE transporteur_nom = '" . addslashes($transporteur_filter) . "'" : "";

$rows = $pdo->query("SELECT * FROM global_infractions $where ORDER BY debut DESC LIMIT 500")->fetchAll();
$total = $pdo->query("SELECT COUNT(*) FROM global_infractions $where")->fetchColumn();

$transporteurs = $pdo->query("SELECT DISTINCT transporteur_nom FROM global_infractions ORDER BY transporteur_nom")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Infractions - Tous Transporteurs</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; }
        .filter { background: #ecf0f1; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .filter select, .filter button { padding: 8px 15px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #e74c3c; color: white; }
        .infraction { background: #fadbd8; }
        .total { background: #fadbd8; padding: 15px; font-size: 18px; font-weight: bold; }
        a.btn { background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; }
        tr:hover { background: #f9f9f9; }
    </style>
</head>
<body>
<div class="container">
    <h1>⚠️ Infractions - TOUS LES TRANSPORTEURS</h1>

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
            <a href="view_global_infractions.php" class="btn">Réinitialiser</a>
        </form>
    </div>

    <div class="total">Total Infractions: <?= $total ?></div>
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
                <th>Infraction</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr class="infraction">
                <td><strong><?= htmlspecialchars($r['transporteur_nom']) ?></strong></td>
                <td><?= htmlspecialchars($r['vehicule']) ?></td>
                <td><?= $r['debut'] ?></td>
                <td><?= $r['fin'] ?></td>
                <td><?= htmlspecialchars($r['emplacement']) ?></td>
                <td><strong><?= htmlspecialchars($r['infraction']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
