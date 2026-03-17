<?php
set_time_limit(600);
header('Content-Type: text/html; charset=utf-8');

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
// 📊 GROUPS TO PROCESS
// ========================================

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

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Debug - IDs dyal Groups</title>
	<style>
		body { font-family: Arial; background: #f5f5f5; margin: 20px; padding: 20px; }
		.container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; }
		.group-id { 
			background: #ecf0f1; 
			border-left: 4px solid #3498db; 
			padding: 12px; 
			margin: 10px 0; 
			font-family: monospace; 
			font-size: 14px;
			display: flex;
			justify-content: space-between;
		}
		.group-name { font-weight: bold; color: #2c3e50; }
		.group-number { color: #e74c3c; font-weight: bold; }
	</style>
</head>
<body>

<div class="container">
	<h1>🔍 Debug - IDs de Groups</h1>
	<p>Voilà les IDs de l’API Wialon:</p>

<?php

// Get session
$sid = sid();

if (!$sid) {
	echo '<p style="color: red;">❌ Erreur: Impossible de créer une session Wialon</p>';
	exit;
}

echo '<p style="color: green;">✅ Session créée</p>';
echo '<hr>';

// Display each group ID
foreach($tab_group as $nom => $id){
	echo '<div class="group-id">';
	echo '<span class="group-name">' . $nom . '</span>';
	echo '<span class="group-number">' . $id . '</span>';
	echo '</div>';
}

echo '<hr>';
echo '<p><strong>Total groups:</strong> ' . count($tab_group) . '</p>';

?>

</div>

</body>
</html>