<?php
require_once "db.php";

$db = Cnx();

echo "======================================\n";
echo "TRAJETS DANS LA BASE DE DONNEES\n";
echo "======================================\n\n";

// Check dernieres dates
$stmt = $db->query("SELECT DATE(debut) as date, COUNT(*) as total FROM trajets GROUP BY DATE(debut) ORDER BY date DESC LIMIT 10");
echo "Dates avec trajets:\n";
while ($row = $stmt->fetch()) {
    echo "  {$row['date']}: {$row['total']} trajets\n";
}

echo "\n";

// Check 27/03 specifically
$stmt = $db->prepare("SELECT COUNT(*) as total FROM trajets WHERE debut LIKE ?");
$stmt->execute(['2026-03-27%']);
$count_27 = $stmt->fetch()['total'];
echo "27/03/2026: $count_27 trajets\n";

// Check 25/03 specifically
$stmt = $db->prepare("SELECT COUNT(*) as total FROM trajets WHERE debut LIKE ?");
$stmt->execute(['2026-03-25%']);
$count_25 = $stmt->fetch()['total'];
echo "25/03/2026: $count_25 trajets\n";

// Check total
$stmt = $db->query("SELECT COUNT(*) as total FROM trajets");
$total = $stmt->fetch()['total'];
echo "\nTotal trajets dans BD: $total\n";

echo "======================================\n";
?>
