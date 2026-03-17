<?php
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Vérifier Task Scheduler</title>
	<style>
		body { font-family: Arial; background: #f5f5f5; padding: 40px; }
		.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; }
		.section { background: #f8f9fa; border-left: 5px solid #3498db; padding: 20px; margin: 20px 0; border-radius: 5px; }
		.success { border-left-color: #27ae60; background: #eafaf1; }
		.error { border-left-color: #e74c3c; background: #fadbd8; }
		.warning { border-left-color: #f39c12; background: #fef5e7; }
		.code { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: monospace; overflow-x: auto; margin: 10px 0; }
		button { background: #3498db; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; margin: 10px 5px 10px 0; }
		button:hover { background: #2980b9; }
		.success button { background: #27ae60; }
		.success button:hover { background: #229954; }
		ul { margin: 15px 0; }
		li { margin: 10px 0; }
	</style>
</head>
<body>

<div class="container">
	<h1>🔍 Vérifier la tâche Task Scheduler</h1>

	<?php
	
	// Check if batch file exists
	$batch_file = 'C:\xampp\htdocs\vehicules\import_trajets.bat';
	$batch_exists = file_exists($batch_file);
	
	echo '<div class="section' . ($batch_exists ? ' success' : ' error') . '">';
	echo '<h2>📄 Étape 1: Fichier import_trajets.bat</h2>';
	
	if ($batch_exists) {
		echo '<p style="color: #27ae60;"><strong>✅ TROUVÉ!</strong></p>';
		echo '<p>Fichier: <code>' . htmlspecialchars($batch_file) . '</code></p>';
		echo '<p>Le fichier .bat est bien créé.</p>';
	} else {
		echo '<p style="color: #e74c3c;"><strong>❌ NON TROUVÉ!</strong></p>';
		echo '<p>Fichier attendu: <code>' . htmlspecialchars($batch_file) . '</code></p>';
		echo '<p style="margin-top: 15px;"><strong>À faire:</strong></p>';
		echo '<ol>';
		echo '<li>Ouvrir Notepad</li>';
		echo '<li>Coller ce contenu:';
		echo '<div class="code">@echo off' . "\n" . 'C:\xampp\php\php.exe C:\xampp\htdocs\vehicules\lesgets.php >> C:\xampp\htdocs\vehicules\logs\cron.log 2>&1</div>';
		echo '</li>';
		echo '<li>Fichier → Enregistrer sous</li>';
		echo '<li>Nom: <code>import_trajets.bat</code> (PAS .txt!)</li>';
		echo '<li>Type fichier: <strong>Tous les fichiers</strong></li>';
		echo '<li>Localisation: <code>C:\xampp\htdocs\vehicules</code></li>';
		echo '<li>Enregistrer</li>';
		echo '</ol>';
		echo '<button onclick="location.reload()">🔄 Vérifier à nouveau</button>';
		echo '</div>';
		exit;
	}
	
	echo '</div>';
	
	// Check if log directory exists
	$log_dir = 'C:\xampp\htdocs\vehicules\logs';
	$log_dir_exists = is_dir($log_dir);
	
	echo '<div class="section' . ($log_dir_exists ? ' success' : ' warning') . '">';
	echo '<h2>📁 Étape 2: Dossier logs</h2>';
	
	if ($log_dir_exists) {
		echo '<p style="color: #27ae60;"><strong>✅ EXISTE!</strong></p>';
		echo '<p>Dossier: <code>' . htmlspecialchars($log_dir) . '</code></p>';
		
		// Check for log file
		$log_file = $log_dir . '\cron.log';
		if (file_exists($log_file)) {
			echo '<p style="color: #27ae60;"><strong>📄 Fichier log trouvé!</strong></p>';
			
			// Show last lines of log
			$lines = file($log_file);
			if (!empty($lines)) {
				echo '<p><strong>Dernières entrées du log:</strong></p>';
				echo '<div class="code">';
				$last_lines = array_slice($lines, -5);
				foreach ($last_lines as $line) {
					echo htmlspecialchars(trim($line)) . "\n";
				}
				echo '</div>';
			}
		} else {
			echo '<p style="color: #f39c12;">ℹ️ Pas encore de fichier log (sera créé au premier import)</p>';
		}
	} else {
		echo '<p style="color: #f39c12;"><strong>⚠️ DOSSIER N\'EXISTE PAS</strong></p>';
		echo '<p>Le dossier sera créé automatiquement lors du premier import.</p>';
	}
	
	echo '</div>';
	
	// Instructions for Task Scheduler
	echo '<div class="section warning">';
	echo '<h2>⏱️ Étape 3: Créer la tâche Task Scheduler</h2>';
	echo '<p><strong>Comment vérifier si la tâche existe?</strong></p>';
	echo '<ol>';
	echo '<li>Appuyer sur <strong>Win+R</strong></li>';
	echo '<li>Taper: <code>taskschd.msc</code></li>';
	echo '<li>Appuyer sur <strong>Enter</strong></li>';
	echo '<li>Dans l\'arborescence à gauche, ouvrir: <strong>Bibliothèque du Planificateur de tâches</strong></li>';
	echo '<li>Chercher une tâche nommée: <strong>"Import Trajets Wialon"</strong></li>';
	echo '</ol>';
	
	echo '<p style="margin-top: 20px;"><strong>Si la tâche n\'existe PAS:</strong></p>';
	echo '<ol>';
	echo '<li>Clic droit dans la fenêtre → <strong>Créer une tâche basique</strong></li>';
	echo '<li><strong>Nom:</strong> Import Trajets Wialon</li>';
	echo '<li><strong>Onglet "Déclencheurs":</strong> Cliquer "Nouveau"</li>';
	echo '<li>Choisir: <strong>Quotidien</strong> à <strong>02:00:00</strong></li>';
	echo '<li><strong>Onglet "Actions":</strong> Cliquer "Nouveau"</li>';
	echo '<li><strong>Programme:</strong> <code>C:\xampp\htdocs\vehicules\import_trajets.bat</code></li>';
	echo '<li>Cliquer <strong>OK</strong></li>';
	echo '</ol>';
	
	echo '</div>';
	
	// Test section
	echo '<div class="section success">';
	echo '<h2>🧪 Étape 4: Tester manuellement</h2>';
	echo '<p>Vous pouvez tester le script .bat directement:</p>';
	echo '<ol>';
	echo '<li>Double-cliquer sur <code>import_trajets.bat</code></li>';
	echo '<li>Une fenêtre noire apparaît quelques secondes</li>';
	echo '<li>Attendre qu\'elle se ferme</li>';
	echo '<li>Rafraîchir cette page (appuyer F5)</li>';
	echo '<li>Si un fichier log apparaît ci-dessus = ✅ ÇA MARCHE!</li>';
	echo '</ol>';
	echo '<button onclick="location.reload()" style="background: #27ae60;">🔄 Rafraîchir cette page</button>';
	echo '</div>';
	
	// Final status
	echo '<div class="section success">';
	echo '<h2>✅ Résumé</h2>';
	echo '<p>Une fois que vous avez:</p>';
	echo '<ul>';
	echo '<li>✅ Créé <code>import_trajets.bat</code></li>';
	echo '<li>✅ Testé en le double-cliquant</li>';
	echo '<li>✅ Créé la tâche dans Task Scheduler</li>';
	echo '</ul>';
	echo '<p style="margin-top: 20px;"><strong>Alors chaque jour à 2h00:</strong></p>';
	echo '<ul>';
	echo '<li>✅ Les trajets seront importés automatiquement</li>';
	echo '<li>✅ Les données seront stockées en base</li>';
	echo '<li>✅ Elles s\'afficheront sur check_trajets.php</li>';
	echo '</ul>';
	echo '</div>';
	
	?>

</div>

</body>
</html>