<?php
/**
 * Reset et ré-import avec durée calculée
 */
require_once "db.php";
$db = Cnx();

echo "Suppression des anciennes données...\n";
$db->exec("DELETE FROM global_infractions");
$db->exec("DELETE FROM global_evaluation");
$db->exec("DELETE FROM global_kilometrage");
echo "OK\n\n";

echo "Ré-import en cours...\n";
require_once "import_30days.php";
?>
