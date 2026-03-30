<?php
/**
 * Réinitialiser et ré-importer les données globales
 */
set_time_limit(1200);
require_once __DIR__ . "/db.php";

$db = Cnx();

echo "======================================\n";
echo "RESET ET RE-IMPORT DES DONNÉES\n";
echo "======================================\n\n";

// Supprimer les anciennes données
echo "1. Suppression des anciennes données...\n";
$db->exec("DELETE FROM global_infractions");
$db->exec("DELETE FROM global_evaluation");
$db->exec("DELETE FROM global_kilometrage");
echo "   ✅ Tables vidées\n\n";

// Ré-importer avec auto_save_3tables.php
echo "2. Ré-import en cours...\n";
echo "======================================\n\n";

require_once __DIR__ . "/auto_save_3tables.php";
?>
