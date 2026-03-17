<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

header('Content-Type: text/html; charset=utf-8');

require_once("db_debug.php");

define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB');

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
		h1 { color: #4ec9b0; }
		.section { background: #252526; border: 1px solid #3e3e42; padding: 15px; margin: 15px 0; border-radius: 4px; }
		.log { margin: 10px 0; padding: 10px; background: #2d2d30; border-left: 3px solid #569cd6; }
		.success { border-left-color: #4ec9b0; color: #4ec9b0; }
		.error { border-left-color: #f48771; color: #f48771; }
		code { background: #2d2d30; padding: 2px 6px; border-radius: 3px; color: #ce9178; }
	</style>
</head>
<body>

<h1>🔍 Debug: Test set_trajet_debug()</h1>

<div class="section">
<h2>Step 1: Clear Database</h2>
<?php

try {
	$db = Cnx();
	$db->query("TRUNCATE TABLE trajets");
	echo '<div class="log success">✅ Database cleared</div>';
} catch (Exception $e) {
	echo '<div class="log error">❌ Error: ' . $e->getMessage() . '</div>';
	exit;
}

?>
</div>

<div class="section">
<h2>Step 2: Get Wialon Data</h2>
<?php

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}",
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
curl_close($curl);

$login_data = json_decode($response, true);
$sid = $login_data['eid'] ?? null;

if (!$sid) {
	echo '<div class="log error">❌ Failed to get session</div>';
	exit;
}

echo '<div class="log success">✅ Session: ' . substr($sid, 0, 20) . '...</div>';

// Get BOUTCHRAFINE data
$groupe_id = 19022033;

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=report/cleanup_result&params={}&sid=".$sid,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_SSL_VERIFYPEER => false,
));
curl_exec($curl);
curl_close($curl);

sleep(1);

$to = time();
$from = $to - (7 * 86400);

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":1,"reportObjectId":'.$groupe_id.',"reportObjectSecId":0,"interval":{"from":'.$from.',"to":'.$to.',"flags":0}}&sid='.$sid,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
curl_close($curl);

$report_data = json_decode($response, true);

if (!isset($report_data['reportResult']['tables'])) {
	echo '<div class="log error">❌ No tables in report</div>';
	exit;
}

$tables = $report_data['reportResult']['tables'];
echo '<div class="log success">✅ Found ' . count($tables) . ' tables</div>';

// Get first row from first table
$table_index = 0;
$num_rows = $tables[0]['rows'] ?? 0;

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":'.$table_index.',"config":{"type":"range","data":{"from":0,"to":'.$num_rows.',"level":2}}}&sid='.$sid,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
curl_close($curl);

$rows_data = json_decode($response, true);

if (!is_array($rows_data) || !isset($rows_data[0]['r'])) {
	echo '<div class="log error">❌ Invalid rows response</div>';
	exit;
}

echo '<div class="log success">✅ Got row data</div>';

?>
</div>

<div class="section">
<h2>Step 3: Insert First 5 Trajets (with DEBUG)</h2>
<?php

$test_count = 0;
$max_tests = 5;

foreach($rows_data as $row_item) {
	if ($test_count >= $max_tests) break;
	
	if (!isset($row_item['r'])) continue;
	
	foreach($row_item['r'] as $trajet) {
		if ($test_count >= $max_tests) break;
		
		$test_count++;
		
		$vehicule = str_replace(" ", "", str_replace("-", "", str_replace("/", "", $trajet['c']['1'] ?? '')));
		$parcour = $trajet['c']['2'] ?? '';
		$depart = $trajet['c']['3'] ?? '';
		$vers = $trajet['c']['4'] ?? '';
		$debut = $trajet['t1'] ?? 0;
		$fin = $trajet['t2'] ?? 0;
		$penalit = $trajet['c']['8'] ?? 0;
		$km = (float)str_replace("km", "", $trajet['c']['9'] ?? 0);
		
		echo "<h3>Test #$test_count: $vehicule</h3>";
		
		$result = set_trajet_debug(1, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, 'CD000');
		
		if ($result) {
			echo '<div class="log success">✅ INSERT OK</div>';
		} else {
			echo '<div class="log error">❌ INSERT FAILED</div>';
		}
	}
}

?>
</div>

<div class="section">
<h2>Step 4: Check Debug Log</h2>
<?php

if (file_exists('logs/insert_debug.log')) {
	$log_content = file_get_contents('logs/insert_debug.log');
	echo '<p>📄 Debug log exists (' . strlen($log_content) . ' bytes)</p>';
	echo '<p><a href="logs/insert_debug.log" target="_blank">Download logs/insert_debug.log</a></p>';
	echo '<h3>Content Preview:</h3>';
	echo '<pre>' . htmlspecialchars(substr($log_content, 0, 1000)) . '</pre>';
} else {
	echo '<div class="log error">❌ No debug log file created yet</div>';
}

?>
</div>

<div class="section">
<h2>Step 5: Check Database</h2>
<?php

try {
	$db = Cnx();
	$result = $db->query("SELECT COUNT(*) as count FROM trajets");
	$row = $result->fetch(PDO::FETCH_ASSOC);
	echo '<div class="log success">✅ Trajets in DB: ' . $row['count'] . '</div>';
} catch (Exception $e) {
	echo '<div class="log error">❌ Query error: ' . $e->getMessage() . '</div>';
}

?>
</div>

</body>
</html>