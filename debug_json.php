<?php
/**
 * debug_json_v2.php
 * Débogage complet - Affiche TOUT en JSON
 */

header('Content-Type: application/json; charset=utf-8');

$output = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => [],
];

// ═══════════════════════════════════════════════════════════
// CHECK 1: Fichier db.php existe?
// ═══════════════════════════════════════════════════════════

$db_file = __DIR__ . '/db.php';
$output['checks']['1_db_file'] = [
    'nom' => 'Fichier db.php',
    'chemin' => $db_file,
    'existe' => file_exists($db_file) ? '✅ OUI' : '❌ NON',
    'readable' => is_readable($db_file) ? '✅ OUI' : '❌ NON'
];

// ═══════════════════════════════════════════════════════════
// CHECK 2: Inclure et tester Cnx()
// ═══════════════════════════════════════════════════════════

try {
    require_once 'db.php';
    
    $db = Cnx();
    
    $output['checks']['2_connexion_bdd'] = [
        'nom' => 'Connexion PDO',
        'status' => '✅ OK',
        'type' => get_class($db)
    ];
} catch (Exception $e) {
    $output['checks']['2_connexion_bdd'] = [
        'nom' => 'Connexion PDO',
        'status' => '❌ ERREUR',
        'error' => $e->getMessage()
    ];
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
// CHECK 3: Tables existent?
// ═══════════════════════════════════════════════════════════

try {
    $result = $db->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    $output['checks']['3_tables'] = [
        'nom' => 'Tables en BDD',
        'tables' => $tables,
        'count' => count($tables),
        'status' => count($tables) > 0 ? '✅ OK' : '❌ VIDE'
    ];
} catch (Exception $e) {
    $output['checks']['3_tables'] = [
        'nom' => 'Tables en BDD',
        'status' => '❌ ERREUR',
        'error' => $e->getMessage()
    ];
}

// ═══════════════════════════════════════════════════════════
// CHECK 4: Table "trajets" existe et contient combien de lignes?
// ═══════════════════════════════════════════════════════════

try {
    $result = $db->query("SELECT COUNT(*) as count FROM trajets");
    $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
    
    $output['checks']['4_trajets_count'] = [
        'nom' => 'Table trajets',
        'total_lignes' => $count,
        'status' => $count > 0 ? "✅ $count trajets" : '⚠️ VIDE (0 trajets)',
    ];
} catch (Exception $e) {
    $output['checks']['4_trajets_count'] = [
        'nom' => 'Table trajets',
        'status' => '❌ ERREUR',
        'error' => $e->getMessage()
    ];
}

// ═══════════════════════════════════════════════════════════
// CHECK 5: Afficher les 5 premiers trajets
// ═══════════════════════════════════════════════════════════

try {
    $result = $db->query("
        SELECT t.*, 
               tt.name as transporteur_name,
               c.name as chauffeur_name
        FROM trajets t
        LEFT JOIN transporteurs tt ON t.transporteur = tt.id
        LEFT JOIN chauffeurs c ON t.chauffeur = c.matricule
        LIMIT 5
    ");
    
    $trajets = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $output['checks']['5_sample_trajets'] = [
        'nom' => '5 premiers trajets',
        'count' => count($trajets),
        'trajets' => $trajets
    ];
} catch (Exception $e) {
    $output['checks']['5_sample_trajets'] = [
        'nom' => '5 premiers trajets',
        'status' => '❌ ERREUR',
        'error' => $e->getMessage()
    ];
}

// ═══════════════════════════════════════════════════════════
// CHECK 6: Structure table trajets
// ═══════════════════════════════════════════════════════════

try {
    $result = $db->query("DESCRIBE trajets");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $output['checks']['6_structure'] = [
        'nom' => 'Structure table trajets',
        'colonnes' => array_column($columns, 'Field'),
        'details' => $columns
    ];
} catch (Exception $e) {
    $output['checks']['6_structure'] = [
        'nom' => 'Structure table trajets',
        'status' => '❌ ERREUR',
        'error' => $e->getMessage()
    ];
}

// ═══════════════════════════════════════════════════════════
// CHECK 7: Statistiques
// ═══════════════════════════════════════════════════════════

try {
    $trans = $db->query("SELECT COUNT(DISTINCT transporteur) as count FROM trajets")->fetch(PDO::FETCH_ASSOC)['count'];
    $chauff = $db->query("SELECT COUNT(DISTINCT chauffeur) as count FROM trajets")->fetch(PDO::FETCH_ASSOC)['count'];
    $vehic = $db->query("SELECT COUNT(DISTINCT vehicule) as count FROM trajets")->fetch(PDO::FETCH_ASSOC)['count'];
    $km = $db->query("SELECT SUM(CAST(kilometrage AS DECIMAL(10,2))) as total FROM trajets")->fetch(PDO::FETCH_ASSOC)['total'];
    
    $output['checks']['7_stats'] = [
        'nom' => 'Statistiques globales',
        'transporteurs' => $trans,
        'chauffeurs' => $chauff,
        'vehicules' => $vehic,
        'kilometrage_total' => $km
    ];
} catch (Exception $e) {
    $output['checks']['7_stats'] = [
        'nom' => 'Statistiques globales',
        'status' => '❌ ERREUR',
        'error' => $e->getMessage()
    ];
}

// ═══════════════════════════════════════════════════════════
// CHECK 8: Dernier trajet importé
// ═══════════════════════════════════════════════════════════

try {
    $result = $db->query("SELECT MAX(debut) as latest FROM trajets");
    $latest_timestamp = $result->fetch(PDO::FETCH_ASSOC)['latest'];
    
    $latest_formatted = $latest_timestamp ? date('d/m/Y H:i', (int)$latest_timestamp) : 'Aucun';
    
    $output['checks']['8_dernier_trajet'] = [
        'nom' => 'Dernier trajet importé',
        'timestamp' => $latest_timestamp,
        'date_formatee' => $latest_formatted
    ];
} catch (Exception $e) {
    $output['checks']['8_dernier_trajet'] = [
        'nom' => 'Dernier trajet importé',
        'status' => '❌ ERREUR',
        'error' => $e->getMessage()
    ];
}

// ═══════════════════════════════════════════════════════════
// RÉSUMÉ FINAL
// ═══════════════════════════════════════════════════════════

$output['resume'] = [
    'total_checks' => count($output['checks']),
    'tous_ok' => !isset($output['checks']['4_trajets_count']['error']) && 
                 ($output['checks']['4_trajets_count']['total_lignes'] ?? 0) > 0 
                 ? '✅ OUI - Les données sont en BDD!' 
                 : '❌ NON - La BDD est vide!'
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>