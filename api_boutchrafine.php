<?php
/**
 * ========================================
 * API BOUTCHRAFINE - WIALON REPORTS
 * ========================================
 * Fetch 3 types de reports:
 * 1. Kilométrage (Template ID: 4)
 * 2. Infractions (Template ID: 2)
 * 3. Évaluation (Template ID: 7)
 *
 * Group ID BOUTCHRAFINE: 19022033
 * Resource ID: 19907460
 */

require_once "lesgets.php";

set_time_limit(600);

// ========================================
// CONFIGURATION
// ========================================
define('BOUTCHRAFINE_GROUP_ID', 19022033);
define('REPORT_RESOURCE_ID', 19907460);

// Template IDs
define('TEMPLATE_KILOMETRAGE', 4);     // R.KILOMETRAGE
define('TEMPLATE_INFRACTIONS', 2);     // R.EXCES DE VITESSE
define('TEMPLATE_EVALUATION', 7);      // Éco-conduite

// ========================================
// FUNCTIONS
// ========================================

/**
 * execReportBoutchrafine - Exécuter un rapport Wialon
 */
function execReportBoutchrafine($templateId, $sid, $from = 0, $to = 0) {
    $base_time = 1575503999;

    if ($from > 0 || $to > 0) {
        $from = ($base_time - ($from * 86400));
        $to = ($base_time - ($to * 86400));
    } else {
        $to = time();
        $from = $to - (7 * 86400); // 7 derniers jours
    }

    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":' . REPORT_RESOURCE_ID . ',"reportTemplateId":' . $templateId . ',"reportObjectId":' . BOUTCHRAFINE_GROUP_ID . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

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
// INSERT FUNCTIONS
// ========================================

/**
 * insertKilometrage - Insérer données kilométrage
 */
function insertKilometrage($vehicule, $debut, $fin, $duree, $km) {
    $db = Cnx();

    // Nettoyer véhicule
    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));

    // Convertir timestamps
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');

    // Nettoyer KM
    $km = (float) preg_replace('/[^0-9.]/', '', $km);

    // Vérifier doublon
    $check = $db->prepare("SELECT COUNT(*) FROM boutchrafine_kilometrage WHERE vehicule=? AND debut=? AND fin=?");
    $check->execute([$vehicule, $debut_dt, $fin_dt]);

    if ($check->fetchColumn() > 0) {
        return false; // Doublon
    }

    // Insérer
    $stmt = $db->prepare("INSERT INTO boutchrafine_kilometrage (vehicule, debut, fin, duree, kilometrage) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$vehicule, $debut_dt, $fin_dt, $duree, $km]);
}

/**
 * insertInfraction - Insérer données infractions
 */
function insertInfraction($vehicule, $debut, $fin, $emplacement, $infraction) {
    $db = Cnx();

    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');

    // Nettoyer infraction (si vide = "-----")
    if ($infraction === '-----' || trim($infraction) === '') {
        return false; // Pas d'infraction
    }

    $check = $db->prepare("SELECT COUNT(*) FROM boutchrafine_infractions WHERE vehicule=? AND debut=? AND fin=? AND infraction=?");
    $check->execute([$vehicule, $debut_dt, $fin_dt, $infraction]);

    if ($check->fetchColumn() > 0) {
        return false;
    }

    $stmt = $db->prepare("INSERT INTO boutchrafine_infractions (vehicule, debut, fin, emplacement, infraction) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$vehicule, $debut_dt, $fin_dt, $emplacement, $infraction]);
}

/**
 * insertEvaluation - Insérer données évaluation
 */
function insertEvaluation($vehicule, $debut, $fin, $emplacement, $penalites, $evaluation) {
    $db = Cnx();

    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : date('Y-m-d H:i:s');
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');

    // Nettoyer pénalités
    $penalites = (float) preg_replace('/[^0-9.]/', '', $penalites);

    $check = $db->prepare("SELECT COUNT(*) FROM boutchrafine_evaluation WHERE vehicule=? AND debut=? AND fin=?");
    $check->execute([$vehicule, $debut_dt, $fin_dt]);

    if ($check->fetchColumn() > 0) {
        return false;
    }

    $stmt = $db->prepare("INSERT INTO boutchrafine_evaluation (vehicule, debut, fin, emplacement, penalites, evaluation) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$vehicule, $debut_dt, $fin_dt, $emplacement, $penalites, $evaluation]);
}

// ========================================
// PROCESS FUNCTIONS
// ========================================

/**
 * processKilometrage - Traiter rapport kilométrage
 */
function processKilometrage($sid) {
    echo '<div class="log info">📊 Traitement KILOMETRAGE...</div>';

    $tables = execReportBoutchrafine(TEMPLATE_KILOMETRAGE, $sid);

    if (!$tables) {
        echo '<div class="log">⚠️ Pas de données kilométrage</div>';
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
                            $vehicule = $data['c']['0'] ?? ''; // Regroupement
                            $debut = $data['t1'] ?? 0;
                            $fin = $data['t2'] ?? 0;
                            $duree = $data['c']['1'] ?? '';
                            $km = $data['c']['2'] ?? 0;

                            if (insertKilometrage($vehicule, $debut, $fin, $duree, $km)) {
                                $count++;
                            }
                        }
                    }
                }
            }
        }
    }

    echo '<div class="log success">✅ KILOMETRAGE: ' . $count . ' enregistrements</div>';
    return $count;
}

/**
 * processInfractions - Traiter rapport infractions
 */
function processInfractions($sid) {
    echo '<div class="log info">⚠️ Traitement INFRACTIONS...</div>';

    $tables = execReportBoutchrafine(TEMPLATE_INFRACTIONS, $sid);

    if (!$tables) {
        echo '<div class="log">⚠️ Pas de données infractions</div>';
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
                            $vehicule = $data['c']['0'] ?? '';
                            $debut = $data['t1'] ?? 0;
                            $fin = $data['t2'] ?? 0;
                            $emplacement = $data['c']['1'] ?? '';
                            $infraction = $data['c']['2'] ?? '';

                            if (insertInfraction($vehicule, $debut, $fin, $emplacement, $infraction)) {
                                $count++;
                            }
                        }
                    }
                }
            }
        }
    }

    echo '<div class="log success">✅ INFRACTIONS: ' . $count . ' enregistrements</div>';
    return $count;
}

/**
 * processEvaluation - Traiter rapport évaluation
 */
function processEvaluation($sid) {
    echo '<div class="log info">📈 Traitement ÉVALUATION...</div>';

    $tables = execReportBoutchrafine(TEMPLATE_EVALUATION, $sid);

    if (!$tables) {
        echo '<div class="log">⚠️ Pas de données évaluation</div>';
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
                            $vehicule = $data['c']['0'] ?? '';
                            $debut = $data['t1'] ?? 0;
                            $fin = $data['t2'] ?? 0;
                            $emplacement = $data['c']['1'] ?? '';
                            $penalites = $data['c']['2'] ?? 0;
                            $evaluation = $data['c']['3'] ?? '';

                            if (insertEvaluation($vehicule, $debut, $fin, $emplacement, $penalites, $evaluation)) {
                                $count++;
                            }
                        }
                    }
                }
            }
        }
    }

    echo '<div class="log success">✅ ÉVALUATION: ' . $count . ' enregistrements</div>';
    return $count;
}
