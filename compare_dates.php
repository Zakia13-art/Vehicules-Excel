<?php
require_once "db.php";
$db = Cnx();

echo "======================================\n";
echo "COMPARAISON DATES - DERNIER TRAJET\n";
echo "======================================\n\n";

echo "Date d'aujourd'hui: " . date('d/m/Y H:i') . "\n\n";

$transporteurs = array('BOUTCHRAFINE', 'G.T.C');

foreach ($transporteurs as $t) {
    $stmt = $db->prepare("SELECT MAX(debut) as last_date, COUNT(*) as total FROM global_kilometrage WHERE transporteur_nom=?");
    $stmt->execute([$t]);
    $r = $stmt->fetch();

    $last_date = $r['last_date'] ?? 'N/A';
    $total = $r['total'];

    if ($last_date != 'N/A') {
        $days_ago = floor((time() - strtotime($last_date)) / 86400);
        $status = $days_ago <= 1 ? '✅ OK' : '⚠️ Ancien';
        echo "$t: $last_date ($days_ago jours) - $total trajets $status\n";
    } else {
        echo "$t: Aucune donnée - ❌ Problème\n";
    }
}

echo "\n======================================\n";
?>
