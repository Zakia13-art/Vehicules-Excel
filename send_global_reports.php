<?php
/**
 * ========================================
 * ENVOI RAPPORTS GLOBAL PAR EMAIL
 * ========================================
 * Choix: KM, Infractions, Evaluation, Tous
 * Envoie email avec PDF en pièce jointe
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use Mpdf\Mpdf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Envoyer Rapports - Email</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 40px auto; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .header p { margin: 10px 0 0; opacity: 0.9; }
        .content { padding: 30px; }
        .choice-section { margin-bottom: 25px; }
        .choice-title { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 15px; }
        .choices-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .choice-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .choice-card:hover { border-color: #667eea; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.2); }
        .choice-card.selected { border-color: #667eea; background: linear-gradient(135deg, rgba(102,126,234,0.05) 0%, rgba(118,75,162,0.05) 100%); }
        .choice-card input { display: none; }
        .choice-icon { font-size: 28px; margin-bottom: 8px; }
        .choice-label { font-weight: 600; color: #1e293b; font-size: 14px; }
        .choice-desc { font-size: 12px; color: #64748b; margin-top: 4px; }
        .email-section { background: #f8fafc; border-radius: 12px; padding: 20px; margin: 20px 0; }
        .email-input { width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: border 0.2s; }
        .email-input:focus { outline: none; border-color: #667eea; }
        .btn-send {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 20px;
        }
        .btn-send:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.3); }
        .btn-send:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }
        .summary { background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .summary p { margin: 5px 0; font-size: 14px; }
        .loading { display: none; text-align: center; padding: 40px; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .back-link { display: inline-block; margin-top: 20px; color: #64748b; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: #667eea; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>📧 Envoyer Rapports par Email</h1>
        <p>Sélectionnez les rapports à envoyer</p>
    </div>

    <div class="content">
        <form id="reportForm" method="POST">
            <!-- KM Section -->
            <div class="choice-section">
                <div class="choice-title">📊 KILOMÉTRAGE</div>
                <div class="choices-grid">
                    <label class="choice-card" onclick="toggleCard(this)">
                        <input type="checkbox" name="reports[]" value="km">
                        <div class="choice-icon">🚗</div>
                        <div class="choice-label">Kilométrage</div>
                        <div class="choice-desc">Rapport kilométrage tous transporteurs</div>
                    </label>
                </div>
            </div>

            <!-- Infractions Section -->
            <div class="choice-section">
                <div class="choice-title">⚠️ INFRACTIONS</div>
                <div class="choices-grid">
                    <label class="choice-card" onclick="toggleCard(this)">
                        <input type="checkbox" name="reports[]" value="infractions">
                        <div class="choice-icon">🚨</div>
                        <div class="choice-label">Infractions</div>
                        <div class="choice-desc">Rapport infractions tous transporteurs</div>
                    </label>
                </div>
            </div>

            <!-- Evaluation Section -->
            <div class="choice-section">
                <div class="choice-title">📈 ÉVALUATION</div>
                <div class="choices-grid">
                    <label class="choice-card" onclick="toggleCard(this)">
                        <input type="checkbox" name="reports[]" value="evaluation">
                        <div class="choice-icon">✅</div>
                        <div class="choice-label">Éco-conduite</div>
                        <div class="choice-desc">Rapport évaluation tous transporteurs</div>
                    </label>
                </div>
            </div>

            <!-- Email Section -->
            <div class="email-section">
                <div class="choice-title">📧 EMAIL DESTINATAIRE</div>
                <input type="email" name="email_to" class="email-input"
                       value="<?= MAIL_TO ?>" placeholder="Entrez l'email destinataire" required>
            </div>

            <!-- Summary -->
            <div class="summary" id="summary">
                <p><strong>Résumé:</strong> Aucun rapport sélectionné (email sans pièce jointe)</p>
            </div>

            <button type="submit" class="btn-send" id="sendBtn">
                📧 Envoyer
            </button>
        </form>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Génération et envoi en cours...</p>
        </div>

        <div style="text-align: center;">
            <a href="index.php" class="back-link">← Retour à l'accueil</a>
        </div>
    </div>
</div>

<script>
function toggleCard(card) {
    const input = card.querySelector('input');
    if (input.type === 'checkbox') {
        input.checked = !input.checked;
    }
    card.classList.toggle('selected', input.checked);
    updateSummary();
}

document.querySelectorAll('.choice-card input').forEach(input => {
    input.addEventListener('change', function() {
        const card = this.closest('.choice-card');
        card.classList.toggle('selected', this.checked);
        updateSummary();
    });
});

function updateSummary() {
    const selected = document.querySelectorAll('.choice-card input:checked');
    const summaryDiv = document.getElementById('summary');
    const sendBtn = document.getElementById('sendBtn');

    if (selected.length === 0) {
        summaryDiv.innerHTML = '<p><strong>Résumé:</strong> Aucun rapport sélectionné (email sans pièce jointe)</p>';
        sendBtn.disabled = false;  // Permettre l'envoi même sans sélection
    } else {
        const names = {
            'km': 'Kilométrage',
            'infractions': 'Infractions',
            'evaluation': 'Évaluation'
        };
        const selectedNames = Array.from(selected).map(i => names[i.value]);
        summaryDiv.innerHTML = '<p><strong>Résumé:</strong> ' + selectedNames.join(', ') + '</p>';
        sendBtn.disabled = false;
    }
}

document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const selected = document.querySelectorAll('.choice-card input:checked');
    // Permettre la soumission même sans sélection

    document.getElementById('loading').style.display = 'block';
    document.getElementById('reportForm').style.display = 'none';

    this.submit();
});
</script>

<?php
// ========================================
// TRAITEMENT FORMULAIRE (QUAND SOUMIS)
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reports = $_POST['reports'] ?? [];
    $email_to = filter_var(trim($_POST['email_to'] ?? ''), FILTER_VALIDATE_EMAIL)
        ? trim($_POST['email_to']) : MAIL_TO;

    // Permettre l'envoi même sans rapport sélectionné
    // if (empty($reports)) {
    //     die('<div style="font-family:Arial;padding:30px;color:#dc2626;">❌ Aucun rapport sélectionné<br><a href="send_global_reports.php">← Retour</a></div>');
    // }

    // Récupérer données depuis global tables
    $pdo = getDB();

    // ----------------------------------------
    // GÉNÉRER PDF - KILOMÉTRAGE
    // ----------------------------------------
    $pdfFiles = [];

    if (in_array('km', $reports)) {
        $sql = "SELECT transporteur_nom, vehicule, debut, fin, duree, kilometrage
                FROM global_kilometrage
                WHERE debut >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY transporteur_nom, vehicule, debut DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $kmData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // TOUJOURS générer PDF, même si vide
        $html = buildPdfKilometrage($kmData);
        $name = 'rapport_kilometrage_' . date('Ymd_Hi') . '.pdf';
        $path = generatePdf($html, $name);
        $pdfFiles[] = ['path' => $path, 'name' => $name, 'label' => '📊 Kilométrage'];
    }

    // ----------------------------------------
    // GÉNÉRER PDF - INFRACTIONS
    // ----------------------------------------
    if (in_array('infractions', $reports)) {
        $sql = "SELECT transporteur_nom, vehicule, debut, fin, emplacement, infraction
                FROM global_infractions
                WHERE debut >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY transporteur_nom, vehicule, debut DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $infraData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // TOUJOURS générer PDF, même si vide
        $html = buildPdfInfractions($infraData);
        $name = 'rapport_infractions_' . date('Ymd_Hi') . '.pdf';
        $path = generatePdf($html, $name);
        $pdfFiles[] = ['path' => $path, 'name' => $name, 'label' => '⚠️ Infractions'];
    }

    // ----------------------------------------
    // GÉNÉRER PDF - ÉVALUATION
    // ----------------------------------------
    if (in_array('evaluation', $reports)) {
        $sql = "SELECT transporteur_nom, vehicule, debut, fin, emplacement, penalites, evaluation
                FROM global_evaluation
                WHERE debut >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY transporteur_nom, vehicule, debut DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $evalData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // TOUJOURS générer PDF, même si vide
        $html = buildPdfEvaluation($evalData);
        $name = 'rapport_evaluation_' . date('Ymd_Hi') . '.pdf';
        $path = generatePdf($html, $name);
        $pdfFiles[] = ['path' => $path, 'name' => $name, 'label' => '📈 Évaluation'];
    }

    // Note: Les PDFs sont générés même si vide (rapports sans données)

    // ----------------------------------------
    // ENVOI EMAIL
    // ----------------------------------------
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'zakia.controlflot@gmail.com';
        $mail->Password = 'vqnslggncuitnavh';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('zakia.controlflot@gmail.com', 'Flotte Transport – Rapports Global');
        $mail->addAddress($email_to, 'Destinataire');
        $mail->CharSet = 'UTF-8';

        // Ajouter les PDFs en pièces jointes
        $labels = [];
        foreach ($pdfFiles as $pdf) {
            $mail->addAttachment($pdf['path'], $pdf['name']);
            $labels[] = $pdf['label'];
        }

        if (count($pdfFiles) > 0) {
            $listeRapports = implode(', ', $labels);
            $sujet = '📋 Rapports Global (' . count($pdfFiles) . ') – ' . date('d/m/Y');
        } else {
            $listeRapports = 'Aucun';
            $sujet = '📋 Rapports Global – ' . date('d/m/Y');
        }

        $mail->isHTML(true);
        $mail->Subject = $sujet;

        // Message différent selon qu'il y a des pièces jointes ou non
        if (count($pdfFiles) > 0) {
            $mail->Body = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
                <h2 style="color:#667eea;margin-bottom:4px">📋 Rapports Global</h2>
                <p style="color:#64748b;">Généré le ' . date('d/m/Y à H:i') . '</p>
                <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
                <p style="font-size:14px;color:#334155">Vous trouverez en pièce jointe <strong>' . count($pdfFiles) . ' rapport(s) PDF</strong> :</p>
                <ul style="font-size:13px;color:#475569;margin:10px 0 16px 20px">
                    <li>' . implode('</li><li>', $labels) . '</li>
                </ul>
                <p style="font-size:12px;color:#94a3b8">Données des 7 derniers jours – Tous les transporteurs</p>
                <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
                <p style="font-size:12px;color:#94a3b8">Ce message est généré automatiquement – ne pas répondre.</p>
            </div>';
        } else {
            $mail->Body = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
                <h2 style="color:#667eea;margin-bottom:4px">📋 Rapports Global</h2>
                <p style="color:#64748b;">Généré le ' . date('d/m/Y à H:i') . '</p>
                <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
                <p style="font-size:14px;color:#334155">Aucun rapport sélectionné. Cet email a été envoyé sans pièce jointe.</p>
                <p style="font-size:12px;color:#94a3b8">Données des 7 derniers jours – Tous les transporteurs</p>
                <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0">
                <p style="font-size:12px;color:#94a3b8">Ce message est généré automatiquement – ne pas répondre.</p>
            </div>';
        }

        $mail->send();

        // Supprimer les fichiers temporaires
        foreach ($pdfFiles as $pdf) {
            if (file_exists($pdf['path'])) unlink($pdf['path']);
        }

        // Page de succès
        $reportsHtml = count($pdfFiles) > 0
            ? '<strong>Rapports envoyés:</strong><br>' . implode('<br>', $labels)
            : '<em>Aucun rapport sélectionné (email envoyé sans pièce jointe)</em>';

        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Succès</title>
            <style>body{font-family:"Segoe UI",sans-serif;background:#f0fdf4;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
            .box{background:#fff;border-radius:16px;padding:40px;text-align:center;box-shadow:0 8px 32px rgba(34,197,94,0.15);max-width:500px}
            .icon{font-size:4rem;margin-bottom:16px}h2{color:#166534;margin:0 0 8px}p{color:#64748b;margin:0 0 8px}
            .reports{background:#f0fdf4;border-left:4px solid #22c55e;padding:12px 16px;margin:16px 0;text-align:left}
            a{display:inline-block;background:#166534;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;margin-top:16px}
            </style></head><body>
            <div class="box">
                <div class="icon">✅</div>
                <h2>Email envoyé avec succès !</h2>
                <p>Envoyé à <strong>' . htmlspecialchars($email_to) . '</strong></p>
                <div class="reports">' . $reportsHtml . '</div>
                <a href="index.php">← Retour à l\'accueil</a>
            </div></body></html>';

    } catch (Exception $e) {
        // Nettoyer les fichiers en cas d'erreur
        foreach ($pdfFiles as $pdf) {
            if (file_exists($pdf['path'])) unlink($pdf['path']);
        }

        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title>
            <style>body{font-family:Arial,sans-serif;background:#fef2f2;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
            .box{background:#fff;border-radius:12px;padding:32px;max-width:500px;box-shadow:0 4px 16px rgba(0,0,0,0.1)}
            h2{color:#dc2626}pre{background:#fee2e2;padding:12px;border-radius:8px;font-size:.8rem;overflow-x:auto}a{color:#2563eb}
            </style></head><body>
            <div class="box"><h2>❌ Erreur d\'envoi</h2><p>Message :</p>
            <pre>' . htmlspecialchars($e->getMessage()) . '</pre>
            <p><a href="send_global_reports.php">← Retour</a></p></div></body></html>';
    }

    exit;
}

// ========================================
// FONCTIONS PDF BUILDERS
// ========================================

function buildPdfKilometrage($data) {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
            body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;margin:20px}
            h1{color:#667eea;border-bottom:3px solid #667eea;padding-bottom:10px;margin-top:0}
            table{width:100%;border-collapse:collapse;margin-top:20px}
            th,td{border:1px solid #e2e8f0;padding:10px;text-align:left}
            th{background:#667eea;color:#fff;font-weight:600}
            tr:nth-child(even){background:#f8fafc}
            .total{background:#d5f4e6;padding:15px;margin-top:20px;font-weight:bold}
            .empty{background:#fff3cd;padding:20px;text-align:center;border-left:4px solid #f59e0b;margin-top:20px}
        </style>
    </head><body>
        <h1>📊 Rapport Kilométrage - Tous Transporteurs</h1>
        <p>Période: 7 derniers jours | Généré: ' . date('d/m/Y H:i') . '</p>';

    if (empty($data)) {
        $html .= '<div class="empty">
            <strong>⚠️ Aucune donnée disponible</strong><br>
            Aucun enregistrement de kilométrage pour la période sélectionnée.
        </div>';
    } else {
        $html .= '<table>
            <thead><tr>
                <th>Transporteur</th>
                <th>Véhicule</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Durée</th>
                <th>Kilométrage</th>
            </tr></thead><tbody>';

        $total_km = 0;
        foreach ($data as $row) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['transporteur_nom']) . '</td>
                <td>' . htmlspecialchars($row['vehicule']) . '</td>
                <td>' . $row['debut'] . '</td>
                <td>' . $row['fin'] . '</td>
                <td>' . htmlspecialchars($row['duree']) . '</td>
                <td><strong>' . number_format($row['kilometrage'], 2) . ' km</strong></td>
            </tr>';
            $total_km += $row['kilometrage'];
        }

        $html .= '</tbody></table>';
        $html .= '<div class="total">Total Kilométrage: ' . number_format($total_km, 2) . ' km | ' . count($data) . ' enregistrements</div>';
    }

    $html .= '</body></html>';
    return $html;
}

function buildPdfInfractions($data) {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
            body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;margin:20px}
            h1{color:#ef4444;border-bottom:3px solid #ef4444;padding-bottom:10px;margin-top:0}
            table{width:100%;border-collapse:collapse;margin-top:20px}
            th,td{border:1px solid #e2e8f0;padding:10px;text-align:left}
            th{background:#ef4444;color:#fff;font-weight:600}
            tr:nth-child(even){background:#fef2f2}
            .total{background:#fee2e2;padding:15px;margin-top:20px;font-weight:bold}
            .empty{background:#d5f4e6;padding:20px;text-align:center;border-left:4px solid #22c55e;margin-top:20px}
        </style>
    </head><body>
        <h1>⚠️ Rapport Infractions - Tous Transporteurs</h1>
        <p>Période: 7 derniers jours | Généré: ' . date('d/m/Y H:i') . '</p>';

    if (empty($data)) {
        $html .= '<div class="empty">
            <strong>✅ Aucune infraction détectée</strong><br>
            Tous les véhicules respectent les règles pour la période sélectionnée.
        </div>';
    } else {
        $html .= '<table>
            <thead><tr>
                <th>Transporteur</th>
                <th>Véhicule</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Emplacement</th>
                <th>Infraction</th>
            </tr></thead><tbody>';

        foreach ($data as $row) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['transporteur_nom']) . '</td>
                <td>' . htmlspecialchars($row['vehicule']) . '</td>
                <td>' . $row['debut'] . '</td>
                <td>' . $row['fin'] . '</td>
                <td>' . htmlspecialchars($row['emplacement']) . '</td>
                <td><strong>' . htmlspecialchars($row['infraction']) . '</strong></td>
            </tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="total">Total Infractions: ' . count($data) . '</div>';
    }

    $html .= '</body></html>';
    return $html;
}

function buildPdfEvaluation($data) {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
            body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;margin:20px}
            h1{color:#9b59b6;border-bottom:3px solid #9b59b6;padding-bottom:10px;margin-top:0}
            table{width:100%;border-collapse:collapse;margin-top:20px}
            th,td{border:1px solid #e2e8f0;padding:10px;text-align:left}
            th{background:#9b59b6;color:#fff;font-weight:600}
            tr:nth-child(even){background:#f3e8ff}
            .eval-A{background:#d5f4e6}
            .eval-B{background:#fff3cd}
            .eval-C{background:#fadbd8}
            .total{background:#e8daef;padding:15px;margin-top:20px;font-weight:bold}
            .empty{background:#e0f2fe;padding:20px;text-align:center;border-left:4px solid #0ea5e9;margin-top:20px}
        </style>
    </head><body>
        <h1>📈 Rapport Évaluation Éco-conduite - Tous Transporteurs</h1>
        <p>Période: 7 derniers jours | Généré: ' . date('d/m/Y H:i') . '</p>';

    if (empty($data)) {
        $html .= '<div class="empty">
            <strong>📊 Aucune donnée d\'évaluation</strong><br>
            Aucun enregistrement d\'éco-conduite pour la période sélectionnée.
        </div>';
    } else {
        $html .= '<table>
            <thead><tr>
                <th>Transporteur</th>
                <th>Véhicule</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Emplacement</th>
                <th>Pénalités</th>
                <th>Évaluation</th>
            </tr></thead><tbody>';

        $total_pen = 0;
        foreach ($data as $row) {
            $evalClass = 'eval-' . substr($row['evaluation'], 0, 1);
            $html .= '<tr class="' . $evalClass . '">
                <td>' . htmlspecialchars($row['transporteur_nom']) . '</td>
                <td>' . htmlspecialchars($row['vehicule']) . '</td>
                <td>' . $row['debut'] . '</td>
                <td>' . $row['fin'] . '</td>
                <td>' . htmlspecialchars($row['emplacement']) . '</td>
                <td>' . number_format($row['penalites'], 2) . '</td>
                <td><strong>' . htmlspecialchars($row['evaluation']) . '</strong></td>
            </tr>';
            $total_pen += $row['penalites'];
        }

        $html .= '</tbody></table>';
        $html .= '<div class="total">Total Pénalités: ' . number_format($total_pen, 2) . ' | ' . count($data) . ' enregistrements</div>';
    }

    $html .= '</body></html>';
    return $html;
}

function generatePdf($html, $filename) {
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    $mpdf = new Mpdf(['mode'=>'utf-8','format'=>'A4',
                      'margin_top'=>10,'margin_bottom'=>10,'margin_left'=>10,'margin_right'=>10]);
    $mpdf->SetAuthor('Flotte Transport');
    $mpdf->WriteHTML($html);
    $mpdf->Output($tmpPath, 'F');
    return $tmpPath;
}
?>

</body>
</html>
