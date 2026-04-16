<?php
/**
 * send_report_services.php — PDF Véhicules de Service + envoi email
 * 1 PDF : Statistiques + Sommaire + Parcours
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use Mpdf\Mpdf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

$dateGeneration = date('d/m/Y à H:i');

// ── Période ──
$dateFrom = $_GET['date_from'] ?? '2026-03-01';
$dateTo   = $_GET['date_to'] ?? '2026-03-31';
$periodeLibelle = (new DateTime($dateFrom))->format('d/m/Y') . ' au ' . (new DateTime($dateTo))->format('d/m/Y');

$email_to = filter_var(trim($_GET['email_to'] ?? ''), FILTER_VALIDATE_EMAIL)
    ? trim($_GET['email_to']) : MAIL_TO;

// ══════════════════════════════════════════════════════════
// ── HELPERS ───────────────────────────────────────────────
// ══════════════════════════════════════════════════════════
function readSommaire(string $filePath): array {
    try {
        $sp = IOFactory::load($filePath);
        $sh = null;
        foreach ($sp->getSheetNames() as $n) { if (stripos($n, 'Sommaire') !== false) { $sh = $sp->getSheetByName($n); break; } }
        if (!$sh) return [];
        $rows = $sh->toArray(null, true, true, false);
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

function readParcoursSheet(string $filePath): array {
    try {
        $sp = IOFactory::load($filePath);
        $sh = null;
        foreach ($sp->getSheetNames() as $n) { if (stripos($n, 'Parcours') !== false) { $sh = $sp->getSheetByName($n); break; } }
        if (!$sh) return [];
        $rows = $sh->toArray(null, true, true, false);
        if (empty($rows)) return [];
        $headers = array_map(fn($h) => trim((string)($h ?? '')), array_shift($rows));
        $data = [];
        foreach ($rows as $row) {
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) continue;
            $assoc = [];
            foreach ($headers as $i => $h) { $assoc[$h] = trim((string)($row[$i] ?? '')); }
            if (($assoc['Regroupement'] ?? '') === '-----' || ($assoc['Regroupement'] ?? '') === 'Total ') continue;
            $data[] = $assoc;
        }
        return $data;
    } catch (\Exception $e) { return []; }
}

function fileLabel(string $path): string {
    $n = basename($path, '.xlsx');
    $n = preg_replace('/_\d{2}-\d{2}-\d{4}_\d{2}-\d{2}-\d{2}$/', '', $n);
    return str_replace('_', ' ', $n);
}

function parseDureeToHours(string $duree): float {
    $h = 0.0;
    if (preg_match('/(\d+)\s*jours?\s*/i', $duree, $m)) { $h += (float)$m[1] * 24; $duree = preg_replace('/\d+\s*jours?\s*/i', '', $duree); }
    if (preg_match('/(\d+):(\d+):(\d+)/', $duree, $m)) { $h += (float)$m[1] + (float)$m[2]/60 + (float)$m[3]/3600; }
    elseif (preg_match('/(\d+):(\d+)/', $duree, $m)) { $h += (float)$m[1] + (float)$m[2]/60; }
    return round($h, 1);
}

function resolveParcoursColumns(array $rows): array {
    if (empty($rows)) return [];
    $rawHeaders = array_keys($rows[0]);
    $colDefs = [
        ['label' => 'N°',          'keys' => ['№', 'N°', 'Numero']],
        ['label' => 'Véhicule',    'keys' => ['Regroupement']],
        ['label' => 'Parcours',    'keys' => ['Parcours']],
        ['label' => 'Départ de',   'keys' => ['Départ de ', 'Départ de']],
        ['label' => 'Trajet vers', 'keys' => ['Trajet vers']],
        ['label' => 'Début',       'keys' => ['Début']],
        ['label' => 'Fin',         'keys' => ['Fin']],
    ];
    $resolved = [];
    foreach ($colDefs as $col) {
        foreach ($col['keys'] as $key) {
            foreach ($rawHeaders as $rh) {
                if (strtolower(trim($rh)) === strtolower(trim($key))) {
                    $resolved[] = ['label' => $col['label'], 'rawKey' => $rh];
                    break 2;
                }
            }
        }
    }
    return $resolved;
}

function colorCircleValue(string $color, $value): string {
    $colors = ['rouge' => '#ef4444', 'orange' => '#f97316', 'vert' => '#22c55e'];
    $c = $colors[$color] ?? '#d1d5db';
    return '<span style="color:' . $c . ';font-size:14px;margin-right:4px;">●</span>'
         . '<span style="font-weight:700;color:#1e293b;font-size:12px;">' . $value . '</span>';
}

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

