<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$dataDir = 'C:/xampp/htdocs/vehicules/data/entreprises/';
$filesEco = glob($dataDir . '*co-conduite*.xlsx');
$filesEco = array_filter($filesEco, fn($f) => strpos(basename($f), '~$') === false);
$filesKilo = glob($dataDir . '*Kilom*.xlsx');
$fileKilo = !empty($filesKilo) ? $filesKilo[0] : null;
$error = '';
if (empty($filesEco) || !$fileKilo) {
    $error = '❌ Fichiers manquants';
}

function readExcelBySheetName(string $filePath, string $searchName): array {
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (stripos($name, $searchName) !== false) {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }
        if ($sheet === null) $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) return [];
        $headers = array_map(fn($h) => trim((string)($h ?? '')), array_shift($rows));
        $data = [];
        foreach ($rows as $row) {
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) continue;
            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = trim((string)($row[$i] ?? ''));
            }
            $data[] = $assoc;
        }
        return $data;
    } catch (\Exception $e) {
        return [];
    }
}

function parseDureeToHours(string $duree): float {
    $h = 0.0;
    if (preg_match('/(\d+)\s*jours?\s*/i', $duree, $m)) {
        $h += (float)$m[1] * 24;
        $duree = preg_replace('/\d+\s*jours?\s*/i', '', $duree);
    }
    if (preg_match('/(\d+):(\d+):(\d+)/', $duree, $m)) {
        $h += (float)$m[1] + (float)$m[2]/60 + (float)$m[3]/3600;
    } elseif (preg_match('/(\d+):(\d+)/', $duree, $m)) {
        $h += (float)$m[1] + (float)$m[2]/60;
    }
    return round($h, 1);
}

// Récupérer les données
$infractionData = [];
foreach ($filesEco as $file) {
    if (stripos($file, 'infraction') !== false) {
        $infractionData = array_merge($infractionData, readExcelBySheetName($file, 'rapport'));
    }
}
$kiloData = readExcelBySheetName($fileKilo, 'Kilométrage');

$normalize = function($str) {
    return trim(preg_replace('/\s+/', ' ', $str));
};

// Récupérer les véhicules uniques (depuis la colonne "Regroupement" qui contient les codes comme 13429/A/25)
$vehicules = [];
foreach ($kiloData as $row) {
    $vehicule = $row['Regroupement'] ?? '';
    if ($vehicule !== '' && !in_array($vehicule, $vehicules)) {
        $vehicules[] = $vehicule;
    }
}
sort($vehicules);

// Construction des données
$allData = [];
foreach ($kiloData as $kRow) {
    $regNorm = $normalize($kRow['Regroupement'] ?? '');
    
    // Chercher l'infraction correspondante
    $infrData = null;
    foreach ($infractionData as $iRow) {
        if ($normalize($iRow['Regroupement'] ?? '') === $regNorm && 
            $normalize($iRow['Début'] ?? '') === $normalize($kRow['Début'] ?? '')) {
            $infrData = $iRow;
            break;
        }
    }
    
    $allData[] = [
        'transporteur' => 'BOUTCHERAFIN',
        'chauffeur' => '',
        'vehicule' => $kRow['Regroupement'] ?? '',
        'trajet' => $infrData['Emplacement initial'] ?? '',
        'depart' => '',
        'vers' => '',
        'debut' => $kRow['Début'] ?? '',
        'fin' => $kRow['Fin'] ?? '',
        'penalite' => '',
        'kilometrage' => $kRow['Kilométrage'] ?? '',
        'etat' => 'Activé',
    ];
}

// Récupérer les filtres
$filterTransporteur = isset($_POST['transporteur']) ? $_POST['transporteur'] : 'Tous';
$filterChauffeur = isset($_POST['chauffeur']) ? $_POST['chauffeur'] : 'Tous';
$filterVehicule = isset($_POST['vehicule']) ? $_POST['vehicule'] : 'Tous';
$filterDu = $_POST['du'] ?? '';
$filterAu = $_POST['au'] ?? '';
$searchQuery = $_POST['search'] ?? '';
$itemsPerPage = intval($_POST['items_per_page'] ?? 10);
$currentPage = intval($_POST['page'] ?? 1);

// Filtrer les données
$filteredData = $allData;

if ($filterTransporteur !== 'Tous') {
    $filteredData = array_filter($filteredData, fn($row) => $row['transporteur'] === $filterTransporteur);
}

