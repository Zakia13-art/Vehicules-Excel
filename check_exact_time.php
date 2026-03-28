<?php
require_once "db.php";
$db = Cnx();

echo "=== VERIFICATION HEURES EXACTES ===\n\n";

echo "--- GLOBAL_KILOMETRAGE (5 derniers) ---\n";
$stmt = $db->query("SELECT id, transporteur_nom, vehicule, debut, fin, duree, kilometrage FROM global_kilometrage ORDER BY id DESC LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['transporteur_nom']} | {$r['vehicule']}\n";
    echo "  DEBUT: {$r['debut']}\n";
    echo "  FIN:   {$r['fin']}\n";
    echo "  DUREE: {$r['duree']} | KM: {$r['kilometrage']}\n\n";
}

echo "--- GLOBAL_EVALUATION (5 derniers) ---\n";
$stmt = $db->query("SELECT id, transporteur_nom, vehicule, debut, fin, penalites, evaluation FROM global_evaluation ORDER BY id DESC LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['transporteur_nom']} | {$r['vehicule']}\n";
    echo "  DEBUT: {$r['debut']}\n";
    echo "  FIN:   {$r['fin']}\n";
    echo "  PENAL: {$r['penalites']} | EVAL: {$r['evaluation']}\n\n";
}
?>
