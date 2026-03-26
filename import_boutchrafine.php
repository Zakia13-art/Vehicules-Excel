<?php
/**
 * ========================================
 * IMPORT BOUTCHRAFINE - MAIN FILE
 * ========================================
 * Exécuter l'import des 3 rapports Wialon
 */

require_once "api_boutchrafine.php";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Import BOUTCHRAFINE - Wialon</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 20px; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .log { background: #f8f9fa; border-left: 4px solid #3498db; padding: 10px; margin: 8px 0; font-family: monospace; font-size: 13px; }
        .success { border-left-color: #27ae60; color: #27ae60; }
        .error { border-left-color: #e74c3c; color: #e74c3c; background: #fadbd8; }
        .info { border-left-color: #3498db; color: #3498db; }
        .summary { background: #d5f4e6; border: 2px solid #27ae60; padding: 15px; margin: 20px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #bdc3c7; padding: 10px; text-align: left; }
        th { background: #3498db; color: white; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚚 Import BOUTCHRAFINE - Wialon API</h1>
    <p><strong>Group ID:</strong> 19022033 | <strong>Resource ID:</strong> 19907460</p>

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
    // ETAPE 2: IMPORT DES DONNÉES
    // ========================================
    echo '<h2>📥 Import des données (7 derniers jours)</h2>';

    $total_km = processKilometrage($sid);
    cleanRepport($sid);
    sleep(1);

    $total_infra = processInfractions($sid);
    cleanRepport($sid);
    sleep(1);

    $total_eval = processEvaluation($sid);
    cleanRepport($sid);

    $total_all = $total_km + $total_infra + $total_eval;

    // ========================================
    // ETAPE 3: RÉSUMÉ
    // ========================================
    echo '<div class="summary">';
    echo '<h3>📊 Résumé de l\'import</h3>';
    echo '<table>';
    echo '<tr><th>Rapport</th><th>Enregistrements</th></tr>';
    echo '<tr><td>Kilométrage</td><td><strong>' . $total_km . '</strong></td></tr>';
    echo '<tr><td>Infractions</td><td><strong>' . $total_infra . '</strong></td></tr>';
    echo '<tr><td>Évaluation</td><td><strong>' . $total_eval . '</strong></td></tr>';
    echo '<tr style="background: #27ae60; color: white;"><td><strong>TOTAL</strong></td><td><strong>' . $total_all . '</strong></td></tr>';
    echo '</table>';
    echo '</div>';

    echo '<p><strong>Fin:</strong> <span style="color: #e74c3c;">' . date('d/m/Y H:i:s') . '</span></p>';
    ?>

    <!-- LINKS -->
    <div style="margin-top: 30px;">
        <h3>🔗 Liens utiles</h3>
        <a href="view_boutchrafine_km.php" class="btn">📊 Voir Kilométrage</a>
        <a href="view_boutchrafine_infractions.php" class="btn">⚠️ Voir Infractions</a>
        <a href="view_boutchrafine_evaluation.php" class="btn">📈 Voir Évaluation</a>
        <a href="index.php" class="btn">🏠 Retour Accueil</a>
    </div>

</div>

</body>
</html>
