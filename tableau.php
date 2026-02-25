<?php
/**
 * tableau.php — Rapport Flotte Transport BOUTCHERAFIN
 * Source : fichiers Excel dans C:\xampp\htdocs\vehicules\data\entreprises
 * Calculs automatiques par véhicule avec barèmes couleurs
 * 
 * FIX: sNote() maintenant applique la condition directement sur /100
 *      ≥ 80 → vert, 60-79 → orange, < 60 → rouge
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ══════════════════════════════════════════════════════════
// ── CONFIG EXCEL ──────────────────────────────────────────
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

// Convertit "1 jours 2:26:21" ou "20:17:05" en heures décimales
function parseDureeToHours(string $duree): float {
    $h = 0.0;
    if (preg_match('/(\d+)\s*jours?\s*/i', $duree, $m)) {
        $h += (float)$m[1] * 24;
        $duree = preg_replace('/\d+\s*jours?\s*/i', '', $duree);
    }
    if (preg_match('/(\d+):(\d+):(\d+)/', $duree, $m))
        $h += (float)$m[1] + (float)$m[2]/60 + (float)$m[3]/3600;
    elseif (preg_match('/(\d+):(\d+)/', $duree, $m))
        $h += (float)$m[1] + (float)$m[2]/60;
    return round($h, 1);
}

// ══════════════════════════════════════════════════════════
// ── BARÈMES COULEURS ──────────────────────────────────────
// ══════════════════════════════════════════════════════════
// ✅ FIX: sNote() applique directement la condition sur /100
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

// ══════════════════════════════════════════════════════════
// ── LECTURE FICHIERS EXCEL ────────────────────────────────
// ══════════════════════════════════════════════════════════
$error     = '';
$vehicules = [];

$fileEco  = findLatestFile($dataDir, 'co-conduite');
$fileKilo = findLatestFile($dataDir, 'om');

if (!$fileEco || !$fileKilo) {
    $error = 'Fichier(s) Excel introuvable(s) dans <code>' . htmlspecialchars($dataDir) . '</code>';
} else {
    $ecoData  = readExcelSheet($fileEco,  $SHEET_ECO);
    $kiloData = readExcelSheet($fileKilo, $SHEET_KILO);

    // Mapping éco par véhicule
    $ecoMap = [];
    foreach ($ecoData as $row) {
        $reg  = $row['Regroupement'] ?? '';
        $eval = $row['Évaluation']   ?? '';
        $infr = $row['Infraction']   ?? '';
        if ($reg === '') continue;
        if (!isset($ecoMap[$reg])) $ecoMap[$reg] = ['evaluation' => 0.0, 'infractions' => 0];
        $evalNum = (float) preg_replace('/[^0-9.]/', '', $eval);
        if ($evalNum > $ecoMap[$reg]['evaluation']) $ecoMap[$reg]['evaluation'] = $evalNum;
        if ($infr !== '' && $infr !== '-----') $ecoMap[$reg]['infractions']++;
    }

    // Fusion kilo + éco → calculs par véhicule
    foreach ($kiloData as $row) {
        $reg  = $row['Regroupement'] ?? '';
        $info = $ecoMap[$reg] ?? null;
        if ($reg === '' || $info === null) continue;

        $heures  = parseDureeToHours($row['Durée'] ?? '0');
        $km      = (float) preg_replace('/[^0-9.]/', '', $row['Kilométrage'] ?? '0');
        $note    = $info['evaluation'] / 10;
        $alertes = $info['infractions'];
        $al100   = $km > 0 ? round(($alertes / $km) * 100, 2) : 0;

        // ✅ FIX: Appliquer sNote() directement sur la note /100 (pas divisée par 10)
        $sN = sNote($note * 100);
        $sA = sAlertes($alertes);
        $sA1 = sAl100($al100);
        $sH = sHeures($heures);
        $sC = sCharge($heures, $km);
        $sR = sRisque($sN, $sA, $sA1, $sC);

        $vehicules[] = [
            'nom'       => $reg,
            'note'      => $note * 100,     'note_s'    => $sN,
            'alertes'   => $alertes,  'alertes_s' => $sA,
            'heures'    => $heures,   'heures_s'  => $sH,
            'km'        => number_format($km, 0, ',', ' ') . ' km', 'km_s' => ($km >= 5000 ? 'rouge' : ($km >= 4000 ? 'orange' : 'vert')),
            'al100'     => $al100,    'al100_s'   => $sA1,
            'charge'    => chargeLabel($sC), 'charge_s' => $sC,
            'risque'    => risqueLabel($sR), 'risque_s' => $sR,
        ];
    }
}

