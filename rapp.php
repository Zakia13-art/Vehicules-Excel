<?php
set_time_limit(1200);
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
	'BOUTCHRAFINE' => 19022033
);

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Import Wialon - Par plages historiques</title>
	<style>
		body { font-family: Arial; background: #f5f5f5; margin: 20px; padding: 20px; }
		.container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
		h2 { color: #34495e; margin-top: 20px; }
		.log { background: #f8f9fa; border-left: 4px solid #3498db; padding: 10px; margin: 8px 0; font-family: monospace; font-size: 13px; }
		.success { border-left-color: #27ae60; color: #27ae60; }
		.error { border-left-color: #e74c3c; color: #e74c3c; background: #fadbd8; }
		.info { border-left-color: #3498db; color: #3498db; }
		.plage-header { background: #ecf0f1; padding: 10px; margin: 15px 0 5px 0; border-radius: 4px; font-weight: bold; }
		.summary { background: #d5f4e6; border: 2px solid #27ae60; padding: 15px; margin: 20px 0; border-radius: 5px; }
		table { width: 100%; border-collapse: collapse; margin: 15px 0; }
		th, td { border: 1px solid #bdc3c7; padding: 10px; text-align: left; }
		th { background: #3498db; color: white; }
	</style>
</head>
<body>

<div class="container">
	<h1>📡 Import Wialon - Historique par plages</h1>
	<p><strong>Note:</strong> Import des 160 derniers jours par plages de 4 jours (BOUTCHRAFINE uniquement)</p>
	<p><strong>Démarrage:</strong> <span style="color: #3498db;"><?php echo date('d/m/Y H:i:s'); ?></span></p>

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
// PROCESS BY DATE RANGES
// ========================================

$i = 161;
$total_plages = 0;
$total_tables = 0;
$total_trajets = 0;

while($i > 1){
	$from1 = $i + 1;
	$to1 = $i - 4;
	$i = $i - 4;
	
	echo '<div class="plage-header">📅 Plage: du jour ' . $from1 . ' au ' . $to1 . '</div>';
	
	$trajets_plage_before = $trajectcount;
	$tables_plage = 0;
	
	foreach($tab_group as $nom => $groupe){
		cleanRepport($sid);
		sleep(1);
		
		$report_index = execRep($groupe, $sid, $from1, $to1);
		
		if ($report_index === null) {
			echo '<div class="log">⚠️ Pas de données pour ' . $nom . '</div>';
			continue;
		}
		
		$j = 0;
		foreach($report_index as $value){
			selectRes($nom, $j, $value, $sid);
			$j++;
			$tables_plage++;
			$total_tables++;
		}
		
		$trajets_plage = $trajectcount - $trajets_plage_before;
		echo '<div class="log success">✅ ' . $nom . ': ' . $trajets_plage . ' trajets (' . $tables_plage . ' tables)</div>';
		$total_trajets += $trajets_plage;
		$total_plages++;
	}
}

// ========================================
// SUMMARY
// ========================================

echo '<h2>📊 Résumé Final</h2>';

echo '<div class="summary">';
echo '<p>✅ <strong>Importation terminée!</strong></p>';
echo '<p>📋 Plages traitées: <strong>' . $total_plages . '</strong></p>';
echo '<p>📋 Tables traitées: <strong>' . $total_tables . '</strong></p>';
echo '<p>🚗 Trajets insérés: <strong style="font-size: 1.3em;">' . $total_trajets . '</strong></p>';
echo '</div>';

echo '<p><strong>Fin:</strong> <span style="color: #e74c3c;">' . date('d/m/Y H:i:s') . '</span></p>';
echo '<p><a href="check_trajets.php" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">📊 Voir les données</a></p>';

?>

</div>

</body>
</html>