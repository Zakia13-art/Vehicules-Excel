<?php
/**
 * debug_table_structure.php
 * Voir la structure EXACTE des données retournées par Wialon
 */

header('Content-Type: application/json; charset=utf-8');

$output = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
];

try {
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
    
    // Exécuter rapport
    $params = json_encode([
        'reportResourceId' => 19907460,
        'reportTemplateId' => 1,
        'reportObjectId' => 19022033,
        'reportObjectSecId' => 0,
        'interval' => [
            'from' => time() - (86400),
            'to' => time(),
            'flags' => 0
        ]
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
    
    $report = json_decode($response, true);
    
    // Afficher la structure complète
    if (isset($report['reportResult']['tables'][0])) {
        $table = $report['reportResult']['tables'][0];
        
        $output['table_structure'] = [
            'nom' => $table['name'] ?? 'N/A',
            'label' => $table['label'] ?? 'N/A',
            'rows' => $table['rows'] ?? 0,
            'columns' => $table['columns'] ?? 0,
            'header' => $table['header'] ?? [],
            'clés_principales' => array_keys($table)
        ];
        
        // Afficher la première ligne (si elle existe)
        if (isset($table['c']) && is_array($table['c']) && count($table['c']) > 0) {
            $first_row = $table['c'][0];
            
            $output['premiere_ligne'] = [
                'structure' => $first_row,
                'clés' => array_keys($first_row),
                'type' => gettype($first_row)
            ];
            
            // Afficher chaque élément de la première ligne
            $output['premiere_ligne_details'] = [];
            foreach ($first_row as $key => $value) {
                $output['premiere_ligne_details'][$key] = [
                    'valeur' => $value,
                    'type' => gettype($value),
                    'description' => "Position $key"
                ];
            }
        }
        
        // Afficher toute la table pour inspection
        $output['table_complete'] = $table;
        
    } else {
        $output['error'] = 'Pas de table reçue';
        $output['response'] = $report;
    }
    
} catch (Exception $e) {
    $output['error'] = $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>