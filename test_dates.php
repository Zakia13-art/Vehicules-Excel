<?php
/**
 * TEST DATES - Chercher donnees dans Wialon
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
    'BOUTCHRAFINE' => 29666936,
    'SOMATRIN' => 30071668
);

echo "======================================\n";
echo "TEST DATES - Ou sont les donnees ?\n";
echo "======================================\n\n";

$sid = sid();
echo "Session: " . substr($sid, 0, 20) . "...\n\n";

// Tester differentes dates
$dates = array(
    'Auj (30/03)' => date('Y-m-d'),
    'Hier (29/03)' => date('Y-m-d', strtotime('yesterday')),
    '28/03' => date('Y-m-d', strtotime('-2 days')),
    '27/03' => date('Y-m-d', strtotime('-3 days')),
    '26/03' => date('Y-m-d', strtotime('-4 days')),
    '25/03' => date('Y-m-d', strtotime('-5 days')),
    '20/03' => date('Y-m-d', strtotime('-10 days')),
);

foreach ($dates as $label => $date) {
    $date_from = strtotime("$date 00:00:00");
    $date_to = strtotime("$date 23:59:59");

    echo "=== $label ($date) ===\n";

    foreach ($tab_group as $nom => $group_id) {
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
            echo "  $nom: $total_rows trajets ✓\n";
        } elseif (isset($v_det['error'])) {
            echo "  $nom: Erreur API\n";
        } else {
            echo "  $nom: 0 trajets\n";
        }
    }
    echo "\n";
}

echo "======================================\n";
echo "CONCLUSION:\n";
echo "Si 0 trajets pour toutes dates =\n";
echo "Soit pas de donnees Wialon,\n";
echo "Soit periode inactive (weekend)\n";
echo "======================================\n";
?>
