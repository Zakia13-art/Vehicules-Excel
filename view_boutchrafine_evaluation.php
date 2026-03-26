<?php
require_once 'config.php';
$pdo = getDB();
$rows = $pdo->query("SELECT * FROM boutchrafine_evaluation ORDER BY debut DESC")->fetchAll();
$total_penalites = $pdo->query("SELECT SUM(penalites) FROM boutchrafine_evaluation")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BOUTCHRAFINE - Évaluation</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #9b59b6; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #9b59b6; color: white; }
        .total { background: #e8daef; padding: 15px; font-size: 18px; font-weight: bold; }
        a.btn { background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; }
        .eval-A { background: #d5f4e6; }
        .eval-B { background: #fff3cd; }
        .eval-C { background: #fadbd8; }
    </style>
</head>
<body>
<div class="container">
    <h1>📈 BOUTCHRAFINE - Évaluation Eco-conduite</h1>
    <div class="total">Total Pénalités: <?= number_format($total_penalites, 2) ?> | <?= count($rows) ?> enregistrements</div>
    <a href="import_boutchrafine.php" class="btn">🔄 Nouvel Import</a>
    <a href="index.php" class="btn">🏠 Accueil</a>
    <table>
        <thead>
            <tr>
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
