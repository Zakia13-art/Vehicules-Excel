<?php
require_once("db.php");

$allData = [];

try {
    $db = Cnx();
    $result = $db->query("
        SELECT t.*, tt.name as transporteur_name, c.name as chauffeur_name
        FROM trajets t
        LEFT JOIN transporteurs tt ON t.transporteur = tt.id
        LEFT JOIN chauffeurs c ON t.chauffeur = c.matricule
        ORDER BY t.debut DESC
    ");
    
    $trajets = $result->fetchAll(PDO::FETCH_ASSOC);
    
    // Compteur de nouveaux trajets (5 dernières minutes)
    $five_min_ago = time() - (5 * 60);
    $newResult = $db->query("SELECT COUNT(*) as count FROM trajets WHERE debut > " . $five_min_ago);
    $new_count = $newResult->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    foreach ($trajets as $row) {
        $debut_display = is_numeric($row['debut']) ? date('d/m/Y H:i', (int)$row['debut']) : $row['debut'];
        $fin_display = is_numeric($row['fin']) ? date('d/m/Y H:i', (int)$row['fin']) : $row['fin'];
        
        $allData[] = [
            'transporteur' => $row['transporteur_name'] ?? 'ID: ' . $row['transporteur'],
            'chauffeur' => $row['chauffeur_name'] ?? $row['chauffeur'] ?? '',
            'vehicule' => $row['vehicule'] ?? '',
            'trajet' => $row['parcour'] ?? '',
            'depart' => $row['depart'] ?? '',
            'vers' => $row['vers'] ?? '',
            'debut_ts' => $row['debut'] ?? '',
            'fin_ts' => $row['fin'] ?? '',
            'debut' => $debut_display,
            'fin' => $fin_display,
            'penalite' => $row['penalite'] ?? '',
            'kilometrage' => $row['kilometrage'] ?? '',
            'etat' => 'Wialon',
        ];
    }
} catch (Exception $e) {
    $allData = [];
    $new_count = 0;
}

// Récupérer listes uniques
$transporteurs = [];
$chauffeurs = [];
$vehicules = [];

foreach ($allData as $row) {
    if (!in_array($row['transporteur'], $transporteurs)) {
        $transporteurs[] = $row['transporteur'];
    }
    if ($row['chauffeur'] !== '' && !in_array($row['chauffeur'], $chauffeurs)) {
        $chauffeurs[] = $row['chauffeur'];
    }
    if ($row['vehicule'] !== '' && !in_array($row['vehicule'], $vehicules)) {
        $vehicules[] = $row['vehicule'];
    }
}
sort($transporteurs);
sort($chauffeurs);
sort($vehicules);

// Récupérer les filtres
$filterTransporteur = isset($_POST['transporteur']) ? $_POST['transporteur'] : 'Tous';
$filterChauffeur = isset($_POST['chauffeur']) ? $_POST['chauffeur'] : 'Tous';
$filterVehicule = isset($_POST['vehicule']) ? $_POST['vehicule'] : 'Tous';
$filterDu = $_POST['du'] ?? '';
$filterAu = $_POST['au'] ?? '';
$searchQuery = $_POST['search'] ?? '';
$itemsPerPage = intval($_POST['items_per_page'] ?? 10);

// Appliquer les filtres
$filteredData = $allData;

if ($filterTransporteur !== 'Tous') {
    $filteredData = array_filter($filteredData, fn($row) => $row['transporteur'] === $filterTransporteur);
}

if ($filterChauffeur !== 'Tous') {
    $filteredData = array_filter($filteredData, fn($row) => $row['chauffeur'] === $filterChauffeur);
}

if ($filterVehicule !== 'Tous') {
    $filteredData = array_filter($filteredData, fn($row) => $row['vehicule'] === $filterVehicule);
}

// Filtre par dates
if (!empty($filterDu)) {
    $duTime = strtotime($filterDu);
    $filteredData = array_filter($filteredData, function($row) use ($duTime) {
        return (int)$row['debut_ts'] >= $duTime;
    });
}

if (!empty($filterAu)) {
    $auTime = strtotime($filterAu) + 86400;
    $filteredData = array_filter($filteredData, function($row) use ($auTime) {
        return (int)$row['fin_ts'] <= $auTime;
    });
}

// Filtre par recherche
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
$totalItems = count($filteredData);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = max(1, min(intval($_POST['page'] ?? 1), $totalPages));
$startIndex = ($currentPage - 1) * $itemsPerPage;
$pageData = array_slice($filteredData, $startIndex, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports Wialon</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .page-header { max-width: 1400px; margin: 0 auto 28px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; color: #0f172a; margin-bottom: 20px; }
        .filters-card { max-width: 1400px; margin: 0 auto 24px; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-group select, .form-group input[type="date"], .form-group input[type="text"] { 
            padding: 10px 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-size: 0.9rem; 
            font-family: 'DM Sans', sans-serif;
        }
        .form-group select:focus, .form-group input:focus { 
            outline: none; 
            border-color: #06b6d4; 
            box-shadow: 0 0 0 3px rgba(6,182,212,.1); 
        }
        .btn-validate { 
            background: #06b6d4; 
            color: #fff; 
            border: none; 
            padding: 10px 24px; 
            border-radius: 8px; 
            font-size: 0.875rem;
            font-weight: 600; 
            cursor: pointer; 
            transition: background 0.2s;
            align-self: flex-end;
        }
        .btn-validate:hover { background: #0891b2; }
        .pagination-section { max-width: 1400px; margin: 0 auto 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .pagination-section select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; }
        .search-box { flex: 1; min-width: 300px; }
        .search-box input { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-size: 0.95rem;
            font-family: 'DM Sans', sans-serif;
        }
        .search-box input:focus { 
            outline: none; 
            border-color: #06b6d4; 
            box-shadow: 0 0 0 3px rgba(6,182,212,.1); 
        }
        .data-card { max-width: 1400px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.07); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        th { padding: 12px; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.84rem; }
        tbody tr:hover { background: #f8fafc; }
        .nav-buttons { max-width: 1400px; margin: 0 auto 20px; display: flex; gap: 12px; }
        .nav-btn { background: #0f172a; color: #fff; padding: 10px 20px; border: none; border-radius: 8px; text-decoration: none; cursor: pointer; font-size: 0.875rem; }
        .nav-btn:hover { background: #1e293b; }
        .no-data { text-align: center; padding: 40px; color: #94a3b8; }
        .sync-info { background: #f0fdf4; border-top: 1px solid #bbf7d0; padding: 12px 16px; text-align: center; color: #166534; font-size: 0.85rem; }
        .pagination-info { text-align: center; padding: 16px; color: #94a3b8; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="page-header">
    <h1>📡 Rapport Wialon</h1>
    <div class="nav-buttons">
        <a href="check_trajets_final_v2.php" class="nav-btn">✅ Vérifier données</a>
        <a href="sync_manager.php" class="nav-btn">⚙️ Synchronisation</a>
    </div>
</div>

<div class="filters-card">
    <form method="POST">
        <div class="filters-grid">
            <div class="form-group">
                <label>Transporteur</label>
                <select name="transporteur">
                    <option value="Tous" <?= $filterTransporteur === 'Tous' ? 'selected' : '' ?>>Tous</option>
                    <?php foreach ($transporteurs as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $filterTransporteur === $t ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Chauffeur</label>
                <select name="chauffeur">
                    <option value="Tous" <?= $filterChauffeur === 'Tous' ? 'selected' : '' ?>>Tous</option>
                    <?php foreach ($chauffeurs as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $filterChauffeur === $c ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Véhicule</label>
                <select name="vehicule">
                    <option value="Tous" <?= $filterVehicule === 'Tous' ? 'selected' : '' ?>>Tous</option>
                    <?php foreach ($vehicules as $v): ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= $filterVehicule === $v ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Du</label>
                <input type="date" name="du" value="<?= htmlspecialchars($filterDu) ?>">
            </div>
            
            <div class="form-group">
                <label>Au</label>
                <input type="date" name="au" value="<?= htmlspecialchars($filterAu) ?>">
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
        <span>Lignes par page</span>
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
                <td><?= $row['debut'] ?></td>
                <td><?= $row['fin'] ?></td>
                <td><?= htmlspecialchars($row['penalite']) ?></td>
                <td><?= htmlspecialchars($row['kilometrage']) ?> km</td>
                <td><?= htmlspecialchars($row['etat']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="sync-info">
        🆕 Nouveaux: <?= $new_count ?> (dans les 5 dernières minutes)
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination-info">
        Page <?= $currentPage ?> sur <?= $totalPages ?>
        <?php if ($currentPage > 1): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="transporteur" value="<?= htmlspecialchars($filterTransporteur) ?>">
                <input type="hidden" name="chauffeur" value="<?= htmlspecialchars($filterChauffeur) ?>">
                <input type="hidden" name="vehicule" value="<?= htmlspecialchars($filterVehicule) ?>">
                <input type="hidden" name="du" value="<?= htmlspecialchars($filterDu) ?>">
                <input type="hidden" name="au" value="<?= htmlspecialchars($filterAu) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                <input type="hidden" name="items_per_page" value="<?= $itemsPerPage ?>">
                <input type="hidden" name="page" value="<?= $currentPage - 1 ?>">
                <button type="submit" style="background: none; border: none; color: #06b6d4; cursor: pointer; text-decoration: underline; margin-left: 16px;">← Précédente</button>
            </form>
        <?php endif; ?>
        
        <?php if ($currentPage < $totalPages): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="transporteur" value="<?= htmlspecialchars($filterTransporteur) ?>">
                <input type="hidden" name="chauffeur" value="<?= htmlspecialchars($filterChauffeur) ?>">
                <input type="hidden" name="vehicule" value="<?= htmlspecialchars($filterVehicule) ?>">
                <input type="hidden" name="du" value="<?= htmlspecialchars($filterDu) ?>">
                <input type="hidden" name="au" value="<?= htmlspecialchars($filterAu) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                <input type="hidden" name="items_per_page" value="<?= $itemsPerPage ?>">
                <input type="hidden" name="page" value="<?= $currentPage + 1 ?>">
                <button type="submit" style="background: none; border: none; color: #06b6d4; cursor: pointer; text-decoration: underline; margin-left: 16px;">Suivante →</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="no-data">
        <p>Aucune donnée trouvée avec les critères sélectionnés.</p>
    </div>
    <?php endif; ?>
</div>

</body>
</html>