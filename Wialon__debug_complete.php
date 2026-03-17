<?php
set_time_limit(1200); // 20 minutes max
header('Content-Type: text/html; charset=utf-8');

require_once("db.php");

// 🔐 TOKEN
define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB');

// 📊 CONFIGURATION
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
// 🎨 STYLE HTML
// ========================================
echo <<<HTML
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 20px; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
		h2 { color: #34495e; margin-top: 30px; border-left: 5px solid #3498db; padding-left: 15px; }
		.log-entry { 
			background: #f8f9fa; 
			border-left: 4px solid #3498db; 
			padding: 10px 15px; 
			margin: 10px 0; 
			font-family: 'Courier New', monospace;
			font-size: 13px;
		}
		.success { border-left-color: #27ae60; color: #27ae60; }
		.error { border-left-color: #e74c3c; color: #e74c3c; background: #fadbd8; }
		.warning { border-left-color: #f39c12; color: #f39c12; background: #fef5e7; }
		.info { border-left-color: #3498db; color: #3498db; }
		.api-call { 
			background: #ecf0f1; 
			border-left: 4px solid #9b59b6; 
			padding: 12px; 
			margin: 15px 0;
			color: #2c3e50;
			font-family: monospace;
		}
		.summary { 
			background: #e8f8f5; 
			border: 2px solid #27ae60; 
			padding: 20px; 
			margin: 30px 0; 
			border-radius: 5px;
		}
		.summary strong { color: #27ae60; font-size: 1.2em; }
		.error-section { 
			background: #fadbd8; 
			border: 2px solid #e74c3c; 
			padding: 20px; 
			margin: 30px 0; 
			border-radius: 5px;
		}
		.timeline { margin: 20px 0; }
		.step { margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
		.step-number { 
			display: inline-block; 
			background: #3498db; 
			color: white; 
			width: 30px; 
			height: 30px; 
			line-height: 30px; 
			text-align: center; 
			border-radius: 50%; 
			margin-right: 10px;
			font-weight: bold;
		}
		table { width: 100%; border-collapse: collapse; margin: 15px 0; }
		th, td { border: 1px solid #bdc3c7; padding: 12px; text-align: left; }
		th { background: #3498db; color: white; }
		tr:nth-child(even) { background: #f8f9fa; }
	</style>
</head>
<body>
<div class="container">
<h1>🔄 Wialon API - Récupération Complète des Trajets</h1>
<p><strong>Démarrage:</strong> <span style="color: #3498db;">HTML
echo date('Y-m-d H:i:s');
echo <<<HTML
</span></p>
<div class="timeline">
HTML;

// ========================================
// 📝 HELPER: Afficher logs
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

function logApi($method, $url, $response = null) {
	echo '<div class="api-call">';
	echo '<strong>API Call:</strong> ' . htmlspecialchars($method) . '<br>';
	echo '<strong>URL:</strong> <small>' . htmlspecialchars(substr($url, 0, 100)) . '...</small><br>';
	if ($response) {
		echo '<strong>Response (first 200 chars):</strong> <pre>' . htmlspecialchars(substr(json_encode($response, JSON_PRETTY_PRINT), 0, 200)) . '...</pre>';
	}
	echo '</div>' . "\n";
	flush();
}

// ========================================
// 🔑 STEP 1: LOGIN
// ========================================

echo '<div class="step"><span class="step-number">1</span><strong>Authentification Wialon</strong></div>';

logInfo('Tentative de connexion avec le token...');

$curl = curl_init();
$login_url = "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}";

curl_setopt_array($curl, array(
	CURLOPT_URL => $login_url,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 60,
	CURLOPT_HTTPHEADER => array(
		"cache-control: no-cache",
		"content-type: application/x-www-form-urlencoded"
	),
	CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
	logError("Erreur connexion: $err");
	exit;
}

$login_data = json_decode($response, true);
logApi('POST', $login_url, $login_data);

if (!isset($login_data['eid'])) {
	logError('Pas de SID retourné! Réponse: ' . $response);
	exit;
}

$sid = $login_data['eid'];
logSuccess("Session créée: SID = $sid");

// ========================================
// 🚗 STEP 2: TRAITER CHAQUE GROUPE
// ========================================

echo '<div class="step"><span class="step-number">2</span><strong>Traitement des Groupes</strong></div>';

$stats = array();
$groupe_errors = array();

foreach($tab_group as $nom_groupe => $groupe_id) {
	logInfo("═══════════════════════════════════");
	logInfo("Groupe: <strong>$nom_groupe</strong> (ID: $groupe_id)");
	
	$transporteur_id = $groupe_to_transporteur[$nom_groupe] ?? 1;
	logInfo("Transporteur ID: $transporteur_id");
	
	// --- NETTOYER RAPPORT PRÉCÉDENT ---
	logInfo("Nettoyage du rapport précédent...");
	
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
	logSuccess("Rapport nettoyé");
	
	// --- EXÉCUTER RAPPORT ---
	logInfo("Exécution du rapport pour le groupe...");
	
	$to = time();
	$from = $to - (7 * 86400); // 7 jours
	
	$curl = curl_init();
	$exec_url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":1,"reportObjectId":'.$groupe_id.',"reportObjectSecId":0,"interval":{"from":'.$from.',"to":'.$to.',"flags":0}}&sid='.$sid;
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $exec_url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
	));
	
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);
	
	if ($err) {
		logError("Erreur execRep: $err");
		$groupe_errors[$nom_groupe] = $err;
		continue;
	}
	
	$report_data = json_decode($response, true);
	
	if (!isset($report_data['reportResult']['tables'])) {
		logWarning("Aucune table trouvée dans le rapport");
		$stats[$nom_groupe] = array('tables' => 0, 'trajets' => 0);
		continue;
	}
	
	$tables = $report_data['reportResult']['tables'];
	$num_tables = count($tables);
	
	logSuccess("$num_tables table(s) trouvée(s)");
	
	// --- PARCOURIR CHAQUE TABLE ---
	$trajets_groupe = 0;
	
	foreach($tables as $table_index => $table) {
		logInfo("Traitement de la table $table_index...");
		
		$num_rows = isset($table['rows']) ? $table['rows'] : 0;
		logInfo("Nombre de lignes: $num_rows");
		
		// --- RÉCUPÉRER LES LIGNES ---
		$curl = curl_init();
		$select_url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":'.$table_index.',"config":{"type":"range","data":{"from":0,"to":'.$num_rows.',"level":2}}}&sid='.$sid;
		
		curl_setopt_array($curl, array(
			CURLOPT_URL => $select_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_SSL_VERIFYPEER => false,
		));
		
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		
		if ($err) {
			logError("Erreur selectRes: $err");
			continue;
		}
		
		$rows_data = json_decode($response, true);
		
		if (!is_array($rows_data) || !isset($rows_data[0]['r'])) {
			logWarning("Format de réponse incorrect");
			continue;
		}
		
		// --- INSÉRER CHAQUE TRAJET ---
		$trajets_inserted = 0;
		
		foreach($rows_data as $row_item) {
			if (!isset($row_item['r'])) continue;
			
			foreach($row_item['r'] as $trajet) {
				// Extraire données
				$vehicule = str_replace(" ", "", str_replace("-", "", str_replace("/", "", $trajet['c']['1'] ?? '')));
				$parcour = $trajet['c']['2'] ?? '';
				$depart = $trajet['c']['3'] ?? '';
				$vers = $trajet['c']['4'] ?? '';
				$debut = $trajet['t1'] ?? 0;
				$fin = $trajet['t2'] ?? 0;
				$penalit = $trajet['c']['8'] ?? 0;
				$km = (float)str_replace("km", "", $trajet['c']['9'] ?? 0);
				
				// Insérer
				$result = set_trajet($transporteur_id, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur_defaut);
				
				if ($result) {
					$trajets_inserted++;
					$trajectcount++;
					logSuccess("Trajet inséré: $vehicule ($parcour)");
				} else {
					logWarning("Trajet NON inséré: $vehicule (doublon ou erreur)");
				}
			}
		}
		
		$trajets_groupe += $trajets_inserted;
		logInfo("Trajets insérés de cette table: $trajets_inserted");
	}
	
	$stats[$nom_groupe] = array('tables' => $num_tables, 'trajets' => $trajets_groupe);
	logSuccess("Total groupe $nom_groupe: $trajets_groupe trajets");
}

echo '</div>';

// ========================================
// 📊 STEP 3: RÉSUMÉ FINAL
// ========================================

echo '<div class="step"><span class="step-number">3</span><strong>Résumé Final</strong></div>';

echo '<div class="summary">';
echo '<h2>✅ Récupération Complétée!</h2>';
echo '<table>';
echo '<tr><th>Groupe</th><th>Tables</th><th>Trajets Insérés</th></tr>';

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
echo '<p><strong>🚗 Total Trajets Insérés:</strong> <span style="font-size: 1.5em; color: #27ae60;">' . $total_trajets . '</span></p>';
echo '</div>';

// --- ERREURS SI EXISTE ---
if (!empty($groupe_errors)) {
	echo '<div class="error-section">';
	echo '<h3>❌ Erreurs Rencontrées:</h3>';
	foreach($groupe_errors as $groupe => $error) {
		echo '<p><strong>' . htmlspecialchars($groupe) . ':</strong> ' . htmlspecialchars($error) . '</p>';
	}
	echo '</div>';
}

// --- FIN ---
echo '<p><strong>Fin:</strong> <span style="color: #e74c3c;">' . date('Y-m-d H:i:s') . '</span></p>';
echo '</div></div></body></html>';

?>