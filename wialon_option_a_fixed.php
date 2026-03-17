<?php
// 🔧 ENABLE ALL ERRORS
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

set_time_limit(1200);
ob_start(); // Buffer output to prevent header issues

header('Content-Type: text/html; charset=utf-8');

echo "<!-- Debug: Starting script -->\n";
flush();

// Load db.php with error checking
if (!file_exists("db.php")) {
	die("❌ FATAL: db.php not found in current directory!");
}

require_once("db.php");

echo "<!-- Debug: db.php loaded -->\n";
flush();

// Test database connection
try {
	$test_db = Cnx();
	echo "<!-- Debug: Database connection OK -->\n";
	flush();
} catch (Exception $e) {
	die("❌ FATAL: Database connection failed: " . $e->getMessage());
}

define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB');

$groupe_to_transporteur = array(
	'BOUTCHRAFINE' => 1,
	'SOMATRIN' => 2,
	'MARATRANS' => 3,
	'G.T.C' => 4,
	'DOUKALI' => 5,
	'COTRAMAB' => 6,
	'CORYAD' => 7,
	'CONSMETA' => 8,
	'CHOUROUK' => 9,
	'CARRE' => 10,
	'STB' => 11,
	'FASTTRANS' => 12
);

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

$chauffeur_defaut = 'CD000';
$trajectcount = 0;

echo "<!-- Debug: Config loaded -->\n";
flush();

