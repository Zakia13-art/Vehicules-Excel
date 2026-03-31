<?php
/**
 * SAUVEGARDE AUTOMATIQUE DES RÉSULTATS
 * Sauvegarde les données de test dans testresultat.txt sur le Desktop
 */
set_time_limit(600);
require_once "C:/xampp/htdocs/vehicules/db.php";

// Créer le dossier logs si nécessaire
@mkdir(__DIR__ . '/logs', 0755, true);

$db = Cnx();

// Fichier de sortie dans le PROJET
$output_file = __DIR__ . "/logs/testresultat.txt";

// Ouvrir le fichier en écriture
$fp = fopen($output_file, "w");

fwrite($fp, "======================================\n");
fwrite($fp, "RAPPORT COMPLET - ETAT DES DONNÉES\n");
fwrite($fp, "Généré le: " . date('d/m/Y H:i:s') . "\n");
fwrite($fp, "======================================\n\n");

// Tous les transporteurs
$all_transporteurs = array(
    'BOUTCHRAFINE', 'SOMATRIN', 'MARATRANS', 'G.T.C',
    'DOUKALI', 'COTRAMAB', 'CORYAD', 'CONSMETA',
    'CHOUROUK', 'CARRE', 'STB', 'FASTTRANS'
);

fwrite($fp, "KILOMÉTRAGE:\n");
fwrite($fp, str_repeat("-", 70) . "\n");
foreach ($all_transporteurs as $t) {
    $stmt = $db->prepare("SELECT COUNT(*) as c, SUM(kilometrage) as km, MAX(debut) as last_date FROM global_kilometrage WHERE transporteur_nom=?");
    $stmt->execute([$t]);
    $r = $stmt->fetch();
    $km_total = number_format($r['km'] ?? 0, 2);
    $last = $r['last_date'] ?? 'N/A';
    fwrite($fp, sprintf("  %-15s | %3d trajets | %10s km | Dernier: %s\n", $t, $r['c'], $km_total, $last));
}

fwrite($fp, "\nÉVALUATION ÉCO-CONDUITE:\n");
fwrite($fp, str_repeat("-", 70) . "\n");
foreach ($all_transporteurs as $t) {
    $stmt = $db->prepare("SELECT COUNT(*) as c, SUM(penalites) as pen, MAX(debut) as last_date FROM global_evaluation WHERE transporteur_nom=?");
    $stmt->execute([$t]);
    $r = $stmt->fetch();
    $pen_total = number_format($r['pen'] ?? 0, 2);
    $last = $r['last_date'] ?? 'N/A';
    fwrite($fp, sprintf("  %-15s | %3d evals    | %8s pénalités | Dernier: %s\n", $t, $r['c'], $pen_total, $last));
}

fwrite($fp, "\nINFRACTIONS:\n");
fwrite($fp, str_repeat("-", 70) . "\n");
foreach ($all_transporteurs as $t) {
    $stmt = $db->prepare("SELECT COUNT(*) as c, MAX(debut) as last_date FROM global_infractions WHERE transporteur_nom=?");
    $stmt->execute([$t]);
    $r = $stmt->fetch();
    $status = $r['c'] == 0 ? '✅ Aucune' : '⚠️ ' . $r['c'] . ' infraction(s)';
    $last = $r['last_date'] ?? 'N/A';
    fwrite($fp, sprintf("  %-15s | %-20s | Dernier: %s\n", $t, $status, $last));
}

fwrite($fp, "\n" . str_repeat("=", 70) . "\n");
fwrite($fp, "TOTAL TOUTES TABLES CONFONDUES:\n");
fwrite($fp, str_repeat("=", 70) . "\n");

// Totaux globaux
$stmt = $db->query("SELECT COUNT(*) as c FROM global_kilometrage");
$km_total = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM global_evaluation");
$eval_total = $stmt->fetch()['c'];

$stmt = $db->query("SELECT COUNT(*) as c FROM global_infractions");
$infra_total = $stmt->fetch()['c'];

$stmt = $db->query("SELECT SUM(kilometrage) as km FROM global_kilometrage");
$km_sum = number_format($stmt->fetch()['km'] ?? 0, 2);

fwrite($fp, "• Kilométrage: $km_total enregistrements ($km_sum km)\n");
fwrite($fp, "• Évaluation: $eval_total enregistrements\n");
fwrite($fp, "• Infractions: $infra_total enregistrements\n");

fwrite($fp, "\n======================================\n");
fwrite($fp, "DÉTAIL - DERNIERS TRAJETS PAR TRANSPORTEUR\n");
fwrite($fp, "======================================\n\n");

foreach ($all_transporteurs as $t) {
    $stmt = $db->prepare("SELECT vehicule, debut, fin, duree, kilometrage FROM global_kilometrage WHERE transporteur_nom=? ORDER BY debut DESC LIMIT 5");
    $stmt->execute([$t]);
    $rows = $stmt->fetchAll();

    if (count($rows) > 0) {
        fwrite($fp, "【$t】\n");
        foreach ($rows as $r) {
            fwrite($fp, "  • {$r['vehicule']} | {$r['debut']} | {$r['duree']} | {$r['kilometrage']} km\n");
        }
        fwrite($fp, "\n");
    }
}

fclose($fp);

// Afficher à l'écran aussi
echo "✅ Résultats sauvegardés dans le PROJET: $output_file\n\n";

echo "Contenu du fichier:\n";
echo file_get_contents($output_file);

echo "\n======================================\n";
echo "Pour voir les résultats:\n";
echo "Ouvrez: C:/xampp/htdocs/vehicules/logs/testresultat.txt\n";
echo "Ou exécutez: php C:/xampp/htdocs/vehicules/save_results.php\n";
echo "======================================\n";
?>
