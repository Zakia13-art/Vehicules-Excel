<?php
/**
 * Vérification détaillée - Affiche les réponses API brutes
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

function cleanRepport($sid) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=report/cleanup_result&params={}&sid=$sid",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    curl_exec($curl);
    curl_close($curl);
}

function execRepDetailed($group, $sid, $from1, $to1) {
    $to = time() - ($to1 * 86400);
    $from = time() - ($from1 * 86400);

    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":22861605,"reportTemplateId":1,"reportObjectId":' . $group . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

    curl_setopt_array($curl, array(
        CURLOPT_URL => $Url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return array(
        'http_code' => $http_code,
        'response' => json_decode($response, true)
    );
}

echo "======================================\n";
echo "VERIFICATION DETAILLEE API WIALON\n";
echo "======================================\n\n";

$sid = sid();
if (!$sid) {
    echo "Erreur session\n";
    exit;
}

echo "Session OK (SID: " . substr($sid, 0, 10) . "...)\n\n";

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

$from1 = 8;
$to1 = 1;

echo "Test avec intervalle: 7 derniers jours\n\n";

foreach ($groups as $nom => $id) {
    cleanRepport($sid);
    sleep(1);

    echo str_repeat("=", 60) . "\n";
    echo "GROUPE: $nom (ID: $id)\n";
    echo str_repeat("-", 60) . "\n";

    $result = execRepDetailed($id, $sid, $from1, $to1);

    echo "HTTP Code: " . $result['http_code'] . "\n";

    if ($result['http_code'] != 200) {
        echo "❌ Erreur HTTP\n";
        continue;
    }

    $data = $result['response'];

    // Vérifier les erreurs Wialon
    if (isset($data['error'])) {
        echo "❌ ERREUR API Wialon:\n";
        echo "   Code: " . $data['error'] . "\n";
        echo "   Message: " . ($data['reason'] ?? 'Unknown') . "\n";
        continue;
    }

    if (isset($data['reportResult']['tables'])) {
        $nbrtab = sizeof($data['reportResult']['tables']);

        if ($nbrtab == 0) {
            echo "⚠️  Pas de tables (groupe existe mais pas de données)\n";
        } else {
            $total_rows = 0;
            for ($i = 0; $i < $nbrtab; $i++) {
                $rows = $data['reportResult']['tables'][$i]['rows'] ?? 0;
                $total_rows += $rows;
            }
            echo "✅ $nbrtab table(s) | $total_rows lignes\n";
        }
    } else {
        echo "❌ Structure réponse invalide:\n";
        echo "   Clés disponibles: " . implode(', ', array_keys($data)) . "\n";
    }

    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "RÉSUMÉ:\n";
echo str_repeat("=", 60) . "\n";
echo "- Les groupes avec '✅' ont des données GPS\n";
echo "- Les groupes avec '⚠️' existent mais n'ont pas d'activité\n";
echo "- Les groupes avec '❌ ERREUR API' = ID invalide ou pas d'accès\n";
echo str_repeat("=", 60) . "\n";
?>
