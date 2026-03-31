<?php
require_once "db.php";
$db = Cnx();

echo "======================================\n";
echo "ETAT DES TABLES PAR TRANSPORTEUR\n";
echo "======================================\n\n";

$all_transporteurs = array(
    'BOUTCHRAFINE', 'SOMATRIN', 'MARATRANS', 'G.T.C',
    'DOUKALI', 'COTRAMAB', 'CORYAD', 'CONSMETA',
    'CHOUROUK', 'CARRE', 'STB', 'FASTTRANS'
);

echo "KILOMETRAGE:\n";
echo str_pad("Transporteur", 15) . " | Enregistrements | Total KM\n";
echo str_repeat("-", 60) . "\n";
foreach ($all_transporteurs as $t) {
    $stmt = $db->prepare("SELECT COUNT(*) as c, SUM(kilometrage) as km FROM global_kilometrage WHERE transporteur_nom=?");
    $stmt->execute([$t]);
    $r = $stmt->fetch();
    echo str_pad($t, 15) . " | " . str_pad($r['c'], 14) . " | " . number_format($r['km'] ?? 0, 2) . " km\n";
}

echo "\nEVALUATION:\n";
echo str_pad("Transporteur", 15) . " | Enregistrements | Pénalités\n";
echo str_repeat("-", 60) . "\n";
foreach ($all_transporteurs as $t) {
    $stmt = $db->prepare("SELECT COUNT(*) as c, SUM(penalites) as p FROM global_evaluation WHERE transporteur_nom=?");
    $stmt->execute([$t]);
    $r = $stmt->fetch();
    echo str_pad($t, 15) . " | " . str_pad($r['c'], 14) . " | " . number_format($r['p'] ?? 0, 2) . "\n";
}

echo "\nINFRACTIONS:\n";
echo str_pad("Transporteur", 15) . " | Enregistrements\n";
echo str_repeat("-", 60) . "\n";
foreach ($all_transporteurs as $t) {
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM global_infractions WHERE transporteur_nom=?");
    $stmt->execute([$t]);
    $r = $stmt->fetch();
    echo str_pad($t, 15) . " | " . $r['c'] . "\n";
}

echo "\n======================================\n";
?>
