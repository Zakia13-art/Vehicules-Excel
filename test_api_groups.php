<?php
/**
 * TEST API - Verifier quels groupes ont des donnees
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

$tab_group = array(
    'BOUTCHRAFINE' => 19022033,
    'SOMATRIN' => 19596491,
    'MARATRANS' => 19631505,
    'G.T.C' => 19590737
);

$date_hier = date('Y-m-d', strtotime('yesterday'));
$date_from = strtotime("$date_hier 00:00:00");
$date_to = strtotime("$date_hier 23:59:59");

echo "======================================\n";
echo "TEST API - Quels groupes ont des donnees ?\n";
echo "Date: $date_hier\n";
echo "======================================\n\n";

$sid = sid();
echo "Session: " . substr($sid, 0, 20) . "...\n\n";

foreach ($tab_group as $nom => $group_id) {
    echo "Testing $nom (ID: $group_id)...\n";

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
        echo "  Resultat: $total_rows trajets trouves\n";
    } elseif (isset($v_det['error'])) {
        echo "  Erreur API: {$v_det['error']}\n";
    } else {
        echo "  Resultat: 0 trajets (pas de donnees)\n";
    }
    echo "\n";
}

echo "======================================\n";
echo "CONCLUSION:\n";
echo "Si un groupe affiche '0 trajets', c'est que\n";
echo "Wialon n'a pas de donnees pour ce groupe.\n";
echo "======================================\n";
?>
