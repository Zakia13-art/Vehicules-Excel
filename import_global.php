<?php
/**
 * ========================================
 * IMPORT GLOBAL - TOUS LES GROUPS
 * ========================================
 * Exécuter l'import des 3 rapports pour TOUS les transporteurs
 */

require_once "api_global.php";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Import GLOBAL - Wialon</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 20px; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        .log { background: #f8f9fa; border-left: 4px solid #3498db; padding: 10px; margin: 8px 0; font-family: monospace; font-size: 13px; }
        .success { border-left-color: #27ae60; color: #27ae60; }
        .error { border-left-color: #e74c3c; color: #e74c3c; background: #fadbd8; }
        .info { border-left-color: #3498db; color: #3498db; }
        .warning { border-left-color: #f39c12; color: #f39c12; }
        .summary { background: #d5f4e6; border: 2px solid #27ae60; padding: 15px; margin: 20px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #bdc3c7; padding: 10px; text-align: left; }
        th { background: #e74c3c; color: white; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
        .btn:hover { background: #2980b9; }
        .group-section { border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px; background: #fafafa; }
        .group-title { font-size: 18px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🌍 Import GLOBAL - Wialon API (Tous les Transporteurs)</h1>
    <p><strong>Resource ID:</strong> 19907460 | <strong>Groups:</strong> 12 transporteurs</p>

    <?php
    // ========================================
    // ETAPE 1: CONNEXION
    // ========================================
    echo '<div class="log info">🔐 Connexion à Wialon...</div>';

    $sid = sid();

    if (!$sid) {
        echo '<div class="log error">❌ Erreur: Impossible de créer une session Wialon</div>';
        exit;
    }

    echo '<div class="log success">✅ Session créée: ' . substr($sid, 0, 20) . '...</div>';

    // ========================================
    // ETAPE 2: IMPORT POUR CHAQUE GROUP
    // ========================================
    echo '<h2>📥 Import des données (7 derniers jours)</h2>';

    global $tab_group;

    $total_km = 0;
    $total_infra = 0;
    $total_eval = 0;
    $stats = array();

    foreach ($tab_group as $nom => $group) {
        echo '<div class="group-section">';
        echo '<div class="group-title">🚚 ' . $nom . '</div>';

        // Kilométrage
        $count_km = processGlobalKilometrage($nom, $group, $sid);
        cleanRepport($sid);
        sleep(1);

        // Infractions
        $count_infra = processGlobalInfractions($nom, $group, $sid);
        cleanRepport($sid);
        sleep(1);

        // Évaluation
        $count_eval = processGlobalEvaluation($nom, $group, $sid);
        cleanRepport($sid);
        sleep(1);

        $total_km += $count_km;
        $total_infra += $count_infra;
        $total_eval += $count_eval;

        $stats[$nom] = array(
            'km' => $count_km,
            'infra' => $count_infra,
            'eval' => $count_eval,
            'total' => $count_km + $count_infra + $count_eval
        );

        echo '</div>';
    }

    $total_all = $total_km + $total_infra + $total_eval;

    // ========================================
    // ETAPE 3: RÉSUMÉ PAR GROUP
    // ========================================
    echo '<h2>📊 Résumé par Transporteur</h2>';

    echo '<table>';
    echo '<tr><th>Transporteur</th><th>KM</th><th>Infractions</th><th>Évaluation</th><th>Total</th></tr>';

    foreach ($stats as $nom => $s) {
        $color = $s['total'] > 0 ? '#27ae60' : '#95a5a6';
        echo '<tr>';
        echo '<td><strong>' . $nom . '</strong></td>';
        echo '<td>' . $s['km'] . '</td>';
        echo '<td>' . $s['infra'] . '</td>';
        echo '<td>' . $s['eval'] . '</td>';
        echo '<td style="color: ' . $color . '; font-weight: bold;">' . $s['total'] . '</td>';
        echo '</tr>';
    }

    echo '</table>';

    // ========================================
    // ETAPE 4: RÉSUMÉ GÉNÉRAL
    // ========================================
    echo '<div class="summary">';
    echo '<h3>🎯 Résumé Général</h3>';
    echo '<table>';
    echo '<tr><th>Type de Rapport</th><th>Enregistrements</th></tr>';
    echo '<tr><td>Kilométrage (tous groups)</td><td><strong>' . $total_km . '</strong></td></tr>';
    echo '<tr><td>Infractions (tous groups)</td><td><strong>' . $total_infra . '</strong></td></tr>';
    echo '<tr><td>Évaluation (tous groups)</td><td><strong>' . $total_eval . '</strong></td></tr>';
    echo '<tr style="background: #e74c3c; color: white;"><td><strong>TOTAL GLOBAL</strong></td><td><strong>' . $total_all . '</strong></td></tr>';
    echo '</table>';
    echo '</div>';

    echo '<p><strong>Fin:</strong> <span style="color: #e74c3c;">' . date('d/m/Y H:i:s') . '</span></p>';
    ?>

    <!-- LINKS -->
    <div style="margin-top: 30px;">
        <h3>🔗 Liens utiles</h3>
        <a href="view_global_km.php" class="btn">📊 Voir Kilométrage (Tous)</a>
        <a href="view_global_infractions.php" class="btn">⚠️ Voir Infractions (Tous)</a>
        <a href="view_global_evaluation.php" class="btn">📈 Voir Évaluation (Tous)</a>
        <a href="index.php" class="btn">🏠 Retour Accueil</a>
    </div>

</div>

</body>
</html>
