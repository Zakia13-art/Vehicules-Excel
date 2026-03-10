<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use Mpdf\Mpdf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get POST data
$html = $_POST['html'] ?? '';
$email_to = filter_var(trim($_POST['email_to'] ?? ''), FILTER_VALIDATE_EMAIL)
    ? trim($_POST['email_to']) : MAIL_TO;
$report_name = $_POST['report_name'] ?? 'Rapport';

if (empty($html)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Aucun contenu HTML fourni.']);
    exit;
}

try {
    // Generate PDF
    $mpdf = new Mpdf(['mode'=>'utf-8','format'=>'A4-L',
                      'margin_top'=>10,'margin_bottom'=>10,'margin_left'=>12,'margin_right'=>12]);
    $mpdf->WriteHTML($html);
    $pdfContent = $mpdf->Output('', 'S'); // Return as string

    // Send email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'zakia.controlflot@gmail.com';
    $mail->Password   = 'vqnslggncuitnavh';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->setFrom('zakia.controlflot@gmail.com', 'Flotte Transport – Rapport Auto');
    $mail->addAddress($email_to);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = '📋 ' . $report_name . ' – ' . date('d/m/Y');
    $mail->isHTML(true);
    $mail->Body = '<p>Bonjour,</p><p>Vous trouverez ci-joint le rapport ' . htmlspecialchars($report_name) . '.</p><p>Ce rapport a été généré automatiquement le ' . date('d/m/Y à H:i') . '.</p>';
    $mail->addStringAttachment($pdfContent, $report_name . '_' . date('Ymd_Hi') . '.pdf');
    $mail->send();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Email envoyé avec succès !']);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