// ========================================
// HTML OUTPUT
// ========================================

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Wialon Import - Option A (Clear Fresh)</title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { 
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
			padding: 20px;
		}
		.container { 
			max-width: 1200px; 
			margin: 0 auto; 
			background: white; 
			border-radius: 12px;
			box-shadow: 0 20px 60px rgba(0,0,0,0.3);
			overflow: hidden;
		}
		.header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px;
			text-align: center;
		}
		.header h1 { font-size: 2.5em; margin-bottom: 10px; }
		.header p { font-size: 1.1em; opacity: 0.9; }
		.content {
			padding: 30px;
		}
		.log-entry {
			background: #f8f9fa;
			border-left: 5px solid #3498db;
			padding: 15px;
			margin: 12px 0;
			border-radius: 4px;
			font-family: 'Courier New', monospace;
			font-size: 14px;
			animation: slideIn 0.3s ease-out;
		}
		@keyframes slideIn {
			from { opacity: 0; transform: translateX(-20px); }
			to { opacity: 1; transform: translateX(0); }
		}
		.success { 
			border-left-color: #27ae60; 
			background: #eafaf1;
			color: #27ae60;
		}
		.error { 
			border-left-color: #e74c3c; 
			background: #fadbd8;
			color: #e74c3c;
		}
		.warning { 
			border-left-color: #f39c12; 
			background: #fef5e7;
			color: #f39c12;
		}
		.info { 
			border-left-color: #3498db; 
			background: #eaf2f8;
			color: #3498db;
		}
		h2 {
			color: #2c3e50;
			border-bottom: 3px solid #667eea;
			padding-bottom: 15px;
			margin: 30px 0 20px 0;
			font-size: 1.8em;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			margin: 20px 0;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		th {
			background: #667eea;
			color: white;
			padding: 15px;
			text-align: left;
			font-weight: 600;
		}
		td {
			padding: 12px 15px;
			border-bottom: 1px solid #ecf0f1;
		}
		tr:hover {
			background: #f5f6fa;
		}
		.summary {
			background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%);
			border-radius: 8px;
			padding: 25px;
			margin: 30px 0;
			box-shadow: 0 4px 15px rgba(212, 252, 121, 0.3);
		}
		.summary h3 {
			color: #27ae60;
			margin-bottom: 15px;
		}
		.stat-box {
			display: inline-block;
			background: white;
			padding: 15px 25px;
			margin: 10px;
			border-radius: 6px;
			text-align: center;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		.stat-box strong {
			display: block;
			font-size: 1.8em;
			color: #667eea;
			margin-bottom: 5px;
		}
		.stat-box span {
			color: #7f8c8d;
			font-size: 0.9em;
		}
		.time {
			color: #95a5a6;
			font-size: 0.9em;
		}
		.separator {
			height: 2px;
			background: linear-gradient(to right, transparent, #bdc3c7, transparent);
			margin: 30px 0;
		}
	</style>
</head>
<body>
<div class="container">
	<div class="header">
		<h1>🔄 Wialon Import</h1>
		<p>Option A: Clear Database & Fresh Import</p>
	</div>
	
	<div class="content">
		<p class="time">⏱️ Start: <strong><?php echo date('Y-m-d H:i:s'); ?></strong></p>
<?php

// ========================================
// LOGGING FUNCTIONS
// ========================================

function logInfo($msg) {
	echo '<div class="log-entry info">ℹ️ ' . htmlspecialchars($msg) . '</div>';
	flush();
	ob_flush();
}

function logSuccess($msg) {
	echo '<div class="log-entry success">✅ ' . htmlspecialchars($msg) . '</div>';
	flush();
	ob_flush();
}

function logError($msg) {
	echo '<div class="log-entry error">❌ ' . htmlspecialchars($msg) . '</div>';
	flush();
	ob_flush();
}

function logWarning($msg) {
	echo '<div class="log-entry warning">⚠️ ' . htmlspecialchars($msg) . '</div>';
	flush();
	ob_flush();
}

// ========================================
// STEP 1: CLEAR DATABASE
// ========================================

echo '<h2>Step 1: Clearing Database</h2>';

try {
	logInfo("Connecting to database...");
	$db = Cnx();
	logSuccess("Database connected!");
	
	logInfo("Truncating trajets table...");
	$db->query("TRUNCATE TABLE trajets");
	logSuccess("✅ Database cleared! All old trajets deleted.");
	
} catch (Exception $e) {
	logError("Failed to clear database: " . $e->getMessage());
	echo '</div></div></body></html>';
	exit;
}

// ========================================
// STEP 2: LOGIN TO WIALON
// ========================================

echo '<h2>Step 2: Connecting to Wialon</h2>';

logInfo('Initiating Wialon session...');

$curl = curl_init();
$login_url = "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}";

curl_setopt_array($curl, array(
	CURLOPT_URL => $login_url,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_SSL_VERIFYPEER => false,
	CURLOPT_HTTPHEADER => array(
		"cache-control: no-cache",
		"content-type: application/x-www-form-urlencoded"
	),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
	logError("Connection failed: $err");
	echo '</div></div></body></html>';
	exit;
}

$login_data = json_decode($response, true);

if (!isset($login_data['eid'])) {
	logError('No session ID returned!');
	logError('Response: ' . substr($response, 0, 500));
	echo '</div></div></body></html>';
	exit;
}

$sid = $login_data['eid'];
logSuccess("✅ Session created: <strong>$sid</strong>");

// ========================================
// STEP 3: FETCH AND INSERT
// ========================================

echo '<h2>Step 3: Fetching & Importing Data</h2>';

$stats = array();
$total_tables = 0;
$total_trajets = 0;

foreach($tab_group as $nom_groupe => $groupe_id) {
	echo '<div class="separator"></div>';
	logInfo("═══════════════════════════════════════");
	logInfo("Processing: <strong>$nom_groupe</strong> (ID: $groupe_id)");
	
	$transporteur_id = $groupe_to_transporteur[$nom_groupe] ?? 1;
	logInfo("Transporteur ID: $transporteur_id");
	
	// Clean previous report
	logInfo("Cleaning previous report...");
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
	logSuccess("Report cleaned");
	
	// Execute report
	logInfo("Executing report...");
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
	$curl_error = curl_error($curl);
	curl_close($curl);
	
	if ($curl_error) {
		logError("Curl error: $curl_error");
		continue;
	}
	
	$report_data = json_decode($response, true);
	
	if (!isset($report_data['reportResult']['tables'])) {
		logWarning("No data found for this group");
		$stats[$nom_groupe] = array('tables' => 0, 'trajets' => 0);
		continue;
	}
	
	$tables = $report_data['reportResult']['tables'];
	$num_tables = count($tables);
	
	logSuccess("$num_tables table(s) found");
	
	$trajets_groupe = 0;
	
	// Process each table
	foreach($tables as $table_index => $table) {
		$num_rows = isset($table['rows']) ? $table['rows'] : 0;
		logInfo("Table $table_index: $num_rows rows");
		
		// Fetch rows
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":'.$table_index.',"config":{"type":"range","data":{"from":0,"to":'.$num_rows.',"level":2}}}&sid='.$sid,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_SSL_VERIFYPEER => false,
		));
		
		$response = curl_exec($curl);
		$curl_error = curl_error($curl);
		curl_close($curl);
		
		if ($curl_error) {
			logError("Curl error fetching rows: $curl_error");
			continue;
		}
		
		$rows_data = json_decode($response, true);
		
		if (!is_array($rows_data) || !isset($rows_data[0]['r'])) {
			logWarning("Invalid response format");
			continue;
		}
		
		// Insert trajets
		$trajets_inserted = 0;
		
		foreach($rows_data as $row_item) {
			if (!isset($row_item['r'])) continue;
			
			foreach($row_item['r'] as $trajet) {
				try {
					$vehicule = str_replace(" ", "", str_replace("-", "", str_replace("/", "", $trajet['c']['1'] ?? '')));
					$parcour = $trajet['c']['2'] ?? '';
					$depart = $trajet['c']['3'] ?? '';
					$vers = $trajet['c']['4'] ?? '';
					$debut = $trajet['t1'] ?? 0;
					$fin = $trajet['t2'] ?? 0;
					$penalit = $trajet['c']['8'] ?? 0;
					$km = (float)str_replace("km", "", $trajet['c']['9'] ?? 0);
					
					$result = set_trajet($transporteur_id, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur_defaut);
					
					if ($result) {
						$trajets_inserted++;
						$trajectcount++;
						logSuccess("✓ Inserted: $vehicule");
					} else {
						logWarning("✗ Failed: $vehicule");
					}
				} catch (Exception $e) {
					logError("Exception: " . $e->getMessage());
				}
			}
		}
		
		$trajets_groupe += $trajets_inserted;
		logInfo("Table $table_index: $trajets_inserted trajets inserted");
	}
	
	$stats[$nom_groupe] = array('tables' => $num_tables, 'trajets' => $trajets_groupe);
	logSuccess("✅ Group <strong>$nom_groupe</strong>: $trajets_groupe trajets");
	
	$total_tables += $num_tables;
	$total_trajets += $trajets_groupe;
}

// ========================================
// SUMMARY
// ========================================

echo '<h2>📊 Final Summary</h2>';

echo '<table>';
echo '<tr><th>Group</th><th>Tables</th><th>Trajets Inserted</th></tr>';

foreach($stats as $groupe => $data) {
	$color = $data['trajets'] > 0 ? '#27ae60' : '#95a5a6';
	echo '<tr>';
	echo '<td><strong>' . htmlspecialchars($groupe) . '</strong></td>';
	echo '<td>' . $data['tables'] . '</td>';
	echo '<td style="color: ' . $color . '; font-weight: bold;">' . $data['trajets'] . '</td>';
	echo '</tr>';
}

echo '</table>';

echo '<div class="summary">';
echo '<h3>✅ Import Completed!</h3>';
echo '<div class="stat-box"><strong>' . $total_tables . '</strong> <span>Tables Processed</span></div>';
echo '<div class="stat-box"><strong style="color: #27ae60;">' . $total_trajets . '</strong> <span>Trajets Inserted</span></div>';
echo '</div>';

echo '<p class="time">⏱️ End: <strong>' . date('Y-m-d H:i:s') . '</strong></p>';

?>
	</div>
</div>
</body>
</html>
<?php

ob_end_flush();

?>