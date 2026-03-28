<?php
/**
 * AUTO SPLIT QUOTIDIEN - Importe seulement HIER
 * Execution automatique tous les jours à 01:00
 */

set_time_limit(600);
require_once __DIR__ . "/db.php";

function logAuto($message, $type = 'INFO') {
    @mkdir(__DIR__ . '/logs', 0755, true);
    $file = __DIR__ . "/logs/auto_split_daily.log";
    $timestamp = date('d-m-Y H:i:s');
    $msg = "[$timestamp] [$type] $message\n";
    file_put_contents($file, $msg, FILE_APPEND);
    echo $msg;
}

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
    $db = Cnx();
    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : $debut;
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : $fin;
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
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : $debut;
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : $fin;

    if ($infraction === '-----' || trim($infraction) === '' || $infraction === '0' || $infraction === 0) return false;

    $check = $db->prepare("SELECT COUNT(*) FROM global_infractions WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=? AND infraction=?");
    $check->execute([$transporteur_id, $vehicule, $debut_dt, $fin_dt, $infraction]);
    if ($check->fetchColumn() > 0) return false;

    $stmt = $db->prepare("INSERT INTO global_infractions (transporteur_id, transporteur_nom, vehicule, debut, fin, emplacement, infraction) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$transporteur_id, $transporteur_nom, $vehicule, $debut_dt, $fin_dt, $emplacement, $infraction]);
}

