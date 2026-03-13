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

function scoreNoteConduite(float $alertes, float $alertesParKm, int $charge): array {
    // Formule: 100 - (B * 2) - (C * 10) - (F * 5)
    $note = 100 - ($alertes * 2) - ($alertesParKm * 10) - ($charge * 5);
    $note = max(0, $note); // Ensure non-negative
    // Barèmes: Vert ≥80, Orange 60-79, Rouge <60
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
    // Formule: B * 100 / kilométrage
    $ratio = $km > 0 ? round(($alertes * 100) / $km, 2) : 0;
    if ($ratio < 0.5) $status = 'vert';
    elseif ($ratio <= 1) $status = 'orange';
    else $status = 'rouge';
    return ['value' => $ratio, 'status' => $status];
}

function scoreHeuresConducte(float $heures): array {
    // Barèmes: <40h=1 (Vert), 40-50h=2 (Orange), >50h=3 (Rouge)
    if ($heures < 40) { $score = 1; $status = 'vert'; }
    elseif ($heures <= 50) { $score = 2; $status = 'orange'; }
    else { $score = 3; $status = 'rouge'; }
    return ['value' => round($heures, 1), 'score' => $score, 'status' => $status];
}

function scoreKilometrage(float $km): array {
    if ($km < 4000) { $score = 1; $status = 'vert'; }
    elseif ($km <= 5000) { $score = 2; $status = 'orange'; }
    else { $score = 3; $status = 'rouge'; }
    return ['value' => $km, 'score' => $score, 'status' => $status, 'formatted' => number_format($km, 0, ',', ' ')];
}

function scoreChargeConducte(int $scoreHeures, int $scoreKm): array {
    // Formule: Score heures + Score km (au lieu du produit)
    $charge = $scoreHeures + $scoreKm;
    if ($charge == 2) { $label = 'Faible'; $status = 'vert'; }
    elseif ($charge >= 3 && $charge <= 4) { $label = 'Moyenne'; $status = 'orange'; }
    else { $label = 'Élevée'; $status = 'rouge'; }
    return ['value' => $charge, 'label' => $label, 'status' => $status];
}

