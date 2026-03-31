<?php
/**
 * Vérifier le TYPE de chaque ID (Resource ou Unit Group)
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
echo "QUE TYPE D'ITEM SONT CES IDs ?\n";
echo "======================================\n\n";

$sid = sid();
if (!$sid) {
    echo "Erreur de connexion\n";
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

echo str_repeat("=", 80) . "\n";
echo "VÉRIFICATION DU TYPE DE CHAQUE ID:\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($groups as $nom => $gid) {
    echo sprintf("%-15s (ID: %-11s) → ", $nom, $gid);

    // Essayer comme Resource (avl_resource)
    $params = json_encode(array(
        'itemId' => $gid,
        'flags' => 0x00000001
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=core/read_item&params=" . urlencode($params) . "&sid=" . $sid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($response, true);

    if (!isset($data['error']) && isset($data['item'])) {
        $item_type = $data['item']['$t'] ?? 'Unknown';
        $item_name = $data['item']['nm'] ?? 'Sans nom';

        $type_map = array(
            'avl_resource' => '📁 RESOURCE',
            'avl_unit_group' => '📋 GROUPE D\'UNITÉS',
            'avl_unit' => '🚗 UNITÉ (VÉHICULE)'
        );

        $type_label = isset($type_map[$item_type]) ? $type_map[$item_type] : $item_type;
        echo "$type_label\n";
        echo "   Nom: \"$item_name\"\n";

        // Si c'est une resource, vérifier les templates
        if ($item_type == 'avl_resource') {
            echo "   ⚠️  C'est une RESOURCE (pas un groupe d'unités!)\n";
            if (isset($data['item']['tbl'])) {
                echo "   Templates disponibles: " . count($data['item']['tbl']) . "\n";
            }
        }
        echo "\n";
        continue;
    }

    echo "❌ NON TROUVÉ\n\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "EXPLICATION:\n";
echo str_repeat("=", 80) . "\n";
echo "• Les IDs de type 'RESOURCE' (avl_resource) sont utilisés pour les RAPPORTS\n";
echo "• Mais ils ne contiennent pas directement les véhicules\n";
echo "• Les rapports peuvent générer des données même si le groupe d'unités est vide\n";
echo "• CAR: les rapports utilisent d'autres critères que les groupes d'unités\n\n";
echo "→ Pour avoir des données, un transporteur doit avoir:\n";
echo "   1. Une Resource avec des Templates de rapports\n";
echo "   2. Des véhicules qui envoient des données GPS\n";
echo str_repeat("=", 80) . "\n";
?>
