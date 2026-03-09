<?php
/**
 * telecharger.php — Multi-Sociétés
 * Interface de téléchargement pour TOUTES les sociétés
 */

require_once 'config.php';
require_once 'lesgets.php';

$manager = new FileManager();
$societies = $manager->getSocieties();
$selectedSociety = $_GET['society'] ?? ($societies[0] ?? null);

$files = $manager->listAllFiles($selectedSociety);
$stats = $manager->getStatistics($selectedSociety);

// Traiter les actions
$message = '';
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['file'])) {
        if ($manager->deleteFile($_POST['file'])) {
            $message = '✅ Fichier supprimé avec succès';
            $files = $manager->listAllFiles($selectedSociety);
            $stats = $manager->getStatistics($selectedSociety);
        } else {
            $message = '❌ Erreur lors de la suppression';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Télécharger les Fichiers - Multi-Sociétés</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { margin-bottom: 40px; }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #e2e8f0; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .back-btn:hover { background: #cbd5e1; }

        /* Sélection société */
        .society-selector { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .society-selector h3 { font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-bottom: 12px; }
        .society-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .society-btn { padding: 10px 16px; border: 2px solid #e2e8f0; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-family: 'DM Sans', sans-serif; transition: all .2s; }
        .society-btn:hover { border-color: #3b82f6; background: #dbeafe; }
        .society-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border-top: 4px solid; }
        .stat-card.blue { border-top-color: #3b82f6; }
        .stat-card.green { border-top-color: #22c55e; }
        .stat-card.amber { border-top-color: #f59e0b; }
        .stat-label { font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: #0f172a; }

        /* Message */
        .message { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .message.error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* Table */
        .card { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 8px 32px rgba(0,0,0,.06); overflow: hidden; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 14px; text-align: left; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
        tbody tr:hover { background: #f8fafc; }
        td { padding: 14px; color: #334155; }
        td:first-child { font-weight: 600; color: #0f172a; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-source { background: #dbeafe; color: #1d4ed8; }
        .badge-generated { background: #f3e8ff; color: #6b21a8; }
        .badge-society { background: #fce7f3; color: #be185d; }

        .actions { display: flex; gap: 8px; }
        .btn-small { padding: 6px 12px; border: none; border-radius: 6px; font-family: 'DM Sans', sans-serif; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .15s; }
        .btn-download { background: #3b82f6; color: #fff; }
        .btn-download:hover { background: #2563eb; }
        .btn-delete { background: #ef4444; color: #fff; }
        .btn-delete:hover { background: #dc2626; }

        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state-icon { font-size: 3rem; margin-bottom: 12px; }

        .footer { text-align: center; margin-top: 40px; font-size: 0.85rem; color: #94a3b8; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Retour Dashboard</a>

    <div class="header">
        <h1>📥 Télécharger les Fichiers</h1>
        <p>Gérez et téléchargez les fichiers de toutes les sociétés</p>
    </div>

    <?php if ($message): ?>
    <div class="message <?= strpos($message, '✅') === 0 ? 'success' : 'error' ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>

    <!-- Sélection Société -->
    <?php if (!empty($societies)): ?>
    <div class="society-selector">
        <h3>🏢 Sélectionner une société</h3>
        <div class="society-buttons">
            <a href="telecharger.php" class="society-btn <?= $selectedSociety === null ? 'active' : '' ?>">
                Toutes les sociétés
            </a>
            <?php foreach ($societies as $s): ?>
            <a href="telecharger.php?society=<?= urlencode($s) ?>" class="society-btn <?= $selectedSociety === $s ? 'active' : '' ?>">
                <?= htmlspecialchars($s) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-label">Fichiers Totaux</div>
            <div class="stat-value"><?= $stats['total_files'] ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Fichiers Source</div>
            <div class="stat-value"><?= $stats['source_files'] ?></div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Fichiers Générés</div>
            <div class="stat-value"><?= $stats['generated_files'] ?></div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label">Taille Totale</div>
            <div class="stat-value" style="font-size: 1.3rem;"><?= $stats['total_size_formatted'] ?></div>
        </div>
    </div>

    <!-- Fichiers -->
    <div class="card">
        <div style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
            <h2 style="font-size: 1.1rem; color: #0f172a; margin-bottom: 0;">
                📁 Fichiers <?= $selectedSociety ? '- ' . htmlspecialchars($selectedSociety) : '(Toutes les sociétés)' ?>
            </h2>
        </div>

        <?php if (empty($files)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <p>Aucun fichier disponible pour le moment</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nom du Fichier</th>
                        <th>Société</th>
                        <th>Type</th>
                        <th>Taille</th>
                        <th>Catégorie</th>
                        <th>Modifié</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?= $file['icon'] ?> <?= htmlspecialchars($file['name']) ?></td>
                        <td><span class="badge badge-society"><?= htmlspecialchars($file['society']) ?></span></td>
                        <td style="text-transform: uppercase; font-weight: 600; color: #64748b;"><?= htmlspecialchars($file['type']) ?></td>
                        <td><?= htmlspecialchars($file['size']) ?></td>
                        <td>
                            <span class="badge <?= $file['category'] === 'Source' ? 'badge-source' : 'badge-generated' ?>">
                                <?= htmlspecialchars($file['category']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($file['modified']) ?></td>
                        <td>
                            <div class="actions">
                                <a href="lesgets.php?action=download&file=<?= urlencode($file['name']) ?>" class="btn-small btn-download">
                                    📥 DL
                                </a>
                                <?php if ($file['category'] === 'Généré'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="file" value="<?= htmlspecialchars($file['name']) ?>">
                                    <button type="submit" class="btn-small btn-delete" onclick="return confirm('Êtes-vous sûr?')">
                                        🗑️ Suppr
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>💾 Gestion centralisée des fichiers Excel pour toutes les sociétés</p>
        <p style="margin-top: 8px; font-size: 0.8rem;">Mise à jour: <?= date('d/m/Y H:i') ?></p>
    </div>
</div>

</body>
</html>