<?php
/**
 * test_selectres.php
 * Test de la fonction selectRes() pour voir si les données sont sauvegardées
 */

header('Content-Type: application/json; charset=utf-8');

$output = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'test' => [],
];

try {
    require_once 'db.php';
    require_once 'getitemid.php';
    
    $db = Cnx();
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 1: Créer session Wialon
    // ═══════════════════════════════════════════════════════════
    
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
    
    $output['test']['1_session'] = [
        'status' => $sid ? '✅ OK' : '❌ Erreur',
        'sid' => $sid ? substr($sid, 0, 20) . '...' : 'Aucun'
    ];
    
    if (!$sid) {
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 2: Exécuter rapport pour BOUTCHRAFINE
    // ═══════════════════════════════════════════════════════════
    
    $groupe_id = 19022033; // BOUTCHRAFINE
    $from = time() - (1 * 86400); // 1 jour
    $to = time();
    
    $params = json_encode([
        'id' => $groupe_id,
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
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    $report_index = json_decode($response, true);
    
    $output['test']['2_execRep'] = [
        'status' => is_array($report_index) ? '✅ OK' : '❌ Erreur',
        'count' => is_array($report_index) ? count($report_index) : 0,
        'data' => is_array($report_index) ? array_slice($report_index, 0, 2) : []
    ];
    
    if (!is_array($report_index) || count($report_index) === 0) {
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 3: Appeler selectRes() pour chaque index
    // ═══════════════════════════════════════════════════════════
    
    $output['test']['3_avant_import'] = [
        'trajets_en_bdd' => $db->query("SELECT COUNT(*) as c FROM trajets")->fetch(PDO::FETCH_ASSOC)['c']
    ];
    
    $output['test']['4_appel_selectres'] = [];
    
    foreach ($report_index as $i => $value) {
        $output['test']['4_appel_selectres'][] = [
            'index' => $i,
            'value' => $value,
            'appel' => "selectRes($groupe_id, $i, $value, $sid)"
        ];
        
        // APPELER selectRes
        selectRes($groupe_id, $i, $value, $sid);
    }
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 4: Vérifier si les données sont en BDD
    // ═══════════════════════════════════════════════════════════
    
    $trajets_apres = $db->query("SELECT COUNT(*) as c FROM trajets")->fetch(PDO::FETCH_ASSOC)['c'];
    
    $output['test']['5_apres_import'] = [
        'trajets_en_bdd' => $trajets_apres,
        'trajets_ajoutes' => $trajets_apres - ($output['test']['3_avant_import']['trajets_en_bdd'] ?? 0)
    ];
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 5: Afficher les données importées
    // ═══════════════════════════════════════════════════════════
    
    if ($trajets_apres > 0) {
        $result = $db->query("SELECT * FROM trajets LIMIT 3");
        $sample = $result->fetchAll(PDO::FETCH_ASSOC);
        
        $output['test']['6_sample'] = [
            'trajets_sample' => $sample
        ];
    }
    
    $output['resume'] = [
        'avant' => $output['test']['3_avant_import']['trajets_en_bdd'] ?? 0,
        'apres' => $trajets_apres,
        'ajoutes' => ($output['test']['5_apres_import']['trajets_ajoutes'] ?? 0),
        'status' => $trajets_apres > 0 ? '✅ IMPORTATION OK!' : '❌ RIEN IMPORTÉ!'
    ];
    
} catch (Exception $e) {
    $output['error'] = $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>