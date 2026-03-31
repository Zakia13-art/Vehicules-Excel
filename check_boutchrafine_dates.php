<?php
require_once "db.php";
$db = Cnx();

echo "======================================\n";
echo "VERIFICATION BOUTCHRAFINE - DATES\n";
echo "======================================\n\n";

echo "Date d'aujourd'hui: " . date('d/m/Y') . "\n\n";

echo "DERNIERS TRAJETS BOUTCHRAFINE:\n";
echo str_pad("Date", 12) . " | Véhicule | Début | Fin | KM\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->query("SELECT DATE(debut) as d, vehicule, debut, fin, kilometrage FROM global_kilometrage WHERE transporteur_nom='BOUTCHRAFINE' ORDER BY debut DESC LIMIT 10");
while ($r = $stmt->fetch()) {
    echo $r['d'] . " | " . str_pad($r['vehicule'], 10) . " | " . $r['debut'] . " | " . $r['fin'] . " | " . number_format($r['kilometrage'], 2) . " km\n";
}

echo "\nDATE DU DERNIER TRAJET: ";
$stmt = $db->query("SELECT MAX(debut) as last_date FROM global_kilometrage WHERE transporteur_nom='BOUTCHRAFINE'");
$last = $stmt->fetch();
$last_date = $last['last_date'] ?? 'Aucune donnée';
echo $last_date . "\n";

// Calculer le nombre de jours depuis le dernier trajet
if ($last_date && $last_date != 'Aucune donnée') {
    $days_ago = floor((time() - strtotime($last_date)) / 86400);
    echo "Nombre de jours depuis: $days_ago jours\n";
}

echo "\n======================================\n";
?>
