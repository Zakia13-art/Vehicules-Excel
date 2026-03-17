<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once("db.php");

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial; background: #f5f5f5; padding: 40px; }
		.container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; }
		.step { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 5px solid #3498db; }
		.success { border-left-color: #27ae60; background: #eafaf1; }
		.error { border-left-color: #e74c3c; background: #fadbd8; }
		.info { border-left-color: #3498db; }
		pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
		code { font-family: monospace; }
		.stats { background: #ecf0f1; padding: 15px; margin: 15px 0; border-radius: 5px; font-size: 1.1em; }
		.stats strong { color: #e74c3c; font-size: 1.3em; }
	</style>
</head>
<body>

<div class="container">
	<h1>🗑️ Suppression COMPLÈTE des trajets</h1>

<?php

try {
	$db = Cnx();
	
	// STEP 1: Count before
	echo '<div class="step info">';
	echo '<h2>Step 1: État actuel</h2>';
	
	$result = $db->query("SELECT COUNT(*) as count FROM trajets");
	$before = $result->fetch(PDO::FETCH_ASSOC)['count'];
	
	echo '<div class="stats">Trajets en base: <strong>' . $before . '</strong></div>';
	
	if ($before > 0) {
		echo '<p>Affichage des trajets actuels:</p>';
		$result = $db->query("SELECT id, vehicule, debut, fin FROM trajets LIMIT 5");
		$rows = $result->fetchAll(PDO::FETCH_ASSOC);
		
		echo '<pre>';
		foreach ($rows as $row) {
			echo "ID: " . $row['id'] . " | Vehicle: " . $row['vehicule'] . " | Debut: " . $row['debut'] . " | Fin: " . $row['fin'] . "\n";
		}
		echo '</pre>';
	}
	
	echo '</div>';
	
	// STEP 2: Delete with DELETE (not TRUNCATE)
	echo '<div class="step info">';
	echo '<h2>Step 2: Suppression avec DELETE</h2>';
	
	$deleted = $db->exec("DELETE FROM trajets");
	
	echo '<p>Nombre de lignes supprimées: <strong>' . $deleted . '</strong></p>';
	
	echo '</div>';
	
	// STEP 3: Verify
	echo '<div class="step success">';
	echo '<h2>Step 3: Vérification</h2>';
	
	$result = $db->query("SELECT COUNT(*) as count FROM trajets");
	$after = $result->fetch(PDO::FETCH_ASSOC)['count'];
	
	echo '<div class="stats">Trajets en base maintenant: <strong>' . $after . '</strong></div>';
	
	if ($after == 0) {
		echo '<p style="color: #27ae60; font-weight: bold;">✅ SUCCÈS! Tous les trajets ont été supprimés!</p>';
		echo '<p style="margin-top: 20px;"><a href="lesgets.php" style="display: inline-block; background: #27ae60; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">▶️ Aller importer les trajectes (lesgets.php)</a></p>';
	} else {
		echo '<p style="color: #e74c3c; font-weight: bold;">❌ ERREUR! Encore ' . $after . ' trajets en base!</p>';
	}
	
	echo '</div>';
	
	// STEP 4: Show table structure
	echo '<div class="step info">';
	echo '<h2>Step 4: Structure de la table</h2>';
	
	$result = $db->query("DESCRIBE trajets");
	$cols = $result->fetchAll(PDO::FETCH_ASSOC);
	
	echo '<pre>';
	foreach ($cols as $col) {
		echo $col['Field'] . " (" . $col['Type'] . ")\n";
	}
	echo '</pre>';
	
	echo '</div>';
	
} catch (Exception $e) {
	echo '<div class="step error">';
	echo '<h2>❌ ERREUR</h2>';
	echo '<p>' . $e->getMessage() . '</p>';
	echo '<pre>' . $e->getTraceAsString() . '</pre>';
	echo '</div>';
}

?>

</div>

</body>
</html>