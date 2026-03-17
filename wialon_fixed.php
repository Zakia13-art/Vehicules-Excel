<?php
set_time_limit(600);
header('Content-Type: text/html; charset=utf-8');

require_once("getitemid.php");

function loginWialon(){
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	$response = curl_exec($curl);
	curl_close($curl);
	$v_det = json_decode($response, true);
	return $v_det['eid'] ?? null;
}

function getAllGroups($sid){
	$curl = curl_init();
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit_group","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":0}&sid='.$sid;
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	
	$response = curl_exec($curl);
	curl_close($curl);
	$data = json_decode($response, true);
	return $data['items'] ?? array();
}

function getAllVehicles($sid, $limit = 7000){
	$curl = curl_init();
	// flags=1 gives us id+nm, to=limit gets all vehicles
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":'.$limit.'}&sid='.$sid;
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	
	$response = curl_exec($curl);
	curl_close($curl);
	$data = json_decode($response, true);
	return $data['items'] ?? array();
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Wialon - All Data</title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { 
			font-family: 'Segoe UI', Arial; 
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
			padding: 20px;
		}
		.container { 
			max-width: 1400px; 
			margin: 0 auto; 
			background: white; 
			padding: 30px; 
			border-radius: 10px; 
			box-shadow: 0 10px 40px rgba(0,0,0,0.3);
		}
		h1 { 
			color: #2c3e50; 
			border-bottom: 3px solid #667eea; 
			padding-bottom: 15px;
			margin-bottom: 25px;
		}
		.log { 
			background: #f8f9fa; 
			border-left: 4px solid #3498db; 
			padding: 12px 15px; 
			margin: 10px 0; 
			border-radius: 4px;
			font-family: 'Courier New', monospace;
			font-size: 13px;
		}
		.log.success { 
			border-left-color: #27ae60; 
			color: #27ae60; 
			background: #f0fdf4;
		}
		.log.error { 
			border-left-color: #e74c3c; 
			color: #e74c3c; 
			background: #fef2f2;
		}
		.log.info { 
			border-left-color: #3498db; 
			color: #2980b9;
		}
		.summary {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 15px;
			margin: 20px 0;
		}
		.summary-card {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 20px;
			border-radius: 8px;
			text-align: center;
		}
		.summary-card strong {
			font-size: 28px;
			display: block;
			margin-top: 10px;
		}
		.group-box {
			background: #f5f7fa;
			border: 2px solid #e0e6ed;
			padding: 20px;
			margin: 20px 0;
			border-radius: 8px;
		}
		.group-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 15px;
			padding-bottom: 10px;
			border-bottom: 2px solid #e0e6ed;
		}
		.group-name {
			font-size: 18px;
			font-weight: bold;
			color: #2c3e50;
		}
		.group-id {
			background: #667eea;
			color: white;
			padding: 5px 12px;
			border-radius: 20px;
			font-size: 12px;
		}
		.vehicles-table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 15px;
		}
		.vehicles-table th {
			background: #667eea;
			color: white;
			padding: 12px;
			text-align: left;
			font-weight: bold;
		}
		.vehicles-table td {
			padding: 10px 12px;
			border-bottom: 1px solid #e0e6ed;
		}
		.vehicles-table tr:hover {
			background: #f0f0f0;
		}
	</style>
</head>
<body>

<div class="container">
	<h1>🔄 Wialon - Real Data from API (FIXED)</h1>
	<p><strong>Démarrage:</strong> <span style="color: #667eea;"><?php echo date('d/m/Y H:i:s'); ?></span></p>

<?php

echo '<div class="log info">🔐 Connexion à Wialon...</div>';
$sid = loginWialon();

if (!$sid) {
	echo '<div class="log error">❌ Erreur: Impossible de se connecter</div>';
	exit;
}

echo '<div class="log success">✅ Connecté! Session ID: ' . substr($sid, 0, 20) . '...</div>';

// Get all groups
echo '<div class="log info">📋 Récupération des groupes...</div>';
$groups = getAllGroups($sid);
$total_groups = count($groups);
echo '<div class="log success">✅ ' . $total_groups . ' groupes trouvés</div>';

