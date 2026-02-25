<?php
/**
 * import_excel.php
 * -------------------------------------------------------
 * Lit les fichiers .xlsx dans data/entreprises/
 * Structure attendue (noms de fichiers BOUTCHERAFIN) :
 *   - *co-conduite*.xlsx  → Colonnes: Regroupement|...|Infraction|...|Évaluation
 *   - *Kilom*.xlsx        → Colonnes: Regroupement|...|Kilométrage
 *
 * Résultat par entreprise :
 *   1. Entreprise  (nom extrait du fichier, ex: BOUTCHERAFIN)
 *   2. Nb véhicules (nb de Regroupement distincts)
 *   3. Note /100   (moyenne colonne Évaluation, lignes résumé '-----')
 *   4. Nb infractions (lignes avec Infraction ≠ '-----')
 *   5. Total km    (somme colonne Kilométrage)
 *   + TOTAL automatique (nb véhicules + total km)
 * -------------------------------------------------------
 * Prérequis : composer require phpoffice/phpspreadsheet
 * -------------------------------------------------------
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

define('EXCEL_DIR', __DIR__ . '/data/entreprises/');

$pdo->exec("TRUNCATE TABLE rapport_fichiers");


// ── Init table BDD ────────────────────────────────────────
function initTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rapport_entreprises (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            entreprise         VARCHAR(150)  NOT NULL UNIQUE,
            nb_vehicules       INT           DEFAULT 0,
            note_conduite      DECIMAL(7,4)  DEFAULT NULL,
            nb_infractions     INT           DEFAULT 0,
            total_kilometrage  DECIMAL(14,2) DEFAULT 0,
            fichiers_source    TEXT,
            date_import        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
}

// ── Extrait nom entreprise depuis nom de fichier ──────────
// Ex: "BOUTCHERAFIN_rapport__Éco-conduite_..." → "BOUTCHERAFIN"
function extractEntreprise(string $filename): string {
    $base  = pathinfo($filename, PATHINFO_FILENAME);
    $parts = explode('_', $base);
    return $parts[0] ?? $base;
}

// ── Lecture fichier Éco-conduite ──────────────────────────
// Lignes avec Infraction='-----' = résumé véhicule (note globale)
// Autres lignes = une infraction comptabilisée
function parseEcoCoduite(string $path): array {
    $wb = IOFactory::load($path);
    $ws = null;
    foreach ($wb->getSheetNames() as $name) {
        if (stripos($name, 'co-conduite') !== false || stripos($name, 'eco') !== false) {
            $ws = $wb->getSheetByName($name); break;
        }
    }
    if (!$ws) $ws = $wb->getSheet(1);

    $data     = [];
    $firstRow = true;
    foreach ($ws->getRowIterator() as $row) {
        if ($firstRow) { $firstRow = false; continue; }
        $cells = [];
        foreach ($row->getCellIterator('A', 'H') as $cell) {
            $cells[] = $cell->getCalculatedValue();
        }
        [$reg, , , , $infraction, , , $evaluation] = array_pad($cells, 8, null);
        if (!$reg) continue;
        if (!isset($data[$reg])) $data[$reg] = ['note' => null, 'infractions' => 0];
        if ($infraction === '-----') {
            $data[$reg]['note'] = is_numeric($evaluation) ? (float)$evaluation : null;
        } else {
            $data[$reg]['infractions']++;
        }
    }
    return $data;
}

// ── Lecture fichier Kilométrage ───────────────────────────
function parseKilometrage(string $path): array {
    $wb = IOFactory::load($path);
    $ws = null;
    foreach ($wb->getSheetNames() as $name) {
        if (stripos($name, 'kilom') !== false) {
            $ws = $wb->getSheetByName($name); break;
        }
    }
    if (!$ws) $ws = $wb->getSheet(1);

    $data     = [];
    $firstRow = true;
    foreach ($ws->getRowIterator() as $row) {
        if ($firstRow) { $firstRow = false; continue; }
        $cells = [];
        foreach ($row->getCellIterator('A', 'E') as $cell) {
            $cells[] = $cell->getCalculatedValue();
        }
        [$reg, , , , $km] = array_pad($cells, 5, null);
        if ($reg && is_numeric($km)) $data[$reg] = (float)$km;
    }
    return $data;
}

// ── Traitement d'un groupe (une entreprise) ───────────────
function processEntreprise(string $entreprise, array $files, PDO $pdo): array {

    $ecoFile = $kmFile = null;

    foreach ($files as $f) {
        $lower = mb_strtolower($f);
        if (str_contains($lower, 'co-conduite') || str_contains($lower, 'eco')) $ecoFile = $f;
        if (str_contains($lower, 'kilom')) $kmFile = $f;
    }

    $ecoData = $ecoFile ? parseEcoCoduite(EXCEL_DIR . $ecoFile) : [];
    $kmData  = $kmFile  ? parseKilometrage(EXCEL_DIR . $kmFile)  : [];

    $allVeh = array_unique(array_merge(array_keys($ecoData), array_keys($kmData)));

    $nb = 0;
    $totalKm = 0;
    $totalInfr = 0;
    $notes = [];

    foreach ($allVeh as $veh) {

        $note = $ecoData[$veh]['note'] ?? null;
        $infractions = $ecoData[$veh]['infractions'] ?? 0;
        $km = $kmData[$veh] ?? 0;

        $pdo->prepare("
            INSERT INTO rapport_fichiers
            (entreprise, nom_fichier, regroupement, kilometrage, duree, infraction, evaluation)
            VALUES (:ent,:veh,:note,:infr,:km)
        ")->execute([
            ':ent'=>$entreprise,
            ':veh'=>$veh,
            ':note'=>$note,
            ':infr'=>$infractions,
            ':km'=>$km
        ]);

        $nb++;
        $totalKm += $km;
        $totalInfr += $infractions;
        if ($note !== null) $notes[] = $note;
    }

    $noteMoy = count($notes) ? round(array_sum($notes)/count($notes), 4) : null;

    return [
        'status'=>'ok',
        'nb'=>$nb,
        'note'=>$noteMoy,
        'infr'=>$totalInfr,
        'km'=>$totalKm
    ];

    // --- Stockage dans rapport_fichiers
foreach ($allVeh as $veh) {
    $note = $ecoData[$veh]['note'] ?? null;
    $infractions = $ecoData[$veh]['infractions'] ?? 0;
    $km = $kmData[$veh] ?? 0;

    $duree = $ecoData[$veh]['duree'] ?? null; // si tu as colonne durée dans le fichier

    $stmt = $pdo->prepare("
        INSERT INTO rapport_fichiers
        (entreprise, nom_fichier, regroupement, kilometrage, duree, infraction, evaluation)
        VALUES (:ent, :file, :reg, :km, :dur, :inf, :eval)
    ");

    $stmt->execute([
        ':ent'  => $entreprise,
        ':file' => $ecoFile ?? $kmFile ?? 'inconnu',
        ':reg'  => $veh,
        ':km'   => $km,
        ':dur'  => $duree,
        ':inf'  => $infractions,
        ':eval' => $note
    ]);
}


}

// ── Import principal ──────────────────────────────────────

$pdo->exec("TRUNCATE TABLE rapport_vehicules");

function importAll(PDO $pdo): array {
    $pdo->exec("TRUNCATE TABLE rapport_vehicules");
    initTable($pdo);
    if (!is_dir(EXCEL_DIR))
        return [['entreprise'=>'—','status'=>'erreur','msg'=>'Dossier introuvable : '.EXCEL_DIR]];

    $groups = [];
    foreach (glob(EXCEL_DIR . '*.xlsx') as $path) {
        $f   = basename($path);
        $ent = extractEntreprise($f);
        $groups[$ent][] = $f;
    }
    $results = [];
    foreach ($groups as $ent => $files) {
        try {
            $r = processEntreprise($ent, $files, $pdo);
            $results[] = array_merge(['entreprise'=>$ent,'fichiers'=>$files], $r);
        } catch (\Exception $e) {
            $results[] = ['entreprise'=>$ent,'status'=>'erreur','msg'=>$e->getMessage(),'fichiers'=>$files];
        }
    }
    return $results;
}

// ── Exécution ─────────────────────────────────────────────
try {
    $pdo     = getDB();
    $results = importAll($pdo);
    $rows    = $pdo->query("SELECT * FROM rapport_entreprises ORDER BY entreprise")->fetchAll();

    $totalVeh  = array_sum(array_column($rows, 'nb_vehicules'));
    $totalKm   = array_sum(array_column($rows, 'total_kilometrage'));
    $totalInfr = array_sum(array_column($rows, 'nb_infractions'));
    $notes     = array_filter(array_column($rows, 'note_conduite'), fn($v) => $v !== null);
    $moyNote   = count($notes) ? round(array_sum($notes)/count($notes), 2) : null;
} catch (PDOException $e) {
    die('<p style="color:red;padding:20px">❌ BDD : '.htmlspecialchars($e->getMessage()).'</p>');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Import Excel — Rapport Entreprises</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#f0f4f8;color:#1e293b;padding:36px 24px}
.header{max-width:1060px;margin:0 auto 28px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.header h1{font-size:1.4rem;font-weight:600;color:#0f172a}
.header p{font-size:.84rem;color:#64748b;margin-top:3px}
.btns{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:.82rem;font-weight:500;text-decoration:none;white-space:nowrap}
.btn-dark{background:#0f172a;color:#fff}.btn-dark:hover{background:#1e293b}
.btn-light{background:#e2e8f0;color:#334155}.btn-light:hover{background:#cbd5e1}

.kpis{max-width:1060px;margin:0 auto 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.kpi{background:#fff;border-radius:14px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.07)}
.kpi .label{font-size:.71rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px}
.kpi .value{font-size:1.8rem;font-weight:700;margin-top:5px;font-variant-numeric:tabular-nums}
.kpi .sub{font-size:.74rem;color:#94a3b8;margin-top:3px}
.kpi.blue .value{color:#0369a1}.kpi.green .value{color:#16a34a}
.kpi.orange .value{color:#c2410c}.kpi.slate .value{color:#475569}

.section-title{max-width:1060px;margin:0 auto 10px;font-size:.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px}
.log{max-width:1060px;margin:0 auto 24px}
.log-item{background:#fff;border-radius:10px;padding:12px 16px;margin-bottom:7px;box-shadow:0 1px 2px rgba(0,0,0,.05);display:flex;align-items:flex-start;gap:12px}
.log-item.ok{border-left:3px solid #22c55e}.log-item.erreur{border-left:3px solid #f97316}
.log-icon{font-size:1.1rem;flex-shrink:0;margin-top:1px}
.log-body .name{font-weight:600;font-size:.88rem;color:#0f172a}
.log-body .files{font-size:.74rem;color:#64748b;margin-top:2px}
.log-body .stats{font-size:.78rem;color:#475569;margin-top:4px}

.card{max-width:1060px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.07),0 8px 32px rgba(0,0,0,.06);overflow-x:auto}
table{width:100%;min-width:760px;border-collapse:collapse}
thead tr{background:#f8fafc;border-bottom:2px solid #e2e8f0}
thead th{padding:12px 16px;text-align:left;font-size:.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .12s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f8fafc}
tr.total-row{background:#eff6ff!important;border-top:2px solid #bfdbfe}
tr.total-row td{font-weight:700;color:#1d4ed8}
td{padding:13px 16px;font-size:.88rem;color:#334155;vertical-align:middle}
td:first-child{font-weight:700;color:#0f172a}
.num{font-variant-numeric:tabular-nums}
.badge{display:inline-block;padding:3px 11px;border-radius:20px;font-size:.78rem;font-weight:600}
.g{background:#dcfce7;color:#166534}.o{background:#ffedd5;color:#9a3412}
.r{background:#fee2e2;color:#991b1b}.s{background:#f1f5f9;color:#64748b}.b{background:#dbeafe;color:#1e40af}
.meta{max-width:1060px;margin:12px auto 0;font-size:.75rem;color:#94a3b8}
</style>
</head>
<body>

<div class="header">
    <div>
        <h1>📊 Import Excel — Rapport Entreprises</h1>
        <p>Fusion automatique Éco-conduite × Kilométrage depuis <code>data/entreprises/</code></p>
    </div>
    <div class="btns">
        <a href="import_excel.php" class="btn btn-light">🔄 Relancer l'import</a>
        <a href="tableau.php"      class="btn btn-dark">← Tableau véhicules</a>

        <a href="tableau_vehicules.php" class="btn btn-dark">← Tableau véhicules</a>

    </div>
</div>

<!-- KPI Cards -->
<div class="kpis">
    <div class="kpi blue">
        <div class="label">Total Véhicules</div>
        <div class="value"><?= number_format($totalVeh,0,',',' ') ?></div>
        <div class="sub">toutes entreprises</div>
    </div>
    <div class="kpi green">
        <div class="label">Note Moyenne /100</div>
        <div class="value"><?= $moyNote !== null ? number_format($moyNote,2,',','') : '–' ?></div>
        <div class="sub">moyenne éco-conduite</div>
    </div>
    <div class="kpi orange">
        <div class="label">Total Infractions</div>
        <div class="value"><?= number_format($totalInfr,0,',',' ') ?></div>
        <div class="sub">alertes critiques</div>
    </div>
    <div class="kpi slate">
        <div class="label">Total Kilométrage</div>
        <div class="value"><?= number_format($totalKm,0,',',' ') ?></div>
        <div class="sub">km parcourus</div>
    </div>
</div>

<!-- Journal -->
<div class="section-title">📂 Journal d'import (<?= count($results) ?> entreprise(s) traitée(s))</div>
<div class="log">
<?php foreach ($results as $r): $ok = $r['status']==='ok'; ?>
    <div class="log-item <?= $r['status'] ?>">
        <div class="log-icon"><?= $ok ? '✅' : '❌' ?></div>
        <div class="log-body">
            <div class="name"><?= htmlspecialchars($r['entreprise']) ?></div>
            <?php if (!empty($r['fichiers'])): ?>
            <div class="files">📄 <?= implode('<br>📄 ', array_map('htmlspecialchars', $r['fichiers'])) ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
            <div class="stats">
                <?= $r['nb'] ?> véhicule(s) &nbsp;·&nbsp;
                Note moy. : <?= $r['note'] !== null ? number_format($r['note'],2,',','') : 'N/A' ?> &nbsp;·&nbsp;
                Infractions : <?= number_format($r['infr'],0,',',' ') ?> &nbsp;·&nbsp;
                Km : <?= number_format($r['km'],2,',',' ') ?> km
            </div>
            <?php else: ?>
            <div class="stats" style="color:#dc2626"><?= htmlspecialchars($r['msg']??'') ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Tableau -->
<div class="section-title">📋 Tableau fusionné</div>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Entreprise</th>
                <th>Nb Véhicules</th>
                <th>Note conduite /100</th>
                <th>Nb Infractions</th>
                <th>Total Kilométrage</th>
                <th>Mise à jour</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row):
            $note = $row['note_conduite'];
            $nc   = ($note === null) ? 's' : ($note >= 9 ? 'g' : ($note >= 7 ? 'o' : 'r'));
            $ic   = $row['nb_infractions'] > 0 ? 'o' : 'g';
        ?>
        <tr>
            <td><?= htmlspecialchars($row['entreprise']) ?></td>
            <td class="num"><span class="badge b"><?= number_format($row['nb_vehicules'],0,',',' ') ?> véh.</span></td>
            <td><?php if ($note !== null): ?>
                <span class="badge <?= $nc ?>"><?= number_format($note,2,',','') ?> / 100</span>
                <?php else: ?><span class="badge s">N/A</span><?php endif; ?>
            </td>
            <td class="num"><span class="badge <?= $ic ?>"><?= number_format($row['nb_infractions'],0,',',' ') ?></span></td>
            <td class="num"><?= number_format($row['total_kilometrage'],2,',',' ') ?> km</td>
            <td style="color:#94a3b8;font-size:.77rem"><?= date('d/m/Y H:i', strtotime($row['date_import'])) ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- Ligne TOTAL automatique -->
        <tr class="total-row">
            <td>🔢 TOTAL GLOBAL</td>
            <td class="num"><?= number_format($totalVeh,0,',',' ') ?> véh.</td>
            <td><?= $moyNote !== null ? number_format($moyNote,2,',','').' / 100 (moy.)' : '–' ?></td>
            <td class="num"><?= number_format($totalInfr,0,',',' ') ?></td>
            <td class="num"><?= number_format($totalKm,2,',',' ') ?> km</td>
            <td style="font-size:.75rem;color:#94a3b8">Calculé automatiquement</td>
        </tr>
        </tbody>
    </table>
</div>

<p class="meta">
    <?= count($rows) ?> entreprise(s) &nbsp;·&nbsp; Dossier : <code><?= EXCEL_DIR ?></code>
</p>

</body>
</html>