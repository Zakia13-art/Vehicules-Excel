<?php
/**
 * historique.php — Historique & Archive
 * Consultez les rapports générés précédemment
 */

require_once 'config.php';

// Données exemple d'historique
$historique = [
    [
        'id' => 1,
        'date' => '2024-03-09 14:30',
        'type' => 'Rapport Flotte',
        'format' => 'PDF',
        'taille' => '2.4 MB',
        'utilisateur' => 'Admin',
        'status' => 'Complété',
    ],
    [
        'id' => 2,
        'date' => '2024-03-08 10:15',
        'type' => 'Synthèse par Société',
        'format' => 'Excel',
        'taille' => '1.2 MB',
        'utilisateur' => 'Manager',
        'status' => 'Complété',
    ],
    [
        'id' => 3,
        'date' => '2024-03-07 16:45',
        'type' => 'Rapport Détail',
        'format' => 'PDF',
        'taille' => '3.1 MB',
        'utilisateur' => 'Admin',
        'status' => 'Complété',
    ],
    [
        'id' => 4,
        'date' => '2024-03-06 09:20',
        'type' => 'Export Complet',
        'format' => 'CSV',
        'taille' => '0.8 MB',
        'utilisateur' => 'Analyste',
        'status' => 'Complété',
    ],
    [
        'id' => 5,
        'date' => '2024-03-05 13:00',
        'type' => 'Rapport Flotte',
        'format' => 'PDF',
        'taille' => '2.2 MB',
        'utilisateur' => 'Admin',
        'status' => 'Complété',
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Rapports</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { margin-bottom: 40px; }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }
        .card { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 8px 32px rgba(0,0,0,0.06); overflow: hidden; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 14px; text-align: left; font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        tbody td { padding: 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        tbody tr:hover { background: #f8fafc; }
        .rapport-name { font-weight: 600; color: #0f172a; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-pdf { background: #fee2e2; color: #991b1b; }
        .badge-excel { background: #dcfce7; color: #166534; }
        .badge-csv { background: #dbeafe; color: #1d4ed8; }
        .badge-status { background: #d1fae5; color: #065f46; }
        .action-btn { padding: 6px 12px; background: #0f172a; color: #fff; border: none; border-radius: 6px; font-size: 0.8rem; cursor: pointer; margin-right: 6px; font-family: 'DM Sans', sans-serif; }
        .action-btn:hover { background: #1e293b; }
        .action-btn-del { background: #ef4444; }
        .action-btn-del:hover { background: #dc2626; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #e2e8f0; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .back-btn:hover { background: #cbd5e1; }
        .filter-bar { display: flex; gap: 12px; padding: 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; }
        .filter-input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state-icon { font-size: 3rem; margin-bottom: 12px; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Retour Dashboard</a>

    <div class="header">
        <h1>⏱️ Historique des Rapports</h1>
        <p>Consultez l'archive complète des rapports générés</p>
    </div>

    <div class="card">
        <div class="filter-bar">
            <input type="text" class="filter-input" placeholder="🔍 Rechercher par type ou utilisateur..." id="searchInput">
            <select class="filter-input" id="formatFilter">
                <option value="">Tous les formats</option>
                <option value="PDF">PDF</option>
                <option value="Excel">Excel</option>
                <option value="CSV">CSV</option>
            </select>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date & Heure</th>
                        <th>Type de Rapport</th>
                        <th>Format</th>
                        <th>Taille</th>
                        <th>Utilisateur</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($historique as $rapport): ?>
                    <tr class="rapport-row" data-type="<?= htmlspecialchars($rapport['type']) ?>" data-format="<?= htmlspecialchars($rapport['format']) ?>">
                        <td><?= htmlspecialchars($rapport['date']) ?></td>
                        <td class="rapport-name"><?= htmlspecialchars($rapport['type']) ?></td>
                        <td><span class="badge badge-<?= strtolower($rapport['format']) === 'excel' ? 'excel' : strtolower($rapport['format']) ?>"><?= htmlspecialchars($rapport['format']) ?></span></td>
                        <td><?= htmlspecialchars($rapport['taille']) ?></td>
                        <td><?= htmlspecialchars($rapport['utilisateur']) ?></td>
                        <td><span class="badge badge-status"><?= htmlspecialchars($rapport['status']) ?></span></td>
                        <td>
                            <button class="action-btn" onclick="downloadRapport(<?= $rapport['id'] ?>)">📥 Télécharger</button>
                            <button class="action-btn action-btn-del" onclick="deleteRapport(<?= $rapport['id'] ?>)">🗑️ Supprimer</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function downloadRapport(id) {
        alert('Téléchargement du rapport #' + id);
        // Implémentation réelle du téléchargement
    }

    function deleteRapport(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce rapport ?')) {
            alert('Rapport #' + id + ' supprimé');
            // Implémentation réelle de la suppression
        }
    }

    // Filtre de recherche
    document.getElementById('searchInput').addEventListener('input', function() {
        const search = this.value.toLowerCase();
        document.querySelectorAll('.rapport-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? '' : 'none';
        });
    });

    // Filtre par format
    document.getElementById('formatFilter').addEventListener('change', function() {
        const format = this.value;
        document.querySelectorAll('.rapport-row').forEach(row => {
            if (!format || row.dataset.format === format) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

</body>
</html>