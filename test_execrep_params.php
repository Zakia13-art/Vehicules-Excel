<?php
/**
 * test_execrep_params.php
 * Tester les BONS paramètres pour execRep
 */

header('Content-Type: application/json; charset=utf-8');

$output = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => [],
];

try {
    require_once 'db.php';
    
    // Créer session
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB\"}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    $data = json_decode($response, true);
    $sid = $data['eid'] ?? null;
    
    if (!$sid) {
        echo json_encode(['error' => 'Pas de session'], JSON_PRETTY_PRINT);
        exit;
    }
    
    // ═══════════════════════════════════════════════════════════
    // TESTER DIFFÉRENTS PARAMÈTRES
    // ═══════════════════════════════════════════════════════════
    
    $tests = [
        [
            'nom' => 'Paramètres avec reportResourceId',
            'params' => json_encode([
                'reportResourceId' => 19907460,
                'reportTemplateId' => 1,
                'reportObjectId' => 19022033,
                'reportObjectSecId' => 0,
                'interval' => [
                    'from' => time() - (86400),
                    'to' => time(),
                    'flags' => 0
                ]
            ])
        ],
        [
            'nom' => 'Paramètres simples avec id',
            'params' => json_encode([
                'id' => 19022033,
                'from' => time() - (86400),
                'to' => time()
            ])
        ],
        [
            'nom' => 'Paramètres avec ressource complète',
            'params' => json_encode([
                'resourceId' => 19907460,
                'templateId' => 1,
                'objectId' => 19022033,
                'from' => time() - (86400),
                'to' => time()
            ])
        ]
    ];
    
    foreach ($tests as $test) {
        $curl = curl_init();
        $url = "https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params=" . urlencode($test['params']) . "&sid=$sid";
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
        ));
        
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);
        
        $result = json_decode($response, true);
        
        $output['tests'][] = [
            'nom' => $test['nom'],
            'params' => $test['params'],
            'response' => $result,
            'status' => isset($result['error']) ? "❌ Erreur " . $result['error'] : (is_array($result) ? "✅ OK (" . count($result) . " items)" : "⚠️ Inconnu"),
            'curl_error' => $curl_error ?: 'Aucun'
        ];
    }
    
} catch (Exception $e) {
    $output['error'] = $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>