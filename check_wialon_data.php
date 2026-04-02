<?php
/**
 * Vérifier quels groupes ont des données dans Wialon
 */
set_time_limit(600);

function sid() {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"b6db68331b4b6ed14b61dbfeeaad9a0605EA995CF621CE53D5C01A0A29C9FCFB6B2902A8\"}",
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

function execRep($group, $sid, $from1, $to1) {
    $to = time() - ($to1 * 86400);
    $from = time() - ($from1 * 86400);

    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":22861605,"reportTemplateId":1,"reportObjectId":' . $group . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

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

echo "======================================\n";
echo "VERIFICATION DES GROUPES DANS WIALON\n";
echo "======================================\n\n";

$sid = sid();
if (!$sid) {
    echo "Erreur session\n";
    exit;
}

echo "Session OK\n\n";

$groups = array(
    'BOUTCHRAFINE' => 12173650,
    'SOMATRIN' => 30071668,
    'MARATRANS' => 19631505,
    'G.T.C' => 30085013,
    'DOUKALI' => 19585587,
    'COTRAMAB' => 19585601,
    'CORYAD' => 19585581,
    'CONSMETA' => 19629962,
    'CHOUROUK' => 19630023,
    'CARRE' => 29440837,
    'STB' => 26577266,
    'FASTTRANS' => 19635796
);

$from1 = 8; // 7 derniers jours
$to1 = 1;

echo "Vérification des 7 derniers jours:\n\n";

foreach ($groups as $nom => $id) {
    cleanRepport($sid);
    sleep(1);

    $tables = execRep($id, $sid, $from1, $to1);

    if ($tables === null) {
        echo "$nom (ID: $id) → ❌ ERREUR API ou PAS DE DONNÉES\n";
        continue;
    }

    $total_rows = 0;
    foreach ($tables as $rows) {
        $total_rows += $rows;
    }

    if ($total_rows > 0) {
        echo "$nom (ID: $id) → ✅ $total_rows lignes de données\n";
    } else {
        echo "$nom (ID: $id) → ⚠️ 0 lignes (pas d'activité)\n";
    }
}

echo "\n======================================\n";
echo "GROUPES AVEC DONNÉES: = ceux qui ont des lignes\n";
echo "GROUPES SANS DONNÉES: = pas d'activité GPS\n";
echo "======================================\n";
?>
