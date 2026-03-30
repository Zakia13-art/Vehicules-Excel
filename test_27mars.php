<?php
/**
 * TEST 27 MARS - Date avec donnees
 */

require_once "db.php";

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

echo "======================================\n";
echo "TEST 27 MARS 2026 - Date avec donnees\n";
echo "======================================\n\n";

$sid = sid();
echo "Session: " . substr($sid, 0, 20) . "...\n\n";

$date = '2026-03-27';
$date_from = strtotime("$date 00:00:00");
$date_to = strtotime("$date 23:59:59");

echo "Date: $date\n\n";

$groups = array(
    'BOUTCHRAFINE' => 19022033,
    'MARATRANS' => 19631505,
);

foreach ($groups as $nom => $group_id) {
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
        $total_rows = 0;
        foreach ($v_det['reportResult']['tables'] as $t) {
            $total_rows += $t['rows'];
        }
        echo "$nom: $total_rows trajets";
        if ($total_rows > 0) {
            echo " ✓✓✓ DONNEES EXISTENT!";
        }
        echo "\n";
    } elseif (isset($v_det['error'])) {
        echo "$nom: Erreur API - {$v_det['error']}\n";
    } else {
        echo "$nom: 0 trajets\n";
    }
}

echo "\n======================================\n";
echo "Si donnees existent -> IDs CORRECTS!\n";
echo "Tout est OK, execution possible.\n";
echo "======================================\n";
?>
