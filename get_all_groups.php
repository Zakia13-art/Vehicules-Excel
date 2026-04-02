<?php
/**
 * Lister TOUS les groupes disponibles avec leurs IDs
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

echo "======================================\n";
echo "LISTE DE TOUS LES GROUPES WIALON\n";
echo "======================================\n\n";

$sid = sid();
if (!$sid) {
    echo "Erreur de connexion\n";
    exit;
}

echo "Session OK\n\n";

// Méthode alternative: lister les items avec une requête plus simple
$params = array(
    'spec' => array(
        'itemsType' => 'avl_resource',
        'propName' => 'sys_name',
        'propValueMask' => '*',
        'sortType' => 'sys_name'
    ),
    'force' => 1,
    'flags' => 1,
    'from' => 0,
    'to' => 0
);

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params=" . urlencode(json_encode($params)) . "&sid=" . $sid,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

if (isset($data['error'])) {
    echo "ERREUR: " . $data['error'] . " - " . ($data['reason'] ?? 'Unknown') . "\n";
    echo "\nRéponse brute:\n";
    print_r($data);
    exit;
}

if (!isset($data['items'])) {
    echo "Pas d'items trouvés\n";
    print_r($data);
    exit;
}

echo "GROUPES TROUVÉS:\n";
echo str_repeat("-", 70) . "\n";
echo sprintf("%-20s | %-15s | %s\n", "NOM", "ID", "LIBELLÉ");
echo str_repeat("-", 70) . "\n";

$found = array();
$recherche = array('DOUKALI', 'CONSMETA', 'SOMATRIN', 'MARATRANS', 'BOUTCHRAFINE', 'G.T.C', 'COTRAMAB', 'CORYAD', 'CONSMETA', 'CHOUROUK', 'CARRE', 'STB', 'FASTTRANS');

foreach ($data['items'] as $item) {
    $nom = $item['nm'] ?? 'Sans nom';
    $id = $item['id'] ?? 'N/A';
    $libelle = $item['d'] ?? '';

    printf("%-20s | %-15s | %s\n", $nom, $id, $libelle);

    // Vérifier si c'est un de nos transporteurs
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

foreach ($recherche as $t) {
    if (isset($found[$t])) {
        echo sprintf("  %-15s | ID: %-12s", $t, $found[$t]);
        if ($found[$t] == $ids_actuels[$t]) {
            echo " | ✅ ID correct";
        } else {
            echo " | ⚠️ ID différent de: " . $ids_actuels[$t];
        }
        echo "\n";
    } else {
        echo sprintf("  %-15s | ❌ NON TROUVÉ (ID actuel: %s)\n", $t, $ids_actuels[$t]);
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Si un groupe est 'NON TROUVÉ', c'est qu'il n'existe pas dans Wialon\n";
echo "ou il a un nom différent.\n";
echo str_repeat("=", 70) . "\n";
?>
