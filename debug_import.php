<?php
// ACTIVER TOUS LES ERREURS
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>🔍 DEBUG - IMPORT BOUTCHRAFINE</h1>";
echo "<pre>";

// Test fichiers
echo "=== 1. FICHIERS ===\n";
echo "import_boutchrafine.php: " . (file_exists(__DIR__ . '/import_boutchrafine.php') ? "✅" : "❌") . "\n";
echo "api_boutchrafine.php: " . (file_exists(__DIR__ . '/api_boutchrafine.php') ? "✅" : "❌") . "\n";
echo "getitemid.php: " . (file_exists(__DIR__ . '/getitemid.php') ? "✅" : "❌") . "\n";
echo "db.php: " . (file_exists(__DIR__ . '/db.php') ? "✅" : "❌") . "\n";
echo "config.php: " . (file_exists(__DIR__ . '/config.php') ? "✅" : "❌") . "\n";

// Test require
echo "\n=== 2. REQUIRE API_BOUTCHRAFINE ===\n";
try {
    require_once "api_boutchrafine.php";
    echo "✅ api_boutchrafine.php chargé\n";
} catch (Throwable $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . " Ligne: " . $e->getLine() . "\n";
}

// Test fonction sid()
echo "\n=== 3. TEST FONCTION SID() ===\n";
try {
    $result = sid();
    echo "✅ sid() fonctionne\n";
} catch (Throwable $e) {
    echo "❌ ERREUR sid(): " . $e->getMessage() . "\n";
}

// Test connexion DB
echo "\n=== 4. TEST CONNEXION DB ===\n";
try {
    $pdo = getDB();
    echo "✅ Connexion DB réussie\n";
    echo "Database: " . DB_NAME . "\n";
} catch (Throwable $e) {
    echo "❌ ERREUR DB: " . $e->getMessage() . "\n";
}

// Test tables existent
echo "\n=== 5. TABLES BOUTCHRAFINE ===\n";
try {
    $pdo = getDB();
    $stmt = $pdo->query("SHOW TABLES LIKE 'boutchrafine%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) > 0) {
        echo "✅ Tables trouvées:\n";
        foreach ($tables as $t) {
            echo "   - $t\n";
        }
    } else {
        echo "❌ Aucune table boutchrafine trouvée!\n";
    }
} catch (Throwable $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// Test import réel
echo "\n=== 6. TEST IMPORT RÉEL ===\n";
try {
    $sid = sid();
    if ($sid) {
        echo "✅ SID obtenu: " . substr($sid, 0, 20) . "...\n";

        // Test un petit rapport
        $tables = execReportBoutchrafine(TEMPLATE_KILOMETRAGE, $sid);
        if ($tables) {
            echo "✅ Rapport exécuté avec succès!\n";
        } else {
            echo "⚠️ Rapport retourne NULL (pas de données?)\n";
        }
    } else {
        echo "❌ SID est vide\n";
    }
} catch (Throwable $e) {
    echo "❌ ERREUR IMPORT: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . " Ligne: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== ✅ FIN DEBUG ===\n";
echo "</pre>";

echo "<hr>";
echo "<p><strong>Si tout est vert ✅:</strong> <a href='import_boutchrafine.php'>Clic ici pour le vrai import</a></p>";
?>
