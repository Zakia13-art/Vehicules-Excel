<?php
require_once 'config.php';

// Récupérer les paramètres de recherche et tri
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$colonne_tri = isset($_GET['tri']) ? $_GET['tri'] : '';
$ordre_tri = isset($_GET['ordre']) ? $_GET['ordre'] : 'ASC';

// Récupérer les données des deux tables et les fusionner
function getDonnees($pdo, $recherche = '', $colonne_tri = '', $ordre_tri = 'ASC') {
    try {
        // Table 1: kilométrage+heures_moteur
        $query1 = "SELECT `Regroupement`, `Durée`, `Kilométrage` FROM `kilométrage+heures_moteur`";
        
        // Table 2: éco-conduite - récupérer toutes les données
        $query2 = "SELECT `Regroupement`, `Évaluation`, `Infraction`, `Valeur`, `Pénalités` FROM `éco-conduite`";
        
        $stmt1 = $pdo->prepare($query1);
        $stmt1->execute();
        $kilo_data = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt2 = $pdo->prepare($query2);
        $stmt2->execute();
        $eco_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        // Créer un mapping par Regroupement (garder la première occurrence)
        $eco_map = [];
        foreach ($eco_data as $row) {
            if (!isset($eco_map[$row['Regroupement']])) {
                $eco_map[$row['Regroupement']] = $row;
            }
        }
        
        // Fusionner les données
        $donnees = [];
        foreach ($kilo_data as $row) {
            $regroupement = $row['Regroupement'];
            $eco_info = $eco_map[$regroupement] ?? [];
            
            // Ne garder que les lignes qui ont une Évaluation valide
            if (isset($eco_info['Évaluation']) && !empty($eco_info['Évaluation']) && $eco_info['Évaluation'] !== 'N/A') {
                $donnees[] = [
                    'entreprise' => 'BOUTCHERAFIN',
                    'vehicule' => $regroupement,
                    'evaluation' => $eco_info['Évaluation'],
                    'infraction' => $eco_info['Infraction'] ?? '0',
                    'duree' => $row['Durée'] ?? '0',
                    'kilometrage' => $row['Kilométrage'] ?? '0'
                ];
            }
        }
        
        // Appliquer la recherche
        if (!empty($recherche)) {
            $donnees = array_filter($donnees, function($row) use ($recherche) {
                return stripos($row['vehicule'], $recherche) !== false ||
                       stripos($row['entreprise'], $recherche) !== false ||
                       stripos((string)$row['note_conduite'], $recherche) !== false ||
                       stripos((string)$row['alertes_critiques'], $recherche) !== false ||
                       stripos((string)$row['kilometrage'], $recherche) !== false;
            });
        }
        
        // Appliquer le tri
        if (!empty($colonne_tri)) {
            usort($donnees, function($a, $b) use ($colonne_tri, $ordre_tri) {
                $val_a = $a[$colonne_tri] ?? '';
                $val_b = $b[$colonne_tri] ?? '';
                
                // Tri numérique pour les colonnes numériques
                if (is_numeric($val_a) && is_numeric($val_b)) {
                    $cmp = $val_a <=> $val_b;
                } else {
                    $cmp = strcasecmp((string)$val_a, (string)$val_b);
                }
                
                return $ordre_tri === 'DESC' ? -$cmp : $cmp;
            });
        }
        
        return $donnees;
    } catch (PDOException $e) {
        return [];
    }
}

// Récupérer les données
$pdo = getDB();
$donnees = getDonnees($pdo, $recherche, $colonne_tri, $ordre_tri);

// Les colonnes à afficher
$colonnes = [
    'entreprise' => 'Entreprise',
    'vehicule' => 'Véhicule',
    'evaluation' => 'Évaluation /100',
    'infraction' => 'Infraction',
    'duree' => 'Durée (h)',
    'kilometrage' => 'Kilométrage (km)'
];

