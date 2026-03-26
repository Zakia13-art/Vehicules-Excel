<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "lesgets.php";

echo "<h1>🔍 CHERCHER RESOURCE ID AVEC GROUPS</h1>";
echo "<pre>";

$sid = sid();
if (!$sid) {
    echo "❌ Erreur connexion\n";
    exit;
}

echo "✅ Connecté\n\n";

// ========================================
// 1. CHERCHER RESOURCE AVEC TEMPLATES
// ========================================
echo "=== RESSOURCES AVEC TEMPLATES ===\n";

$curl = curl_init();
$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_resource","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":0}&sid=' . $sid;

curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

$resources_with_templates = array();

if (isset($data['items'])) {
    echo "Trouvé " . count($data['items']) . " resources\n\n";

    // Chercher les resources qui ressemblent à des ressources de rapports
    foreach ($data['items'] as $res) {
        $res_id = $res['id'];
        $res_name = $res['nm'] ?? '';

        // Chercher des ressources avec des noms typiques
        if (stripos($res_name, 'webservice') !== false ||
            stripos($res_name, 'report') !== false ||
            stripos($res_name, 'rapport') !== false ||
            stripos($res_name, 'groupe') !== false) {
            echo "Resource ID: $res_id | Nom: $res_name\n";
            $resources_with_templates[] = $res_id;
        }
    }
}

// ========================================
// 2. TESTER CHAQUE RESOURCE AVEC MARATRANS
// ========================================
echo "\n=== TESTER REPORT AVEC MARATRANS ===\n";

$test_group_id = 19631505; // MARATRANS
$to = time();
$from = $to - (7 * 86400);

$resources_to_test = !empty($resources_with_templates) ? $resources_with_templates : [19907460];

foreach ($resources_to_test as $res_id) {
    echo "\nTest Resource ID: $res_id\n";

    $curl2 = curl_init();
    $url2 = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":' . $res_id . ',"reportTemplateId":1,"reportObjectId":' . $test_group_id . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

    curl_setopt_array($curl2, array(
        CURLOPT_URL => $url2,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ));

    $resp2 = curl_exec($curl2);
    $err2 = curl_error($curl2);
    curl_close($curl2);

    if ($err2) {
        echo "  ❌ Erreur CURL: $err2\n";
    } else {
        $result2 = json_decode($resp2, true);

        if (isset($result2['error'])) {
            echo "  ⚠️ Erreur API: " . $result2['error'] . "\n";
        } elseif (isset($result2['reportResult']['tables'])) {
            $num_tables = count($result2['reportResult']['tables']);
            echo "  ✅ FONCTIONNE! Tables: $num_tables\n";

            if ($num_tables > 0) {
                echo "  🎯 C'EST LA BONNE RESOURCE!\n";
                break;
            }
        } else {
            echo "  ❌ Response inconnue\n";
        }
    }
}

echo "\n=== CONSEIL ===\n";
echo "Si aucune resource ne fonctionne, essayez d'aller dans votre interface Wialon:\n";
echo "1. Ouvrir un rapport manuellement\n";
echo "2. Regarder l'URL pour trouver: reportResourceId=XXXXX\n";
echo "3. Utiliser ce XXXXX comme Resource ID\n";

echo "\n=== ✅ FIN ===\n";
echo "</pre>";
?>