// ── Fonctions badge ───────────────────────────────────────
function dot(string $status): string {
    $c = ['vert'=>'#22c55e','orange'=>'#f97316','rouge'=>'#ef4444'][$status] ?? '#d1d5db';
    return '<span class="dot" style="background:' . $c . '"></span>';
}
function pill(string $status, string $label): string {
    $map = ['vert'=>['#dcfce7','#166534'],'orange'=>['#ffedd5','#9a3412'],'rouge'=>['#fee2e2','#991b1b']];
    [$bg, $tc] = $map[$status] ?? ['#f1f5f9','#475569'];
    return '<span class="pill" style="background:' . $bg . ';color:' . $tc . ';">'
         . dot($status) . htmlspecialchars($label) . '</span>';
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
        .page-header p  { font-size: 0.875rem; color: #64748b; margin-top: 4px; }

        .actions { max-width: 1300px; margin: 0 auto 16px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-start; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: #0f172a; color: #fff; border: none;
            padding: 9px 18px; border-radius: 8px; font-size: 0.85rem;
            font-family: inherit; font-weight: 500; cursor: pointer;
            text-decoration: none; transition: background .15s;
        }
        .btn:hover { background: #1e293b; }
        .btn.accent { background: #1e3a5f; }
        .btn.accent:hover { background: #162d4a; }

        /* ── Panneau envoi ── */
        .send-panel { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.45); z-index: 100; align-items: center; justify-content: center; }
        .send-panel.open { display: flex; }
        .send-box { background: #fff; border-radius: 16px; padding: 32px 36px; max-width: 480px; width: 100%; box-shadow: 0 16px 48px rgba(0,0,0,0.15); position: relative; }
        .send-box h2 { font-size: 1.1rem; font-weight: 600; color: #0f172a; margin-bottom: 6px; }
        .send-box p.sub { font-size: 0.82rem; color: #64748b; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group > label { display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; }
        .form-group input[type="email"] { width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.875rem; color: #1e293b; }
        .form-group input[type="email"]:focus { outline: none; border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15,23,42,0.08); }
        .checkbox-group { display: flex; flex-direction: column; gap: 8px; }
        .checkbox-group label { display: flex; align-items: center; gap: 10px; font-size: 0.875rem; color: #334155; cursor: pointer; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; transition: all .15s; font-weight: 400; text-transform: none; letter-spacing: 0; }
        .checkbox-group label:hover { background: #f8fafc; border-color: #cbd5e1; }
        .checkbox-group input[type="checkbox"] { accent-color: #0f172a; width: 16px; height: 16px; }
        .checkbox-group label:has(input:checked) { border-color: #0f172a; background: #f0f4f8; }
        .select-all-btn { font-size: 0.75rem; color: #6366f1; background: none; border: none; cursor: pointer; padding: 0; margin-bottom: 6px; font-family: inherit; }
        .select-all-btn:hover { text-decoration: underline; }
        .send-actions { display: flex; gap: 10px; margin-top: 24px; }
        .btn-send { flex: 1; padding: 10px; background: #0f172a; color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; }
        .btn-send:hover { background: #1e293b; }
        .btn-cancel { padding: 10px 18px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.875rem; cursor: pointer; }
        .btn-cancel:hover { background: #e2e8f0; }
        .close-btn { position: absolute; top: 14px; right: 16px; background: none; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer; }
        .close-btn:hover { color: #475569; }

        /* ── Tableau ── */
        .card { max-width: 1300px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06); overflow-x: auto; }
        table { width: 100%; min-width: 1000px; border-collapse: collapse; }
        thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        thead th { padding: 12px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        thead th:first-child { border-right: 1px solid #e2e8f0; }
        tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 11px 14px; font-size: 0.84rem; color: #334155; vertical-align: middle; }
        tbody td:first-child { font-weight: 600; color: #0f172a; border-right: 1px solid #e2e8f0; white-space: nowrap; }

        .cell-indicator { display: flex; align-items: center; gap: 7px; }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 7px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }

        /* ── Légende ── */
        .legende { max-width: 1300px; margin: 0 auto 16px; display: flex; gap: 20px; flex-wrap: wrap; align-items: center; font-size: 0.78rem; color: #64748b; }
        .legende-item { display: flex; align-items: center; gap: 5px; }

        .error-box { max-width: 1300px; margin: 0 auto; background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 16px 20px; color: #dc2626; }
        .meta { max-width: 1300px; margin: 12px auto 0; font-size: 0.78rem; color: #94a3b8; }
    </style>
</head>
<body>

<div class="page-header">
    <h1>Rapport Flotte Transport BOUTCHERAFIN</h1>
    <p>Données calculées depuis les fichiers Excel · <?= date('d/m/Y H:i') ?></p>
</div>

<div class="actions">
    <button class="btn" id="open-send-panel">📧 Envoyer le rapport PDF par email</button>
    <a href="BOUTCHERAFIN.php" class="btn">📊 Voir le tableau BOUTCHERAFIN</a>
    <a href="synthese.php" class="btn accent">📈 Rapport Par Société</a>
</div>

<!-- Légende barèmes -->
<div class="legende">
    <strong style="color:#0f172a;">Barème :</strong>
    <div class="legende-item"><?= dot('vert') ?> Bon / Faible risque</div>
    <div class="legende-item"><?= dot('orange') ?> Moyen / Attention</div>
    <div class="legende-item"><?= dot('rouge') ?> Critique / Élevé</div>
</div>

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
                <button type="submit" class="btn-send">✉️ Envoyer maintenant</button>
                <button type="button" class="btn-cancel" id="cancel-btn">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ TABLEAU ══ -->
<?php if ($error): ?>
    <div class="error-box">❌ <strong>Erreur :</strong> <?= $error ?></div>
<?php elseif (empty($vehicules)): ?>
    <div class="error-box">⚠️ Aucune donnée trouvée dans <code><?= htmlspecialchars($dataDir) ?></code></div>
<?php else: ?>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Véhicule</th>
                <th>Note /100</th>
                <th>Alertes CRIT</th>
                <th>Heures de conduite (h)</th>
                <th>Kilométrage (km)</th>
                <th>Alertes / 100 km</th>
                <th>Charge conduite</th>
                <th>Niveau de risque global</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($vehicules as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['nom']) ?></td>
                <td><div class="cell-indicator"><?= dot($v['note_s']) ?><?= htmlspecialchars((string)$v['note']) ?></div></td>
                <td><div class="cell-indicator"><?= dot($v['alertes_s']) ?><?= htmlspecialchars((string)$v['alertes']) ?></div></td>
                <td><div class="cell-indicator"><?= dot($v['heures_s']) ?><?= htmlspecialchars((string)$v['heures']) ?> h</div></td>
                <td><div class="cell-indicator"><?= dot($v['km_s']) ?><?= htmlspecialchars($v['km']) ?></div></td>
                <td><div class="cell-indicator"><?= dot($v['al100_s']) ?><?= htmlspecialchars((string)$v['al100']) ?></div></td>
                <td><?= pill($v['charge_s'], $v['charge']) ?></td>
                <td><?= pill($v['risque_s'], $v['risque']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="meta"><?= count($vehicules) ?> véhicule(s) — Dernière mise à jour : <?= date('d/m/Y H:i') ?></p>
<?php endif; ?>

<script>
    const panel      = document.getElementById('send-panel');
    const openBtn    = document.getElementById('open-send-panel');
    const closeBtn   = document.getElementById('close-panel');
    const cancelBtn  = document.getElementById('cancel-btn');
    const checkboxes = document.querySelectorAll('.checkbox-group input[type="checkbox"]');
    const selectAll  = document.getElementById('select-all-btn');

    openBtn.addEventListener('click',  () => panel.classList.add('open'));
    closeBtn.addEventListener('click', () => panel.classList.remove('open'));
    cancelBtn.addEventListener('click',() => panel.classList.remove('open'));
    panel.addEventListener('click', e => { if (e.target === panel) panel.classList.remove('open'); });

    let allSelected = true;
    selectAll.addEventListener('click', () => {
        allSelected = !allSelected;
        checkboxes.forEach(cb => cb.checked = allSelected);
        selectAll.textContent = allSelected ? 'Tout désélectionner' : 'Tout sélectionner';
    });

    document.getElementById('send-form').addEventListener('submit', e => {
        if (![...checkboxes].some(cb => cb.checked)) {
            e.preventDefault();
            alert('Veuillez sélectionner au moins un tableau.');
        }
    });
</script>

</body>
</html>