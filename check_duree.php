<?php
require_once "db.php";
$db = Cnx();

echo "======================================\n";
echo "VERIFICATION DE LA DUREE\n";
echo "======================================\n\n";

echo "BOUTCHRAFINE - KILOMETRAGE:\n";
$stmt = $db->query("SELECT vehicule, debut, fin, duree, kilometrage FROM global_kilometrage WHERE transporteur_nom='BOUTCHRAFINE' LIMIT 5");
while ($r = $stmt->fetch()) {
    echo "  {$r['vehicule']} | {$r['debut']} -> {$r['fin']} | Durée: {$r['duree']} | KM: {$r['kilometrage']}\n";
}

echo "\nG.T.C - KILOMETRAGE:\n";
$stmt = $db->query("SELECT vehicule, debut, fin, duree, kilometrage FROM global_kilometrage WHERE transporteur_nom='G.T.C' LIMIT 5");
while ($r = $stmt->fetch()) {
    echo "  {$r['vehicule']} | {$r['debut']} -> {$r['fin']} | Durée: {$r['duree']} | KM: {$r['kilometrage']}\n";
}

echo "\n======================================\n";
?>
