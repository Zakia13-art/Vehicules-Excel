<?php
/**
 * test_final_import.php
 * Test final - Import des données avec les paramètres corrigés
 */

header('Content-Type: application/json; charset=utf-8');

$output = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'steps' => [],
];

try {
    require_once 'db.php';
    require_once 'getitemid.php';
    
    $db = Cnx();
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 1: Créer session
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
    
    $output['steps']['1_session'] = [
        'status' => $sid ? '✅ OK' : '❌ Erreur',
        'sid' => $sid ? substr($sid, 0, 20) . '...' : null
    ];
    
    if (!$sid) {
        echo json_encode($output, JSON_PRETTY_PRINT);
        exit;
    }
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 2: Vérifier nombre de trajets AVANT
    // ═══════════════════════════════════════════════════════════
    
    $before = $db->query("SELECT COUNT(*) as c FROM trajets")->fetch(PDO::FETCH_ASSOC)['c'];
    
    $output['steps']['2_avant_import'] = [
        'trajets_en_bdd' => $before
    ];
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 3: Exécuter rapport pour BOUTCHRAFINE
    // ═══════════════════════════════════════════════════════════
    
    $groupe_id = 19022033;
    
    cleanup_report($sid);
    sleep(1);
    
    $tables = execRep($groupe_id, $sid, 1, 0); // 1 jour
    
    $output['steps']['3_execRep'] = [
        'groupe_id' => $groupe_id,
        'tables_reçues' => is_array($tables) ? count($tables) : 0,
        'status' => is_array($tables) && count($tables) > 0 ? '✅ OK' : '❌ Erreur'
    ];
    
    if (!is_array($tables) || count($tables) === 0) {
        echo json_encode($output, JSON_PRETTY_PRINT);
        exit;
    }
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 4: Importer les données avec selectRes
    // ═══════════════════════════════════════════════════════════
    
    $total_imported = 0;
    
    foreach ($tables as $table_index => $table) {
        $count = selectRes($groupe_id, $table_index, $table, $sid);
        $total_imported += $count;
    }
    
    $output['steps']['4_selectRes'] = [
        'tables_traitées' => count($tables),
        'trajets_importés' => $total_imported,
        'status' => $total_imported > 0 ? '✅ OK' : '⚠️ 0 trajets'
    ];
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 5: Vérifier nombre de trajets APRÈS
    // ═══════════════════════════════════════════════════════════
    
    $after = $db->query("SELECT COUNT(*) as c FROM trajets")->fetch(PDO::FETCH_ASSOC)['c'];
    
    $output['steps']['5_apres_import'] = [
        'trajets_en_bdd' => $after,
        'trajets_ajoutés' => $after - $before,
        'status' => $after > $before ? '✅ IMPORT RÉUSSI!' : '❌ Aucun import'
    ];
    
    // ═══════════════════════════════════════════════════════════
    // ÉTAPE 6: Afficher un sample
    // ═══════════════════════════════════════════════════════════
    
    if ($after > 0) {
        $sample = $db->query("SELECT * FROM trajets LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        
        $output['steps']['6_sample'] = [
            'first_3_trajets' => $sample
        ];
    }
    
    // ═══════════════════════════════════════════════════════════
    // RÉSUMÉ
    // ═══════════════════════════════════════════════════════════
    
    $output['summary'] = [
        'avant' => $before,
        'apres' => $after,
        'ajoutes' => $after - $before,
        'status' => $after > $before ? '✅ SUCCÈS!' : '❌ ÉCHOUÉ'
    ];
    
} catch (Exception $e) {
    $output['error'] = $e->getMessage();
    $output['trace'] = $e->getTrace();
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>