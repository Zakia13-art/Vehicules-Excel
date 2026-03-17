<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);

require_once("getitemid.php");

// Override set_trajet with debug version
function set_trajet_debug($transporteur,$veh,$parc,$dep,$vers,$debut,$fin,$penalite,$km,$chauff){
	$db=Cnx();
	
	// Clean data
	$veh = trim($veh);
	$parc = trim($parc);
	$dep = trim($dep);
	$vers = trim($vers);
	$chauff = trim($chauff);
	
	// Convert timestamps - SAME LOGIC AS db_FIXED.php
	if (is_numeric($debut) && $debut > 1000000000) {
		$debut_dt = date('Y-m-d H:i:s', (int)$debut);
	} else {
		$debut_dt = date('Y-m-d H:i:s');
	}
	
	if (is_numeric($fin) && $fin > 1000000000) {
		$fin_dt = date('Y-m-d H:i:s', (int)$fin);
	} else {
		$fin_dt = date('Y-m-d H:i:s');
	}
	
	// Clean penalty
	if (is_string($penalite) && ($penalite === '-----' || trim($penalite) === '')) {
		$penalite = 0;
	} else {
		$penalite = (int) preg_replace('/[^0-9]/', '', (string) $penalite);
	}
	
	// Clean km
	$km = (float) preg_replace('/[^0-9.]/', '', (string) $km);
	
	// LOG FILE
	@mkdir('logs', 0755, true);
	$logfile = "logs/debug_import.log";
	
	$log = "═══════════════════════════════════\n";
	$log .= date("Y-m-d H:i:s") . " - INSERTING TRAJET\n";
	$log .= "Transporteur: $transporteur | Vehicle: $veh\n";
	$log .= "Debut (raw): $debut | Fin (raw): $fin\n";
	$log .= "Debut (converted): $debut_dt | Fin (converted): $fin_dt\n";
	$log .= "Penalite: $penalite | KM: $km\n";
	
	file_put_contents($logfile, $log, FILE_APPEND);
	
	try {
		// Check duplicate
		$checkQuery = "SELECT COUNT(*) FROM trajets WHERE transporteur = ? AND vehicule = ? AND debut = ? AND fin = ?";
		$checkStmt = $db->prepare($checkQuery);
		$checkStmt->execute([$transporteur, $veh, $debut_dt, $fin_dt]);
		
		$count = $checkStmt->fetchColumn();
		$log = "Duplicate check: $count found\n";
		file_put_contents($logfile, $log, FILE_APPEND);
		
		if ($count > 0) {
			$log = "Result: DUPLICATE - REJECTED\n\n";
			file_put_contents($logfile, $log, FILE_APPEND);
			return false;
		}
		
		// Try to insert
		$requete7 = "INSERT INTO trajets (transporteur, vehicule, parcour, depart, vers, debut, fin, penalite, kilometrage, chauffeur) 
		             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		
		$req = $db->prepare($requete7);
		
		$log = "SQL prepared\n";
		$log .= "Executing with values: [$transporteur, '$veh', '$parc', '$dep', '$vers', '$debut_dt', '$fin_dt', $penalite, $km, '$chauff']\n";
		file_put_contents($logfile, $log, FILE_APPEND);
		
		$result = $req->execute([
			$transporteur,
			$veh,
			$parc,
			$dep,
			$vers,
			$debut_dt,
			$fin_dt,
			$penalite,
			$km,
			$chauff
		]);
		
		$log = "Execute result: " . ($result ? "TRUE" : "FALSE") . "\n";
		$log .= "Rows affected: " . $req->rowCount() . "\n";
		$log = "Result: SUCCESS\n\n";
		file_put_contents($logfile, $log, FILE_APPEND);
		
		return $result;
		
	} catch (PDOException $e) {
		$log = "PDOException: " . $e->getMessage() . "\n";
		$log .= "Code: " . $e->getCode() . "\n";
		$log .= "Result: FAILED\n\n";
		file_put_contents($logfile, $log, FILE_APPEND);
		return false;
	} catch (Exception $e) {
		$log = "Exception: " . $e->getMessage() . "\n";
		$log .= "Result: FAILED\n\n";
		file_put_contents($logfile, $log, FILE_APPEND);
		return false;
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
		.section { background: #252526; border: 1px solid #3e3e42; padding: 15px; margin: 15px 0; border-radius: 4px; }
		h1 { color: #4ec9b0; }
		.success { color: #4ec9b0; }
		.info { color: #569cd6; }
		.error { color: #f48771; }
		.data { background: #2d2d30; padding: 10px; margin: 10px 0; border-left: 3px solid #569cd6; }
		a { color: #569cd6; text-decoration: none; }
		a:hover { text-decoration: underline; }
	</style>
</head>
<body>

<h1>🔍 Debug: Import 1 trajet seulement</h1>

<?php

// Get session
echo '<div class="section">';
echo '<p class="info">Connexion à Wialon...</p>';

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
	curl_close($curl);
	$data = json_decode($response, true);
	return $data['eid'] ?? '';
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

$sid = sid();

if (!$sid) {
	echo '<p class="error">❌ Failed to get session</p>';
	exit;
}

echo '<p class="success">✅ Session: ' . substr($sid, 0, 20) . '...</p>';
echo '</div>';

// Get data
echo '<div class="section">';
echo '<p class="info">Fetching BOUTCHRAFINE data...</p>';

cleanRepport($sid);
sleep(1);

$groupe_id = 19022033;
$to = time();
$from = $to - (7 * 86400);

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":1,"reportObjectId":'.$groupe_id.',"reportObjectSecId":0,"interval":{"from":'.$from.',"to":'.$to.',"flags":0}}&sid='.$sid,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_SSL_VERIFYPEER => false,
	CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
));

$response = curl_exec($curl);
curl_close($curl);

$report_data = json_decode($response, true);

if (!isset($report_data['reportResult']['tables'])) {
	echo '<p class="error">❌ No tables found</p>';
	exit;
}

$tables = $report_data['reportResult']['tables'];
echo '<p class="success">✅ Found ' . count($tables) . ' tables</p>';
echo '</div>';

// Get first trajet
echo '<div class="section">';
echo '<p class="info">Fetching first trajet...</p>';

$table_index = 0;
$num_rows = $tables[0]['rows'] ?? 0;

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL => 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":'.$table_index.',"config":{"type":"range","data":{"from":0,"to":'.$num_rows.',"level":2}}}&sid='.$sid,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_SSL_VERIFYPEER => false,
	CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
));

$response = curl_exec($curl);
curl_close($curl);

$rows_data = json_decode($response, true);

if (!is_array($rows_data) || !isset($rows_data[0]['r'])) {
	echo '<p class="error">❌ Invalid response format</p>';
	exit;
}

echo '<p class="success">✅ Got row data</p>';
echo '</div>';

// Process FIRST trajet only
echo '<div class="section">';
echo '<h2>📋 Inserting FIRST trajet:</h2>';

$test_count = 0;
$max_tests = 1;

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
		
		echo '<div class="data">';
		echo "Vehicle: <span class='success'>$vehicule</span><br>";
		echo "Parcour: <span class='success'>$parcour</span><br>";
		echo "Debut (timestamp): <span class='info'>$debut</span><br>";
		echo "Fin (timestamp): <span class='info'>$fin</span><br>";
		echo "KM: <span class='success'>$km</span><br>";
		echo '</div>';
		
		echo '<p class="info">Calling set_trajet_debug()...</p>';
		
		$result = set_trajet_debug(1, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, 'CD000');
		
		if ($result) {
			echo '<p class="success">✅ INSERT SUCCESSFUL!</p>';
		} else {
			echo '<p class="error">❌ INSERT FAILED!</p>';
		}
	}
}

echo '</div>';

// Show log
echo '<div class="section">';
echo '<h2>📄 Debug Log (logs/debug_import.log):</h2>';

if (file_exists('logs/debug_import.log')) {
	$log_content = file_get_contents('logs/debug_import.log');
	echo '<p><a href="logs/debug_import.log" target="_blank">📥 Download full log</a></p>';
	echo '<pre style="background: #2d2d30; padding: 15px; border-radius: 4px; overflow-x: auto;">' . htmlspecialchars($log_content) . '</pre>';
} else {
	echo '<p class="error">❌ No log file found yet</p>';
}

echo '</div>';

// Check database
echo '<div class="section">';
echo '<h2>📊 Database check:</h2>';

try {
	$db = Cnx();
	$result = $db->query("SELECT COUNT(*) as count FROM trajets");
	$row = $result->fetch(PDO::FETCH_ASSOC);
	echo '<p><span class="success">Trajets in DB now:</span> <strong>' . $row['count'] . '</strong></p>';
} catch (Exception $e) {
	echo '<p class="error">Error: ' . $e->getMessage() . '</p>';
}

echo '</div>';

?>

</body>
</html>