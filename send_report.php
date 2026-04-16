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

// ── Période de traitement ──────────────────────────────────
$dateFrom = $_GET['date_from'] ?? '2026-03-01';
$dateTo   = $_GET['date_to'] ?? '2026-03-31';
$periodeLibelle = (new DateTime($dateFrom))->format('d/m/Y') . ' au ' . (new DateTime($dateTo))->format('d/m/Y');

// ── Rapports demandés ─────────────────────────────────────
$reports = $_GET['reports'] ?? ['flotte', 'detail', 'synthese'];
if (!is_array($reports)) $reports = [$reports];
$doFlotte   = in_array('flotte',  $reports);
$doDetail   = in_array('detail',  $reports);
$doSynthese = in_array('synthese', $reports);

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
// ── LECTURE FICHIERS EXCEL (par société) ──────────────────
// ══════════════════════════════════════════════════════════
$societes = array_filter(glob($dataDir . '*'), 'is_dir');

if (empty($societes)) {
    die('<div style="font-family:Arial;padding:30px;color:#dc2626;">
         ❌ Aucun dossier société trouvé dans <code>' . htmlspecialchars($dataDir) . '</code><br>
         <a href="tableau.php" style="color:#2563eb;">← Retour</a></div>');
}

$normalize = function($str) {
    return trim(preg_replace('/\s+/', ' ', $str));
};

// ── Données par société ───────────────────────────────────
$societesVehicules = [];  // [socName => [vehicules...]]
$societesDetail    = [];  // [socName => [rows...]]
$societesSynthese  = [];  // [socName => [stats...]]

foreach ($societes as $societeDir) {
    $socName = strtoupper(basename($societeDir));
    $filesEco = glob($societeDir . '/*co-conduite*.xlsx');
    $filesEco = array_filter($filesEco, fn($f) => strpos(basename($f), '~$') === false);
    $filesKilo = glob($societeDir . '/*Kilom*.xlsx');
    $fileKilo = !empty($filesKilo) ? $filesKilo[0] : null;
    if (empty($filesEco) || !$fileKilo) continue;

    $infractionData = [];
    foreach ($filesEco as $file) {
        if (stripos($file, 'infraction') !== false) {
            $infractionData = array_merge($infractionData, readExcelBySheetName($file, 'rapport'));
        }
    }
    $kiloData = readExcelBySheetName($fileKilo, 'Kilométrage');

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

    $vehicules = [];
    $rowsDetail = [];

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

        $scoreB = scoreAlertesCritiques($infractions);
        $scoreC = scoreAlertesParKm($infractions, $km);
        $scoreD = scoreHeuresConducte($heures);
        $scoreE = scoreKilometrage($km);
        $scoreF = scoreChargeConducte($scoreD['score'], $scoreE['score']);
        $scoreA = scoreNoteConduite((float)$scoreB['value'], (float)$scoreC['value'], (int)$scoreF['value']);
        $scoreG = scoreRisqueGlobal((float)$scoreA['value'], (int)$scoreB['value'], (float)$scoreC['value'], $scoreF['status']);

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
        $rowsDetail[] = [
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
    usort($rowsDetail, fn($a, $b) => strcmp($a['vehicule'], $b['vehicule']));

    $societesVehicules[$socName] = $vehicules;
    $societesDetail[$socName] = $rowsDetail;

    // Synthèse par société
    $nbV = count($vehicules);
    $totalNote = $totalKm = $totalInfr = 0;
    foreach ($vehicules as $v) {
        $totalNote += $v['note'];
        $totalKm   += $v['km_raw'];
        $totalInfr += $v['alertes'];
    }
    $totalAl100 = $totalKm > 0 ? round(($totalInfr * 100) / $totalKm, 2) : 0;
    $countRouge  = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'rouge'));
    $countOrange = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'orange'));
    $countVert   = count(array_filter($vehicules, fn($v) => $v['alertes_s'] === 'vert'));

    $societesSynthese[$socName] = [
        'nb'           => $nbV,
        'total_note'   => number_format($totalNote, 0, ',', ' '),
        'total_infr'   => $totalInfr,
        'total_km'     => number_format($totalKm, 0, ',', ' ') . ' Km',
        'moy100'       => $totalAl100,
        'count_rouge'  => $countRouge,
        'count_orange' => $countOrange,
        'count_vert'   => $countVert,
    ];
}

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

