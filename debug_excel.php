<?php
/**
 * debug_excel.php — Diagnostic des fichiers Excel
 * Place ce fichier dans C:/xampp/htdocs/vehicules/
 * Accède via : http://localhost/vehicules/debug_excel.php
 */

require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$dataDir = 'C:/xampp/htdocs/vehicules/data/entreprises/';

function findAllXlsx(string $dir): array {
    return glob($dir . '*.xlsx') ?: [];
}

function inspectFile(string $filePath): void {
    echo '<div style="background:#fff;border-radius:12px;padding:20px;margin-bottom:20px;
          box-shadow:0 2px 8px rgba(0,0,0,0.08);">';
    echo '<h3 style="color:#0f172a;margin:0 0 6px;">' . htmlspecialchars(basename($filePath)) . '</h3>';
    echo '<p style="color:#64748b;font-size:0.8rem;margin:0 0 14px;">📁 ' . htmlspecialchars($filePath) . '</p>';

    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheetNames  = $spreadsheet->getSheetNames();

        echo '<p style="margin:0 0 10px;font-weight:600;color:#334155;">Feuilles trouvées (' . count($sheetNames) . ') :</p>';

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $rows  = $sheet->toArray(null, true, true, false);

            echo '<div style="background:#f8fafc;border-radius:8px;padding:14px;margin-bottom:10px;
                  border-left:3px solid #6366f1;">';
            echo '<p style="margin:0 0 8px;font-weight:700;color:#6366f1;">📄 Feuille : "' . htmlspecialchars($sheetName) . '"</p>';

            if (empty($rows)) {
                echo '<p style="color:#dc2626;">⚠️ Feuille vide</p>';
            } else {
                // En-têtes (ligne 1)
                $headers = array_shift($rows);
                echo '<p style="margin:0 0 6px;font-weight:600;color:#475569;">Colonnes détectées :</p>';
                echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">';
                foreach ($headers as $i => $h) {
                    $display = $h !== null && $h !== '' ? $h : '(vide col ' . $i . ')';
                    echo '<span style="background:#e0e7ff;color:#3730a3;padding:3px 10px;border-radius:20px;
                          font-size:0.8rem;font-family:monospace;">'
                         . htmlspecialchars($display) . '</span>';
                }
                echo '</div>';

                // Aperçu des 3 premières lignes de données
                $preview = array_slice($rows, 0, 3);
                if (!empty($preview)) {
                    echo '<p style="margin:0 0 6px;font-weight:600;color:#475569;">Aperçu (3 premières lignes) :</p>';
                    echo '<table style="width:100%;border-collapse:collapse;font-size:0.78rem;">';
                    echo '<thead><tr style="background:#e0e7ff;">';
                    foreach ($headers as $h) {
                        echo '<th style="padding:5px 8px;text-align:left;color:#3730a3;">'
                             . htmlspecialchars($h ?? '') . '</th>';
                    }
                    echo '</tr></thead><tbody>';
                    foreach ($preview as $row) {
                        echo '<tr style="border-bottom:1px solid #e2e8f0;">';
                        foreach ($row as $cell) {
                            echo '<td style="padding:5px 8px;color:#334155;">'
                                 . htmlspecialchars((string)($cell ?? '')) . '</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }

                echo '<p style="margin:8px 0 0;font-size:0.78rem;color:#94a3b8;">'
                     . count($rows) . ' ligne(s) de données</p>';
            }
            echo '</div>';
        }
    } catch (\Exception $e) {
        echo '<p style="color:#dc2626;">❌ Erreur lecture : ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Debug Excel — Diagnostic</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; padding: 30px 24px; color: #1e293b; }
        h1 { font-size: 1.3rem; color: #0f172a; margin-bottom: 4px; }
        .sub { color: #64748b; font-size: 0.85rem; margin-bottom: 24px; }
        .alert { background: #fef2f2; border-left: 3px solid #dc2626; border-radius: 8px;
                 padding: 14px; color: #dc2626; margin-bottom: 20px; }
        .ok    { background: #dcfce7; border-left: 3px solid #22c55e; border-radius: 8px;
                 padding: 14px; color: #166534; margin-bottom: 20px; }
        a { color: #6366f1; }
    </style>
</head>
<body>
<h1>🔍 Diagnostic — Fichiers Excel</h1>
<p class="sub">Dossier analysé : <code><?= htmlspecialchars($dataDir) ?></code></p>

<?php
$files = findAllXlsx($dataDir);

if (empty($files)) {
    echo '<div class="alert">❌ <strong>Aucun fichier .xlsx trouvé</strong> dans ce dossier.<br>
          Vérifiez que le chemin est correct et que les fichiers sont bien présents.</div>';
} else {
    echo '<div class="ok">✅ <strong>' . count($files) . ' fichier(s) Excel trouvé(s)</strong></div>';
    foreach ($files as $file) {
        inspectFile($file);
    }
}
?>

<p style="margin-top:20px;"><a href="tableau.php">← Retour au tableau</a></p>
</body>
</html>