<?php
/**
 * ========================================
 * AUTO IMPORT WIALON - AVEC TIMESTAMP
 * ========================================
 */

set_time_limit(600);
require_once __DIR__ . "/db.php";

function logAuto($message, $type = 'INFO') {
    @mkdir(__DIR__ . '/logs', 0755, true);
    $file = __DIR__ . "/logs/auto_import.log";
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

function execRep($group, $sid, $from_ts = 0, $to_ts = 0) {
    // Si pas de timestamps fournis, utiliser 7 DERNIERS JOURS
    if ($from_ts == 0 || $to_ts == 0) {
        // Il y a 7 jours 00:00:00
        $from_ts = strtotime('-7 days 00:00:00');
        // Hier 23:59:59
        $to_ts = strtotime('yesterday 23:59:59');
    }

    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":1,"reportObjectId":' . $group . ',"reportObjectSecId":0,"interval":{"from":' . $from_ts . ',"to":' . $to_ts . ',"flags":0}}&sid=' . $sid;
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
    'STE STB' => 26577266, 'SOTRAFOREST' => 26623545, 'SOMATRIN' => 30071668,
    'MARATRANS' => 19631505, 'GTC CIMAT' => 30085013, 'FLEXILOG' => 23607333,
    'FIRST LOGISTIQUE' => 23297975, 'FAYSSAL METAL' => 30066387, 'FAST TRANS' => 19635796,
    'COTRAMAB' => 19585601, 'CORYAD' => 19585581, 'CIMATRAK' => 30105885,
    'CHOUROUK' => 15125142, 'BOUTCHRAFIN_CIMAT' => 19022033, 'ANFAL' => 27720630
);

$execution_start = date('Y-m-d H:i:s');
$periode_debut = date('Y-m-d H:i:s', strtotime('-7 days 00:00:00'));
$periode_fin = date('Y-m-d H:i:s', strtotime('yesterday 23:59:59'));
$periode_text = date('d/m/Y', strtotime('-7 days')) . ' -> ' . date('d/m/Y', strtotime('yesterday'));

logAuto("========================================");
logAuto("IMPORT AUTOMATIQUE - 7 DERNIERS JOURS");
logAuto("Periode: $periode_text");
logAuto("Du: $periode_debut");
logAuto("Au: $periode_fin");
logAuto("Execution: $execution_start");
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
    $report_index = execRep($id, $sid); // Utilise 7 derniers jours par defaut
    if ($report_index === null) {
        logAuto("Pas de donnees pour $nom (7 derniers jours)", 'WARNING');
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
logAuto("Periode: $periode_text (7 derniers jours)");
logAuto("Execution: $execution_start -> $execution_end");
logAuto("========================================");

echo "\n=== RESUME - IMPORT 7 DERNIERS JOURS ===\n";
echo "Periode: $periode_text\n\n";
foreach ($stats as $nom => $count) {
    echo "$nom: $count trajets\n";
}
echo "\nTOTAL: $total_imported trajets";
echo "\nPeriode: $periode_text\n";
echo "Execution: $execution_start -> $execution_end\n";
?>
