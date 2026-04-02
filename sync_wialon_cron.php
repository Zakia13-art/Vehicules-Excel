<?php
/**
 * sync_wialon_cron.php
 * Script de synchronisation automatique Wialon → BDD
 * FRÉQUENCE: Toutes les 5 minutes
 * À exécuter via Cron: toutes les 5 minutes
 * Commande Cron: /usr/bin/php /chemin/vers/sync_wialon_cron.php
 */

set_time_limit(300); // 5 minutes max

require_once 'db.php';
require_once 'getitemid.php';

// ═══════════════════════════════════════════════════════════
// LOGGER
// ═══════════════════════════════════════════════════════════

function log_sync($message, $type = 'INFO') {
    @mkdir('logs', 0755, true);
    $file = "logs/sync_wialon.log";
    $timestamp = date('d-m-Y H:i:s');
    $msg = "[$timestamp] [$type] $message\n";
    file_put_contents($file, $msg, FILE_APPEND);
    echo $msg;
}

// ═══════════════════════════════════════════════════════════
// CRÉER SESSION WIALON
// ═══════════════════════════════════════════════════════════

function get_wialon_session() {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"b6db68331b4b6ed14b61dbfeeaad9a0605EA995CF621CE53D5C01A0A29C9FCFB6B2902A8\"}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if (!$err) {
        $data = json_decode($response, true);
        return $data['eid'] ?? null;
    }
    
    log_sync("Erreur création session: $err", 'ERROR');
    return null;
}

// ═══════════════════════════════════════════════════════════
// NETTOYER ANCIEN RAPPORT
// ═══════════════════════════════════════════════════════════

function cleanup_report($sid) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=report/cleanup_result&params={}&sid=$sid",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    curl_exec($curl);
    curl_close($curl);
}

// ═══════════════════════════════════════════════════════════
// LISTES DES TRANSPORTEURS À SYNCHRONISER
// ═══════════════════════════════════════════════════════════

$transporteurs = array(
    'BOUTCHRAFINE' => 19022033,
    'SOMATRIN' => 19596491,
    'MARATRANS' => 19631505,
    'G.T.C' => 19590737,
    'DOUKALI' => 19585587,
    'COTRAMAB' => 19585601,
    'CORYAD' => 19585581,
    'CONSMETA' => 19629962,
    'CHOUROUK' => 19630023,
    'CARRE' => 19643391,
    'STB' => 19585942,
    'FASTTRANS' => 19635796
);

// ═══════════════════════════════════════════════════════════
// MAIN - SYNCHRONISATION (Toutes les 5 minutes)
// ═══════════════════════════════════════════════════════════

log_sync("═══════════════════════════════════════════════════════════");
log_sync("🔄 SYNCHRONISATION WIALON STARTED (Fréquence: 5 minutes)");
log_sync("═══════════════════════════════════════════════════════════");

// Créer session
$sid = get_wialon_session();
if (!$sid) {
    log_sync("❌ ERREUR: Impossible de créer une session Wialon", 'ERROR');
    exit(1);
}

log_sync("✅ Session créée: $sid");

$total_inserted = 0;
$total_skipped = 0;
$start_time = time();

// Récupérer les données RÉCENTES (dernière heure)
// Pour une synchro toutes les 5 min, on récupère 1 jour (marge de sécurité)
try {
    $db = Cnx();
    
    foreach ($transporteurs as $nom => $groupe_id) {
        log_sync("├─ Processing: $nom (ID: $groupe_id)");
        
        cleanup_report($sid);
        sleep(1);
        
        // Exécuter le rapport pour le dernier 1 jour
        // Paramètres: from=1 jour, to=0 (aujourd'hui)
        $report_index = execRep($groupe_id, $sid, 1, 0);
        
        if ($report_index === null) {
            log_sync("├─ ⚠️ Aucune donnée pour $nom", 'WARNING');
            continue;
        }
        
        $count = 0;
        foreach ($report_index as $value) {
            selectRes($groupe_id, $count, $value, $sid);
            $count++;
        }
        
        log_sync("├─ ✅ $nom: $count table(s) importée(s)");
        $total_inserted += $count;
    }
    
    $elapsed_time = time() - $start_time;
    
    log_sync("═══════════════════════════════════════════════════════════");
    log_sync("✅ SYNCHRONISATION TERMINÉE");
    log_sync("├─ Total trajets traités: $total_inserted");
    log_sync("├─ Temps écoulé: ${elapsed_time}s");
    log_sync("├─ Prochaine sync: +5 minutes");
    log_sync("└─ Statut: OK");
    log_sync("═══════════════════════════════════════════════════════════");
    
} catch (Exception $e) {
    log_sync("❌ ERREUR BDD: " . $e->getMessage(), 'ERROR');
    exit(1);
}

exit(0);
?>