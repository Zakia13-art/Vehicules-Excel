<?php
require_once "db.php";
$db = Cnx();

echo "======================================\n";
echo "ETAT ACTUEL DES TABLES GLOBALES\n";
echo "======================================\n\n";

echo "1. GLOBAL_KILOMETRAGE:\n";
echo "   Total enregistrements: ";
$stmt = $db->query("SELECT COUNT(*) as c FROM global_kilometrage");
echo $stmt->fetch()['c'] . "\n\n";

echo "   Par transporteur:\n";
$stmt = $db->query("SELECT transporteur_nom, COUNT(*) as c FROM global_kilometrage GROUP BY transporteur_nom ORDER BY c DESC");
while ($r = $stmt->fetch()) {
    echo "   - {$r['transporteur_nom']}: {$r['c']}\n";
}

echo "\n2. GLOBAL_EVALUATION:\n";
echo "   Total enregistrements: ";
$stmt = $db->query("SELECT COUNT(*) as c FROM global_evaluation");
echo $stmt->fetch()['c'] . "\n\n";

echo "   Par transporteur:\n";
$stmt = $db->query("SELECT transporteur_nom, COUNT(*) as c FROM global_evaluation GROUP BY transporteur_nom ORDER BY c DESC");
while ($r = $stmt->fetch()) {
    echo "   - {$r['transporteur_nom']}: {$r['c']}\n";
}

echo "\n3. GLOBAL_INFRACTIONS:\n";
echo "   Total enregistrements: ";
$stmt = $db->query("SELECT COUNT(*) as c FROM global_infractions");
echo $stmt->fetch()['c'] . "\n\n";

echo "   Par transporteur:\n";
$stmt = $db->query("SELECT transporteur_nom, COUNT(*) as c FROM global_infractions GROUP BY transporteur_nom ORDER BY c DESC");
while ($r = $stmt->fetch()) {
    echo "   - {$r['transporteur_nom']}: {$r['c']}\n";
}

echo "\n======================================\n";
?>
