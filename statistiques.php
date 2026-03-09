<?php
/**
 * statistiques.php — Statistiques & Graphiques
 * Analyses et visualisations des données
 */

require_once 'config.php';

// Données exemple pour les graphiques
$stats = [
    'par_note' => [
        'Excellent (80-100)' => 15,
        'Bon (60-79)' => 18,
        'À améliorer (<60)' => 15,
    ],
    'par_infractions' => [
        '0-2' => 12,
        '3-5' => 15,
        '6-10' => 12,
        '>10' => 9,
    ],
    'par_entreprise' => [
        'BOUTCHERAFIN' => 48,
        'Autre Société A' => 32,
        'Autre Société B' => 28,
    ],
    'evolution' => [
        'Jan' => 74,
        'Fév' => 72,
        'Mar' => 75,
        'Avr' => 78,
        'Mai' => 76,
        'Juin' => 79,
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques & Graphiques</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { margin-bottom: 40px; }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 28px; margin-bottom: 28px; }
        .chart-card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 8px 32px rgba(0,0,0,0.06); }
        .chart-card h3 { font-size: 1.1rem; font-weight: 600; color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .chart-container { position: relative; height: 300px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #e2e8f0; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .back-btn:hover { background: #cbd5e1; }
        .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-item { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .stat-label { font-size: 0.8rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: #0f172a; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Retour Dashboard</a>

    <div class="header">
        <h1>📊 Statistiques & Graphiques</h1>
        <p>Analyses visuelles et tendances des données de flotte</p>
    </div>

    <!-- Key Stats -->
    <div class="stat-row">
        <div class="stat-item">
            <div class="stat-label">Conformité Globale</div>
            <div class="stat-value">74.5%</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Véhicules Excellents</div>
            <div class="stat-value">15</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Infractions Totales</div>
            <div class="stat-value">156</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Tendance (6 mois)</div>
            <div class="stat-value" style="color: #22c55e;">↗ +5%</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <!-- Distribution par Note -->
        <div class="chart-card">
            <h3>📈 Distribution par Note</h3>
            <div class="chart-container">
                <canvas id="noteChart"></canvas>
            </div>
        </div>

        <!-- Distribution par Infractions -->
        <div class="chart-card">
            <h3>🚨 Distribution par Infractions</h3>
            <div class="chart-container">
                <canvas id="infractionsChart"></canvas>
            </div>
        </div>

        <!-- Par Entreprise -->
        <div class="chart-card">
            <h3>🏢 Répartition par Entreprise</h3>
            <div class="chart-container">
                <canvas id="entrepriseChart"></canvas>
            </div>
        </div>

        <!-- Évolution Conformité -->
        <div class="chart-card">
            <h3>📉 Évolution Conformité (6 mois)</h3>
            <div class="chart-container">
                <canvas id="evolutionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // Chart.js Setup
    const chartColor = {
        primary: '#3b82f6',
        success: '#22c55e',
        warning: '#f59e0b',
        danger: '#ef4444',
        secondary: '#8b5cf6',
    };

    // Distribution par Note
    new Chart(document.getElementById('noteChart'), {
        type: 'doughnut',
        data: {
            labels: ['Excellent (80-100)', 'Bon (60-79)', 'À améliorer (<60)'],
            datasets: [{
                data: [15, 18, 15],
                backgroundColor: [chartColor.success, chartColor.primary, chartColor.danger],
                borderColor: '#fff',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Distribution par Infractions
    new Chart(document.getElementById('infractionsChart'), {
        type: 'bar',
        data: {
            labels: ['0-2', '3-5', '6-10', '>10'],
            datasets: [{
                label: 'Véhicules',
                data: [12, 15, 12, 9],
                backgroundColor: chartColor.warning,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Par Entreprise
    new Chart(document.getElementById('entrepriseChart'), {
        type: 'pie',
        data: {
            labels: ['BOUTCHERAFIN', 'Autre Société A', 'Autre Société B'],
            datasets: [{
                data: [48, 32, 28],
                backgroundColor: [chartColor.primary, chartColor.secondary, chartColor.warning],
                borderColor: '#fff',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Évolution Conformité
    new Chart(document.getElementById('evolutionChart'), {
        type: 'line',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
            datasets: [{
                label: 'Taux de conformité (%)',
                data: [74, 72, 75, 78, 76, 79],
                borderColor: chartColor.success,
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: chartColor.success,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } },
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
</script>

</body>
</html>