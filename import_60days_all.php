<?php
/**
 * Import 60 jours - TOUS les transporteurs
 */
set_time_limit(1200);
require_once __DIR__ . "/db.php";

function sid() {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB\"}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $v_det = json_decode($response, true);
    return $v_det['eid'] ?? null;
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

function execRep($group, $sid, $from1, $to1, $templateId = 1) {
    $to = time() - ($to1 * 86400);
    $from = time() - ($from1 * 86400);

    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":' . $templateId . ',"reportObjectId":' . $group . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

    curl_setopt_array($curl, array(
        CURLOPT_URL => $Url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $v_det = json_decode($response, true);
    if (isset($v_det['reportResult']['tables'])) {
        $nbrtab = sizeof($v_det['reportResult']['tables']);
        if ($nbrtab == 0) return null;
        $tabline = array();
        for ($i = 0; $i < $nbrtab; $i++) {
            $tabline[$i] = $v_det['reportResult']['tables'][$i]['rows'];
        }
        return $tabline;
    }
    return null;
}

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

function insertGlobalKilometrage($transporteur_id, $transporteur_nom, $vehicule, $debut, $fin, $duree, $km) {
    global $db;
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
    global $db;
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
    global $db;
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

$db = Cnx();

$groups = array(
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

echo "======================================\n";
echo "IMPORT 60 JOURS - TOUS LES TRANSPORTEURS\n";
echo "======================================\n\n";

$sid = sid();
if (!$sid) {
    echo "Erreur session\n";
    exit;
}
echo "Session OK\n\n";

$from1 = 61; // 60 jours
$to1 = 1;

$total_km = 0;
$total_eval = 0;
$total_infra = 0;

foreach ($groups as $nom => $groupe) {
    echo "$nom... ";

    $transporteur_id = $groupe['transporteur_id'];
    $group_id = $groupe['id'];

    $count_km = 0;
    $count_eval = 0;
    $count_infra = 0;

    // 1. KILOMETRAGE (Template ID 529)
    $tables_km = execRep($group_id, $sid, $from1, $to1, 529);
    if ($tables_km) {
        foreach ($tables_km as $tableIndex => $rowCount) {
            if ($rowCount > 0) {
                $result = selectResultRows($tableIndex, $rowCount, $sid);
                if (isset($result[0]['r'])) {
                    foreach ($result as $row) {
                        if (isset($row['r'])) {
                            foreach ($row['r'] as $data) {
                                $vehicule = $data['c']['1'] ?? '';
                                $debut = $data['t1'] ?? 0;
                                $fin = $data['t2'] ?? 0;
                                $km = $data['c']['9'] ?? 0;

                                $diff_seconds = $fin - $debut;
                                $hours = floor($diff_seconds / 3600);
                                $minutes = floor(($diff_seconds % 3600) / 60);
                                $duree = "$hours h $minutes min";

                                if (insertGlobalKilometrage($transporteur_id, $nom, $vehicule, $debut, $fin, $duree, $km)) {
                                    $count_km++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    cleanRepport($sid);

    // 2. EVALUATION (Template ID 21146)
    $tables_eval = execRep($group_id, $sid, $from1, $to1, 21146);
    if ($tables_eval) {
        foreach ($tables_eval as $tableIndex => $rowCount) {
            if ($rowCount > 0) {
                $result = selectResultRows($tableIndex, $rowCount, $sid);
                if (isset($result[0]['r'])) {
                    foreach ($result as $row) {
                        if (isset($row['r'])) {
                            foreach ($row['r'] as $data) {
                                $vehicule = $data['c']['1'] ?? '';
                                $depart = $data['c']['3'] ?? '';
                                $debut = $data['t1'] ?? 0;
                                $fin = $data['t2'] ?? 0;
                                $penalites = (int)($data['c']['8'] ?? 0);
                                $emplacement = $depart ?: 'Inconnu';
                                $evaluation = $penalites > 0 ? 'Non conforme' : 'Conforme';

                                if (insertGlobalEvaluation($transporteur_id, $nom, $vehicule, $debut, $fin, $emplacement, $penalites, $evaluation)) {
                                    $count_eval++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    cleanRepport($sid);

    // 3. INFRINGEMENTS (Template ID 36793)
    $tables_infra = execRep($group_id, $sid, $from1, $to1, 36793);
    if ($tables_infra) {
        foreach ($tables_infra as $tableIndex => $rowCount) {
            if ($rowCount > 0) {
                $result = selectResultRows($tableIndex, $rowCount, $sid);
                if (isset($result[0]['r'])) {
                    foreach ($result as $row) {
                        if (isset($row['r'])) {
                            foreach ($row['r'] as $data) {
                                $vehicule = $data['c']['1'] ?? '';
                                $depart = $data['c']['3'] ?? '';
                                $debut = $data['t1'] ?? 0;
                                $fin = $data['t2'] ?? 0;
                                $penalites = (int)($data['c']['8'] ?? 0);
                                $emplacement = $depart ?: 'Inconnu';

                                if ($penalites > 0) {
                                    $infraction = "$penalites pénalité(s)";
                                    if (insertGlobalInfraction($transporteur_id, $nom, $vehicule, $debut, $fin, $emplacement, $infraction)) {
                                        $count_infra++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    echo "KM: $count_km | Eval: $count_eval | Infra: $count_infra\n";

    $total_km += $count_km;
    $total_eval += $count_eval;
    $total_infra += $count_infra;
}

echo "\n======================================\n";
echo "TOTAL: KM=$total_km | Eval=$total_eval | Infra=$total_infra\n";
echo "======================================\n";
?>
