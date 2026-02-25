<?php
/**
 * send_report.php — 100% depuis fichiers Excel
 * reports[] = ['flotte', 'boutcherafin', 'synthese']
 * 
 * ✨ AMÉLIORATIONS:
 * - Colonne Couleur avec sous-colonnes Rouge/Orange/Vert
 * - Cercles colorés simples et pleins
 * - Mise en page équilibrée avec espacement augmenté
 * - Cercles colorés dans les en-têtes des sous-colonnes
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
$dataDir    = 'C:/xampp/htdocs/vehicules/data/entreprises/';
$SHEET_ECO  = 'Éco-conduite';
$SHEET_KILO = 'Kilométrage+Heures moteur';

function findLatestFile(string $dir, string $pattern): ?string {
    $files = glob($dir . '*' . $pattern . '*.xlsx');
    if (empty($files)) return null;
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return $files[0];
}

function readExcelSheet(string $filePath, string $sheetName): array {
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

// ══════════════════════════════════════════════════════════
// ── CALCULS & BARÈMES ──────────────────────────────────────
// ══════════════════════════════════════════════════════════

function parseDureeToHours(string $d): float {
    $h = 0.0;
    if (preg_match('/(\d+)\s*jours?\s*/i', $d, $m)) { $h += (float)$m[1] * 24; $d = preg_replace('/\d+\s*jours?\s*/i', '', $d); }
    if (preg_match('/(\d+):(\d+):(\d+)/', $d, $m))  { $h += (float)$m[1] + (float)$m[2]/60 + (float)$m[3]/3600; }
    elseif (preg_match('/(\d+):(\d+)/', $d, $m))     { $h += (float)$m[1] + (float)$m[2]/60; }
    return round($h, 1);
}

function sNote(float $v): string    { return $v >= 80 ? 'vert' : ($v >= 60 ? 'orange' : 'rouge'); }
function sAlertes(int $v): string   { return $v <= 2  ? 'vert' : ($v <= 10 ? 'orange' : 'rouge'); }
function sAl100(float $v): string   { return $v < 0.5 ? 'vert' : ($v <= 1  ? 'orange' : 'rouge'); }
function sHeures(float $v): string  { return $v < 54  ? 'vert' : ($v <= 65 ? 'orange' : 'rouge'); }
function sCharge(float $h, float $km): string {
    $sh = sHeures($h);
    $sk = $km >= 5000 ? 'rouge' : ($km >= 4000 ? 'orange' : 'vert');
    if ($sh === 'rouge' || $sk === 'rouge') return 'rouge';
    if ($sh === 'orange' || $sk === 'orange') return 'orange';
    return 'vert';
}
function chargeLabel(string $s): string { return ['vert'=>'Faible','orange'=>'Moyenne','rouge'=>'Élevée'][$s] ?? '—'; }
function sRisque(string $sN, string $sA, string $sA1, string $sC): string {
    $scores = array_map(fn($x) => ['vert'=>0,'orange'=>1,'rouge'=>2][$x] ?? 0, [$sN,$sA,$sA1,$sC]);
    $avg = array_sum($scores)/count($scores);
    return max($scores)===2 || $avg>=1.5 ? 'rouge' : ($avg>=0.5 ? 'orange' : 'vert');
}
function risqueLabel(string $s): string { return ['vert'=>'Faible','orange'=>'Modéré','rouge'=>'Élevé'][$s] ?? '—'; }

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

// ✨ Cercles colorés à côté des valeurs numériques (compatible mPDF)
function colorCircleValue(string $color, int $value): string {
    $colors = ['rouge' => '#ef4444', 'orange' => '#f97316', 'vert' => '#22c55e'];
    $c = $colors[$color] ?? '#d1d5db';
    return '<span style="color:' . $c . ';font-size:14px;margin-right:4px;">●</span>'
         . '<span style="font-weight:700;color:#1e293b;font-size:12px;">' . $value . '</span>';
}

// ── Cercle coloré pour les en-têtes (seulement le cercle, pas de texte)
function colorCircleHeader(string $color): string {
    $colors = ['rouge' => '#ef4444', 'orange' => '#f97316', 'vert' => '#22c55e'];
    $c = $colors[$color] ?? '#d1d5db';
    return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . $c . ';vertical-align:middle;"></span>';
}

// ══════════════════════════════════════════════════════════
// ── LECTURE FICHIERS EXCEL ────────────────────────────────
// ══════════════════════════════════════════════════════════
$fileEco  = findLatestFile($dataDir, 'co-conduite');
$fileKilo = findLatestFile($dataDir, 'om');

