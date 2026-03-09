<?php
/**
 * alertes.php — Alertes Critiques
 * Notifications et violations détectées
 */

require_once 'config.php';

// Données exemple d'alertes
$alertes = [
    [
        'id' => 1,
        'niveau' => 'critique',
        'vehicule' => 'ABC Car',
        'type' => 'Infractions élevées',
        'description' => 'Ce véhicule a enregistré plus de 15 infractions ce mois',
        'date' => '2024-03-09 14:30',
        'action' => 'Vérifier le conducteur',
    ],
    [
        'id' => 2,
        'niveau' => 'avertissement',
        'vehicule' => 'XYZ Van',
        'type' => 'Note faible',
        'description' => 'Note d\'éco-conduite en baisse (55/100)',
        'date' => '2024-03-09 12:15',
        'action' => 'Former le conducteur',
    ],
    [
        'id' => 3,
        'niveau' => 'info',
        'vehicule' => 'LMN Truck',
        'type' => 'Kilométrage élevé',
        'description' => 'Kilométrage mensuel approche de la limite (4800/5000)',
        'date' => '2024-03-08 10:45',
        'action' => 'Planifier révision',
    ],
    [
        'id' => 4,
        'niveau' => 'critique',
        'vehicule' => 'DEF Bus',
        'type' => 'Heures de conduite excessives',
        'description' => 'Dépassement des heures légales de conduite',
        'date' => '2024-03-07 16:20',
        'action' => 'Suspension immédiate',
    ],
    [
        'id' => 5,
        'niveau' => 'avertissement',
        'vehicule' => 'GHI Car',
        'type' => 'Maintenance requise',
        'description' => 'Révision technique due ce mois',
        'date' => '2024-03-06 09:00',
        'action' => 'Prévoir rendez-vous',
    ],
];

// Compter les alertes par niveau
$count_critique = count(array_filter($alertes, fn($a) => $a['niveau'] === 'critique'));
$count_avertissement = count(array_filter($alertes, fn($a) => $a['niveau'] === 'avertissement'));
$count_info = count(array_filter($alertes, fn($a) => $a['niveau'] === 'info'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes Critiques</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { margin-bottom: 40px; }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }
        .alert-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; border-left: 4px solid; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .stat-card.critique { border-left-color: #ef4444; }
        .stat-card.avertissement { border-left-color: #f59e0b; }
        .stat-card.info { border-left-color: #3b82f6; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #0f172a; line-height: 1; }
        .stat-label { font-size: 0.85rem; color: #64748b; margin-top: 4px; }
        .alert-list { }
        .alert-item { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border-left: 4px solid; }
        .alert-item.critique { border-left-color: #ef4444; background: linear-gradient(to right, rgba(239,68,68,0.05), transparent); }
        .alert-item.avertissement { border-left-color: #f59e0b; background: linear-gradient(to right, rgba(245,158,11,0.05), transparent); }
        .alert-item.info { border-left-color: #3b82f6; background: linear-gradient(to right, rgba(59,130,246,0.05), transparent); }
        .alert-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .alert-icon { font-size: 1.5rem; }
        .alert-title { flex: 1; }
        .alert-title-text { font-weight: 700; color: #0f172a; }
        .alert-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .alert-badge.critique { background: #fee2e2; color: #991b1b; }
        .alert-badge.avertissement { background: #fef3c7; color: #92400e; }
        .alert-badge.info { background: #dbeafe; color: #1d4ed8; }
        .alert-content { margin: 12px 0; }
        .alert-vehicule { font-weight: 600; color: #0f172a; }
        .alert-description { color: #64748b; font-size: 0.9rem; margin: 6px 0; }
        .alert-date { font-size: 0.8rem; color: #94a3b8; }
        .alert-action { display: inline-block; margin-top: 12px; padding: 8px 14px; background: #dbeafe; color: #1d4ed8; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; }
        .alert-action:hover { background: #bfdbfe; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #e2e8f0; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .back-btn:hover { background: #cbd5e1; }
        .filter-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 14px; border: 1px solid #e2e8f0; background: #fff; border-radius: 6px; cursor: pointer; font-family: 'DM Sans', sans-serif; font-weight: 500; transition: all 0.2s; }
        .filter-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state-icon { font-size: 3rem; margin-bottom: 12px; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Retour Dashboard</a>

    <div class="header">
        <h1>🔔 Alertes Critiques</h1>
        <p>Violations détectées et notifications importantes</p>
    </div>

    <!-- Stats -->
    <div class="alert-stats">
        <div class="stat-card critique">
            <div class="stat-value"><?= $count_critique ?></div>
            <div class="stat-label">Alertes critiques</div>
        </div>
        <div class="stat-card avertissement">
            <div class="stat-value"><?= $count_avertissement ?></div>
            <div class="stat-label">Avertissements</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?= $count_info ?></div>
            <div class="stat-label">Informations</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($alertes) ?></div>
            <div class="stat-label">Total alertes</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filter-bar">
        <button class="filter-btn active" onclick="filterAlerts('tous')">Toutes les alertes</button>
        <button class="filter-btn" onclick="filterAlerts('critique')">🔴 Critiques</button>
        <button class="filter-btn" onclick="filterAlerts('avertissement')">🟠 Avertissements</button>
        <button class="filter-btn" onclick="filterAlerts('info')">🔵 Informations</button>
    </div>

    <!-- Liste d'alertes -->
    <div class="alert-list">
        <?php foreach ($alertes as $alerte): ?>
        <div class="alert-item <?= htmlspecialchars($alerte['niveau']) ?>" data-niveau="<?= htmlspecialchars($alerte['niveau']) ?>">
            <div class="alert-header">
                <span class="alert-icon">
                    <?php 
                        echo match($alerte['niveau']) {
                            'critique' => '🔴',
                            'avertissement' => '🟠',
                            'info' => '🔵',
                            default => '⚪'
                        };
                    ?>
                </span>
                <div class="alert-title">
                    <div class="alert-title-text"><?= htmlspecialchars($alerte['type']) ?></div>
                </div>
                <span class="alert-badge <?= htmlspecialchars($alerte['niveau']) ?>">
                    <?= htmlspecialchars(ucfirst($alerte['niveau'])) ?>
                </span>
            </div>

            <div class="alert-content">
                <div class="alert-vehicule">🚗 <?= htmlspecialchars($alerte['vehicule']) ?></div>
                <div class="alert-description"><?= htmlspecialchars($alerte['description']) ?></div>
                <div class="alert-date">⏰ <?= htmlspecialchars($alerte['date']) ?></div>
            </div>

            <button class="alert-action" onclick="actionAlerte(<?= $alerte['id'] ?>, '<?= htmlspecialchars($alerte['action']) ?>')">
                ➜ <?= htmlspecialchars($alerte['action']) ?>
            </button>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    function filterAlerts(level) {
        // Mettre à jour les boutons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        // Filtrer les alertes
        document.querySelectorAll('.alert-item').forEach(item => {
            if (level === 'tous' || item.dataset.niveau === level) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function actionAlerte(id, action) {
        alert('Action: ' + action + ' (Alerte #' + id + ')');
        // Implémentation réelle de l'action
    }
</script>

</body>
</html>