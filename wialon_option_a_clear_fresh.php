<?php
set_time_limit(1200);
header('Content-Type: text/html; charset=utf-8');

require_once("db.php");

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

// ========================================
// 🎨 HTML STYLE
// ========================================
echo <<<HTML
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 20px; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
		h2 { color: #34495e; margin-top: 30px; border-left: 5px solid #3498db; padding-left: 15px; }
		.log-entry { background: #f8f9fa; border-left: 4px solid #3498db; padding: 10px 15px; margin: 10px 0; font-family: monospace; font-size: 13px; }
		.success { border-left-color: #27ae60; color: #27ae60; }
		.error { border-left-color: #e74c3c; color: #e74c3c; background: #fadbd8; }
		.warning { border-left-color: #f39c12; color: #f39c12; background: #fef5e7; }
		.info { border-left-color: #3498db; color: #3498db; }
		.summary { background: #e8f8f5; border: 2px solid #27ae60; padding: 20px; margin: 30px 0; border-radius: 5px; }
		.summary strong { color: #27ae60; font-size: 1.2em; }
		table { width: 100%; border-collapse: collapse; margin: 15px 0; }
		th, td { border: 1px solid #bdc3c7; padding: 12px; text-align: left; }
		th { background: #3498db; color: white; }
		tr:nth-child(even) { background: #f8f9fa; }
	</style>
</head>
<body>
<div class="container">
<h1>🔄 Wialon - Fresh Import (Clear Duplicates)</h1>
<p><strong>Mode:</strong> Clear old data → Fresh import</p>
<p><strong>Start time:</strong> <span style="color: #3498db;">
HTML;

echo date('Y-m-d H:i:s');

echo <<<HTML
</span></p>
HTML;

// ========================================
// 📝 LOGGING
// ========================================

function logInfo($message) {
	echo '<div class="log-entry info">ℹ️ ' . htmlspecialchars($message) . '</div>' . "\n";
	flush();
}

function logSuccess($message) {
	echo '<div class="log-entry success">✅ ' . htmlspecialchars($message) . '</div>' . "\n";
	flush();
}

function logError($message) {
	echo '<div class="log-entry error">❌ ' . htmlspecialchars($message) . '</div>' . "\n";
	flush();
}

function logWarning($message) {
	echo '<div class="log-entry warning">⚠️ ' . htmlspecialchars($message) . '</div>' . "\n";
	flush();
}

// ========================================
// STEP 1: CLEAR DATABASE
// ========================================

echo '<h2>Step 1: Clearing old trajets from database...</h2>';

try {
	$db = Cnx();
	
	logInfo("Deleting all trajets...");
	$db->query("TRUNCATE TABLE trajets");
	
	logSuccess("Database cleared! All old trajets deleted.");
	
} catch (Exception $e) {
	logError("Failed to clear database: " . $e->getMessage());
	exit;
}

// ========================================
// STEP 2: LOGIN TO WIALON
// ========================================

echo '<h2>Step 2: Authenticating with Wialon...</h2>';

logInfo('Connecting to Wialon API...');

$curl = curl_init();
$login_url = "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}";

curl_setopt_array($curl, array(
	CURLOPT_URL => $login_url,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
	logError("Connection failed: $err");
	exit;
}

$login_data = json_decode($response, true);

if (!isset($login_data['eid'])) {
	logError('No session ID returned!');
	exit;
}

$sid = $login_data['eid'];
logSuccess("Session created: $sid");

// ========================================
// STEP 3: FETCH AND INSERT
// ========================================

echo '<h2>Step 3: Fetching trajets from Wialon...</h2>';

$stats = array();

foreach($tab_group as $nom_groupe => $groupe_id) {
	logInfo("═══════════════════════════════════");
	logInfo("Processing group: <strong>$nom_groupe</strong>");
	
	$transporteur_id = $groupe_to_transporteur[$nom_groupe] ?? 1;
	
	// Clean
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
	
	// Execute report
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
		logWarning("No data found");
		$stats[$nom_groupe] = array('tables' => 0, 'trajets' => 0);
		continue;
	}
	
	$tables = $report_data['reportResult']['tables'];
	$num_tables = count($tables);
	
	logSuccess("$num_tables table(s) found");
	
	$trajets_groupe = 0;
	
	foreach($tables as $table_index => $table) {
		$num_rows = isset($table['rows']) ? $table['rows'] : 0;
		
		// Fetch rows
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
			continue;
		}
		
		// Insert trajets
		$trajets_inserted = 0;
		
		foreach($rows_data as $row_item) {
			if (!isset($row_item['r'])) continue;
			
			foreach($row_item['r'] as $trajet) {
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
				}
			}
		}
		
		$trajets_groupe += $trajets_inserted;
	}
	
	$stats[$nom_groupe] = array('tables' => $num_tables, 'trajets' => $trajets_groupe);
	logSuccess("Group total: $trajets_groupe trajets inserted");
}

// ========================================
// FINAL SUMMARY
// ========================================

echo '<h2>✅ Import Complete!</h2>';

echo '<div class="summary">';
echo '<h3>📊 Summary by Group:</h3>';
echo '<table>';
echo '<tr><th>Group</th><th>Tables</th><th>Trajets Inserted</th></tr>';

$total_tables = 0;
$total_trajets = 0;

foreach($stats as $groupe => $data) {
	echo '<tr>';
	echo '<td><strong>' . htmlspecialchars($groupe) . '</strong></td>';
	echo '<td>' . $data['tables'] . '</td>';
	echo '<td style="color: #27ae60; font-weight: bold;">' . $data['trajets'] . '</td>';
	echo '</tr>';
	
	$total_tables += $data['tables'];
	$total_trajets += $data['trajets'];
}

echo '</table>';
echo '<p><strong>📋 Total Tables:</strong> ' . $total_tables . '</p>';
echo '<p><strong>🚗 Total Trajets Inserted:</strong> <span style="font-size: 1.5em; color: #27ae60; font-weight: bold;">' . $total_trajets . '</span></p>';
echo '</div>';

echo '<p><strong>End time:</strong> <span style="color: #e74c3c;">' . date('Y-m-d H:i:s') . '</span></p>';
echo '</div></body></html>';

?>