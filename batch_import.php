<?php
/**
 * ========================================
 * BATCH IMPORT - AUTOMATIQUE TOUTES LES 24H
 * ========================================
 * Exécute tous les imports et stocke dans la base
 * Peut être appelé par:
 * - Browser: http://localhost/vehicules/batch_import.php
 * - Cron/Task Scheduler: php batch_import.php
 * - .bat file
 */

// Silence output pour les logs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/batch_import.log');

@mkdir('logs', 0755, true);

// Log file
$log_file = 'logs/batch_import_' . date('Y-m-d') . '.log';

function log_msg($msg) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $msg\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $msg . "\n";
}

log_msg("========== DÉBUT IMPORT AUTOMATIQUE ==========");

require_once "api_global_simple.php";

try {
    // Connexion
    $sid = sid();
    if (!$sid) {
        log_msg("❌ ERREUR: Connexion Wialon échouée");
        exit(1);
    }
    log_msg("✅ Connecté à Wialon");

    global $tab_group;

    $stats = array(
        'km' => 0,
        'infra' => 0,
        'eval' => 0
    );

    // Traiter chaque groupe
    foreach ($tab_group as $nom => $gid) {
        log_msg("🔄 Traitement: $nom (ID: $gid)");

        // 1. KILOMETRAGE
        $tables_km = execGlobalReport(4, $gid, $sid);
        if ($tables_km) {
            $count = 0;
            foreach ($tables_km as $idx => $tbl) {
                $rows = $tbl['rows'] ?? 0;
                if ($rows > 0) {
                    $data = getReportRows($idx, $rows, $sid);
                    if (isset($data[0]['r'])) {
                        foreach ($data as $r) {
                            if (isset($r['r'])) {
                                foreach ($r['r'] as $d) {
                                    $veh = $d['c']['0'] ?? '';
                                    $deb = $d['t1'] ?? 0;
                                    $fin = $d['t2'] ?? 0;
                                    $dur = $d['c']['1'] ?? '';
                                    $km = $d['c']['2'] ?? 0;
                                    if (insertGlobalKM($nom, $veh, $deb, $fin, $dur, $km)) {
                                        $count++;
                                        $stats['km']++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            log_msg("  ✅ KM: $count insérés");
        }
        cleanRepport($sid);

        // 2. INFRACTIONS
        $tables_infra = execGlobalReport(2, $gid, $sid);
        if ($tables_infra) {
            $count = 0;
            foreach ($tables_infra as $idx => $tbl) {
                $rows = $tbl['rows'] ?? 0;
                if ($rows > 0) {
                    $data = getReportRows($idx, $rows, $sid);
                    if (isset($data[0]['r'])) {
                        foreach ($data as $r) {
                            if (isset($r['r'])) {
                                foreach ($r['r'] as $d) {
                                    $veh = $d['c']['0'] ?? '';
                                    $deb = $d['t1'] ?? 0;
                                    $fin = $d['t2'] ?? 0;
                                    $emp = $d['c']['1'] ?? '';
                                    $inf = $d['c']['2'] ?? '';
                                    if (insertGlobalInfra($nom, $veh, $deb, $fin, $emp, $inf)) {
                                        $count++;
                                        $stats['infra']++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            log_msg("  ✅ Infractions: $count insérées");
        }
        cleanRepport($sid);

        // 3. EVALUATION
        $tables_eval = execGlobalReport(7, $gid, $sid);
        if ($tables_eval) {
            $count = 0;
            foreach ($tables_eval as $idx => $tbl) {
                $rows = $tbl['rows'] ?? 0;
                if ($rows > 0) {
                    $data = getReportRows($idx, $rows, $sid);
                    if (isset($data[0]['r'])) {
                        foreach ($data as $r) {
                            if (isset($r['r'])) {
                                foreach ($r['r'] as $d) {
                                    $veh = $d['c']['0'] ?? '';
                                    $deb = $d['t1'] ?? 0;
                                    $fin = $d['t2'] ?? 0;
                                    $emp = $d['c']['1'] ?? '';
                                    $pen = $d['c']['2'] ?? 0;
                                    $eval = $d['c']['3'] ?? '';
                                    if (insertGlobalEval($nom, $veh, $deb, $fin, $emp, $pen, $eval)) {
                                        $count++;
                                        $stats['eval']++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            log_msg("  ✅ Évaluation: $count insérées");
        }
        cleanRepport($sid);
    }

    // Résumé
    $total = $stats['km'] + $stats['infra'] + $stats['eval'];
    log_msg("========== RÉSUMÉ ==========");
    log_msg("Kilométrage: {$stats['km']}");
    log_msg("Infractions: {$stats['infra']}");
    log_msg("Évaluation: {$stats['eval']}");
    log_msg("TOTAL: $total enregistrements");
    log_msg("========== FIN IMPORT ==========");
    log_msg("");

    exit(0);

} catch (Exception $e) {
    log_msg("❌ ERREUR FATALE: " . $e->getMessage());
    exit(1);
}
?>
