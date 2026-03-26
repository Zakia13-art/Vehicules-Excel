<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "lesgets.php";

echo "<h1>🎯 TEST RAPIDE - BONNE RESOURCE</h1>";
echo "<pre>";

$sid = sid();
if (!$sid) {
    echo "❌ Erreur connexion\n";
    exit;
}

echo "✅ Connecté\n\n";

$test_group = 19631505; // MARATRANS (confirme existe)
$to = time();
$from = $to - (7 * 86400);

// Resources à tester (api.txt + communs)
$resources_to_test = [
    19907460 => 'webservice (actuel)',
    29547077 => '2K2Z',
    29741168 => '2RD SERVICES',
    11487067 => '3 S TRANSPORT',
    29270435 => 'ABDA LUXE CAR',
    25058821 => 'AELYAZID CIMENT',
    27983236 => 'ACL TRAVAUX',
    29325396 => '3Z TFI',
    14664552 => 'a.houda',
    30021687 => 'AKRITE USB',
    28442519 => 'AJDIR GAZ',
    26667243 => 'AKHI TRANS',
    23208115 => 'AFTO TRANS',
    29291996 => 'AKHDIR TRAVAUX',
];

echo "=== TESTER CHAQUE RESOURCE ===\n\n";

$working_resource = null;

foreach ($resources_to_test as $res_id => $res_name) {
    echo "Testing Resource ID: $res_id ($res_name)... ";

    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":' . $res_id . ',"reportTemplateId":1,"reportObjectId":' . $test_group . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
    ));

    $resp = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "❌ CURL ERROR\n";
    } else {
        $result = json_decode($resp, true);

        if (isset($result['error'])) {
            echo "⚠️ API Error\n";
        } elseif (isset($result['reportResult']['tables'])) {
            $num = count($result['reportResult']['tables']);
            if ($num > 0) {
                echo "✅✅✅ FONCTIONNE! Tables: $num\n";
                $working_resource = $res_id;
                echo "\n🎯🎯🎯 BONNE RESOURCE TROUVÉE: $res_id 🎯🎯🎯\n\n";
                break;
            } else {
                echo "❌ Tables: 0\n";
            }
        } else {
            echo "❌ No data\n";
        }
    }
}

if ($working_resource) {
    echo "\n=== ✅ SOLUTION ===\n";
    echo "BONNE RESOURCE ID: $working_resource\n\n";

    echo "COPIE had code f getitemid.php line 127:\n";
    echo "Old: \"reportResourceId\":19907460\n";
    echo "New: \"reportResourceId\":$working_resource\n";

} else {
    echo "\n=== ❌ PAS DE RESOURCE FONCTIONNELLE ===\n";
    echo "Essaie de contacter ton admin Wialon pour le bon Resource ID\n";
}

echo "\n</pre>";
?>
