<?php
/**
 * rapp.php — Multi-Sociétés
 * Génère les rapports pour TOUTES les sociétés
 */

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'getitemid.php';

class RapportGenerator {
    private $getter;

    public function __construct() {
        $this->getter = new ItemGetter();
    }

    /**
     * Générer un rapport pour un véhicule spécifique
     */
    public function generateVehicleReport($vehicleName, $society) {
        $details = $this->getter->getVehicleDetails($vehicleName, $society);
        if (!$details) return null;

        $eco = $details['eco'] ?? [];
        $kilo = $details['kilo'] ?? [];

        return [
            'title' => 'Rapport Détail - ' . $vehicleName,
            'society' => $society,
            'date' => date('d/m/Y H:i'),
            'vehicle' => $vehicleName,
            'data' => [
                'eco_evaluation' => $eco['Évaluation'] ?? '—',
                'eco_infractions' => $eco['Infraction'] ?? '—',
                'kilo_km' => $kilo['Kilométrage'] ?? '—',
                'kilo_duration' => $kilo['Durée'] ?? '—',
                'kilo_hours' => $this->parseHours($kilo['Durée'] ?? '0'),
            ],
            'generated_by' => 'Système Flotte - ' . $society,
        ];
    }

    /**
     * Générer un rapport récapitulatif pour une société
     */
    public function generateSummaryReport($society = null) {
        $vehicles = $this->getter->getAllVehicles($society);
        $totalVehicles = count($vehicles);
        $totalNote = array_sum(array_column($vehicles, 'note'));
        $totalAlerts = array_sum(array_column($vehicles, 'alertes'));
        $totalKm = array_sum(array_column($vehicles, 'km'));
        
        $title = $society ? 'Rapport Récapitulatif - ' . $society : 'Rapport Récapitulatif Flotte';
        
        return [
            'title' => $title,
            'society' => $society ?? 'Toutes les sociétés',
            'date' => date('d/m/Y H:i'),
            'total_vehicles' => $totalVehicles,
            'total_note' => $totalVehicles > 0 ? round($totalNote / $totalVehicles) : 0,
            'total_alerts' => $totalAlerts,
            'total_km' => $totalKm,
            'vehicles' => $vehicles,
            'generated_by' => 'Système Gestion Flotte',
        ];
    }

    /**
     * Générer un rapport en HTML
     */
    public function generateHTMLReport($vehicleName, $society) {
        $report = $this->generateVehicleReport($vehicleName, $society);
        if (!$report) return null;

        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; background: #f0f4f8; color: #1e293b; }
                .header { background: #0f172a; color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .header h1 { margin: 0 0 5px; font-size: 24px; }
                .header .society { font-size: 14px; opacity: 0.9; margin: 5px 0 0; }
                .header p { margin: 8px 0 0; font-size: 13px; opacity: 0.8; }
                .section { background: #fff; padding: 20px; margin: 15px 0; border-left: 4px solid #3b82f6; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
                .section h2 { margin-top: 0; color: #0f172a; font-size: 18px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
                .row { display: flex; justify-content: space-between; margin: 12px 0; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
                .row:last-child { border-bottom: none; }
                .label { font-weight: bold; color: #64748b; font-size: 14px; }
                .value { color: #0f172a; font-weight: 600; text-align: right; }
                .footer { text-align: center; color: #94a3b8; margin-top: 40px; font-size: 12px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . htmlspecialchars($report['title']) . '</h1>
                <div class="society">🏢 Société: ' . htmlspecialchars($report['society']) . '</div>
                <p>Généré le ' . $report['date'] . '</p>
            </div>
            
            <div class="section">
                <h2>🚗 Véhicule</h2>
                <div class="row">
                    <span class="label">Nom:</span>
                    <span class="value">' . htmlspecialchars($report['vehicle']) . '</span>
                </div>
                <div class="row">
                    <span class="label">Société:</span>
                    <span class="value">' . htmlspecialchars($report['society']) . '</span>
                </div>
            </div>
            
            <div class="section">
                <h2>📊 Éco-conduite</h2>
                <div class="row">
                    <span class="label">Évaluation:</span>
                    <span class="value">' . htmlspecialchars($report['data']['eco_evaluation']) . '/100</span>
                </div>
                <div class="row">
                    <span class="label">Infractions Relevées:</span>
                    <span class="value">' . htmlspecialchars($report['data']['eco_infractions']) . '</span>
                </div>
            </div>
            
            <div class="section">
                <h2>📍 Kilométrage & Durée</h2>
                <div class="row">
                    <span class="label">Kilométrage Total:</span>
                    <span class="value">' . htmlspecialchars($report['data']['kilo_km']) . '</span>
                </div>
                <div class="row">
                    <span class="label">Durée:</span>
                    <span class="value">' . htmlspecialchars($report['data']['kilo_duration']) . '</span>
                </div>
                <div class="row">
                    <span class="label">Heures de Conduite:</span>
                    <span class="value">' . $report['data']['kilo_hours'] . ' h</span>
                </div>
            </div>
            
            <div class="footer">
                <p>' . $report['generated_by'] . '</p>
                <p>Rapport généré automatiquement le ' . date('d/m/Y à H:i') . '</p>
                <p>© 2024 - Tous droits réservés</p>
            </div>
        </body>
        </html>
        ';

        return $html;
    }

    private function parseHours($duree) {
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

    /**
     * Exporter en HTML
     */
    public function exportHTML($vehicleName, $society, $filename = null) {
        if (!$filename) {
            $filename = 'rapport_' . strtolower(str_replace(' ', '_', $vehicleName)) . '_' . date('Ymd_Hi') . '.html';
        }
        $html = $this->generateHTMLReport($vehicleName, $society);
        if (!$html) return false;

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $html;
        return true;
    }

    /**
     * Obtenir les fichiers source
     */
    public function getSourceFiles($society = null) {
        return $this->getter->getFileInfo($society);
    }

    /**
     * Obtenir les sociétés
     */
    public function getSocieties() {
        return $this->getter->getSocieties();
    }
}

if (isset($_GET['action'])) {
    $generator = new RapportGenerator();
    header('Content-Type: application/json; charset=utf-8');
    switch ($_GET['action']) {
        case 'generate_vehicle':
            $name = $_GET['vehicle'] ?? '';
            $society = $_GET['society'] ?? '';
            echo json_encode($generator->generateVehicleReport($name, $society));
            break;
        case 'generate_summary':
            $society = $_GET['society'] ?? null;
            echo json_encode($generator->generateSummaryReport($society));
            break;
        case 'societies':
            echo json_encode($generator->getSocieties());
            break;
        case 'files':
            $society = $_GET['society'] ?? null;
            echo json_encode($generator->getSourceFiles($society));
            break;
        default:
            echo json_encode(['error' => 'Action inconnue']);
    }
    exit;
}

$generator = new RapportGenerator();