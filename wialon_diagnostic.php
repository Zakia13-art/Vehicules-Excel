<?php
set_time_limit(1200);
header('Content-Type: text/html; charset=utf-8');

require_once("db.php");

define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB');

$groupe_to_transporteur = array('BOUTCHRAFINE' => 1, 'SOMATRIN' => 2, 'MARATRANS' => 3, 'G.T.C' => 4, 'DOUKALI' => 5, 'COTRAMAB' => 6, 'CORYAD' => 7, 'CONSMETA' => 8, 'CHOUROUK' => 9, 'CARRE' => 10, 'STB' => 11, 'FASTTRANS' => 12);
$tab_group = array('BOUTCHRAFINE' => 19022033, 'SOMATRIN' => 19596491, 'MARATRANS' => 19631505, 'G.T.C' => 19590737, 'DOUKALI' => 19585587, 'COTRAMAB' => 19585601, 'CORYAD' => 19585581, 'CONSMETA' => 19629962, 'CHOUROUK' => 19630023, 'CARRE' => 19643391, 'STB' => 19585942, 'FASTTRANS' => 19635796);

$chauffeur_defaut = 'CD000';

echo <<<HTML
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; margin: 0; padding: 20px; }
		.log { background: #252526; border: 1px solid #3e3e42; padding: 15px; margin: 10px 0; border-radius: 4px; font-size: 12px; }
		.success { border-left: 4px solid #4ec9b0; }
		.error { border-left: 4px solid #f48771; background: #3d2626; }
		.warning { border-left: 4px solid #dcdcaa; }
		.info { border-left: 4px solid #569cd6; }
		.data { background: #1e1e1e; color: #ce9178; margin: 10px 0; padding: 10px; border: 1px solid #3e3e42; overflow-x: auto; font-size: 11px; }
		h1 { color: #4ec9b0; }
		h2 { color: #569cd6; }
		.sql { background: #2d2d30; color: #d7ba7d; padding: 10px; border: 1px solid #3e3e42; margin: 10px 0; border-radius: 3px; font-size: 11px; }
		.query-error { background: #3d2626; color: #f48771; padding: 10px; border: 1px solid #f48771; margin: 10px 0; border-radius: 3px; }
	</style>
</head>
<body>
<h1>🔍 Database Error Diagnostic</h1>
HTML;

function logError($msg) { echo "<div class='log error'>❌ $msg</div>"; flush(); }
function logInfo($msg) { echo "<div class='log info'>ℹ️ $msg</div>"; flush(); }
function logSuccess($msg) { echo "<div class='log success'>✅ $msg</div>"; flush(); }
function logSQL($sql) { echo "<div class='sql'><strong>SQL:</strong><br>$sql</div>"; flush(); }
function logQueryError($error) { echo "<div class='query-error'><strong>Query Error:</strong><br>$error</div>"; flush(); }

// LOGIN
logInfo('Logging in...');
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
if (!$sid) { logError("No SID"); exit; }
logSuccess("Session: $sid");

// GET DATA
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

if (!isset($report_data['reportResult']['tables'])) { logError("No tables"); exit; }
$tables = $report_data['reportResult']['tables'];

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

if (!is_array($rows_data) || !isset($rows_data[0]['r'])) { logError("Invalid response"); exit; }

echo '<h2>📊 Testing set_trajet() - First Trajet Only</h2>';

$test_count = 0;
foreach($rows_data as $row_item) {
	if ($test_count >= 1) break;
	if (!isset($row_item['r'])) continue;
	
	foreach($row_item['r'] as $trajet) {
		if ($test_count >= 1) break;
		$test_count++;
		
		$vehicule = str_replace(" ", "", str_replace("-", "", str_replace("/", "", $trajet['c']['1'] ?? '')));
		$parcour = $trajet['c']['2'] ?? '';
		$depart = $trajet['c']['3'] ?? '';
		$vers = $trajet['c']['4'] ?? '';
		$debut = $trajet['t1'] ?? 0;
		$fin = $trajet['t2'] ?? 0;
		$penalit = $trajet['c']['8'] ?? 0;
		$km = (float)str_replace("km", "", $trajet['c']['9'] ?? 0);
		
		logInfo("Calling set_trajet() with:");
		echo "<div class='data'>";
		echo "transporteur_id: 1<br>";
		echo "vehicule: $vehicule<br>";
		echo "parcour: $parcour<br>";
		echo "depart: $depart<br>";
		echo "vers: $vers<br>";
		echo "debut: $debut<br>";
		echo "fin: $fin<br>";
		echo "penalit: $penalit<br>";
		echo "km: $km<br>";
		echo "chauffeur: $chauffeur_defaut<br>";
		echo "</div>";
		
		// Check if database connection exists
		if (!isset($db)) {
			logError("❌ DATABASE CONNECTION NOT FOUND - \$db variable doesn't exist!");
			logInfo("This likely means db.php didn't load properly or didn't create \$db connection");
			echo '<h2>Possible Solutions:</h2>';
			echo '<ol>';
			echo '<li>Check if db.php has: <code>$db = new mysqli(...);</code></li>';
			echo '<li>Check if mysqli is installed on the server</li>';
			echo '<li>Check database credentials (host, user, password, database)</li>';
			echo '</ol>';
		} else {
			logInfo("Database connection found: " . get_class($db));
		}
		
		// Try calling set_trajet with error tracking
		logInfo("Executing set_trajet()...");
		
		// Enable all errors
		error_reporting(E_ALL);
		ini_set('display_errors', 0);
		
		// Capture any PHP errors
		set_error_handler(function($errno, $errstr, $errfile, $errline) {
			logError("PHP Error [$errno]: $errstr in $errfile:$errline");
		});
		
		try {
			$result = set_trajet(1, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur_defaut);
			
			if ($result === true) {
				logSuccess("✅ Trajet inserted successfully!");
			} else if ($result === false) {
				logError("set_trajet() returned FALSE");
				
				// Try to get database error if available
				if (isset($db) && method_exists($db, 'error')) {
					logQueryError("MySQL Error: " . $db->error);
				}
				if (isset($db) && method_exists($db, 'errno')) {
					logQueryError("MySQL Error Code: " . $db->errno);
				}
			} else {
				logInfo("set_trajet() returned: " . var_export($result, true));
			}
		} catch (Exception $e) {
			logError("Exception: " . $e->getMessage());
			logInfo("Stack trace: " . $e->getTraceAsString());
		}
		
		restore_error_handler();
	}
}

echo '<h2>📋 Recommendations:</h2>';
echo '<ol>';
echo '<li><strong>Share your db.php</strong> - Show the set_trajet() function definition</li>';
echo '<li><strong>Check duplicate detection</strong> - Is there a UNIQUE constraint checking duplicates?</li>';
echo '<li><strong>Check database errors</strong> - Enable MySQL error reporting</li>';
echo '<li><strong>Verify all tables exist</strong> - Make sure "trajets" table and all fields exist</li>';
echo '</ol>';

?>
</body></html>
HTML;
?>