// Fonction pour générer l'URL de tri
function getTrueURL($colonne, $ordre_actuel) {
    $nouvel_ordre = ($ordre_actuel === 'ASC') ? 'DESC' : 'ASC';
    $url = '?';
    if (!empty($_GET['recherche'])) {
        $url .= 'recherche=' . urlencode($_GET['recherche']) . '&';
    }
    $url .= 'tri=' . urlencode($colonne) . '&ordre=' . $nouvel_ordre;
    return $url;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOUTCHERAFIN – Tableau de bord</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }
        
        body {
            font-family: 'DM Sans', sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            min-height: 100vh;
            padding: 40px 24px;
        }
        
        .page-header { 
            max-width: 1200px; 
            margin: 0 auto 28px; 
        }
        
        .page-header h1 { 
            font-size: 1.5rem; 
            font-weight: 600; 
            color: #0f172a; 
        }
        
        .page-header p  { 
            font-size: 0.875rem; 
            color: #64748b; 
            margin-top: 4px; 
        }
        
        .search-section {
            max-width: 1200px;
            margin: 0 auto 16px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
        }
        
        .search-box form {
            display: flex;
            gap: 10px;
            flex: 1;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            color: #1e293b;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #0f172a;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.1);
        }
        
        .search-box button {
            padding: 10px 16px;
            background: #0f172a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            font-size: 0.85rem;
            transition: background 0.2s;
        }
        
        .search-box button:hover {
            background: #1e293b;
        }
        
        .clear-btn {
            padding: 10px 16px;
            background: #e2e8f0;
            color: #475569;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            font-size: 0.85rem;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .clear-btn:hover {
            background: #cbd5e1;
        }
        
        .info-bar {
            max-width: 1200px;
            margin: 0 auto 16px;
            background: #ecf0f9;
            border-left: 3px solid #0f172a;
            padding: 12px 14px;
            border-radius: 4px;
            color: #0f172a;
            font-size: 0.85rem;
        }
        
        .info-bar strong {
            color: #0f172a;
            font-weight: 600;
        }
        
        .card {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            min-width: 960px;
            border-collapse: collapse;
        }
        
        thead tr {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        
        thead th {
            padding: 12px 14px;
            text-align: left;
            font-size: .72rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
        }
        
        thead th:first-child {
            border-right: 1px solid #e2e8f0;
        }
        
        thead th a {
            color: #0f172a;
            text-decoration: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        thead th a:hover {
            color: #64748b;
        }
        
        tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background .15s;
        }
        
        tbody tr:last-child {
            border-bottom: none;
        }
        
        tbody tr:hover {
            background: #f8fafc;
        }
        
        tbody td {
            padding: 12px 14px;
            font-size: .85rem;
            color: #334155;
            vertical-align: middle;
        }
        
        tbody td:first-child {
            font-weight: 600;
            font-size: .85rem;
            color: #0f172a;
            border-right: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .search-box form {
                flex-direction: column;
            }
            
            table {
                font-size: 0.8em;
            }
            
            th, td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>

<div class="page-header">
    <h1>Tableau de bord — BOUTCHERAFIN</h1>
    <p>Données en temps réel depuis la base de données · <?= date('d/m/Y H:i') ?></p>
</div>
<div class="actions">
        <!-- Nouveau bouton avec le même style -->
    <a href="tableau.php" class="btn-export">
        📊 Voir le tableau Flotte Transport
    </a>
</div>

<!-- Barre de recherche -->
<div class="search-section">
    <div class="search-box">
        <form method="GET">
            <input type="text" name="recherche" placeholder="Rechercher..." 
                   value="<?php echo htmlspecialchars($recherche); ?>" 
                   autocomplete="off">
            <button type="submit">Rechercher</button>
        </form>
        <?php if (!empty($recherche)): ?>
            <a href="BOUTCHERAFIN.php" class="clear-btn">Réinitialiser</a>
        <?php endif; ?>
    </div>
</div>

<!-- Message d'information -->
<?php if (!empty($recherche)): ?>
    <div class="info-bar">
        Résultats pour : <strong>"<?php echo htmlspecialchars($recherche); ?>"</strong> 
        (<?php echo count($donnees); ?> résultat<?php echo count($donnees) > 1 ? 's' : ''; ?>)
    </div>
<?php endif; ?>

<!-- Tableau des données -->
<div class="card">
    <?php if (!empty($donnees)): ?>
        <table>
            <thead>
                <tr>
                    <?php foreach ($colonnes as $key => $label): ?>
                        <th>
                            <a href="<?php echo getTrueURL($key, $ordre_tri); ?>">
                                <?php echo htmlspecialchars($label); ?>
                                <?php if ($colonne_tri === $key): ?>
                                    <span><?php echo $ordre_tri === 'ASC' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($donnees as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['entreprise']); ?></td>
                        <td><?php echo htmlspecialchars($row['vehicule']); ?></td>
                        <td><?php echo htmlspecialchars($row['evaluation']); ?></td>
                        <td><?php echo htmlspecialchars($row['infraction']); ?></td>
                        <td><?php echo htmlspecialchars($row['duree']); ?></td>
                        <td><?php echo htmlspecialchars($row['kilometrage']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            <?php if (!empty($recherche)): ?>
                Aucun résultat trouvé pour "<?php echo htmlspecialchars($recherche); ?>"
            <?php else: ?>
                Aucune donnée disponible.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>