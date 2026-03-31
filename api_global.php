<?php
/**
 * ========================================
 * API GLOBAL - WIALON REPORTS (TOUS LES GROUPS)
 * ========================================
 * Fetch 3 types de reports pour TOUS les transporteurs:
 * 1. Kilométrage (Template ID: 4)
 * 2. Infractions (Template ID: 2)
 * 3. Évaluation (Template ID: 7)
 *
 * Resource ID: 19907460
 */

require_once "lesgets.php";

set_time_limit(600);

// ========================================
// CONFIGURATION
// ========================================
define('REPORT_RESOURCE_ID', 19907460);

// Template IDs
// Templates spécifiques pour chaque type de rapport
define('TEMPLATE_KILOMETRAGE', 529);       // Kilométrage+Heures moteur cimat
define('TEMPLATE_INFRACTIONS', 36793);     // Eco-conduite cimat/infraction
define('TEMPLATE_EVALUATION', 21146);      // Eco-conduite cimat/Evaluation

// TOUS LES GROUPS (IDs originaux - tous corrects)
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
// FUNCTIONS
// ========================================

/**
 * execReportGlobal - Exécuter un rapport Wialon pour un group
 */
function execReportGlobal($templateId, $groupId, $sid, $from = 0, $to = 0) {
    $base_time = 1575503999;

    if ($from > 0 || $to > 0) {
        $from = ($base_time - ($from * 86400));
        $to = ($base_time - ($to * 86400));
    } else {
        $to = time();
        $from = $to - (7 * 86400);
    }

    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":' . REPORT_RESOURCE_ID . ',"reportTemplateId":' . $templateId . ',"reportObjectId":' . $groupId . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

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
            return $data['reportResult']['tables'];
        }
    }

    return null;
}

/**
 * selectResultRows - Récupérer les lignes du rapport
 */
function selectResultRows($tableIndex, $rowCount, $sid) {
    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":' . $tableIndex . ',"config":{"type":"range","data":{"from":0,"to":' . $rowCount . ',"level":2}}}&sid=' . $sid;

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}

// ========================================
// INSERT FUNCTIONS (GLOBAL)
// ========================================

/**
 * insertGlobalKilometrage - Insérer données kilométrage (tous transporteurs)
 */
function insertGlobalKilometrage($transporteur_id, $transporteur_nom, $vehicule, $debut, $fin, $duree, $km) {
    $db = Cnx();

    // Nettoyer véhicule
    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));

    // Convertir timestamps
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');

    // Nettoyer KM
    $km = (float) preg_replace('/[^0-9.]/', '', $km);

    // Vérifier doublon
    $check = $db->prepare("SELECT COUNT(*) FROM global_kilometrage WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=?");
    $check->execute([$transporteur_id, $vehicule, $debut_dt, $fin_dt]);

    if ($check->fetchColumn() > 0) {
        return false;
    }

    // Insérer
    $stmt = $db->prepare("INSERT INTO global_kilometrage (transporteur_id, transporteur_nom, vehicule, debut, fin, duree, kilometrage) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$transporteur_id, $transporteur_nom, $vehicule, $debut_dt, $fin_dt, $duree, $km]);
}

/**
 * insertGlobalInfraction - Insérer données infractions (tous transporteurs)
 */
function insertGlobalInfraction($transporteur_id, $transporteur_nom, $vehicule, $debut, $fin, $emplacement, $infraction) {
    $db = Cnx();

    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');

    // Si pas d'infraction
    if ($infraction === '-----' || trim($infraction) === '') {
        return false;
    }

    $check = $db->prepare("SELECT COUNT(*) FROM global_infractions WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=? AND infraction=?");
    $check->execute([$transporteur_id, $vehicule, $debut_dt, $fin_dt, $infraction]);

    if ($check->fetchColumn() > 0) {
        return false;
    }

    $stmt = $db->prepare("INSERT INTO global_infractions (transporteur_id, transporteur_nom, vehicule, debut, fin, emplacement, infraction) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$transporteur_id, $transporteur_nom, $vehicule, $debut_dt, $fin_dt, $emplacement, $infraction]);
}

/**
 * insertGlobalEvaluation - Insérer données évaluation (tous transporteurs)
 */
function insertGlobalEvaluation($transporteur_id, $transporteur_nom, $vehicule, $debut, $fin, $emplacement, $penalites, $evaluation) {
    $db = Cnx();

    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');

    // Nettoyer pénalités
    $penalites = (float) preg_replace('/[^0-9.]/', '', $penalites);

    $check = $db->prepare("SELECT COUNT(*) FROM global_evaluation WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=?");
    $check->execute([$transporteur_id, $vehicule, $debut_dt, $fin_dt]);

    if ($check->fetchColumn() > 0) {
        return false;
    }

    $stmt = $db->prepare("INSERT INTO global_evaluation (transporteur_id, transporteur_nom, vehicule, debut, fin, emplacement, penalites, evaluation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$transporteur_id, $transporteur_nom, $vehicule, $debut_dt, $fin_dt, $emplacement, $penalites, $evaluation]);
}

// ========================================
// PROCESS FUNCTIONS (GLOBAL)
// ========================================

/**
 * processGlobalKilometrage - Traiter kilométrage pour un group (Template ID 1)
 */
function processGlobalKilometrage($nom, $group, $sid) {
    global $tab_group;

    $transporteur_id = $group['transporteur_id'];
    $group_id = $group['id'];

    echo '<div class="log info">📊 ' . $nom . ' - Kilométrage...</div>';

    $tables = execReportGlobal(TEMPLATE_KILOMETRAGE, $group_id, $sid);

    if (!$tables) {
        echo '<div class="log">⚠️ ' . $nom . ' - Pas de données kilométrage</div>';
        return 0;
    }

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

    echo '<div class="log success">✅ ' . $nom . ' - KM: ' . $count . ' enregistrements</div>';
    return $count;
}

/**
 * processGlobalInfractions - Traiter infractions pour un group (Template ID 1)
 */
function processGlobalInfractions($nom, $group, $sid) {
    $transporteur_id = $group['transporteur_id'];
    $group_id = $group['id'];

    echo '<div class="log info">⚠️ ' . $nom . ' - Infractions...</div>';

    $tables = execReportGlobal(TEMPLATE_INFRACTIONS, $group_id, $sid);

    if (!$tables) {
        echo '<div class="log">⚠️ ' . $nom . ' - Pas de données infractions</div>';
        return 0;
    }

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

    echo '<div class="log success">✅ ' . $nom . ' - Infractions: ' . $count . ' enregistrements</div>';
    return $count;
}

/**
 * processGlobalEvaluation - Traiter évaluation pour un group (Template ID 1)
 */
function processGlobalEvaluation($nom, $group, $sid) {
    $transporteur_id = $group['transporteur_id'];
    $group_id = $group['id'];

    echo '<div class="log info">📈 ' . $nom . ' - Évaluation...</div>';

    $tables = execReportGlobal(TEMPLATE_EVALUATION, $group_id, $sid);

    if (!$tables) {
        echo '<div class="log">⚠️ ' . $nom . ' - Pas de données évaluation</div>';
        return 0;
    }

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

    echo '<div class="log success">✅ ' . $nom . ' - Évaluation: ' . $count . ' enregistrements</div>';
    return $count;
}
