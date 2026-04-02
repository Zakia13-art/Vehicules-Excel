<?php
/**
 * Vérifier chaque groupe de transporteur et lister ses unités
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
echo "VÉRIFICATION DÉTAILLÉE PAR GROUPE\n";
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

echo str_repeat("=", 100) . "\n";
echo "VÉRIFICATION DE CHAQUE GROUPE:\n";
echo str_repeat("=", 100) . "\n\n";

foreach ($groups as $nom => $gid) {
    echo "📋 GROUPE: $nom (ID: $gid)\n";
    echo str_repeat("-", 100) . "\n";

    // Récupérer les infos du groupe
    $params = json_encode(array(
        'itemId' => $gid,
        'flags' => 0x00000001  // Basic data
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=core/read_item&params=" . urlencode($params) . "&sid=" . $sid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $data = json_decode($response, true);

    if ($http_code != 200 || isset($data['error'])) {
        echo "   ❌ ERREUR: " . ($data['error'] ?? 'HTTP ' . $http_code) . "\n";
        echo "   → Le groupe n'existe pas ou pas d'accès\n\n";
        continue;
    }

    $group_name = $data['item']['nm'] ?? 'Sans nom';
    $unit_count = isset($data['item']['u']) ? count($data['item']['u']) : 0;

    echo "   ✅ Groupe trouvé: \"$group_name\"\n";
    echo "   📦 Nombre d'unités: $unit_count\n";

    if ($unit_count > 0) {
        echo "   🚗 Unités dans ce groupe:\n";

        $active_count = 0;
        foreach ($data['item']['u'] as $unit_id) {
            // Récupérer les infos de l'unité
            $params2 = json_encode(array(
                'itemId' => $unit_id,
                'flags' => 0x00000011  // Basic + position
            ));

            $curl2 = curl_init();
            curl_setopt_array($curl2, array(
                CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=core/read_item&params=" . urlencode($params2) . "&sid=" . $sid,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
            ));

            $response2 = curl_exec($curl2);
            curl_close($curl2);

            $unit_data = json_decode($response2, true);

            if (isset($unit_data['item'])) {
                $unit_name = $unit_data['item']['nm'] ?? 'Sans nom';
                $last_seen = isset($unit_data['item']['pos']['t']) ? $unit_data['item']['pos']['t'] : 0;

                if ($last_seen > 0) {
                    $days_ago = floor((time() - $last_seen) / 86400);
                    $last_date = date('d/m/Y H:i', $last_seen);

                    if ($days_ago < 30) {
                        $active_count++;
                        $status = "🟢 ACTIF";
                    } elseif ($days_ago < 90) {
                        $status = "🟡 Passif (" . $days_ago . "j)";
                    } else {
                        $status = "🔴 Inactif (" . $days_ago . "j)";
                    }

                    echo "      • $unit_name | $last_date | $status\n";
                } else {
                    echo "      • $unit_name | ❌ Jamais envoyé de position\n";
                }
            }
        }

        echo "   📊 Résumé: $active_count/$unit_count unités actives (derniers 30 jours)\n";
    } else {
        echo "   ⚠️  Ce groupe est VIDE (pas d'unités)\n";
    }

    echo "\n";
    sleep(1);  // Pause pour éviter rate limiting
}

echo str_repeat("=", 100) . "\n";
echo "CONCLUSION:\n";
echo str_repeat("=", 100) . "\n";
echo "Les groupes avec '❌ ERREUR' n'existent pas dans Wialon.\n";
echo "Les groupes vides existent mais n'ont pas d'unités assignées.\n";
echo "Seuls les groupes avec unités actives peuvent générer des données.\n";
echo str_repeat("=", 100) . "\n";
?>