if ($filterChauffeur !== 'Tous') {
    if ($filterChauffeur === 'flot') {
        $filteredData = array_filter($filteredData, fn($row) => strpos(strtolower($row['chauffeur']), 'flot') !== false);
    } elseif ($filterChauffeur === 'non') {
        $filteredData = array_filter($filteredData, fn($row) => strpos(strtolower($row['chauffeur']), 'flot') === false);
    }
}

if ($filterVehicule !== 'Tous') {
    $filteredData = array_filter($filteredData, fn($row) => $row['vehicule'] === $filterVehicule);
}

if (!empty($filterDu)) {
    $duDate = DateTime::createFromFormat('Y-m-d', $filterDu);
    if ($duDate) {
        $filteredData = array_filter($filteredData, function($row) use ($duDate) {
            $rowDate = DateTime::createFromFormat('d/m/Y', explode(' ', $row['debut'])[0]);
            return $rowDate && $rowDate >= $duDate;
        });
    }
}

if (!empty($filterAu)) {
    $auDate = DateTime::createFromFormat('Y-m-d', $filterAu);
    if ($auDate) {
        $filteredData = array_filter($filteredData, function($row) use ($auDate) {
            $rowDate = DateTime::createFromFormat('d/m/Y', explode(' ', $row['fin'])[0]);
            return $rowDate && $rowDate <= $auDate;
        });
    }
}

if (!empty($searchQuery)) {
    $query = strtolower($searchQuery);
    $filteredData = array_filter($filteredData, function($row) use ($query) {
        return strpos(strtolower($row['transporteur']), $query) !== false ||
               strpos(strtolower($row['chauffeur']), $query) !== false ||
               strpos(strtolower($row['vehicule']), $query) !== false ||
               strpos(strtolower($row['trajet']), $query) !== false ||
               strpos(strtolower($row['kilometrage']), $query) !== false;
    });
}

$filteredData = array_values($filteredData);

// Pagination
$totalItems = count($filteredData);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = max(1, min($currentPage, $totalPages));
$startIndex = ($currentPage - 1) * $itemsPerPage;
$pageData = array_slice($filteredData, $startIndex, $itemsPerPage);

