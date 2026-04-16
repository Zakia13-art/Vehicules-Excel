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
    return ['value' => $km, 'score' => $score, 'status' => $status, 'formatted' => number_format($km, 0, ',', ' ')];
}
function scoreChargeConducte(int $scoreHeures, int $scoreKm): array {
    $charge = $scoreHeures + $scoreKm;
    if ($charge == 2) { $label = 'Faible'; $status = 'vert'; }
    elseif ($charge >= 3 && $charge <= 4) { $label = 'Moyenne'; $status = 'orange'; }
    else { $label = 'Élevée'; $status = 'rouge'; }
    return ['value' => $charge, 'label' => $label, 'status' => $status];
}
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

function dot(string $status): string {
    $colors = ['vert' => '#22c55e', 'orange' => '#f97316', 'rouge' => '#ef4444'];
    return '<span class="dot" style="background:' . ($colors[$status] ?? '#d1d5db') . '"></span>';
}
function pill(string $status, string $label): string {
    $map = ['vert' => ['#dcfce7', '#166534'], 'orange' => ['#ffedd5', '#9a3412'], 'rouge' => ['#fee2e2', '#991b1b']];
    [$bg, $tc] = $map[$status] ?? ['#f1f5f9', '#475569'];
    return '<span class="pill" style="background:' . $bg . ';color:' . $tc . ';">' . dot($status) . htmlspecialchars($label) . '</span>';
}

