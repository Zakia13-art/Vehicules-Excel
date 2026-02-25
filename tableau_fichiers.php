<?php
require_once 'config.php';
$pdo = getDB();
$rows = $pdo->query("SELECT * FROM rapport_fichiers ORDER BY entreprise, regroupement")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Tableau fichiers fusionnés</title>
<style>
table { width:100%; border-collapse: collapse }
th, td { border:1px solid #ddd; padding:8px; text-align:left }
th { background:#f4f4f4 }
</style>
</head>
<body>
<h1>📋 Tableau fusionné des fichiers</h1>
<table>
    <thead>
        <tr>
            <th>Entreprise</th>
            <th>Regroupement</th>
            <th>Kilométrage</th>
            <th>Durée</th>
            <th>Infraction</th>
            <th>Évaluation</th>
            <th>Fichier source</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['entreprise']) ?></td>
            <td><?= htmlspecialchars($r['regroupement']) ?></td>
            <td><?= number_format($r['kilometrage'],2,',',' ') ?></td>
            <td><?= htmlspecialchars($r['duree'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['infraction']) ?></td>
            <td><?= htmlspecialchars($r['evaluation']) ?></td>
            <td><?= htmlspecialchars($r['nom_fichier']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
