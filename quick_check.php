<?php
require_once "db.php";
$db = Cnx();

echo "ETAT DES TABLES APRES RESET:\n";
echo "======================================\n\n";

echo "1. GLOBAL_KILOMETRAGE: ";
$stmt = $db->query("SELECT COUNT(*) as c FROM global_kilometrage");
echo $stmt->fetch()['c'] . " enregistrements\n";

echo "2. GLOBAL_EVALUATION: ";
$stmt = $db->query("SELECT COUNT(*) as c FROM global_evaluation");
echo $stmt->fetch()['c'] . " enregistrements\n";

echo "3. GLOBAL_INFRACTIONS: ";
$stmt = $db->query("SELECT COUNT(*) as c FROM global_infractions");
echo $stmt->fetch()['c'] . " enregistrements\n";

echo "\nExemple evaluation:\n";
$stmt = $db->query("SELECT * FROM global_evaluation LIMIT 5");
while ($r = $stmt->fetch()) {
    echo "  {$r['transporteur_nom']} | {$r['vehicule']} | {$r['emplacement']} | {$r['evaluation']}\n";
}
?>
