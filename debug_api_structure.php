<?php
set_time_limit(600);
header('Content-Type: application/json; charset=utf-8');

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

function getGroupDetails($groupId, $sid){
	$curl = curl_init();
	// Get full group details including items
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit_group","propName":"id","propValueMask":"'.$groupId.'"},"force":1,"flags":1,"from":0,"to":0}&sid='.$sid;
	
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
	return $data;
}

function getAllVehicles($sid){
	$curl = curl_init();
	// Get ALL vehicles without group filter
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*"},"force":1,"flags":1,"from":0,"to":20}&sid='.$sid;
	
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
	return $data;
}

$result = array(
	'login' => 'pending',
	'groups_test' => array(),
	'vehicles_test' => array()
);

$sid = loginWialon();
if ($sid) {
	$result['login'] = 'success';
} else {
	$result['login'] = 'failed';
	echo json_encode($result);
	exit;
}

// Test 1: Get group details (first group BOUTCHRAFINE)
$group_test = getGroupDetails(19022033, $sid);
$result['groups_test'] = array(
	'group_id' => 19022033,
	'group_name' => 'BOUTCHRAFINE',
	'response_keys' => isset($group_test['items'][0]) ? array_keys($group_test['items'][0]) : [],
	'first_item_sample' => isset($group_test['items'][0]) ? $group_test['items'][0] : null,
	'total_items' => count($group_test['items'] ?? [])
);

// Test 2: Get all vehicles
$vehicles_test = getAllVehicles($sid);
$result['vehicles_test'] = array(
	'query_type' => 'ALL vehicles (first 20)',
	'response_keys' => isset($vehicles_test['items'][0]) ? array_keys($vehicles_test['items'][0]) : [],
	'total_items' => count($vehicles_test['items'] ?? []),
	'sample_vehicles' => []
);

// Get first 3 vehicles as samples
if (isset($vehicles_test['items'])) {
	for ($i = 0; $i < min(3, count($vehicles_test['items'])); $i++) {
		$v = $vehicles_test['items'][$i];
		$result['vehicles_test']['sample_vehicles'][] = array(
			'id' => $v['id'] ?? 'N/A',
			'nm' => $v['nm'] ?? 'N/A',
			'cls' => $v['cls'] ?? 'N/A',
			'all_keys' => array_keys($v)
		);
	}
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>