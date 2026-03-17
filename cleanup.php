<?php
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
		.section { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 5px solid #e74c3c; }
		.success { border-left-color: #27ae60; background: #eafaf1; color: #27ae60; }
		.info { border-left-color: #3498db; background: #eaf2f8; color: #3498db; }
		button { background: #e74c3c; color: white; padding: 12px 24px; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; margin: 10px 5px 0 0; }
		button:hover { background: #c0392b; }
		.btn-next { background: #27ae60; }
		.btn-next:hover { background: #229954; }
		code { background: #2c3e50; color: #ecf0f1; padding: 2px 6px; border-radius: 3px; }
		.stats { background: #ecf0f1; padding: 15px; margin: 15px 0; border-radius: 5px; }
		.stats strong { color: #2c3e50; font-size: 1.2em; }
	</style>
</head>
<body>

<div class="container">
	<h1>🧹 Nettoyage & Réimportation</h1>

<?php

$action = $_GET['action'] ?? '';

if ($action === 'delete') {
	// STEP 1: Delete all trajets
	echo '<div class="section success">';
	echo '<h2>✅ Étape 1: Suppression des trajets</h2>';
	
	try {
		$db = Cnx();
		$db->query("TRUNCATE TABLE trajets");
		
		$result = $db->query("SELECT COUNT(*) as count FROM trajets");
		$row = $result->fetch(PDO::FETCH_ASSOC);
		$count = $row['count'];
		
		echo '<div class="stats">';
		echo '<p>✅ <strong>Tous les trajets ont été supprimés!</strong></p>';
		echo '<p>Trajets en base maintenant: <strong>' . $count . '</strong></p>';
		echo '</div>';
		
		echo '<p>Vous pouvez maintenant importer à nouveau les données avec les dates correctes.</p>';
		
		echo '<p><a href="lesgets.php" style="display: inline-block; background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">▶️ Aller à l\'import (lesgets.php)</a></p>';
		
	} catch (Exception $e) {
		echo '<p style="color: #e74c3c;"><strong>Erreur:</strong> ' . $e->getMessage() . '</p>';
	}
	
	echo '</div>';
	
} else {
	// STEP 0: Show current data
	echo '<div class="section info">';
	echo '<h2>ℹ️ État actuel</h2>';
	
	try {
		$db = Cnx();
		$result = $db->query("SELECT COUNT(*) as count FROM trajets");
		$row = $result->fetch(PDO::FETCH_ASSOC);
		$count = $row['count'];
		
		echo '<div class="stats">';
		echo '<p>Trajets en base de données: <strong>' . $count . '</strong></p>';
		echo '</div>';
		
		if ($count > 0) {
			echo '<p style="color: #e74c3c;"><strong>⚠️ Important:</strong> Ces trajets ont probablement des dates incorrectes (01/01/1970).</p>';
			echo '<p>Vous devez les supprimer et réimporter avec le code corrigé.</p>';
			
			echo '<p><strong>Êtes-vous sûr?</strong></p>';
			echo '<button onclick="location.href=\'?action=delete\'">🗑️ OUI - Supprimer tous les trajets</button>';
			echo '<button onclick="location.href=\'check_trajets.php\'" style="background: #95a5a6;">❌ Non, annuler</button>';
		} else {
			echo '<p style="color: #27ae60;"><strong>✅ La base est vide!</strong></p>';
			echo '<p>Vous pouvez maintenant importer les données avec le code corrigé.</p>';
			echo '<p><a href="lesgets.php" style="display: inline-block; background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">▶️ Aller à l\'import (lesgets.php)</a></p>';
		}
		
	} catch (Exception $e) {
		echo '<p style="color: #e74c3c;"><strong>Erreur:</strong> ' . $e->getMessage() . '</p>';
	}
	
	echo '</div>';
}

?>

	<div class="section">
		<h3>📋 Processus:</h3>
		<ol>
			<li><strong style="color: #27ae60;">✅ Code db.php corrigé</strong> - Vous l'avez déjà fait!</li>
			<li><strong>Supprimer les trajets</strong> - Cette page</li>
			<li><strong>Réimporter</strong> - Cliquer le bouton "Import 7 jours"</li>
			<li><strong>Vérifier les dates</strong> - Aller sur check_trajets.php</li>
		</ol>
	</div>

</div>

</body>
</html>