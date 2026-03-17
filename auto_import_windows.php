<?php
/**
 * AUTO_IMPORT.PHP - Import automatique depuis Wialon
 * ⏰ Version Windows - Fonctionne avec Task Scheduler
 * Chemin: C:\xampp\htdocs\vehicules\auto_import.php
 */

set_time_limit(1800);
header('Content-Type: text/html; charset=utf-8');

// ===========================
// 📝 Écran de démarrage
// ===========================
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Import Wialon</title>
        <style>
            body { font-family: Arial; background: #f5f5f5; margin: 20px; padding: 20px; }
            .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
            .log { background: #f8f9fa; border-left: 4px solid #3498db; padding: 10px; margin: 8px 0; font-family: monospace; font-size: 13px; }
            .success { border-left-color: #27ae60; color: #27ae60; }
            .error { border-left-color: #e74c3c; color: #e74c3c; background: #fadbd8; }
            .info { border-left-color: #3498db; color: #3498db; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>🔄 Import Wialon - Version Automatique</h1>
        <p><strong>Démarrage:</strong> ' . date('d/m/Y H:i:s') . '</p>
    ';
}

require_once("db.php");
require_once("config_wialon.php");

// ===========================
// Fonction de journalisation
// ===========================
function log_msg($msg, $type = 'info') {
    global $is_cli;
    
    $timestamp = date('d-m-Y H:i:s');
    
    if (!$is_cli) {
        $colors = array(
            'info' => '#3498db',
            'success' => '#27ae60',
            'error' => '#e74c3c',
            'warning' => '#f39c12'
        );
        echo '<div class="log ' . $type . '">['.$timestamp.'] ' . htmlspecialchars($msg) . '</div>';
    } else {
        echo "[$timestamp] [$type] $msg\n";
    }
    
    // Sauvegarde dans un fichier
    @mkdir(LOG_DIR, 0755, true);
    file_put_contents(
        LOG_MAIN, 
        "[$timestamp] [$type] $msg\n", 
        FILE_APPEND
    );
}

// ===========================
// Fonctions API Wialon
// ===========================

function getWialonSession() {
    // Connexion à l'API Wialon
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if (!$err) {
        $data = json_decode($response, true);
        return $data['eid'] ?? null;
    }
    
    log_msg("Erreur de connexion à Wialon: $err", 'error');
    return null;
}

function cleanReport($sid) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=report/cleanup_result&params={}&sid=".$sid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
    ));
    curl_exec($curl);
    curl_close($curl);
}

function execReport($group_id, $sid, $from_days = 30, $to_days = 0) {
    // Exécuter le rapport pour une plage de dates
    $base_time = time();
    $from = $base_time - ($from_days * 86400);
    $to = $base_time - ($to_days * 86400);
    
    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":1,"reportObjectId":'.$group_id.',"reportObjectSecId":0,"interval":{"from":'.$from.',"to":'.$to.',"flags":0}}&sid='.$sid;
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if (!$err) {
        $data = json_decode($response, true);
        if (isset($data['reportResult']['tables'])) {
            $tables = array();
            foreach ($data['reportResult']['tables'] as $table) {
                $tables[] = $table['rows'];
            }
            return $tables;
        }
    }
    
    return null;
}

function selectResults($groupe, $table_index, $row_count, $sid) {
    // Récupérer les résultats du tableau
    global $trajectcount;
    
    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":'.$table_index.',"config":{"type":"range","data":{"from":0,"to":'.$row_count.',"level":2}}}&sid='.$sid;
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if (!$err) {
        $data = json_decode($response, true);
        
        if (is_array($data) && isset($data[0]['r'])) {
            foreach ($data as $item) {
                if (isset($item['r'])) {
                    foreach ($item['r'] as $row) {
                        $vehicule = str_replace(" ", "", str_replace("-", "", str_replace("/", "", $row['c']['1'] ?? '')));
                        $parcour = $row['c']['2'] ?? '';
                        $depart = $row['c']['3'] ?? '';
                        $vers = $row['c']['4'] ?? '';
                        $debut = $row['t1'] ?? 0;
                        $fin = $row['t2'] ?? 0;
                        $penalite = $row['c']['8'] ?? 0;
                        $km = (float)str_replace("km", "", $row['c']['9'] ?? 0);
                        
                        $transporteur_id = $GLOBALS['TRANSPORTEUR_MAPPING'][$groupe] ?? 1;
                        
                        if (set_trajet($transporteur_id, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalite, $km, 'CD000')) {
                            $trajectcount++;
                        }
                    }
                }
            }
        }
    } else {
        log_msg("Erreur dans selectResults: $err", 'error');
    }
}

function cleanup_old_data() {
    // Supprimer les données plus anciennes que 30 jours
    try {
        $db = Cnx();
        $thirty_days_ago = date('Y-m-d H:i:s', time() - (30 * 86400));
        
        $stmt = $db->prepare("DELETE FROM trajets WHERE debut < ?");
        $stmt->execute([$thirty_days_ago]);
        
        $deleted = $stmt->rowCount();
        log_msg("Suppression de $deleted anciens trajets", 'info');
        
        return true;
    } catch (Exception $e) {
        log_msg("Erreur lors de la suppression des données: " . $e->getMessage(), 'error');
        return false;
    }
}

// ===========================
// 🚀 Démarrage
// ===========================

$trajectcount = 0;
$stats = array();

log_msg("========================================", 'info');
log_msg("Démarrage de l'import automatique", 'info');
log_msg("Heure: " . date('d/m/Y H:i:s'), 'info');
log_msg("Plage: Derniers " . IMPORT_DAYS . " jours", 'info');
log_msg("Nombre de groupes: " . count($GLOBALS['GROUP_MAPPING']), 'info');
log_msg("========================================", 'info');

// Connexion à Wialon
log_msg("Connexion à l'API Wialon...", 'info');
$sid = getWialonSession();

if (!$sid) {
    log_msg("Échec de la connexion - Import annulé", 'error');
    if (!$is_cli) {
        echo '<div class="log error">❌ Échec de la connexion à Wialon</div></div></body></html>';
    }
    exit;
}

log_msg("✅ Connexion à Wialon réussie", 'success');

// Suppression des données anciennes
cleanup_old_data();

// Traitement de chaque groupe
$total_tables = 0;
$start_time = time();

foreach ($GLOBALS['GROUP_MAPPING'] as $nom => $group_id) {
    log_msg("Traitement du groupe: $nom...", 'info');
    
    $trajectcount_before = $trajectcount;
    
    cleanReport($sid);
    sleep(1);
    
    // Récupération des données
    $tables = execReport($group_id, $sid, IMPORT_DAYS, 0);
    
    if ($tables === null) {
        log_msg("⚠️ Pas de données pour le groupe: $nom", 'warning');
        $stats[$nom] = 0;
        continue;
    }
    
    // Traitement de chaque tableau
    foreach ($tables as $table_index => $rows) {
        selectResults($nom, $table_index, count($rows) - 1, $sid);
        $total_tables++;
    }
    
    $inserted = $trajectcount - $trajectcount_before;
    $stats[$nom] = $inserted;
    
    log_msg("✅ $nom: $inserted trajets insérés", 'success');
}

$duration = time() - $start_time;

// ===========================
// Résumé
// ===========================

log_msg("========================================", 'info');
log_msg("✅ Import terminé avec succès", 'success');
log_msg("Durée: {$duration} secondes", 'info');
log_msg("Tableaux: $total_tables", 'info');
log_msg("Trajets: $trajectcount", 'success');
log_msg("========================================", 'info');

// Sauvegarde des statistiques
try {
    $db = Cnx();
    $timestamp = date('Y-m-d H:i:s');
    $stats_json = json_encode($stats, JSON_UNESCAPED_UNICODE);
    
    $stmt = $db->prepare("INSERT INTO import_logs (timestamp, total_trajets, total_tables, duration, stats) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$timestamp, $trajectcount, $total_tables, $duration, $stats_json]);
    
    log_msg("✅ Statistiques sauvegardées", 'success');
} catch (Exception $e) {
    log_msg("Erreur lors de la sauvegarde: " . $e->getMessage(), 'error');
}

if (!$is_cli) {
    echo '</div></body></html>';
}

?>