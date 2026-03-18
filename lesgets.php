<?php
set_time_limit(600);
header('Content-Type: text/html; charset=utf-8');

require_once("getitemid.php");

// ========================================
// 🔐 LOGIN
// ========================================

function sid(){
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	$sid = "";
	if (!$err) {
		$v_det = json_decode($response, true);
		$sid = $v_det['eid'] ?? '';
	}
	return $sid;
}

function cleanRepport($sid){
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=report/cleanup_result&params={}&sid=".$sid,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	curl_exec($curl);
	curl_close($curl);
}

// ========================================
// 📊 GROUPS TO PROCESS
// ========================================

$tab_group = array(
	'BOUTCHRAFINE' => 19022033,
	'SOMATRIN' => 19596491,
	'MARATRANS' => 19631505,
	'G.T.C' => 19590737,
	'DOUKALI' => 19585587,
	'COTRAMAB' => 19585601,
	'CORYAD' => 19585581,
	'CONSMETA' => 19629962,
	'CHOUROUK' => 19630023,
	'CARRE' => 19643391,
	'STB' => 19585942,
	'FASTTRANS' => 19635796
);

// ✅ CREATE BACKUP DIRECTORY
@mkdir('backups', 0755, true);

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Import Wialon - 7 derniers jours</title>
	<style>
		body { font-family: Arial; background: #f5f5f5; margin: 20px; padding: 20px; }
		.container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
		.log { background: #f8f9fa; border-left: 4px solid #3498db; padding: 10px; margin: 8px 0; font-family: monospace; font-size: 13px; }
		.success { border-left-color: #27ae60; color: #27ae60; }
		.error { border-left-color: #e74c3c; color: #e74c3c; background: #fadbd8; }
		.info { border-left-color: #3498db; color: #3498db; }
		.summary { background: #d5f4e6; border: 2px solid #27ae60; padding: 15px; margin: 20px 0; border-radius: 5px; }
		table { width: 100%; border-collapse: collapse; margin: 15px 0; }
		th, td { border: 1px solid #bdc3c7; padding: 10px; text-align: left; }
		th { background: #3498db; color: white; }
		.backup-info { background: #fff3cd; border-left: 4px solid #ff9800; padding: 10px; margin: 10px 0; }
	</style>
</head>
<body>

<div class="container">
	<h1>🔄 Import Wialon - 7 derniers jours</h1>
	<p><strong>Démarrage:</strong> <span style="color: #3498db;"><?php echo date('d/m/Y H:i:s'); ?></span></p>
	<div class="backup-info">💾 <strong>Auto-backup activée:</strong> Tous les trajets seront sauvegardés dans <code>backups/trajets_backup_<?php echo date('Y-m-d'); ?>.json</code></div>

<?php

// Get session
echo '<div class="log info">Connexion à Wialon...</div>';
$sid = sid();

if (!$sid) {
	echo '<div class="log error">❌ Erreur: Impossible de créer une session Wialon</div>';
	exit;
}

echo '<div class="log success">✅ Session créée: ' . substr($sid, 0, 20) . '...</div>';

// ========================================
// PROCESS EACH GROUP
// ========================================

$total_tables = 0;
$total_trajets = 0;
$stats = array();

foreach($tab_group as $nom => $groupe){
	echo '<div class="log info">Traitement: <strong>' . $nom . '</strong></div>';
	
	cleanRepport($sid);
	sleep(1);
	
	$report_index = execRep($groupe, $sid);
	
	if ($report_index === null) {
		echo '<div class="log">⚠️ Pas de données pour ' . $nom . '</div>';
		$stats[$nom] = 0;
		continue;
	}
	
	$trajets_before = $trajectcount;
	
	$i = 0;
	foreach($report_index as $value){
		selectRes($nom, $i, $value, $sid);
		$i++;
		$total_tables++;
	}
	
	$trajets_inserted = $trajectcount - $trajets_before;
	$stats[$nom] = $trajets_inserted;
	
	echo '<div class="log success">✅ ' . $nom . ': ' . $trajets_inserted . ' trajets insérés (' . $i . ' tables)</div>';
	$total_trajets += $trajets_inserted;
}

// ========================================
// SUMMARY
// ========================================

echo '<h2>📊 Résumé</h2>';

echo '<table>';
echo '<tr><th>Groupe</th><th>Trajets Insérés</th></tr>';

foreach($stats as $groupe => $count) {
	$color = $count > 0 ? '#27ae60' : '#95a5a6';
	echo '<tr><td>' . $groupe . '</td><td style="color: ' . $color . '; font-weight: bold;">' . $count . '</td></tr>';
}

echo '</table>';

echo '<div class="summary">';
echo '<p>✅ <strong>Importation terminée!</strong></p>';
echo '<p>📋 Tables traitées: <strong>' . $total_tables . '</strong></p>';
echo '<p>🚗 Trajets insérés: <strong style="font-size: 1.3em;">' . $total_trajets . '</strong></p>';
if ($total_trajets > 0) {
	echo '<p>💾 <strong>Backup sauvegardé:</strong> backups/trajets_backup_' . date('Y-m-d') . '.json</p>';
}
echo '</div>';

echo '<p><strong>Fin:</strong> <span style="color: #e74c3c;">' . date('d/m/Y H:i:s') . '</span></p>';
echo '<p><a href="check_trajets.php" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">📊 Voir les données</a></p>';

?>

</div>

</body>
</html>