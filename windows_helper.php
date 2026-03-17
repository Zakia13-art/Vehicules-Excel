<?php
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Windows CRON Helper</title>
	<style>
		body { font-family: Arial; background: #f5f5f5; padding: 40px; }
		.container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
		.box { background: #f8f9fa; border-left: 5px solid #3498db; padding: 15px; margin: 15px 0; border-radius: 5px; }
		.success { background: #eafaf1; border-left-color: #27ae60; }
		.warning { background: #fef5e7; border-left-color: #f39c12; }
		.code { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: monospace; overflow-x: auto; margin: 10px 0; word-break: break-all; }
		code { font-family: monospace; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
		button { background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; margin: 10px 0; }
		button:hover { background: #229954; }
		.copy-icon { margin-right: 5px; }
		h2 { color: #34495e; }
		pre { white-space: pre-wrap; word-wrap: break-word; }
	</style>
</head>
<body>

<div class="container">
	<h1>🪟 Windows Task Scheduler Helper</h1>
	<p style="color: #7f8c8d;"><strong>Note:</strong> Vous êtes sur Windows. Cron n'existe pas. Utilisez Task Scheduler à la place!</p>

	<?php
	
	// Get current directory
	$current_dir = dirname(__FILE__);
	$lesgets_file = $current_dir . DIRECTORY_SEPARATOR . 'lesgets.php';
	
	// Check if lesgets.php exists
	$lesgets_exists = file_exists($lesgets_file);
	
	echo '<div class="box success">';
	echo '<h2>✅ Votre dossier projet:</h2>';
	echo '<div class="code">' . htmlspecialchars($current_dir) . '</div>';
	echo '</div>';
	
	echo '<div class="box ' . ($lesgets_exists ? 'success' : 'warning') . '">';
	echo '<h2>📄 Chemin lesgets.php:</h2>';
	echo '<div class="code">' . htmlspecialchars($lesgets_file) . '</div>';
	
	if ($lesgets_exists) {
		echo '<p style="color: #27ae60;">✅ lesgets.php TROUVÉ!</p>';
	} else {
		echo '<p style="color: #f39c12;">⚠️ lesgets.php NON TROUVÉ!</p>';
		echo '<p>Assurez-vous que lesgets.php est dans le même dossier que ce fichier.</p>';
	}
	
	echo '</div>';
	
	// Get possible PHP paths
	$possible_php_paths = array(
		'C:\xampp\php\php.exe',
		'C:\wamp\bin\php\php7.4.26\php.exe',
		'C:\wamp\bin\php\php8.0.0\php.exe',
		'C:\wamp\bin\php\php8.1.0\php.exe',
		'C:\Program Files\PHP\php.exe',
		'C:\Program Files (x86)\PHP\php.exe',
	);
	
	echo '<div class="box">';
	echo '<h2>📍 Chemins PHP possibles (essayer dans cet ordre):</h2>';
	
	$php_found = false;
	
	foreach ($possible_php_paths as $php_path) {
		$exists = file_exists($php_path);
		
		if ($exists) {
			$php_found = true;
			echo '<div class="box success">';
			echo '<p style="color: #27ae60;"><strong>✅ TROUVÉ:</strong></p>';
			echo '<div class="code">' . htmlspecialchars($php_path) . '</div>';
			echo '<button onclick="copyToClipboard(this)"><span class="copy-icon">📋</span>Copier</button>';
			echo '</div>';
		}
	}
	
	if (!$php_found) {
		echo '<p style="color: #e74c3c;"><strong>⚠️ Aucun PHP trouvé aux emplacements standards!</strong></p>';
		echo '<p>Vérifiez votre installation PHP (XAMPP, WAMP, etc)</p>';
		echo '<p>Cherchez le dossier contenant <code>php.exe</code></p>';
		
		echo '<h3>Chemins à essayer:</h3>';
		foreach ($possible_php_paths as $php_path) {
			echo '<div class="box warning">';
			echo '<div class="code">' . htmlspecialchars($php_path) . '</div>';
			echo '<button onclick="copyToClipboard(this)"><span class="copy-icon">📋</span>Copier</button>';
			echo '</div>';
		}
	}
	
	echo '</div>';
	
	// Generate batch file content
	echo '<div class="box warning">';
	echo '<h2>🎯 Contenu du fichier import_trajets.bat</h2>';
	echo '<p>Créer un fichier texte avec ce contenu (adapter les chemins!)</p>';
	
	// Use first PHP path found or default
	$php_to_use = 'C:\xampp\php\php.exe';
	if ($php_found) {
		foreach ($possible_php_paths as $php_path) {
			if (file_exists($php_path)) {
				$php_to_use = $php_path;
				break;
			}
		}
	}
	
	$batch_content = '@echo off' . "\n" . 
	                 $php_to_use . ' ' . $lesgets_file . ' >> ' . dirname($lesgets_file) . '\logs\cron.log 2>&1';
	
	echo '<div class="code">';
	echo '<pre>' . htmlspecialchars($batch_content) . '</pre>';
	echo '</div>';
	
	echo '<button onclick="copyToClipboard(document.querySelector(\'.code pre\'))"><span class="copy-icon">📋</span>Copier le contenu .bat</button>';
	echo '</div>';
	
	// Instructions
	echo '<div class="box">';
	echo '<h2>📋 Instructions pas à pas:</h2>';
	echo '<ol>';
	echo '<li><strong>Créer un fichier:</strong> Clic droit → Nouveau → Document texte</li>';
	echo '<li><strong>Nommer le fichier:</strong> <code>import_trajets.bat</code> (PAS .txt!)</li>';
	echo '<li><strong>Copier le contenu</strong> ci-dessus dans le fichier</li>';
	echo '<li><strong>Sauvegarder</strong> dans le dossier de votre projet</li>';
	echo '<li><strong>Double-cliquer</strong> pour tester (fenêtre noire apparaît)</li>';
	echo '<li><strong>Ouvrir Task Scheduler:</strong> Win+R → taskschd.msc</li>';
	echo '<li><strong>Créer tâche:</strong> Clic droit → Créer une tâche</li>';
	echo '<li><strong>Général:</strong> Nom = "Import Trajets Wialon"</li>';
	echo '<li><strong>Déclencheurs:</strong> Quotidien à 02:00</li>';
	echo '<li><strong>Actions:</strong> Démarrer le programme <code>import_trajets.bat</code></li>';
	echo '<li><strong>Tester:</strong> Clic droit sur la tâche → Exécuter</li>';
	echo '</ol>';
	echo '</div>';
	
	echo '<div class="box success">';
	echo '<h2>✅ Résultat attendu:</h2>';
	echo '<p>Chaque jour à 2h00 du matin:</p>';
	echo '<ul>';
	echo '<li>✅ Task Scheduler lance import_trajets.bat</li>';
	echo '<li>✅ PHP exécute lesgets.php</li>';
	echo '<li>✅ Les trajets Wialon sont importés</li>';
	echo '<li>✅ Données ajoutées à la base de données</li>';
	echo '<li>✅ Affichées sur check_trajets.php</li>';
	echo '</ul>';
	echo '</div>';
	
	?>

</div>

<script>
function copyToClipboard(element) {
	let text;
	
	if (element.tagName === 'BUTTON') {
		// If button clicked, get next element content
		text = element.nextElementSibling?.textContent || 
		       element.parentElement?.querySelector('code')?.textContent ||
		       element.parentElement?.querySelector('pre')?.textContent;
	} else if (element.tagName === 'PRE') {
		text = element.textContent;
	} else {
		text = element.textContent;
	}
	
	if (!text) return;
	
	// Copy to clipboard
	navigator.clipboard.writeText(text).then(() => {
		const btn = event.target.tagName === 'BUTTON' ? event.target : event.target.closest('button');
		if (btn) {
			const originalText = btn.innerHTML;
			btn.innerHTML = '<span class="copy-icon">✅</span>Copié!';
			setTimeout(() => {
				btn.innerHTML = originalText;
			}, 2000);
		}
	}).catch(err => {
		alert('Erreur: ' + err);
	});
}
</script>

</body>
</html>