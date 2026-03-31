<?php
/**
 * Lister TOUS les GROUPES D'UNITÉS (pas les ressources)
 */
set_time_limit(600);

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
echo "LISTE DES GROUPES D'UNITÉS WIALON\n";
echo "======================================\n\n";

$sid = sid();
if (!$sid) {
    echo "Erreur de connexion\n";
    exit;
}

echo "Session OK\n\n";

// Lister les groupes d'unités
$params = json_encode(array(
    'spec' => array(
        'itemsType' => 'avl_unit_group',
        'propName' => 'sys_name',
        'propValueMask' => '*',
        'sortType' => 'sys_name'
    ),
    'force' => 1,
    'flags' => 1,
    'from' => 0,
    'to' => 0
));

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params=" . urlencode($params) . "&sid=" . $sid,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

if (isset($data['error'])) {
    echo "ERREUR: " . $data['error'] . " - " . ($data['reason'] ?? 'Unknown') . "\n";
    exit;
}

if (!isset($data['items'])) {
    echo "Pas d'items trouvés\n";
    exit;
}

echo "GROUPES D'UNITÉS TROUVÉS:\n";
echo str_repeat("-", 70) . "\n";
echo sprintf("%-20s | %-15s | %s\n", "NOM", "ID", "LIBELLÉ");
echo str_repeat("-", 70) . "\n";

$found = array();
$recherche = array('DOUKALI', 'CONSMETA', 'SOMATRIN', 'MARATRANS', 'BOUTCHRAFINE', 'G.T.C', 'COTRAMAB', 'CORYAD', 'CHOUROUK', 'CARRE', 'STB', 'FASTTRANS');

foreach ($data['items'] as $item) {
    $nom = $item['nm'] ?? 'Sans nom';
    $id = $item['id'] ?? 'N/A';
    $libelle = $item['d'] ?? '';

    printf("%-20s | %-15s | %s\n", $nom, $id, $libelle);

    foreach ($recherche as $t) {
        if (stripos($nom, $t) !== false || stripos($libelle, $t) !== false) {
            if (!isset($found[$t])) {
                $found[$t] = $id;
            }
        }
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "RÉSULTAT - IDs TROUVÉS POUR NOS TRANSPORTEURS:\n";
echo str_repeat("=", 70) . "\n";

$ids_actuels = array(
    'BOUTCHRAFINE' => 12173650,
    'SOMATRIN' => 19596443,
    'MARATRANS' => 25044600,
    'G.T.C' => 30085013,
    'DOUKALI' => 19585587,
    'COTRAMAB' => 19675133,
    'CORYAD' => 19675239,
    'CONSMETA' => 19629962,
    'CHOUROUK' => 19675675,
    'CARRE' => 29440837,
    'STB' => 19675330,
    'FASTTRANS' => 19675777
);

foreach ($recherche as $t) {
    if (isset($found[$t])) {
        echo sprintf("  %-15s | ID: %-12s", $t, $found[$t]);
        if ($found[$t] == $ids_actuels[$t]) {
            echo " | ✅ ID correct";
        } else {
            echo " | ⚠️ Différent de: " . $ids_actuels[$t];
        }
        echo "\n";
    } else {
        echo sprintf("  %-15s | ❌ NON TROUVÉ\n", $t);
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
?>
