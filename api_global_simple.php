<?php
/**
 * ========================================
 * API GLOBAL SIMPLE - VERSION FIXÉE
 * ========================================
 */

require_once "lesgets.php";

set_time_limit(600);

// Configuration
define('RESOURCE_ID', 19907460); // Resource qui marche (3 tables retournées)
define('TEMPLATE_KM', 4);
define('TEMPLATE_INFRA', 2);
define('TEMPLATE_EVAL', 7);

// Tous les groups (Group IDs confirmés)
$tab_group = array(
	'BOUTCHRAFINE' => 12173650,
	'MARATRANS' => 19631505,
	'COTRAMAB' => 19585601,
	'CORYAD' => 19585581,
	'CHOUROUK' => 19630023,
	'FASTTRANS' => 19635796
);

// ========================================
// EXEC REPORT
// ========================================
function execGlobalReport($templateId, $groupId, $sid) {
	$to = time();
	$from = $to - (7 * 86400);

	$curl = curl_init();
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":' . RESOURCE_ID . ',"reportTemplateId":' . $templateId . ',"reportObjectId":' . $groupId . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
	));

	$response = curl_exec($curl);
	curl_close($curl);

	$data = json_decode($response, true);

	if (isset($data['reportResult']['tables'])) {
		return $data['reportResult']['tables'];
	}

	return null;
}

// ========================================
// SELECT ROWS
// ========================================
function getReportRows($tableIndex, $rowCount, $sid) {
	$curl = curl_init();
	$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":' . $tableIndex . ',"config":{"type":"range","data":{"from":0,"to":' . $rowCount . ',"level":2}}}&sid=' . $sid;

	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
	));

	$response = curl_exec($curl);
	curl_close($curl);

	return json_decode($response, true);
}

// ========================================
// INSERT FUNCTIONS
// ========================================
function insertGlobalKM($nom, $veh, $deb, $fin, $duree, $km) {
	$db = Cnx();
	$veh = trim(str_replace(['-', '/', ' '], '', $veh));
	$deb_dt = is_numeric($deb) && $deb > 1000000000 ? date('Y-m-d H:i:s', $deb) : date('Y-m-d H:i:s');
	$fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');
	$km = (float) preg_replace('/[^0-9.]/', '', $km);

	// Get transporteur_id
	$transport_ids = ['BOUTCHRAFINE'=>1, 'MARATRANS'=>3, 'COTRAMAB'=>6, 'CORYAD'=>7, 'CHOUROUK'=>9, 'FASTTRANS'=>12];
	$tid = $transport_ids[$nom] ?? 1;

	$check = $db->prepare("SELECT COUNT(*) FROM global_kilometrage WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=?");
	$check->execute([$tid, $veh, $deb_dt, $fin_dt]);
	if ($check->fetchColumn() > 0) return false;

	$stmt = $db->prepare("INSERT INTO global_kilometrage (transporteur_id, transporteur_nom, vehicule, debut, fin, duree, kilometrage) VALUES (?, ?, ?, ?, ?, ?, ?)");
	return $stmt->execute([$tid, $nom, $veh, $deb_dt, $fin_dt, $duree, $km]);
}

function insertGlobalInfra($nom, $veh, $deb, $fin, $emp, $inf) {
	$db = Cnx();
	$veh = trim(str_replace(['-', '/', ' '], '', $veh));
	$deb_dt = is_numeric($deb) && $deb > 1000000000 ? date('Y-m-d H:i:s', $deb) : date('Y-m-d H:i:s');
	$fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');

	if ($inf === '-----' || trim($inf) === '') return false;

	$transport_ids = ['BOUTCHRAFINE'=>1, 'MARATRANS'=>3, 'COTRAMAB'=>6, 'CORYAD'=>7, 'CHOUROUK'=>9, 'FASTTRANS'=>12];
	$tid = $transport_ids[$nom] ?? 1;

	$check = $db->prepare("SELECT COUNT(*) FROM global_infractions WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=? AND infraction=?");
	$check->execute([$tid, $veh, $deb_dt, $fin_dt, $inf]);
	if ($check->fetchColumn() > 0) return false;

	$stmt = $db->prepare("INSERT INTO global_infractions (transporteur_id, transporteur_nom, vehicule, debut, fin, emplacement, infraction) VALUES (?, ?, ?, ?, ?, ?, ?)");
	return $stmt->execute([$tid, $nom, $veh, $deb_dt, $fin_dt, $emp, $inf]);
}

function insertGlobalEval($nom, $veh, $deb, $fin, $emp, $pen, $eval) {
	$db = Cnx();
	$veh = trim(str_replace(['-', '/', ' '], '', $veh));
	$deb_dt = is_numeric($deb) && $deb > 1000000000 ? date('Y-m-d H:i:s', $deb) : date('Y-m-d H:i:s');
	$fin_dt = is_numeric($fin) && $fin > 1000000000 ? date('Y-m-d H:i:s', $fin) : date('Y-m-d H:i:s');
	$pen = (float) preg_replace('/[^0-9.]/', '', $pen);

	$transport_ids = ['BOUTCHRAFINE'=>1, 'MARATRANS'=>3, 'COTRAMAB'=>6, 'CORYAD'=>7, 'CHOUROUK'=>9, 'FASTTRANS'=>12];
	$tid = $transport_ids[$nom] ?? 1;

	$check = $db->prepare("SELECT COUNT(*) FROM global_evaluation WHERE transporteur_id=? AND vehicule=? AND debut=? AND fin=?");
	$check->execute([$tid, $veh, $deb_dt, $fin_dt]);
	if ($check->fetchColumn() > 0) return false;

	$stmt = $db->prepare("INSERT INTO global_evaluation (transporteur_id, transporteur_nom, vehicule, debut, fin, emplacement, penalites, evaluation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
	return $stmt->execute([$tid, $nom, $veh, $deb_dt, $fin_dt, $emp, $pen, $eval]);
}