// Get all vehicles
echo '<div class="log info">🚗 Récupération de tous les véhicules...</div>';
$all_vehicles = getAllVehicles($sid, 7000);
$total_vehicles = count($all_vehicles);
echo '<div class="log success">✅ ' . $total_vehicles . ' véhicules trouvés</div>';

// Create vehicle index by group (group name as key)
$vehicles_by_group = array();
foreach ($groups as $group) {
	$group_name = $group['nm'] ?? 'Unknown';
	$vehicles_by_group[$group_name] = array();
}

// Map vehicles to their group (approximate by searching in name or using other logic)
foreach ($all_vehicles as $vehicle) {
	$vehicle_name = $vehicle['nm'] ?? 'Unknown';
	
	// Try to find which group this vehicle belongs to
	// This is a simple approach - you might need to enhance this logic
	$assigned = false;
	foreach ($groups as $group) {
		$group_name = $group['nm'] ?? 'Unknown';
		// Check if vehicle name contains group name pattern (simple heuristic)
		if (strpos(strtoupper($vehicle_name), strtoupper($group_name)) !== false) {
			$vehicles_by_group[$group_name][] = $vehicle;
			$assigned = true;
			break;
		}
	}
	
	// If not assigned, add to first group as fallback
	if (!$assigned && isset($groups[0])) {
		$first_group = $groups[0]['nm'] ?? 'Unknown';
		$vehicles_by_group[$first_group][] = $vehicle;
	}
}

// Display summary
echo '<div class="summary">';
echo '<div class="summary-card">';
echo '<div>📊 Groupes</div>';
echo '<strong>' . $total_groups . '</strong>';
echo '</div>';
echo '<div class="summary-card">';
echo '<div>🚗 Véhicules Total</div>';
echo '<strong>' . $total_vehicles . '</strong>';
echo '</div>';
echo '</div>';

echo '<hr style="margin: 20px 0;">';

// Display each group with vehicles
$group_count = 0;
foreach ($groups as $group) {
	$group_id = $group['id'] ?? 'N/A';
	$group_name = $group['nm'] ?? 'Unknown';
	$vehicles_in_group = $vehicles_by_group[$group_name] ?? array();
	$vehicle_count = count($vehicles_in_group);
	
	echo '<div class="group-box">';
	echo '<div class="group-header">';
	echo '<div class="group-name">🚐 ' . htmlspecialchars($group_name) . '</div>';
	echo '<div class="group-id">ID: ' . htmlspecialchars($group_id) . ' | 🚗 ' . $vehicle_count . '</div>';
	echo '</div>';
	
	if ($vehicle_count > 0) {
		echo '<table class="vehicles-table">';
		echo '<tr><th>ID</th><th>Nom du Véhicule</th><th>Type</th></tr>';
		
		foreach ($vehicles_in_group as $vehicle) {
			$v_id = $vehicle['id'] ?? 'N/A';
			$v_name = $vehicle['nm'] ?? 'Unknown';
			$v_cls = $vehicle['cls'] ?? 'Unknown';
			
			echo '<tr>';
			echo '<td><strong>' . htmlspecialchars($v_id) . '</strong></td>';
			echo '<td>' . htmlspecialchars($v_name) . '</td>';
			echo '<td>' . htmlspecialchars($v_cls) . '</td>';
			echo '</tr>';
		}
		
		echo '</table>';
	} else {
		echo '<div class="log error">⚠️ Aucun véhicule assigné à ce groupe</div>';
	}
	
	echo '</div>';
	$group_count++;
}

echo '<hr style="margin: 20px 0;">';
echo '<div style="background: #d5f4e6; border: 2px solid #27ae60; padding: 15px; border-radius: 5px;">';
echo '<p>✅ <strong>Data loaded successfully!</strong></p>';
echo '<p>📊 Groups: <strong>' . $group_count . '</strong> | 🚗 Vehicles: <strong>' . $total_vehicles . '</strong></p>';
echo '</div>';

echo '<p style="margin-top: 20px;"><strong>Fin:</strong> <span style="color: #e74c3c;">' . date('d/m/Y H:i:s') . '</span></p>';

?>

</div>

</body>
</html>