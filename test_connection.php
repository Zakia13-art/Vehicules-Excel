<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial; background: #f5f5f5; padding: 20px; }
		.box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; border-left: 5px solid #3498db; }
		.success { border-left-color: #27ae60; color: #27ae60; }
		.error { border-left-color: #e74c3c; color: #e74c3c; background: #fadbd8; }
		code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
	</style>
</head>
<body>

<h1>🔍 Wialon + Database Test</h1>

<?php

echo '<div class="box">';
echo '<h2>Test 1: Database Connection</h2>';

if (file_exists("db.php")) {
	echo '<p>✅ db.php found</p>';
	
	try {
		require_once("db.php");
		
		$db = Cnx();
		echo '<p class="success">✅ Database connected successfully!</p>';
		
		// Test query
		$result = $db->query("SELECT COUNT(*) as count FROM trajets");
		$row = $result->fetch(PDO::FETCH_ASSOC);
		echo '<p>📊 Current trajets in database: <strong>' . $row['count'] . '</strong></p>';
		
	} catch (Exception $e) {
		echo '<p class="error">❌ Database error: ' . $e->getMessage() . '</p>';
	}
} else {
	echo '<p class="error">❌ db.php not found!</p>';
}

echo '</div>';

echo '<div class="box">';
echo '<h2>Test 2: Wialon API Connection</h2>';

define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB');

echo '<p>Token: <code>b6db68...13766308C1CFB</code></p>';

logInfo("Connecting to Wialon API...");

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}",
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
$err = curl_error($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($err) {
	echo '<p class="error">❌ Curl Error: ' . $err . '</p>';
} else {
	echo '<p>HTTP Code: ' . $http_code . '</p>';
	
	$data = json_decode($response, true);
	
	if (isset($data['eid'])) {
		echo '<p class="success">✅ Wialon API OK!</p>';
		echo '<p>Session ID: <code>' . substr($data['eid'], 0, 20) . '...</code></p>';
	} else {
		echo '<p class="error">❌ No session ID returned</p>';
		echo '<p>Response: <code>' . substr($response, 0, 200) . '</code></p>';
	}
}

echo '</div>';

echo '<div class="box">';
echo '<h2>Test 3: File Permissions</h2>';

$test_dir = 'logs';
if (!is_dir($test_dir)) {
	if (@mkdir($test_dir, 0755, true)) {
		echo '<p class="success">✅ logs/ directory created</p>';
	} else {
		echo '<p class="error">❌ Cannot create logs/ directory</p>';
	}
} else {
	echo '<p class="success">✅ logs/ directory exists</p>';
}

if (is_writable($test_dir)) {
	echo '<p class="success">✅ logs/ is writable</p>';
} else {
	echo '<p class="error">❌ logs/ is not writable</p>';
}

echo '</div>';

echo '<div class="box">';
echo '<h2>✅ Tests Complete</h2>';
echo '<p>If all tests pass, you can run: <strong>wialon_option_a_fixed.php</strong></p>';
echo '</div>';

function logInfo($msg) {
	echo '<p>ℹ️ ' . $msg . '</p>';
}

?>

</body>
</html>