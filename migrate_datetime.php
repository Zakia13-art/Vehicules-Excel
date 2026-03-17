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
		.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; }
		.step { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 5px solid #3498db; }
		.success { border-left-color: #27ae60; background: #eafaf1; }
		.error { border-left-color: #e74c3c; background: #fadbd8; }
		.warning { border-left-color: #f39c12; background: #fef5e7; }
		.code { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; margin: 10px 0; }
		code { font-family: monospace; }
		button { background: #27ae60; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; margin: 10px 0; }
		button:hover { background: #229954; }
		.stats { background: #ecf0f1; padding: 15px; margin: 15px 0; border-radius: 5px; font-size: 1.1em; }
		.stats strong { color: #2c3e50; font-size: 1.3em; }
	</style>
</head>
<body>

<div class="container">
	<h1>🔧 Migration: bigint → DATETIME</h1>

<?php

$action = $_GET['action'] ?? '';

if ($action === 'migrate') {
	// Do the migration
	echo '<div class="step success">';
	echo '<h2>🚀 Exécution de la migration</h2>';
	
	try {
		$db = Cnx();
		
		// Step 1: Backup structure
		echo '<p>Step 1: Affichage structure actuelle...</p>';
		$result = $db->query("DESCRIBE trajets");
		$cols = $result->fetchAll(PDO::FETCH_ASSOC);
		
		echo '<div class="code">';
		foreach ($cols as $col) {
			if ($col['Field'] === 'debut' || $col['Field'] === 'fin') {
				echo '<strong>' . $col['Field'] . ': ' . $col['Type'] . '</strong>' . "\n";
			}
		}
		echo '</div>';
		
		// Step 2: Modify columns
		echo '<p>Step 2: Modification des colonnes...</p>';
		
		// ALTER debut column
		$db->exec("ALTER TABLE trajets MODIFY COLUMN debut DATETIME");
		echo '<p style="color: #27ae60;">✅ Colonne <code>debut</code> modifiée en DATETIME</p>';
		
		// ALTER fin column
		$db->exec("ALTER TABLE trajets MODIFY COLUMN fin DATETIME");
		echo '<p style="color: #27ae60;">✅ Colonne <code>fin</code> modifiée en DATETIME</p>';
		
		// Step 3: Verify
		echo '<p>Step 3: Vérification...</p>';
		$result = $db->query("DESCRIBE trajets");
		$cols = $result->fetchAll(PDO::FETCH_ASSOC);
		
		echo '<div class="code">';
		foreach ($cols as $col) {
			if ($col['Field'] === 'debut' || $col['Field'] === 'fin') {
				echo '<strong>' . $col['Field'] . ': ' . $col['Type'] . '</strong>' . "\n";
			}
		}
		echo '</div>';
		
		echo '<p style="color: #27ae60; font-weight: bold;">✅ MIGRATION RÉUSSIE!</p>';
		
		echo '<p style="margin-top: 20px; padding: 15px; background: #d5f4e6; border-radius: 5px; border-left: 5px solid #27ae60;">';
		echo '<strong>Les colonnes sont maintenant en DATETIME!</strong><br>';
		echo 'Vous pouvez maintenant importer les trajets avec les bonnes dates.';
		echo '</p>';
		
		echo '<p style="margin-top: 20px;"><a href="lesgets.php" style="display: inline-block; background: #27ae60; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">▶️ Importer les trajets (lesgets.php)</a></p>';
		
	} catch (Exception $e) {
		echo '<p style="color: #e74c3c; font-weight: bold;">❌ ERREUR!</p>';
		echo '<div class="code">' . htmlspecialchars($e->getMessage()) . '</div>';
	}
	
	echo '</div>';
	
} else {
	// Show warning and confirm
	echo '<div class="step warning">';
	echo '<h2>⚠️ Important!</h2>';
	
	echo '<p><strong>Le problème:</strong></p>';
	echo '<ul>';
	echo '<li>Les colonnes <code>debut</code> et <code>fin</code> sont de type <code>bigint(20)</code></li>';
	echo '<li>Elles devraient être de type <code>DATETIME</code></li>';
	echo '<li>C\'est pourquoi les dates s\'affichent comme <code>2026</code> au lieu de dates complètes</li>';
	echo '</ul>';
	
	echo '<p><strong>La solution:</strong></p>';
	echo '<p>Modifier les colonnes de <code>bigint</code> à <code>DATETIME</code></p>';
	
	echo '<div class="code">';
	echo "ALTER TABLE trajets MODIFY COLUMN debut DATETIME;<br>";
	echo "ALTER TABLE trajets MODIFY COLUMN fin DATETIME;";
	echo '</div>';
	
	echo '<p><strong>Conséquences:</strong></p>';
	echo '<ul>';
	echo '<li>✅ Les dates s\'afficheront correctement</li>';
	echo '<li>✅ Les imports futurs stockeront les vraies dates</li>';
	echo '<li>❌ Les 9 anciens trajets seront perdus (mais ils ont de mauvaises dates de toute façon)</li>';
	echo '</ul>';
	
	echo '<p style="margin-top: 20px; padding: 15px; background: #fadbd8; border-radius: 5px;">';
	echo '<strong>⚠️ ATTENTION:</strong> Cette opération modifie la structure de la table!<br>';
	echo 'Assurez-vous d\'avoir une sauvegarde si nécessaire.';
	echo '</p>';
	
	echo '<p style="margin-top: 20px;">';
	echo '<button onclick="if(confirm(\'Êtes-vous sûr? Cette opération est irréversible!\')) location.href=\'?action=migrate\'">✅ OUI - Faire la migration</button>';
	echo '<button onclick="location.href=\'check_trajets.php\'" style="background: #95a5a6;">❌ Non, annuler</button>';
	echo '</p>';
	
	echo '</div>';
}

?>

</div>

</body>
</html>