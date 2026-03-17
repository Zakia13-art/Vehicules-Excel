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

function testQuery($description, $url, $sid){
	$curl = curl_init();
	$full_url = $url . '&sid=' . $sid;
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $full_url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	
	$response = curl_exec($curl);
	curl_close($curl);
	$data = json_decode($response, true);
	
	return array(
		'description' => $description,
		'total_items' => count($data['items'] ?? []),
		'sample' => isset($data['items'][0]) ? array(
			'id' => $data['items'][0]['id'] ?? null,
			'nm' => $data['items'][0]['nm'] ?? null,
			'keys' => array_keys($data['items'][0] ?? [])
		) : 'No items'
	);
}

$results = array();
$sid = loginWialon();

if (!$sid) {
	echo json_encode(['error' => 'Login failed']);
	exit;
}

// Test 1: Using flags=1 (from original code - works for groups)
$results[] = testQuery(
	'Groups with flags=1',
	'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit_group","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":0}',
	$sid
);

// Test 2: Using flags=8192 (from gettempID - might work for units)
$results[] = testQuery(
	'Vehicles with flags=8192',
	'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":8192,"from":0,"to":0}',
	$sid
);

// Test 3: Using flags=0
$results[] = testQuery(
	'Vehicles with flags=0',
	'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":0,"from":0,"to":0}',
	$sid
);

// Test 4: Using flags=256 
$results[] = testQuery(
	'Vehicles with flags=256',
	'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":256,"from":0,"to":0}',
	$sid
);

// Test 5: Using flags=512
$results[] = testQuery(
	'Vehicles with flags=512',
	'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":512,"from":0,"to":0}',
	$sid
);

// Test 6: Using flags=1024
$results[] = testQuery(
	'Vehicles with flags=1024',
	'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1024,"from":0,"to":0}',
	$sid
);

// Test 7: Using from=0, to=100 with flags=1
$results[] = testQuery(
	'Vehicles with flags=1, from=0, to=100',
	'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":100}',
	$sid
);

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>