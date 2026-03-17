<?php
set_time_limit(600);
header('Content-Type: text/html; charset=utf-8');

require_once("getitemid.php");

// ========================================
// 🔐 LOGIN
// ========================================

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

// ========================================
// 🔍 GET ALL GROUPS - REAL DATA FROM API
// ========================================

function getAllGroups($sid){
	$curl = curl_init();
	// Search for ALL groups (avl_unit_group type)
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit_group","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":0}&sid='.$sid;
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if ($err) {
		return array('error' => $err, 'items' => array());
	}

	$data = json_decode($response, true);
	return $data;
}

// ========================================
// 🚗 GET VEHICLES FOR A GROUP
// ========================================

function getVehiclesForGroup($groupId, $sid){
	$curl = curl_init();
	// Get units/vehicles in a specific group
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_lgroup","propValueMask":"'.$groupId.'","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":0}&sid='.$sid;
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if ($err) {
		return array('error' => $err, 'items' => array());
	}

	$data = json_decode($response, true);
	return $data;
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Wialon - Real Data from API</title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { 
			font-family: 'Segoe UI', Arial; 
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
			padding: 20px;
		}
		.container { 
			max-width: 1200px; 
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
		.group-box {
			background: #f5f7fa;
			border: 2px solid #e0e6ed;
			padding: 20px;
			margin: 20px 0;
			border-radius: 8px;
			transition: all 0.3s;
		}
		.group-box:hover {
			border-color: #667eea;
			box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
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
			font-weight: bold;
		}
		.vehicles-count {
			background: #e8f4f8;
			color: #0066cc;
			padding: 8px 12px;
			border-radius: 5px;
			font-weight: bold;
		}
		.vehicle-list {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
			gap: 10px;
			margin-top: 15px;
		}
		.vehicle-item {
			background: white;
			border: 1px solid #ddd;
			padding: 12px;
			border-radius: 5px;
			font-size: 13px;
		}
		.vehicle-item strong {
			color: #667eea;
		}
		.loading {
			text-align: center;
			padding: 20px;
			color: #3498db;
			font-weight: bold;
		}
		.error-box {
			background: #fee;
			border: 2px solid #e74c3c;
			color: #c0392b;
			padding: 15px;
			border-radius: 5px;
			margin: 10px 0;
		}
	</style>
</head>
<body>

<div class="container">
	<h1>🔄 Wialon - Real Data from API</h1>
	<p><strong>Démarrage:</strong> <span style="color: #667eea;"><?php echo date('d/m/Y H:i:s'); ?></span></p>

<?php

// Login
echo '<div class="log info">🔐 Connexion à Wialon...</div>';
$sid = loginWialon();

if (!$sid) {
	echo '<div class="log error">❌ Erreur: Impossible de se connecter à Wialon</div>';
	exit;
}

echo '<div class="log success">✅ Connecté! Session ID: ' . substr($sid, 0, 20) . '...</div>';

// Get all groups
echo '<div class="log info">📋 Récupération des groupes...</div>';
$groups_response = getAllGroups($sid);

if (isset($groups_response['error'])) {
	echo '<div class="log error">❌ Erreur: ' . $groups_response['error'] . '</div>';
	exit;
}

$groups = $groups_response['items'] ?? array();
$total_groups = count($groups);

echo '<div class="log success">✅ ' . $total_groups . ' groupes trouvés</div>';
echo '<hr style="margin: 20px 0;">';

// Display each group
$group_count = 0;
$total_vehicles = 0;

foreach ($groups as $group) {
	$group_id = $group['id'] ?? 'N/A';
	$group_name = $group['nm'] ?? 'Sans nom';
	
	echo '<div class="group-box">';
	echo '<div class="group-header">';
	echo '<div class="group-name">🚐 ' . htmlspecialchars($group_name) . '</div>';
	echo '<div class="group-id">ID: ' . htmlspecialchars($group_id) . '</div>';
	echo '</div>';
	
	// Get vehicles for this group
	echo '<div class="log info" style="margin-bottom: 15px;">Récupération des véhicules...</div>';
	$vehicles_response = getVehiclesForGroup($group_id, $sid);
	
	$vehicles = $vehicles_response['items'] ?? array();
	$vehicle_count = count($vehicles);
	$total_vehicles += $vehicle_count;
	
	if ($vehicle_count > 0) {
		echo '<div class="vehicles-count">🚗 ' . $vehicle_count . ' véhicule(s)</div>';
		echo '<div class="vehicle-list">';
		
		foreach ($vehicles as $vehicle) {
			$vehicle_id = $vehicle['id'] ?? 'N/A';
			$vehicle_name = $vehicle['nm'] ?? 'Sans nom';
			$vehicle_type = $vehicle['cls'] ?? 'Unknown';
			
			echo '<div class="vehicle-item">';
			echo '<strong>🚙 ' . htmlspecialchars($vehicle_name) . '</strong><br>';
			echo 'ID: ' . htmlspecialchars($vehicle_id) . '<br>';
			echo 'Type: ' . htmlspecialchars($vehicle_type);
			echo '</div>';
		}
		
		echo '</div>';
	} else {
		echo '<div class="log error">⚠️ Aucun véhicule trouvé pour ce groupe</div>';
	}
	
	echo '</div>';
	$group_count++;
}

echo '<hr style="margin: 20px 0;">';
echo '<div style="background: #d5f4e6; border: 2px solid #27ae60; padding: 15px; border-radius: 5px;">';
echo '<p>✅ <strong>Import terminé!</strong></p>';
echo '<p>📊 Groupes trouvés: <strong>' . $group_count . '</strong></p>';
echo '<p>🚗 Véhicules totaux: <strong style="font-size: 1.3em;">' . $total_vehicles . '</strong></p>';
echo '</div>';

echo '<p style="margin-top: 20px;"><strong>Fin:</strong> <span style="color: #e74c3c;">' . date('d/m/Y H:i:s') . '</span></p>';

?>

</div>

</body>
</html>