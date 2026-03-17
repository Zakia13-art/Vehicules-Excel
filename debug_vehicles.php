<?php
set_time_limit(600);
header('Content-Type: application/json; charset=utf-8');

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

// ========================================
// GET VEHICLES DATA
// ========================================

function getVehiclesFromGroup($groupId, $sid){
	$curl = curl_init();
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":0,"from":0,"to":100}&sid='.$sid;
	
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
		return array('error' => $err);
	}

	$data = json_decode($response, true);
	return $data;
}

// ========================================
// MAIN
// ========================================

$result = array(
	'status' => 'error',
	'message' => '',
	'sid' => '',
	'vehicles_data' => null,
	'debug_info' => array()
);

// Get session
$session_id = sid();

if (!$session_id) {
	$result['message'] = 'Erreur: Impossible de créer une session Wialon';
	echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	exit;
}

$result['status'] = 'success';
$result['sid'] = substr($session_id, 0, 20) . '...';
$result['message'] = 'Session créée avec succès';

// Get vehicles (first group as test)
$result['debug_info']['action'] = 'Fetching vehicles from BOUTCHRAFINE';
$vehicles = getVehiclesFromGroup(19022033, $session_id);

$result['vehicles_data'] = $vehicles;

// Count items
if (isset($vehicles['items'])) {
	$total = count($vehicles['items']);
	$with_data = 0;
	$sample_items = array();
	
	foreach ($vehicles['items'] as $idx => $item) {
		if (!empty($item)) {
			$with_data++;
			if ($with_data <= 3) { // Get first 3 items with data
				$sample_items[] = array(
					'index' => $idx,
					'item_keys' => array_keys($item),
					'id' => $item['id'] ?? 'N/A',
					'nm' => $item['nm'] ?? 'N/A'
				);
			}
		}
	}
	
	$result['debug_info']['total_items'] = $total;
	$result['debug_info']['items_with_data'] = $with_data;
	$result['debug_info']['sample_items'] = $sample_items;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>