// ── 1. Rapport Flotte Transport (par société) ─────────────
function buildHtmlFlotte(array $rows, string $socName, string $date, string $css, string $periode): string {
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
    $titre = 'Rapport Flotte Transport ' . htmlspecialchars($socName);
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
    <div class="header"><h1>' . $titre . '</h1><p>Indicateurs selon matrice CIMAT officielle &mdash; Période : ' . $periode . ' &mdash; Généré le ' . $date . '</p></div>
    <table class="data"><thead><tr>
        <th style="text-align:center;">Véhicule</th><th style="text-align:center;">Note /100 </th><th style="text-align:center;">Alertes CRIT </th><th style="text-align:center;">Alertes /100km </th>
        <th style="text-align:center;">Heures </th><th style="text-align:center;">Km </th><th style="text-align:center;">Charge </th><th style="text-align:center;">Risque </th>
    </tr></thead><tbody>' . $lignes . '</tbody></table>
    <div class="footer">' . $titre . ' &middot; ' . count($rows) . ' véhicule(s) &middot; ' . $date . '</div>
    </body></html>';
}

// ── 2. Détail par société ─────────────────────────────────
function buildHtmlDetail(array $rows, string $socName, string $date, string $css, string $periode): string {
    $lignes = '';
    foreach ($rows as $v) {
        $lignes .= '<tr>
            <td style="font-weight:700;color:#0f172a;">' . htmlspecialchars($socName) . '</td>
            <td>' . htmlspecialchars($v['vehicule'])    . '</td>
            <td style="font-size:10px;">' . htmlspecialchars($v['infraction']) . '</td>
            <td>' . htmlspecialchars($v['duree'])       . '</td>
            <td>' . htmlspecialchars($v['kilometrage']) . '</td>
            <td>' . colorCircleValue('rouge',  $v['alerts_rouge'])  . '</td>
            <td>' . colorCircleValue('orange', $v['alerts_orange']) . '</td>
            <td>' . colorCircleValue('vert',   $v['alerts_vert'])   . '</td>
        </tr>';
    }
    $titre = htmlspecialchars($socName) . ' — Détail par véhicule';
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
    <div class="header"><h1>' . $titre . '</h1><p>Source : fichiers Excel &mdash; Période : ' . $periode . ' &mdash; Généré le ' . $date . '</p></div>
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
    <div class="footer">' . htmlspecialchars($socName) . ' &middot; ' . count($rows) . ' véhicule(s) &middot; ' . $date . '</div>
    </body></html>';
}

