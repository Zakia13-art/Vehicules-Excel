<?php
/**
 * conformite.php — Taux de Conformité
 * Scores et indicateurs par critère
 */

require_once 'config.php';

// Données de conformité
$criteres = [
    [
        'nom' => 'Éco-conduite',
        'score' => 74.5,
        'cible' => 80,
        'trend' => 'up',
        'description' => 'Performance en éco-conduite globale',
    ],
    [
        'nom' => 'Infractions',
        'score' => 68,
        'cible' => 90,
        'trend' => 'down',
        'description' => 'Réduction des infractions relevées',
    ],
    [
        'nom' => 'Kilométrage',
        'score' => 82,
        'cible' => 85,
        'trend' => 'up',
        'description' => 'Optimisation des trajets',
    ],
    [
        'nom' => 'Heures conduite',
        'score' => 71,
        'cible' => 75,
        'trend' => 'stable',
        'description' => 'Respect des heures légales',
    ],
    [
        'nom' => 'Maintenance',
        'score' => 88,
        'cible' => 95,
        'trend' => 'up',
        'description' => 'État technique des véhicules',
    ],
    [
        'nom' => 'Sécurité',
        'score' => 92,
        'cible' => 100,
        'trend' => 'up',
        'description' => 'Respect des règles de sécurité',
    ],
];

// Calcul de la conformité globale
$score_global = round(array_sum(array_column($criteres, 'score')) / count($criteres), 1);
$compliance_level = match(true) {
    $score_global >= 90 => ['Excellent', '#22c55e'],
    $score_global >= 80 => ['Bon', '#3b82f6'],
    $score_global >= 70 => ['Acceptable', '#f59e0b'],
    default => ['À améliorer', '#ef4444']
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taux de Conformité</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { margin-bottom: 40px; }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }
        .card { background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 8px 32px rgba(0,0,0,0.06); margin-bottom: 28px; }
        .global-score { text-align: center; padding: 40px; background: linear-gradient(135deg, #f0f4f8, #e2e8f0); border-radius: 14px; margin-bottom: 28px; }
        .score-circle { width: 150px; height: 150px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; font-weight: 700; color: #fff; }
        .score-label { font-size: 1.2rem; font-weight: 600; color: #0f172a; margin-bottom: 8px; }
        .score-status { font-size: 1rem; padding: 8px 16px; border-radius: 20px; display: inline-block; font-weight: 600; }
        .criteria-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .criteria-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; transition: all 0.2s; }
        .criteria-card:hover { border-color: #3b82f6; box-shadow: 0 4px 12px rgba(59,130,246,0.1); }
        .criteria-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .criteria-name { font-weight: 700; color: #0f172a; font-size: 1.05rem; }
        .criteria-trend { font-size: 1.2rem; }
        .criteria-desc { font-size: 0.8rem; color: #64748b; margin-bottom: 14px; }
        .progress-bar { height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; margin-bottom: 10px; }
        .progress-fill { height: 100%; background: linear-gradient(to right, #3b82f6, #3b82f6); border-radius: 5px; transition: width 0.3s; }
        .score-details { display: flex; justify-content: space-between; font-size: 0.85rem; }
        .score-current { font-weight: 700; color: #0f172a; }
        .score-target { color: #64748b; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #e2e8f0; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .back-btn:hover { background: #cbd5e1; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Retour Dashboard</a>

    <div class="header">
        <h1>✅ Taux de Conformité</h1>
        <p>Scores et indicateurs de performance par critère</p>
    </div>

    <!-- Score Global -->
    <div class="card">
        <div class="global-score">
            <div style="width: 100%;">
                <div class="score-circle" style="background: <?= $compliance_level[1] ?>;">
                    <?= round($score_global) ?>%
                </div>
                <div class="score-label">Score Global de Conformité</div>
                <span class="score-status" style="background: <?= $compliance_level[1] ?>; color: #fff;">
                    <?= $compliance_level[0] ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Critères détaillés -->
    <div class="card">
        <h2 style="margin-bottom: 24px; color: #0f172a; font-size: 1.2rem;">Détail par critère</h2>
        <div class="criteria-grid">
            <?php foreach ($criteres as $critere): ?>
            <div class="criteria-card">
                <div class="criteria-header">
                    <div class="criteria-name"><?= htmlspecialchars($critere['nom']) ?></div>
                    <div class="criteria-trend">
                        <?php
                            echo match($critere['trend']) {
                                'up' => '↗️',
                                'down' => '↘️',
                                'stable' => '→',
                                default => '⚪'
                            };
                        ?>
                    </div>
                </div>

                <div class="criteria-desc">
                    <?= htmlspecialchars($critere['description']) ?>
                </div>

                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $critere['score'] ?>%; background: <?php
                        echo match(true) {
                            $critere['score'] >= 90 => '#22c55e',
                            $critere['score'] >= 80 => '#3b82f6',
                            $critere['score'] >= 70 => '#f59e0b',
                            default => '#ef4444'
                        };
                    ?>;"></div>
                </div>

                <div class="score-details">
                    <span class="score-current"><?= htmlspecialchars($critere['score']) ?>%</span>
                    <span class="score-target">Cible: <?= htmlspecialchars($critere['cible']) ?>%</span>
                </div>

                <!-- Badge de status -->
                <div style="margin-top: 12px;">
                    <?php if ($critere['score'] >= $critere['cible']): ?>
                        <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">✓ En cible</span>
                    <?php else: ?>
                        <span style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">⚠ À améliorer</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recommandations -->
    <div class="card">
        <h2 style="margin-bottom: 16px; color: #0f172a; font-size: 1.2rem;">📋 Recommandations</h2>
        <div style="display: grid; gap: 12px;">
            <div style="padding: 14px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                <div style="font-weight: 600; color: #92400e; margin-bottom: 4px;">Infractions: Réduire de 22%</div>
                <div style="font-size: 0.85rem; color: #78350f;">Mettre en place un programme de formation continue pour les conducteurs</div>
            </div>
            <div style="padding: 14px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                <div style="font-weight: 600; color: #92400e; margin-bottom: 4px;">Heures conduite: Réduire de 4%</div>
                <div style="font-size: 0.85rem; color: #78350f;">Optimiser les horaires et les trajets pour réduire la fatigue des conducteurs</div>
            </div>
            <div style="padding: 14px; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 6px;">
                <div style="font-weight: 600; color: #991b1b; margin-bottom: 4px;">Éco-conduite: Augmenter de 5.5%</div>
                <div style="font-size: 0.85rem; color: #7f1d1d;">Implémenter un système d'incentives et de récompenses pour les meilleurs conducteurs</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>