<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$dataDir = 'C:/xampp/htdocs/vehicules/data/services/';
$files = array_filter(glob($dataDir . '*Activités_Chauffeurs*.xlsx'), fn($f) => strpos(basename($f), '~$') === false);
if (empty($files)) {
    $files = array_filter(glob($dataDir . '*.xlsx'), fn($f) => strpos(basename($f), '~$') === false);
}

function readSommaire(string $filePath): array {
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (stripos($name, 'Sommaire') !== false) {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }
        if ($sheet === null) return [];
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

function fileLabel(string $path): string {
    $name = basename($path, '.xlsx');
    $name = preg_replace('/_\d{2}-\d{2}-\d{4}_\d{2}-\d{2}-\d{2}$/', '', $name);
    $name = str_replace('_', ' ', $name);
    return $name;
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

function scoreNoteConduite(float $alertes, float $alertesParKm, int $charge): array {
    $note = 100 - ($alertes * 2) - ($alertesParKm * 10) - ($charge * 5);
    $note = max(0, $note);
    if ($note >= 80) $status = 'vert';
    elseif ($note >= 60) $status = 'orange';
    else $status = 'rouge';
    return ['value' => round($note, 0), 'status' => $status];
}
function scoreAlertesCritiques(int $alertes): array {
    if ($alertes <= 2) $status = 'vert';
    elseif ($alertes <= 10) $status = 'orange';
    else $status = 'rouge';
    return ['value' => $alertes, 'status' => $status];
}
function scoreAlertesParKm(int $alertes, float $km): array {
    $ratio = $km > 0 ? round(($alertes * 100) / $km, 2) : 0;
    if ($ratio < 0.5) $status = 'vert';
    elseif ($ratio <= 1) $status = 'orange';
    else $status = 'rouge';
    return ['value' => $ratio, 'status' => $status];
}
function scoreHeuresConducte(float $heures): array {
    if ($heures < 40) { $score = 1; $status = 'vert'; }
    elseif ($heures <= 50) { $score = 2; $status = 'orange'; }
    else { $score = 3; $status = 'rouge'; }
    return ['value' => round($heures, 1), 'score' => $score, 'status' => $status];
}
function scoreKilometrage(float $km): array {
    if ($km < 4000) { $score = 1; $status = 'vert'; }
    elseif ($km <= 5000) { $score = 2; $status = 'orange'; }
    else { $score = 3; $status = 'rouge'; }
    return ['value' => $km, 'score' => $score, 'status' => $status];
}
function scoreChargeConducte(int $scoreHeures, int $scoreKm): array {
    $charge = $scoreHeures + $scoreKm;
    if ($charge == 2) { $label = 'Faible'; $status = 'vert'; }
    elseif ($charge >= 3 && $charge <= 4) { $label = 'Moyenne'; $status = 'orange'; }
    else { $label = 'Élevée'; $status = 'rouge'; }
    return ['value' => $charge, 'label' => $label, 'status' => $status];
}

function dot(string $status): string {
    $colors = ['vert' => '#22c55e', 'orange' => '#f97316', 'rouge' => '#ef4444'];
    return '<span class="dot" style="background:' . ($colors[$status] ?? '#d1d5db') . '"></span>';
}

$societesData = [];
foreach ($files as $filePath) {
    $label = fileLabel($filePath);
    $sommaire = readSommaire($filePath);
    $vehicules = [];
    foreach ($sommaire as $row) {
        $reg = $row['Regroupement'] ?? '';
        if ($reg === '') continue;
        $kmRaw = $row['Kilométrage dans les missions'] ?? '0';
        $dureeRaw = $row['Temps de déplacement'] ?? '0';
        $penalitesRaw = $row['Pénalités'] ?? '0';
        $km = (float) preg_replace('/[^0-9.]/', '', $kmRaw);
        $heures = parseDureeToHours($dureeRaw);
        $infractions = (int) preg_replace('/[^0-9]/', '', $penalitesRaw);

        $scoreB = scoreAlertesCritiques($infractions);
        $scoreC = scoreAlertesParKm($infractions, $km);
        $scoreD = scoreHeuresConducte($heures);
        $scoreE = scoreKilometrage($km);
        $scoreF = scoreChargeConducte($scoreD['score'], $scoreE['score']);
        $scoreA = scoreNoteConduite((float)$scoreB['value'], (float)$scoreC['value'], (int)$scoreF['value']);

        $vehicules[] = [
            'note' => $scoreA['value'],
            'alertes' => $scoreB['value'],
            'alertes_s' => $scoreB['status'],
            'al100' => $scoreC['value'],
            'km' => $km,
        ];
    }

    $nbVehicules = count($vehicules);
    $totalNote = array_sum(array_column($vehicules, 'note'));
    $totalAlertes = array_sum(array_column($vehicules, 'alertes'));
    $totalKm = array_sum(array_column($vehicules, 'km'));
    $totalAl100 = $totalKm > 0 ? round(($totalAlertes * 100) / $totalKm, 2) : 0;
    $infractionsRouge = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'rouge'));
    $infractionsOrange = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'orange'));
    $infractionsVert = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'vert'));

    $societesData[] = [
        'nom' => $label,
        'nbVehicules' => $nbVehicules,
        'totalNote' => $totalNote,
        'totalAlertes' => $totalAlertes,
        'totalKm' => $totalKm,
        'totalAl100' => $totalAl100,
        'infractionsRouge' => $infractionsRouge,
        'infractionsOrange' => $infractionsOrange,
        'infractionsVert' => $infractionsVert,
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques — Véhicules de Service</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .page-header { max-width: 1300px; margin: 0 auto 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; color: #0f172a; }
        .page-header p { font-size: 0.875rem; color: #64748b; margin-top: 4px; }
        .btn-back, .btn-flotte {
            background: #0f172a; color: #fff; border: none;
            padding: 10px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; transition: background 0.2s; white-space: nowrap;
        }
        .btn-back:hover, .btn-flotte:hover { background: #1e293b; }
        .btn-back { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .btn-back:hover { background: #f1f5f9; }
        .buttons-group { display: flex; gap: 12px; }

        .legende { max-width: 1300px; margin: 0 auto 16px; display: flex; gap: 20px; flex-wrap: wrap; align-items: center; font-size: 0.78rem; color: #64748b; }
        .legende-item { display: flex; align-items: center; gap: 5px; }

        .card { max-width: 1300px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        thead th { padding: 12px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        thead th.sub-header { font-size: 0.65rem; padding: 8px 10px; text-align: center; }
        thead th.sub-header.rouge { border-left: 3px solid #ef4444; }
        thead th.sub-header.orange { border-left: 3px solid #f97316; }
        thead th.sub-header.vert { border-left: 3px solid #22c55e; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 11px 14px; font-size: 0.84rem; color: #334155; vertical-align: middle; }
        tbody td:first-child { font-weight: 600; color: #0f172a; }
        .cell-indicator { display: flex; align-items: center; gap: 7px; }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-right: 6px; }
        .meta { max-width: 1300px; margin: 12px auto 0; font-size: 0.78rem; color: #94a3b8; text-align: center; }
        .no-data { max-width: 1300px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 40px; text-align: center; color: #94a3b8; }
    </style>
</head>
<body>

<div class="page-header">
    <div>
        <h1>Statistiques — Véhicules de Service</h1>
        <p>Totaux calculés depuis les fichiers Excel · <?= date('d/m/Y H:i') ?></p>
    </div>
    <div class="buttons-group">
        <a href="services_tableau.php" class="btn-flotte">
            <span>📋</span> Sommaire
        </a>
        <a href="tableau.php" class="btn-back">&larr; Retour</a>
    </div>
</div>

<div class="legende">
    <strong style="color:#0f172a;">Barème :</strong>
    <div class="legende-item"><?= dot('vert') ?> Bon / Faible risque</div>
    <div class="legende-item"><?= dot('orange') ?> Moyen / Attention</div>
    <div class="legende-item"><?= dot('rouge') ?> Critique / Élevé</div>
</div>

<?php if (empty($societesData)): ?>
    <div class="no-data">Aucune donnée trouvée dans <code>data/services/</code></div>
<?php else: ?>
<div class="card">
    <table>
        <thead>
            <tr>
                <th rowspan="2">Source</th>
                <th rowspan="2">Nb véhicules</th>
                <th rowspan="2">Total Note /100</th>
                <th rowspan="2">Total Pénalités</th>
                <th rowspan="2">Total Kilométrage</th>
                <th rowspan="2">Moy. Pénalités /100km</th>
                <th colspan="3">Véhicules par statut</th>
            </tr>
            <tr>
                <th class="sub-header rouge">Rouge</th>
                <th class="sub-header orange">Orange</th>
                <th class="sub-header vert">Vert</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($societesData as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['nom']) ?></td>
                <td><?= $s['nbVehicules'] ?></td>
                <td><?= number_format($s['totalNote'], 0, ',', ' ') ?></td>
                <td><?= $s['totalAlertes'] ?></td>
                <td><?= number_format($s['totalKm'], 0, ',', ' ') ?> Km</td>
                <td><?= $s['totalAl100'] ?></td>
                <td><div class="cell-indicator"><?= dot('rouge') ?><?= $s['infractionsRouge'] ?></div></td>
                <td><div class="cell-indicator"><?= dot('orange') ?><?= $s['infractionsOrange'] ?></div></td>
                <td><div class="cell-indicator"><?= dot('vert') ?><?= $s['infractionsVert'] ?></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="meta"><?= count($societesData) ?> source(s) · <?= date('d/m/Y H:i') ?></p>
<?php endif; ?>

</body>
</html>
