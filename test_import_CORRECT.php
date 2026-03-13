<?php
/**
 * test_import_CORRECT.php
 * Test final avec extraction CORRECTE des données
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
    
    $output['steps']['1_session'] = [
        'status' => $sid ? '✅ OK' : '❌ Erreur',
    ];
    
    if (!$sid) {
        echo json_encode($output, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Avant import
    $before = $db->query("SELECT COUNT(*) as c FROM trajets")->fetch(PDO::FETCH_ASSOC)['c'];
    
    $output['steps']['2_avant'] = ['trajets' => $before];
    
    // Exécuter rapport pour BOUTCHRAFINE
    $groupe_id = 19022033;
    
    cleanup_report($sid);
    sleep(1);
    
    $tables = execRep($groupe_id, $sid, 1, 0);
    
    $output['steps']['3_execRep'] = [
        'tables' => is_array($tables) ? count($tables) : 0,
        'status' => is_array($tables) && count($tables) > 0 ? '✅ OK' : '❌ Erreur'
    ];
    
    if (!is_array($tables) || count($tables) === 0) {
        echo json_encode($output, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Importer avec selectRes CORRECT
    $total_imported = 0;
    
    foreach ($tables as $table_index => $table) {
        $count = selectRes($groupe_id, $table_index, $table, $sid);
        $total_imported += $count;
        
        $output['steps']['4_table_' . $table_index] = [
            'nom' => $table['label'] ?? 'Table ' . $table_index,
            'rows' => $table['rows'] ?? 0,
            'imported' => $count
        ];
    }
    
    // Après import
    $after = $db->query("SELECT COUNT(*) as c FROM trajets")->fetch(PDO::FETCH_ASSOC)['c'];
    
    $output['steps']['5_apres'] = [
        'trajets' => $after,
        'ajoutés' => $after - $before,
        'status' => $after > $before ? '✅ SUCCÈS!' : '❌ Aucun'
    ];
    
    // Sample
    if ($after > 0) {
        $sample = $db->query("SELECT * FROM trajets LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        $output['steps']['6_sample'] = $sample;
    }
    
    $output['resume'] = [
        'status' => $after > $before ? '✅ IMPORT RÉUSSI!' : '❌ ÉCHOUÉ',
        'total_traités' => $total_imported,
        'avant' => $before,
        'après' => $after
    ];
    
} catch (Exception $e) {
    $output['error'] = $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>