// ── 3. Rapport Par Société (toutes les sociétés) ──────────
function buildHtmlSynthese(array $societesSynthese, string $date, string $css, string $periode): string {
    $lignes = '';
    $totalVehicules = 0;
    foreach ($societesSynthese as $socName => $s) {
        $lignes .= '<tr>
            <td style="font-weight:700;color:#0f172a;text-align:center;">' . htmlspecialchars($socName) . '</td>
            <td style="text-align:center;color:#1d4ed8;font-weight:600;">' . $s['nb']         . ' véhicules</td>
            <td style="text-align:center;color:#166534;font-weight:600;">' . $s['total_note']  . '</td>
            <td style="text-align:center;color:#991b1b;font-weight:600;">' . $s['total_infr']  . '</td>
            <td style="text-align:center;color:#1d4ed8;font-weight:600;">' . $s['total_km']    . '</td>
            <td style="text-align:center;color:#854d0e;font-weight:600;">' . $s['moy100']      . '</td>
            <td style="text-align:center;">' . colorCircleValue('rouge',  $s['count_rouge'])  . '</td>
            <td style="text-align:center;">' . colorCircleValue('orange', $s['count_orange']) . '</td>
            <td style="text-align:center;">' . colorCircleValue('vert',   $s['count_vert'])   . '</td>
        </tr>';
        $totalVehicules += $s['nb'];
    }
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '
    table.data { table-layout: fixed; }
    table.data th { padding: 10px 8px; }
    table.data td { padding: 10px 8px; }
    </style></head><body>
    <div class="header"><h1>Rapport Par Société</h1><p>Totaux calculés depuis les fichiers Excel &mdash; Période : ' . $periode . ' &mdash; Généré le ' . $date . '</p></div>
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
    </thead><tbody>' . $lignes . '</tbody></table>
    <div class="footer">Rapport Par Société &middot; ' . $totalVehicules . ' véhicule(s) &middot; ' . $date . '</div>
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
    foreach ($societesVehicules as $socName => $vehicules) {
        if ($doFlotte && !empty($vehicules)) {
            $safeName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $socName));
            $name = 'rapport_flotte_' . $safeName . '_' . date('Ymd_Hi') . '.pdf';
            $pdfFiles[] = ['path' => generatePdf(buildHtmlFlotte($vehicules, $socName, $dateGeneration, $css, $periodeLibelle), $name),
                           'name' => $name, 'label' => '🚗 Rapport Flotte Transport ' . $socName];
        }
    }
    foreach ($societesDetail as $socName => $rows) {
        if ($doDetail && !empty($rows)) {
            $safeName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $socName));
            $name = 'rapport_' . $safeName . '_detail_' . date('Ymd_Hi') . '.pdf';
            $pdfFiles[] = ['path' => generatePdf(buildHtmlDetail($rows, $socName, $dateGeneration, $css, $periodeLibelle), $name),
                           'name' => $name, 'label' => '🚛 ' . $socName . ' — Détail'];
        }
    }
    if ($doSynthese && !empty($societesSynthese)) {
        $name = 'rapport_par_societe_' . date('Ymd_Hi') . '.pdf';
        $pdfFiles[] = ['path' => generatePdf(buildHtmlSynthese($societesSynthese, $dateGeneration, $css, $periodeLibelle), $name),
                       'name' => $name, 'label' => '📈 Rapport Par Société'];
    }

} catch (\Exception $e) {
    die('❌ Erreur génération PDF : ' . htmlspecialchars($e->getMessage()));
}

