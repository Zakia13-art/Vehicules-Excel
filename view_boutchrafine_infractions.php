<?php
require_once 'config.php';
$pdo = getDB();
$rows = $pdo->query("SELECT * FROM boutchrafine_infractions ORDER BY debut DESC")->fetchAll();
$total = $pdo->query("SELECT COUNT(*) FROM boutchrafine_infractions")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BOUTCHRAFINE - Infractions</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #e74c3c; color: white; }
        .infraction { background: #fadbd8; }
        .total { background: #fadbd8; padding: 15px; font-size: 18px; font-weight: bold; }
        a.btn { background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>⚠️ BOUTCHRAFINE - Infractions</h1>
    <div class="total">Total Infractions: <?= $total ?></div>
    <a href="import_boutchrafine.php" class="btn">🔄 Nouvel Import</a>
    <a href="index.php" class="btn">🏠 Accueil</a>
    <table>
        <thead>
            <tr>
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
