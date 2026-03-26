<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>TEST API BOUTCHRAFINE</h1>";

// Test 1: Fichier existe?
echo "<h2>Test 1: Fichiers existent?</h2>";
echo "api_boutchrafine.php: " . (file_exists(__DIR__ . '/api_boutchrafine.php') ? "✅ OUI" : "❌ NON") . "<br>";
echo "getitemid.php: " . (file_exists(__DIR__ . '/getitemid.php') ? "✅ OUI" : "❌ NON") . "<br>";
echo "db.php: " . (file_exists(__DIR__ . '/db.php') ? "✅ OUI" : "❌ NON") . "<br>";
echo "config.php: " . (file_exists(__DIR__ . '/config.php') ? "✅ OUI" : "❌ NON") . "<br>";

// Test 2: require api_boutchrafine
echo "<h2>Test 2: Require api_boutchrafine.php</h2>";
try {
    require_once "api_boutchrafine.php";
    echo "✅ api_boutchrafine.php chargé avec succès!<br>";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "❌ Erreur Fatal: " . $e->getMessage() . "<br>";
    echo "Dans: " . $e->getFile() . " line " . $e->getLine() . "<br>";
}

echo "<h2>✅ FIN DU TEST</h2>";
?>
