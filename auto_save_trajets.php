<?php
set_time_limit(1200);
require_once __DIR__ . "/db.php";

function logAuto($message, $type = 'INFO') {
    @mkdir(__DIR__ . '/logs', 0755, true);
    $file = __DIR__ . "/logs/auto_save_trajets.log";
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

function execRep($group, $sid, $from1, $to1) {
    $base_time = 1575503999;
    $from = ($base_time - ($from1 * 86400));
    $to = ($base_time - ($to1 * 86400));
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
    return null;
}

function selectRes($groupe_id, $groupe_nom, $tabindex, $to, $sid, $execution_time) {
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
    return 0;
}

$groupe_to_transporteur = array(
    'BOUTCHRAFINE' => 1, 'SOMATRIN' => 2, 'MARATRANS' => 3,
    'G.T.C' => 4, 'DOUKALI' => 5, 'COTRAMAB' => 6,
    'CORYAD' => 7, 'CONSMETA' => 8, 'CHOUROUK' => 9,
    'CARRE' => 10, 'STB' => 11, 'FASTTRANS' => 12
);

$tab_group = array(
    'BOUTCHRAFINE' => 12173650, 'SOMATRIN' => 30071668, 'MARATRANS' => 19631505,
    'G.T.C' => 19590737, 'DOUKALI' => 19585587, 'COTRAMAB' => 19585601,
    'CORYAD' => 19585581, 'CONSMETA' => 19629962, 'CHOUROUK' => 19630023,
    'CARRE' => 29440837, 'STB' => 26577266, 'FASTTRANS' => 19635796
);

$execution_start = date('Y-m-d H:i:s');
logAuto("========================================");
logAuto("AUTO SAVE TRAJETS - 7 DERNIERS JOURS");
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

// Importer jour par jour (comme Desktop/codes mais avec dates correctes)
for ($day = 0; $day <= 7; $day++) {
    $date_target = date('Y-m-d', strtotime("-$day days"));
    $date_from = strtotime("$date_target 00:00:00");
    $date_to = strtotime("$date_target 23:59:59");

    logAuto("Periode: $date_target (from: " . date('Y-m-d H:i:s', $date_from) . " to: " . date('Y-m-d H:i:s', $date_to) . ")");

    foreach ($tab_group as $nom => $groupe_id) {
        $transporteur_id = $groupe_to_transporteur[$nom];

        cleanRepport($sid);
        sleep(1);

        // Appel execRep avec timestamps directs
        $curl = curl_init();
        $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":1,"reportObjectId":' . $groupe_id . ',"reportObjectSecId":0,"interval":{"from":' . $date_from . ',"to":' . $date_to . ',"flags":0}}&sid=' . $sid;
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
                for ($t = 0; $t < $nbrtab; $t++) {
                    $rows = $v_det['reportResult']['tables'][$t]['rows'];
                    if ($rows > 0) {
                        $result_rows = selectRes($transporteur_id, $nom, $t, $rows, $sid, $execution_start);
                        if (!isset($stats[$nom])) $stats[$nom] = 0;
                        $stats[$nom] += $result_rows;
                        $total_imported += $result_rows;
                    }
                }
            }
        }
    }
}

$execution_end = date('Y-m-d H:i:s');
logAuto("========================================");
logAuto("FIN SAVE - Total: $total_imported trajets");
logAuto("Execution: $execution_start -> $execution_end");
logAuto("========================================");

echo "\n=== RESUME ===\n";
foreach ($stats as $nom => $count) {
    echo "$nom: $count trajets\n";
}
echo "\nTOTAL: $total_imported trajets\n";
echo "Execution: $execution_start -> $execution_end\n";
?>
