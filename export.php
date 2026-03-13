<?php
/**
 * export.php — Export de Données
 * Exporter en PDF, Excel ou CSV avec options
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

$export_done = false;
$export_format = '';

if ($_POST && isset($_POST['export_format'])) {
    $export_format = $_POST['export_format'];
    $export_type = $_POST['export_type'] ?? 'flotte';
    $include_infractions = isset($_POST['include_infractions']);
    $include_stats = isset($_POST['include_stats']);

    // Créer un spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Données');

    // Headers
    $headers = ['Véhicule', 'Note /100', 'Alertes', 'Kilométrage', 'Durée'];
    if ($include_infractions) $headers[] = 'Infractions';
    if ($include_stats) $headers[] = 'Score Global';

    // Ajouter les headers
    foreach ($headers as $col => $header) {
        $sheet->setCellValue(chr(65 + $col) . '1', $header);
    }

    // Données exemple (à remplacer par vos données réelles)
    $data = [
        ['ABC Car', 85, 2, '5000 km', '100h'],
        ['XYZ Van', 72, 8, '8500 km', '150h'],
        ['LMN Truck', 91, 1, '12000 km', '200h'],
    ];

    foreach ($data as $row_idx => $row) {
        foreach ($row as $col_idx => $value) {
            $sheet->setCellValue(chr(65 + $col_idx) . ($row_idx + 2), $value);
        }
    }

    // Export
    $filename = 'export_flotte_' . date('Ymd_Hi');

    if ($export_format === 'xlsx') {
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        $writer->save('php://output');
        exit;
    } elseif ($export_format === 'csv') {
        $writer = new Csv($spreadsheet);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $writer->save('php://output');
        exit;
    } elseif ($export_format === 'pdf') {
        // Utiliser mPDF
        require_once 'vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf();
        $html = '<h2>Export Flotte Transport</h2>';
        $html .= '<table border="1" style="width:100%;"><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $val) {
                $html .= '<td>' . htmlspecialchars($val) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename . '.pdf', 'D');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export de Données</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { margin-bottom: 40px; }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }
        .card { background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 8px 32px rgba(0,0,0,0.06); margin-bottom: 28px; }
        .form-section { margin-bottom: 28px; }
        .form-section h3 { font-size: 1rem; font-weight: 600; color: #0f172a; margin-bottom: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
        .format-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .format-option { position: relative; }
        .format-option input[type="radio"] { display: none; }
        .format-option label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .format-option input[type="radio"]:checked + label {
            border-color: #3b82f6;
            background: #dbeafe;
        }
        .format-option label span { font-size: 1.3rem; }
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; }
        .checkbox-item input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #3b82f6; }
        .checkbox-item label { cursor: pointer; font-weight: 500; }
        .btn { padding: 12px 24px; background: #0f172a; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; }
        .btn:hover { background: #1e293b; }
        .btn:disabled { background: #cbd5e1; cursor: not-allowed; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 8px 16px; background: #e2e8f0; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 500; }
        .back-btn:hover { background: #cbd5e1; }
        .preview { background: #f8fafc; padding: 16px; border-radius: 8px; margin-top: 16px; }
        .preview p { margin: 6px 0; font-size: 0.9rem; color: #64748b; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Retour Dashboard</a>

    <div class="header">
        <h1>📥 Export de Données</h1>
        <p>Téléchargez les données au format PDF, Excel ou CSV</p>
    </div>

    <div class="card">
        <form method="POST">
            <!-- Format Selection -->
            <div class="form-section">
                <h3>📋 Format d'export</h3>
                <div class="format-options">
                    <div class="format-option">
                        <input type="radio" id="pdf" name="export_format" value="pdf" checked>
                        <label for="pdf"><span>📄</span> PDF</label>
                    </div>
                    <div class="format-option">
                        <input type="radio" id="xlsx" name="export_format" value="xlsx">
                        <label for="xlsx"><span>📊</span> Excel</label>
                    </div>
                    <div class="format-option">
                        <input type="radio" id="csv" name="export_format" value="csv">
                        <label for="csv"><span>📑</span> CSV</label>
                    </div>
                </div>
            </div>

            <!-- Type Selection -->
            <div class="form-section">
                <h3>🎯 Type de rapport</h3>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="radio" id="type_flotte" name="export_type" value="flotte" checked>
                        <label for="type_flotte">Flotte complète</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="radio" id="type_synthese" name="export_type" value="synthese">
                        <label for="type_synthese">Synthèse par société</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="radio" id="type_detail" name="export_type" value="detail">
                        <label for="type_detail">Détail véhicules</label>
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div class="form-section">
                <h3>⚙️ Options supplémentaires</h3>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="infractions" name="include_infractions" checked>
                        <label for="infractions">Inclure les infractions</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="stats" name="include_stats" checked>
                        <label for="stats">Inclure les statistiques</label>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="preview">
                <p><strong>📌 Aperçu:</strong></p>
                <p>✓ Données de <span id="preview-type">la flotte complète</span></p>
                <p>✓ Format: <span id="preview-format">PDF</span></p>
                <p id="preview-options">✓ Avec infractions et statistiques</p>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 12px; margin-top: 28px;">
                <button type="submit" class="btn">
                    📥 Télécharger l'export
                </button>
                <button type="reset" class="btn" style="background: #ef4444;">
                    ✕ Réinitialiser
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Mise à jour de l'aperçu en temps réel
    document.querySelectorAll('input[name="export_format"]').forEach(el => {
        el.addEventListener('change', () => {
            document.getElementById('preview-format').textContent = {
                'pdf': 'PDF',
                'xlsx': 'Excel',
                'csv': 'CSV'
            }[el.value];
        });
    });

    document.querySelectorAll('input[name="export_type"]').forEach(el => {
        el.addEventListener('change', () => {
            document.getElementById('preview-type').textContent = {
                'flotte': 'la flotte complète',
                'synthese': 'la synthèse par société',
                'detail': 'le détail des véhicules'
            }[el.value];
        });
    });
</script>

</body>
</html>
