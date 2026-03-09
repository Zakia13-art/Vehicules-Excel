<?php
/**
 * index.php — Dashboard Principal
 * Point d'entrée du projet avec accès à tous les modules
 */

require_once 'config.php';

// Récupérer les stats générales
$data = [
    'total_vehicules' => 48,
    'total_infractions' => 156,
    'conformite' => 74.5,
    'rapports_generes' => 12,
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flotte Transport - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            color: #1e293b;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: #0f172a;
            padding: 24px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 4px 0 12px rgba(0,0,0,0.15);
        }

        .sidebar-logo {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 8px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .sidebar-menu a:hover {
            background: #1e293b;
            color: #fff;
        }

        .sidebar-menu a.active {
            background: #3b82f6;
            color: #fff;
        }

        .sidebar-menu a span {
            font-size: 1.2rem;
        }

        .main-content {
            margin-left: 280px;
            padding: 40px 24px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-size: 2rem;
            color: #0f172a;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: #0f172a;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #1e293b;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        /* Dashboard Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.05);
            border-top: 4px solid #3b82f6;
        }

        .stat-card.red { border-top-color: #ef4444; }
        .stat-card.green { border-top-color: #22c55e; }
        .stat-card.amber { border-top-color: #f59e0b; }

        .stat-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .stat-sub {
            font-size: 0.75rem;
            color: #64748b;
        }

        /* Modules Grid */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .module-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.05);
            border: 2px solid #e2e8f0;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        .module-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 8px 24px rgba(59,130,246,0.15);
            transform: translateY(-2px);
        }

        .module-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
            display: block;
        }

        .module-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .module-desc {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.4;
        }

        .module-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-top: 12px;
            text-transform: uppercase;
        }

        .footer {
            text-align: center;
            padding: 40px 24px;
            color: #94a3b8;
            font-size: 0.85rem;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 24px 12px;
            }

            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <div class="sidebar-logo">
        <span>🚗</span> Flotte Transport
    </div>
    <ul class="sidebar-menu">
        <li><a href="index.php" class="active"><span>📊</span> Dashboard</a></li>
        <li><a href="tableau.php"><span>📋</span> Flotte</a></li>
        <li><a href="synthese.php"><span>📈</span> Synthèse</a></li>
        <li><a href="filtres.php"><span>🔍</span> Filtres</a></li>
        <li><a href="statistiques.php"><span>📉</span> Statistiques</a></li>
        <li><a href="export.php"><span>📥</span> Export</a></li>
        <li><a href="historique.php"><span>⏱️</span> Historique</a></li>
        <li><a href="alertes.php"><span>🔔</span> Alertes</a></li>
    </ul>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="header">
        <div>
            <h1>📊 Dashboard Flotte</h1>
            <p style="color: #64748b; margin-top: 4px;">Bienvenue dans le système de gestion de flotte</p>
        </div>
        <div class="header-actions">
            <a href="synthese.php" class="btn">📄 Générer rapport</a>
            <a href="export.php" class="btn btn-secondary">📥 Exporter</a>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Véhicules</div>
            <div class="stat-value"><?= $data['total_vehicules'] ?></div>
            <div class="stat-sub">Véhicules actifs</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Infractions</div>
            <div class="stat-value"><?= $data['total_infractions'] ?></div>
            <div class="stat-sub">Total relevées</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Conformité</div>
            <div class="stat-value"><?= $data['conformite'] ?>%</div>
            <div class="stat-sub">Taux global</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Rapports</div>
            <div class="stat-value"><?= $data['rapports_generes'] ?></div>
            <div class="stat-sub">Générés ce mois</div>
        </div>
    </div>

    <!-- Modules -->
    <div style="margin-bottom: 40px;">
        <h2 style="font-size: 1.2rem; font-weight: 600; color: #0f172a; margin-bottom: 20px;">Modules disponibles</h2>
    </div>

    <div class="modules-grid">
        <a href="tableau.php" class="module-card">
            <span class="module-icon">🚗</span>
            <div class="module-title">Flotte Transport</div>
            <div class="module-desc">Voir tous les véhicules avec leurs indicateurs</div>
            <span class="module-badge">Principal</span>
        </a>

        <a href="synthese.php" class="module-card">
            <span class="module-icon">📈</span>
            <div class="module-title">Synthèse par Société</div>
            <div class="module-desc">Totaux et statistiques par entreprise</div>
            <span class="module-badge">Reporting</span>
        </a>

        <a href="filtres.php" class="module-card">
            <span class="module-icon">🔍</span>
            <div class="module-title">Filtres Avancés</div>
            <div class="module-desc">Filtrer par date, véhicule, critères</div>
            <span class="module-badge">Nouveau</span>
        </a>

        <a href="statistiques.php" class="module-card">
            <span class="module-icon">📊</span>
            <div class="module-title">Statistiques</div>
            <div class="module-desc">Graphiques, histogrammes, analyses</div>
            <span class="module-badge">Nouveau</span>
        </a>

        <a href="export.php" class="module-card">
            <span class="module-icon">📥</span>
            <div class="module-title">Export Données</div>
            <div class="module-desc">PDF, Excel, CSV avec options</div>
            <span class="module-badge">Nouveau</span>
        </a>

        <a href="historique.php" class="module-card">
            <span class="module-icon">⏱️</span>
            <div class="module-title">Historique</div>
            <div class="module-desc">Archive complète des rapports</div>
            <span class="module-badge">Nouveau</span>
        </a>

        <a href="alertes.php" class="module-card">
            <span class="module-icon">🔔</span>
            <div class="module-title">Alertes Critiques</div>
            <div class="module-desc">Notifications et violations</div>
            <span class="module-badge">Nouveau</span>
        </a>

        <a href="conformite.php" class="module-card">
            <span class="module-icon">✅</span>
            <div class="module-title">Taux de Conformité</div>
            <div class="module-desc">Scores et indicateurs par critère</div>
            <span class="module-badge">Nouveau</span>
        </a>
    </div>

    <div class="footer">
        <p>🚗 Système de Gestion de Flotte Transport — Tous droits réservés © 2024</p>
        <p style="margin-top: 8px; font-size: 0.8rem;">Dernière mise à jour : <?= date('d/m/Y H:i') ?></p>
    </div>
</div>

</body>
</html>