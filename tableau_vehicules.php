<?php
require_once 'config.php';

$pdo = getDB();

// Récupérer toutes les données importées
$rows = $pdo->query("SELECT * FROM rapport_vehicules ORDER BY entreprise, nom_vehicule")->fetchAll();

// Totaux
$totalKm   = array_sum(array_column($rows,'kilometrage'));
$totalInfr = array_sum(array_column($rows,'nb_infractions'));
$nbVeh     = count($rows);
$notes     = array_filter(array_column($rows,'note_conduite'));
$moyNote   = count($notes) ? round(array_sum($notes)/count($notes),2) : null;

// Fonction badge
function badgeStatus($val) {
    if ($val === null) return 'inactif';
    return $val >= 80 ? 'vert' : 'orange';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Tableau véhicules</title>
<style>
body { font-family: Arial, sans-serif; background:#f0f4f8; color:#1e293b; padding:20px; }
table { width:100%; border-collapse: collapse; background:#fff; border-radius:8px; overflow:hidden; }
th, td { padding:10px; border-bottom:1px solid #e2e8f0; text-align:left; }
th { background:#f8fafc; text-transform:uppercase; }
.badge { padding:4px 8px; border-radius:12px; color:#fff; font-weight:600; }
.vert { background:#22c55e; }
.orange { background:#f97316; }
.inactif { background:#64748b; background-opacity:.3; }
</style>
</head>
<body>

<h2>Tableau des véhicules importés</h2>
<p>Total véhicules : <?= $nbVeh ?> | Total km : <?= $totalKm ?> | Total infractions : <?= $totalInfr ?> | Note moyenne : <?= $moyNote ?? 'N/A' ?></p>

<table>
    <thead>
        <tr>
            <th>Entreprise</th>
            <th>Nom véhicule</th>
            <th>Note conduite /100</th>
            <th>Nb infractions</th>
            <th>Kilométrage</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $v): ?>
        <tr>
            <td><?= htmlspecialchars($v['entreprise']) ?></td>
            <td><?= htmlspecialchars($v['nom_vehicule']) ?></td>
            <td><span class="badge <?= badgeStatus($v['note_conduite']) ?>"><?= $v['note_conduite'] ?? 'N/A' ?></span></td>
            <td><?= $v['nb_infractions'] ?></td>
            <td><?= $v['kilometrage'] ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="3"><strong>TOTAL</strong></td>
            <td><?= $totalInfr ?></td>
            <td><?= $totalKm ?></td>
        </tr>
    </tbody>
</table>

</body>
</html>
