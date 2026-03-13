<?php
/**
 * send_report.php — Génération PDF + envoi email
 * reports[] = ['flotte', 'boutcherafin', 'synthese']
 * 
 * Calculs alignés sur tableau.php / synthese.php (matrice CIMAT)
 * A = 100 - (B×2) - (C×10) - (F×5)
 * F = scoreD + scoreE
 * G = matrice CIMAT
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use Mpdf\Mpdf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

$dateGeneration = date('d/m/Y à H:i');

// ── Rapports demandés ─────────────────────────────────────
$reports = $_GET['reports'] ?? ['flotte', 'boutcherafin', 'synthese'];
if (!is_array($reports)) $reports = [$reports];
$doFlotte   = in_array('flotte',       $reports);
$doBoutch   = in_array('boutcherafin', $reports);
$doSynthese = in_array('synthese',     $reports);

// ── Email destinataire ────────────────────────────────────
$email_to = filter_var(trim($_GET['email_to'] ?? ''), FILTER_VALIDATE_EMAIL)
    ? trim($_GET['email_to']) : MAIL_TO;

// ══════════════════════════════════════════════════════════
// ── HELPERS EXCEL ─────────────────────────────────────────
// ══════════════════════════════════════════════════════════

$dataDir = 'C:/xampp/htdocs/vehicules/data/entreprises/';

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

// ══════════════════════════════════════════════════════════
// ── CALCULS & BARÈMES (identiques à tableau.php) ─────────
// ══════════════════════════════════════════════════════════

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

// A — Note /100 : 100 - (B×2) - (C×10) - (F×5)
function scoreNoteConduite(float $alertes, float $alertesParKm, int $charge): array {
    $note = 100 - ($alertes * 2) - ($alertesParKm * 10) - ($charge * 5);
    $note = max(0, $note);
    if ($note >= 80) $status = 'vert';
    elseif ($note >= 60) $status = 'orange';
    else $status = 'rouge';
    return ['value' => round($note, 0), 'status' => $status];
}

// B — Alertes critiques
function scoreAlertesCritiques(int $alertes): array {
    if ($alertes <= 2) $status = 'vert';
    elseif ($alertes <= 10) $status = 'orange';
    else $status = 'rouge';
    return ['value' => $alertes, 'status' => $status];
}

// C — Alertes /100km : B × 100 / km
function scoreAlertesParKm(int $alertes, float $km): array {
    $ratio = $km > 0 ? round(($alertes * 100) / $km, 2) : 0;
    if ($ratio < 0.5) $status = 'vert';
    elseif ($ratio <= 1) $status = 'orange';
    else $status = 'rouge';
    return ['value' => $ratio, 'status' => $status];
}

// D — Heures conduite : <40h=1(vert), 40-50h=2(orange), >50h=3(rouge)
function scoreHeuresConducte(float $heures): array {
    if ($heures < 40) { $score = 1; $status = 'vert'; }
    elseif ($heures <= 50) { $score = 2; $status = 'orange'; }
    else { $score = 3; $status = 'rouge'; }
    return ['value' => round($heures, 1), 'score' => $score, 'status' => $status];
}

// E — Kilométrage : <4000=1(vert), 4000-5000=2(orange), >5000=3(rouge)
function scoreKilometrage(float $km): array {
    if ($km < 4000) { $score = 1; $status = 'vert'; }
    elseif ($km <= 5000) { $score = 2; $status = 'orange'; }
    else { $score = 3; $status = 'rouge'; }
    return ['value' => $km, 'score' => $score, 'status' => $status, 'formatted' => number_format($km, 0, ',', ' ')];
}

// F — Charge conduite : scoreD + scoreE
function scoreChargeConducte(int $scoreHeures, int $scoreKm): array {
    $charge = $scoreHeures + $scoreKm;
    if ($charge == 2) { $label = 'Faible'; $status = 'vert'; }
    elseif ($charge >= 3 && $charge <= 4) { $label = 'Moyenne'; $status = 'orange'; }
    else { $label = 'Élevée'; $status = 'rouge'; }
    return ['value' => $charge, 'label' => $label, 'status' => $status];
}

// G — Risque global : matrice CIMAT
function scoreRisqueGlobal(float $note, int $alertes, float $alertesParKm, string $chargeStatus): array {
    if ($note >= 85 && $alertes < 15 && $alertesParKm < 1) {
        $risque = 'Faible'; $status = 'vert';
    } elseif ($note >= 70 && $alertes < 15 && $alertesParKm < 1) {
        $risque = 'Modéré'; $status = 'orange';
    } elseif ($note >= 55 && $alertes < 15 && $alertesParKm < 1) {
        $risque = 'Élevé'; $status = 'orange';
    } else {
        $risque = 'Critique'; $status = 'rouge';
    }
    return ['label' => $risque, 'status' => $status];
}

// ── Helpers HTML pour PDF ─────────────────────────────────
function dotPdf(string $status, string $value): string {
    $colors = ['vert'=>'#22c55e','orange'=>'#f97316','rouge'=>'#ef4444'];
    $c = $colors[$status] ?? '#94a3b8';
    return '<span style="color:' . $c . ';font-size:15px;vertical-align:middle;">&#9679;</span>'
         . '<span style="font-size:11px;vertical-align:middle;margin-left:4px;color:#334155;">' . htmlspecialchars($value) . '</span>';
}
function pillPdf(string $status, string $label): string {
    $map = ['vert'=>['#dcfce7','#166534'],'orange'=>['#ffedd5','#9a3412'],'rouge'=>['#fee2e2','#991b1b']];
    [$bg, $tc] = $map[$status] ?? ['#f1f5f9','#475569'];
    $dot = ['vert'=>'#22c55e','orange'=>'#f97316','rouge'=>'#ef4444'][$status] ?? '#94a3b8';
    return '<span style="background:' . $bg . ';color:' . $tc . ';padding:3px 8px 3px 5px;border-radius:10px;font-size:10px;font-weight:700;display:inline-block;">'
         . '<span style="color:' . $dot . ';font-size:13px;vertical-align:middle;margin-right:3px;">&#9679;</span>'
         . '<span style="vertical-align:middle;">' . htmlspecialchars($label) . '</span></span>';
}

function colorCircleValue(string $color, int $value): string {
    $colors = ['rouge' => '#ef4444', 'orange' => '#f97316', 'vert' => '#22c55e'];
    $c = $colors[$color] ?? '#d1d5db';
    return '<span style="color:' . $c . ';font-size:14px;margin-right:4px;">●</span>'
         . '<span style="font-weight:700;color:#1e293b;font-size:12px;">' . $value . '</span>';
}

// ══════════════════════════════════════════════════════════
// ── LECTURE FICHIERS EXCEL ────────────────────────────────
// ══════════════════════════════════════════════════════════
$filesEco = glob($dataDir . '*co-conduite*.xlsx');
$filesEco = array_filter($filesEco, fn($f) => strpos(basename($f), '~$') === false);
$filesKilo = glob($dataDir . '*Kilom*.xlsx');
$fileKilo = !empty($filesKilo) ? $filesKilo[0] : null;

if (empty($filesEco) || !$fileKilo) {
    die('<div style="font-family:Arial;padding:30px;color:#dc2626;">
         ❌ Fichier(s) Excel introuvable(s) dans <code>' . htmlspecialchars($dataDir) . '</code><br>
         <a href="tableau.php" style="color:#2563eb;">← Retour</a></div>');
}

// Lire les infractions
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

// Mapping infractions par véhicule
$ecoMap = [];
foreach ($infractionData as $row) {
    $reg = $row['Regroupement'] ?? '';
    if ($reg === '') continue;
    $regNorm = $normalize($reg);
    if (!isset($ecoMap[$regNorm])) {
        $ecoMap[$regNorm] = ['infractions' => 0, 'infr_list' => []];
    }
    $infr = $row['Infraction'] ?? '';
    if ($infr !== '' && $infr !== '-----' && strtolower(trim($infr)) !== '----------') {
        $ecoMap[$regNorm]['infractions']++;
        $ecoMap[$regNorm]['infr_list'][] = $infr;
    }
}

// Construction des données véhicules
$vehicules  = [];
$rowsBoutch = [];

foreach ($kiloData as $row) {
    $reg = $row['Regroupement'] ?? '';
    if ($reg === '') continue;
    $regNorm = $normalize($reg);
    $infractions = $ecoMap[$regNorm]['infractions'] ?? 0;
    $infrList = $ecoMap[$regNorm]['infr_list'] ?? [];

    $dureeRaw = $row['Durée']       ?? '0';
    $kmRaw    = $row['Kilométrage'] ?? '0';
    $heures   = parseDureeToHours($dureeRaw);
    $km       = (float) preg_replace('/[^0-9.]/', '', $kmRaw);

    // Calcul dans le bon ordre : B,C,D,E → F → A → G
    $scoreB = scoreAlertesCritiques($infractions);
    $scoreC = scoreAlertesParKm($infractions, $km);
    $scoreD = scoreHeuresConducte($heures);
    $scoreE = scoreKilometrage($km);
    $scoreF = scoreChargeConducte($scoreD['score'], $scoreE['score']);
    $scoreA = scoreNoteConduite((float)$scoreB['value'], (float)$scoreC['value'], (int)$scoreF['value']);
    $scoreG = scoreRisqueGlobal((float)$scoreA['value'], (int)$scoreB['value'], (float)$scoreC['value'], $scoreF['status']);

    // Couleur des alertes pour colonnes Rouge/Orange/Vert
    $alerts_vert = 0; $alerts_orange = 0; $alerts_rouge = 0;
    if ($scoreB['status'] === 'vert') { $alerts_vert = $infractions; }
    elseif ($scoreB['status'] === 'orange') { $alerts_orange = $infractions; }
    else { $alerts_rouge = $infractions; }

    $vehicules[] = [
        'nom'      => $reg,
        'note'     => $scoreA['value'],    'note_s'    => $scoreA['status'],
        'alertes'  => $scoreB['value'],    'alertes_s' => $scoreB['status'],
        'al100'    => $scoreC['value'],    'al100_s'   => $scoreC['status'],
        'heures'   => $scoreD['value'],    'heures_s'  => $scoreD['status'],
        'km'       => $scoreE['formatted'] . ' km', 'km_s' => $scoreE['status'],
        'charge'   => $scoreF['label'],    'charge_s'  => $scoreF['status'],
        'risque'   => $scoreG['label'],    'risque_s'  => $scoreG['status'],
        'km_raw'   => $km,
        'alerts_vert'   => $alerts_vert,
        'alerts_orange' => $alerts_orange,
        'alerts_rouge'  => $alerts_rouge,
    ];

    $uniqInfr = array_unique($infrList);
    $rowsBoutch[] = [
        'vehicule'    => $reg,
        'infraction'  => !empty($uniqInfr) ? implode(' / ', $uniqInfr) : '—',
        'duree'       => $dureeRaw,
        'kilometrage' => $kmRaw,
        'alerts_vert'   => $alerts_vert,
        'alerts_orange' => $alerts_orange,
        'alerts_rouge'  => $alerts_rouge,
    ];
}

usort($vehicules, fn($a, $b) => strcmp($a['nom'], $b['nom']));
usort($rowsBoutch, fn($a, $b) => strcmp($a['vehicule'], $b['vehicule']));

// Calculs synthèse
$nbV = count($vehicules);
$totalNote = $totalKm = $totalInfr = 0;
foreach ($vehicules as $v) {
    $totalNote += $v['note'];
    $totalKm   += $v['km_raw'];
    $totalInfr += $v['alertes'];
}
$totalAl100 = $totalKm > 0 ? round(($totalInfr * 100) / $totalKm, 2) : 0;

// Comptage véhicules par couleur d'alertes
$countRouge = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'rouge'));
$countOrange = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'orange'));
$countVert = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'vert'));

$synthese = [
    'nb'            => $nbV,
    'total_note'    => number_format($totalNote, 0, ',', ' '),
    'total_infr'    => $totalInfr,
    'total_km'      => number_format($totalKm, 0, ',', ' ') . ' Km',
    'moy100'        => $totalAl100,
    'count_rouge'   => $countRouge,
    'count_orange'  => $countOrange,
    'count_vert'    => $countVert,
];

// ══════════════════════════════════════════════════════════
// ── CSS COMMUN ────────────────────────────────────────────
// ══════════════════════════════════════════════════════════
$css = '
    body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;margin:0;padding:0;background:#fff;}
    .header{background:#f1f5f9;color:#0f172a;padding:14px 18px;margin-bottom:16px;border-radius:6px;border-left:5px solid #3b82f6;}
    .header h1{margin:0 0 3px;font-size:15px;font-weight:700;color:#0f172a;}
    .header p{margin:0;font-size:10px;color:#64748b;}
    table.data{width:100%;border-collapse:collapse;font-size:11px;}
    table.data thead tr{background:#e2e8f0;}
    table.data thead tr:first-child th{color:#334155;padding:10px 16px;text-align:left;font-size:9px;
                        text-transform:uppercase;letter-spacing:0.5px;font-weight:700;border-bottom:2px solid #cbd5e1;}
    table.data thead tr:last-child th{color:#334155;padding:8px 18px;text-align:center;font-size:8px;
                        text-transform:uppercase;letter-spacing:0.5px;font-weight:700;border-bottom:2px solid #cbd5e1;}
    table.data tbody tr:nth-child(even){background:#f8fafc;}
    table.data tbody tr:nth-child(odd){background:#ffffff;}
    table.data tbody td{border-bottom:1px solid #e2e8f0;vertical-align:middle;padding:10px 16px;color:#334155;}
    .footer{margin-top:16px;font-size:9px;color:#94a3b8;text-align:center;border-top:1px solid #e2e8f0;padding-top:10px;}
';

// ══════════════════════════════════════════════════════════
// ── BUILDERS PDF ──────────────────────────────────────────
// ══════════════════════════════════════════════════════════

// ── 1. Rapport Flotte Transport BOUTCHERAFIN ──────────────
function buildHtmlFlotte(array $rows, string $date, string $css): string {
    $lignes = '';
    foreach ($rows as $v) {
        $lignes .= '<tr>
            <td style="font-weight:700;color:#0f172a;white-space:nowrap;text-align:center;">' . htmlspecialchars($v['nom']) . '</td>
            <td style="text-align:center;">' . dotPdf($v['note_s'],    (string)$v['note'])    . '</td>
            <td style="text-align:center;">' . dotPdf($v['alertes_s'], (string)$v['alertes']) . '</td>
            <td style="text-align:center;">' . dotPdf($v['al100_s'],   (string)$v['al100'])  . '</td>
            <td style="text-align:center;">' . dotPdf($v['heures_s'],  $v['heures'] . ' h')  . '</td>
            <td style="text-align:center;">' . dotPdf($v['km_s'],      $v['km'])             . '</td>
            <td style="text-align:center;">' . pillPdf($v['charge_s'], $v['charge'])          . '</td>
            <td style="text-align:center;">' . pillPdf($v['risque_s'], $v['risque'])          . '</td>
        </tr>';
    }
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
    <div class="header"><h1>Rapport Flotte Transport BOUTCHERAFIN</h1><p>Indicateurs selon matrice CIMAT officielle &mdash; Généré le ' . $date . '</p></div>
    <table class="data"><thead><tr>
        <th style="text-align:center;">Véhicule</th><th style="text-align:center;">Note /100 </th><th style="text-align:center;">Alertes CRIT </th><th style="text-align:center;">Alertes /100km </th>
        <th style="text-align:center;">Heures </th><th style="text-align:center;">Km </th><th style="text-align:center;">Charge </th><th style="text-align:center;">Risque </th>
    </tr></thead><tbody>' . $lignes . '</tbody></table>
    <div class="footer">Rapport Flotte Transport BOUTCHERAFIN &middot; ' . count($rows) . ' véhicule(s) &middot; ' . $date . '</div>
    </body></html>';
}

// ── 2. BOUTCHERAFIN Détail ────────────────────────────────
function buildHtmlBoutch(array $rows, string $date, string $css): string {
    $lignes = '';
    foreach ($rows as $v) {
        $lignes .= '<tr>
            <td style="font-weight:700;color:#0f172a;">BOUTCHERAFIN</td>
            <td>' . htmlspecialchars($v['vehicule'])    . '</td>
            <td style="font-size:10px;">' . htmlspecialchars($v['infraction']) . '</td>
            <td>' . htmlspecialchars($v['duree'])       . '</td>
            <td>' . htmlspecialchars($v['kilometrage']) . '</td>
            <td>' . colorCircleValue('rouge',  $v['alerts_rouge'])  . '</td>
            <td>' . colorCircleValue('orange', $v['alerts_orange']) . '</td>
            <td>' . colorCircleValue('vert',   $v['alerts_vert'])   . '</td>
        </tr>';
    }
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
    <div class="header"><h1>BOUTCHERAFIN — Détail par véhicule</h1><p>Source : fichiers Excel &mdash; Généré le ' . $date . '</p></div>
    <table class="data"><thead>
        <tr>
            <th>Entreprise</th><th>Véhicule</th><th>Infractions</th><th>Durée</th><th>Kilométrage</th><th colspan="3" style="text-align:center;">Couleur</th>
        </tr>
        <tr>
            <th colspan="5"></th>
            <th style="color:#ef4444;">Rouge</th>
            <th style="color:#f97316;">Orange</th>
            <th style="color:#22c55e;">Vert</th>
        </tr>
    </thead><tbody>' . $lignes . '</tbody></table>
    <div class="footer">BOUTCHERAFIN &middot; ' . count($rows) . ' véhicule(s) &middot; ' . $date . '</div>
    </body></html>';
}

// ── 3. Rapport Par Société ────────────────────────────────
function buildHtmlSynthese(array $s, string $date, string $css): string {
    $ligne = '<tr>
        <td style="font-weight:700;color:#0f172a;text-align:center;">BOUTCHERAFIN</td>
        <td style="text-align:center;color:#1d4ed8;font-weight:600;">' . $s['nb']         . ' véhicules</td>
        <td style="text-align:center;color:#166534;font-weight:600;">' . $s['total_note']  . '</td>
        <td style="text-align:center;color:#991b1b;font-weight:600;">' . $s['total_infr']  . '</td>
        <td style="text-align:center;color:#1d4ed8;font-weight:600;">' . $s['total_km']    . '</td>
        <td style="text-align:center;color:#854d0e;font-weight:600;">' . $s['moy100']      . '</td>
        <td style="text-align:center;">' . colorCircleValue('rouge',  $s['count_rouge'])  . '</td>
        <td style="text-align:center;">' . colorCircleValue('orange', $s['count_orange']) . '</td>
        <td style="text-align:center;">' . colorCircleValue('vert',   $s['count_vert'])   . '</td>
    </tr>';
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '
    table.data { table-layout: fixed; }
    table.data th { padding: 10px 8px; }
    table.data td { padding: 10px 8px; }
    </style></head><body>
    <div class="header"><h1>Rapport Par Société</h1><p>Totaux calculés depuis les fichiers Excel &mdash; Généré le ' . $date . '</p></div>
    <table class="data"><thead>
        <tr>
            <th style="text-align:center;">Entreprise</th><th style="text-align:center;">Nb véhicules</th><th style="text-align:center;">Total Note /100</th>
            <th style="text-align:center;">Total Alertes CRIT</th><th style="text-align:center;">Total Kilométrage</th><th style="text-align:center;">Moy. Alertes. /100km</th>
            <th colspan="3" style="text-align:center;">Total Alertes Sign</th>
        </tr>
        <tr>
            <th colspan="6"></th>
            <th style="text-align:center;color:#ef4444;">Rouge</th>
            <th style="text-align:center;color:#f97316;">Orange</th>
            <th style="text-align:center;color:#22c55e;">Vert</th>
        </tr>
    </thead><tbody>' . $ligne . '</tbody></table>
    <div class="footer">Rapport Par Société &middot; ' . $s['nb'] . ' véhicule(s) &middot; ' . $date . '</div>
    </body></html>';
}

// ══════════════════════════════════════════════════════════
// ── GÉNÉRATION PDF ────────────────────────────────────────
// ══════════════════════════════════════════════════════════
function generatePdf(string $html, string $filename): string {
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    $mpdf = new Mpdf(['mode'=>'utf-8','format'=>'A4-L',
                      'margin_top'=>10,'margin_bottom'=>10,'margin_left'=>12,'margin_right'=>12]);
    $mpdf->SetAuthor('Flotte Transport');
    $mpdf->WriteHTML($html);
    $mpdf->Output($tmpPath, 'F');
    return $tmpPath;
}

$pdfFiles = [];
try {
    if ($doFlotte && !empty($vehicules)) {
        $name = 'rapport_flotte_boutcherafin_' . date('Ymd_Hi') . '.pdf';
        $pdfFiles[] = ['path' => generatePdf(buildHtmlFlotte($vehicules, $dateGeneration, $css), $name),
                       'name' => $name, 'label' => '🚗 Rapport Flotte Transport BOUTCHERAFIN'];
    }
    if ($doBoutch && !empty($rowsBoutch)) {
        $name = 'rapport_boutcherafin_detail_' . date('Ymd_Hi') . '.pdf';
        $pdfFiles[] = ['path' => generatePdf(buildHtmlBoutch($rowsBoutch, $dateGeneration, $css), $name),
                       'name' => $name, 'label' => '🚛 BOUTCHERAFIN — Détail'];
    }
    if ($doSynthese && $synthese) {
        $name = 'rapport_par_societe_' . date('Ymd_Hi') . '.pdf';
        $pdfFiles[] = ['path' => generatePdf(buildHtmlSynthese($synthese, $dateGeneration, $css), $name),
                       'name' => $name, 'label' => '📈 Rapport Par Société'];
    }
} catch (\Exception $e) {
    die('❌ Erreur génération PDF : ' . htmlspecialchars($e->getMessage()));
}

if (empty($pdfFiles)) {
    die('<div style="font-family:Arial;padding:30px;color:#dc2626;">
         ❌ Aucun rapport sélectionné ou aucune donnée disponible.<br>
         <a href="tableau.php" style="color:#2563eb;">← Retour</a></div>');
}

// ══════════════════════════════════════════════════════════
// ── ENVOI EMAIL ───────────────────────────────────────────
// ══════════════════════════════════════════════════════════
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'zakia.controlflot@gmail.com';
    $mail->Password   = 'vqnslggncuitnavh';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->setFrom('zakia.controlflot@gmail.com', 'Flotte Transport – Rapport Auto');
    $mail->addAddress($email_to, 'Destinataire');
    $mail->CharSet = 'UTF-8';

    $labelsJoints = [];
    foreach ($pdfFiles as $pdf) {
        $mail->addAttachment($pdf['path'], $pdf['name']);
        $labelsJoints[] = $pdf['label'];
    }

    $listeRapports = implode('', array_map(fn($l) => '<li>' . htmlspecialchars($l) . '</li>', $labelsJoints));
    $nbRapports    = count($pdfFiles);
    $sujetLabel    = $nbRapports > 1 ? 'Rapports Flotte BOUTCHERAFIN' : $labelsJoints[0];

    $mail->isHTML(true);
    $mail->Subject = '📋 ' . $sujetLabel . ' – ' . date('d/m/Y');
    $mail->Body = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;">
        <h2 style="color:#0f172a;margin-bottom:4px">' . htmlspecialchars($sujetLabel) . '</h2>
        <p style="color:#64748b;font-size:14px">Généré automatiquement le ' . $dateGeneration . '</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
        <p style="font-size:14px;color:#334155">Vous trouverez en pièce jointe <strong>' . $nbRapports . ' rapport(s) PDF</strong> :</p>
        <ul style="font-size:13px;color:#475569;margin:10px 0 16px 20px">' . $listeRapports . '</ul>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
        <p style="font-size:12px;color:#94a3b8">Ce message est généré automatiquement – ne pas répondre.</p>
    </div>';
    $mail->AltBody = $sujetLabel . ' – ' . date('d/m/Y') . ' – Voir les pièces jointes PDF.';

    $mail->send();
    foreach ($pdfFiles as $pdf) { if (file_exists($pdf['path'])) unlink($pdf['path']); }

    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Email envoyé</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>body{font-family:"DM Sans",sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .box{background:#fff;border-radius:16px;padding:40px 48px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.08);max-width:460px}
    .icon{font-size:3rem;margin-bottom:12px}h2{color:#0f172a;margin:0 0 8px}p{color:#64748b;font-size:.9rem;margin:0 0 8px}
    .rapports{background:#f0f4f8;border-radius:8px;padding:10px 14px;margin:12px 0 20px;text-align:left;font-size:.85rem;color:#334155}
    .rapports li{margin:4px 0}a{display:inline-block;background:#0f172a;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:.85rem}
    a:hover{background:#1e293b}</style></head><body>
    <div class="box">
        <div class="icon">✅</div>
        <h2>Email envoyé avec succès !</h2>
        <p>Rapport(s) envoyé(s) à <strong>' . htmlspecialchars($email_to) . '</strong></p>
        <ul class="rapports">' . $listeRapports . '</ul>
        <a href="tableau.php">← Retour au tableau</a>
    </div></body></html>';

} catch (Exception $e) {
    foreach ($pdfFiles as $pdf) { if (file_exists($pdf['path'])) unlink($pdf['path']); }
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title>
    <style>body{font-family:Arial,sans-serif;background:#fff0f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .box{background:#fff;border-radius:12px;padding:32px;max-width:480px;box-shadow:0 4px 16px rgba(0,0,0,.1)}
    h2{color:#dc2626}pre{background:#fef2f2;padding:12px;border-radius:8px;font-size:.8rem;overflow-x:auto}a{color:#2563eb}
    </style></head><body>
    <div class="box"><h2>❌ Erreur d\'envoi</h2><p>Message PHPMailer :</p>
    <pre>' . htmlspecialchars($mail->ErrorInfo) . '</pre>
    <p><a href="tableau.php">← Retour au tableau</a></p></div></body></html>';
}