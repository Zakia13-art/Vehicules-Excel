<?php
/**
 * LISTER TOUS les véhicules avec GPS actif et leurs groupes
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
echo "TOUS LES GROUPES ET VÉHICULES ACTIFS\n";
echo "======================================\n\n";

$sid = sid();
if (!$sid) {
    echo "Erreur de connexion\n";
    exit;
}

echo "Session OK\n\n";

// D'abord, lister tous les groupes d'unités
$params = json_encode(array(
    'spec' => array(
        'itemsType' => 'avl_unit_group',
        'propName' => 'sys_name',
        'propValueMask' => '*',
        'sortType' => 'sys_name'
    ),
    'force' => 1,
    'flags' => 0x0001,  // Basic data
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

$groups_data = json_decode($response, true);

if (isset($groups_data['error'])) {
    echo "ERREUR groupes: " . $groups_data['error'] . "\n";
} else {
    echo "GROUPES D'UNITÉS: " . count($groups_data['items'] ?? array()) . "\n\n";

    $groups_list = array();
    if (isset($groups_data['items'])) {
        foreach ($groups_data['items'] as $g) {
            $groups_list[$g['id']] = $g['nm'] ?? 'Sans nom';
        }
    }

    // Maintenant lister les véhicules avec plus de flags
    $params2 = json_encode(array(
        'spec' => array(
            'itemsType' => 'avl_unit',
            'propName' => 'sys_name',
            'propValueMask' => '*',
            'sortType' => 'sys_name'
        ),
        'force' => 1,
        'flags' => 0x00000111,  // Include basic data + position data + group ID
        'from' => 0,
        'to' => 0
    ));

    $curl2 = curl_init();
    curl_setopt_array($curl2, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params=" . urlencode($params2) . "&sid=" . $sid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response2 = curl_exec($curl2);
    curl_close($curl2);

    $units_data = json_decode($response2, true);

    if (isset($units_data['items'])) {
        echo "VÉHICULES TROUVÉS: " . count($units_data['items']) . "\n";
        echo str_repeat("-", 100) . "\n\n";

        // Grouper par groupe d'unités
        $by_group = array();

        foreach ($units_data['items'] as $item) {
            $nom = $item['nm'] ?? 'Sans nom';
            $id = $item['id'] ?? 'N/A';

            // Récupérer les groupes de l'unité
            $unit_groups = array();
            if (isset($item['pguv'])) {
                foreach ($item['pguv'] as $g) {
                    $gid = $g['id'];
                    if (isset($groups_list[$gid])) {
                        $unit_groups[] = $groups_list[$gid];
                        if (!isset($by_group[$gid])) {
                            $by_group[$gid] = array('name' => $groups_list[$gid], 'units' => array(), 'active' => 0);
                        }
                    }
                }
            }

            // Vérifier dernière activité
            $last_seen = isset($item['pos']['t']) ? $item['pos']['t'] : 0;
            $is_active = ($last_seen > 0 && (time() - $last_seen) < 30 * 86400); // Actif dans les 30 derniers jours

            foreach (array_keys($item['pguv'] ?? array()) as $gid) {
                if (isset($by_group[$gid])) {
                    $by_group[$gid]['units'][] = array(
                        'name' => $nom,
                        'last_seen' => $last_seen
                    );
                    if ($is_active) {
                        $by_group[$gid]['active']++;
                    }
                }
            }
        }

        // Afficher les groupes avec véhicules actifs
        echo str_repeat("=", 100) . "\n";
        echo "GROUPES AVEC VÉHICULES ACTIFS (derniers 30 jours):\n";
        echo str_repeat("=", 100) . "\n\n";

        $found_our_groups = false;
        $recherche = array('DOUKALI', 'CONSMETA', 'SOMATRIN', 'MARATRANS', 'BOUTCHRAF', 'GTC', 'G.T.C', 'COTRAMAB', 'CORYAD', 'CHOUROUK', 'CARRE', 'STB', 'FASTTRANS');

        foreach ($by_group as $gid => $info) {
            if ($info['active'] > 0) {
                $is_our = false;
                foreach ($recherche as $r) {
                    if (stripos($info['name'], $r) !== false) {
                        $is_our = true;
                        $found_our_groups = true;
                        break;
                    }
                }

                $marker = $is_our ? " 👉 UN DE NOS TRANSPORTEURS" : "";
                echo "📋 " . $info['name'] . " (ID: $gid)" . $marker . "\n";
                echo "   → " . $info['active'] . " véhicules actifs\n";

                if ($is_our) {
                    echo "   Véhicules:\n";
                    foreach ($info['units'] as $u) {
                        if ($u['last_seen'] > 0) {
                            $days = floor((time() - $u['last_seen']) / 86400);
                            $date = date('d/m/Y', $u['last_seen']);
                            echo "      • " . substr($u['name'], 0, 30) . " | " . $date . " (" . $days . "j)\n";
                        }
                    }
                }
                echo "\n";
            }
        }

        if (!$found_our_groups) {
            echo "⚠️  AUCUN de nos transporteurs n'a de véhicules actifs!\n\n";
        }

        echo str_repeat("=", 100) . "\n";
        echo "TOUS NOS TRANSPORTEURS (même sans activité):\n";
        echo str_repeat("=", 100) . "\n";

        foreach ($by_group as $gid => $info) {
            foreach ($recherche as $r) {
                if (stripos($info['name'], $r) !== false) {
                    echo "✅ " . sprintf("%-20s | ID: %-12s | %2d véhicules | %2d actifs\n",
                        $info['name'], $gid, count($info['units']), $info['active']);
                }
            }
        }
    }
}

echo "\n======================================\n";
?>