// Build data per file
$tables = [];
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
    $tables[] = ['label' => $label, 'file' => basename($filePath), 'vehicules' => $vehicules];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sommaire — Véhicules de Service</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .page-header { max-width: 1300px; margin: 0 auto 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; color: #0f172a; }
        .page-header p { font-size: 0.875rem; color: #64748b; margin-top: 4px; }
        .btn-back, .btn-synthese {
            background: #0f172a; color: #fff; border: none;
            padding: 10px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; transition: background 0.2s; white-space: nowrap;
        }
        .btn-back:hover, .btn-synthese:hover { background: #1e293b; }
        .btn-back { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .btn-back:hover { background: #f1f5f9; }
        .buttons-group { display: flex; gap: 12px; }

        .legende { max-width: 1300px; margin: 0 auto 16px; display: flex; gap: 20px; flex-wrap: wrap; align-items: center; font-size: 0.78rem; color: #64748b; }
        .legende-item { display: flex; align-items: center; gap: 5px; }

        .table-section { max-width: 1300px; margin: 0 auto 28px; }
        .table-title { font-size: 1.05rem; font-weight: 600; color: #0f172a; margin-bottom: 8px; padding-left: 4px; }
        .table-subtitle { font-size: .78rem; color: #94a3b8; margin-bottom: 10px; padding-left: 4px; }

        .card { background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        thead th { padding: 12px 14px; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 11px 14px; font-size: 0.84rem; color: #334155; vertical-align: middle; }
        tbody td:first-child { font-weight: 600; color: #0f172a; }
        .cell-indicator { display: flex; align-items: center; gap: 7px; }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 7px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .filter-bar {
            max-width: 1300px; margin: 0 auto 16px;
            display: flex; align-items: center; gap: 12px;
            background: #fff; border-radius: 10px; padding: 12px 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .filter-bar label { font-size: .85rem; font-weight: 600; color: #334155; white-space: nowrap; }
        .filter-bar select {
            padding: 8px 14px; font-size: .85rem; border: 2px solid #e2e8f0;
            border-radius: 8px; outline: none; font-family: 'DM Sans', sans-serif;
            background: #fff; color: #0f172a; cursor: pointer; min-width: 200px;
            transition: border-color .2s;
        }
        .filter-bar select:focus { border-color: #3b82f6; }
        .meta { max-width: 1300px; margin: 12px auto 0; font-size: 0.78rem; color: #94a3b8; text-align: center; }
        .no-data { max-width: 1300px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 40px; text-align: center; color: #94a3b8; }
        .send-panel { position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);z-index:1000;display:none;align-items:center;justify-content:center; }
        .send-panel.active { display:flex; }
        .send-box { position:relative;background:#fff;width:90%;max-width:500px;border-radius:16px;padding:32px 36px;box-shadow:0 20px 60px rgba(0,0,0,.18);animation:panelIn .3s ease-out; }
        @keyframes panelIn { from{opacity:0;transform:translateY(-30px) scale(.95)} to{opacity:1;transform:translateY(0) scale(1)} }
        .send-box .close-btn { position:absolute;top:14px;right:16px;background:none;border:none;font-size:1.4rem;color:#94a3b8;cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:background .2s; }
        .send-box .close-btn:hover { background:#f1f5f9;color:#0f172a; }
        .send-box h2 { margin:0 0 4px;font-size:1.25rem;font-weight:600;color:#0f172a; }
        .send-box .sub { margin:0 0 20px;font-size:.85rem;color:#64748b; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block;font-size:.85rem;font-weight:500;color:#334155;margin-bottom:6px; }
        .form-group input[type="email"] { width:100%;padding:11px 14px;font-size:.9rem;border:2px solid #e2e8f0;border-radius:8px;outline:none;font-family:'DM Sans',sans-serif;transition:border-color .2s,box-shadow .2s; }
        .form-group input[type="email"]:focus { border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1); }
        .form-group input[type="date"] { flex:1;padding:11px 14px;font-size:.9rem;border:2px solid #e2e8f0;border-radius:8px;outline:none;font-family:'DM Sans',sans-serif; }
        .send-actions { display:flex;gap:12px;margin-top:22px;justify-content:flex-end; }
        .btn-send-now { background:#0f172a;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s; }
        .btn-send-now:hover { background:#1e293b; }
        .btn-cancel { background:#fff;color:#475569;border:1px solid #cbd5e1;padding:10px 20px;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s; }
        .btn-cancel:hover { background:#f1f5f9; }
    </style>
</head>
<body>

<div class="page-header">
    <div>
        <h1>Sommaire — Véhicules de Service</h1>
        <p>Indicateurs selon matrice CIMAT officielle · <?= date('d/m/Y H:i') ?></p>
    </div>
    <div class="buttons-group">
        <button class="btn-synthese" onclick="sendReport()">
            <span>📧</span> Envoyer le rapport
        </button>
        <a href="services_synthese.php" class="btn-synthese">
            <span>📊</span> Statistiques
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

<?php if (empty($tables)): ?>
    <div class="no-data">Aucun fichier de données trouvé dans <code>data/services/</code></div>
<?php else: ?>
<div class="filter-bar">
    <label for="src-filter">Source :</label>
    <select id="src-filter">
        <?php foreach ($tables as $i => $t): ?>
            <?php if ($i === 0): ?>
                <option value="<?= $i ?>" selected><?= htmlspecialchars($t['label']) ?></option>
            <?php else: ?>
                <option value="<?= $i ?>"><?= htmlspecialchars($t['label']) ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
</div>
    <?php foreach ($tables as $i => $t): ?>
    <div class="table-section" data-index="<?= $i ?>" style="<?= $i !== 0 ? 'display:none;' : '' ?>">
        <div class="table-title"><?= htmlspecialchars($t['label']) ?></div>
        <div class="table-subtitle"><?= htmlspecialchars($t['file']) ?></div>
        <?php if (empty($t['vehicules'])): ?>
            <div class="no-data">Aucune donnée Sommaire</div>
        <?php else: ?>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Véhicule</th>
                        <th>Note /100</th>
                        <th>Alertes CRIT</th>
                        <th>Alertes / 100 km</th>
                        <th>Heures</th>
                        <th>Km</th>
                        <th>Charge</th>
                        <th>Risque</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($t['vehicules'] as $v): ?>
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
        <p class="meta"><?= count($t['vehicules']) ?> véhicule(s) · Dernière mise à jour : <?= date('d/m/Y H:i') ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.getElementById('src-filter').addEventListener('change', function() {
    var val = this.value;
    var sections = document.querySelectorAll('.table-section');
    sections.forEach(function(sec) {
        sec.style.display = sec.dataset.index === val ? '' : 'none';
    });
});
function sendReport() { document.getElementById('send-panel').classList.add('active'); }
document.getElementById('close-panel').addEventListener('click', function() { document.getElementById('send-panel').classList.remove('active'); });
document.getElementById('cancel-btn').addEventListener('click', function() { document.getElementById('send-panel').classList.remove('active'); });
document.getElementById('send-panel').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.getElementById('send-panel').classList.remove('active'); });
</script>

<!-- PANNEAU ENVOI SERVICES -->
<div class="send-panel" id="send-panel">
    <div class="send-box">
        <button class="close-btn" id="close-panel">✕</button>
        <h2>📧 Envoyer le rapport Services</h2>
        <p class="sub">PDF contenant : Statistiques + Sommaire + Parcours</p>
        <form method="GET" action="send_report_services.php">
            <div class="form-group">
                <label>Adresse email destinataire</label>
                <input type="email" name="email_to" placeholder="exemple@email.com"
                       value="<?= htmlspecialchars(MAIL_TO) ?>" required>
            </div>
            <div class="form-group">
                <label>Période de traitement</label>
                <div style="display:flex;gap:10px;">
                    <input type="date" name="date_from" value="2026-03-01" required>
                    <input type="date" name="date_to" value="2026-03-31" required>
                </div>
            </div>
            <div class="send-actions">
                <button type="submit" class="btn-send-now">✉️ Envoyer maintenant</button>
                <button type="button" class="btn-cancel" id="cancel-btn">Annuler</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