function scoreRisqueGlobal(float $note, int $alertes, float $alertesParKm, string $chargeStatus): array {
    // Matrice CIMAT officielle
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

function dot(string $status): string {
    $colors = ['vert' => '#22c55e', 'orange' => '#f97316', 'rouge' => '#ef4444'];
    return '<span class="dot" style="background:' . ($colors[$status] ?? '#d1d5db') . '"></span>';
}

function pill(string $status, string $label): string {
    $map = ['vert' => ['#dcfce7', '#166534'], 'orange' => ['#ffedd5', '#9a3412'], 'rouge' => ['#fee2e2', '#991b1b']];
    [$bg, $tc] = $map[$status] ?? ['#f1f5f9', '#475569'];
    return '<span class="pill" style="background:' . $bg . ';color:' . $tc . ';">' . dot($status) . htmlspecialchars($label) . '</span>';
}

$vehicules = [];
if (empty($error)) {
    $infractionData = [];
    foreach ($filesEco as $file) {
        // Lire uniquement le fichier d'infractions (pas besoin du fichier Évaluation)
        if (stripos($file, 'infraction') !== false) {
            $infractionData = array_merge($infractionData, readExcelBySheetName($file, 'rapport'));
        }
    }
    $kiloData = readExcelBySheetName($fileKilo, 'Kilométrage');
    $normalize = function($str) {
        return trim(preg_replace('/\s+/', ' ', $str));
    };
    $ecoMap = [];
    foreach ($infractionData as $row) {
        $reg = $row['Regroupement'] ?? '';
        if ($reg === '') continue;
        $regNorm = $normalize($reg);
        if (!isset($ecoMap[$regNorm])) {
            $ecoMap[$regNorm] = ['infractions' => 0];
        }
        $infr = $row['Infraction'] ?? '';
        if ($infr !== '' && $infr !== '-----' && strtolower(trim($infr)) !== '----------') {
            $ecoMap[$regNorm]['infractions']++;
        }
    }
    foreach ($kiloData as $row) {
        $reg = $row['Regroupement'] ?? '';
        if ($reg === '') continue;
        $regNorm = $normalize($reg);
        $infractions = $ecoMap[$regNorm]['infractions'] ?? 0;
        $dureeVal = $row['Durée'] ?? '';
        $kmVal = $row['Kilométrage'] ?? '';
        $heures = parseDureeToHours($dureeVal);
        $km = (float) preg_replace('/[^0-9.]/', '', $kmVal);

        // Calculer B, C, D, E en premier (indépendants)
        $scoreB = scoreAlertesCritiques($infractions);
        $scoreC = scoreAlertesParKm($infractions, $km);
        $scoreD = scoreHeuresConducte($heures);
        $scoreE = scoreKilometrage($km);

        // Calculer F (dépend de D et E)
        $scoreF = scoreChargeConducte($scoreD['score'], $scoreE['score']);

        // Calculer A (dépend de B, C, F)
        $scoreA = scoreNoteConduite((float)$scoreB['value'], (float)$scoreC['value'], (int)$scoreF['value']);

        // Calculer G (dépend de A, B, C, F)
        $scoreG = scoreRisqueGlobal((float)$scoreA['value'], (int)$scoreB['value'], (float)$scoreC['value'], $scoreF['status']);

        $vehicules[] = [
            'nom' => $reg,
            'note' => $scoreA['value'], 'note_s' => $scoreA['status'],
            'alertes' => $scoreB['value'], 'alertes_s' => $scoreB['status'],
            'al100' => $scoreC['value'], 'al100_s' => $scoreC['status'],
            'heures' => $scoreD['value'], 'heures_s' => $scoreD['status'],
            'km' => $scoreE['formatted'] . ' km', 'km_s' => $scoreE['status'],
            'charge' => $scoreF['label'], 'charge_s' => $scoreF['status'],
            'risque' => $scoreG['label'], 'risque_s' => $scoreG['status'],
        ];
    }
    usort($vehicules, fn($a, $b) => strcmp($a['nom'], $b['nom']));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport Flotte Transport BOUTCHERAFIN</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .page-header { max-width: 1300px; margin: 0 auto 28px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; color: #0f172a; }
        .page-header p { font-size: 0.875rem; color: #64748b; margin-top: 4px; }
        .card { max-width: 1300px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        thead th { padding: 12px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        thead th:first-child { border-right: 1px solid #e2e8f0; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 11px 14px; font-size: 0.84rem; color: #334155; vertical-align: middle; }
        tbody td:first-child { font-weight: 600; color: #0f172a; border-right: 1px solid #e2e8f0; }
        .cell-indicator { display: flex; align-items: center; gap: 7px; }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 7px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .error-box { max-width: 1300px; margin: 0 auto; background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 16px 20px; color: #dc2626; }
        .meta { max-width: 1300px; margin: 12px auto 0; font-size: 0.78rem; color: #94a3b8; }
        .legende { max-width: 1300px; margin: 0 auto 16px; display: flex; gap: 20px; flex-wrap: wrap; align-items: center; font-size: 0.78rem; color: #64748b; }
        .legende-item { display: flex; align-items: center; gap: 5px; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; gap: 20px; }
        .btn-send, .btn-synthese {
            background: #0f172a;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
            white-space: nowrap;
            text-decoration: none;
        }
        .btn-send:hover, .btn-synthese:hover { background: #1e293b; }
        .btn-send span, .btn-synthese span { font-size: 1rem; }
        .buttons-group { display: flex; gap: 12px; }
        /* ── Send Panel ── */
        .send-panel {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,.45); backdrop-filter: blur(4px);
            z-index: 1000; display: none; align-items: center; justify-content: center;
        }
        .send-panel.active { display: flex; }
        .send-box {
            position: relative; background: #fff; width: 90%; max-width: 500px;
            border-radius: 16px; padding: 32px 36px;
            box-shadow: 0 20px 60px rgba(0,0,0,.18);
            animation: panelIn .3s ease-out;
        }
        @keyframes panelIn {
            from { opacity: 0; transform: translateY(-30px) scale(.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .send-box .close-btn {
            position: absolute; top: 14px; right: 16px; background: none; border: none;
            font-size: 1.4rem; color: #94a3b8; cursor: pointer; padding: 0;
            width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
            border-radius: 8px; transition: background .2s;
        }
        .send-box .close-btn:hover { background: #f1f5f9; color: #0f172a; }
        .send-box h2 { margin: 0 0 4px; font-size: 1.25rem; font-weight: 600; color: #0f172a; }
        .send-box .sub { margin: 0 0 20px; font-size: .85rem; color: #64748b; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: .85rem; font-weight: 500; color: #334155; margin-bottom: 6px; }
        .form-group input[type="email"] {
            width: 100%; padding: 11px 14px; font-size: .9rem; border: 2px solid #e2e8f0;
            border-radius: 8px; outline: none; font-family: 'DM Sans', sans-serif;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-group input[type="email"]:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .select-all-btn {
            background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; padding: 6px 12px;
            border-radius: 6px; font-size: .78rem; cursor: pointer; margin-bottom: 10px;
            font-family: 'DM Sans', sans-serif; transition: background .2s;
        }
        .select-all-btn:hover { background: #e2e8f0; }
        .checkbox-group { display: flex; flex-direction: column; gap: 10px; }
        .checkbox-group label {
            display: flex; align-items: center; gap: 8px; font-size: .88rem; color: #334155;
            cursor: pointer; padding: 8px 12px; border-radius: 8px; transition: background .15s;
        }
        .checkbox-group label:hover { background: #f8fafc; }
        .checkbox-group input[type="checkbox"] { width: 17px; height: 17px; accent-color: #0f172a; cursor: pointer; }
        .send-actions { display: flex; gap: 12px; margin-top: 22px; justify-content: flex-end; }
        .send-actions .btn-send-now {
            background: #0f172a; color: #fff; border: none; padding: 10px 22px;
            border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: background .2s;
        }
        .send-actions .btn-send-now:hover { background: #1e293b; }
        .send-actions .btn-cancel {
            background: #fff; color: #475569; border: 1px solid #cbd5e1; padding: 10px 20px;
            border-radius: 8px; font-size: .875rem; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: background .2s;
        }
        .send-actions .btn-cancel:hover { background: #f1f5f9; }
    </style>
</head>
<body>
<div class="page-header">
    <div>
        <h1>Rapport Flotte Transport BOUTCHERAFIN</h1>
    </div>
    <div class="header-actions">
        <p>Indicateurs selon matrice CIMAT officielle · <?= date('d/m/Y H:i') ?></p>
        <div class="buttons-group">
            <button class="btn-send" onclick="sendReport()">
                <span>📧</span> Envoyer le rapport
            </button>
            <a href="synthese.php" class="btn-synthese">
                <span>📈</span> Voir Rapport Par Société
            </a>
        </div>
    </div>
</div>
<div class="legende">
    <strong style="color:#0f172a;">Barème :</strong>
    <div class="legende-item"><?= dot('vert') ?> Bon / Faible risque</div>
    <div class="legende-item"><?= dot('orange') ?> Moyen / Attention</div>
    <div class="legende-item"><?= dot('rouge') ?> Critique / Élevé</div>
</div>
<?php if ($error): ?>
    <div class="error-box"><?= $error ?></div>
<?php elseif (empty($vehicules)): ?>
    <div class="error-box">⚠️ Aucune donnée trouvée</div>
<?php else: ?>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Véhicule</th>
                <th>Note /100<br></th>
                <th>Alertes CRIT<br></th>
                <th>Alertes / 100 km<br></th>
                <th>Heures<br></th>
                <th>Km<br></th>
                <th>Charge<br></th>
                <th>Risque<br></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($vehicules as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['nom']) ?></td>
                <td><div class="cell-indicator"><?= dot($v['note_s']) ?><?= htmlspecialchars((string)$v['note']) ?></div></td>
                <td><div class="cell-indicator"><?= dot($v['alertes_s']) ?><?= htmlspecialchars((string)$v['alertes']) ?></div></td>
                <td><div class="cell-indicator"><?= dot($v['al100_s']) ?><?= htmlspecialchars((string)$v['al100']) ?></div></td>
                <td><div class="cell-indicator"><?= dot($v['heures_s']) ?><?= htmlspecialchars((string)$v['heures']) ?> h</div></td>
                <td><div class="cell-indicator"><?= dot($v['km_s']) ?><?= htmlspecialchars($v['km']) ?></div></td>
                <td><?= pill($v['charge_s'], $v['charge']) ?></td>
                <td><?= pill($v['risque_s'], $v['risque']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="meta"><?= count($vehicules) ?> véhicule(s) traité(s) — Dernière mise à jour : <?= date('d/m/Y H:i') ?></p>
<?php endif; ?>

<!-- ══ PANNEAU ENVOI ══ -->
<div class="send-panel" id="send-panel">
    <div class="send-box">
        <button class="close-btn" id="close-panel">✕</button>
        <h2>📧 Envoyer le rapport PDF</h2>
        <p class="sub">Choisissez le destinataire et les tableaux à envoyer.</p>
        <form method="GET" action="send_report.php" id="send-form">
            <div class="form-group">
                <label>Adresse email destinataire</label>
                <input type="email" name="email_to" placeholder="exemple@email.com"
                       value="<?= htmlspecialchars(MAIL_TO) ?>" required>
            </div>
            <div class="form-group">
                <label>Tableaux à envoyer</label>
                <button type="button" class="select-all-btn" id="select-all-btn">Tout sélectionner</button>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="reports[]" value="flotte" checked> 🚗 Rapport Flotte Transport BOUTCHERAFIN</label>
                    <label><input type="checkbox" name="reports[]" value="boutcherafin" checked> 🚛 BOUTCHERAFIN — Détail</label>
                    <label><input type="checkbox" name="reports[]" value="synthese" checked> 📈 Rapport Par Société</label>
                </div>
            </div>
            <div class="send-actions">
                <button type="submit" class="btn-send-now">✉️ Envoyer maintenant</button>
                <button type="button" class="btn-cancel" id="cancel-btn">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function sendReport() {
    document.getElementById('send-panel').classList.add('active');
}

document.getElementById('close-panel').addEventListener('click', function() {
    document.getElementById('send-panel').classList.remove('active');
});
document.getElementById('cancel-btn').addEventListener('click', function() {
    document.getElementById('send-panel').classList.remove('active');
});
document.getElementById('send-panel').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});
document.getElementById('select-all-btn').addEventListener('click', function() {
    const boxes = document.querySelectorAll('#send-form input[type="checkbox"]');
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
    this.textContent = allChecked ? 'Tout sélectionner' : 'Tout désélectionner';
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('send-panel').classList.remove('active');
});
</script>
</body>
</html>