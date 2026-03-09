<?php
/**
 * filtres.php — Filtres Avancés
 * Filtrer les données par date, véhicule, entreprise, critères
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$dataDir = 'C:/xampp/htdocs/vehicules/data/entreprises/';
$fileEco = null;
$fileKilo = null;

// Récupérer les fichiers
$files = glob($dataDir . '*.xlsx');
if (!empty($files)) {
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    foreach ($files as $f) {
        if (strpos($f, 'co-conduite') !== false) $fileEco = $f;
        if (strpos($f, 'om') !== false) $fileKilo = $f;
    }
}

$results = [];
$appliedFilters = [];

// Appliquer les filtres
if ($_POST) {
    $filterType = $_POST['filter_type'] ?? 'tous';
    $filterValue = $_POST['filter_value'] ?? '';
    $filterDate = $_POST['filter_date'] ?? '';
    $filterCritere = $_POST['filter_critere'] ?? 'tous';

    $appliedFilters['type'] = $filterType;
    $appliedFilters['value'] = $filterValue;
    $appliedFilters['date'] = $filterDate;
    $appliedFilters['critere'] = $filterCritere;

    if ($fileEco && $fileKilo) {
        try {
            $spreadsheet = IOFactory::load($fileEco);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            foreach ($rows as $row) {
                $reg = trim($row[0] ?? '');
                if ($reg === '' || $reg === 'Regroupement') continue;

                // Appliquer filtres
                if ($filterType === 'vehicule' && stripos($reg, $filterValue) === false) continue;
                if ($filterType === 'note' && !isset($row[2])) continue;

                $results[] = [
                    'vehicule' => $reg,
                    'note' => $row[2] ?? '—',
                    'infractions' => $row[3] ?? 0,
                    'status' => $row[4] ?? 'OK',
                ];
            }
        } catch (\Exception $e) {
            $error = 'Erreur lecture fichier: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filtres Avancés</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { margin-bottom: 40px; }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }
        .card { background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 8px 32px rgba(0,0,0,0.06); margin-bottom: 28px; }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group select { padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .btn { padding: 10px 20px; background: #0f172a; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn:hover { background: #1e293b; }
        .btn-reset { background: #ef4444; }
        .btn-reset:hover { background: #dc2626; }
        .results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .result-card { background: #f8fafc; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; }
        .result-card.alert { border-left-color: #ef4444; }
        .result-vehicle { font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .result-detail { font-size: 0.85rem; color: #64748b; margin: 4px 0; }
        .badge { display: inline-block; padding: 4px 10px; background: #dbeafe; color: #1d4ed8; border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-top: 8px; }
        .no-results { text-align: center; color: #94a3b8; padding: 40px; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #e2e8f0; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .back-btn:hover { background: #cbd5e1; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Retour Dashboard</a>

    <div class="header">
        <h1>🔍 Filtres Avancés</h1>
        <p>Filtrez les données par date, véhicule, entreprise ou critères spécifiques</p>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px; color: #0f172a;">Paramètres de filtre</h2>
        <form method="POST">
            <div class="filter-form">
                <div class="form-group">
                    <label>Type de filtre</label>
                    <select name="filter_type" required>
                        <option value="tous">Tous les véhicules</option>
                        <option value="vehicule">Véhicule spécifique</option>
                        <option value="note">Par note</option>
                        <option value="infractions">Par infractions</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Valeur</label>
                    <input type="text" name="filter_value" placeholder="Ex: ABC, 80, 5...">
                </div>

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="filter_date">
                </div>

                <div class="form-group">
                    <label>Critère</label>
                    <select name="filter_critere">
                        <option value="tous">Tous</option>
                        <option value="note">Note éco-conduite</option>
                        <option value="infractions">Infractions</option>
                        <option value="kilométrage">Kilométrage</option>
                        <option value="heures">Heures conduite</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn">🔍 Appliquer filtres</button>
                <button type="reset" class="btn btn-reset">✕ Réinitialiser</button>
            </div>
        </form>
    </div>

    <?php if (!empty($appliedFilters)): ?>
    <div class="card" style="background: #f0f4f8; border-top: 3px solid #3b82f6;">
        <h3 style="margin-bottom: 12px; color: #0f172a;">Filtres appliqués</h3>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <?php foreach ($appliedFilters as $key => $value): ?>
                <?php if ($value): ?>
                <span style="background: #dbeafe; color: #1d4ed8; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                    <?= htmlspecialchars(ucfirst($key) . ': ' . $value) ?>
                </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
    <div class="card">
        <h2 style="margin-bottom: 20px; color: #0f172a;">
            Résultats (<?= count($results) ?> véhicule(s))
        </h2>
        <div class="results-grid">
            <?php foreach ($results as $result): ?>
            <div class="result-card <?= $result['infractions'] > 5 ? 'alert' : '' ?>">
                <div class="result-vehicle">🚗 <?= htmlspecialchars($result['vehicule']) ?></div>
                <div class="result-detail">Note: <strong><?= htmlspecialchars($result['note']) ?>/100</strong></div>
                <div class="result-detail">Infractions: <strong><?= htmlspecialchars($result['infractions']) ?></strong></div>
                <div class="result-detail">Statut: <strong><?= htmlspecialchars($result['status']) ?></strong></div>
                <span class="badge">
                    <?= $result['infractions'] > 5 ? '⚠️ À surveiller' : '✅ Bon' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($_POST): ?>
    <div class="card">
        <div class="no-results">
            <p style="font-size: 1.2rem; margin-bottom: 8px;">Aucun résultat trouvé</p>
            <p style="font-size: 0.9rem;">Essayez d'ajuster vos critères de filtre</p>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>