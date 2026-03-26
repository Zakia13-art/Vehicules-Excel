<?php
require_once 'config.php';
$pdo = getDB();
$rows = $pdo->query("SELECT * FROM boutchrafine_kilometrage ORDER BY debut DESC")->fetchAll();
$total_km = $pdo->query("SELECT SUM(kilometrage) FROM boutchrafine_kilometrage")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BOUTCHRAFINE - Kilométrage</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #27ae60; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #27ae60; color: white; }
        .total { background: #d5f4e6; padding: 15px; font-size: 18px; font-weight: bold; }
        a.btn { background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 BOUTCHRAFINE - Kilométrage</h1>
    <div class="total">Total Kilométrage: <?= number_format($total_km, 2) ?> km | <?= count($rows) ?> enregistrements</div>
    <a href="import_boutchrafine.php" class="btn">🔄 Nouvel Import</a>
    <a href="index.php" class="btn">🏠 Accueil</a>
    <table>
        <thead>
            <tr>
                <th>Véhicule</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Durée</th>
                <th>Kilométrage</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['vehicule']) ?></td>
                <td><?= $r['debut'] ?></td>
                <td><?= $r['fin'] ?></td>
                <td><?= htmlspecialchars($r['duree']) ?></td>
                <td><strong><?= number_format($r['kilometrage'], 2) ?> km</strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