// ── Scoring functions (same as services_tableau.php) ──
function scoreNoteConduite(float $alertes, float $alertesParKm, int $charge): array {
    $note = 100 - ($alertes * 2) - ($alertesParKm * 10) - ($charge * 5);
    $note = max(0, $note);
    if ($note >= 80) $status = 'vert'; elseif ($note >= 60) $status = 'orange'; else $status = 'rouge';
    return ['value' => round($note, 0), 'status' => $status];
}
function scoreAlertesCritiques(int $alertes): array {
    if ($alertes <= 2) $status = 'vert'; elseif ($alertes <= 10) $status = 'orange'; else $status = 'rouge';
    return ['value' => $alertes, 'status' => $status];
}
function scoreAlertesParKm(int $alertes, float $km): array {
    $ratio = $km > 0 ? round(($alertes * 100) / $km, 2) : 0;
    if ($ratio < 0.5) $status = 'vert'; elseif ($ratio <= 1) $status = 'orange'; else $status = 'rouge';
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
function scoreRisqueGlobal(float $note, int $alertes, float $alertesParKm, string $chargeStatus): array {
    if ($note >= 85 && $alertes < 15 && $alertesParKm < 1) { $risque = 'Faible'; $status = 'vert'; }
    elseif ($note >= 70 && $alertes < 15 && $alertesParKm < 1) { $risque = 'Modéré'; $status = 'orange'; }
    elseif ($note >= 55 && $alertes < 15 && $alertesParKm < 1) { $risque = 'Élevé'; $status = 'orange'; }
    else { $risque = 'Critique'; $status = 'rouge'; }
    return ['label' => $risque, 'status' => $status];
}

// ══════════════════════════════════════════════════════════
// ── CSS ──────────────────────────────────────────────────
// ══════════════════════════════════════════════════════════
$css = '
    body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;margin:0;padding:0;background:#fff;}
    .header{background:#f1f5f9;color:#0f172a;padding:14px 18px;margin-bottom:16px;border-radius:6px;border-left:5px solid #3b82f6;text-align:center;}
    .header h1{margin:0 0 3px;font-size:15px;font-weight:700;color:#0f172a;text-align:center;}
    .header p{margin:0;font-size:10px;color:#64748b;text-align:center;}
    table.data{width:100%;border-collapse:collapse;font-size:11px;}
    table.data thead tr{background:#e2e8f0;}
    table.data thead tr:first-child th{color:#334155;padding:10px 12px;text-align:left;font-size:9px;
                        text-transform:uppercase;letter-spacing:0.5px;font-weight:700;border-bottom:2px solid #cbd5e1;}
    table.data thead tr:last-child th{color:#334155;padding:8px 12px;text-align:center;font-size:8px;
                        text-transform:uppercase;letter-spacing:0.5px;font-weight:700;border-bottom:2px solid #cbd5e1;}
    table.data tbody tr:nth-child(even){background:#f8fafc;}
    table.data tbody tr:nth-child(odd){background:#ffffff;}
    table.data tbody td{border-bottom:1px solid #e2e8f0;vertical-align:middle;padding:9px 12px;color:#334155;}
    .footer{margin-top:16px;font-size:9px;color:#94a3b8;text-align:center;border-top:1px solid #e2e8f0;padding-top:10px;}
';

// ══════════════════════════════════════════════════════════
// ── BUILD HTML (3 sections) ──────────────────────────────
// ══════════════════════════════════════════════════════════
$servicesDir = 'C:/xampp/htdocs/vehicules/data/services/';
$svcFiles = array_filter(glob($servicesDir . '*Activités_Chauffeurs*.xlsx'), fn($f) => strpos(basename($f), '~$') === false);
if (empty($svcFiles)) $svcFiles = array_filter(glob($servicesDir . '*.xlsx'), fn($f) => strpos(basename($f), '~$') === false);

// ── Statistiques + Sommaire (calculs complets kifma services_tableau.php) ──
$statRows = [];
$fileTables = [];  // per-file vehicles with full scores for Sommaire
$order = ['Véhicule de services BA', 'Véhicule de services BM', 'SIEGE'];

foreach ($svcFiles as $sf) {
    $label = fileLabel($sf);
    $sommaire = readSommaire($sf);
    $vehs = [];
    foreach ($sommaire as $row) {
        $reg = $row['Regroupement'] ?? '';
        if ($reg === '') continue;
        $km = (float) preg_replace('/[^0-9.]/', '', $row['Kilométrage dans les missions'] ?? '0');
        $pen = (int) preg_replace('/[^0-9]/', '', $row['Pénalités'] ?? '0');
        $heures = parseDureeToHours($row['Temps de déplacement'] ?? '0');

        // Full scoring
        $scoreB = scoreAlertesCritiques($pen);
        $scoreC = scoreAlertesParKm($pen, $km);
        $scoreD = scoreHeuresConducte($heures);
        $scoreE = scoreKilometrage($km);
        $scoreF = scoreChargeConducte($scoreD['score'], $scoreE['score']);
        $scoreA = scoreNoteConduite((float)$scoreB['value'], (float)$scoreC['value'], (int)$scoreF['value']);
        $scoreG = scoreRisqueGlobal((float)$scoreA['value'], (int)$scoreB['value'], (float)$scoreC['value'], $scoreF['status']);

        $vehs[] = [
            'nom' => $reg,
            'note' => $scoreA['value'], 'note_s' => $scoreA['status'],
            'alertes' => $scoreB['value'], 'alertes_s' => $scoreB['status'],
            'al100' => $scoreC['value'], 'al100_s' => $scoreC['status'],
            'heures' => $scoreD['value'], 'heures_s' => $scoreD['status'],
            'km' => $km,
            'charge' => $scoreF['label'], 'charge_s' => $scoreF['status'],
            'risque' => $scoreG['label'], 'risque_s' => $scoreG['status'],
        ];
    }

    // Stats for Statistiques section
    $nbV = count($vehs);
    $tNote = $tPen = $tKm = 0; $rouge = $orange = $vert = 0;
    foreach ($vehs as $v) {
        $tNote += $v['note']; $tPen += $v['alertes']; $tKm += $v['km'];
        if ($v['alertes_s'] === 'rouge') $rouge++;
        elseif ($v['alertes_s'] === 'orange') $orange++;
        else $vert++;
    }
    $moy100 = $tKm > 0 ? round(($tPen * 100) / $tKm, 2) : 0;
    $statRows[] = ['nom' => $label, 'nb' => $nbV, 'totalNote' => $tNote, 'pen' => $tPen, 'km' => $tKm, 'moy100' => $moy100, 'rouge' => $rouge, 'orange' => $orange, 'vert' => $vert];

    usort($vehs, fn($a, $b) => strcmp($a['nom'], $b['nom']));
    $fileTables[] = ['label' => $label, 'vehicules' => $vehs];
}

// Sort statRows by order (BA → BM → SIEGE)
usort($statRows, function($a, $b) use ($order) {
    $pA = array_search($a['nom'], $order); if ($pA === false) $pA = 99;
    $pB = array_search($b['nom'], $order); if ($pB === false) $pB = 99;
    return $pA <=> $pB;
});

// Sort fileTables by order
usort($fileTables, function($a, $b) use ($order) {
    $pA = array_search($a['label'], $order); if ($pA === false) $pA = 99;
    $pB = array_search($b['label'], $order); if ($pB === false) $pB = 99;
    return $pA <=> $pB;
});

// ── Build Statistiques HTML ──
$statHtml = '';
foreach ($statRows as $s) {
    $statHtml .= '<tr>
        <td style="font-weight:700;text-align:center;">' . htmlspecialchars($s['nom']) . '</td>
        <td style="text-align:center;">' . $s['nb'] . '</td>
        <td style="text-align:center;color:#166534;font-weight:600;">' . number_format($s['totalNote'], 0, ',', ' ') . '</td>
        <td style="text-align:center;color:#991b1b;font-weight:600;">' . $s['pen'] . '</td>
        <td style="text-align:center;">' . number_format($s['km'], 0, ',', ' ') . ' Km</td>
        <td style="text-align:center;">' . $s['moy100'] . '</td>
        <td style="text-align:center;">' . colorCircleValue('rouge', $s['rouge']) . '</td>
        <td style="text-align:center;">' . colorCircleValue('orange', $s['orange']) . '</td>
        <td style="text-align:center;">' . colorCircleValue('vert', $s['vert']) . '</td>
    </tr>';
}

// ── Build Sommaire HTML (3 tableaux khol: BA, BM, SIEGE) ──
$sommaireHtml = '';
$totalVehicules = 0;
foreach ($fileTables as $ft) {
    $totalVehicules += count($ft['vehicules']);
    $sommaireHtml .= '<div style="margin-top:14px;margin-bottom:6px;font-size:13px;font-weight:700;color:#0f172a;">'
        . htmlspecialchars($ft['label']) . '</div>';
    if (empty($ft['vehicules'])) {
        $sommaireHtml .= '<p style="color:#94a3b8;font-size:10px;">Aucune donnée</p>';
        continue;
    }
    $sommaireHtml .= '<table class="data"><thead><tr>
        <th>Véhicule</th><th>Note /100</th><th>Alertes CRIT</th><th>Alertes / 100 km</th>
        <th>Heures</th><th>Km</th><th>Charge</th><th>Risque</th>
    </tr></thead><tbody>';
    foreach ($ft['vehicules'] as $v) {
        $sommaireHtml .= '<tr>
            <td style="font-weight:700;">' . htmlspecialchars($v['nom']) . '</td>
            <td>' . dotPdf($v['note_s'], (string)$v['note']) . '</td>
            <td>' . dotPdf($v['alertes_s'], (string)$v['alertes']) . '</td>
            <td>' . dotPdf($v['al100_s'], (string)$v['al100']) . '</td>
            <td>' . dotPdf($v['heures_s'], $v['heures'] . ' h') . '</td>
            <td>' . dotPdf($v['km'] >= 5000 ? 'rouge' : ($v['km'] >= 4000 ? 'orange' : 'vert'), number_format($v['km'], 0, ',', ' ') . ' km') . '</td>
            <td>' . pillPdf($v['charge_s'], $v['charge']) . '</td>
            <td>' . pillPdf($v['risque_s'], $v['risque']) . '</td>
        </tr>';
    }
    $sommaireHtml .= '</tbody></table>';
}

// ── Parcours (3 tableaux khol: BA, BM, SIEGE) ──
$parcSection = '';
$totalParcours = 0;
$parcoursCount = 0;

// Sort files by order
usort($svcFiles, function($a, $b) use ($order) {
    $lA = fileLabel($a); $lB = fileLabel($b);
    $pA = array_search($lA, $order); if ($pA === false) $pA = 99;
    $pB = array_search($lB, $order); if ($pB === false) $pB = 99;
    return $pA <=> $pB;
});

foreach ($svcFiles as $pf) {
    $pRows = readParcoursSheet($pf);
    $pCols = resolveParcoursColumns($pRows);
    if (empty($pRows) || empty($pCols)) continue;

    $pLabel = fileLabel($pf);
    $totalParcours += count($pRows);
    $parcoursCount++;

    // Build header
    $pHeaderHtml = '';
    foreach ($pCols as $col) {
        $align = ($col['label'] === 'N°') ? 'text-align:center;' : '';
        $pHeaderHtml .= '<th style="' . $align . '">' . htmlspecialchars($col['label']) . '</th>';
    }

    // Build rows
    $pBodyHtml = '';
    foreach ($pRows as $row) {
        $tdCells = ''; $num = '';
        foreach ($pCols as $col) {
            $val = $row[$col['rawKey']] ?? '—';
            if ($col['label'] === 'N°') $num = $val;
            $align = ($col['label'] === 'N°') ? 'text-align:center;' : '';
            $tdCells .= '<td style="' . $align . 'font-size:9px;">' . htmlspecialchars($val) . '</td>';
        }
        $isMain = ($num !== '' && $num !== '-----' && strpos($num, '.') === false);
        $style = $isMain ? 'background:#f0f9ff;font-weight:600;' : 'color:#64748b;font-size:9px;';
        $pBodyHtml .= '<tr style="' . $style . '">' . $tdCells . '</tr>';
    }

    $parcSection .= '<div style="margin-top:14px;margin-bottom:6px;font-size:13px;font-weight:700;color:#0f172a;">'
        . htmlspecialchars($pLabel) . ' — ' . count($pRows) . ' ligne(s)</div>'
        . '<table class="data"><thead><tr>' . $pHeaderHtml . '</tr></thead><tbody>' . $pBodyHtml . '</tbody></table>';
}

if ($parcSection !== '') {
    $parcSection = '
    <div class="header" style="margin-top:20px;"><h1>Parcours</h1><p>Véhicules de Service &mdash; Période : ' . $periodeLibelle . ' &mdash; Généré le ' . $dateGeneration . '</p></div>'
    . $parcSection . '
    <div class="footer">Parcours &middot; ' . $totalParcours . ' ligne(s) &middot; ' . $parcoursCount . ' tableau(x) &middot; ' . $dateGeneration . '</div>';
}

$html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
<div class="header"><h1>Statistiques</h1><p>Véhicules de Service &mdash; Période : ' . $periodeLibelle . ' &mdash; Généré le ' . $dateGeneration . '</p></div>
<table class="data"><thead>
    <tr><th style="text-align:center;">Source</th><th style="text-align:center;">Nb véhicules</th>
        <th style="text-align:center;">Total Note /100</th><th style="text-align:center;">Total Pénalités</th>
        <th style="text-align:center;">Total Kilométrage</th><th style="text-align:center;">Moy. Pén. /100km</th>
        <th colspan="3" style="text-align:center;">Véhicules par statut</th></tr>
    <tr><th colspan="6"></th>
        <th style="text-align:center;color:#ef4444;">Rouge</th>
        <th style="text-align:center;color:#f97316;">Orange</th>
        <th style="text-align:center;color:#22c55e;">Vert</th></tr>
</thead><tbody>' . $statHtml . '</tbody></table>
<div class="footer">Statistiques &middot; ' . count($statRows) . ' source(s) &middot; ' . $dateGeneration . '</div>

<div class="header" style="margin-top:20px;"><h1>Sommaires</h1><p>Véhicules de Service &mdash; Période : ' . $periodeLibelle . ' &mdash; Généré le ' . $dateGeneration . '</p></div>
' . $sommaireHtml . '
<div class="footer">Sommaires &middot; ' . $totalVehicules . ' véhicule(s) &middot; ' . $dateGeneration . '</div>

' . $parcSection . '
</body></html>';

// ══════════════════════════════════════════════════════════
// ── GÉNÉRATION PDF ───────────────────────────────────────
// ══════════════════════════════════════════════════════════
$pdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rapport_services_' . date('Ymd_Hi') . '.pdf';
$mpdf = new Mpdf(['mode'=>'utf-8','format'=>'A4-L',
                  'margin_top'=>10,'margin_bottom'=>10,'margin_left'=>12,'margin_right'=>12]);
$mpdf->SetAuthor('Véhicules de Service');
$mpdf->WriteHTML($html);
$mpdf->Output($pdfPath, 'F');

// ══════════════════════════════════════════════════════════
// ── ENVOI EMAIL ──────────────────────────────────────────
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
    $mail->setFrom('zakia.controlflot@gmail.com', 'Véhicules de Service – Rapport Auto');
    $mail->addAddress($email_to, 'Destinataire');
    $mail->CharSet = 'UTF-8';
    $mail->addAttachment($pdfPath, 'Rapport_Services_' . date('Ymd_Hi') . '.pdf');

    $mail->isHTML(true);
    $mail->Subject = '🚐 Rapport Véhicules de Service – Période ' . $periodeLibelle;
    $mail->Body = '
    <div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;">
        <h2 style="color:#0f172a;margin-bottom:4px">Rapport Véhicules de Service</h2>
        <p style="color:#64748b;font-size:14px">Période de traitement : <strong>' . htmlspecialchars($periodeLibelle) . '</strong></p>
        <p style="color:#64748b;font-size:14px">Généré automatiquement le ' . $dateGeneration . '</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
        <p style="font-size:14px;color:#334155">Vous trouverez en pièce jointe <strong>1 rapport PDF</strong> contenant :</p>
        <ul style="font-size:13px;color:#475569;margin:10px 0 16px 20px">
            <li>📊 Statistiques</li>
            <li>📋 Sommaire</li>
            <li>🛣️ Parcours</li>
        </ul>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
        <p style="font-size:12px;color:#94a3b8">Ce message est généré automatiquement – ne pas répondre.</p>
    </div>';
    $mail->AltBody = 'Rapport Véhicules de Service – ' . $periodeLibelle . ' – Voir pièce jointe PDF.';
    $mail->send();

    if (file_exists($pdfPath)) unlink($pdfPath);

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
        <p>Rapport envoyé à <strong>' . htmlspecialchars($email_to) . '</strong></p>
        <ul class="rapports">
            <li>📊 Statistiques</li>
            <li>📋 Sommaire</li>
            <li>🛣️ Parcours</li>
        </ul>
        <a href="services_tableau.php">← Retour au Sommaire</a>
    </div></body></html>';

} catch (Exception $e) {
    if (file_exists($pdfPath)) unlink($pdfPath);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title>
    <style>body{font-family:Arial,sans-serif;background:#fff0f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .box{background:#fff;border-radius:12px;padding:32px;max-width:480px;box-shadow:0 4px 16px rgba(0,0,0,.1)}
    h2{color:#dc2626}pre{background:#fef2f2;padding:12px;border-radius:8px;font-size:.8rem;overflow-x:auto}a{color:#2563eb}
    </style></head><body>
    <div class="box"><h2>❌ Erreur d\'envoi</h2><pre>' . htmlspecialchars($mail->ErrorInfo) . '</pre>
    <p><a href="services_tableau.php">← Retour</a></p></div></body></html>';
}