$allPdfs = $pdfFiles;
if (empty($allPdfs)) {
    die('<div style="font-family:Arial;padding:30px;color:#dc2626;">
         ❌ Aucun rapport sélectionné ou aucune donnée disponible.<br>
         <a href="tableau.php" style="color:#2563eb;">← Retour</a></div>');
}

// ══════════════════════════════════════════════════════════
// ── HELPER ENVOI EMAIL ───────────────────────────────────
// ══════════════════════════════════════════════════════════
function smtpConfig(): array {
    return [
        'host'     => 'smtp.gmail.com',
        'username' => 'zakia.controlflot@gmail.com',
        'password' => 'vqnslggncuitnavh',
        'from'     => 'zakia.controlflot@gmail.com',
        'fromName' => 'Flotte Transport – Rapport Auto',
    ];
}

function sendEmailWithPdfs(array $pdfs, string $emailTo, string $sujetLabel, string $periodeLibelle, string $dateGeneration): void {
    $smtp = smtpConfig();
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtp['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp['username'];
    $mail->Password   = $smtp['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->setFrom($smtp['from'], $smtp['fromName']);
    $mail->addAddress($emailTo, 'Destinataire');
    $mail->CharSet = 'UTF-8';

    foreach ($pdfs as $pdf) {
        $mail->addAttachment($pdf['path'], $pdf['name']);
    }

    $labelsJoints = array_map(fn($p) => $p['label'], $pdfs);
    $listeRapports = implode('', array_map(fn($l) => '<li>' . htmlspecialchars($l) . '</li>', $labelsJoints));
    $nbRapports    = count($pdfs);

    $mail->isHTML(true);
    $mail->Subject = '📋 ' . $sujetLabel . ' – Période ' . $periodeLibelle;
    $mail->Body = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;">
        <h2 style="color:#0f172a;margin-bottom:4px">' . htmlspecialchars($sujetLabel) . '</h2>
        <p style="color:#64748b;font-size:14px">Période de traitement : <strong>' . htmlspecialchars($periodeLibelle) . '</strong></p>
        <p style="color:#64748b;font-size:14px">Généré automatiquement le ' . $dateGeneration . '</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
        <p style="font-size:14px;color:#334155">Vous trouverez en pièce jointe <strong>' . $nbRapports . ' rapport(s) PDF</strong> :</p>
        <ul style="font-size:13px;color:#475569;margin:10px 0 16px 20px">' . $listeRapports . '</ul>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
        <p style="font-size:12px;color:#94a3b8">Ce message est généré automatiquement – ne pas répondre.</p>
    </div>';
    $mail->AltBody = $sujetLabel . ' – ' . date('d/m/Y') . ' – Voir les pièces jointes PDF.';
    $mail->send();
}

// ══════════════════════════════════════════════════════════
// ── ENVOI EMAIL(S) ───────────────────────────────────────
// ══════════════════════════════════════════════════════════
$envoyes = [];
$erreurs = [];

// ── Email 1 : Rapports Flotte / Détail / Synthèse ───────
if (!empty($pdfFiles)) {
    try {
        $sujetLabel = count($pdfFiles) > 1 ? 'Rapports Flotte Transport' : $pdfFiles[0]['label'];
        sendEmailWithPdfs($pdfFiles, $email_to, $sujetLabel, $periodeLibelle, $dateGeneration);
        $envoyes = array_merge($envoyes, array_column($pdfFiles, 'label'));
        foreach ($pdfFiles as $pdf) { if (file_exists($pdf['path'])) unlink($pdf['path']); }
    } catch (Exception $e) {
        $erreurs[] = 'Rapports Flotte : ' . $mail->ErrorInfo ?? $e->getMessage();
        foreach ($pdfFiles as $pdf) { if (file_exists($pdf['path'])) unlink($pdf['path']); }
    }
}

// ══════════════════════════════════════════════════════════
// ── RÉSULTAT ─────────────────────────────────────────────
// ══════════════════════════════════════════════════════════
$flotteLabels    = array_column($pdfFiles, 'label');
$hadFlotte       = !empty($flotteLabels);
$hadParcours     = false;

$listeErreurs = empty($erreurs) ? '' : '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;margin:12px 0;text-align:left;font-size:.85rem;color:#dc2626;"><strong>Erreurs :</strong><ul>' . implode('', array_map(fn($e) => '<li>' . htmlspecialchars($e) . '</li>', $erreurs)) . '</ul></div>';

echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Email envoyé</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>body{font-family:"DM Sans",sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;border-radius:16px;padding:40px 48px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.08);max-width:520px}
.icon{font-size:3rem;margin-bottom:12px}h2{color:#0f172a;margin:0 0 8px}p{color:#64748b;font-size:.9rem;margin:0 0 8px}
.rapports{background:#f0f4f8;border-radius:8px;padding:10px 14px;margin:12px 0 20px;text-align:left;font-size:.85rem;color:#334155}
.rapports li{margin:4px 0}a{display:inline-block;background:#0f172a;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:.85rem}
a:hover{background:#1e293b}
.sep{margin:16px 0;padding:8px 0;border-top:2px solid #e2e8f0;font-size:.78rem;color:#94a3b8;font-weight:600;letter-spacing:0.5px;text-transform:uppercase}
</style></head><body>
<div class="box">
    <div class="icon">✅</div>
    <h2>Email(s) envoyé(s) !</h2>
    <p>Destinataire : <strong>' . htmlspecialchars($email_to) . '</strong></p>
    <p style="font-size:.8rem;color:#94a3b8;">Période : ' . htmlspecialchars($periodeLibelle) . '</p>';

if ($hadFlotte) {
    if ($hadParcours) echo '<div class="sep">Email 1 — Rapports Flotte</div>';
    echo '<ul class="rapports">' . implode('', array_map(fn($l) => '<li>' . htmlspecialchars($l) . '</li>', $flotteLabels)) . '</ul>';
}

if ($hadParcours) {
    if ($hadFlotte) echo '<div class="sep">Email 2 — Parcours (envoyé séparément)</div>';
    echo '<ul class="rapports">' . implode('', array_map(fn($l) => '<li>' . htmlspecialchars($l) . '</li>', $parcoursLabels)) . '</ul>';
}

echo $listeErreurs . '
    <a href="tableau.php">← Retour au tableau</a>
</div></body></html>';