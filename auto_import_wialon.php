<?php
/**
 * ========================================
 * AUTO IMPORT WIALON - AVEC TIMESTAMP
 * ========================================
 */

set_time_limit(600);
require_once "db.php";

function logAuto($message, $type = 'INFO') {
    @mkdir('logs', 0755, true);
    $file = "logs/auto_import.log";
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

function execRep($group, $sid, $from1 = 0, $to1 = 0) {
    $base_time = 1575503999;
    if ($from1 > 0 || $to1 > 0) {
        $from = ($base_time - ($from1 * 86400));
        $to = ($base_time - ($to1 * 86400));
    } else {
        $to = time();
        $from = $to - (7 * 86400);
    }
    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":1,"reportObjectId":' . $group . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;
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
    logAuto("Erreur execRep", 'ERROR');
    return null;
}

function selectRes($groupe_nom, $groupe_id, $tabindex, $to, $sid, $execution_time) {
    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":' . $tabindex . ',"config":{"type":"range","data":{"from":0,"to":' . $to . ',"level":2}}}&sid=' . $sid;
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
        if (is_array($v_det) && isset($v_det[0]['r'])) {
            $count = 0;
            foreach ($v_det as $row) {
                if (isset($row['r'])) {
                    foreach ($row['r'] as $tabline) {
                        $vehicule = str_replace([" ", "-", "/"], "", $tabline['c']['1'] ?? '');
                        $parcour = $tabline['c']['2'] ?? '';
                        $depart = $tabline['c']['3'] ?? '';
                        $vers = $tabline['c']['4'] ?? '';
                        $debut = $tabline['t1'] ?? 0;
                        $fin = $tabline['t2'] ?? 0;
                        $penalit = $tabline['c']['8'] ?? 0;
                        $km = (float)str_replace("km", "", $tabline['c']['9'] ?? 0);
                        if (set_trajet($groupe_id, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, 'CD000')) {
                            $count++;
                        }
                    }
                }
            }
            return $count;
        }
    }
    logAuto("Erreur selectRes pour $groupe_nom", 'ERROR');
    return 0;
}

$groupe_to_transporteur = array(
    'BOUTCHRAFINE' => 1, 'SOMATRIN' => 2, 'MARATRANS' => 3,
    'G.T.C' => 4, 'DOUKALI' => 5, 'COTRAMAB' => 6,
    'CORYAD' => 7, 'CONSMETA' => 8, 'CHOUROUK' => 9,
    'CARRE' => 10, 'STB' => 11, 'FASTTRANS' => 12
);

$tab_group = array(
    'BOUTCHRAFINE' => 19022033, 'SOMATRIN' => 19596491, 'MARATRANS' => 19631505,
    'G.T.C' => 19590737, 'DOUKALI' => 19585587, 'COTRAMAB' => 19585601,
    'CORYAD' => 19585581, 'CONSMETA' => 19629962, 'CHOUROUK' => 19630023,
    'CARRE' => 19643391, 'STB' => 19585942, 'FASTTRANS' => 19635796
);

$execution_start = date('Y-m-d H:i:s');
logAuto("========================================");
logAuto("DEBUT IMPORT AUTOMATIQUE - $execution_start");
logAuto("========================================");

$sid = sid();
if (!$sid) {
    logAuto("ERREUR: Impossible de creer session", 'ERROR');
    exit(1);
}

logAuto("Session cree: " . substr($sid, 0, 20) . "...");

$total_imported = 0;
$stats = array();

foreach ($tab_group as $nom => $id) {
    $transporteur_id = $groupe_to_transporteur[$nom];
    logAuto("Processing: $nom (ID: $id)");
    cleanRepport($sid);
    sleep(1);
    $report_index = execRep($id, $sid, 7, 0);
    if ($report_index === null) {
        logAuto("Pas de donnees pour $nom", 'WARNING');
        $stats[$nom] = 0;
        continue;
    }
    $count = 0;
    foreach ($report_index as $key => $value) {
        $imported = selectRes($nom, $transporteur_id, $key, $value, $sid, $execution_start);
        $count += $imported;
    }
    $total_imported += $count;
    $stats[$nom] = $count;
    logAuto("$nom: $count trajets importes");
}

$execution_end = date('Y-m-d H:i:s');
logAuto("========================================");
logAuto("FIN IMPORT - Total: $total_imported trajets");
logAuto("Debut: $execution_start");
logAuto("Fin: $execution_end");
logAuto("========================================");

echo "\n=== RESUME ===\n";
foreach ($stats as $nom => $count) {
    echo "$nom: $count trajets\n";
}
echo "\nTOTAL: $total_imported trajets\n";
echo "Execution: $execution_start -> $execution_end\n";
?>
