<?php
/**
 * ========================================
 * IMPORT GLOBAL SIMPLE - MAIN FILE
 * ========================================
 */

require_once "api_global_simple.php";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Import Global Simple</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #e74c3c; border-bottom: 3px solid #e74c3c; }
        .log { background: #f8f9fa; padding: 8px; margin: 5px 0; font-size: 13px; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .summary { background: #d5f4e6; padding: 15px; margin: 15px 0; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 Import Global Simple</h1>

    <?php
    echo "<p>Début: " . date('H:i:s') . "</p>";

    $sid = sid();
    if (!$sid) {
        echo "<p class='error'>❌ Erreur connexion</p>";
        exit;
    }

    echo "<p class='success'>✅ Connecté</p>";

    global $tab_group;

    $total_km = 0;
    $total_infra = 0;
    $total_eval = 0;

    foreach ($tab_group as $nom => $gid) {
        echo "<div class='log'><strong>$nom</strong> (ID: $gid)</div>";

        // KM
        $tables_km = execGlobalReport(TEMPLATE_KM, $gid, $sid);
        if ($tables_km) {
            foreach ($tables_km as $idx => $tbl) {
                $rows = $tbl['rows'] ?? 0;
                if ($rows > 0) {
                    $data = getReportRows($idx, $rows, $sid);
                    if (isset($data[0]['r'])) {
                        foreach ($data as $r) {
                            if (isset($r['r'])) {
                                foreach ($r['r'] as $d) {
                                    $veh = $d['c']['0'] ?? '';
                                    $deb = $d['t1'] ?? 0;
                                    $fin = $d['t2'] ?? 0;
                                    $dur = $d['c']['1'] ?? '';
                                    $km = $d['c']['2'] ?? 0;
                                    if (insertGlobalKM($nom, $veh, $deb, $fin, $dur, $km)) $total_km++;
                                }
                            }
                        }
                    }
                }
            }
        }
        cleanRepport($sid);

        // Infractions
        $tables_infra = execGlobalReport(TEMPLATE_INFRA, $gid, $sid);
        if ($tables_infra) {
            foreach ($tables_infra as $idx => $tbl) {
                $rows = $tbl['rows'] ?? 0;
                if ($rows > 0) {
                    $data = getReportRows($idx, $rows, $sid);
                    if (isset($data[0]['r'])) {
                        foreach ($data as $r) {
                            if (isset($r['r'])) {
                                foreach ($r['r'] as $d) {
                                    $veh = $d['c']['0'] ?? '';
                                    $deb = $d['t1'] ?? 0;
                                    $fin = $d['t2'] ?? 0;
                                    $emp = $d['c']['1'] ?? '';
                                    $inf = $d['c']['2'] ?? '';
                                    if (insertGlobalInfra($nom, $veh, $deb, $fin, $emp, $inf)) $total_infra++;
                                }
                            }
                        }
                    }
                }
            }
        }
        cleanRepport($sid);

        // Evaluation
        $tables_eval = execGlobalReport(TEMPLATE_EVAL, $gid, $sid);
        if ($tables_eval) {
            foreach ($tables_eval as $idx => $tbl) {
                $rows = $tbl['rows'] ?? 0;
                if ($rows > 0) {
                    $data = getReportRows($idx, $rows, $sid);
                    if (isset($data[0]['r'])) {
                        foreach ($data as $r) {
                            if (isset($r['r'])) {
                                foreach ($r['r'] as $d) {
                                    $veh = $d['c']['0'] ?? '';
                                    $deb = $d['t1'] ?? 0;
                                    $fin = $d['t2'] ?? 0;
                                    $emp = $d['c']['1'] ?? '';
                                    $pen = $d['c']['2'] ?? 0;
                                    $eval = $d['c']['3'] ?? '';
                                    if (insertGlobalEval($nom, $veh, $deb, $fin, $emp, $pen, $eval)) $total_eval++;
                                }
                            }
                        }
                    }
                }
            }
        }
        cleanRepport($sid);
    }

    echo "<div class='summary'>";
    echo "<h3>Résultats</h3>";
    echo "<p>Kilométrage: <strong>$total_km</strong></p>";
    echo "<p>Infractions: <strong>$total_infra</strong></p>";
    echo "<p>Évaluation: <strong>$total_eval</strong></p>";
    echo "<p>Total: <strong>" . ($total_km + $total_infra + $total_eval) . "</strong></p>";
    echo "</div>";

    echo "<p>Fin: " . date('H:i:s') . "</p>";
    ?>

    <hr>
    <a href="view_global_km.php" class="btn">📊 Voir KM</a>
    <a href="view_global_infractions.php" class="btn">⚠️ Voir Infractions</a>
    <a href="view_global_evaluation.php" class="btn">📈 Voir Évaluation</a>

</div>

</body>
</html>