function insertGlobalEvaluation($transporteur_id, $transporteur_nom, $vehicule, $debut, $fin, $emplacement, $penalites, $evaluation) {
    $db = Cnx();
    $vehicule = trim(str_replace(['-', '/', ' '], '', $vehicule));
    $debut_dt = is_numeric($debut) && $debut > 1000000000 ? date('Y-m-d H:i:s', $debut) : $debut;
    $fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : $fin;
    $penalites = (float) preg_replace('/[^0-9.]/', '', $penalites);

    $check = $db->prepare("SELECT COUNT(*) FROM global_evaluation WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=?");
    $check->execute([$transporteur_id, $vehicule, $debut_dt, $fin_dt]);
    if ($check->fetchColumn() > 0) return false;

    $stmt = $db->prepare("INSERT INTO global_evaluation (transporteur_id, transporteur_nom, vehicule, debut, fin, emplacement, penalites, evaluation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$transporteur_id, $transporteur_nom, $vehicule, $debut_dt, $fin_dt, $emplacement, $penalites, $evaluation]);
}

$tab_group = array(
    'BOUTCHRAFINE' => array('id' => 19022033, 'transporteur_id' => 1),
    'SOMATRIN' => array('id' => 19596491, 'transporteur_id' => 2),
    'MARATRANS' => array('id' => 19631505, 'transporteur_id' => 3),
    'G.T.C' => array('id' => 19590737, 'transporteur_id' => 4),
    'DOUKALI' => array('id' => 19585587, 'transporteur_id' => 5),
    'COTRAMAB' => array('id' => 19585601, 'transporteur_id' => 6),
    'CORYAD' => array('id' => 19585581, 'transporteur_id' => 7),
    'CONSMETA' => array('id' => 19629962, 'transporteur_id' => 8),
    'CHOUROUK' => array('id' => 19630023, 'transporteur_id' => 9),
    'CARRE' => array('id' => 19643391, 'transporteur_id' => 10),
    'STB' => array('id' => 19585942, 'transporteur_id' => 11),
    'FASTTRANS' => array('id' => 19635796, 'transporteur_id' => 12)
);

$execution_start = date('Y-m-d H:i:s');
$date_hier = date('Y-m-d', strtotime('yesterday'));
$date_from = strtotime("$date_hier 00:00:00");
$date_to = strtotime("$date_hier 23:59:59");

logAuto("========================================");
logAuto("AUTO SPLIT QUOTIDIEN");
logAuto("Import des donnees d'HIER: $date_hier");
logAuto("Execution: $execution_start");
logAuto("========================================");

$sid = sid();
if (!$sid) {
    logAuto("ERREUR: Impossible de creer session", 'ERROR');
    exit(1);
}

logAuto("Session cree: " . substr($sid, 0, 20) . "...");

$total_km = 0;
$total_infra = 0;
$total_eval = 0;
$stats = array();

// Un seul jour : HIER
logAuto("Periode: $date_hier");

foreach ($tab_group as $nom => $groupe) {
    $transporteur_id = $groupe['transporteur_id'];
    $group_id = $groupe['id'];

    cleanRepport($sid);
    sleep(1);

    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":1,"reportObjectId":' . $group_id . ',"reportObjectSecId":0,"interval":{"from":' . $date_from . ',"to":' . $date_to . ',"flags":0}}&sid=' . $sid;
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
        for ($t = 0; $t < $nbrtab; $t++) {
            $rows = $v_det['reportResult']['tables'][$t]['rows'];
            if ($rows > 0) {
                $result = selectResultRows($t, $rows, $sid);
                if (isset($result[0]['r'])) {
                    foreach ($result as $row) {
                        if (isset($row['r'])) {
                            foreach ($row['r'] as $data) {
                                $vehicule = $data['c']['1'] ?? '';
                                $parcour = $data['c']['2'] ?? '';
                                $depart = $data['c']['3'] ?? '';
                                $vers = $data['c']['4'] ?? '';
                                $debut = $data['t1'] ?? 0;
                                $fin = $data['t2'] ?? 0;

                                $duree = '';
                                if ($debut && $fin) {
                                    $diff = $fin - $debut;
                                    $heures = floor($diff / 3600);
                                    $minutes = floor(($diff % 3600) / 60);
                                    $duree = sprintf('%02d:%02d', $heures, $minutes);
                                }

                                $penalit = $data['c']['8'] ?? 0;
                                $km = (float) str_replace("km", "", $data['c']['9'] ?? 0);

                                $emplacement = $depart . ' -> ' . $vers;

                                if ($km > 0) {
                                    if (insertGlobalKilometrage($transporteur_id, $nom, $vehicule, $debut, $fin, $duree, $km)) {
                                        $total_km++;
                                        if (!isset($stats[$nom])) $stats[$nom] = array('km' => 0, 'infra' => 0, 'eval' => 0);
                                        $stats[$nom]['km']++;
                                    }
                                }

                                if ($penalit != 0 && $penalit != '-----' && $penalit != '') {
                                    if (insertGlobalInfraction($transporteur_id, $nom, $vehicule, $debut, $fin, $emplacement, $penalit)) {
                                        $total_infra++;
                                        $stats[$nom]['infra']++;
                                    }
                                }

                                $evaluation = 'BON';
                                if ($penalit > 0 && $penalit != '-----') {
                                    $evaluation = 'MAUVAIS';
                                } elseif ($penalit == 0 || $penalit == '-----') {
                                    $evaluation = 'BON';
                                }

                                if (insertGlobalEvaluation($transporteur_id, $nom, $vehicule, $debut, $fin, $emplacement, $penalit, $evaluation)) {
                                    $total_eval++;
                                    $stats[$nom]['eval']++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

$execution_end = date('Y-m-d H:i:s');
logAuto("========================================");
logAuto("FIN SPLIT - HIER: $date_hier");
logAuto("KM: $total_km | Infra: $total_infra | Eval: $total_eval");
logAuto("Execution: $execution_start -> $execution_end");
logAuto("========================================");

echo "\n=== RESUME - HIER $date_hier ===\n";
foreach ($stats as $nom => $s) {
    echo "$nom: KM={$s['km']}, Infra={$s['infra']}, Eval={$s['eval']}\n";
}
echo "\nTOTAL: KM=$total_km, Infra=$total_infra, Eval=$total_eval\n";
echo "Execution: $execution_start -> $execution_end\n";
?>
