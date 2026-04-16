<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$dataDir  = 'C:/xampp/htdocs/vehicules/data/services/';
$files    = array_filter(glob($dataDir . '*Activités_Chauffeurs*.xlsx'), fn($f) => strpos(basename($f), '~$') === false);
if (empty($files)) {
    $files = array_filter(glob($dataDir . '*.xlsx'), fn($f) => strpos(basename($f), '~$') === false);
}

function fileLabel(string $path): string {
    $name = basename($path, '.xlsx');
    $name = preg_replace('/_\d{2}-\d{2}-\d{4}_\d{2}-\d{2}-\d{2}$/', '', $name);
    $name = str_replace('_', ' ', $name);
    return $name;
}

function readParcours(string $filePath): array {
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (stripos($name, 'Parcours') !== false) {
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
            // Skip total rows
            if (($assoc['Regroupement'] ?? '') === '-----' || ($assoc['Regroupement'] ?? '') === 'Total ') continue;
            $data[] = $assoc;
        }
        return $data;
    } catch (\Exception $e) {
        return [];
    }
}

// Build all files data
$tables = [];
foreach ($files as $filePath) {
    $label = fileLabel($filePath);
    $rows  = readParcours($filePath);
    $tables[] = [
        'label' => $label,
        'file'  => basename($filePath),
        'rows'  => $rows,
    ];
}

// Display columns mapping: [display_label => array of possible raw header keys to search for]
$colDefs = [
    ['label' => 'N°',           'keys' => ['№', 'N°', 'Numero', 'NÂ°']],
    ['label' => 'Véhicule',     'keys' => ['Regroupement']],
    ['label' => 'Parcours',     'keys' => ['Parcours']],
    ['label' => 'Départ de',    'keys' => ['Départ de ', 'Départ de', 'Depart de ', 'Depart de']],
    ['label' => 'Trajet vers',  'keys' => ['Trajet vers']],
    ['label' => 'Début',        'keys' => ['Début', 'Debut']],
    ['label' => 'Fin',          'keys' => ['Fin']],
];

// For each table, find which raw header maps to which column
function resolveColumns(array $rows): array {
    if (empty($rows)) return [];
    $rawHeaders = array_keys($rows[0]);
    $resolved = [];
    foreach ($GLOBALS['colDefs'] as $col) {
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcours - Véhicules de Service</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .page-header { max-width: 1400px; margin: 0 auto 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 1.5rem; font-weight: 600; color: #0f172a; }
        .page-header p { font-size: 0.875rem; color: #64748b; margin-top: 4px; }
        .btn-back {
            background: #fff; color: #334155; border: 1px solid #cbd5e1;
            padding: 10px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
            cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
            font-family: 'DM Sans', sans-serif; transition: background 0.2s;
        }
        .btn-back:hover { background: #f1f5f9; }

        .filter-bar {
            max-width: 1400px; margin: 0 auto 16px;
            display: flex; align-items: center; gap: 12px;
            background: #fff; border-radius: 10px; padding: 12px 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .filter-bar label { font-size: .85rem; font-weight: 600; color: #334155; white-space: nowrap; }
        .filter-bar select {
            padding: 8px 14px; font-size: .85rem; border: 2px solid #e2e8f0;
            border-radius: 8px; outline: none; font-family: 'DM Sans', sans-serif;
            background: #fff; color: #0f172a; cursor: pointer; min-width: 280px;
            transition: border-color .2s;
        }
        .filter-bar select:focus { border-color: #3b82f6; }
        .filter-info { font-size: .8rem; color: #94a3b8; }

        .table-section { max-width: 1400px; margin: 0 auto 24px; }
        .table-title { font-size: 1.05rem; font-weight: 600; color: #0f172a; margin-bottom: 8px; padding-left: 4px; }
        .table-subtitle { font-size: .78rem; color: #94a3b8; margin-bottom: 10px; padding-left: 4px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.06); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        thead th { padding: 12px 14px; text-align: left; font-size: .72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 10px 14px; font-size: .84rem; color: #334155; vertical-align: middle; }
        tbody td:first-child { font-weight: 600; color: #0f172a; }
        .col-nb { width: 60px; text-align: center; }
        .col-nb th, .col-nb td { text-align: center; }
        .meta { max-width: 1400px; margin: 0 auto; font-size: .78rem; color: #94a3b8; text-align: center; padding-top: 8px; }
        .no-data { max-width: 1400px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 40px; text-align: center; color: #94a3b8; }
        .row-main { background: #f0f9ff; font-weight: 500; }
        .row-sub td { color: #64748b; font-size: .82rem; }
    </style>
</head>
<body>

<div class="page-header">
    <div>
        <h1>Parcours - Véhicules de Service</h1>
        <p>Affichage des parcours par fichier source</p>
    </div>
    <a href="tableau.php" class="btn-back">&larr; Retour au Tableau</a>
    <div class="buttons-group">
        <a href="services_tableau.php" class="btn-back">🚗 Flotte Services</a>
        <a href="services_synthese.php" class="btn-back">📈 Synthèse Services</a>
    </div>
</div>

<div class="filter-bar">
    <label for="table-filter">Fichier :</label>
    <select id="table-filter">
        <option value="">— Tous les fichiers —</option>
        <?php foreach ($tables as $i => $t): ?>
            <option value="<?= $i ?>"><?= htmlspecialchars($t['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="filter-info"><?= count($tables) ?> fichier(s) disponible(s)</span>
</div>

<?php if (empty($tables)): ?>
    <div class="no-data">Aucun fichier de données trouvé dans <code>data/services/</code></div>
<?php else: ?>
    <?php foreach ($tables as $i => $t):
        $resolvedCols = resolveColumns($t['rows']);
    ?>
    <div class="table-section" data-table-index="<?= $i ?>">
        <div class="table-title"><?= htmlspecialchars($t['label']) ?></div>
        <div class="table-subtitle"><?= htmlspecialchars($t['file']) ?> — <?= count($t['rows']) ?> ligne(s)</div>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <?php foreach ($resolvedCols as $col): ?>
                            <th<?= $col['label'] === 'N°' ? ' class="col-nb"' : '' ?>><?= htmlspecialchars($col['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($t['rows'])): ?>
                    <tr><td colspan="<?= count($resolvedCols) ?>" style="text-align:center;color:#94a3b8;">Aucune donnée</td></tr>
                <?php else: ?>
                    <?php foreach ($t['rows'] as $row): ?>
                        <?php
                            $num = '';
                            foreach ($resolvedCols as $col) {
                                if ($col['label'] === 'N°') {
                                    $num = $row[$col['rawKey']] ?? '';
                                    break;
                                }
                            }
                            $isMain = ($num !== '' && $num !== '-----' && strpos($num, '.') === false);
                        ?>
                        <tr class="<?= $isMain ? 'row-main' : 'row-sub' ?>">
                            <?php foreach ($resolvedCols as $col): ?>
                                <td<?= $col['label'] === 'N°' ? ' class="col-nb"' : '' ?>>
                                    <?= htmlspecialchars($row[$col['rawKey']] ?? '—') ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
    <p class="meta"><?= count($tables) ?> fichier(s) — Dernière mise à jour : <?= date('d/m/Y H:i') ?></p>
<?php endif; ?>

<script>
document.getElementById('table-filter').addEventListener('change', function() {
    var val = this.value;
    var sections = document.querySelectorAll('.table-section');
    sections.forEach(function(sec) {
        if (val === '' || sec.dataset.tableIndex === val) {
            sec.style.display = '';
        } else {
            sec.style.display = 'none';
        }
    });
});
</script>
</body>
</html>
