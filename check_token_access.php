<?php
/**
 * Verifier quels groupes sont accessibles avec ce token
 */

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
    return $v_det;
}

echo "======================================\n";
echo "VERIFICATION TOKEN WIALON\n";
echo "======================================\n\n";

$login_result = sid();

if (isset($login_result['eid'])) {
    echo "Connexion reussie !\n";
    echo "Session ID: " . substr($login_result['eid'], 0, 20) . "...\n";
    echo "User: " . ($login_result['user']['nm'] ?? 'Inconnu') . "\n\n";

    // Recuperer tous les items accessibles
    $sid = $login_result['eid'];
    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit_group","propName":"sys_name","propValueMask":"*","sortType":"sys_name"},"force":1,"flags":1,"from":0,"to":1000}&sid=' . $sid;
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    $items = json_decode($response, true);

    echo "======================================\n";
    echo "GROUPES ACCESSIBLES AVEC CE TOKEN:\n";
    echo "======================================\n\n";

    if (isset($items['items'])) {
        echo "Total groupes accessibles: " . count($items['items']) . "\n\n";
        foreach ($items['items'] as $item) {
            echo "- ID: {$item['id']} | Nom: {$item['nm']}\n";
        }
    } else {
        echo "Aucun groupe trouve ou erreur de permission\n";
        if (isset($items['error'])) {
            echo "Erreur: {$items['error']}\n";
        }
    }
} else {
    echo "Erreur de connexion:\n";
    print_r($login_result);
}

echo "\n======================================\n";
?>
