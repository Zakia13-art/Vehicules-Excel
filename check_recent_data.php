<?php
require_once "db.php";

$db = Cnx();

echo "======================================\n";
echo "DONNEES RECENTES DANS LA BASE\n";
echo "======================================\n\n";

// Dernieres dates avec donnees
$stmt = $db->query("SELECT DATE(debut) as date, COUNT(*) as total FROM trajets GROUP BY DATE(debut) ORDER BY date DESC LIMIT 15");
echo "DATES AVEC TRAJETS (plus recent -> plus ancien):\n";
while ($row = $stmt->fetch()) {
    echo "  {$row['date']}: {$row['total']} trajets\n";
}

echo "\n";

// Dates des 7 derniers jours
echo "7 DERNIERS JOURS:\n";
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM trajets WHERE debut LIKE ?");
    $stmt->execute(["$date%"]);
    $count = $stmt->fetch()['total'];
    echo "  $date: $count trajets\n";
}

echo "\n";

// Global kilometrage
echo "KILOMETRAGE:\n";
$stmt = $db->query("SELECT DATE(debut) as date, SUM(kilometrage) as total FROM global_kilometrage GROUP BY DATE(debut) ORDER BY date DESC LIMIT 10");
while ($row = $stmt->fetch()) {
    echo "  {$row['date']}: {$row['total']} km\n";
}

echo "\n";

// Dernier trajet insere
echo "DERNIER TRAJET INSERE:\n";
$stmt = $db->query("SELECT * FROM trajets ORDER BY id DESC LIMIT 1");
$last = $stmt->fetch();
if ($last) {
    echo "  ID: {$last['id']}\n";
    echo "  Vehicule: {$last['vehicule']}\n";
    echo "  Debut: {$last['debut']}\n";
    echo "  Fin: {$last['fin']}\n";
    echo "  Parcour: {$last['parcour']}\n";
    echo "  KM: {$last['km']}\n";
}

echo "\n======================================\n";
?>
