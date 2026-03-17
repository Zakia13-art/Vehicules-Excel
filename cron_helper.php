<?php
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>CRON Helper</title>
	<style>
		body { font-family: Arial; background: #f5f5f5; padding: 40px; }
		.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
		.info-box { background: #eaf2f8; border-left: 5px solid #3498db; padding: 15px; margin: 15px 0; border-radius: 5px; }
		.success { background: #eafaf1; border-left-color: #27ae60; }
		.warning { background: #fef5e7; border-left-color: #f39c12; }
		.code { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: monospace; overflow-x: auto; margin: 10px 0; }
		code { font-family: monospace; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
		button { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; margin: 10px 0; }
		button:hover { background: #2980b9; }
		table { width: 100%; border-collapse: collapse; margin: 15px 0; }
		th, td { border: 1px solid #bdc3c7; padding: 10px; text-align: left; }
		th { background: #3498db; color: white; }
		.copy-btn { background: #27ae60; font-size: 0.9rem; padding: 8px 15px; }
		.copy-btn:hover { background: #229954; }
	</style>
</head>
<body>

<div class="container">
	<h1>🔧 CRON Helper - Trouvez vos chemins</h1>

	<div class="info-box success">
		<h2>✅ Informations détectées automatiquement:</h2>
	</div>

	<?php
	
	// Detect PHP path
	$php_path = PHP_EXECUTABLE ?? '/usr/bin/php';
	$script_dir = dirname(__FILE__);
	$script_file = $script_dir . '/lesgets.php';
	$log_dir = $script_dir . '/logs';
	$log_file = $log_dir . '/cron.log';
	
	echo '<div class="info-box">';
	echo '<h3>📍 Chemin PHP:</h3>';
	echo '<div class="code">' . htmlspecialchars($php_path) . '</div>';
	
	if (file_exists($php_path)) {
		echo '<p style="color: #27ae60;">✅ PHP trouvé!</p>';
	} else {
		echo '<p style="color: #e74c3c;">⚠️ PHP non trouvé à ce chemin (serveur peut utiliser un autre chemin)</p>';
	}
	
	echo '</div>';
	
	echo '<div class="info-box">';
	echo '<h3>📁 Répertoire du script:</h3>';
	echo '<div class="code">' . htmlspecialchars($script_dir) . '</div>';
	echo '</div>';
	
	echo '<div class="info-box">';
	echo '<h3>📄 Chemin lesgets.php:</h3>';
	echo '<div class="code">' . htmlspecialchars($script_file) . '</div>';
	
	if (file_exists($script_file)) {
		echo '<p style="color: #27ae60;">✅ Fichier trouvé!</p>';
	} else {
		echo '<p style="color: #e74c3c;">❌ Fichier non trouvé!</p>';
	}
	
	echo '</div>';
	
	echo '<div class="info-box">';
	echo '<h3>📋 Chemin du log:</h3>';
	echo '<div class="code">' . htmlspecialchars($log_file) . '</div>';
	
	if (is_dir($log_dir)) {
		echo '<p style="color: #27ae60;">✅ Répertoire logs existe</p>';
	} else {
		echo '<p style="color: #f39c12;">⚠️ Le répertoire logs n\'existe pas (sera créé par lesgets.php)</p>';
	}
	
	echo '</div>';
	
	// Show the ready-made CRON lines
	echo '<div class="info-box success">';
	echo '<h2>🎯 Ligne CRON prête à copier:</h2>';
	echo '<p>Choisissez votre horaire préféré et copiez la ligne correspondante</p>';
	echo '</div>';
	
	$cron_lines = array(
		'2h du matin (recommandé)' => '0 2 * * * ' . $php_path . ' ' . $script_file . ' >> ' . $log_file . ' 2>&1',
		'Midi (12h00)' => '0 12 * * * ' . $php_path . ' ' . $script_file . ' >> ' . $log_file . ' 2>&1',
		'18h (6PM)' => '0 18 * * * ' . $php_path . ' ' . $script_file . ' >> ' . $log_file . ' 2>&1',
		'2h ET 14h (2x/jour)' => '0 2,14 * * * ' . $php_path . ' ' . $script_file . ' >> ' . $log_file . ' 2>&1',
		'Toutes les 6h' => '0 */6 * * * ' . $php_path . ' ' . $script_file . ' >> ' . $log_file . ' 2>&1',
		'Chaque heure' => '0 * * * * ' . $php_path . ' ' . $script_file . ' >> ' . $log_file . ' 2>&1',
	);
	
	foreach ($cron_lines as $label => $cron_line) {
		echo '<div class="info-box warning">';
		echo '<h3>' . htmlspecialchars($label) . '</h3>';
		echo '<div class="code">' . htmlspecialchars($cron_line) . '</div>';
		echo '<button class="copy-btn" onclick="copyToClipboard(this)">📋 Copier</button>';
		echo '</div>';
	}
	
	echo '<div class="info-box">';
	echo '<h2>📖 Instructions:</h2>';
	echo '<ol>';
	echo '<li>Copier la ligne CRON que vous préférez (bouton 📋 Copier)</li>';
	echo '<li>Ouvrir terminal SSH sur votre serveur</li>';
	echo '<li>Taper: <code>crontab -e</code></li>';
	echo '<li>Coller la ligne CRON</li>';
	echo '<li>Sauvegarder: <code>Ctrl+X</code>, puis <code>Y</code>, puis <code>Enter</code></li>';
	echo '<li>Vérifier: <code>crontab -l</code></li>';
	echo '</ol>';
	echo '</div>';
	
	echo '<div class="info-box warning">';
	echo '<h2>⚠️ Important:</h2>';
	echo '<ul>';
	echo '<li>Ces chemins sont spécifiques à VOTRE serveur</li>';
	echo '<li>Si lesgets.php n\'est pas trouvé, vérifier le chemin</li>';
	echo '<li>Vérifier les permissions: <code>chmod +x ' . htmlspecialchars($script_file) . '</code></li>';
	echo '</ul>';
	echo '</div>';
	
	?>

</div>

<script>
function copyToClipboard(button) {
	const text = button.previousElementSibling.textContent;
	navigator.clipboard.writeText(text).then(() => {
		button.textContent = '✅ Copié!';
		setTimeout(() => {
			button.textContent = '📋 Copier';
		}, 2000);
	});
}
</script>

</body>
</html>