function dot(string $status): string {
    $colors = ['vert' => '#22c55e', 'orange' => '#f97316', 'rouge' => '#ef4444'];
    return '<span class="dot" style="background:' . ($colors[$status] ?? '#d1d5db') . '"></span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Flotte Transport</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .page-header { max-width: 1400px; margin: 0 auto 28px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; color: #0f172a; margin-bottom: 20px; }
        .filters-card { max-width: 1400px; margin: 0 auto 24px; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06); }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: flex-end; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-group select, .form-group input[type="date"] { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; font-family: 'DM Sans', sans-serif; }
        .form-group select:focus, .form-group input[type="date"]:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .form-group select option { background: #fff; color: #334155; padding: 8px; }
        .form-group select option:checked { background: #2563eb; color: #fff; }
        .btn-validate { background: #06b6d4; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-validate:hover { background: #0891b2; }
        .pagination-section { max-width: 1400px; margin: 0 auto 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .pagination-section select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; }
        .search-box { flex: 1; min-width: 200px; }
        .search-box input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; }
        .data-card { max-width: 1400px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        thead th { padding: 12px 14px; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 11px 14px; font-size: 0.84rem; color: #334155; vertical-align: middle; }
        .nav-buttons { max-width: 1400px; margin: 0 auto 20px; display: flex; gap: 12px; }
        .nav-btn { background: #0f172a; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .nav-btn:hover { background: #1e293b; }
        .no-data { text-align: center; padding: 40px; color: #94a3b8; }
        .pagination-info { font-size: 0.85rem; color: #64748b; }
        input[type="date"] { cursor: pointer; }
        input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; }
    </style>
</head>
<body>

<div class="page-header">
    <h1>Rapport Global</h1>
    <div class="nav-buttons">
        <a href="tableau.php" class="nav-btn">🚗 Retour Flotte</a>
        <a href="synthese.php" class="nav-btn"> Voir Rapport Par Société</a>
    </div>
</div>

<div class="filters-card">
    <form method="POST">
        <div class="filters-grid">
            <div class="form-group">
                <label for="transporteur">Transporteur</label>
                <select id="transporteur" name="transporteur">
                    <option value="Tous" <?= $filterTransporteur === 'Tous' ? 'selected' : '' ?>>Tous</option>
                    <option value="BOUTCHERAFIN" <?= $filterTransporteur === 'BOUTCHERAFIN' ? 'selected' : '' ?>>BOUTCHERAFIN</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="chauffeur">Chauffeur</label>
                <select id="chauffeur" name="chauffeur">
                    <option value="Tous" <?= $filterChauffeur === 'Tous' ? 'selected' : '' ?>>Tous</option>
                    <option value="flot" <?= $filterChauffeur === 'flot' ? 'selected' : '' ?>>Flot</option>
                    <option value="non" <?= $filterChauffeur === 'non' ? 'selected' : '' ?>>Non</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="vehicule">Véhicule</label>
                <select id="vehicule" name="vehicule">
                    <option value="Tous">Tous</option>
                    <?php foreach ($vehicules as $v): ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= $filterVehicule === $v ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="du">Du</label>
                <input type="date" id="du" name="du" value="<?= htmlspecialchars($filterDu) ?>">
            </div>
            
            <div class="form-group">
                <label for="au">Au</label>
                <input type="date" id="au" name="au" value="<?= htmlspecialchars($filterAu) ?>">
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn-validate">valider</button>
            </div>
        </div>
    </form>
</div>

<div class="pagination-section">
    <form method="POST" style="display: flex; align-items: center; gap: 8px;">
        <select name="items_per_page" onchange="this.form.submit()">
            <option value="10" <?= $itemsPerPage === 10 ? 'selected' : '' ?>>10</option>
            <option value="25" <?= $itemsPerPage === 25 ? 'selected' : '' ?>>25</option>
            <option value="50" <?= $itemsPerPage === 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $itemsPerPage === 100 ? 'selected' : '' ?>>100</option>
        </select>
        <span>Ligne par page</span>
        <input type="hidden" name="transporteur" value="<?= htmlspecialchars($filterTransporteur) ?>">
        <input type="hidden" name="chauffeur" value="<?= htmlspecialchars($filterChauffeur) ?>">
        <input type="hidden" name="vehicule" value="<?= htmlspecialchars($filterVehicule) ?>">
        <input type="hidden" name="du" value="<?= htmlspecialchars($filterDu) ?>">
        <input type="hidden" name="au" value="<?= htmlspecialchars($filterAu) ?>">
        <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
    </form>
    
    <form method="POST" class="search-box">
        <input type="text" name="search" placeholder="Recherche:" value="<?= htmlspecialchars($searchQuery) ?>" onkeyup="this.form.submit()">
        <input type="hidden" name="transporteur" value="<?= htmlspecialchars($filterTransporteur) ?>">
        <input type="hidden" name="chauffeur" value="<?= htmlspecialchars($filterChauffeur) ?>">
        <input type="hidden" name="vehicule" value="<?= htmlspecialchars($filterVehicule) ?>">
        <input type="hidden" name="du" value="<?= htmlspecialchars($filterDu) ?>">
        <input type="hidden" name="au" value="<?= htmlspecialchars($filterAu) ?>">
        <input type="hidden" name="items_per_page" value="<?= $itemsPerPage ?>">
    </form>
</div>

<div class="data-card">
    <?php if (!empty($pageData)): ?>
    <table>
        <thead>
            <tr>
                <th>Transporteur</th>
                <th>Chauffeur</th>
                <th>Véhicule</th>
                <th>Trajet</th>
                <th>Départ</th>
                <th>Vers</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Pénalité</th>
                <th>Kilométrage</th>
                <th>État</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pageData as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['transporteur']) ?></td>
                <td><?= htmlspecialchars($row['chauffeur']) ?></td>
                <td><?= htmlspecialchars($row['vehicule']) ?></td>
                <td><?= htmlspecialchars($row['trajet']) ?></td>
                <td><?= htmlspecialchars($row['depart']) ?></td>
                <td><?= htmlspecialchars($row['vers']) ?></td>
                <td><?= htmlspecialchars($row['debut']) ?></td>
                <td><?= htmlspecialchars($row['fin']) ?></td>
                <td><?= htmlspecialchars($row['penalite']) ?></td>
                <td><?= htmlspecialchars($row['kilometrage']) ?></td>
                <td><?= htmlspecialchars($row['etat']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="padding: 16px 14px; text-align: center; color: #94a3b8; font-size: 0.85rem;">
        Affichage de <?= $startIndex + 1 ?> à <?= min($startIndex + $itemsPerPage, $totalItems) ?> sur <?= $totalItems ?> ligne(s)
    </div>
    <?php else: ?>
    <div class="no-data">
        <p>Aucune donnée trouvée avec les critères sélectionnés.</p>
    </div>
    <?php endif; ?>
</div>

</body>
</html>