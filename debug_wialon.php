<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "lesgets.php";

echo "<h1>🔍 DEBUG WIALON - RESSOURCES & GROUPS</h1>";
echo "<pre>";

// ========================================
// 1. CONNEXION
// ========================================
echo "\n=== 1. CONNEXION ===\n";
$sid = sid();
if (!$sid) {
    echo "❌ Erreur connexion Wialon\n";
    exit;
}
echo "✅ SID: " . substr($sid, 0, 30) . "...\n";

// ========================================
// 2. TESTER RESOURCE 19907460
// ========================================
echo "\n=== 2. RESSOURCE 19907460 ===\n";

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

if (isset($data['items'])) {
    echo "Nombre de resources trouvées: " . count($data['items']) . "\n\n";

    foreach ($data['items'] as $item) {
        $id = $item['id'];
        $name = $item['nm'] ?? 'Sans nom';

        echo "Resource ID: $id | Nom: $name\n";

        // Si c'est la resource 19907460, chercher ses templates
        if ($id == 19907460) {
            echo "  >>> CHERCHER LES TEMPLATES DE CETTE RESOURCE <<<\n";

            $curl2 = curl_init();
            $url2 = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_resource","propName":"reporttemplates","propValueMask":"*","sortType":"reporttemplates","propType":"propitemname"},"force":1,"flags":8192,"from":0,"to":0}&sid=' . $sid;

            curl_setopt_array($curl2, array(
                CURLOPT_URL => $url2,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ));

            $resp2 = curl_exec($curl2);
            curl_close($curl2);

            $data2 = json_decode($resp2, true);

            if (isset($data2['items'])) {
                foreach ($data2['items'] as $res) {
                    if ($res['id'] == 19907460 && isset($res['rep'])) {
                        echo "  Templates disponibles:\n";
                        foreach ($res['rep'] as $tpl) {
                            $tpl_id = $tpl['id'] ?? '';
                            $tpl_name = $tpl['n'] ?? '';
                            echo "    - Template ID $tpl_id: $tpl_name\n";
                        }
                    }
                }
            }
        }
    }
} else {
    echo "❌ Erreur: Pas de resources trouvées\n";
}

// ========================================
// 3. TESTER LES GROUPS
// ========================================
echo "\n=== 3. VÉRIFIER LES GROUPS ===\n";

$groups_to_test = array(
    'BOUTCHRAFINE' => 19022033,
    'SOMATRIN' => 19596491,
    'MARATRANS' => 19631505
);

foreach ($groups_to_test as $name => $group_id) {
    echo "\nGroupe: $name (ID: $group_id)\n";

    $curl3 = curl_init();
    $url3 = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit_group","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":0}&sid=' . $sid;

    curl_setopt_array($curl3, array(
        CURLOPT_URL => $url3,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $resp3 = curl_exec($curl3);
    curl_close($curl3);

    $data3 = json_decode($resp3, true);

    if (isset($data3['items'])) {
        $found = false;
        foreach ($data3['items'] as $item) {
            if ($item['id'] == $group_id) {
                echo "  ✅ Groupe TROUVÉ: " . ($item['nm'] ?? 'Sans nom') . "\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "  ❌ Groupe NON TROUVÉ dans ce compte!\n";
        }
    }
}

// ========================================
// 4. TESTER UN REPORT
// ========================================
echo "\n=== 4. TESTER REPORT (MARATRANS) ===\n";

$to = time();
$from = $to - (7 * 86400);

$curl4 = curl_init();
$url4 = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":22861605,"reportTemplateId":1,"reportObjectId":19631505,"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

curl_setopt_array($curl4, array(
    CURLOPT_URL => $url4,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
));

$resp4 = curl_exec($curl4);
$err4 = curl_error($curl4);
curl_close($curl4);

if ($err4) {
    echo "❌ Erreur CURL: $err4\n";
} else {
    $result4 = json_decode($resp4, true);

    if (isset($result4['error'])) {
        echo "❌ Erreur API: " . $result4['error'] . "\n";
    } elseif (isset($result4['reportResult']['tables'])) {
        echo "✅ Report fonctionne! Tables: " . count($result4['reportResult']['tables']) . "\n";
    } else {
        echo "⚠️ Response inattendue:\n";
        print_r($result4);
    }
}

echo "\n=== ✅ FIN DEBUG ===\n";
echo "</pre>";
?>
