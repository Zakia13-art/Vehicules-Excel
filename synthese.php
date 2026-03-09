<?php
/**
 * synthese.php — Rapport Par Société
 * Lit directement les fichiers Excel dans data/entreprises/
 * ✨ BARRE DE RECHERCHE AVANCÉE avec filtres structurés
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$dataDir    = 'C:/xampp/htdocs/vehicules/data/entreprises/';
$SHEET_ECO  = 'Éco-conduite';
$SHEET_KILO = 'Kilométrage+Heures moteur';

function findLatestSyn(string $dir, string $pattern): ?string {
    $files = glob($dir . '*' . $pattern . '*.xlsx');
    if (empty($files)) return null;
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return $files[0];
}
function readSheetSyn(string $filePath, string $sheetName): array {
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);
        if (empty($rows)) return [];
        $headers = array_map(fn($h) => trim((string)($h ?? '')), array_shift($rows));
        $data = [];
        foreach ($rows as $row) {
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) continue;
            $assoc = [];
            foreach ($headers as $i => $h) { $assoc[$h] = trim((string)($row[$i] ?? '')); }
            $data[] = $assoc;
        }
        return $data;
    } catch (\Exception $e) { return []; }
}
function parseKmSyn(string $val): float { return (float) preg_replace('/[^0-9.]/', '', $val); }

function sAlertes(int $v): string { return $v <= 2 ? 'vert' : ($v <= 10 ? 'orange' : 'rouge'); }

$error    = '';
$synthese = null;
$rows     = [];

$fileEco  = findLatestSyn($dataDir, 'co-conduite');
$fileKilo = findLatestSyn($dataDir, 'om');

if (!$fileEco || !$fileKilo) {
    $error = 'Fichier(s) Excel introuvable(s) dans <code>' . htmlspecialchars($dataDir) . '</code>';
} else {
    $ecoData  = readSheetSyn($fileEco,  $SHEET_ECO);
    $kiloData = readSheetSyn($fileKilo, $SHEET_KILO);

    $ecoMap = [];
    foreach ($ecoData as $row) {
        $reg  = $row['Regroupement'] ?? '';
        $eval = $row['Évaluation']   ?? '';
        $infr = $row['Infraction']   ?? '';
        if ($reg === '') continue;
        if (!isset($ecoMap[$reg])) $ecoMap[$reg] = ['evaluations' => [], 'infractions' => []];
        if ($eval !== '' && $eval !== '00' && is_numeric($eval))
            $ecoMap[$reg]['evaluations'][] = (float)$eval;
        if ($infr !== '' && $infr !== '-----')
            $ecoMap[$reg]['infractions'][] = $infr;
    }

    foreach ($kiloData as $row) {
        $reg  = $row['Regroupement'] ?? '';
        $info = $ecoMap[$reg] ?? null;
        if ($reg === '' || $info === null) continue;
        $nbInfr  = count($info['infractions']);
        $note    = min(100, round(array_sum($info['evaluations'])));
        $km      = parseKmSyn($row['Kilométrage'] ?? '0');
        $couleur = sAlertes($nbInfr);
        
        $alerts_vert = 0;
        $alerts_orange = 0;
        $alerts_rouge = 0;
        
        if ($couleur === 'vert') {
            $alerts_vert = $nbInfr;
        } elseif ($couleur === 'orange') {
            $alerts_orange = $nbInfr;
        } else {
            $alerts_rouge = $nbInfr;
        }
        
        $rows[] = [
            'vehicule'    => $reg,
            'note'        => $note,
            'alertes'     => $nbInfr,
            'alertes_couleur' => $couleur,
            'km'          => $km,
            'km_raw'      => $row['Kilométrage'] ?? '—',
            'duree'       => $row['Durée'] ?? '—',
            'infraction'  => implode(' / ', array_unique($info['infractions'])),
            'alerts_vert' => $alerts_vert,
            'alerts_orange' => $alerts_orange,
            'alerts_rouge' => $alerts_rouge,
        ];
    }

    $nbV       = count($rows);
    $totalNote = array_sum(array_column($rows, 'note'));
    $totalInfr = array_sum(array_column($rows, 'alertes'));
    $totalKm   = array_sum(array_column($rows, 'km'));
    $moy100    = $totalKm > 0 ? round(($totalInfr / $totalKm) * 100, 2) : 0;

    $countRouge   = 0;
    $countOrange  = 0;
    $countVert    = 0;
    foreach ($rows as $v) {
        switch ($v['alertes_couleur']) {
            case 'rouge':
                $countRouge++;
                break;
            case 'orange':
                $countOrange++;
                break;
            case 'vert':
                $countVert++;
                break;
        }
    }

    $synthese = [
        'nb'         => $nbV,
        'total_note' => $totalNote,
        'total_infr' => $totalInfr,
        'total_km'   => number_format($totalKm, 0, ',', ' ') . ' Km',
        'moy100'     => $moy100,
        'count_rouge' => $countRouge,
        'count_orange' => $countOrange,
        'count_vert' => $countVert,
    ];
}

function coloredValue(string $color, int $value): string {
    $colors = ['rouge' => '#ef4444', 'orange' => '#f97316', 'vert' => '#22c55e'];
    $c = $colors[$color] ?? '#94a3b8';
    return '<span style="display:inline-flex;align-items:center;gap:6px;">'
         . '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' . $c . ';flex-shrink:0;"></span>'
         . '<span>' . $value . '</span>'
         . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport Par Société</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .page-header { max-width: 1200px; margin: 0 auto 28px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; color: #0f172a; }
        .page-header p  { font-size: 0.875rem; color: #64748b; margin-top: 4px; }
        .actions { max-width: 1200px; margin: 0 auto 24px; display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 8px; background: #0f172a; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font-size: 0.85rem; font-family: inherit; font-weight: 500; cursor: pointer; text-decoration: none; transition: background .15s; }
        .btn:hover { background: #1e293b; }

        /* KPI Cards */
        .kpi-grid { max-width: 1200px; margin: 0 auto 28px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .kpi-card { background: #fff; border-radius: 14px; padding: 20px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05); }
        .kpi-card.purple { border-top: 3px solid #8b5cf6; }
        .kpi-card.blue   { border-top: 3px solid #3b82f6; }
        .kpi-card.red    { border-top: 3px solid #ef4444; }
        .kpi-card.green  { border-top: 3px solid #22c55e; }
        .kpi-card.yellow { border-top: 3px solid #f59e0b; }
        .kpi-label { font-size: 0.72rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .kpi-value { font-size: 1.8rem; font-weight: 700; color: #0f172a; line-height: 1; }
        .kpi-sub   { font-size: 0.78rem; color: #94a3b8; margin-top: 4px; }

        /* Tableaux */
        .section-title { max-width: 1200px; margin: 0 auto 12px; font-size: 0.95rem; font-weight: 600; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .card { max-width: 1200px; margin: 0 auto 28px; background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        thead tr:first-child th { padding: 12px 14px; text-align: left; font-size: 0.72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        thead tr:last-child th { padding: 6px 14px; font-size: 0.65rem; border-top: 1px solid #e2e8f0; text-align: center; }
        tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }
        tbody tr.hidden { display: none; }
        tbody td { padding: 12px 14px; font-size: 0.85rem; color: #334155; vertical-align: middle; }
        tbody td:first-child { font-weight: 600; color: #0f172a; }

        .synthese-row td { background: #f0f4f8; font-weight: 700 !important; font-size: 1rem !important; color: #0f172a !important; border-top: 2px solid #e2e8f0; }

        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; }
        .badge-blue   { background: #dbeafe; color: #1d4ed8; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-purple { background: #ede9fe; color: #5b21b6; }

        .error-box { max-width: 1200px; margin: 0 auto; background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 16px 20px; color: #dc2626; }
        .meta { max-width: 1200px; margin: 0 auto; font-size: 0.78rem; color: #94a3b8; }

        /* Search Bar Avancée */
        .search-container { max-width: 1200px; margin: 0 auto 24px; }
        .search-box { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05); border: 1px solid #e2e8f0; }
        .search-select { padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; background: #fff; }
        .search-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .btn-validate { padding: 10px 20px; background: #17a2b8; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; }
        .btn-validate:hover { background: #138496; }
        .btn-reset { padding: 10px 20px; background: #e2e8f0; color: #0f172a; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; }
        .btn-reset:hover { background: #cbd5e1; }
        .search-stats { font-size: 0.85rem; color: #64748b; }
    </style>
</head>
<body>

<div class="page-header">
    <h1>📊 Rapport Par Société</h1>
    <p>Totaux calculés automatiquement depuis les fichiers Excel · <?= date('d/m/Y H:i') ?></p>
</div>

<div class="actions">
    <a href="tableau.php" class="btn">← Retour Flotte Transport</a>
    <a href="BOUTCHERAFIN.php" class="btn">📋 Voir le détail BOUTCHERAFIN</a>
    <a href="index.php" class="btn">📊 Accéder au Dashboard</a>
</div>

<!-- Barre de Recherche Avancée ✨ NOUVEAU -->
<div class="search-container">
    <div class="search-box">
        <form id="filterForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 16px;">
            
            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase;">Transporteur</label>
                <select id="transporteur" class="search-select">
                    <option value="">BOUTCHERAFIN</option>
                    <option value="tous">Tous</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase;">Chauffeur</label>
                <select id="chauffeur" class="search-select">
                    <option value="">Tous</option>
                    <option value="non">Non</option>
                    <option value="flot">Flot</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase;">Véhicule</label>
                <select id="vehicule" class="search-select">
                    <option value="">Tous</option>
                    <option value="13429">13429/A/25</option>
                    <option value="21093">21093/A/17</option>
                    <option value="MAN">MAN 23930/A/25</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase;">Du</label>
                <input type="date" id="dateFrom" class="search-select">
            </div>

            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 6px; text-transform: uppercase;">Au</label>
                <input type="date" id="dateTo" class="search-select">
            </div>
        </form>

        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <button class="btn-validate" onclick="applyFilters()">Valider</button>
            <button class="btn-reset" onclick="resetAllFilters()">Réinitialiser</button>
            <div id="searchStats" class="search-stats" style="margin-left: auto; display: none;">
                Résultats: <span id="resultCount">0</span> véhicule(s)
            </div>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="error-box">❌ <strong>Erreur :</strong> <?= $error ?></div>

<?php elseif ($synthese): ?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card purple">
        <div class="kpi-label">Nb véhicules</div>
        <div class="kpi-value"><?= $synthese['nb'] ?></div>
        <div class="kpi-sub">véhicules actifs</div>
    </div>
    <div class="kpi-card blue">
        <div class="kpi-label">Total Note /100</div>
        <div class="kpi-value"><?= number_format($synthese['total_note'], 0, ',', ' ') ?></div>
        <div class="kpi-sub">points cumulés</div>
    </div>
    <div class="kpi-card red">
        <div class="kpi-label">Total Infractions</div>
        <div class="kpi-value"><?= $synthese['total_infr'] ?></div>
        <div class="kpi-sub">alertes relevées</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-label">Total Kilométrage</div>
        <div class="kpi-value" style="font-size:1.3rem;"><?= $synthese['total_km'] ?></div>
        <div class="kpi-sub">distance parcourue</div>
    </div>
    <div class="kpi-card yellow">
        <div class="kpi-label">Moy. Infr. /100km</div>
        <div class="kpi-value"><?= $synthese['moy100'] ?></div>
        <div class="kpi-sub">infractions / 100 km</div>
    </div>
</div>

<!-- Tableau synthèse -->
<div class="section-title">Synthèse par société</div>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Société</th>
                <th>Nb véhicules</th>
                <th>Total Note /100</th>
                <th>Total Infractions</th>
                <th>Total Kilométrage</th>
                <th>Moy. Infr. /100 km</th>
                <th colspan="3" style="text-align:center;">Couleur</th>
            </tr>
            <tr>
                <th colspan="6"></th>
                <th>● Rouge</th>
                <th>● Orange</th>
                <th>● Vert</th>
            </tr>
        </thead>
        <tbody>
            <tr class="synthese-row">
                <td>BOUTCHERAFIN</td>
                <td><span class="badge badge-purple">🚗 <?= $synthese['nb'] ?> véhicules</span></td>
                <td><span class="badge badge-blue">📊 <?= number_format($synthese['total_note'], 0, ',', ' ') ?></span></td>
                <td><span class="badge badge-red">⚠️ <?= $synthese['total_infr'] ?></span></td>
                <td><span class="badge badge-green">📍 <?= $synthese['total_km'] ?></span></td>
                <td><span class="badge badge-yellow">📈 <?= $synthese['moy100'] ?></span></td>
                <td><?= coloredValue('rouge', $synthese['count_rouge']) ?></td>
                <td><?= coloredValue('orange', $synthese['count_orange']) ?></td>
                <td><?= coloredValue('vert', $synthese['count_vert']) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Tableau détail -->
<div class="section-title">Détail par véhicule</div>
<div style="max-width: 1200px; margin: 0 auto 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
    <div></div>
    <div style="font-size: 0.9rem; color: #64748b;">
        Lignes par page: 
        <select id="pageSize" style="padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: 'DM Sans', sans-serif;">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select>
    </div>
</div>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Véhicule</th>
                <th>Note /100</th>
                <th>Alertes CRIT</th>
                <th>Durée</th>
                <th>Kilométrage</th>
                <th colspan="3" style="text-align:center;">Couleur</th>
                <th>Infractions</th>
            </tr>
            <tr>
                <th colspan="5"></th>
                <th colspan="3" style="border-top: 1px solid #cbd5e1; padding: 6px 14px;">
                    <div style="display: flex; justify-content: space-around; font-size: 0.65rem;">
                        <span style="color: #ef4444;">● Rouge</span>
                        <span style="color: #f97316;">● Orange</span>
                        <span style="color: #22c55e;">● Vert</span>
                    </div>
                </th>
                <th colspan="1" style="border-top: 1px solid #cbd5e1;"></th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <?php foreach ($rows as $i => $v): ?>
            <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>" class="data-row" data-vehicule="<?= strtolower(htmlspecialchars($v['vehicule'])) ?>" data-note="<?= $v['note'] ?>" data-alertes="<?= $v['alertes'] ?>" data-km="<?= $v['km'] ?>">
                <td><?= htmlspecialchars($v['vehicule']) ?></td>
                <td><?= $v['note'] ?></td>
                <td><?= $v['alertes'] ?></td>
                <td><?= htmlspecialchars($v['duree']) ?></td>
                <td><?= htmlspecialchars($v['km_raw']) ?></td>
                <td style="text-align:center;"><?= coloredValue('rouge', $v['alerts_rouge']) ?></td>
                <td style="text-align:center;"><?= coloredValue('orange', $v['alerts_orange']) ?></td>
                <td style="text-align:center;"><?= coloredValue('vert', $v['alerts_vert']) ?></td>
                <td style="font-size:0.8rem;color:#475569;"><?= htmlspecialchars($v['infraction']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<p class="meta"><?= $synthese ? $synthese['nb'] . ' véhicule(s)' : '' ?> — Dernière mise à jour : <?= date('d/m/Y H:i') ?></p>

<script>
    // Appliquer les filtres
    function applyFilters() {
        const transporteur = document.getElementById('transporteur').value;
        const chauffeur = document.getElementById('chauffeur').value;
        const vehicule = document.getElementById('vehicule').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        const rows = document.querySelectorAll('.data-row');
        let count = 0;

        rows.forEach(row => {
            const vehiculeText = row.dataset.vehicule.toLowerCase();
            let match = true;

            // Filtrer par véhicule si sélectionné
            if (vehicule && !vehiculeText.includes(vehicule.toLowerCase())) {
                match = false;
            }

            if (match) {
                row.classList.remove('hidden');
                count++;
            } else {
                row.classList.add('hidden');
            }
        });

        // Afficher les stats
        const statsDiv = document.getElementById('searchStats');
        if (transporteur || chauffeur || vehicule || dateFrom || dateTo) {
            statsDiv.style.display = 'block';
            document.getElementById('resultCount').textContent = count;
        } else {
            statsDiv.style.display = 'none';
        }
    }

    // Réinitialiser tous les filtres
    function resetAllFilters() {
        document.getElementById('transporteur').value = '';
        document.getElementById('chauffeur').value = '';
        document.getElementById('vehicule').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        document.querySelectorAll('.data-row').forEach(row => row.classList.remove('hidden'));
        document.getElementById('searchStats').style.display = 'none';
    }
</script>

</body>
</html>