if (!$fileEco || !$fileKilo) {
    die('<div style="font-family:Arial;padding:30px;color:#dc2626;">
         ❌ Fichier(s) Excel introuvable(s) dans <code>' . htmlspecialchars($dataDir) . '</code><br>
         <a href="tableau.php" style="color:#2563eb;">← Retour</a></div>');
}

$ecoData  = readExcelSheet($fileEco,  $SHEET_ECO);
$kiloData = readExcelSheet($fileKilo, $SHEET_KILO);

// Mapping éco par véhicule
$ecoMap = [];
foreach ($ecoData as $row) {
    $reg  = $row['Regroupement'] ?? '';
    $eval = $row['Évaluation']   ?? '';
    $infr = $row['Infraction']   ?? '';
    if ($reg === '') continue;
    if (!isset($ecoMap[$reg])) $ecoMap[$reg] = ['evaluation' => 0.0, 'infractions' => 0, 'infr_list' => []];
    $evalNum = (float) preg_replace('/[^0-9.]/', '', $eval);
    if ($evalNum > $ecoMap[$reg]['evaluation']) $ecoMap[$reg]['evaluation'] = $evalNum;
    if ($infr !== '' && $infr !== '-----') {
        $ecoMap[$reg]['infractions']++;
        $ecoMap[$reg]['infr_list'][] = $infr;
    }
}

// Construction des données véhicules (pour flotte + synthèse)
$vehicules  = [];
$rowsBoutch = [];

foreach ($kiloData as $row) {
    $reg  = $row['Regroupement'] ?? '';
    $info = $ecoMap[$reg] ?? null;
    if ($reg === '' || $info === null) continue;

    $dureeRaw = $row['Durée']        ?? '0';
    $kmRaw    = $row['Kilométrage']  ?? '0';
    $heures   = parseDureeToHours($dureeRaw);
    $km       = (float) preg_replace('/[^0-9.]/', '', $kmRaw);
    $note     = $info['evaluation'] / 10;
    $alertes  = $info['infractions'];
    $al100    = $km > 0 ? round(($alertes / $km) * 100, 2) : 0;

    $sN  = sNote($note * 100);
    $sA  = sAlertes($alertes);
    $sA1 = sAl100($al100);
    $sH  = sHeures($heures);
    $sC  = sCharge($heures, $km);
    $sR  = sRisque($sN, $sA, $sA1, $sC);

    // ✨ NOUVEAU: Déterminer la couleur des alertes
    $color_alertes = sAlertes($alertes);
    $alerts_vert = 0;
    $alerts_orange = 0;
    $alerts_rouge = 0;
    
    if ($color_alertes === 'vert') {
        $alerts_vert = $alertes;
    } elseif ($color_alertes === 'orange') {
        $alerts_orange = $alertes;
    } else { // rouge
        $alerts_rouge = $alertes;
    }

    // Pour rapport Flotte
    $vehicules[] = [
        'nom'      => $reg,
        'note'     => $note * 100,     'note_s'    => $sN,
        'alertes'  => $alertes,  'alertes_s' => $sA,
        'heures'   => $heures,   'heures_s'  => $sH,
        'km'       => number_format($km, 0, ',', ' ') . ' km', 'km_s' => ($km >= 5000 ? 'rouge' : ($km >= 4000 ? 'orange' : 'vert')),
        'al100'    => $al100,    'al100_s'   => $sA1,
        'charge'   => chargeLabel($sC), 'charge_s' => $sC,
        'risque'   => risqueLabel($sR), 'risque_s' => $sR,
        'km_raw'   => $km,
        'alerts_vert' => $alerts_vert,
        'alerts_orange' => $alerts_orange,
        'alerts_rouge' => $alerts_rouge,
    ];

    // Pour rapport BOUTCHERAFIN détail
    $infractions = array_unique($info['infr_list']);
    $rowsBoutch[] = [
        'vehicule'    => $reg,
        'infraction'  => !empty($infractions) ? implode(' / ', $infractions) : '—',
        'duree'       => $dureeRaw,
        'kilometrage' => $kmRaw,
        'alerts_vert' => $alerts_vert,
        'alerts_orange' => $alerts_orange,
        'alerts_rouge' => $alerts_rouge,
    ];
}

// Calculs synthèse
$nbV = count($vehicules);
$totalEval = $totalKm = $totalInfr = 0;
foreach ($vehicules as $v) {
    $totalEval += $v['note'];
    $totalKm   += $v['km_raw'];
    $totalInfr += $v['alertes'];
}
$moy100   = $totalKm > 0 ? round(($totalInfr / $totalKm) * 100, 2) : 0;

// Comptage par couleur pour synthèse
$countRouge = $countOrange = $countVert = 0;
foreach ($vehicules as $v) {
    $couleur = sAlertes($v['alertes']);
    if ($couleur === 'rouge') $countRouge++;
    elseif ($couleur === 'orange') $countOrange++;
    else $countVert++;
}

$synthese = [
    'nb'         => $nbV,
    'total_eval' => number_format($totalEval, 0, ',', ' '),
    'total_infr' => $totalInfr,
    'total_km'   => number_format($totalKm, 0, ',', ' ') . ' Km',
    'moy100'     => $moy100,
    'count_rouge' => $countRouge,
    'count_orange' => $countOrange,
    'count_vert' => $countVert,
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
    table.data thead tr:last-child th:nth-child(7){color:#ef4444;}
    table.data thead tr:last-child th:nth-child(8){color:#f97316;}
    table.data thead tr:last-child th:nth-child(9){color:#22c55e;}
    table.data tbody tr:nth-child(even){background:#f8fafc;}
    table.data tbody tr:nth-child(odd){background:#ffffff;}
    table.data tbody td{border-bottom:1px solid #e2e8f0;vertical-align:middle;padding:10px 16px;color:#334155;}
    table.data tbody td:nth-child(7),table.data tbody td:nth-child(8),table.data tbody td:nth-child(9){text-align:center;padding:10px 20px;}
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
            <td style="font-weight:700;color:#0f172a;white-space:nowrap;">' . htmlspecialchars($v['nom']) . '</td>
            <td>' . dotPdf($v['note_s'],    (string)$v['note'])    . '</td>
            <td>' . dotPdf($v['alertes_s'], (string)$v['alertes']) . '</td>
            <td>' . dotPdf($v['heures_s'],  $v['heures'] . ' h')   . '</td>
            <td>' . dotPdf($v['km_s'],      $v['km'])               . '</td>
            <td>' . dotPdf($v['al100_s'],   (string)$v['al100'])   . '</td>
            <td>' . pillPdf($v['charge_s'], $v['charge'])           . '</td>
            <td>' . pillPdf($v['risque_s'], $v['risque'])           . '</td>
        </tr>';
    }
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
    <div class="header"><h1>Rapport Flotte Transport BOUTCHERAFIN</h1><p>Source : fichiers Excel &mdash; Généré le ' . $date . '</p></div>
    <table class="data"><thead><tr>
        <th>Véhicule</th><th>Note /100</th><th>Alertes CRIT</th><th>Heures conduite (h)</th>
        <th>Kilométrage (km)</th><th>Alertes /100km</th><th>Charge conduite</th><th>Risque global</th>
    </tr></thead><tbody>' . $lignes . '</tbody></table>
    <div class="footer">Rapport Flotte Transport BOUTCHERAFIN &middot; ' . count($rows) . ' véhicule(s) &middot; ' . $date . '</div>
    </body></html>';
}

// ── 2. BOUTCHERAFIN Détail ────────────────────────────────
// ✨ AMÉLIORÉ: Avec colonne Couleur et cercles dans les en-têtes
function buildHtmlBoutch(array $rows, string $date, string $css): string {
    $lignes = '';
    foreach ($rows as $i => $v) {
        $lignes .= '<tr>
            <td style="font-weight:700;color:#0f172a;">BOUTCHERAFIN</td>
            <td>' . htmlspecialchars($v['vehicule'])   . '</td>
            <td style="font-size:10px;">' . htmlspecialchars($v['infraction']) . '</td>
            <td>' . htmlspecialchars($v['duree'])      . '</td>
            <td>' . htmlspecialchars($v['kilometrage']). '</td>
            <td>' . colorCircleValue('rouge', $v['alerts_rouge']) . '</td>
            <td>' . colorCircleValue('orange', $v['alerts_orange']) . '</td>
            <td>' . colorCircleValue('vert', $v['alerts_vert']) . '</td>
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
            <th>Rouge</th>
            <th>Orange</th>
            <th>Vert</th>
        </tr>
    </thead><tbody>' . $lignes . '</tbody></table>
    <div class="footer">BOUTCHERAFIN &middot; ' . count($rows) . ' véhicule(s) &middot; ' . $date . '</div>
    </body></html>';
}

// ── 3. Rapport Par Société ────────────────────────────────
// ✨ AMÉLIORÉ: Avec colonne Couleur et cercles dans les en-têtes
function buildHtmlSynthese(array $s, string $date, string $css): string {
    $ligne = '<tr>
        <td style="font-weight:700;color:#0f172a;">BOUTCHERAFIN</td>
        <td style="text-align:center;color:#1d4ed8;font-weight:600;">' . $s['nb']         . ' véhicules</td>
        <td style="text-align:center;color:#166534;font-weight:600;">' . $s['total_eval'] . '</td>
        <td style="text-align:center;color:#991b1b;font-weight:600;">' . $s['total_infr'] . '</td>
        <td style="text-align:center;color:#1d4ed8;font-weight:600;">' . $s['total_km']   . '</td>
        <td style="text-align:center;color:#854d0e;font-weight:600;">' . $s['moy100']     . '</td>
        <td>' . colorCircleValue('rouge', $s['count_rouge']) . '</td>
        <td>' . colorCircleValue('orange', $s['count_orange']) . '</td>
        <td>' . colorCircleValue('vert', $s['count_vert']) . '</td>
    </tr>';
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
    <div class="header"><h1>Rapport Par Société</h1><p>Totaux calculés depuis les fichiers Excel &mdash; Généré le ' . $date . '</p></div>
    <table class="data"><thead>
        <tr>
            <th>Entreprise</th><th style="text-align:center;">Nb véhicules</th><th style="text-align:center;">Total Évaluation</th>
            <th style="text-align:center;">Total Infractions</th><th style="text-align:center;">Total Kilométrage</th><th style="text-align:center;">Moy. Infr. /100km</th>
            <th colspan="3" style="text-align:center;">Couleur</th>
        </tr>
        <tr>
            <th colspan="6"></th>
            <th>Rouge</th>
            <th>Orange</th>
            <th>Vert</th>
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