<?php
require_once "db.php";
$db = Cnx();

echo "=== GLOBAL_KILOMETRAGE ===\n";
$stmt = $db->query("SELECT COUNT(*) as total FROM global_kilometrage");
echo "Total: " . $stmt->fetchColumn() . " enregistrements\n";

$stmt = $db->query("SELECT * FROM global_kilometrage ORDER BY id DESC LIMIT 2");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['transporteur_nom']} | {$r['vehicule']} | KM: {$r['kilometrage']}\n";
}

echo "\n=== GLOBAL_INFRACTIONS ===\n";
$stmt = $db->query("SELECT COUNT(*) as total FROM global_infractions");
echo "Total: " . $stmt->fetchColumn() . " enregistrements\n";

$stmt = $db->query("SELECT * FROM global_infractions ORDER BY id DESC LIMIT 2");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['transporteur_nom']} | {$r['infraction']}\n";
}

echo "\n=== GLOBAL_EVALUATION ===\n";
$stmt = $db->query("SELECT COUNT(*) as total FROM global_evaluation");
echo "Total: " . $stmt->fetchColumn() . " enregistrements\n";

$stmt = $db->query("SELECT * FROM global_evaluation ORDER BY id DESC LIMIT 2");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['transporteur_nom']} | {$r['evaluation']}\n";
}
?>
