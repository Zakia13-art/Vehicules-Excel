<?php
/**
 * test_wialon_api.php
 * Test complet de l'API Wialon - Débogage JSON
 */

header('Content-Type: application/json; charset=utf-8');

$output = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => [],
    'errors' => [],
];

// ═══════════════════════════════════════════════════════════
// TEST 1: Token Wialon valide?
// ═══════════════════════════════════════════════════════════

$token = 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB';

$output['tests']['1_token'] = [
    'nom' => 'Vérifier le Token',
    'token' => substr($token, 0, 20) . '...',
    'longueur' => strlen($token),
    'status' => strlen($token) > 50 ? '✅ Valid' : '❌ Trop court'
];

// ═══════════════════════════════════════════════════════════
// TEST 2: Connexion à l'API Wialon
// ═══════════════════════════════════════════════════════════

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . $token . "\"}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_VERBOSE => false,
));

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curl_error = curl_error($curl);
curl_close($curl);

$output['tests']['2_connexion'] = [
    'nom' => 'Connexion API Wialon',
    'url' => 'https://hst-api.wialon.com/wialon/ajax.html',
    'http_code' => $http_code,
    'response_length' => strlen($response),
    'curl_error' => $curl_error ?: 'Aucun',
    'status' => $http_code === 200 ? '✅ OK' : "❌ HTTP $http_code"
];

if ($curl_error) {
    $output['errors'][] = "Erreur cURL: $curl_error";
}

// ═══════════════════════════════════════════════════════════
// TEST 3: Décoder la réponse JSON
// ═══════════════════════════════════════════════════════════

$data = json_decode($response, true);
$json_error = json_last_error_msg();

$output['tests']['3_json_decode'] = [
    'nom' => 'Décoder réponse JSON',
    'raw_response' => substr($response, 0, 100) . '...',
    'json_error' => $json_error,
    'decoded_keys' => $data ? array_keys($data) : [],
    'status' => $data ? '✅ Décodé' : '❌ Erreur JSON'
];

// ═══════════════════════════════════════════════════════════
// TEST 4: Session ID reçu?
// ═══════════════════════════════════════════════════════════

$sid = null;
if (isset($data['eid'])) {
    $sid = $data['eid'];
    $output['tests']['4_session'] = [
        'nom' => 'Session ID (eid)',
        'sid' => $sid,
        'longueur' => strlen($sid),
        'status' => '✅ Reçu'
    ];
} else {
    $output['tests']['4_session'] = [
        'nom' => 'Session ID (eid)',
        'status' => '❌ Non reçu',
        'response_data' => $data
    ];
    $output['errors'][] = 'Session ID non reçu. Réponse API: ' . json_encode($data);
}

// ═══════════════════════════════════════════════════════════
// TEST 5: Tester getResID (Wialon)
// ═══════════════════════════════════════════════════════════

if ($sid) {
    $curl = curl_init();
    $params = json_encode(['id' => 19907460]);
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=resource/get_id&params=" . urlencode($params) . "&sid=$sid",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response_res = curl_exec($curl);
    $curl_error_res = curl_error($curl);
    curl_close($curl);
    
    $data_res = json_decode($response_res, true);
    
    $output['tests']['5_getResID'] = [
        'nom' => 'getResID (Récupérer ID Ressource)',
        'response' => $data_res,
        'status' => isset($data_res['id']) ? '✅ ID reçu' : '❌ Erreur',
        'error' => $curl_error_res ?: 'Aucun'
    ];
}

// ═══════════════════════════════════════════════════════════
// TEST 6: Tester execRep (Exécuter Rapport)
// ═══════════════════════════════════════════════════════════

if ($sid) {
    // Paramètres: ID groupe, 7 derniers jours
    $from = time() - (7 * 86400);
    $to = time();
    $params = json_encode([
        'id' => 19022033,  // BOUTCHRAFINE
        'from' => $from,
        'to' => $to
    ]);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params=" . urlencode($params) . "&sid=$sid",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response_exec = curl_exec($curl);
    $curl_error_exec = curl_error($curl);
    curl_close($curl);
    
    $data_exec = json_decode($response_exec, true);
    
    $output['tests']['6_execRep'] = [
        'nom' => 'execRep (Exécuter Rapport)',
        'groupe_id' => 19022033,
        'from' => date('Y-m-d H:i', $from),
        'to' => date('Y-m-d H:i', $to),
        'response_type' => gettype($data_exec),
        'response_count' => is_array($data_exec) ? count($data_exec) : 0,
        'first_item' => is_array($data_exec) && count($data_exec) > 0 ? reset($data_exec) : null,
        'status' => is_array($data_exec) && count($data_exec) > 0 ? '✅ Données reçues' : '❌ Aucune donnée',
        'error' => $curl_error_exec ?: 'Aucun'
    ];
}

// ═══════════════════════════════════════════════════════════
// RÉSUMÉ
// ═══════════════════════════════════════════════════════════

$output['summary'] = [
    'total_tests' => count($output['tests']),
    'erreurs' => count($output['errors']),
    'status' => empty($output['errors']) ? '✅ TOUS LES TESTS PASSÉS' : '❌ ERREURS DÉTECTÉES'
];

if (!empty($output['errors'])) {
    $output['errors_details'] = $output['errors'];
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>