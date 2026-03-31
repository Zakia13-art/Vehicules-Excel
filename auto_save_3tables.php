<?php
/**
 * ========================================
 * AUTO SAVE 3 TABLES - AVEC TIMESTAMP
 * ========================================
 * Combine:
 * - Fonction execRep (avec temps)
 * - Loop automatique de lesgets.php
 * - 3 tables de api_global.php (KM, Infra, Eval)
 */

set_time_limit(1200);
require_once __DIR__ . "/db.php";

// ========================================
// LOG AVEC TIMESTAMP
// ========================================

function logAuto($message, $type = 'INFO') {
    @mkdir(__DIR__ . '/logs', 0755, true);
    $file = __DIR__ . "/logs/auto_save_3tables.log";
    $timestamp = date('d-m-Y H:i:s');
    $msg = "[$timestamp] [$type] $message\n";
    file_put_contents($file, $msg, FILE_APPEND);
    echo $msg;
}

// ========================================
// SESSION WIALON (de Desktop/codes)
// ========================================

function sid() {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB\"}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if (!$err) {
        $v_det = json_decode($response, true);
        return $v_det['eid'] ?? null;
    }
    logAuto("Erreur session: $err", 'ERROR');
    return null;
}

function cleanRepport($sid) {
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

// ========================================
// EXEC REP AVEC TEMPS 
// ========================================

function execRep($group, $sid, $from1, $to1, $templateId = 1) {
    // Utiliser time() actuel pour calcul correct
    if ($from1 > 0 || $to1 > 0) {
        $to = time() - ($to1 * 86400);
        $from = time() - ($from1 * 86400);
    } else {
        $to = time();
        $from = $to - (7 * 86400);
    }

    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":' . $templateId . ',"reportObjectId":' . $group . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

    curl_setopt_array($curl, array(
        CURLOPT_URL => $Url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if (!$err) {
        $v_det = json_decode($response, true);
        if (isset($v_det['reportResult']['tables'])) {
            $nbrtab = sizeof($v_det['reportResult']['tables']);
            $tabline = array();
            for ($i = 0; $i < $nbrtab; $i++) {
                $tabline[$i] = $v_det['reportResult']['tables'][$i]['rows'];
            }
            return $tabline;
        }
    }
    return null;
}

// ========================================
// SELECT RESULT ROWS
// ========================================

function selectResultRows($tableIndex, $rowCount, $sid) {
    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":' . $tableIndex . ',"config":{"type":"range","data":{"from":0,"to":' . $rowCount . ',"level":2}}}&sid=' . $sid;
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}

// ========================================
// INSERT 3 TABLES (de api_global.php)
// ========================================

function insertGlobalKilometrage($transporteur_id, $transporteur_nom, $vehicule, $debut, $fin, $duree, $km) {
    $db = Cnx();
    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');
    $km = (float) preg_replace('/[^0-9.]/', '', $km);

    $check = $db->prepare("SELECT COUNT(*) FROM global_kilometrage WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=?");
    $check->execute([$transporteur_id, $vehicule, $debut_dt, $fin_dt]);
    if ($check->fetchColumn() > 0) return false;

    $stmt = $db->prepare("INSERT INTO global_kilometrage (transporteur_id, transporteur_nom, vehicule, debut, fin, duree, kilometrage) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$transporteur_id, $transporteur_nom, $vehicule, $debut_dt, $fin_dt, $duree, $km]);
}

function insertGlobalInfraction($transporteur_id, $transporteur_nom, $vehicule, $debut, $fin, $emplacement, $infraction) {
    $db = Cnx();
    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');

    if ($infraction === '-----' || trim($infraction) === '') return false;

    $check = $db->prepare("SELECT COUNT(*) FROM global_infractions WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=? AND infraction=?");
    $check->execute([$transporteur_id, $vehicule, $debut_dt, $fin_dt, $infraction]);
    if ($check->fetchColumn() > 0) return false;

    $stmt = $db->prepare("INSERT INTO global_infractions (transporteur_id, transporteur_nom, vehicule, debut, fin, emplacement, infraction) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$transporteur_id, $transporteur_nom, $vehicule, $debut_dt, $fin_dt, $emplacement, $infraction]);
}

function insertGlobalEvaluation($transporteur_id, $transporteur_nom, $vehicule, $debut, $fin, $emplacement, $penalites, $evaluation) {
    $db = Cnx();
    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');
    $penalites = (float) preg_replace('/[^0-9.]/', '', $penalites);

    $check = $db->prepare("SELECT COUNT(*) FROM global_evaluation WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=?");
    $check->execute([$transporteur_id, $vehicule, $debut_dt, $fin_dt]);
    if ($check->fetchColumn() > 0) return false;

    $stmt = $db->prepare("INSERT INTO global_evaluation (transporteur_id, transporteur_nom, vehicule, debut, fin, emplacement, penalites, evaluation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$transporteur_id, $transporteur_nom, $vehicule, $debut_dt, $fin_dt, $emplacement, $penalites, $evaluation]);
}

// ========================================
// TRANSPORTEURS
// ========================================

$tab_group = array(
    'STE STB' => array('id' => 26577266, 'transporteur_id' => 1),
    'SOTRAFOREST' => array('id' => 26623545, 'transporteur_id' => 2),
    'SOMATRIN' => array('id' => 30071668, 'transporteur_id' => 3),
    'MARATRANS' => array('id' => 19631505, 'transporteur_id' => 4),
    'GTC CIMAT' => array('id' => 30085013, 'transporteur_id' => 5),
    'FLEXILOG' => array('id' => 23607333, 'transporteur_id' => 6),
    'FIRST LOGISTIQUE' => array('id' => 23297975, 'transporteur_id' => 7),
    'FAYSSAL METAL' => array('id' => 30066387, 'transporteur_id' => 8),
    'FAST TRANS' => array('id' => 19635796, 'transporteur_id' => 9),
    'COTRAMAB' => array('id' => 19585601, 'transporteur_id' => 10),
    'CORYAD' => array('id' => 19585581, 'transporteur_id' => 11),
    'CIMATRAK' => array('id' => 30105885, 'transporteur_id' => 12),
    'CHOUROUK' => array('id' => 15125142, 'transporteur_id' => 13),
    'BOUTCHRAFIN_CIMAT' => array('id' => 19022033, 'transporteur_id' => 14),
    'ANFAL' => array('id' => 27720630, 'transporteur_id' => 15)
);

// ========================================
// PROCESS 3 REPORTS (comme api_global.php)
// ========================================

function processKilometrage($nom, $group, $sid, $from1, $to1) {
    $transporteur_id = $group['transporteur_id'];
    $group_id = $group['id'];

    $tables = execRep($group_id, $sid, $from1, $to1, 529); // Template ID 529 (Kilométrage+Heures moteur cimat)

    if (!$tables) return 0;

    $count = 0;
    foreach ($tables as $tableIndex => $table) {
        $rows = $table['rows'] ?? 0;
        if ($rows > 0) {
            $result = selectResultRows($tableIndex, $rows, $sid);
            if (isset($result[0]['r'])) {
                foreach ($result as $row) {
                    if (isset($row['r'])) {
                        foreach ($row['r'] as $data) {
                            // Template ID 1 format: c[1]=vehicule, c[9]=KM
                            $vehicule = $data['c']['1'] ?? '';
                            $debut = $data['t1'] ?? 0;
                            $fin = $data['t2'] ?? 0;
                            $km = $data['c']['9'] ?? 0;

                            // Calculer la durée
                            $diff_seconds = $fin - $debut;
                            $hours = floor($diff_seconds / 3600);
                            $minutes = floor(($diff_seconds % 3600) / 60);
                            $duree = "$hours h $minutes min";

                            if (insertGlobalKilometrage($transporteur_id, $nom, $vehicule, $debut, $fin, $duree, $km)) {
                                $count++;
                            }
                        }
                    }
                }
            }
        }
    }
    return $count;
}

function processInfractions($nom, $group, $sid, $from1, $to1) {
    $transporteur_id = $group['transporteur_id'];
    $group_id = $group['id'];

    $tables = execRep($group_id, $sid, $from1, $to1, 36793); // Template ID 36793 (Eco-conduite cimat/infraction)

    if (!$tables) return 0;

    $count = 0;
    foreach ($tables as $tableIndex => $table) {
        $rows = $table['rows'] ?? 0;
        if ($rows > 0) {
            $result = selectResultRows($tableIndex, $rows, $sid);
            if (isset($result[0]['r'])) {
                foreach ($result as $row) {
                    if (isset($row['r'])) {
                        foreach ($row['r'] as $data) {
                            // Template ID 1 format: c[1]=vehicule, c[3]=depart, c[8]=pénalités
                            $vehicule = $data['c']['1'] ?? '';
                            $debut = $data['t1'] ?? 0;
                            $fin = $data['t2'] ?? 0;
                            $depart = $data['c']['3'] ?? '';
                            $penalites = (int)($data['c']['8'] ?? 0);

                            // Insert seulement si pénalités > 0
                            if ($penalites > 0) {
                                $emplacement = $depart ?: 'Inconnu';
                                $infraction = "$penalites pénalité(s)";
                                if (insertGlobalInfraction($transporteur_id, $nom, $vehicule, $debut, $fin, $emplacement, $infraction)) {
                                    $count++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    return $count;
}

function processEvaluation($nom, $group, $sid, $from1, $to1) {
    $transporteur_id = $group['transporteur_id'];
    $group_id = $group['id'];

    $tables = execRep($group_id, $sid, $from1, $to1, 21146); // Template ID 21146 (Eco-conduite cimat/Evaluation)

    if (!$tables) return 0;

    $count = 0;
    foreach ($tables as $tableIndex => $table) {
        $rows = $table['rows'] ?? 0;
        if ($rows > 0) {
            $result = selectResultRows($tableIndex, $rows, $sid);
            if (isset($result[0]['r'])) {
                foreach ($result as $row) {
                    if (isset($row['r'])) {
                        foreach ($row['r'] as $data) {
                            // Template ID 1 format: c[1]=vehicule, c[3]=depart, c[8]=pénalités
                            $vehicule = $data['c']['1'] ?? '';
                            $debut = $data['t1'] ?? 0;
                            $fin = $data['t2'] ?? 0;
                            $depart = $data['c']['3'] ?? '';
                            $penalites = (int)($data['c']['8'] ?? 0);
                            $emplacement = $depart ?: 'Inconnu';
                            $evaluation = $penalites > 0 ? 'Non conforme' : 'Conforme';
                            if (insertGlobalEvaluation($transporteur_id, $nom, $vehicule, $debut, $fin, $emplacement, $penalites, $evaluation)) {
                                $count++;
                            }
                        }
                    }
                }
            }
        }
    }
    return $count;
}

// ========================================
// MAIN - LOOP AUTOMATIQUE (de lesgets.php)
// ========================================

$execution_start = date('Y-m-d H:i:s');
logAuto("========================================");
logAuto("AUTO SAVE 3 TABLES - 7 DERNIERS JOURS");
logAuto("Execution: $execution_start");
logAuto("========================================");

$sid = sid();
if (!$sid) {
    logAuto("ERREUR: Impossible de creer session", 'ERROR');
    exit(1);
}

logAuto("Session cree: " . substr($sid, 0, 20) . "...");

// Loop pour 7 derniers jours (comme lesgets.php)
$i = 7;
$total_km = 0;
$total_infra = 0;
$total_eval = 0;
$stats = array();

while ($i >= 0) {
    $from1 = $i + 1;
    $to1 = $i - 0;
    $i = $i - 1;

    if ($to1 < 0) $to1 = 0;

    logAuto("Periode: Jours -$from1 a -$to1");

    foreach ($tab_group as $nom => $groupe) {
        // KM
        cleanRepport($sid);
        sleep(1);
        $count_km = processKilometrage($nom, $groupe, $sid, $from1, $to1);
        $total_km += $count_km;

        // Infractions
        cleanRepport($sid);
        sleep(1);
        $count_infra = processInfractions($nom, $groupe, $sid, $from1, $to1);
        $total_infra += $count_infra;

        // Evaluation
        cleanRepport($sid);
        sleep(1);
        $count_eval = processEvaluation($nom, $groupe, $sid, $from1, $to1);
        $total_eval += $count_eval;

        if (!isset($stats[$nom])) {
            $stats[$nom] = array('km' => 0, 'infra' => 0, 'eval' => 0);
        }
        $stats[$nom]['km'] += $count_km;
        $stats[$nom]['infra'] += $count_infra;
        $stats[$nom]['eval'] += $count_eval;
    }
}

$execution_end = date('Y-m-d H:i:s');
logAuto("========================================");
logAuto("FIN SAVE - KM: $total_km | Infra: $total_infra | Eval: $total_eval");
logAuto("Execution: $execution_start -> $execution_end");
logAuto("========================================");

echo "\n=== RESUME ===\n";
foreach ($stats as $nom => $s) {
    echo "$nom: KM={$s['km']}, Infra={$s['infra']}, Eval={$s['eval']}\n";
}
echo "\nTOTAL: KM=$total_km, Infra=$total_infra, Eval=$total_eval\n";
echo "Execution: $execution_start -> $execution_end\n";
?>
